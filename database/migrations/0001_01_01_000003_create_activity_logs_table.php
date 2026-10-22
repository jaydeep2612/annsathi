<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable(); // snapshot: super_admin, owner, restaurant_admin, manager, waiter, chef, etc.
            $table->string('actor_type')->nullable(); // 'staff', 'customer', 'system'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');                // 'created', 'updated', 'deleted', 'status_changed', 'login', 'logout'
            $table->string('model_type')->nullable(); // App\Models\Order, App\Models\MenuItem, etc.
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();  // Before update/delete
            $table->json('new_values')->nullable();  // After create/update
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
