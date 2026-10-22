<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\QrSession;
use App\Models\MenuItem;
use App\Models\Category;
use App\Models\User;
use App\Models\Role;
use App\Services\WorkflowEngineService;
use Database\Seeders\CleanSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealWorldOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CleanSystemSeeder::class);
    }

    public function test_full_dining_order_lifecycle()
    {
        // 1. Casual Dining Restaurant setup
        $restaurant = Restaurant::create([
            'name' => 'Royal Dine',
            'slug' => 'royal-dine',
        ]);
        $restaurant->configuration->update([
            'business_type' => 'casual_dining',
            'workflow_preset' => 'manager_waiter_chef',
            'active_workflow_roles' => ['manager', 'waiter', 'chef'],
            'kitchen_order_dispatch' => true,
            'waiter_serving_dispatch' => true,
            'is_pay_first' => false,
        ]);

        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'table_number' => 'T1',
            'capacity' => 4,
        ]);

        $session = QrSession::create([
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $table->id,
            'customer_name' => 'John Doe',
            'session_token' => 'session-123',
            'is_primary' => true,
            'join_status' => 'active',
            'is_active' => true,
            'expires_at' => now()->addHours(3),
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $table->id,
            'qr_session_id' => $session->id,
            'customer_name' => 'John Doe',
            'service_type' => 'dine_in',
            'status' => 'placed',
            'payment_status' => 'unpaid',
            'total_amount' => 500.00,
        ]);

        $workflow = app(WorkflowEngineService::class);

        // Step 1: Order Placed -> goes to in_kitchen (KOT)
        $workflow->handleOrderPlaced($order);
        $order->refresh();
        $this->assertEquals('in_kitchen', $order->status);
        $this->assertDatabaseHas('kitchen_queue', ['order_id' => $order->id]);

        // Step 2: Chef starts cooking -> preparing
        $workflow->handleStartCooking($order);
        $order->refresh();
        $this->assertEquals('preparing', $order->status);

        // Step 3: Chef marks ready -> waiter_assigned (since waiters are active)
        $workflow->handleFoodReady($order);
        $order->refresh();
        $this->assertEquals('waiter_assigned', $order->status);

        // Step 4: Waiter delivers food -> served
        $order->update(['status' => 'served']);
        $order->refresh();
        $this->assertEquals('served', $order->status);

        // Step 5: Cashier settles payment -> completed
        $workflow->handlePaymentSettled($order);
        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_qsr_pay_first_order_lifecycle()
    {
        // Setup QSR outlet with pay-first & daily token
        $restaurant = Restaurant::create([
            'name' => 'Burger Station QSR',
            'slug' => 'burger-station',
        ]);
        $restaurant->configuration->update([
            'business_type' => 'quick_service',
            'is_pay_first' => true,
            'token_system_enabled' => true,
            'kitchen_order_dispatch' => true,
            'waiter_serving_dispatch' => false, // Self-pickup
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'customer_name' => 'Alice',
            'service_type' => 'parcel',
            'status' => 'placed',
            'payment_status' => 'unpaid',
            'total_amount' => 250.00,
        ]);

        $workflow = app(WorkflowEngineService::class);

        // Step 1: Placed -> Should be 'pending' awaiting payment + daily token assigned
        $workflow->handleOrderPlaced($order);
        $order->refresh();
        $this->assertEquals('pending', $order->status);
        $this->assertNotEmpty($order->token_number);
        $this->assertEquals('#01', $order->token_number);

        // Step 2: Customer pays online or at counter -> settles and automatically moves to in_kitchen
        $workflow->handlePaymentSettled($order);
        $order->refresh();
        $this->assertEquals('in_kitchen', $order->status);
        $this->assertEquals('paid', $order->payment_status);

        // Step 3: Kitchen marks ready -> since waiter is bypassed, moves directly to ready_for_pickup
        $workflow->handleFoodReady($order);
        $order->refresh();
        $this->assertEquals('completed', $order->status); // auto_completed because already paid
    }
}
