<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend delivery_men with ownership, shared network, and vendor pay fields
        Schema::table('delivery_men', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_men', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('business_client_id')->index();
            }
            if (!Schema::hasColumn('delivery_men', 'ownership_type')) {
                $table->string('ownership_type', 32)->default('urban_goodz')->after('vendor_id'); // urban_goodz, vendor_owned, business_owned
            }
            if (!Schema::hasColumn('delivery_men', 'available_for_marketplace')) {
                $table->boolean('available_for_marketplace')->default(false)->after('ownership_type');
            }
            if (!Schema::hasColumn('delivery_men', 'network_dispatch_status')) {
                $table->string('network_dispatch_status', 32)->default('available')->after('available_for_marketplace'); // available, on_business_job, available_for_ug, offline, pending_approval, suspended
            }
            if (!Schema::hasColumn('delivery_men', 'pay_model')) {
                $table->string('pay_model', 32)->default('per_order')->after('network_dispatch_status'); // per_order, per_mile, flat_route, hourly, percentage
            }
            if (!Schema::hasColumn('delivery_men', 'pay_rate')) {
                $table->decimal('pay_rate', 10, 2)->default(15.00)->after('pay_model');
            }
            if (!Schema::hasColumn('delivery_men', 'platform_fee_percent')) {
                $table->decimal('platform_fee_percent', 5, 2)->default(5.00)->after('pay_rate');
            }
            if (!Schema::hasColumn('delivery_men', 'platform_fee_fixed')) {
                $table->decimal('platform_fee_fixed', 10, 2)->default(1.50)->after('platform_fee_percent');
            }
            if (!Schema::hasColumn('delivery_men', 'admin_approval_status')) {
                $table->string('admin_approval_status', 32)->default('approved')->after('platform_fee_fixed'); // pending, approved, rejected, suspended
            }
            if (!Schema::hasColumn('delivery_men', 'approved_by_admin_at')) {
                $table->timestamp('approved_by_admin_at')->nullable()->after('admin_approval_status');
            }
        });

        // 2. Recruiting campaigns table
        if (!Schema::hasTable('urban_goodz_driver_recruiting_campaigns')) {
            Schema::create('urban_goodz_driver_recruiting_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_name', 128);
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->integer('target_shortage_count')->default(10);
                $table->decimal('referral_bonus_amount', 10, 2)->default(50.00);
                $table->decimal('sign_on_bonus_amount', 10, 2)->default(100.00);
                $table->string('status', 32)->default('active'); // active, paused, completed
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Driver referrals table
        if (!Schema::hasTable('urban_goodz_driver_referrals')) {
            Schema::create('urban_goodz_driver_referrals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('referrer_id')->index();
                $table->string('referrer_type', 32)->default('driver'); // driver, vendor, business
                $table->string('referred_name', 128)->nullable();
                $table->string('referred_phone', 32)->index();
                $table->string('referred_email', 128)->nullable();
                $table->unsignedBigInteger('referred_driver_id')->nullable()->index();
                $table->string('status', 32)->default('invited'); // invited, applied, approved, active, rewarded
                $table->decimal('reward_amount', 10, 2)->default(50.00);
                $table->timestamp('rewarded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_driver_referrals');
        Schema::dropIfExists('urban_goodz_driver_recruiting_campaigns');

        Schema::table('delivery_men', function (Blueprint $table) {
            $cols = [
                'vendor_id',
                'ownership_type',
                'available_for_marketplace',
                'network_dispatch_status',
                'pay_model',
                'pay_rate',
                'platform_fee_percent',
                'platform_fee_fixed',
                'admin_approval_status',
                'approved_by_admin_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('delivery_men', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
