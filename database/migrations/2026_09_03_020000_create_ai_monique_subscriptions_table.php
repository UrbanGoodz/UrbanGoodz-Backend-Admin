<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_monique_subscriptions')) {
            Schema::create('ai_monique_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('account_type', 32)->default('vendor'); // 'vendor', 'business', 'admin'
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->string('status', 32)->default('trial_active'); // 'trial_active', 'trial_expired', 'active_paid', 'cancelled', 'disabled'
                $table->timestamp('trial_start_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->boolean('auto_continue')->default(true);
                $table->decimal('price_per_month', 10, 2)->default(49.00);
                $table->string('post_trial_policy', 32)->default('auto_charge'); // 'auto_charge', 'explicit_opt_in', 'auto_disable'
                $table->string('stripe_customer_id', 128)->nullable();
                $table->string('stripe_subscription_id', 128)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('reactivated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_monique_subscriptions');
    }
};
