<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\RestaurantTable;
use App\Models\ParcelQrCode; // 👈 ADDED for Parcel Support
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Events\OrderStatusUpdated;

class WaiterAppController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->with('role')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if (!in_array($user->role->name, ['waiter', 'manager', 'restaurant_admin'])) {
            throw ValidationException::withMessages(['email' => ['Unauthorized access.']]);
        }

        $token = $user->createToken('waiter-app')->plainTextToken;
        $waiterCanTakeOrders = (bool) ($user->restaurant?->configuration?->waiter_can_take_orders ?? true);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name,
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => null,
                'total_served' => $user->total_served ?? 0,
                'waiter_can_take_orders' => $waiterCanTakeOrders,
            ]
        ]);
    }

    public function getProfile(Request $request)
    {
        $user = $request->user();
        $waiterCanTakeOrders = (bool) ($user->restaurant?->configuration?->waiter_can_take_orders ?? true);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->name,
            'restaurant_id' => $user->restaurant_id,
            'total_served' => $user->total_served ?? 0,
            'waiter_can_take_orders' => $waiterCanTakeOrders,
        ]);
    }

    public function markAsServed(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('restaurant_id', $user->restaurant_id)
            ->where('id', $id)
            ->firstOrFail();

        if (!in_array($order->status, ['ready', 'waiter_assigned'])) {
            return response()->json(['message' => 'Order is not ready for serving.'], 400);
        }

        DB::transaction(function () use ($order, $user) {
            $order->update(['status' => 'served']);
            \App\Models\KitchenQueue::where('order_id', $order->id)->delete();

            $user->increment('total_served');

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => 'ready',
                'to_status' => 'served',
                'changed_by' => $user->id,
            ]);
            
            ActivityLog::create([
                'actor_type' => 'staff',
                'actor_id' => $user->id,
                'action' => 'marked_served',
                'entity_type' => Order::class,
                'entity_id' => $order->id,
                'metadata' => [
                    'table_id' => $order->restaurant_table_id,
                    'room_session_id' => $order->room_session_id,
                    'parcel_session_id' => $order->parcel_qr_session_id, // 👈 Added Parcel Tracking
                ]
            ]);
        });

        event(new OrderStatusUpdated($order));

        return response()->json([
            'message' => 'Order marked as served successfully.',
            'total_served' => $user->total_served
        ]);
    }

    public function getReadyOrders(Request $request)
    {
        $user = $request->user();
        
        // 👇 FIX 1: Eager load parcel session and its code
        $orders = Order::with(['items.menuItem', 'table', 'session', 'roomSession.room', 'parcelQrSession.parcelQrCode'])
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', ['pending', 'placed', 'in_kitchen', 'preparing', 'ready', 'waiter_assigned'])
            ->orderBy('updated_at', 'asc')
            ->get()
            ->map(function ($order) {
                
                // 👇 FIX 2: Dynamic display string calculations & service_type passing
                $displayNumber = 'Unknown Location';
                $serviceType = $order->service_type ?? 'dine_in';
                
                if ($serviceType === 'parcel' || $order->parcel_qr_session_id) {
                    $counterName = $order->parcelQrSession->parcelQrCode->name ?? 'Main Counter';
                    $displayNumber = $counterName;
                    $serviceType = 'parcel';
                } elseif ($serviceType === 'room_service' || $order->room_session_id) {
                    $displayNumber = $order->roomSession->room->room_number ?? '?';
                    $serviceType = 'room_service';
                } elseif ($order->restaurant_table_id) {
                    $displayNumber = $order->table ? ($order->table->number ?? $order->table->table_number) : '?';
                }

                // Inject service_type so Waiter app knows how to style the badge
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'updated_at' => $order->updated_at,
                    'items' => $order->items,
                    'service_type' => $serviceType, // 👈 CRITICAL FOR FRONTEND
                    'room_session_id' => $order->room_session_id,
                    'parcel_session_id' => $order->parcel_qr_session_id,
                    'table_number' => $displayNumber, 
                    'customer_name' => $order->customer_name ?? 'Guest',
                    'total_items' => $order->items->sum('quantity'),
                    'notes' => $order->notes,
                ];
            });

        return response()->json($orders);
    }

    public function getTables(Request $request)
    {
        $user = $request->user();
        
        // 1. Fetch Normal Tables
        $tables = RestaurantTable::where('restaurant_id', $user->restaurant_id)
            ->get()
            ->map(function ($table) {
                return [
                    'id' => 'table_' . $table->id, // Prevent key collision
                    'raw_id' => $table->id,
                    'number' => $table->number ?? $table->table_number,
                    'status' => $table->status ?? 'available',
                    'capacity' => $table->seating_capacity ?? 4,
                    'type' => 'table'
                ];
            });
        
        // 2. Fetch Parcel Counters so Waiters see them in the grid too
        $parcelCounters = ParcelQrCode::where('restaurant_id', $user->restaurant_id)
            ->where('is_active', true)
            ->get()
            ->map(function ($counter) {
                return [
                    'id' => 'parcel_' . $counter->id,
                    'raw_id' => $counter->id,
                    'number' => '🛍️ ' . $counter->name, // Displays nicely for waiter
                    'status' => 'parcel', // Special status
                    'capacity' => 'Queue',
                    'type' => 'parcel'
                ];
            });

        // Merge both together
        return response()->json($parcelCounters->merge($tables));
    }

    public function updateTableStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string|in:available,occupied,cleaning']);
        $user = $request->user();
        
        // Safety check to ensure we only update real tables, not parcel counters
        $table = RestaurantTable::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        
        $oldStatus = $table->status;
        $table->update(['status' => $request->status]);
        
        ActivityLog::create([
            'actor_type' => 'staff',
            'actor_id' => $user->id,
            'action' => 'updated_table_status',
            'entity_type' => RestaurantTable::class,
            'entity_id' => $table->id,
            'metadata' => [
                'from_status' => $oldStatus,
                'to_status' => $request->status,
            ]
        ]);
        
        event(new \App\Events\TableStatusUpdated($table->id, $table->status, $table->restaurant_id));
        return response()->json(['message' => 'Table status updated', 'table' => $table]);
    }

    public function placeOrder(Request $request)
    {
        $user = $request->user();
        $config = $user->restaurant?->configuration;

        if ($config && !$config->waiter_can_take_orders && $user->role->name === 'waiter') {
            return response()->json([
                'message' => 'Waiters are not authorized to place orders for this restaurant. Serving only.',
            ], 403);
        }

        $validated = $request->validate([
            'table_id' => 'nullable|exists:restaurant_tables,id',
            'room_id' => 'nullable|exists:rooms,id',
            'parcel_session_id' => 'nullable|exists:parcel_qr_sessions,id',
            'customer_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        $subtotal = 0;
        $orderItems = [];
        foreach ($validated['items'] as $item) {
            $menuItem = \App\Models\MenuItem::where('id', $item['menu_item_id'])
                ->where('restaurant_id', $user->restaurant_id)
                ->firstOrFail();

            $lineTotal = $menuItem->price * $item['quantity'];
            $subtotal += $lineTotal;
            $orderItems[] = [
                'menu_item_id' => $menuItem->id,
                'item_name' => $menuItem->name,
                'item_type' => $menuItem->item_type ?? 'prepared',
                'unit_price' => $menuItem->price,
                'quantity' => $item['quantity'],
                'confirmed_qty' => $item['quantity'],
                'total_price' => $lineTotal,
                'notes' => $item['notes'] ?? null,
            ];
        }

        $serviceType = 'dine_in';
        if (!empty($validated['room_id'])) $serviceType = 'room_service';
        elseif (!empty($validated['parcel_session_id'])) $serviceType = 'parcel';

        $order = DB::transaction(function () use ($user, $validated, $serviceType, $subtotal, $orderItems) {
            $order = Order::create([
                'restaurant_id' => $user->restaurant_id,
                'service_type' => $serviceType,
                'restaurant_table_id' => $validated['table_id'] ?? null,
                'room_session_id' => $validated['room_id'] ?? null,
                'parcel_qr_session_id' => $validated['parcel_session_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? 'Guest (Taken by ' . $user->name . ')',
                'status' => 'placed',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'confirmed_total' => $subtotal,
                'notes' => $validated['notes'] ?? null,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            foreach ($orderItems as $itemData) {
                $order->items()->create($itemData);
            }

            // Staff-submitted orders bypass customer approval and dispatch directly to kitchen/prep
            app(\App\Services\WorkflowEngineService::class)->handleOrderSubmitted($order, isCustomerSubmitted: false);

            ActivityLog::create([
                'restaurant_id' => $user->restaurant_id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role->name,
                'action' => 'waiter_placed_order',
                'model_type' => Order::class,
                'model_id' => $order->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'description' => "Staff {$user->name} placed order #{$order->id}",
            ]);

            return $order;
        });

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order->load('items'),
        ], 201);
    }
}