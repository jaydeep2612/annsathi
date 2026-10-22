<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Role;
use App\Support\Tenant\RestaurantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestaurantOnboardingService
{
    public function onboard(array $data): Restaurant
    {
        return DB::transaction(function () use ($data) {

            $slug = Str::slug($data['name']);

            $restaurant = Restaurant::create([
                'name' => $data['name'],
                'slug' => $slug,
                'is_active' => true,
            ]);

            // Create base folders
            Storage::disk('public')->makeDirectory(
                RestaurantStorage::logoPath($restaurant)
            );
            Storage::disk('public')->makeDirectory(
                RestaurantStorage::tableQrPath($restaurant)
            );

            // Move logo
            Storage::disk('public')->move(
                $data['logo'],
                RestaurantStorage::logoPath($restaurant) . '/logo.png'
            );

            $restaurant->update([
                'logo_path' => RestaurantStorage::logoPath($restaurant) . '/logo.png'
            ]);

            // Default admin role
            $adminRole = Role::firstOrCreate(
                ['name' => 'admin'],
                ['label' => 'Restaurant Admin']
            );

            // Restaurant admin user
            User::create([
                'restaurant_id' => $restaurant->id,
                'role_id' => $adminRole->id,
                'name' => 'Restaurant Admin',
                'email' => "admin@{$slug}.local",
                'password' => Hash::make('ChangeMe123'),
            ]);

            return $restaurant;
        });
    }
}
