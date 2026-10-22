<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kitchen_queue', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->foreignId('order_id')->constrained()->restrictOnDelete();
    $table->string('current_status');
    $table->unsignedInteger('priority')->default(0);
    $table->timestamps();

    $table->unique('order_id');
    $table->index(['current_status', 'priority']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_queue');
    }
};
