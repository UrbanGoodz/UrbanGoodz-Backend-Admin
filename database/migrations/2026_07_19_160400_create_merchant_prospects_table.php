<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('merchant_prospects')) {
            Schema::create('merchant_prospects', function (Blueprint $table) {
                $table->id();
                $table->string('business_name');
                $table->string('business_name_normalized')->index();
                $table->string('category')->nullable();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('zone')->nullable();
                $table->string('website')->nullable();
                $table->string('domain')->nullable()->index();
                $table->string('public_email')->nullable();
                $table->string('public_phone')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('data_source')->nullable(); // order_anywhere, manual, import
                $table->string('source_url')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->decimal('confidence_score', 5, 4)->nullable();
                $table->decimal('prospect_score', 5, 2)->nullable();
                $table->string('prospect_status')->default('new'); // new, researching, qualified, contacted, engaged, applied, converted, disqualified, opted_out
                $table->unsignedInteger('order_anywhere_request_count')->default(0);
                $table->unsignedInteger('unique_customer_count')->default(0);
                $table->json('requested_categories')->nullable();
                $table->date('first_demand_date')->nullable();
                $table->date('latest_demand_date')->nullable();
                $table->decimal('estimated_demand_value', 12, 2)->default(0);
                $table->string('campaign_status')->default('none'); // none, pending_approval, active, paused, completed, stopped
                $table->timestamp('last_contacted_at')->nullable();
                $table->timestamp('next_followup_at')->nullable();
                $table->string('reply_status')->nullable(); // interested, wants_info, wants_meeting, ready_to_apply, not_now, wrong_contact, remove_me, complaint, auto_reply, delivery_failure, unclear, human_review
                $table->boolean('opt_out')->default(false);
                $table->boolean('do_not_contact')->default(false);
                $table->unsignedBigInteger('vendor_application_id')->nullable();
                $table->unsignedBigInteger('converted_vendor_id')->nullable();
                $table->unsignedBigInteger('first_completed_order_id')->nullable();
                $table->decimal('attributed_revenue', 12, 2)->default(0);
                $table->unsignedBigInteger('ai_agent_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('prospect_status');
                $table->index('campaign_status');
                $table->index('opt_out');
                $table->index('do_not_contact');
            });
        }

        // Drop if exists to clean up from a partially failed/failed run
        Schema::dropIfExists('merchant_prospect_order_anywhere');

        Schema::create('merchant_prospect_order_anywhere', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_prospect_id');
            $table->unsignedBigInteger('order_anywhere_request_id');
            $table->timestamps();

            $table->foreign('merchant_prospect_id', 'mp_oa_prospect_fk')
                ->references('id')->on('merchant_prospects')
                ->cascadeOnDelete();

            $table->foreign('order_anywhere_request_id', 'mp_oa_request_fk')
                ->references('id')->on('order_anywhere_requests')
                ->cascadeOnDelete();

            $table->unique(['merchant_prospect_id', 'order_anywhere_request_id'], 'mp_oar_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_prospect_order_anywhere');
        Schema::dropIfExists('merchant_prospects');
    }
};
