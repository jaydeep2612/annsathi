<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\KitchenQueue;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Auth;

class WorkflowEngineService
{
    /**
     * Entry point when an order is submitted (checks manager approval toggle).
     */
    public function handleOrderSubmitted(Order $order, bool $isCustomerSubmitted = true): void
    {
        $config = $order->restaurant?->configuration;

        // Check if customer order approval is enabled (default TRUE)
        $requiresApproval = $isCustomerSubmitted && ($config?->require_order_approval ?? true);

        if ($requiresApproval) {
            $this->transition($order, 'pending_approval', 'Order submitted by customer, awaiting Manager approval');
            \App\Events\OrderStatusUpdated::dispatch($order->fresh());
            return;
        }

        $this->handleOrderPlaced($order);
    }

    /**
     * Manager or Captain approves the order.
     */
    public function handleOrderApproved(Order $order, ?\App\Models\User $approvedBy = null): void
    {
        $order->update([
            'approved_by' => $approvedBy?->id ?? Auth::id(),
            'approved_at' => now(),
        ]);

        $this->handleOrderPlaced($order);
    }

    /**
     * Advance order status and item-by-item status on initial placement.
     */
    public function handleOrderPlaced(Order $order): void
    {
        $config = $order->restaurant?->configuration;

        // 1. Assign Daily Token Number if enabled and not already assigned
        if ($config && $config->token_system_enabled && empty($order->token_number)) {
            $today = now()->toDateString();
            $maxTokenToday = Order::where('restaurant_id', $order->restaurant_id)
                ->whereDate('created_at', $today)
                ->whereNotNull('token_number')
                ->lockForUpdate()
                ->count();

            $tokenStr = '#' . str_pad($maxTokenToday + 1, 2, '0', STR_PAD_LEFT);
            $order->update(['token_number' => $tokenStr]);
        }

        // 2. Classify individual items: readymade vs prepared
        $order->loadMissing('items.menuItem');
        $itemCount = $order->items->count();
        $hasPreparedItems = false;
        $allReadymade = $itemCount > 0; // Must have items and ALL of them be readymade

        foreach ($order->items as $item) {
            $itemType = $item->menuItem?->item_type ?? $item->item_type ?? 'prepared';
            $item->update(['item_type' => $itemType]);

            if ($itemType === 'readymade') {
                // Readymade packaged items mark ready immediately (no kitchen cooking needed)
                $item->update([
                    'status'      => 'ready',
                    'prepared_at' => now(),
                ]);
            } else {
                $hasPreparedItems = true;
                $allReadymade = false;
                $item->update([
                    'status' => 'pending',
                ]);
            }
        }

        // 3. In Pay-First model, order waits for customer payment before proceeding to preparation
        if ($config && $config->is_pay_first && $order->payment_status !== 'paid') {
            $this->transition($order, 'pending', 'Awaiting customer payment before kitchen dispatch (Pay-First model)');
            return;
        }

        // 4. Check Kitchen / Chef Dispatch or All-Readymade Flow
        if (($config && $config->shouldBypassKitchen()) || $allReadymade) {
            // If kitchen is bypassed or every item in the order is readymade, whole order is ready for pickup
            foreach ($order->items as $item) {
                if ($item->status !== 'ready') {
                    $item->update(['status' => 'ready', 'prepared_at' => now()]);
                }
            }
            $this->transition($order, 'ready_for_pickup', 'Preparation bypassed (Readymade or Direct flow)');
            $this->handleFoodReady($order);
        } else {
            $this->transition($order, 'in_kitchen', 'Sent to Kitchen Display / Chef Queue');

            // Ensure kitchen queue entry exists for the order
            if (!$order->kitchenQueue()->exists()) {
                KitchenQueue::create([
                    'order_id'       => $order->id,
                    'status'         => 'pending',
                    'current_status' => 'placed',
                ]);
            }
        }
    }

    /**
     * Mark an individual order item as cooking / preparing.
     */
    public function handleStartCookingItem(OrderItem $item): void
    {
        $item->update(['status' => 'cooking']);
        $order = $item->order;

        if ($order) {
            if ($order->kitchenQueue && $order->kitchenQueue->status === 'pending') {
                $order->kitchenQueue->update(['status' => 'preparing']);
            }
            if ($order->status !== 'preparing') {
                $this->transition($order, 'preparing', "Chef started preparing: {$item->item_name}");
            }
            \App\Events\OrderStatusUpdated::dispatch($order->fresh());
        }
    }

    /**
     * Mark an individual order item as ready.
     */
    public function handleItemReady(OrderItem $item): void
    {
        $item->update([
            'status'      => 'ready',
            'prepared_at' => now(),
        ]);

        $order = $item->order;
        if (!$order) return;

        // Check if all items in this order are now ready
        $remainingPending = $order->items()->whereNotIn('status', ['ready', 'served', 'cancelled'])->count();

        if ($remainingPending === 0) {
            // All items ready -> advance whole order
            $this->handleFoodReady($order);
        } else {
            \App\Events\OrderStatusUpdated::dispatch($order->fresh());
        }
    }

