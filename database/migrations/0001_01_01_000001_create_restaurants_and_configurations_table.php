<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Restaurants (Identity & Profile)
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone_no')->nullable();
            $table->string('gst_no')->nullable();
            $table->string('upi_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. Dynamic Restaurant Configurations (Workflow, Tax, Print, Limits & Extensibility)
        Schema::create('restaurant_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete()->unique();

            // Business Archetype & Workflow Presets
            // cafe, quick_service, casual_dining, fine_dining, cloud_kitchen, sweet_shop_outlet, hotel_resort
            $table->string('business_type')->default('casual_dining');
            $table->string('workflow_preset')->default('standard'); // solo_manager, manager_waiter, manager_waiter_chef, full_crew, custom

            // Active Staff Roles in Workflow (JSON array e.g. ["manager", "waiter", "chef"])
            $table->json('active_workflow_roles')->nullable();

            // Dynamic Dispatch & Transition Flags
            $table->boolean('require_order_approval')->default(true);
            $table->boolean('waiter_can_take_orders')->default(true);
            $table->boolean('kitchen_order_dispatch')->default(true);
            $table->boolean('waiter_serving_dispatch')->default(true);
            $table->boolean('counter_billing_dispatch')->default(true);
            $table->boolean('auto_complete_on_payment')->default(true);

            // Service Modes & Daily Token System
            $table->json('enabled_service_modes')->nullable(); // ["dine_in", "takeaway_parcel", "room_service", "counter_pos"]
            $table->boolean('token_system_enabled')->default(false); // Daily incremental token e.g. #01, #02

            // Capacities & Modular Limits
            $table->unsignedInteger('table_capacity_limit')->default(20);
            $table->unsignedInteger('user_capacity_limit')->default(10);
            $table->unsignedInteger('rooms_capacity_limit')->default(0);

            // Modular SaaS Feature Toggles
            $table->boolean('is_rooms_facility')->default(false);
            $table->boolean('has_inventory')->default(false);
            $table->boolean('has_detailed_inventory')->default(false);
            $table->boolean('has_attendance')->default(false);
            $table->boolean('is_pay_first')->default(false);
            $table->boolean('is_all_in_one_dashboard')->default(false);

            // Ordering Channels
            $table->boolean('qr_ordering_enabled')->default(true);
            $table->boolean('parcel_ordering_enabled')->default(true);
            $table->boolean('allow_customer_call_waiter')->default(true);

            // 🧾 Multi-Type Tax & Billing Configuration
            $table->boolean('enable_tax')->default(false);
            $table->string('tax_regime')->default('cgst_sgst'); // 'cgst_sgst', 'igst', 'cgst_utgst', 'custom'
            $table->string('tax_calculation_type')->default('exclusive'); // 'exclusive', 'inclusive', 'exempt'
            $table->string('tax_level')->default('order_total');         // 'order_total', 'item_level'
            $table->json('tax_components')->nullable();
            $table->decimal('service_charge_percent', 5, 2)->default(0.00);
            $table->json('additional_charges')->nullable();

            // 🖨️ Bill Print Configuration
            $table->string('print_paper_size')->default('thermal_80mm'); // 'thermal_58mm', 'thermal_80mm', 'a4'
            $table->boolean('print_show_logo')->default(true);
            $table->boolean('print_show_upi_qr')->default(true);
            $table->boolean('print_show_tax_breakup')->default(true);
            $table->string('print_header_text')->nullable();
            $table->string('print_footer_text')->nullable();
            $table->string('fssai_lic_no')->nullable();

            // 🌟 Future Functionalities & Extensible Settings (Arbitrary JSON store)
            $table->json('custom_features')->nullable();

            $table->timestamps();
        });

        // 3. Attach Foreign Keys to Users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
        });

        Schema::dropIfExists('restaurant_configurations');
        Schema::dropIfExists('restaurants');
    }
};
