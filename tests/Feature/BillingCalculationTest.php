<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\BillingCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\CleanSystemSeeder;

class BillingCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected BillingCalculationService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CleanSystemSeeder::class);
        $this->billingService = new BillingCalculationService();
    }

    public function test_exclusive_cgst_and_sgst_tax_calculation(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Tax Test Bistro',
            'slug' => 'tax-test-bistro-' . uniqid(),
        ]);

        $restaurant->configuration->update([
            'enable_tax'           => true,
            'tax_regime'           => 'cgst_sgst',
            'tax_calculation_type' => 'exclusive',
            'tax_level'            => 'order_total',
            'tax_components'       => [
                ['name' => 'CGST', 'type' => 'CGST', 'rate' => 2.5, 'is_active' => true],
                ['name' => 'SGST', 'type' => 'SGST', 'rate' => 2.5, 'is_active' => true],
            ],
            'service_charge_percent' => 5.0,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Main Course',
            'slug'          => 'main-course-' . uniqid(),
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id'   => $category->id,
            'name'          => 'Paneer Butter Masala',
            'slug'          => 'paneer-butter-masala-' . uniqid(),
            'price'         => 200.00,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'status'        => 'pending',
            'total_amount'  => 400.00,
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'menu_item_id' => $item->id,
            'item_name'    => $item->name,
            'price'        => 200.00,
            'quantity'     => 2,
        ]);

        $result = $this->billingService->calculate($order);

        // Subtotal: 200 * 2 = 400
        $this->assertEquals(400.00, $result['subtotal']);
        // CGST: 2.5% of 400 = 10.00
        // SGST: 2.5% of 400 = 10.00
        $this->assertEquals(20.00, $result['total_tax']);
        $this->assertCount(2, $result['tax_breakup']);
        // Service Charge: 5% of 400 = 20.00
        $this->assertEquals(20.00, $result['service_charge']);
        // Grand Total = 400 + 20 (tax) + 20 (service charge) = 440.00
        $this->assertEquals(440.00, $result['grand_total']);
    }

    public function test_inclusive_igst_tax_calculation(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Inclusive IGST Outlet',
            'slug' => 'inclusive-igst-' . uniqid(),
        ]);

        $restaurant->configuration->update([
            'enable_tax'           => true,
            'tax_regime'           => 'igst',
            'tax_calculation_type' => 'inclusive',
            'tax_level'            => 'order_total',
            'tax_components'       => [
                ['name' => 'IGST', 'type' => 'IGST', 'rate' => 5.0, 'is_active' => true],
            ],
            'service_charge_percent' => 0.0,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Beverages',
            'slug'          => 'beverages-' . uniqid(),
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id'   => $category->id,
            'name'          => 'Cappuccino',
            'slug'          => 'cappuccino-' . uniqid(),
            'price'         => 105.00,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'status'        => 'pending',
            'total_amount'  => 105.00,
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'menu_item_id' => $item->id,
            'item_name'    => $item->name,
            'price'        => 105.00,
            'quantity'     => 1,
        ]);

        $result = $this->billingService->calculate($order);

        // Subtotal: 105
        $this->assertEquals(105.00, $result['subtotal']);
        // Back-calculated tax: (105 * 5) / 105 = 5.00
        $this->assertEquals(5.00, $result['total_tax']);
        // Grand total in inclusive mode is equal to subtotal
        $this->assertEquals(105.00, $result['grand_total']);
    }
}
