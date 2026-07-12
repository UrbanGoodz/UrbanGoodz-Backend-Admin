<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add dispatch company type + territory to business_clients
        Schema::table('urban_goodz_business_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_business_clients', 'account_type')) {
                $table->string('account_type')->default('business')->index()->after('company_name');
            }
            if (!Schema::hasColumn('urban_goodz_business_clients', 'territory_states')) {
                $table->json('territory_states')->nullable()->after('settings');
            }
            if (!Schema::hasColumn('urban_goodz_business_clients', 'territory_corridors')) {
                $table->json('territory_corridors')->nullable()->after('territory_states');
            }
            if (!Schema::hasColumn('urban_goodz_business_clients', 'dispatch_default_commission_rate')) {
                $table->decimal('dispatch_default_commission_rate', 5, 2)->nullable()->after('territory_corridors');
            }
        });

        // 2. Add dispatcher fields to load board loads
        Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'dispatch_company_id')) {
                $table->unsignedBigInteger('dispatch_company_id')->nullable()->index()->after('business_client_id');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'dispatcher_id')) {
                $table->unsignedBigInteger('dispatcher_id')->nullable()->index()->after('dispatch_company_id');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'dispatch_status')) {
                $table->string('dispatch_status')->default('unassigned')->after('dispatcher_id');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'commission_amount')) {
                $table->decimal('commission_amount', 10, 2)->nullable()->after('dispatch_status');
            }
            if (!Schema::hasColumn('urban_goodz_load_board_loads', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_amount');
            }
        });

        // 3. Create dispatch commission tracking table
        if (!Schema::hasTable('urban_goodz_dispatch_commissions')) {
            Schema::create('urban_goodz_dispatch_commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dispatch_company_id')->index();
                $table->unsignedBigInteger('dispatcher_id')->nullable()->index();
                $table->unsignedBigInteger('load_id')->index();
                $table->decimal('load_payout', 10, 2);
                $table->decimal('commission_rate', 5, 2);
                $table->decimal('commission_amount', 10, 2);
                $table->string('status')->default('pending'); // pending, approved, paid, disputed
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['dispatch_company_id', 'status']);
                $table->index(['dispatcher_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('urban_goodz_business_clients', function (Blueprint $table) {
            $table->dropColumn([
                'account_type', 'territory_states', 'territory_corridors',
                'dispatch_default_commission_rate',
            ]);
        });

        Schema::table('urban_goodz_load_board_loads', function (Blueprint $table) {
            $table->dropColumn([
                'dispatch_company_id', 'dispatcher_id', 'dispatch_status',
                'commission_amount', 'commission_rate',
            ]);
        });

        Schema::dropIfExists('urban_goodz_dispatch_commissions');
    }
};
