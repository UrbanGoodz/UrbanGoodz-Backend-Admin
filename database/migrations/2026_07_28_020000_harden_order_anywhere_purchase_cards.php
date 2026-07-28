<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Additive hardening for the Order Anywhere purchase-card lifecycle.
 *
 * Every index carries an explicit short name: the table names here are long enough
 * that Laravel's generated identifiers exceed MySQL's 64-character limit. Each step
 * is guarded so a partially applied run can be completed rather than blocking.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'urban_goodz_order_anywhere_card_requests';

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn($table, 'single_use')) {
            DB::statement(
                "ALTER TABLE {$table} MODIFY single_use TINYINT(1) NOT NULL DEFAULT 0"
            );
        }

        $columns = [
            'issuance_key' => fn (Blueprint $t) => $t->string('issuance_key', 191)->nullable(),
            'customer_payment_intent_id' => fn (Blueprint $t) => $t->string('customer_payment_intent_id')->nullable(),
            'provider_authorization_id' => fn (Blueprint $t) => $t->string('provider_authorization_id')->nullable(),
            'provider_transaction_id' => fn (Blueprint $t) => $t->string('provider_transaction_id')->nullable(),
            'approved_purchase_budget' => fn (Blueprint $t) => $t->decimal('approved_purchase_budget', 12, 2)->nullable(),
            'approved_quote_version' => fn (Blueprint $t) => $t->string('approved_quote_version')->nullable(),
            'market_zone_reference' => fn (Blueprint $t) => $t->string('market_zone_reference')->nullable(),
            'payment_count_limit' => fn (Blueprint $t) => $t->unsignedSmallInteger('payment_count_limit')->default(1),
            'eligible_at' => fn (Blueprint $t) => $t->timestamp('eligible_at')->nullable(),
            'provider_configuration_status' => fn (Blueprint $t) => $t->string('provider_configuration_status', 30)->default('not_configured'),
            'retry_eligible_at' => fn (Blueprint $t) => $t->timestamp('retry_eligible_at')->nullable(),
            'issuance_attempts' => fn (Blueprint $t) => $t->unsignedSmallInteger('issuance_attempts')->default(0),
            'final_failure_at' => fn (Blueprint $t) => $t->timestamp('final_failure_at')->nullable(),
            'receipt_path' => fn (Blueprint $t) => $t->string('receipt_path')->nullable(),
            'receipt_original_name' => fn (Blueprint $t) => $t->string('receipt_original_name')->nullable(),
            'receipt_mime' => fn (Blueprint $t) => $t->string('receipt_mime', 100)->nullable(),
            'receipt_size' => fn (Blueprint $t) => $t->unsignedBigInteger('receipt_size')->nullable(),
            'receipt_total' => fn (Blueprint $t) => $t->decimal('receipt_total', 12, 2)->nullable(),
            'receipt_notes' => fn (Blueprint $t) => $t->text('receipt_notes')->nullable(),
            'receipt_submitted_at' => fn (Blueprint $t) => $t->timestamp('receipt_submitted_at')->nullable(),
            'failure_category' => fn (Blueprint $t) => $t->string('failure_category', 100)->nullable(),
            'failure_reported_at' => fn (Blueprint $t) => $t->timestamp('failure_reported_at')->nullable(),
            'reconciled_at' => fn (Blueprint $t) => $t->timestamp('reconciled_at')->nullable(),
            'reconciled_by' => fn (Blueprint $t) => $t->unsignedBigInteger('reconciled_by')->nullable(),
        ];

        $missing = array_filter(
            $columns,
            fn ($_, $column) => ! Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH
        );

        if ($missing !== []) {
            Schema::table($table, function (Blueprint $blueprint) use ($missing) {
                foreach ($missing as $definition) {
                    $definition($blueprint);
                }
            });
        }

        $this->addIndex($table, 'oa_card_issuance_key_unique', ['issuance_key'], true);
        $this->addIndex($table, 'oa_card_provider_auth_idx', ['provider_authorization_id']);
        $this->addIndex($table, 'oa_card_provider_txn_idx', ['provider_transaction_id']);
        $this->addIndex($table, 'oa_card_customer_pi_idx', ['customer_payment_intent_id']);

        if (! Schema::hasTable('urban_goodz_issuing_cardholders')) {
            Schema::create('urban_goodz_issuing_cardholders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_man_id');
                $table->string('provider', 50);
                $table->string('provider_cardholder_id');
                $table->string('verification_status', 30)->default('unverified');
                $table->string('provider_status', 30)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->json('safe_metadata')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'delivery_man_id'], 'oa_issuing_driver_unique');
                $table->unique(['provider', 'provider_cardholder_id'], 'oa_issuing_cardholder_unique');
            });
        }

        if (! Schema::hasTable('urban_goodz_order_anywhere_card_events')) {
            Schema::create('urban_goodz_order_anywhere_card_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('card_request_id')->nullable();
                $table->string('provider', 50);
                $table->string('event_id', 191);
                $table->string('event_type', 100);
                $table->string('payload_hash', 64);
                $table->string('processing_status', 30)->default('received');
                $table->json('safe_metadata')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'event_id'], 'oa_card_event_provider_id_unique');
                $table->index('card_request_id', 'oa_card_event_card_idx');
            });
        }

        if (! Schema::hasTable('urban_goodz_order_anywhere_card_reveal_sessions')) {
            Schema::create('urban_goodz_order_anywhere_card_reveal_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('card_request_id');
                $table->unsignedBigInteger('delivery_man_id');
                $table->string('token_hash', 64);
                $table->timestamp('expires_at');
                $table->timestamp('first_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique('token_hash', 'oa_card_reveal_token_unique');
                $table->index('card_request_id', 'oa_card_reveal_card_idx');
                $table->index('delivery_man_id', 'oa_card_reveal_driver_idx');
            });
        }

        if (! Schema::hasTable('urban_goodz_order_anywhere_card_reconciliations')) {
            Schema::create('urban_goodz_order_anywhere_card_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('card_request_id');
                $table->unsignedBigInteger('order_anywhere_request_id');
                $table->string('customer_payment_intent_id')->nullable();
                $table->string('provider_authorization_id')->nullable();
                $table->string('provider_transaction_id')->nullable();
                $table->decimal('approved_budget', 12, 2);
                $table->decimal('authorized_amount', 12, 2)->default(0);
                $table->decimal('transaction_amount', 12, 2)->default(0);
                $table->decimal('receipt_amount', 12, 2)->nullable();
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->decimal('reversed_amount', 12, 2)->default(0);
                $table->decimal('unused_amount', 12, 2)->default(0);
                $table->decimal('overage_amount', 12, 2)->default(0);
                $table->boolean('partial_capture')->default(false);
                $table->boolean('force_post')->default(false);
                $table->string('status', 30)->default('pending_receipt');
                $table->string('mismatch_category')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->json('safe_metadata')->nullable();
                $table->timestamps();

                $table->unique('card_request_id', 'oa_card_recon_card_unique');
                $table->index('order_anywhere_request_id', 'oa_card_recon_request_idx');
                $table->index('customer_payment_intent_id', 'oa_card_recon_pi_idx');
                $table->index('provider_authorization_id', 'oa_card_recon_auth_idx');
                $table->index('provider_transaction_id', 'oa_card_recon_txn_idx');
                $table->index('status', 'oa_card_recon_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_order_anywhere_card_reconciliations');
        Schema::dropIfExists('urban_goodz_order_anywhere_card_reveal_sessions');
        Schema::dropIfExists('urban_goodz_order_anywhere_card_events');
        Schema::dropIfExists('urban_goodz_issuing_cardholders');

        $table = 'urban_goodz_order_anywhere_card_requests';

        foreach ([
            'oa_card_issuance_key_unique',
            'oa_card_provider_auth_idx',
            'oa_card_provider_txn_idx',
            'oa_card_customer_pi_idx',
        ] as $index) {
            $this->dropIndex($table, $index);
        }

        $columns = array_filter([
            'issuance_key',
            'customer_payment_intent_id',
            'provider_authorization_id',
            'provider_transaction_id',
            'approved_purchase_budget',
            'approved_quote_version',
            'market_zone_reference',
            'payment_count_limit',
            'eligible_at',
            'provider_configuration_status',
            'retry_eligible_at',
            'issuance_attempts',
            'final_failure_at',
            'receipt_path',
            'receipt_original_name',
            'receipt_mime',
            'receipt_size',
            'receipt_total',
            'receipt_notes',
            'receipt_submitted_at',
            'failure_category',
            'failure_reported_at',
            'reconciled_at',
            'reconciled_by',
        ], fn ($column) => Schema::hasColumn($table, $column));

        if ($columns !== []) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropColumn(array_values($columns));
            });
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn($table, 'single_use')) {
            DB::statement(
                "ALTER TABLE {$table} MODIFY single_use TINYINT(1) NOT NULL DEFAULT 1"
            );
        }
    }

    private function addIndex(string $table, string $name, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns, $unique) {
            $unique
                ? $blueprint->unique($columns, $name)
                : $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
