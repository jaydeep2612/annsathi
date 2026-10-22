<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StaffAttendance extends Model
{
    use Auditable;

    protected $fillable = [
        'restaurant_id',
        'staff_id',
        'role_id',
        'date',
        'status',
        'overtime_hours',
        'manual_deduction',
        'manual_bonus',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}