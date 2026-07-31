<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_provider_portfolio_items')) {
            Schema::create('urban_goodz_provider_portfolio_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
                // Portfolio pieces outlive the service they were produced for, so the
                // service link is nullable and deliberately not constrained.
                $table->unsignedBigInteger('provider_service_id')->nullable()->index();
                $table->string('title');
                $table->text('caption')->nullable();
                $table->string('media_path', 2048);
                $table->string('media_type', 16)->default('image');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                // Explicit name: the generated one exceeds MySQL's 64-char limit.
                $table->index(['provider_id', 'is_active', 'sort_order'], 'ug_portfolio_provider_active_sort_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_service_disputes')) {
            Schema::create('urban_goodz_service_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_request_id')->constrained('urban_goodz_service_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('provider_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('reason', 64);
                $table->text('details');
                $table->unsignedBigInteger('requested_amount_minor')->default(0);
                $table->string('status', 24)->default('open');
                $table->text('resolution_notes')->nullable();
                $table->unsignedBigInteger('resolved_amount_minor')->default(0);
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }

        // Admin actions such as commission changes are not tied to a booking, so
        // they cannot be recorded in urban_goodz_service_booking_events (whose
        // service_request_id is a required foreign key).
        if (!Schema::hasTable('urban_goodz_service_admin_audits')) {
            Schema::create('urban_goodz_service_admin_audits', function (Blueprint $table) {
                $table->id();
                $table->string('subject_type', 64);
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('action', 64);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
                $table->index(['action', 'created_at']);
            });
        }

        if (Schema::hasTable('urban_goodz_service_providers')) {
            Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_service_providers', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_providers', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_providers', 'commission_percent')) {
                    // Null means "inherit the platform default" so that changing the
                    // global rate keeps applying to every provider that never had an
                    // explicit admin override.
                    $table->decimal('commission_percent', 5, 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('urban_goodz_service_provider_earnings')) {
            Schema::table('urban_goodz_service_provider_earnings', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_service_provider_earnings', 'commission_percent')) {
                    $table->decimal('commission_percent', 5, 2)->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_provider_earnings', 'settlement_batch')) {
                    $table->string('settlement_batch', 64)->nullable()->index();
                }
                if (!Schema::hasColumn('urban_goodz_service_provider_earnings', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_provider_earnings', 'settled_at')) {
                    $table->timestamp('settled_at')->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_service_provider_earnings', 'adjustment_minor')) {
                    $table->bigInteger('adjustment_minor')->default(0);
                }
                if (!Schema::hasColumn('urban_goodz_service_provider_earnings', 'adjustment_reason')) {
                    $table->string('adjustment_reason', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_service_provider_earnings')) {
            Schema::table('urban_goodz_service_provider_earnings', function (Blueprint $table) {
                $columns = [
                    'commission_percent', 'settlement_batch', 'approved_at',
                    'settled_at', 'adjustment_minor', 'adjustment_reason',
                ];
                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column) => Schema::hasColumn('urban_goodz_service_provider_earnings', $column)
                ));
                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('urban_goodz_service_providers')) {
            Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
                $columns = ['latitude', 'longitude', 'commission_percent'];
                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column) => Schema::hasColumn('urban_goodz_service_providers', $column)
                ));
                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }

        Schema::dropIfExists('urban_goodz_service_admin_audits');
        Schema::dropIfExists('urban_goodz_service_disputes');
        Schema::dropIfExists('urban_goodz_provider_portfolio_items');
    }
};
