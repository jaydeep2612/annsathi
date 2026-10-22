<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\MenuResource;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Room;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CleanSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedMenuDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CleanSystemSeeder::class);

        $this->restaurant = Restaurant::create([
            'name'      => 'Flavors of India',
            'slug'      => 'flavors-of-india',
            'is_active' => true,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'restaurant_admin'], ['description' => 'Restaurant Admin']);

        $this->adminUser = User::create([
            'restaurant_id' => $this->restaurant->id,
            'role_id'       => $adminRole->id,
            'name'          => 'Admin User',
            'email'         => 'admin@flavors.com',
            'password'      => bcrypt('secret'),
        ]);
    }

    public function test_categories_table_has_sort_order_column_and_can_be_queried_without_error(): void
    {
        $cat1 = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Drinks',
            'slug'          => 'drinks',
            'sort_order'    => 2,
            'is_active'     => true,
        ]);

        $cat2 = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Appetizers',
            'slug'          => 'appetizers',
            'sort_order'    => 1,
            'is_active'     => true,
        ]);

        $categories = Category::where('restaurant_id', $this->restaurant->id)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->pluck('name', 'id')
            ->toArray();

        $this->assertEquals(['Appetizers', 'Drinks'], array_values($categories));
    }

    public function test_category_and_menu_item_resources_are_hidden_from_navigation(): void
    {
        $this->assertFalse(CategoryResource::canAccess());
        $this->assertFalse(MenuItemResource::canAccess());
    }

    public function test_menu_resource_is_accessible_to_restaurant_admin(): void
    {
        $this->actingAs($this->adminUser);
        $this->assertTrue(MenuResource::canAccess());
    }

    public function test_restaurant_table_and_room_creation_with_seating_capacity(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id'    => $this->restaurant->id,
            'table_number'     => '01',
            'seating_capacity' => 5,
        ]);

        $this->assertNotNull($table->id);
        $this->assertEquals(5, $table->seating_capacity);
        $this->assertEquals(5, $table->capacity);

        $room = Room::create([
            'restaurant_id' => $this->restaurant->id,
            'room_number'   => '101',
            'max_guests'    => 3,
        ]);

        $this->assertNotNull($room->id);
        $this->assertEquals(3, $room->max_guests);
        $this->assertEquals(3, $room->capacity);
    }
}
