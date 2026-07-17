<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Load Sources — master registry of all source configurations
        if (!Schema::hasTable('load_sources')) {
            Schema::create('load_sources', function (Blueprint $table) {
                $table->id();
                $table->string('source_key')->unique();
                $table->string('name');
                $table->string('type'); // api, email, manual, internal, referral, deep_link
                $table->boolean('enabled')->default(false);
                $table->string('api_status')->default('awaiting_credentials'); // awaiting_credentials, configured, connected, error, disabled
                $table->string('partnership_status')->default('pending'); // pending, applied, active, inactive, terminated
                $table->boolean('supports_bidding')->default(false);
                $table->boolean('supports_booking')->default(false);
                $table->boolean('supports_automation')->default(false);
                $table->text('description')->nullable();
                $table->string('source_url')->nullable();
                $table->string('deep_link_template')->nullable();
                $table->integer('rate_limit_per_minute')->default(60);
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->text('last_error_message')->nullable();
                $table->integer('total_syncs')->default(0);
                $table->integer('total_loads_sourced')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 2. External Loads — normalized loads from all sources
        if (!Schema::hasTable('external_loads')) {
            Schema::create('external_loads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->constrained('load_sources')->onDelete('cascade');
                $table->string('external_id');
                $table->string('fingerprint')->index();
                $table->string('source_url')->nullable();

                // Broker info
                $table->string('broker_name')->nullable();
                $table->string('broker_contact')->nullable();
                $table->string('broker_reference')->nullable();
                $table->decimal('broker_rating', 3, 2)->nullable();
                $table->string('broker_credit_status')->nullable(); // excellent, good, fair, poor, unknown

                // Origin
                $table->string('origin_address')->nullable();
                $table->string('origin_city')->nullable();
                $table->string('origin_state', 2)->nullable();
                $table->string('origin_zip')->nullable();
                $table->decimal('origin_latitude', 10, 7)->nullable();
                $table->decimal('origin_longitude', 10, 7)->nullable();

                // Destination
                $table->string('destination_address')->nullable();
                $table->string('destination_city')->nullable();
                $table->string('destination_state', 2)->nullable();
                $table->string('destination_zip')->nullable();
                $table->decimal('destination_latitude', 10, 7)->nullable();
                $table->decimal('destination_longitude', 10, 7)->nullable();

                // Timing
                $table->timestamp('pickup_start')->nullable();
                $table->timestamp('pickup_end')->nullable();
                $table->timestamp('delivery_start')->nullable();
                $table->timestamp('delivery_end')->nullable();

                // Equipment
                $table->string('equipment_type')->nullable();
                $table->string('trailer_type')->nullable();
                $table->string('vehicle_requirements')->nullable();
                $table->json('certifications_required')->nullable();
                $table->string('commodity')->nullable();
                $table->decimal('weight', 10, 2)->nullable();

                // Distances
                $table->decimal('distance_loaded', 8, 2)->nullable();
                $table->decimal('distance_deadhead', 8, 2)->nullable();

                // Financials
                $table->decimal('gross_rate', 10, 2)->nullable();
                $table->decimal('rate_per_loaded_mile', 8, 4)->nullable();
                $table->decimal('estimated_fuel_cost', 10, 2)->nullable();
                $table->decimal('estimated_tolls', 10, 2)->nullable();
                $table->decimal('estimated_platform_fee', 10, 2)->nullable();
                $table->decimal('estimated_driver_net', 10, 2)->nullable();
                $table->decimal('estimated_net_per_total_mile', 8, 4)->nullable();

                // Status and lifecycle
                $table->string('status')->default('sourced'); // sourced, pending_review, approved, available, bid_submitted, booked, expired, cancelled
                $table->string('compliance_status')->default('internal'); // internal, business_client, authorized_partner, user_imported, email_sourced, external_link
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_duplicate')->default(false);
                $table->foreignId('deduplicated_to_id')->nullable()->constrained('external_loads')->nullOnDelete();

                // Raw data
                $table->json('raw_source_payload')->nullable();

                // Audit
                $table->foreignId('approved_by')->nullable();
                $table->string('approved_by_type')->nullable();
                $table->timestamp('approved_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['source_id', 'external_id']);
            });
        }

        // 3. Load Source Credentials — encrypted provider credentials
        if (!Schema::hasTable('load_source_credentials')) {
            Schema::create('load_source_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->constrained('load_sources')->onDelete('cascade');
                $table->string('credential_key'); // api_key, client_secret, access_token, etc.
                $table->text('encrypted_value');
                $table->string('status')->default('active'); // active, expired, revoked
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_validated_at')->nullable();
                $table->timestamps();

                $table->unique(['source_id', 'credential_key']);
            });
        }

        // 4. Load Source Searches — search history
        if (!Schema::hasTable('load_source_searches')) {
            Schema::create('load_source_searches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->nullable()->constrained('load_sources')->nullOnDelete();
                $table->foreignId('searched_by')->nullable();
                $table->string('searched_by_type')->nullable(); // admin, dispatcher, driver, system
                $table->string('search_scope')->default('single_source'); // single_source, all_sources
                $table->json('criteria')->nullable();
                $table->integer('result_count')->default(0);
                $table->decimal('duration_ms', 10, 2)->nullable();
                $table->boolean('completed')->default(true);
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['searched_by', 'searched_by_type']);
            });
        }

        // 5. Load Source Search Results — links searches to external loads
        if (!Schema::hasTable('load_source_search_results')) {
            Schema::create('load_source_search_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('search_id')->constrained('load_source_searches')->onDelete('cascade');
                $table->foreignId('external_load_id')->constrained('external_loads')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // 6. Load Source Sync Runs — sync history
        if (!Schema::hasTable('load_source_sync_runs')) {
            Schema::create('load_source_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->constrained('load_sources')->onDelete('cascade');
                $table->string('status')->default('running'); // running, completed, failed, partial
                $table->json('search_criteria')->nullable();
                $table->integer('loads_found')->default(0);
                $table->integer('loads_new')->default(0);
                $table->integer('loads_updated')->default(0);
                $table->integer('loads_duplicate')->default(0);
                $table->integer('loads_expired')->default(0);
                $table->decimal('duration_ms', 10, 2)->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 7. Load Source Errors — error tracking per source
        if (!Schema::hasTable('load_source_errors')) {
            Schema::create('load_source_errors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->constrained('load_sources')->onDelete('cascade');
                $table->foreignId('sync_run_id')->nullable()->constrained('load_source_sync_runs')->nullOnDelete();
                $table->string('error_code')->nullable();
                $table->text('error_message');
                $table->json('context')->nullable();
                $table->boolean('resolved')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        // 8. Load Recommendations — AI scoring output per driver
        if (!Schema::hasTable('load_recommendations')) {
            Schema::create('load_recommendations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('external_load_id')->constrained('external_loads')->onDelete('cascade');
                $table->foreignId('delivery_man_id')->nullable()->constrained('delivery_men')->nullOnDelete();
                $table->foreignId('generated_by')->nullable(); // admin/dispatcher who triggered
                $table->string('generated_by_type')->nullable();

                // AI scoring
                $table->integer('score')->default(0); // 0-100
                $table->string('confidence_level')->default('low'); // low, medium, high
                $table->decimal('estimated_driver_net', 10, 2)->nullable();
                $table->decimal('net_per_total_mile', 8, 4)->nullable();
                $table->decimal('deadhead_miles', 8, 2)->nullable();
                $table->boolean('equipment_match')->default(false);
                $table->boolean('certification_match')->default(false);
                $table->boolean('schedule_feasible')->default(false);
                $table->string('broker_risk')->default('unknown'); // low, medium, high, unknown
                $table->json('reasons_recommended')->nullable();
                $table->json('reasons_penalized')->nullable();

                // Driver interaction
                $table->string('status')->default('pending'); // pending, viewed, saved, hidden, interested, bid_submitted, assigned, dismissed, expired
                $table->boolean('driver_notified')->default(false);
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('saved_at')->nullable();
                $table->timestamp('hidden_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['delivery_man_id', 'status']);
                $table->index(['external_load_id', 'score']);
            });
        }

        // 9. Driver Load Preferences — per-driver sourcing preferences
        if (!Schema::hasTable('driver_load_preferences')) {
            Schema::create('driver_load_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_man_id')->constrained('delivery_men')->onDelete('cascade');
                $table->decimal('min_rate_per_mile', 8, 4)->nullable();
                $table->decimal('max_deadhead_miles', 8, 2)->nullable();
                $table->decimal('max_total_distance', 8, 2)->nullable();
                $table->json('preferred_origins')->nullable(); // states or cities
                $table->json('preferred_destinations')->nullable();
                $table->json('excluded_origins')->nullable();
                $table->json('excluded_destinations')->nullable();
                $table->json('preferred_equipment')->nullable();
                $table->json('excluded_commodities')->nullable();
                $table->boolean('prefer_home_routes')->default(false);
                $table->boolean('prefer_high_value')->default(false);
                $table->boolean('prefer_short_haul')->default(false);
                $table->boolean('prefer_long_haul')->default(false);
                $table->boolean('open_to_hazmat')->default(false);
                $table->boolean('open_to_temperature_controlled')->default(false);
                $table->integer('max_hours_per_day')->nullable();
                $table->timestamp('available_from')->nullable();
                $table->json('notes')->nullable();
                $table->timestamps();
            });
        }

        // 10. Dispatcher Saved Searches
        if (!Schema::hasTable('dispatcher_saved_searches')) {
            Schema::create('dispatcher_saved_searches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_client_user_id')->nullable()->constrained('business_client_users')->nullOnDelete();
                $table->foreignId('dispatch_company_id')->nullable();
                $table->string('name');
                $table->json('criteria')->nullable();
                $table->json('source_keys')->nullable();
                $table->boolean('auto_alert')->default(false);
                $table->integer('alert_threshold_score')->default(70);
                $table->integer('last_run_result_count')->default(0);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }

        // 11. Load Imports — manual imports and submissions
        if (!Schema::hasTable('load_imports')) {
            Schema::create('load_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->nullable()->constrained('load_sources')->nullOnDelete();
                $table->foreignId('imported_by');
                $table->string('imported_by_type'); // admin, dispatcher, driver
                $table->string('import_method'); // single_form, csv, share_to_urban_goodz, external_url
                $table->string('import_reference')->nullable();
                $table->string('original_filename')->nullable();
                $table->integer('total_rows')->default(0);
                $table->integer('successful_rows')->default(0);
                $table->integer('failed_rows')->default(0);
                $table->integer('duplicate_rows')->default(0);
                $table->string('status')->default('pending'); // pending, processing, completed, failed, partially_completed
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['imported_by', 'imported_by_type']);
            });
        }

        // 12. Load Email Ingestions
        if (!Schema::hasTable('load_email_ingestions')) {
            Schema::create('load_email_ingestions', function (Blueprint $table) {
                $table->id();
                $table->string('source_email_id')->unique();
                $table->string('from_address')->nullable();
                $table->string('from_name')->nullable();
                $table->string('subject')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->text('raw_body')->nullable();

                // Extracted data
                $table->string('origin_city')->nullable();
                $table->string('origin_state', 2)->nullable();
                $table->string('destination_city')->nullable();
                $table->string('destination_state', 2)->nullable();
                $table->string('equipment_type')->nullable();
                $table->decimal('weight', 10, 2)->nullable();
                $table->string('commodity')->nullable();
                $table->decimal('rate', 10, 2)->nullable();
                $table->string('broker_name')->nullable();
                $table->string('broker_contact')->nullable();
                $table->string('broker_reference')->nullable();
                $table->decimal('confidence_score', 5, 2)->nullable(); // 0-1

                // Processing
                $table->string('status')->default('received'); // received, extracted, pending_review, approved, rejected, imported
                $table->foreignId('external_load_id')->nullable()->constrained('external_loads')->nullOnDelete();
                $table->foreignId('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        // 13. Load Duplicates — tracks fingerprint deduplication
        if (!Schema::hasTable('load_duplicates')) {
            Schema::create('load_duplicates', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint');
                $table->foreignId('canonical_load_id')->constrained('external_loads')->onDelete('cascade');
                $table->foreignId('duplicate_load_id')->constrained('external_loads')->onDelete('cascade');
                $table->decimal('similarity_score', 5, 4)->default(1.0);
                $table->timestamps();

                $table->unique('fingerprint');
                $table->index('canonical_load_id');
            });
        }

        // 14. Load Partner Referrals — external handoff tracking
        if (!Schema::hasTable('load_partner_referrals')) {
            Schema::create('load_partner_referrals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('external_load_id')->constrained('external_loads')->onDelete('cascade');
                $table->foreignId('source_id')->constrained('load_sources')->onDelete('cascade');
                $table->foreignId('referred_by'); // driver or dispatcher who clicked
                $table->string('referred_by_type');
                $table->string('referral_action'); // open_source, share, contact_broker
                $table->string('external_url')->nullable();
                $table->boolean('user_confirmed_booked')->default(false);
                $table->string('booking_status')->default('pending'); // pending, booked, not_booked, unknown
                $table->string('rate_confirmation_url')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['external_load_id', 'referred_by']);
            });
        }

        // 15. Load Sourcing Settings — global AI config
        if (!Schema::hasTable('load_sourcing_settings')) {
            Schema::create('load_sourcing_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->unique();
                $table->text('setting_value')->nullable();
                $table->string('setting_type')->default('string'); // string, integer, decimal, boolean, json
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Add columns to existing urban_goodz_load_board_loads table
        if (Schema::hasTable('urban_goodz_load_board_loads')) {
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'source_id')) {
                Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
                    $table->foreignId('source_id')->nullable()->after('provider')->constrained('load_sources')->nullOnDelete();
                });
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'fingerprint')) {
                Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
                    $table->string('fingerprint')->nullable()->after('source_id')->index();
                });
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'raw_source_payload')) {
                Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
                    $table->json('raw_source_payload')->nullable()->after('load_type');
                });
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'source_url')) {
                Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
                    $table->string('source_url')->nullable()->after('raw_source_payload');
                });
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'expires_at')) {
                Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
                    $table->timestamp('expires_at')->nullable()->after('source_url');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('load_partner_referrals');
        Schema::dropIfExists('load_duplicates');
        Schema::dropIfExists('load_email_ingestions');
        Schema::dropIfExists('load_imports');
        Schema::dropIfExists('dispatcher_saved_searches');
        Schema::dropIfExists('driver_load_preferences');
        Schema::dropIfExists('load_recommendations');
        Schema::dropIfExists('load_source_errors');
        Schema::dropIfExists('load_source_sync_runs');
        Schema::dropIfExists('load_source_search_results');
        Schema::dropIfExists('load_source_searches');
        Schema::dropIfExists('load_source_credentials');
        Schema::dropIfExists('external_loads');
        Schema::dropIfExists('load_sourcing_settings');
        Schema::dropIfExists('load_sources');

        if (Schema::hasTable('urban_goodz_load_board_loads')) {
            Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
                $table->dropColumn(['source_id', 'fingerprint', 'raw_source_payload', 'source_url', 'expires_at']);
            });
        }
    }
};
