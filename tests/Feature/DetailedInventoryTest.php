<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Restaurant;
use App\Models\MeasurementUnit;
use App\Models\GroceryItem;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InventoryTransaction;
use App\Services\InventoryService;

class DetailedInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_service_deducts_and_restores_stock_correctly()
    {
        // 1. Create a restaurant with detailed inventory enabled
        $restaurant = Restaurant::create([
            'name' => 'Pizza Palace',
            'slug' => 'pizza-palace',
            'has_detailed_inventory' => true,
        ]);

        // 2. Create measurement unit
        $unit = MeasurementUnit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

        // 3. Create grocery item (raw material)
        $cheese = GroceryItem::create([
            'restaurant_id' => $restaurant->id,
            'measurement_unit_id' => $unit->id,
            'name' => 'Cheese',
            'current_stock' => 1000.0,
            'low_stock_threshold' => 100.0,
        ]);

        // 4. Create category & menu item
        $category = \App\Models\Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Pizzas',
        ]);

        $pizza = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Cheese Pizza',
            'price' => 12.99,
        ]);

        // 5. Create Recipe: 1 Cheese Pizza needs 150g cheese
        Recipe::create([
            'restaurant_id' => $restaurant->id,
            'menu_item_id' => $pizza->id,
            'grocery_item_id' => $cheese->id,
            'quantity_required' => 150.0,
            'measurement_unit_id' => $unit->id,
        ]);

        // 6. Place an Order for 2 Cheese Pizzas
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'total_amount' => 25.98,
            'status' => 'pending',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $pizza->id,
            'quantity' => 2,
            'price' => 12.99,
        ]);

        // Verify pre-check
        $availabilityWarnings = InventoryService::checkAvailability($order);
        $this->assertEmpty($availabilityWarnings);

        // 7. Deduct stock for the order
        $warnings = InventoryService::deductForOrder($order);
        $this->assertEmpty($warnings);

        // Check that stock was reduced: 1000 - (150 * 2) = 700
        $cheese->refresh();
        $this->assertEquals(700.0, $cheese->current_stock);

        // Verify transaction logged
        $this->assertDatabaseHas('inventory_transactions', [
            'restaurant_id' => $restaurant->id,
            'grocery_item_id' => $cheese->id,
            'type' => 'order_fulfillment',
            'quantity' => 300.0,
        ]);

        // 8. Restore stock for the order (e.g. on cancellation)
        InventoryService::restoreForOrder($order);

        $cheese->refresh();
        $this->assertEquals(1000.0, $cheese->current_stock);

        // Verify restore transaction logged
        $this->assertDatabaseHas('inventory_transactions', [
            'restaurant_id' => $restaurant->id,
            'grocery_item_id' => $cheese->id,
            'type' => 'order_cancellation',
            'quantity' => 300.0,
        ]);
    }
}
