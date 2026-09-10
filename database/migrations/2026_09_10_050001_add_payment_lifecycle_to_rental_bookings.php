<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives rental bookings the same authorize -> capture -> refund lifecycle that
 * order_anywhere_requests already carries.
 *
 * Before this, a booking had only `payment_status`, `deposit_status`,
 * `total_amount` and `deposit_amount` - no amounts actually held or taken, and
 * no provider reference - so nothing could reconcile a rental against the
 * gateway, and the polymorphic payment ledger had no booking-side counterpart
 * to verify against.
 *
 * Rentals differ from Order Anywhere in one important way: there are two
 * independent money flows. The rent itself is authorized then captured, while
 * the damage deposit is normally authorized and never captured - it is voided
 * on a clean return, or captured in part when an inspection finds damage. Both
 * therefore need their own amount/reference columns.
 *
 * Guarded per column so it is safe on deployments that already drifted.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> column => type
     */
    private const MONEY_COLUMNS = [
        'authorized_amount' => 'decimal',
        'captured_amount' => 'decimal',
        'refunded_amount' => 'decimal',
        'deposit_authorized_amount' => 'decimal',
        'deposit_captured_amount' => 'decimal',
        'deposit_refunded_amount' => 'decimal',
    ];

    private const REFERENCE_COLUMNS = [
        'payment_provider',
        'provider_reference',
        'authorization_reference',
        'capture_reference',
        'refund_reference',
        'deposit_authorization_reference',
        'deposit_capture_reference',
        'deposit_refund_reference',
    ];

    private const TIMESTAMP_COLUMNS = [
        'payment_authorized_at',
        'payment_captured_at',
        'payment_refunded_at',
        'deposit_authorized_at',
        'deposit_released_at',
        'authorization_expires_at',
    ];

    public function up(): void
    {
        Schema::table('urban_goodz_rental_bookings', function (Blueprint $table) {
            foreach (self::MONEY_COLUMNS as $column => $type) {
                if (Schema::hasColumn('urban_goodz_rental_bookings', $column)) {
                    continue;
                }

                // refunded totals are summed, so they must never be null.
                if (str_contains($column, 'refunded')) {
                    $table->decimal($column, 12, 2)->default(0);
                } else {
                    $table->decimal($column, 12, 2)->nullable();
                }
            }

            foreach (self::REFERENCE_COLUMNS as $column) {
                if (! Schema::hasColumn('urban_goodz_rental_bookings', $column)) {
                    $table->string($column)->nullable();
                }
            }

            foreach (self::TIMESTAMP_COLUMNS as $column) {
                if (! Schema::hasColumn('urban_goodz_rental_bookings', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }

            if (! Schema::hasColumn('urban_goodz_rental_bookings', 'currency')) {
                $table->string('currency', 8)->default('USD');
            }
        });

        // Indexed separately: reconciling a gateway webhook back to a booking
        // looks the reference up directly, and that has to stay cheap.
        Schema::table('urban_goodz_rental_bookings', function (Blueprint $table) {
            foreach (['provider_reference', 'payment_status'] as $column) {
                $indexName = self::indexName($column);

                if (Schema::hasColumn('urban_goodz_rental_bookings', $column)
                    && ! Schema::hasIndex('urban_goodz_rental_bookings', $indexName)) {
                    $table->index($column, $indexName);
                }
            }
        });
    }

    private static function indexName(string $column): string
    {
        return 'ug_rental_bookings_' . $column . '_index';
    }

    public function down(): void
    {
        Schema::table('urban_goodz_rental_bookings', function (Blueprint $table) {
            foreach (['provider_reference', 'payment_status'] as $column) {
                $indexName = self::indexName($column);

                if (Schema::hasIndex('urban_goodz_rental_bookings', $indexName)) {
                    // A literal name, so it must be passed as a string - an
                    // array argument would be read as a column list instead.
                    $table->dropIndex($indexName);
                }
            }
        });

        $columns = array_merge(
            array_keys(self::MONEY_COLUMNS),
            self::REFERENCE_COLUMNS,
            self::TIMESTAMP_COLUMNS,
            ['currency']
        );

        Schema::table('urban_goodz_rental_bookings', function (Blueprint $table) use ($columns) {
            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('urban_goodz_rental_bookings', $column)
            ));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
