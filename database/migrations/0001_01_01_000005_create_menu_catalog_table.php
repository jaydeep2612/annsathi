<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug']);
        });

        // 2. Menu Items (with tax_rate support for item-level tax slabs)
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('tax_rate', 5, 2)->nullable(); // Optional item-level tax slab (e.g. 5%, 12%, 18%)
            $table->enum('item_type', ['prepared', 'readymade'])->default('prepared'); // Cooked vs Packaged
            $table->enum('image_source', ['none', 'global', 'custom'])->default('none');
            $table->boolean('is_available')->default(true);
            $table->string('image_path')->nullable();
            $table->boolean('track_stock')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('is_veg')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'category_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('categories');
    }
};
