<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\UserResourcePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\CleanSystemSeeder;

class UserResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CleanSystemSeeder::class);
    }

    public function test_user_resource_permissions_enforcement(): void
    {
        $role = Role::where('name', 'waiter')->first();
        $restaurant = Restaurant::create([
            'name' => 'Access Test Cafe',
            'slug' => 'access-test-cafe-' . uniqid(),
        ]);

        $user = User::create([
            'name'          => 'Restricted Waiter',
            'email'         => 'waiter_' . uniqid() . '@example.com',
            'password'      => bcrypt('password'),
            'role_id'       => $role->id,
            'restaurant_id' => $restaurant->id,
            'is_active'     => true,
        ]);

        // Grant permission only to view orders, but NOT create or delete
        UserResourcePermission::create([
            'user_id'       => $user->id,
            'resource_name' => 'orders',
            'can_view_any'  => true,
            'can_view'      => true,
            'can_create'    => false,
            'can_update'    => false,
            'can_delete'    => false,
        ]);

        $this->assertTrue($user->hasResourcePermission('orders', 'viewAny'));
        $this->assertTrue($user->hasResourcePermission('orders', 'view'));
        $this->assertFalse($user->hasResourcePermission('orders', 'create'));
        $this->assertFalse($user->hasResourcePermission('orders', 'update'));
        $this->assertFalse($user->hasResourcePermission('orders', 'delete'));

        // Resource not granted at all
        $this->assertFalse($user->hasResourcePermission('inventory', 'viewAny'));
    }

    public function test_owner_and_super_admin_bypass(): void
    {
        $superAdmin = User::where('email', 'admin@annsathi.com')->first();
        $this->assertTrue($superAdmin->hasResourcePermission('anything', 'delete'));

        $ownerRole = Role::where('name', 'owner')->first();
        $owner = User::create([
            'name'          => 'Multi Owner',
            'email'         => 'owner_' . uniqid() . '@example.com',
            'password'      => bcrypt('password'),
            'role_id'       => $ownerRole->id,
            'is_active'     => true,
        ]);

        $this->assertTrue($owner->hasResourcePermission('orders', 'create'));
        $this->assertTrue($owner->hasResourcePermission('inventory', 'delete'));
    }
}
