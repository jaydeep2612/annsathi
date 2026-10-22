<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Measurement Units
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol');
            $table->string('base_unit')->nullable(); // 'g', 'ml', 'pcs'
            $table->decimal('conversion_factor', 12, 4)->default(1.0000); // multiplier to convert to base_unit
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Grocery Items (Raw Ingredients & Stock)
        Schema::create('grocery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->foreignId('measurement_unit_id')->constrained('measurement_units')->restrictOnDelete();
            $table->decimal('current_stock', 12, 4)->default(0);
            $table->decimal('low_stock_threshold', 12, 4)->default(0);
            $table->decimal('cost_per_unit', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'name'], 'grocery_items_unique');
        });

        // 3. Recipes (Bill of Materials)
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('grocery_item_id')->constrained('grocery_items')->restrictOnDelete();
            $table->decimal('quantity_required', 10, 4);
            $table->foreignId('measurement_unit_id')->constrained('measurement_units')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['menu_item_id', 'grocery_item_id']);
        });

        // 4. Inventory Transactions
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('grocery_item_id')->constrained('grocery_items')->restrictOnDelete();
            $table->enum('type', ['addition', 'deduction', 'spoilage', 'order_fulfillment', 'order_cancellation']);
            $table->decimal('quantity', 12, 4);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['grocery_item_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });

        // 5. Staff Attendances
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['pending', 'present', 'absent', 'half_day'])->default('pending');
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('grocery_items');
        Schema::dropIfExists('measurement_units');
    }
};
