<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_financial_rules')) {
            Schema::create('urban_goodz_financial_rules', function (Blueprint $table) {
                $table->id();
                $table->uuid('rule_key');
                $table->unsignedInteger('version')->default(1);
                $table->string('name');
                $table->string('rule_family');
                $table->string('calculation_type');
                $table->unsignedBigInteger('amount_cents')->default(0);
                $table->unsignedInteger('rate_basis_points')->default(0);
                $table->string('scope_type')->default('platform');
                $table->string('scope_key')->nullable();
                $table->string('service_type')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->json('visibility_roles')->nullable();
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('supersedes_id')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->text('change_reason')->nullable();
                $table->timestamps();

                $table->unique(['rule_key', 'version'], 'ug_fin_rule_version_unique');
                $table->index(['rule_family', 'is_active'], 'ug_fin_rule_family_active_idx');
                $table->index(['scope_type', 'scope_key'], 'ug_fin_rule_scope_idx');
                $table->index(['service_type', 'effective_from', 'effective_to'], 'ug_fin_rule_effective_idx');
            });
        }

        if (! Schema::hasTable('urban_goodz_settlement_snapshots')) {
            Schema::create('urban_goodz_settlement_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('snapshot_number')->unique();
                $table->string('source_type');
                $table->string('source_id');
                $table->string('idempotency_key')->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('business_id')->nullable();
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->string('service_type')->nullable();
                $table->string('currency', 8)->default('USD');
                $table->unsignedBigInteger('shopper_total_cents')->default(0);
                $table->unsignedBigInteger('merchandise_subtotal_cents')->default(0);
                $table->unsignedBigInteger('delivery_charge_cents')->default(0);
                $table->unsignedBigInteger('business_commission_cents')->default(0);
                $table->unsignedBigInteger('provider_proceeds_cents')->default(0);
                $table->unsignedBigInteger('driver_compensation_cents')->default(0);
                $table->unsignedBigInteger('driver_admin_fee_cents')->default(0);
                $table->unsignedBigInteger('driver_net_cents')->default(0);
                $table->bigInteger('platform_delivery_margin_cents')->default(0);
                $table->bigInteger('platform_net_cents')->default(0);
                $table->unsignedBigInteger('refunded_cents')->default(0);
                $table->string('status')->default('settled');
                $table->string('reconciliation_status')->default('pending');
                $table->json('rule_snapshot');
                $table->json('inputs');
                $table->unsignedBigInteger('settled_by_admin_id')->nullable();
                $table->timestamp('settled_at');
                $table->timestamps();

                $table->index(['source_type', 'source_id'], 'ug_fin_snapshot_source_idx');
                $table->index(['customer_id', 'status'], 'ug_fin_snapshot_customer_idx');
                $table->index(['business_id', 'provider_id'], 'ug_fin_snapshot_business_idx');
                $table->index(['driver_id', 'status'], 'ug_fin_snapshot_driver_idx');
            });
        }

        if (! Schema::hasTable('urban_goodz_financial_ledger_entries')) {
            Schema::create('urban_goodz_financial_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_number')->unique();
                $table->unsignedBigInteger('settlement_snapshot_id');
                $table->string('event_type');
                $table->string('account_code');
                $table->string('party_type')->nullable();
                $table->unsignedBigInteger('party_id')->nullable();
                $table->string('direction');
                $table->unsignedBigInteger('amount_cents');
                $table->string('currency', 8)->default('USD');
                $table->string('reference')->nullable();
                $table->string('idempotency_key')->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['settlement_snapshot_id', 'event_type'], 'ug_fin_ledger_snapshot_event_idx');
                $table->index(['party_type', 'party_id'], 'ug_fin_ledger_party_idx');
            });
        }

        if (! Schema::hasTable('urban_goodz_reconciliation_runs')) {
            Schema::create('urban_goodz_reconciliation_runs', function (Blueprint $table) {
                $table->id();
                $table->string('run_number')->unique();
                $table->unsignedBigInteger('settlement_snapshot_id');
                $table->unsignedBigInteger('total_debits_cents');
                $table->unsignedBigInteger('total_credits_cents');
                $table->bigInteger('difference_cents');
                $table->string('status');
                $table->json('details')->nullable();
                $table->unsignedBigInteger('run_by_admin_id')->nullable();
                $table->timestamp('ran_at');
                $table->timestamps();

                $table->index(['settlement_snapshot_id', 'ran_at'], 'ug_fin_recon_snapshot_idx');
                $table->index('status', 'ug_fin_recon_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_reconciliation_runs');
        Schema::dropIfExists('urban_goodz_financial_ledger_entries');
        Schema::dropIfExists('urban_goodz_settlement_snapshots');
        Schema::dropIfExists('urban_goodz_financial_rules');
    }
};
