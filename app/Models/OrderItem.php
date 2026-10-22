<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class OrderItem extends Model
{
    use Auditable;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'item_name',
        'item_type',
        'price',
        'unit_price',
        'quantity',
        'tax_rate',
        'tax_amount',
        'total_price',
        'notes',
        'status',
        'prepared_at',
        'served_at',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'tax_rate'   => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'quantity'   => 'integer',
    ];

    public function getPriceAttribute($val)
    {
        return $val ?? $this->attributes['unit_price'] ?? 0;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
