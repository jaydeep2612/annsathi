<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Restaurant Tables
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('table_number');
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->unsignedSmallInteger('seating_capacity')->default(4);
            $table->string('status')->default('available'); // 'available', 'occupied', 'reserved'
            $table->string('qr_code_path')->nullable();
            $table->string('qr_path')->nullable();
            $table->string('qr_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'table_number']);
            $table->index(['restaurant_id', 'status']);
        });

        // 2. Hotel Rooms
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('room_number');
            $table->string('room_name')->nullable();
            $table->string('room_type')->nullable(); // 'Standard', 'Deluxe', 'Suite'
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->unsignedSmallInteger('max_guests')->default(2);
            $table->string('status')->default('available'); // 'available', 'occupied', 'cleaning', 'maintenance'
            $table->string('guest_name')->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->unsignedBigInteger('active_room_session_id')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('qr_path')->nullable();
            $table->string('qr_token')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'room_number']);
            $table->index(['restaurant_id', 'status']);
        });

        // 3. Table QR Sessions
        Schema::create('qr_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained('restaurant_tables')->cascadeOnDelete();
            $table->string('session_token')->unique();
            $table->string('customer_name')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->string('join_status')->default('active'); // 'pending', 'approved', 'rejected', 'active'
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('host_session_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // 'active', 'completed', 'cancelled'
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'restaurant_table_id', 'is_active']);
        });

        // 4. Room Dining Sessions
        Schema::create('room_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('session_token')->unique();
            $table->string('status')->default('active'); // 'active', 'completed', 'cancelled'
            $table->timestamps();

            $table->index(['restaurant_id', 'room_id', 'status']);
        });

        // 5. Parcel QR Codes (Counter takeaway stations)
        Schema::create('parcel_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name')->default('Counter Parcel');
            $table->string('qr_code_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // 6. Parcel QR Sessions
        Schema::create('parcel_qr_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('parcel_qr_code_id')->constrained('parcel_qr_codes')->cascadeOnDelete();
            $table->string('session_token')->unique();
            $table->string('status')->default('active');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_qr_sessions');
        Schema::dropIfExists('parcel_qr_codes');
        Schema::dropIfExists('room_sessions');
        Schema::dropIfExists('qr_sessions');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('restaurant_tables');
    }
};
