<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_driver_earnings', 'load_id')) {
                $table->unsignedBigInteger('load_id')->nullable()->after('delivery_man_id');

                $table->foreign('load_id', 'ug_earn_load_fk')
                      ->references('id')->on('urban_goodz_load_board_loads')
                      ->nullOnDelete();

                $table->index('load_id', 'ug_earn_load_idx');
            }
        });

        if (!Schema::hasTable('urban_goodz_load_board_bids')) {
            Schema::create('urban_goodz_load_board_bids', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('load_id');
                $table->foreign('load_id', 'ug_bid_load_fk')
                      ->references('id')->on('urban_goodz_load_board_loads')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('driver_id');
                $table->foreign('driver_id', 'ug_bid_driver_fk')
                      ->references('id')->on('delivery_men')
                      ->cascadeOnDelete();
                $table->decimal('bid_amount', 10, 2);
                $table->text('bid_message')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('responded_at')->nullable();
                $table->unsignedBigInteger('responded_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['load_id', 'driver_id'], 'ug_bid_load_driver_uniq');
                $table->index(['load_id', 'status'], 'ug_bid_load_status_idx');
                $table->index('driver_id', 'ug_bid_driver_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_dispatch_audit_logs')) {
            Schema::create('urban_goodz_dispatch_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dispatch_company_id');
                $table->foreign('dispatch_company_id', 'ug_da_company_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('dispatcher_id')->nullable();
                $table->foreign('dispatcher_id', 'ug_da_dispatcher_fk')
                      ->references('id')->on('urban_goodz_business_client_users')
                      ->nullOnDelete();
                $table->unsignedBigInteger('load_id')->nullable();
                $table->foreign('load_id', 'ug_da_load_fk')
                      ->references('id')->on('urban_goodz_load_board_loads')
                      ->nullOnDelete();
                $table->string('event_type');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->json('context')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_type')->nullable();
                $table->text('notes')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamps();

                $table->index(['dispatch_company_id', 'event_type'], 'ug_da_company_event_idx');
                $table->index(['load_id', 'event_type'], 'ug_da_load_event_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_dispatch_audit_logs');
        Schema::dropIfExists('urban_goodz_load_board_bids');

        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('ug_earn_load_fk');
            }
            $table->dropIndex('ug_earn_load_idx');
            $table->dropColumn('load_id');
        });
    }
};
