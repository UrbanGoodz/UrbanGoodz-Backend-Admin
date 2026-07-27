<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation rule engine storage.
 *
 * Three tables:
 *  - urban_goodz_compensation_rules      the versioned, scoped, prioritised rule
 *  - urban_goodz_compensation_rule_audits every state/field change, append only
 *  - urban_goodz_compensation_results     immutable calculation snapshots
 *
 * Money is stored in integer cents everywhere. Decimal columns are deliberately
 * avoided in this engine so that percentage splits and rounding stay exact and
 * reproducible; the existing decimal(8,2) policy table remains untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_compensation_rules')) {
            Schema::create('urban_goodz_compensation_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_key', 191);
                $table->string('name');
                $table->unsignedInteger('version')->default(1);
                $table->string('state', 32)->default('draft'); // draft|published|archived
                $table->boolean('is_active')->default(true);

                // Scope. NULL means "any" for every scope column.
                $table->string('work_type', 32);          // delivery|route|logistics|medical
                $table->string('service_scope', 64)->nullable();
                $table->json('vehicle_scope')->nullable(); // ["cargo_van","box_truck"]
                $table->json('market_scope')->nullable();  // ["stl","kc"]
                $table->unsignedBigInteger('zone_id')->nullable();

                // Resolution
                $table->integer('priority')->default(0);   // higher wins

                // Effectivity
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();

                // Structure
                $table->json('components');                // pay component configuration
                $table->json('splits')->nullable();        // split configuration
                $table->string('rounding_mode', 16)->default('half_up');
                $table->unsignedBigInteger('minimum_payout_cents')->nullable();
                $table->unsignedBigInteger('maximum_payout_cents')->nullable();

                // Provenance
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['rule_key', 'version'], 'ug_comp_rule_key_version_uq');
                $table->index(['work_type', 'state', 'is_active'], 'ug_comp_rule_lookup_idx');
                $table->index(['effective_from', 'effective_to'], 'ug_comp_rule_effective_idx');
                $table->index('priority', 'ug_comp_rule_priority_idx');
                $table->index('zone_id', 'ug_comp_rule_zone_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_compensation_rule_audits')) {
            Schema::create('urban_goodz_compensation_rule_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id');
                $table->string('rule_key', 191);
                $table->unsignedInteger('version');
                $table->string('event', 64); // created|updated|published|archived|disabled|enabled
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_type', 64)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['rule_id', 'created_at'], 'ug_comp_audit_rule_idx');
                $table->index(['rule_key', 'version'], 'ug_comp_audit_key_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_compensation_results')) {
            Schema::create('urban_goodz_compensation_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->nullable();
                $table->string('rule_key', 191)->nullable();
                $table->unsignedInteger('rule_version')->nullable();

                $table->string('subject_type', 64);       // order|route|load|medical_job
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('driver_id')->nullable();

                $table->json('context');                   // inputs, verbatim
                $table->json('breakdown');                 // every component line
                $table->json('splits')->nullable();        // resolved split amounts
                $table->text('explanation');               // human readable derivation

                $table->bigInteger('gross_cents')->default(0);
                $table->bigInteger('driver_cents')->default(0);
                $table->boolean('is_final')->default(false);
                $table->timestamp('finalized_at')->nullable();

                $table->timestamps();

                $table->index(['subject_type', 'subject_id'], 'ug_comp_result_subject_idx');
                $table->index('driver_id', 'ug_comp_result_driver_idx');
                $table->index('is_final', 'ug_comp_result_final_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_compensation_results');
        Schema::dropIfExists('urban_goodz_compensation_rule_audits');
        Schema::dropIfExists('urban_goodz_compensation_rules');
    }
};
