<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class RestaurantTable extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'restaurant_id',
        'table_number',
        'capacity',
        'seating_capacity',
        'status',
        'qr_code_path',
        'qr_token',
        'qr_path',
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(function ($table) {
            if (!$table->qr_token) {
                $table->qr_token = Str::uuid()->toString();
            }
        });

        static::deleting(function ($table) {
            if ($table->qr_path && Storage::disk('public')->exists($table->qr_path)) {
                Storage::disk('public')->delete($table->qr_path);
            }
            if ($table->qr_code_path && Storage::disk('public')->exists($table->qr_code_path)) {
                Storage::disk('public')->delete($table->qr_code_path);
            }
        });
    }

    public function getCapacityAttribute($value)
    {
        return $value ?? $this->attributes['seating_capacity'] ?? 4;
    }

    public function getSeatingCapacityAttribute($value)
    {
        return $value ?? $this->attributes['capacity'] ?? 4;
    }

    public function setSeatingCapacityAttribute($value)
    {
        $this->attributes['seating_capacity'] = $value;
        $this->attributes['capacity'] = $value;
    }

    public function setCapacityAttribute($value)
    {
        $this->attributes['capacity'] = $value;
        $this->attributes['seating_capacity'] = $value;
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QrSession::class);
    }

    public function qrSessions(): HasMany
    {
        return $this->hasMany(QrSession::class, 'restaurant_table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'restaurant_table_id');
    }
}
