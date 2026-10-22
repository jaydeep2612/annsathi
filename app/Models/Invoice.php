<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Exception;
use App\Traits\Auditable;

class Invoice extends Model
{
    use Auditable;

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'qr_session_id',
        'room_session_id',
        'parcel_session_id',
        'payment_id',
        'invoice_sequence',
        'invoice_prefix',
        'invoice_number',
        'bill_number',
        'invoice_date',
        'gstin',
        'place_of_supply',
        'customer_name',
        'subtotal',
        'tax_amount',
        'tax_breakup',
        'discount_amount',
        'service_charge_amount',
        'extra_charges',
        'grand_total',
        'items_snapshot',
        'status',
    ];

    protected $casts = [
        'items_snapshot' => 'array',
        'tax_breakup'    => 'array',
        'invoice_date'   => 'date',
        'subtotal'       => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'discount_amount'=> 'decimal:2',
        'extra_charges'  => 'decimal:2',
        'grand_total'    => 'decimal:2',
    ];

    // 🔒 IMMUTABILITY: Non-Negotiable
    protected static function booted()
    {
        static::updating(function ($invoice) {
            throw new Exception("Compliance Error: Invoice cannot be modified once generated.");
        });

        static::deleting(function ($invoice) {
            throw new Exception("Compliance Error: Invoice cannot be deleted.");
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function qrSession(): BelongsTo
    {
        return $this->belongsTo(QrSession::class, 'qr_session_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function roomSession(): BelongsTo
    {
        return $this->belongsTo(RoomSession::class);
    }

    public function parcelQrSession(): BelongsTo
    {
        return $this->belongsTo(ParcelQrSession::class, 'parcel_session_id');
    }
}