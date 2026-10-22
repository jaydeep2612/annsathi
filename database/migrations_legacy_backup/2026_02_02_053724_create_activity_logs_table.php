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
        Schema::create('activity_logs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('actor_type');
    $table->unsignedBigInteger('actor_id')->nullable();
    $table->string('action');
    $table->string('entity_type');
    $table->unsignedBigInteger('entity_id');
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['entity_type', 'entity_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
