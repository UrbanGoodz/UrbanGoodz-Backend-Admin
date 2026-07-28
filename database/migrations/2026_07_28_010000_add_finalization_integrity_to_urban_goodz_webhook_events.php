<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            $table->string('internal_reference')->nullable()->after('charge_id');
            $table->string('operation')->nullable()->after('internal_reference');
            $table->unsignedBigInteger('amount_minor')->nullable()->after('operation');
            $table->string('currency', 3)->nullable()->after('amount_minor');
            $table->char('allocation_hash', 64)->nullable()->after('currency');
            $table->unsignedInteger('attempt_count')->default(1)->after('failure_type');
            $table->unsignedInteger('duplicate_count')->default(0)->after('attempt_count');
            $table->json('result')->nullable()->after('duplicate_count');

            $table->index(
                ['provider', 'payment_intent_id', 'operation'],
                'ugwe_provider_payment_operation_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            $table->dropIndex('ugwe_provider_payment_operation_index');
            $table->dropColumn([
                'internal_reference',
                'operation',
                'amount_minor',
                'currency',
                'allocation_hash',
                'attempt_count',
                'duplicate_count',
                'result',
            ]);
        });
    }
};
