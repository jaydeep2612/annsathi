<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResourcePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resource_name',
        'can_view_any',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
    ];

    protected $casts = [
        'can_view_any' => 'boolean',
        'can_view'     => 'boolean',
        'can_create'   => 'boolean',
        'can_update'   => 'boolean',
        'can_delete'   => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
