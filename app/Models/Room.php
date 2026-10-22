<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Room extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'restaurant_id',
        'room_number',
        'room_name',
        'room_type',
        'capacity',
        'max_guests',
        'status',
        'guest_name',
        'check_in_at',
        'check_out_at',
        'active_room_session_id',
        'qr_code_path',
        'qr_path',
        'qr_token',
    ];

    public function getCapacityAttribute($value)
    {
        return $value ?? $this->attributes['max_guests'] ?? 2;
    }

    public function getMaxGuestsAttribute($value)
    {
        return $value ?? $this->attributes['capacity'] ?? 2;
    }

    public function setMaxGuestsAttribute($value)
    {
        $this->attributes['max_guests'] = $value;
        $this->attributes['capacity'] = $value;
    }

    public function setCapacityAttribute($value)
    {
        $this->attributes['capacity'] = $value;
        $this->attributes['max_guests'] = $value;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($room) {
            if (empty($room->qr_token)) {
                $room->qr_token = Str::uuid()->toString();
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function activeSession(): BelongsTo
    {
        return $this->belongsTo(RoomSession::class, 'active_room_session_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(RoomSession::class);
    }
}