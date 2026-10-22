<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenQueue extends Model
{
    protected $table = 'kitchen_queue';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'status',
        'current_status',
        'priority',
        'started_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (KitchenQueue $queue) {
            if ($queue->isDirty('status') && !$queue->isDirty('current_status')) {
                $queue->current_status = $queue->status;
            } elseif ($queue->isDirty('current_status') && !$queue->isDirty('status')) {
                $queue->status = $queue->current_status;
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
