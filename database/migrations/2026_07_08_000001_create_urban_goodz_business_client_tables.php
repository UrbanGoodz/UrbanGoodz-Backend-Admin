<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_business_clients')) {
            Schema::create('urban_goodz_business_clients', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('legal_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('website')->nullable();
                $table->string('tax_id')->nullable();
                $table->string('business_type')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->default('US');
                $table->string('status')->default('pending'); // pending, approved, suspended, inactive
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('urban_goodz_business_client_users')) {
            Schema::create('urban_goodz_business_client_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_biz_users_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('password');
                $table->string('role')->default('dispatcher'); // owner, admin, dispatcher, billing, ops_manager, compliance, location_manager, viewer
                $table->json('permissions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_client_id', 'email'], 'ug_biz_users_email_unique');
            });
        }

        if (!Schema::hasTable('urban_goodz_business_client_locations')) {
            Schema::create('urban_goodz_business_client_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_biz_locs_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->string('name');
                $table->string('type')->default('primary'); // primary, branch, warehouse, clinic, lab, pickup, dropoff
                $table->text('address');
                $table->string('city');
                $table->string('state');
                $table->string('postal_code');
                $table->string('country')->default('US');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('contact_email')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('urban_goodz_business_client_documents')) {
            Schema::create('urban_goodz_business_client_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_biz_docs_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('document_type'); // contract, insurance, tax, license, cert, permit, misc
                $table->string('document_name');
                $table->string('file_path');
                $table->string('file_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->text('notes')->nullable();
                $table->date('expires_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('urban_goodz_business_client_jobs')) {
            Schema::create('urban_goodz_business_client_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('job_number')->unique();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_biz_jobs_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('job_type'); // logistics, load_board, medical_courier, event, bulk_delivery, rental
                $table->string('status')->default('submitted');
                $table->text('description')->nullable();
                $table->string('reference_number')->nullable();
                $table->string('po_number')->nullable();

                // Pickup
                $table->unsignedBigInteger('pickup_location_id')->nullable();
                $table->string('pickup_contact_name')->nullable();
                $table->string('pickup_contact_phone')->nullable();
                $table->dateTime('pickup_earliest')->nullable();
                $table->dateTime('pickup_latest')->nullable();

                // Dropoff
                $table->unsignedBigInteger('dropoff_location_id')->nullable();
                $table->string('dropoff_contact_name')->nullable();
                $table->string('dropoff_contact_phone')->nullable();
                $table->dateTime('delivery_deadline')->nullable();

                // Load details
                $table->string('load_type')->nullable();
                $table->decimal('weight', 10, 2)->nullable();
                $table->string('weight_unit')->default('lbs');
                $table->string('dimensions')->nullable();
                $table->integer('pallet_count')->nullable();
                $table->string('vehicle_type_needed')->nullable();
                $table->boolean('needs_liftgate')->default(false);
                $table->boolean('needs_dock')->default(false);
                $table->text('special_handling')->nullable();

                // Medical courier specific
                $table->string('specimen_type')->nullable();
                $table->string('temperature_requirement')->nullable();
                $table->string('urgency_level')->nullable();
                $table->boolean('chain_of_custody_required')->default(false);
                $table->boolean('sealed_package_confirmed')->default(false);
                $table->string('courier_certification_required')->nullable();

                // Financial
                $table->decimal('rate_offered', 12, 2)->nullable();
                $table->decimal('quoted_amount', 12, 2)->nullable();
                $table->decimal('final_amount', 12, 2)->nullable();
                $table->string('currency', 3)->default('USD');

                // Assignment
                $table->unsignedBigInteger('assigned_delivery_man_id')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();

                // Tracking
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('proof_of_pickup')->nullable();
                $table->text('proof_of_delivery')->nullable();

                // Admin
                $table->text('admin_notes')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();

                // Invoice
                $table->string('invoice_number')->nullable()->unique();
                $table->timestamp('invoiced_at')->nullable();
                $table->timestamp('paid_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_client_id', 'status'], 'ug_jobs_client_status_idx');
                $table->index(['job_type', 'status'], 'ug_jobs_type_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_business_client_jobs');
        Schema::dropIfExists('urban_goodz_business_client_documents');
        Schema::dropIfExists('urban_goodz_business_client_locations');
        Schema::dropIfExists('urban_goodz_business_client_users');
        Schema::dropIfExists('urban_goodz_business_clients');
    }
};
