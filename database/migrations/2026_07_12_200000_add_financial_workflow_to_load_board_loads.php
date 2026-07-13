<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'customer_price')) {
                $table->decimal('customer_price', 10, 2)->nullable()->after('payout_amount');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'platform_margin')) {
                $table->decimal('platform_margin', 10, 2)->nullable()->after('customer_price');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'dispatcher_incentive')) {
                $table->decimal('dispatcher_incentive', 10, 2)->nullable()->after('platform_margin');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'source_cost')) {
                $table->decimal('source_cost', 10, 2)->nullable()->after('dispatcher_incentive');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'processing_fee')) {
                $table->decimal('processing_fee', 10, 2)->nullable()->after('source_cost');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'accessorials')) {
                $table->decimal('accessorials', 10, 2)->default(0)->after('processing_fee');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('assigned_by');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'driver_payout_amount')) {
                $table->decimal('driver_payout_amount', 10, 2)->nullable()->after('payout_amount');
            }
        });

        if (!Schema::hasTable('urban_goodz_load_board_audit_logs')) {
            Schema::create('urban_goodz_load_board_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('load_id')->index();
                $table->string('event_type');
                $table->string('old_value')->nullable();
                $table->string('new_value')->nullable();
                $table->json('context')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_type')->default('admin');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['load_id', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
            $table->dropColumn([
                'customer_price', 'platform_margin', 'dispatcher_incentive',
                'source_cost', 'processing_fee', 'accessorials',
                'reviewed_by', 'reviewed_at', 'cancelled_at', 'cancellation_reason',
                'driver_payout_amount',
            ]);
        });

        Schema::dropIfExists('urban_goodz_load_board_audit_logs');
    }
};
