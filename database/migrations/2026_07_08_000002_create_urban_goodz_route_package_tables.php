<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::create('urban_goodz_dedicated_routes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_rt_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->string('route_name');
                $table->string('route_type'); // logistics, medical_courier, load_board, bulk_delivery
                $table->text('pickup_location')->nullable();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->date('scheduled_date')->nullable();
                $table->string('recurring_rule')->nullable(); // daily, weekly, monthly, null=one-time
                $table->integer('max_packages_per_batch')->default(50);
                $table->string('status')->default('pending'); // pending, active, in_progress, completed, canceled
                $table->unsignedBigInteger('assigned_driver_id')->nullable()->index();
                $table->string('vehicle_type_required')->nullable();
                $table->integer('total_packages')->default(0);
                $table->integer('completed_packages')->default(0);
                $table->integer('failed_packages')->default(0);
                // Financial
                $table->decimal('driver_pay_per_package', 12, 2)->default(0);
                $table->decimal('business_charge_per_package', 12, 2)->default(0);
                $table->decimal('pickup_bonus', 12, 2)->default(0);
                $table->decimal('route_completion_bonus', 12, 2)->default(0);
                $table->decimal('priority_package_bonus', 12, 2)->default(0);
                $table->decimal('failed_delivery_partial_pay', 12, 2)->default(0);
                $table->decimal('return_to_sender_pay', 12, 2)->default(0);
                $table->boolean('instant_payout_allowed')->default(true);
                $table->boolean('weekly_payout_allowed')->default(true);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamp('route_started_at')->nullable();
                $table->timestamp('route_completed_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_client_id', 'status'], 'ug_dr_client_status_idx');
                $table->index(['assigned_driver_id', 'status'], 'ug_dr_driver_status_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_route_batches')) {
            Schema::create('urban_goodz_route_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dedicated_route_id');
                $table->foreign('dedicated_route_id', 'ug_batch_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->cascadeOnDelete();
                $table->string('batch_number');
                $table->integer('package_count')->default(0);
                $table->string('status')->default('pending'); // pending, assigned, picked_up, in_transit, completed, failed
                $table->unsignedBigInteger('assigned_driver_id')->nullable()->index();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['dedicated_route_id', 'batch_number'], 'ug_batch_route_num_uniq');
            });
        }

        if (!Schema::hasTable('urban_goodz_route_packages')) {
            Schema::create('urban_goodz_route_packages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dedicated_route_id');
                $table->foreign('dedicated_route_id', 'ug_pkg_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('route_batch_id')->nullable()->index();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_pkg_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->string('tracking_id')->unique();
                $table->string('external_reference')->nullable();
                $table->string('barcode')->nullable()->unique();
                $table->string('qr_code')->nullable()->unique();
                // Pickup
                $table->unsignedBigInteger('pickup_location_id')->nullable()->index();
                $table->string('pickup_contact_name')->nullable();
                $table->string('pickup_contact_phone')->nullable();
                $table->text('pickup_address')->nullable();
                // Dropoff
                $table->string('dropoff_name')->nullable();
                $table->text('dropoff_address');
                $table->string('dropoff_phone')->nullable();
                $table->decimal('dropoff_lat', 10, 7)->nullable();
                $table->decimal('dropoff_lng', 10, 7)->nullable();
                // Scheduling
                $table->dateTime('delivery_window_start')->nullable();
                $table->dateTime('delivery_window_end')->nullable();
                // Package details
                $table->string('package_type')->default('parcel'); // parcel, document, specimen, supply, pallet, envelope
                $table->decimal('weight', 10, 2)->nullable();
                $table->string('weight_unit')->default('lbs');
                $table->string('dimensions')->nullable();
                $table->string('priority')->default('normal'); // normal, high, urgent, medical
                $table->boolean('requires_signature')->default(false);
                $table->boolean('requires_photo')->default(false);
                $table->boolean('requires_custody')->default(false);
                $table->string('temperature_requirement')->nullable(); // ambient, refrigerated, frozen, controlled
                // Status lifecycle
                $table->string('status')->default('pending'); // pending, picked_up, in_transit, delivered, failed, returned
                $table->timestamp('pickup_scanned_at')->nullable();
                $table->unsignedBigInteger('pickup_scanned_by')->nullable()->index();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->timestamp('dropoff_scanned_at')->nullable();
                $table->unsignedBigInteger('dropoff_scanned_by')->nullable()->index();
                // Proof
                $table->text('proof_photo')->nullable();
                $table->text('recipient_signature')->nullable();
                $table->string('exception_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['dedicated_route_id', 'status'], 'ug_pkg_route_status_idx');
                $table->index(['business_client_id', 'status'], 'ug_pkg_client_status_idx');
                $table->index(['route_batch_id', 'status'], 'ug_pkg_batch_status_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_package_scans')) {
            Schema::create('urban_goodz_package_scans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('package_id');
                $table->foreign('package_id', 'ug_scan_pkg_fk')
                      ->references('id')->on('urban_goodz_route_packages')
                      ->cascadeOnDelete();
                $table->string('scan_type'); // pickup, dropoff, custody_check, exception, return_to_sender
                $table->unsignedBigInteger('scanned_by')->nullable()->index();
                $table->string('scanner_type')->default('driver'); // driver, admin, system
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('photo')->nullable();
                $table->text('signature')->nullable();
                $table->string('exception_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['package_id', 'scan_type'], 'ug_scan_pkg_type_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_route_assignments')) {
            Schema::create('urban_goodz_route_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dedicated_route_id');
                $table->foreign('dedicated_route_id', 'ug_assign_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('delivery_man_id')->index();
                $table->string('status')->default('assigned'); // assigned, accepted, en_route, started, completed, canceled
                $table->unsignedBigInteger('assigned_by')->nullable()->index();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('route_started_at')->nullable();
                $table->timestamp('route_completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['dedicated_route_id', 'delivery_man_id'], 'ug_assign_route_dm_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_route_optimization_stops')) {
            Schema::create('urban_goodz_route_optimization_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dedicated_route_id');
                $table->foreign('dedicated_route_id', 'ug_opt_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('package_id');
                $table->foreign('package_id', 'ug_opt_pkg_fk')
                      ->references('id')->on('urban_goodz_route_packages')
                      ->cascadeOnDelete();
                $table->integer('stop_order')->default(0);
                $table->decimal('estimated_distance_from_prev', 10, 2)->nullable(); // miles/km
                $table->integer('estimated_duration_from_prev')->nullable(); // minutes
                $table->string('status')->default('pending'); // pending, completed, skipped
                $table->timestamps();

                $table->unique(['dedicated_route_id', 'stop_order'], 'ug_opt_route_order_uniq');
                $table->index(['dedicated_route_id', 'package_id'], 'ug_opt_route_pkg_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_driver_earnings')) {
            Schema::create('urban_goodz_driver_earnings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_man_id')->index();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->foreign('package_id', 'ug_earn_pkg_fk')
                      ->references('id')->on('urban_goodz_route_packages')
                      ->nullOnDelete();
                $table->unsignedBigInteger('dedicated_route_id')->nullable();
                $table->foreign('dedicated_route_id', 'ug_earn_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->nullOnDelete();
                $table->string('earning_type'); // per_package, pickup_bonus, completion_bonus, priority_bonus, partial_pay, return_pay
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('pending'); // pending, approved, paid, held, disputed
                $table->text('description')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['delivery_man_id', 'status'], 'ug_earn_dm_status_idx');
                $table->index(['dedicated_route_id', 'earning_type'], 'ug_earn_route_type_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_driver_payout_requests')) {
            Schema::create('urban_goodz_driver_payout_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_man_id')->index();
                $table->string('payout_type'); // instant, weekly, held
                $table->decimal('requested_amount', 12, 2);
                $table->decimal('instant_fee', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2);
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('pending'); // pending, approved, processing, paid, rejected, held
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->text('driver_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['delivery_man_id', 'status'], 'ug_payout_dm_status_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_client_invoices')) {
            Schema::create('urban_goodz_client_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_inv_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('dedicated_route_id')->nullable();
                $table->foreign('dedicated_route_id', 'ug_inv_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->nullOnDelete();
                $table->string('invoice_type')->default('route'); // route, batch, summary, custom
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax', 12, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('draft'); // draft, sent, paid, overdue, canceled
                $table->text('notes')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_client_id', 'status'], 'ug_inv_client_status_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_medical_custody_logs')) {
            Schema::create('urban_goodz_medical_custody_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('package_id');
                $table->foreign('package_id', 'ug_cust_pkg_fk')
                      ->references('id')->on('urban_goodz_route_packages')
                      ->cascadeOnDelete();
                $table->string('custody_event'); // pickup, handoff, dropoff, temp_check, seal_check, exception
                $table->unsignedBigInteger('from_user_id')->nullable()->index();
                $table->string('from_user_type')->nullable(); // driver, client, admin, lab
                $table->unsignedBigInteger('to_user_id')->nullable()->index();
                $table->string('to_user_type')->nullable();
                $table->decimal('temperature', 6, 2)->nullable();
                $table->boolean('seal_intact')->nullable();
                $table->text('notes')->nullable();
                $table->string('signature')->nullable();
                $table->timestamps();

                $table->index(['package_id', 'custody_event'], 'ug_cust_pkg_event_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_medical_custody_logs');
        Schema::dropIfExists('urban_goodz_client_invoices');
        Schema::dropIfExists('urban_goodz_driver_payout_requests');
        Schema::dropIfExists('urban_goodz_driver_earnings');
        Schema::dropIfExists('urban_goodz_route_optimization_stops');
        Schema::dropIfExists('urban_goodz_route_assignments');
        Schema::dropIfExists('urban_goodz_package_scans');
        Schema::dropIfExists('urban_goodz_route_packages');
        Schema::dropIfExists('urban_goodz_route_batches');
        Schema::dropIfExists('urban_goodz_dedicated_routes');
    }
};
