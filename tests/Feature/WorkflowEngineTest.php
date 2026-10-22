<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Restaurant;
use App\Models\Order;
use App\Services\WorkflowEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\CleanSystemSeeder;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngineService $workflowService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CleanSystemSeeder::class);
        $this->workflowService = new WorkflowEngineService();
    }

    public function test_cafe_flow_bypasses_kitchen_and_waiter_to_completed_on_payment(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Speedy Cafe',
            'slug' => 'speedy-cafe-' . uniqid(),
        ]);

        // Configure as Cafe / Solo Manager
        $restaurant->configuration->update([
            'business_type'            => 'cafe',
            'workflow_preset'          => 'solo_manager',
            'active_workflow_roles'    => ['manager'],
            'kitchen_order_dispatch'   => false,
            'waiter_serving_dispatch'  => false,
            'auto_complete_on_payment' => true,
            'is_pay_first'             => true,
        ]);

        $order = Order::create([
            'restaurant_id'  => $restaurant->id,
            'status'         => 'pending',
            'payment_status' => 'unpaid',
            'total_amount'   => 150.00,
        ]);

        // In Pay-First model, placed order waits in pending until payment
        $this->workflowService->handleOrderPlaced($order);
        $this->assertEquals('pending', $order->fresh()->status);

        // When payment settles, order bypasses kitchen and waiter directly to completed!
        $this->workflowService->handlePaymentSettled($order);
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    public function test_standard_dining_routes_through_kitchen_and_waiter(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Royal Dining',
            'slug' => 'royal-dining-' . uniqid(),
        ]);

        $restaurant->configuration->update([
            'business_type'            => 'casual_dining',
            'workflow_preset'          => 'manager_waiter_chef',
            'active_workflow_roles'    => ['manager', 'waiter', 'chef'],
            'kitchen_order_dispatch'   => true,
            'waiter_serving_dispatch'  => true,
            'is_pay_first'             => false,
        ]);

        $order = Order::create([
            'restaurant_id'  => $restaurant->id,
            'status'         => 'pending',
            'payment_status' => 'unpaid',
            'total_amount'   => 500.00,
        ]);

        // Order placed goes to in_kitchen and creates kitchen queue
        $this->workflowService->handleOrderPlaced($order);
        $this->assertEquals('in_kitchen', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->kitchenQueue);

        // Chef marks food ready -> dispatches to waiter
        $this->workflowService->handleFoodReady($order);
        $this->assertEquals('waiter_assigned', $order->fresh()->status);
    }

    public function test_self_healing_reevaluates_active_orders_when_waiters_are_disabled(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Adaptive Bistro',
            'slug' => 'adaptive-bistro-' . uniqid(),
        ]);

        $restaurant->configuration->update([
            'active_workflow_roles'   => ['manager', 'waiter', 'chef'],
            'waiter_serving_dispatch' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'status'        => 'waiter_assigned',
            'total_amount'  => 300.00,
        ]);

        // Mid-service scenario: Waiters went on leave!
        // Admin toggles off waiters and disables waiter dispatch
        $restaurant->configuration->update([
            'active_workflow_roles'   => ['manager', 'chef'], // Waiter removed!
            'waiter_serving_dispatch' => false,
        ]);

        // Trigger self-healing
        $healedCount = $this->workflowService->reevaluateActiveOrders($restaurant);

        $this->assertEquals(1, $healedCount);
        // Order automatically rerouted to 'served' (counter pickup) without stalling!
        $this->assertEquals('served', $order->fresh()->status);
    }
}
