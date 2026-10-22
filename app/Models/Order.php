<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;

class Order extends Model
{
    use Auditable;

    protected $fillable = [
        'restaurant_id',
        'restaurant_table_id',
        'qr_session_id',
        'room_session_id',
        'parcel_qr_session_id',
        'user_id',
        'order_number',
        'token_number',
        'service_type',
        'status',
        'payment_status',
        'customer_name',
        'approved_by',
        'approved_at',
        'notes',
        'subtotal',
        'tax_amount',
        'service_charge_amount',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'subtotal'              => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'total_amount'          => 'decimal:2',
        'approved_at'           => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QrSession::class, 'qr_session_id');
    }

    public function roomSession(): BelongsTo
    {
        return $this->belongsTo(RoomSession::class);
    }

    public function parcelQrSession(): BelongsTo
    {
        return $this->belongsTo(ParcelQrSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function kitchenQueue(): HasOne
    {
        return $this->hasOne(KitchenQueue::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
