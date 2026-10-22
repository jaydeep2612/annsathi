<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Traits\Auditable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens, Auditable;

    protected $fillable = [
        'restaurant_id',
        'role_id',
        'name',
        'email',
        'is_super_admin',
        'password',
        'is_active',
        'total_served',
        'monthly_salary',
        'shift_hours',
    ];

    protected $casts = [
        'is_super_admin' => 'boolean',
        'is_active'      => 'boolean',
        'monthly_salary' => 'decimal:2',
        'shift_hours'    => 'decimal:1',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /* ---------------------------------------------------
     | FILAMENT PANEL ACCESS
     |---------------------------------------------------*/
    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return $this->is_super_admin
            || $this->isOwner()
            || $this->isRestaurantAdmin()
            || $this->isManager()
            || $this->role?->name === 'cashier'
            || $this->role?->name === 'captain'
            || $this->role?->name === 'waiter'
            || $this->role?->name === 'chef';
    }

    /* ---------------------------------------------------
     | RELATIONSHIPS
     |---------------------------------------------------*/
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function ownedRestaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }

    public function resourcePermissions(): HasMany
    {
        return $this->hasMany(UserResourcePermission::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /* ---------------------------------------------------
     | STRICT PER-USER RESOURCE PERMISSION CHECKER
     |---------------------------------------------------*/
    public function hasResourcePermission(string $resource, string $action): bool
    {
        // 1. Super Admin has unrestricted access to everything
        if ($this->is_super_admin) {
            return true;
        }

        // 2. Owner has unrestricted access across all their owned restaurants
        if ($this->isOwner()) {
            return true;
        }

        // 3. User-specific granular permission override
        $perm = $this->resourcePermissions->where('resource_name', $resource)->first();
        if ($perm) {
            return match ($action) {
                'viewAny' => (bool) $perm->can_view_any,
                'view'    => (bool) $perm->can_view,
                'create'  => (bool) $perm->can_create,
                'update'  => (bool) $perm->can_update,
                'delete'  => (bool) $perm->can_delete,
                default   => false,
            };
        }

        // 4. Fallback defaults by role if no individual record exists
        $roleName = $this->role?->name;
        if (in_array($roleName, ['restaurant_admin', 'manager'])) {
            return true; // Managers & Admins have standard access unless specifically restricted
        }

        return false;
    }

    /* ---------------------------------------------------
     | ROLE HELPERS
     |---------------------------------------------------*/
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isOwner(): bool
    {
        return $this->role?->name === 'owner';
    }

    public function isRestaurantAdmin(): bool
    {
        return $this->role?->name === 'restaurant_admin';
    }

    public function isBranchAdmin(): bool
    {
        return false;
    }

    public function isManager(): bool
    {
        return $this->role?->name === 'manager';
    }
}