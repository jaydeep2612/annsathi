<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Payment extends Model
{
    use Auditable;

    protected $fillable = [
        'order_id',
        'restaurant_id',
        'qr_session_id',
        'room_session_id',
        'parcel_qr_session_id',
        'amount',
        'amount_paise',
        'bill_number',
        'payment_method',
        'status',
        'transaction_reference',
        'transaction_id',
        'upi_link',
        'paid_at',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'service_charge_amount',
        'extra_charges',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',
        'gateway_status',
        'expires_at',
        'verified_at',
        'attempts',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $casts = [
        'amount'       => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'extra_charges'=> 'decimal:2',
        'expires_at'   => 'datetime',
        'verified_at'  => 'datetime',
        'paid_at'      => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}