<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Traits\Auditable;

class Restaurant extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'logo_path',
        'address',
        'phone_no',
        'gst_no',
        'upi_id',
        'is_active',
        'created_by',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Automatically assign created_by
        static::creating(function (Model $restaurant) {
            if (Auth::check() && !$restaurant->created_by) {
                $restaurant->created_by = Auth::id();
            }
        });

        // Automatically create a default configuration if none exists
        static::created(function (Restaurant $restaurant) {
            if (!$restaurant->configuration()->exists()) {
                $restaurant->configuration()->create([
                    'business_type'            => 'casual_dining',
                    'workflow_preset'          => 'standard',
                    'active_workflow_roles'    => ['manager', 'waiter', 'chef'],
                    'kitchen_order_dispatch'   => true,
                    'waiter_serving_dispatch'  => true,
                    'counter_billing_dispatch' => true,
                    'auto_complete_on_payment' => true,
                    'enabled_service_modes'    => ['dine_in', 'takeaway_parcel'],
                    'table_capacity_limit'     => 20,
                    'user_capacity_limit'      => 10,
                    'enable_tax'               => false,
                    'tax_regime'               => 'cgst_sgst',
                    'tax_calculation_type'     => 'exclusive',
                    'tax_components'           => [
                        ['name' => 'CGST', 'type' => 'CGST', 'rate' => 2.5, 'is_active' => true],
                        ['name' => 'SGST', 'type' => 'SGST', 'rate' => 2.5, 'is_active' => true],
                    ],
                    'print_paper_size'         => 'thermal_80mm',
                    'print_show_logo'          => true,
                    'print_show_upi_qr'        => true,
                ]);
            }
        });

        // Cleanup directory when restaurant is deleted
        static::deleted(function (Model $restaurant) {
            if ($restaurant->slug) {
                Storage::disk('public')->deleteDirectory('restaurants/' . $restaurant->slug);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(RestaurantConfiguration::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function groceryItems(): HasMany
    {
        return $this->hasMany(GroceryItem::class);
    }

    public function measurementUnits(): HasMany
    {
        return $this->hasMany(MeasurementUnit::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function staffAttendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Backward-Compatibility Accessors
    |--------------------------------------------------------------------------
    */
    public function getTableLimitsAttribute(): int
    {
        return (int) ($this->configuration?->table_capacity_limit ?? 20);
    }

    public function getUserLimitsAttribute(): int
    {
        return (int) ($this->configuration?->user_capacity_limit ?? 10);
    }

    public function getHasInventoryAttribute(): bool
    {
        return (bool) ($this->configuration?->has_inventory ?? false);
    }

    public function getHasDetailedInventoryAttribute(): bool
    {
        return (bool) ($this->configuration?->has_detailed_inventory ?? false);
    }

    public function getHasAttendanceAttribute(): bool
    {
        return (bool) ($this->configuration?->has_attendance ?? false);
    }

    public function getIsRoomsFacilityAttribute(): bool
    {
        return (bool) ($this->configuration?->is_rooms_facility ?? false);
    }

    public function getRoomsLimitAttribute(): int
    {
        return (int) ($this->configuration?->rooms_capacity_limit ?? 0);
    }

    public function getIsAllInOneCafeAttribute(): bool
    {
        return (bool) ($this->configuration?->is_all_in_one_dashboard ?? false);
    }

    public function getIsPayFirstAttribute(): bool
    {
        return (bool) ($this->configuration?->is_pay_first ?? false);
    }
}