<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentMethodSelected implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $restaurantId;
    public $tableNumber;
    public $method;

    public function __construct($restaurantId, $tableNumber, $method)
    {
        $this->restaurantId = $restaurantId;
        $this->tableNumber = $tableNumber;
        $this->method = $method;
    }

    public function broadcastOn(): array
    {
        // Pushes directly to the Manager's Dashboard
        return [new PrivateChannel('restaurant.' . $this->restaurantId . '.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'PaymentMethodSelected';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'table_number' => $this->tableNumber,
            'method' => $this->method,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}