<?php

namespace App\Filament\Resources\RestaurantResource\Pages;

use App\Filament\Resources\RestaurantResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class CreateRestaurant extends CreateRecord
{
    protected static string $resource = RestaurantResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // 👇 JAISE HI RESTAURANT SAVE HOGA, YEH FUNCTION CHALEGA 👇
    protected function afterCreate(): void
    {
        // Form mein jo bhi data dala tha (including dehydrated(false) fields) usko get karo
        $data = $this->form->getRawState();

        // Database se 'restaurant_admin' role dhundho
        $role = Role::where('name', 'restaurant_admin')->first();

        // 1. Agar form me email mili hai aur role exists karta hai, toh user bana do
        if ($role && !empty($data['admin_email'])) {
            User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'role_id' => $role->id,
                'restaurant_id' => $this->record->id,
                'is_active' => true,
            ]);
        }

        // 2. Save / Update the configuration created by the model observer with Wizard options
        if ($this->record->configuration) {
            $taxComponents = match ($data['tax_regime'] ?? 'cgst_sgst') {
                'igst' => [
                    ['name' => 'IGST', 'type' => 'IGST', 'rate' => 5.0, 'is_active' => true],
                ],
                'cgst_utgst' => [
                    ['name' => 'CGST', 'type' => 'CGST', 'rate' => 2.5, 'is_active' => true],
                    ['name' => 'UTGST', 'type' => 'UTGST', 'rate' => 2.5, 'is_active' => true],
                ],
                default => [
                    ['name' => 'CGST', 'type' => 'CGST', 'rate' => 2.5, 'is_active' => true],
                    ['name' => 'SGST', 'type' => 'SGST', 'rate' => 2.5, 'is_active' => true],
                ],
            };

            $this->record->configuration->update([
                'business_type'            => $data['business_type'] ?? 'casual_dining',
                'workflow_preset'          => $data['workflow_preset'] ?? 'manager_waiter_chef',
                'active_workflow_roles'    => $data['active_workflow_roles'] ?? ['manager', 'waiter', 'chef'],
                'kitchen_order_dispatch'   => (bool) ($data['kitchen_order_dispatch'] ?? true),
                'waiter_serving_dispatch'  => (bool) ($data['waiter_serving_dispatch'] ?? true),
                'require_order_approval'   => (bool) ($data['require_order_approval'] ?? true),
                'waiter_can_take_orders'   => (bool) ($data['waiter_can_take_orders'] ?? true),
                'is_pay_first'             => (bool) ($data['is_pay_first'] ?? false),
                'token_system_enabled'     => (bool) ($data['token_system_enabled'] ?? false),
                'enabled_service_modes'    => $data['enabled_service_modes'] ?? ['dine_in', 'takeaway_parcel'],
                'table_capacity_limit'     => (int) ($data['table_capacity_limit'] ?? 20),
                'user_capacity_limit'      => (int) ($data['user_capacity_limit'] ?? 10),
                'rooms_capacity_limit'     => (int) ($data['rooms_capacity_limit'] ?? 0),
                'enable_tax'               => (bool) ($data['enable_tax'] ?? false),
                'tax_regime'               => $data['tax_regime'] ?? 'cgst_sgst',
                'tax_calculation_type'     => $data['tax_calculation_type'] ?? 'exclusive',
                'tax_components'           => $taxComponents,
                'service_charge_percent'   => (float) ($data['service_charge_percent'] ?? 0.0),
                'print_paper_size'         => $data['print_paper_size'] ?? 'thermal_80mm',
                'fssai_lic_no'             => $data['fssai_lic_no'] ?? null,
                'print_header_text'        => $data['print_header_text'] ?? null,
                'print_footer_text'        => $data['print_footer_text'] ?? null,
            ]);
        }
    }
}