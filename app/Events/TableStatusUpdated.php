<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\RestaurantTable; // 🔥 Import Model

class TableStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tableId;
    public $status;
    public $restaurantId;

    public function __construct($tableId, $status, $restaurantId)
    {
        $this->tableId = $tableId;
        $this->status = $status;
        $this->restaurantId = $restaurantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->restaurantId . '.alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TableStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'tableId' => $this->tableId,
            'status' => $this->status,
            'restaurantId' => $this->restaurantId,
            'updatedAt' => now()->timestamp,
        ];
    }
}