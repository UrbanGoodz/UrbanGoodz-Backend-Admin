<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_anywhere_requests')) {
            return;
        }

        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('order_anywhere_requests', 'quote_amount')) {
                $table->decimal('quote_amount', 12, 2)->nullable()->after('budget_estimate');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'authorized_amount')) {
                $table->decimal('authorized_amount', 12, 2)->nullable()->after('quote_amount');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'final_amount')) {
                $table->decimal('final_amount', 12, 2)->nullable()->after('authorized_amount');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'captured_amount')) {
                $table->decimal('captured_amount', 12, 2)->nullable()->after('final_amount');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'refunded_amount')) {
                $table->decimal('refunded_amount', 12, 2)->default(0)->after('captured_amount');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_status')) {
                $table->string('payment_status')->default('unquoted')->index()->after('status');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'authorization_reference')) {
                $table->string('authorization_reference')->nullable()->index()->after('payment_method');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'capture_reference')) {
                $table->string('capture_reference')->nullable()->index()->after('authorization_reference');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'refund_reference')) {
                $table->string('refund_reference')->nullable()->index()->after('capture_reference');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('refund_reference');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_authorized_at')) {
                $table->timestamp('payment_authorized_at')->nullable()->after('receipt_path');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_captured_at')) {
                $table->timestamp('payment_captured_at')->nullable()->after('payment_authorized_at');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_refunded_at')) {
                $table->timestamp('payment_refunded_at')->nullable()->after('payment_captured_at');
            }
        });
    }

    public function down(): void
    {
    }
};
