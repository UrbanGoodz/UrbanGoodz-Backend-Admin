<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Universal Urban Goodz commission rule storage.
 *
 * Before this table the only commission configuration on the platform was
 * `stores.comission` (marketplace stores only) and the global
 * `business_settings.admin_commission`. Load board, dispatcher, medical
 * courier, business courier, services, rentals, creator commerce, Fashion Fit
 * and Order Anywhere had nowhere to store a rate at all.
 *
 * A rule matches a transaction when every populated dimension equals the
 * transaction's context. A NULL dimension is a wildcard. Specificity is
 * therefore derived from which dimensions are populated, which keeps
 * resolution deterministic without a hand-maintained precedence column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Matching dimensions. NULL means "applies to any".
            $table->string('transaction_type')->nullable()->index();
            $table->unsignedBigInteger('module_id')->nullable()->index();
            $table->string('partner_type')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->string('service_type')->nullable()->index();
            $table->unsignedBigInteger('zone_id')->nullable()->index();
            $table->string('market')->nullable()->index();

            // Job/transaction-specific approved override.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Configuration.
            $table->boolean('commission_enabled')->default(true);
            $table->string('calculation_type')->default('percentage'); // percentage | fixed
            $table->decimal('rate_percent', 8, 4)->nullable();
            $table->bigInteger('fixed_amount_cents')->nullable();

            // What the rate applies to, e.g. merchandise_subtotal,
            // booked_load_amount, dispatcher_fee, job_revenue, route_charge,
            // booking_subtotal, rental_subtotal, creator_attributed_revenue.
            $table->string('basis');

            $table->bigInteger('minimum_cents')->nullable();
            $table->bigInteger('maximum_cents')->nullable();

            // Tie-break between rules of identical specificity. Higher wins.
            $table->integer('priority')->default(0);

            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedInteger('version')->default(1);
            $table->text('internal_reason')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['partner_type', 'partner_id'], 'ug_cr_partner_idx');
            $table->index(['subject_type', 'subject_id'], 'ug_cr_subject_idx');
            $table->index(['is_active', 'transaction_type'], 'ug_cr_active_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_commission_rules');
    }
};
