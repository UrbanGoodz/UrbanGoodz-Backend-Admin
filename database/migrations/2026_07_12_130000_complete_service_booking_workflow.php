<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }
        $indexes = array_map(
            fn($i) => $i->getName(),
            Schema::getIndexes($table)
        );
        return in_array($index, $indexes);
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }
        $tableForeignKey = Schema::getForeignKeys($table);
        foreach ($tableForeignKey as $fk) {
            if (in_array($column, $fk->getColumns())) {
                return true;
            }
        }
        return false;
    }

    private function uniqueIndexExists(string $table, string $indexName): bool
    {
        return $this->indexExists($table, $indexName);
    }

    private function safeAddColumn(Blueprint $table, string $column, string $type, ...$args): void
    {
        // Intentionally unused; each caller handles its own add logic
    }

    public function up(): void
    {
        $this->migrateProviders();
        $this->migrateProviderServices();
        $this->migrateProviderAvailability();
        $this->migrateServiceRequests();
        $this->migrateBookingEvents();
        $this->migrateEarnings();
        $this->migrateReviews();
    }

    private function migrateProviders(): void
    {
        if (!Schema::hasTable('urban_goodz_service_providers')) {
            return;
        }

        Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
            if (!$this->columnExists('urban_goodz_service_providers', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            }

            if (!$this->columnExists('urban_goodz_service_providers', 'approval_status')) {
                $table->string('approval_status', 24)->default('pending')->index();
            }

            if (!$this->columnExists('urban_goodz_service_providers', 'location_modes')) {
                $table->json('location_modes')->nullable();
            }

            if (!$this->columnExists('urban_goodz_service_providers', 'rating')) {
                $table->decimal('rating', 4, 2)->default(0);
            }

            if (!$this->columnExists('urban_goodz_service_providers', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0);
            }

            if (!$this->uniqueIndexExists('urban_goodz_service_providers', 'ug_service_provider_vendor_unique')) {
                if ($this->columnExists('urban_goodz_service_providers', 'vendor_id')) {
                    $table->unique('vendor_id', 'ug_service_provider_vendor_unique');
                }
            }
        });
    }

    private function migrateProviderServices(): void
    {
        if (Schema::hasTable('urban_goodz_provider_services')) {
            return;
        }

        Schema::create('urban_goodz_provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->string('category', 64)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('deposit_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('requires_quote')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function migrateProviderAvailability(): void
    {
        if (Schema::hasTable('urban_goodz_provider_availability')) {
            return;
        }

        Schema::create('urban_goodz_provider_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('timezone', 64)->default('America/Chicago');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['provider_id', 'day_of_week']);
        });
    }

    private function migrateServiceRequests(): void
    {
        if (!Schema::hasTable('urban_goodz_service_requests')) {
            return;
        }

        $columnsToAdd = [
            'user_id'                => fn(Blueprint $t) => $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(),
            'provider_id'            => fn(Blueprint $t) => $t->foreignId('provider_id')->nullable()->constrained('urban_goodz_service_providers')->nullOnDelete(),
            'provider_service_id'    => fn(Blueprint $t) => $t->foreignId('provider_service_id')->nullable()->constrained('urban_goodz_provider_services')->nullOnDelete(),
            'location_mode'          => fn(Blueprint $t) => $t->string('location_mode', 24)->default('in_person'),
            'location_details'       => fn(Blueprint $t) => $t->text('location_details')->nullable(),
            'requested_start_at'     => fn(Blueprint $t) => $t->dateTime('requested_start_at')->nullable()->index(),
            'scheduled_at'           => fn(Blueprint $t) => $t->dateTime('scheduled_at')->nullable()->index(),
            'quoted_amount_minor'    => fn(Blueprint $t) => $t->unsignedBigInteger('quoted_amount_minor')->nullable(),
            'deposit_amount_minor'   => fn(Blueprint $t) => $t->unsignedBigInteger('deposit_amount_minor')->default(0),
            'currency'               => fn(Blueprint $t) => $t->string('currency', 3)->default('USD'),
            'provider_notes'         => fn(Blueprint $t) => $t->text('provider_notes')->nullable(),
            'cancellation_reason'    => fn(Blueprint $t) => $t->text('cancellation_reason')->nullable(),
            'payment_status'         => fn(Blueprint $t) => $t->string('payment_status', 24)->default('not_required'),
            'accepted_at'            => fn(Blueprint $t) => $t->timestamp('accepted_at')->nullable(),
            'completed_at'           => fn(Blueprint $t) => $t->timestamp('completed_at')->nullable(),
        ];

        $pendingColumns = [];
        foreach ($columnsToAdd as $col => $_) {
            if (!$this->columnExists('urban_goodz_service_requests', $col)) {
                $pendingColumns[$col] = $_;
            }
        }

        if (empty($pendingColumns)) {
            return;
        }

        Schema::table('urban_goodz_service_requests', function (Blueprint $table) use ($pendingColumns) {
            foreach ($pendingColumns as $col => $definition) {
                $definition($table);
            }
        });
    }

    private function migrateBookingEvents(): void
    {
        if (Schema::hasTable('urban_goodz_service_booking_events')) {
            return;
        }

        Schema::create('urban_goodz_service_booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained('urban_goodz_service_requests')->cascadeOnDelete();
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function migrateEarnings(): void
    {
        if (Schema::hasTable('urban_goodz_service_provider_earnings')) {
            return;
        }

        Schema::create('urban_goodz_service_provider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->foreignId('service_request_id')->unique()->constrained('urban_goodz_service_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('provider_amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('pending');
            $table->timestamps();
        });
    }

    private function migrateReviews(): void
    {
        if (Schema::hasTable('urban_goodz_service_reviews')) {
            return;
        }

        Schema::create('urban_goodz_service_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained('urban_goodz_service_requests')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_service_reviews')) {
            Schema::dropIfExists('urban_goodz_service_reviews');
        }

        if (Schema::hasTable('urban_goodz_service_provider_earnings')) {
            Schema::dropIfExists('urban_goodz_service_provider_earnings');
        }

        if (Schema::hasTable('urban_goodz_service_booking_events')) {
            Schema::dropIfExists('urban_goodz_service_booking_events');
        }

        if (Schema::hasTable('urban_goodz_service_requests')) {
            Schema::table('urban_goodz_service_requests', function (Blueprint $table) {
                if ($this->foreignKeyExists('urban_goodz_service_requests', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
                if ($this->foreignKeyExists('urban_goodz_service_requests', 'provider_id')) {
                    $table->dropConstrainedForeignId('provider_id');
                }
                if ($this->foreignKeyExists('urban_goodz_service_requests', 'provider_service_id')) {
                    $table->dropConstrainedForeignId('provider_service_id');
                }

                $dropColumns = array_filter([
                    'location_mode', 'location_details', 'requested_start_at', 'scheduled_at',
                    'quoted_amount_minor', 'deposit_amount_minor', 'currency', 'provider_notes',
                    'cancellation_reason', 'payment_status', 'accepted_at', 'completed_at',
                ], fn($col) => $this->columnExists('urban_goodz_service_requests', $col));

                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        if (Schema::hasTable('urban_goodz_provider_availability')) {
            Schema::dropIfExists('urban_goodz_provider_availability');
        }

        if (Schema::hasTable('urban_goodz_provider_services')) {
            Schema::dropIfExists('urban_goodz_provider_services');
        }

        if (Schema::hasTable('urban_goodz_service_providers')) {
            Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
                if ($this->uniqueIndexExists('urban_goodz_service_providers', 'ug_service_provider_vendor_unique')) {
                    $table->dropUnique('ug_service_provider_vendor_unique');
                }
                if ($this->foreignKeyExists('urban_goodz_service_providers', 'vendor_id')) {
                    $table->dropConstrainedForeignId('vendor_id');
                }

                $dropColumns = array_filter([
                    'approval_status', 'location_modes', 'rating', 'rating_count',
                ], fn($col) => $this->columnExists('urban_goodz_service_providers', $col));

                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }
};