    /**
     * Mark an individual item as served to customer table/room.
     */
    public function handleItemServed(OrderItem $item): void
    {
        $item->update([
            'status'    => 'served',
            'served_at' => now(),
        ]);

        $order = $item->order;
        if (!$order) return;

        $unservedCount = $order->items()->whereNotIn('status', ['served', 'cancelled'])->count();
        if ($unservedCount === 0 && $order->status !== 'completed') {
            $this->transition($order, 'served', 'All items delivered and served');
            $config = $order->restaurant?->configuration;
            if ($config && $config->auto_complete_on_payment && $order->payment_status === 'paid') {
                $this->transition($order, 'completed', 'Order auto-completed and fulfilled');
            }
        }

        \App\Events\OrderStatusUpdated::dispatch($order->fresh());
    }

    /**
     * Mark all prepared items in kitchen as preparing.
     */
    public function handleStartCooking(Order $order): void
    {
        if ($order->kitchenQueue) {
            $order->kitchenQueue->update(['status' => 'preparing']);
        }

        // Advance all pending prepared items to cooking
        $order->items()->where('item_type', 'prepared')->where('status', 'pending')->update(['status' => 'cooking']);

        $this->transition($order, 'preparing', 'Chef started preparing order');
        \App\Events\OrderStatusUpdated::dispatch($order->fresh());
    }

    /**
     * Advance order when food is ready.
     */
    public function handleFoodReady(Order $order): void
    {
        $config = $order->restaurant?->configuration;

        // If kitchen queue exists, mark it as ready
        if ($order->kitchenQueue) {
            $order->kitchenQueue->update([
                'status'       => 'ready',
                'completed_at' => now(),
            ]);
        }

        // Ensure all items are marked ready
        $order->items()->whereNotIn('status', ['served', 'cancelled'])->update([
            'status'      => 'ready',
            'prepared_at' => now(),
        ]);

        // Check Waiter Dispatch
        if ($config && $config->shouldBypassWaiter()) {
            // Direct to counter / self-pickup
            $this->transition($order, 'served', 'Waiter bypassed (Self-pickup / Counter served)');

            // Auto complete if already paid
            if ($config->auto_complete_on_payment && $order->payment_status === 'paid') {
                $this->transition($order, 'completed', 'Order auto-completed and fulfilled');
            }
        } else {
            $this->transition($order, 'waiter_assigned', 'Ready for waiter pickup and table delivery');
        }
    }

    /**
     * Handle payment settlement event.
     */
    public function handlePaymentSettled(Order $order): void
    {
        $config = $order->restaurant?->configuration;
        $order->update(['payment_status' => 'paid']);

        // If order was waiting in Pay-First mode, now dispatch to kitchen or ready
        if ($order->status === 'pending') {
            $this->handleOrderPlaced($order);
            return;
        }

        // If order is already served or ready for pickup, auto-complete
        if ($config && $config->auto_complete_on_payment && in_array($order->status, ['ready_for_pickup', 'served'])) {
            $this->transition($order, 'completed', 'Order auto-completed upon payment settlement');
        }
    }

    /**
     * Self-Healing Workflow: Re-evaluate active orders when staff roles change mid-service
     * (e.g. Waiters go on leave or Chef is toggled off).
     */
    public function reevaluateActiveOrders(Restaurant $restaurant): int
    {
        $config = $restaurant->configuration;
        if (!$config) return 0;

        $updatedCount = 0;
        $activeOrders = $restaurant->orders()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get();

        foreach ($activeOrders as $order) {
            // If currently waiting for a waiter, but waiter role is now disabled, auto-route to served/counter
            if ($order->status === 'waiter_assigned' && $config->shouldBypassWaiter()) {
                $this->transition($order, 'served', 'Self-healed: Waiters unavailable, auto-routed to counter/served');
                $updatedCount++;
            }

            // If in kitchen, but kitchen role is now disabled, move to ready
            if ($order->status === 'in_kitchen' && $config->shouldBypassKitchen()) {
                $this->transition($order, 'ready_for_pickup', 'Self-healed: Kitchen bypassed, moved to ready');
                $this->handleFoodReady($order);
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Internal transition logger.
     */
    protected function transition(Order $order, string $newStatus, string $note): void
    {
        $from = $order->status;
        $order->update(['status' => $newStatus]);

        OrderStatusLog::create([
            'order_id'    => $order->id,
            'user_id'     => Auth::id() ?? $order->user_id,
            'from_status' => $from,
            'to_status'   => $newStatus,
            'notes'       => $note,
        ]);
    }
}
