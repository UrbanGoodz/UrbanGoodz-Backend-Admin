<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('business_status')->default('active_partner');
            $table->string('contract_status')->default('contracted');
            $table->string('vendor_admin_status')->default('active');
            $table->string('banking_status')->default('active');
            $table->string('subscription_status')->default('active');
            $table->string('admin_approval_status')->default('approved');
            $table->string('badge_status')->default('urban_goodz_partner');
            $table->string('fulfillment_mode')->default('direct_vendor_order');
            
            $table->boolean('is_public_sourced')->default(false);
            $table->boolean('is_claimed')->default(true);
            $table->boolean('is_partner')->default(true);
            $table->boolean('can_direct_checkout')->default(true);
            $table->boolean('requires_admin_quote')->default(false);
            $table->boolean('vendor_admin_account_created')->default(true);
            $table->boolean('vendor_has_logged_in')->default(true);
            $table->boolean('partner_badge_enabled')->default(true);
            $table->boolean('order_anywhere_enabled')->default(true);

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('vendor_panel_activated_at')->nullable();
            $table->timestamp('banking_submitted_at')->nullable();
            $table->timestamp('banking_verified_at')->nullable();
            $table->timestamp('contracted_at')->nullable();
            $table->timestamp('subscription_activated_at')->nullable();
            $table->timestamp('admin_approved_at')->nullable();
            $table->timestamp('partner_badge_enabled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'business_status',
                'contract_status',
                'vendor_admin_status',
                'banking_status',
                'subscription_status',
                'admin_approval_status',
                'badge_status',
                'fulfillment_mode',
                'is_public_sourced',
                'is_claimed',
                'is_partner',
                'can_direct_checkout',
                'requires_admin_quote',
                'vendor_admin_account_created',
                'vendor_has_logged_in',
                'partner_badge_enabled',
                'order_anywhere_enabled',
                'invited_at',
                'claimed_at',
                'vendor_panel_activated_at',
                'banking_submitted_at',
                'banking_verified_at',
                'contracted_at',
                'subscription_activated_at',
                'admin_approved_at',
                'partner_badge_enabled_at'
            ]);
        });
    }
};
