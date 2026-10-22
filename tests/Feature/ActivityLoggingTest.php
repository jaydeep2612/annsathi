<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\CleanSystemSeeder;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CleanSystemSeeder::class);
    }

    public function test_model_operations_are_automatically_logged(): void
    {
        $superAdmin = User::where('email', 'admin@annsathi.com')->first();
        $this->actingAs($superAdmin);

        // 1. Create a restaurant -> should log 'created'
        $restaurant = Restaurant::create([
            'name'      => 'Test Audit Cafe',
            'slug'      => 'test-audit-cafe',
            'is_active' => true,
        ]);

        $createLog = ActivityLog::where('model_type', Restaurant::class)
            ->where('model_id', $restaurant->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertEquals('super_admin', $createLog->user_role);
        $this->assertEquals($superAdmin->id, $createLog->user_id);

        // 2. Update restaurant -> should log 'updated' with old and new values
        $restaurant->update(['name' => 'Updated Audit Cafe']);

        $updateLog = ActivityLog::where('model_type', Restaurant::class)
            ->where('model_id', $restaurant->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertEquals('Test Audit Cafe', $updateLog->old_values['name']);
        $this->assertEquals('Updated Audit Cafe', $updateLog->new_values['name']);

        // 3. Delete restaurant -> should log 'deleted'
        $resId = $restaurant->id;
        $restaurant->delete();

        $deleteLog = ActivityLog::where('model_type', Restaurant::class)
            ->where('model_id', $resId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($deleteLog);
    }
}
