<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_service_areas')) {
            Schema::create('urban_goodz_service_areas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
                $table->string('name');
                $table->string('area_type', 24)->default('city');
                $table->string('country_code', 2)->default('US');
                $table->string('region_code', 16)->nullable();
                $table->string('city')->nullable();
                $table->string('postal_code', 24)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedSmallInteger('radius_miles')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['provider_id', 'is_active']);
                $table->index(['postal_code', 'is_active']);
            });
        }

        if (!Schema::hasTable('urban_goodz_service_quotes')) {
            Schema::create('urban_goodz_service_quotes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_request_id')->constrained('urban_goodz_service_requests')->cascadeOnDelete();
                $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('deposit_minor')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->text('notes')->nullable();
                $table->dateTime('scheduled_at');
                $table->dateTime('expires_at')->nullable();
                $table->string('status', 24)->default('offered');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamps();
                $table->index(['service_request_id', 'status']);
            });
        }

        if (Schema::hasTable('urban_goodz_service_providers')) {
            Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_service_providers', 'onboarding_data')) {
                    $table->json('onboarding_data')->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_providers', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_providers', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('urban_goodz_service_requests')) {
            Schema::table('urban_goodz_service_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_service_requests', 'active_quote_id')) {
                    $table->unsignedBigInteger('active_quote_id')->nullable()->index();
                }
                if (!Schema::hasColumn('urban_goodz_service_requests', 'scheduled_end_at')) {
                    $table->dateTime('scheduled_end_at')->nullable()->index();
                }
                if (!Schema::hasColumn('urban_goodz_service_requests', 'amount_paid_minor')) {
                    $table->unsignedBigInteger('amount_paid_minor')->default(0);
                }
                if (!Schema::hasColumn('urban_goodz_service_requests', 'refunded_amount_minor')) {
                    $table->unsignedBigInteger('refunded_amount_minor')->default(0);
                }
                if (!Schema::hasColumn('urban_goodz_service_requests', 'canceled_by')) {
                    $table->string('canceled_by', 24)->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_requests', 'canceled_at')) {
                    $table->timestamp('canceled_at')->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_requests', 'service_area_id')) {
                    // Deliberately not constrained: service-area history must remain readable
                    // if a provider later retires or replaces its coverage configuration.
                    $table->unsignedBigInteger('service_area_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_service_requests')) {
            Schema::table('urban_goodz_service_requests', function (Blueprint $table) {
                $columns = [
                    'active_quote_id', 'scheduled_end_at', 'amount_paid_minor',
                    'refunded_amount_minor', 'canceled_by', 'canceled_at',
                    'service_area_id',
                ];
                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column) => Schema::hasColumn('urban_goodz_service_requests', $column)
                ));
                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('urban_goodz_service_providers')) {
            Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
                $columns = ['onboarding_data', 'submitted_at', 'approved_at'];
                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column) => Schema::hasColumn('urban_goodz_service_providers', $column)
                ));
                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }

        Schema::dropIfExists('urban_goodz_service_quotes');
        Schema::dropIfExists('urban_goodz_service_areas');
    }
};
