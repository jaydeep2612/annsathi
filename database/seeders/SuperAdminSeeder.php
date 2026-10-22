<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 1. Create Super Admin Role
         * This role bypasses tenant isolation
         */
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['label' => 'Super Administrator']
        );

        /**
         * 2. Create Super Admin User
         * - NO restaurant_id (critical)
         * - System-level user
         */
        User::updateOrCreate(
            ['email' => 'superadmin@system.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('123'), // change later
                'role_id' => $superAdminRole->id,
                'is_super_admin' => true,
                'restaurant_id' => null, // 🚨 NEVER SET THIS
                'is_active' => true,
            ]
        );
    }
}
