<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_copilot_settings')) {
            Schema::create('ai_copilot_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            'ai_ops_enabled' => 'recommend_only',
            'ai_auto_dispatch_enabled' => '0',
            'ai_auto_customer_support_enabled' => '0',
            'ai_auto_driver_support_enabled' => '0',
            'ai_auto_vendor_support_enabled' => '0',
            'ai_auto_order_anywhere_triage_enabled' => '0',
            'ai_auto_package_route_assignment_enabled' => '0',
            'ai_auto_business_courier_assignment_enabled' => '0',
            'ai_escalate_high_risk_to_admin' => '1',
            'ai_audit_log_enabled' => '1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('ai_copilot_settings')->upsert(
                ['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()],
                'key'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_copilot_settings')) {
            Schema::dropIfExists('ai_copilot_settings');
        }
    }
};
