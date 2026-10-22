<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Orders Table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('qr_session_id')->nullable()->constrained('qr_sessions')->nullOnDelete();
            $table->foreignId('room_session_id')->nullable()->constrained('room_sessions')->nullOnDelete();
            $table->foreignId('parcel_qr_session_id')->nullable()->constrained('parcel_qr_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Staff / Waiter who placed or served
            $table->string('order_number')->nullable();
            $table->string('token_number')->nullable(); // Daily token for QSR/Cafe e.g. #01, #02
            $table->enum('service_type', ['dine_in', 'room_service', 'parcel', 'counter_pos'])->default('dine_in');
            $table->string('status')->default('pending'); // 'pending', 'in_kitchen', 'ready_for_pickup', 'waiter_assigned', 'served', 'completed', 'cancelled'
            $table->string('payment_status')->default('unpaid'); // 'unpaid', 'partially_paid', 'paid'
            $table->string('customer_name')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('service_charge_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['restaurant_id', 'created_at']);
        });

        // 2. Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->string('item_name')->nullable();
            $table->enum('item_type', ['prepared', 'readymade'])->default('prepared');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'cooking', 'ready', 'served', 'cancelled'
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
        });

        // 3. Kitchen Queue (Chef KOT)
        Schema::create('kitchen_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->cascadeOnDelete();
            $table->string('status')->default('pending'); // 'pending', 'cooking', 'ready'
            $table->string('current_status')->default('pending');
            $table->integer('priority')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        // 4. Order Status Logs (Lifecycle tracking)
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Payments Table
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('qr_session_id')->nullable()->constrained('qr_sessions')->nullOnDelete();
            $table->foreignId('room_session_id')->nullable()->constrained('room_sessions')->nullOnDelete();
            $table->foreignId('parcel_qr_session_id')->nullable()->constrained('parcel_qr_sessions')->nullOnDelete();
            $table->string('bill_number')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('service_charge_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('amount_paise')->nullable();
            $table->string('payment_method')->default('cash'); // 'cash', 'upi', 'card', 'room_charge', 'online'
            $table->string('status')->default('pending'); // 'pending', 'paid', 'completed', 'failed', 'refunded'
            $table->string('transaction_reference')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('upi_link')->nullable();
            $table->string('gateway')->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();
            $table->string('gateway_status')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->decimal('extra_charges', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['order_id']);
            $table->index(['paid_at']);
        });

        // 6. Invoices Table (with tax_breakup JSON for multi-type tax reporting)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('qr_session_id')->nullable()->constrained('qr_sessions')->nullOnDelete();
            $table->foreignId('room_session_id')->nullable()->constrained('room_sessions')->nullOnDelete();
            $table->foreignId('parcel_session_id')->nullable()->constrained('parcel_qr_sessions')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('bill_number')->nullable();
            $table->unsignedBigInteger('invoice_sequence')->default(1);
            $table->string('invoice_prefix')->default('INV');
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->string('gstin')->nullable();
            $table->string('place_of_supply')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->json('tax_breakup')->nullable(); // Itemized: [{"name":"CGST","rate":2.5,"amount":12.50},{"name":"SGST","rate":2.5,"amount":12.50}]
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('service_charge_amount', 10, 2)->default(0);
            $table->decimal('extra_charges', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->json('items_snapshot')->nullable();
            $table->string('status')->default('generated');
            $table->timestamps();

            $table->index(['restaurant_id', 'created_at']);
        });

        // 7. Idempotency Keys
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('response')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // 8. Razorpay Webhook Logs
        Schema::create('razorpay_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_webhook_logs');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('kitchen_queue');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
