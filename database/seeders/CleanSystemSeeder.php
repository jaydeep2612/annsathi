<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\User;

class CleanSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Core Roles
        $roles = [
            ['name' => 'super_admin',      'label' => 'Super Admin'],
            ['name' => 'owner',            'label' => 'Owner'],
            ['name' => 'restaurant_admin', 'label' => 'Restaurant Admin'],
            ['name' => 'manager',          'label' => 'Manager'],
            ['name' => 'captain',          'label' => 'Captain'],
            ['name' => 'waiter',           'label' => 'Waiter'],
            ['name' => 'chef',             'label' => 'Chef'],
            ['name' => 'cashier',          'label' => 'Cashier'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r['name']], ['label' => $r['label']]);
        }

        $superAdminRole = Role::where('name', 'super_admin')->first();

        // 2. Primary Super Admin Account
        User::firstOrCreate(
            ['email' => 'admin@annsathi.com'],
            [
                'name'           => 'AnnSathi Super Admin',
                'password'       => Hash::make('123'),
                'role_id'        => $superAdminRole->id,
                'is_super_admin' => true,
                'is_active'      => true,
                'restaurant_id'  => null,
            ]
        );

        $this->command->info('Clean system initialized successfully with core roles and Super Admin: admin@annsathi.com');
    }
}
