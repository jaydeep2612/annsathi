<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\QrSession;
use App\Models\MenuItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\KitchenQueue;
use App\Services\BillingCalculationService;
use App\Services\WorkflowEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class OrderApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $manager;
    protected User $waiter;
    protected RestaurantTable $table;
    protected QrSession $session;
    protected MenuItem $pizza;
    protected MenuItem $coke;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CleanSystemSeeder::class);

        $adminRole = Role::where('name', 'restaurant_admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $waiterRole = Role::where('name', 'waiter')->first();

        $this->restaurant = Restaurant::create([
            'name' => 'Approval Test Bistro',
            'slug' => 'approval-test-bistro-' . uniqid(),
            'business_type' => 'casual_dining',
            'is_active' => true,
        ]);

        $this->restaurant->configuration->update([
            'business_type' => 'casual_dining',
            'workflow_preset' => 'manager_waiter_chef',
            'active_workflow_roles' => ['manager', 'waiter', 'chef'],
            'kitchen_order_dispatch' => true,
            'waiter_serving_dispatch' => true,
            'require_order_approval' => true,
            'waiter_can_take_orders' => true,
            'service_charge_percent' => 5.0,
            'enable_tax' => true,
            'tax_calculation_type' => 'exclusive',
            'tax_components' => [
                ['name' => 'CGST', 'type' => 'CGST', 'rate' => 2.5, 'is_active' => true],
                ['name' => 'SGST', 'type' => 'SGST', 'rate' => 2.5, 'is_active' => true],
            ],
        ]);

        $this->manager = User::create([
            'name' => 'Jane Manager',
            'email' => 'jane.manager@example.com',
            'password' => bcrypt('password'),
            'role_id' => $managerRole->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Bob Waiter',
            'email' => 'bob.waiter@example.com',
            'password' => bcrypt('password'),
            'role_id' => $waiterRole->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Mains',
        ]);

        $this->pizza = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'price' => 300.00,
            'item_type' => 'prepared',
            'is_available' => true,
        ]);

        $this->coke = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Chilled Cola Can',
            'price' => 50.00,
            'item_type' => 'readymade',
            'is_available' => true,
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'table_number' => 'T-01',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->session = QrSession::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'session_token' => 'sess_' . uniqid(),
            'customer_name' => 'Guest Charlie',
            'is_active' => true,
            'is_primary' => true,
            'join_status' => 'approved',
            'expires_at' => now()->addHours(2),
        ]);
    }

    public function test_customer_order_pauses_in_pending_approval_when_toggle_enabled(): void
    {
        $response = $this->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'session_token' => $this->session->session_token,
            'type' => 'table',
            'items' => [
                ['menu_item_id' => $this->pizza->id, 'quantity' => 1],
                ['menu_item_id' => $this->coke->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('order_id') ?? $response->json('order.id');

        $order = Order::with('items')->find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals('pending_approval', $order->status);
        $this->assertNull($order->approved_by);
        $this->assertNull($order->approved_at);

        // When manager approves the order
        app(WorkflowEngineService::class)->handleOrderApproved($order, $this->manager);

        $order->refresh();
        $this->assertEquals($this->manager->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertEquals('in_kitchen', $order->status);

        // Individual item progress: readymade coke is immediately ready, pizza is pending cook
        $pizzaItem = $order->items->firstWhere('menu_item_id', $this->pizza->id);
        $cokeItem = $order->items->firstWhere('menu_item_id', $this->coke->id);

        $this->assertEquals('readymade', $cokeItem->item_type);
        $this->assertEquals('ready', $cokeItem->status);
        $this->assertEquals('prepared', $pizzaItem->item_type);
        $this->assertEquals('pending', $pizzaItem->status);

        // Kitchen Queue has the order placed for the chef
        $queue = KitchenQueue::where('order_id', $order->id)->first();
        $this->assertNotNull($queue);
        $this->assertEquals('placed', $queue->current_status);
    }

    public function test_customer_order_bypasses_approval_when_toggle_disabled(): void
    {
        $this->restaurant->configuration->update(['require_order_approval' => false]);

        $response = $this->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'session_token' => $this->session->session_token,
            'type' => 'table',
            'items' => [
                ['menu_item_id' => $this->pizza->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('order_id') ?? $response->json('order.id');

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        // Routed directly to kitchen without stopping in pending_approval
        $this->assertEquals('in_kitchen', $order->status);
    }

    public function test_waiter_can_place_order_when_permitted(): void
    {
        Sanctum::actingAs($this->waiter);

        $response = $this->postJson('/api/waiter/orders', [
            'table_id' => $this->table->id,
            'customer_name' => 'VIP Table 1',
            'items' => [
                ['menu_item_id' => $this->pizza->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('order_id') ?? $response->json('order.id');

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals($this->waiter->id, $order->approved_by);
        $this->assertEquals('in_kitchen', $order->status);
    }

    public function test_waiter_is_blocked_from_placing_order_when_disabled(): void
    {
        $this->restaurant->configuration->update(['waiter_can_take_orders' => false]);

        Sanctum::actingAs($this->waiter);

        $response = $this->postJson('/api/waiter/orders', [
            'table_id' => $this->table->id,
            'items' => [
                ['menu_item_id' => $this->pizza->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Waiters are not authorized to place orders for this restaurant. Serving only.',
        ]);
    }

    public function test_billing_calculation_with_service_charge_and_taxes(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'service_type' => 'dine_in',
            'status' => 'placed',
            'payment_status' => 'pending',
            'total_amount' => 400.00,
        ]);

        $order->items()->create([
            'menu_item_id' => $this->pizza->id,
            'item_name' => 'Margherita Pizza',
            'quantity' => 1,
            'confirmed_qty' => 1,
            'unit_price' => 300.00,
            'total_price' => 300.00,
        ]);

        $order->items()->create([
            'menu_item_id' => $this->coke->id,
            'item_name' => 'Chilled Cola Can',
            'quantity' => 2,
            'confirmed_qty' => 2,
            'unit_price' => 50.00,
            'total_price' => 100.00,
        ]);

        $billingService = app(BillingCalculationService::class);
        $calculation = $billingService->calculate($order);

        // Subtotal = 300 + 100 = 400
        $this->assertEquals(400.00, $calculation['subtotal']);

        // Service charge = 5% of 400 = 20
        $this->assertEquals(20.00, $calculation['service_charge']);

        // Taxable base = 400. CGST 2.5% = 10.00, SGST 2.5% = 10.00. Total tax = 20.00
        $this->assertEquals(20.00, $calculation['total_tax']);

        // Grand total = 400 + 20 (tax) + 20 (service charge) = 440.00
        $this->assertEquals(440.00, $calculation['grand_total']);
    }
}
