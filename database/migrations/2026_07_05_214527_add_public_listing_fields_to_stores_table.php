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
            if (!Schema::hasColumn('stores', 'business_status')) {
                $table->string('business_status')->default('active_partner');
            }
            if (!Schema::hasColumn('stores', 'contract_status')) {
                $table->string('contract_status')->default('contracted');
            }
            if (!Schema::hasColumn('stores', 'vendor_admin_status')) {
                $table->string('vendor_admin_status')->default('active');
            }
            if (!Schema::hasColumn('stores', 'banking_status')) {
                $table->string('banking_status')->default('active');
            }
            if (!Schema::hasColumn('stores', 'subscription_status')) {
                $table->string('subscription_status')->default('active');
            }
            if (!Schema::hasColumn('stores', 'admin_approval_status')) {
                $table->string('admin_approval_status')->default('approved');
            }
            if (!Schema::hasColumn('stores', 'badge_status')) {
                $table->string('badge_status')->default('urban_goodz_partner');
            }
            if (!Schema::hasColumn('stores', 'fulfillment_mode')) {
                $table->string('fulfillment_mode')->default('direct_vendor_order');
            }
            
            if (!Schema::hasColumn('stores', 'is_public_sourced')) {
                $table->boolean('is_public_sourced')->default(false);
            }
            if (!Schema::hasColumn('stores', 'is_claimed')) {
                $table->boolean('is_claimed')->default(true);
            }
            if (!Schema::hasColumn('stores', 'is_partner')) {
                $table->boolean('is_partner')->default(true);
            }
            if (!Schema::hasColumn('stores', 'can_direct_checkout')) {
                $table->boolean('can_direct_checkout')->default(true);
            }
            if (!Schema::hasColumn('stores', 'requires_admin_quote')) {
                $table->boolean('requires_admin_quote')->default(false);
            }
            if (!Schema::hasColumn('stores', 'vendor_admin_account_created')) {
                $table->boolean('vendor_admin_account_created')->default(true);
            }
            if (!Schema::hasColumn('stores', 'vendor_has_logged_in')) {
                $table->boolean('vendor_has_logged_in')->default(true);
            }
            if (!Schema::hasColumn('stores', 'partner_badge_enabled')) {
                $table->boolean('partner_badge_enabled')->default(true);
            }
            if (!Schema::hasColumn('stores', 'order_anywhere_enabled')) {
                $table->boolean('order_anywhere_enabled')->default(true);
            }

            if (!Schema::hasColumn('stores', 'invited_at')) {
                $table->timestamp('invited_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'vendor_panel_activated_at')) {
                $table->timestamp('vendor_panel_activated_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'banking_submitted_at')) {
                $table->timestamp('banking_submitted_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'banking_verified_at')) {
                $table->timestamp('banking_verified_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'contracted_at')) {
                $table->timestamp('contracted_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'subscription_activated_at')) {
                $table->timestamp('subscription_activated_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'admin_approved_at')) {
                $table->timestamp('admin_approved_at')->nullable();
            }
            if (!Schema::hasColumn('stores', 'partner_badge_enabled_at')) {
                $table->timestamp('partner_badge_enabled_at')->nullable();
            }
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
