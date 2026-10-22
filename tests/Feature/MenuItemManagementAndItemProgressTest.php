<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantConfiguration;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Services\MenuImageService;
use App\Services\MenuImportExportService;
use App\Services\WorkflowEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuItemManagementAndItemProgressTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CleanSystemSeeder::class);

        Storage::fake('public');
        Storage::fake('local');

        // Create standard restaurant and configuration
        $this->restaurant = Restaurant::create([
            'name'       => 'Spicy Villa',
            'slug'       => 'spicy-villa',
            'is_active'  => true,
        ]);

        RestaurantConfiguration::create([
            'restaurant_id'              => $this->restaurant->id,
            'workflow_preset'            => 'hybrid_table_service',
            'ordering_enabled'           => true,
            'dining_options'             => ['dine_in'],
            'waiter_role_enabled'        => true,
            'kitchen_role_enabled'       => true,
            'is_pay_first'               => false,
            'token_system_enabled'       => true,
            'auto_complete_on_payment'   => true,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'restaurant_admin'], ['description' => 'Restaurant Admin']);

        $this->adminUser = User::create([
            'restaurant_id' => $this->restaurant->id,
            'role_id'       => $adminRole->id,
            'name'          => 'Villa Manager',
            'email'         => 'manager@spicyvilla.com',
            'password'      => bcrypt('secret123'),
        ]);
    }

    public function test_sample_csv_generation_does_not_contain_image_column(): void
    {
        $csv = MenuImportExportService::generateSampleCsv();

        $this->assertStringContainsString('category_name,item_name,description,price,item_type,is_veg,tax_rate,is_available', $csv);
        $this->assertStringNotContainsString('image', strtolower(explode("\n", $csv)[0]));
        $this->assertStringContainsString('readymade', $csv);
        $this->assertStringContainsString('prepared', $csv);
    }

    public function test_menu_bulk_import_from_csv_assigns_matching_global_images_and_creates_categories(): void
    {
        Storage::disk('public')->put('menu_images/paneer-tikka-masala-red.jpg', 'dummy_paneer_bytes');

        $csvContent = implode("\n", [
            'category_name,item_name,description,price,item_type,is_veg,tax_rate,is_available',
            'Starters,Paneer Tikka,Spicy grilled cottage cheese,250.00,prepared,1,5.0,1',
            'Specials,Exotic Space Salad XYZ,Futuristic salad without photo,199.00,prepared,1,5.0,1',
            'Beverages,Coke Can 330ml,Chilled carbonated soft drink,40.00,readymade,1,12.0,1',
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'csv_import_test');
        file_put_contents($tempPath, $csvContent);

        $result = MenuImportExportService::importMenuFromCsv($this->restaurant, $tempPath);
        unlink($tempPath);

        $this->assertEquals(3, $result['imported_items']);
        $this->assertEquals(3, $result['created_categories']); // Starters, Specials & Beverages
        $this->assertEmpty($result['errors']);

        // 1. Paneer Tikka automatically finds and matches global image!
        $paneerTikka = MenuItem::where('restaurant_id', $this->restaurant->id)->where('name', 'Paneer Tikka')->first();
        $this->assertNotNull($paneerTikka);
        $this->assertEquals('prepared', $paneerTikka->item_type);
        $this->assertEquals('global', $paneerTikka->image_source);
        $this->assertNotNull($paneerTikka->image_path);
        $this->assertStringContainsString('menu_images/paneer', $paneerTikka->image_path);
        $this->assertEquals(250.00, (float) $paneerTikka->price);

        // 2. Unrecognized item has no match and stays blank for later upload
        $exoticSalad = MenuItem::where('restaurant_id', $this->restaurant->id)->where('name', 'Exotic Space Salad XYZ')->first();
        $this->assertNotNull($exoticSalad);
        $this->assertEquals('none', $exoticSalad->image_source);
        $this->assertNull($exoticSalad->image_path);
    }

    public function test_menu_bulk_export_to_csv(): void
    {
        $cat = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Desserts',
            'slug'          => 'desserts',
            'is_active'     => true,
        ]);

        MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Gulab Jamun',
            'slug'          => 'gulab-jamun',
            'price'         => 80.00,
            'item_type'     => 'prepared',
            'is_veg'        => true,
            'is_available'  => true,
            'image_source'  => 'none',
        ]);

        $exportedCsv = MenuImportExportService::exportMenuToCsv($this->restaurant);

        $this->assertStringContainsString('Desserts', $exportedCsv);
        $this->assertStringContainsString('Gulab Jamun', $exportedCsv);
        $this->assertStringContainsString('80.00', $exportedCsv);
        $this->assertStringContainsString('prepared', $exportedCsv);
    }

    public function test_global_image_library_file_is_not_deleted_when_menu_item_is_deleted(): void
    {
        Storage::disk('public')->put('menu_images/shared_burger.jpg', 'fake_image_content');

        $cat = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Burgers',
            'slug'          => 'burgers',
            'is_active'     => true,
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Veggie Burger',
            'slug'          => 'veggie-burger',
            'price'         => 120.00,
            'item_type'     => 'prepared',
            'image_source'  => 'global',
            'image_path'    => 'menu_images/shared_burger.jpg',
            'is_available'  => true,
        ]);

        $item->delete();

        // Shared global image must still exist on disk!
        $this->assertTrue(Storage::disk('public')->exists('menu_images/shared_burger.jpg'));
    }

    public function test_custom_image_is_deleted_when_menu_item_is_deleted(): void
    {
        Storage::disk('public')->put('restaurants/spicy-villa/Categories/burgers/custom_burger.jpg', 'custom_image_content');

        $cat = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Burgers',
            'slug'          => 'burgers',
            'is_active'     => true,
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Custom Burger',
            'slug'          => 'custom-burger',
            'price'         => 140.00,
            'item_type'     => 'prepared',
            'image_source'  => 'custom',
            'image_path'    => 'restaurants/spicy-villa/Categories/burgers/custom_burger.jpg',
            'is_available'  => true,
        ]);

        $item->delete();

        // Custom uploaded image should be deleted
        $this->assertFalse(Storage::disk('public')->exists('restaurants/spicy-villa/Categories/burgers/custom_burger.jpg'));
    }

    public function test_unique_custom_image_is_automatically_contributed_to_global_library(): void
    {
        $uniqueContent = 'unique_delicious_dish_bytes_' . uniqid();
        Storage::disk('public')->put('restaurants/spicy-villa/Categories/specials/dragon-roll.jpg', $uniqueContent);

        $cat = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Specials',
            'slug'          => 'specials-' . uniqid(),
            'is_active'     => true,
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Dragon Sushi Roll',
            'price'         => 350.00,
            'item_type'     => 'prepared',
            'image_source'  => 'custom',
            'image_path'    => 'restaurants/spicy-villa/Categories/specials/dragon-roll.jpg',
            'is_available'  => true,
        ]);

        // It should have contributed a copy to global menu_images named after the item name!
        $this->assertTrue(Storage::disk('public')->exists('menu_images/dragon-sushi-roll.jpg'));

        // If another item uploads the identical image (matching hash), it must NOT create a duplicate
        $contributedAgain = \App\Services\MenuImageService::contributeToGlobalLibrary('restaurants/spicy-villa/Categories/specials/dragon-roll.jpg', 'Dragon Roll Copy');
        $this->assertEquals('menu_images/dragon-sushi-roll.jpg', $contributedAgain);
        $this->assertFalse(Storage::disk('public')->exists('menu_images/dragon-roll-copy.jpg'));

        // Clean up test file from global library
        Storage::disk('public')->delete('menu_images/dragon-sushi-roll.jpg');
    }

    public function test_custom_image_upload_never_stores_temporary_path_and_places_in_restaurant_category_folder(): void
    {
        $cat = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Mocktails',
            'slug'          => 'mocktails',
            'is_active'     => true,
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Blue Lagoon',
            'slug'          => 'blue-lagoon',
            'price'         => 180.00,
            'item_type'     => 'prepared',
            'is_veg'        => true,
            'is_available'  => true,
        ]);

        // Simulate custom uploaded file using UploadedFile fake
        $fakeUpload = \Illuminate\Http\UploadedFile::fake()->image('blue-lagoon.jpg', 400, 400);

        // Run through the exact storage pipeline
        $restaurantSlug = \Illuminate\Support\Str::slug($this->restaurant->name);
        $categorySlug = $cat->slug;
        $itemSlug = \Illuminate\Support\Str::slug($item->name);
        $ext = $fakeUpload->getClientOriginalExtension() ?: 'jpg';
        $dir = "restaurants/{$restaurantSlug}/Categories/{$categorySlug}";
        $filename = "{$itemSlug}.{$ext}";

        $storedPath = Storage::disk('public')->putFileAs($dir, $fakeUpload, $filename);

        $item->update([
            'image_path'   => $storedPath,
            'image_source' => 'custom',
        ]);

        $item->refresh();

        // 1. image_path MUST NOT contain AppData or end with .tmp
        $this->assertStringNotContainsString('AppData', $item->image_path);
        $this->assertStringNotContainsString('.tmp', $item->image_path);

        // 2. image_path MUST match restaurants/{restaurantSlug}/Categories/{categorySlug}/{itemSlug}.jpg
        $expectedPath = "restaurants/spicy-villa/Categories/mocktails/blue-lagoon.jpg";
        $this->assertEquals($expectedPath, $item->image_path);
        $this->assertEquals('custom', $item->image_source);

        // 3. File must actually exist on public disk
        $this->assertTrue(Storage::disk('public')->exists($expectedPath));
    }

    public function test_image_suggestions_empty_when_name_blank_and_matches_culinary_aliases(): void
    {
        // Populate test fake storage with sample library files
        Storage::disk('public')->put('menu_images/cream-of-tomato.jpg', 'img');
        Storage::disk('public')->put('menu_images/manchow.jpg', 'img');
        Storage::disk('public')->put('menu_images/hot-sour.jpg', 'img');
        Storage::disk('public')->put('menu_images/paneer-tikka-masala-red.jpg', 'img');

        // 1. Before typing name (empty or 1 character) -> suggestions MUST be empty!
        $emptySuggestions = MenuImageService::getMatchingSuggestions('');
        $this->assertEmpty($emptySuggestions);

        $singleCharSuggestions = MenuImageService::getMatchingSuggestions('a');
        $this->assertEmpty($singleCharSuggestions);

        // 2. Typing generic culinary category "soup" -> surfaces all relevant soups!
        $soupSuggestions = MenuImageService::getMatchingSuggestions('soup');
        $this->assertNotEmpty($soupSuggestions);
        $soupFiles = array_column($soupSuggestions, 'filename');
        $this->assertContains('cream-of-tomato.jpg', $soupFiles);
        $this->assertContains('manchow.jpg', $soupFiles);
        $this->assertContains('hot-sour.jpg', $soupFiles);
        $this->assertNotContains('paneer-tikka-masala-red.jpg', $soupFiles);

        // 3. Specific query "Tomato Soup" -> Cream of tomato is the top suggestion
        $tomatoSoupSuggestions = MenuImageService::getMatchingSuggestions('Tomato Soup');
        $this->assertNotEmpty($tomatoSoupSuggestions);
        $this->assertEquals('cream-of-tomato.jpg', $tomatoSoupSuggestions[0]['filename']);
    }

    public function test_mixed_order_granular_item_progress_lifecycle(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'table_number'  => 'T-10',
            'capacity'      => 4,
            'status'        => 'occupied',
        ]);

        $cat = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Combos',
            'slug'          => 'combos',
            'is_active'     => true,
        ]);

        $cokeItem = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Packaged Soda',
            'slug'          => 'packaged-soda',
            'price'         => 35.00,
            'item_type'     => 'readymade',
            'is_available'  => true,
        ]);

        $pizzaItem = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $cat->id,
            'name'          => 'Farmhouse Pizza',
            'slug'          => 'farmhouse-pizza',
            'price'         => 350.00,
            'item_type'     => 'prepared',
            'is_available'  => true,
        ]);

        // Place Order with 1 Readymade + 1 Prepared item
        $order = Order::create([
            'restaurant_id'       => $this->restaurant->id,
            'restaurant_table_id' => $table->id,
            'customer_name'       => 'Alice',
            'status'              => 'placed',
            'payment_status'      => 'pending',
            'total_amount'        => 385.00,
        ]);

        $orderSoda = OrderItem::create([
            'order_id'      => $order->id,
            'menu_item_id'  => $cokeItem->id,
            'item_name'     => $cokeItem->name,
            'item_type'     => 'readymade',
            'price'         => 35.00,
            'quantity'      => 1,
            'status'        => 'pending',
        ]);

        $orderPizza = OrderItem::create([
            'order_id'      => $order->id,
            'menu_item_id'  => $pizzaItem->id,
            'item_name'     => $pizzaItem->name,
            'item_type'     => 'prepared',
            'price'         => 350.00,
            'quantity'      => 1,
            'status'        => 'pending',
        ]);

        $workflowService = app(WorkflowEngineService::class);

        // 1. Trigger handleOrderPlaced
        $workflowService->handleOrderPlaced($order);

        $orderSoda->refresh();
        $orderPizza->refresh();
        $order->refresh();

        // Granular check: Readymade item is immediately marked 'ready'
        $this->assertEquals('ready', $orderSoda->status);
        $this->assertNotNull($orderSoda->prepared_at);

        // Prepared item goes into 'pending' kitchen prep
        $this->assertEquals('pending', $orderPizza->status);
        $this->assertNull($orderPizza->prepared_at);

        // Master order is routed to kitchen because of the prepared pizza
        $this->assertEquals('in_kitchen', $order->status);
        $this->assertNotNull($order->kitchenQueue);

        // 2. Chef starts cooking the Pizza
        $workflowService->handleStartCookingItem($orderPizza);
        $orderPizza->refresh();
        $order->refresh();

        $this->assertEquals('cooking', $orderPizza->status);
        $this->assertEquals('preparing', $order->status);

        // 3. Chef marks the Pizza ready
        $workflowService->handleItemReady($orderPizza);
        $orderPizza->refresh();
        $order->refresh();

        $this->assertEquals('ready', $orderPizza->status);
        $this->assertNotNull($orderPizza->prepared_at);

        // Since all items are now ready, order transitions to waiter pickup
        $this->assertEquals('waiter_assigned', $order->status);

        // 4. Waiter delivers items
        $workflowService->handleItemServed($orderSoda);
        $workflowService->handleItemServed($orderPizza);
        $order->refresh();

        $this->assertEquals('served', $order->status);
    }
}
