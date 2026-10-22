<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class RestaurantConfiguration extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'restaurant_id',
        'business_type',
        'workflow_preset',
        'active_workflow_roles',
        'require_order_approval',
        'waiter_can_take_orders',
        'kitchen_order_dispatch',
        'waiter_serving_dispatch',
        'counter_billing_dispatch',
        'auto_complete_on_payment',
        'enabled_service_modes',
        'token_system_enabled',
        'table_capacity_limit',
        'user_capacity_limit',
        'rooms_capacity_limit',
        'is_rooms_facility',
        'has_inventory',
        'has_detailed_inventory',
        'has_attendance',
        'is_pay_first',
        'is_all_in_one_dashboard',
        'qr_ordering_enabled',
        'parcel_ordering_enabled',
        'allow_customer_call_waiter',
        'enable_tax',
        'tax_regime',
        'tax_calculation_type',
        'tax_level',
        'tax_components',
        'service_charge_percent',
        'additional_charges',
        'print_paper_size',
        'print_show_logo',
        'print_show_upi_qr',
        'print_show_tax_breakup',
        'print_header_text',
        'print_footer_text',
        'fssai_lic_no',
        'custom_features',
    ];

    protected $casts = [
        'active_workflow_roles'      => 'array',
        'enabled_service_modes'     => 'array',
        'tax_components'            => 'array',
        'additional_charges'        => 'array',
        'custom_features'           => 'array',
        'require_order_approval'    => 'boolean',
        'waiter_can_take_orders'    => 'boolean',
        'kitchen_order_dispatch'    => 'boolean',
        'waiter_serving_dispatch'   => 'boolean',
        'counter_billing_dispatch'  => 'boolean',
        'auto_complete_on_payment'  => 'boolean',
        'token_system_enabled'      => 'boolean',
        'is_rooms_facility'         => 'boolean',
        'has_inventory'             => 'boolean',
        'has_detailed_inventory'    => 'boolean',
        'has_attendance'            => 'boolean',
        'is_pay_first'              => 'boolean',
        'is_all_in_one_dashboard'   => 'boolean',
        'qr_ordering_enabled'       => 'boolean',
        'parcel_ordering_enabled'   => 'boolean',
        'allow_customer_call_waiter'=> 'boolean',
        'enable_tax'                => 'boolean',
        'print_show_logo'           => 'boolean',
        'print_show_upi_qr'         => 'boolean',
        'print_show_tax_breakup'    => 'boolean',
        'service_charge_percent'    => 'decimal:2',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /* ---------------------------------------------------
     | DYNAMIC WORKFLOW & ROLE CHECKERS
     |---------------------------------------------------*/
    public function hasRoleInWorkflow(string $role): bool
    {
        $roles = $this->active_workflow_roles ?? [];
        return in_array($role, $roles);
    }

    public function shouldBypassKitchen(): bool
    {
        return !$this->kitchen_order_dispatch || !$this->hasRoleInWorkflow('chef');
    }

    public function shouldBypassWaiter(): bool
    {
        return !$this->waiter_serving_dispatch || !$this->hasRoleInWorkflow('waiter');
    }

    public function supportsServiceMode(string $mode): bool
    {
        $modes = $this->enabled_service_modes ?? ['dine_in', 'takeaway_parcel'];
        return in_array($mode, $modes);
    }

    /* ---------------------------------------------------
     | FUTURE EXTENSIBLE FEATURES (JSON API)
     |---------------------------------------------------*/
    public function getCustomFeature(string $key, mixed $default = null): mixed
    {
        return data_get($this->custom_features, $key, $default);
    }

    public function setCustomFeature(string $key, mixed $value): self
    {
        $features = $this->custom_features ?? [];
        data_set($features, $key, $value);
        $this->custom_features = $features;
        return $this;
    }

    public function hasCustomFeature(string $key): bool
    {
        return (bool) $this->getCustomFeature($key, false);
    }
}
