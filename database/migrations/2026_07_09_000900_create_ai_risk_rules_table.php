<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_risk_rules')) {
            Schema::create('ai_risk_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_name');
                $table->string('trigger_type');
                $table->string('trigger_operator', 20)->nullable();
                $table->string('trigger_value')->nullable();
                $table->string('risk_level', 20)->default('medium');
                $table->boolean('requires_approval')->default(true);
                $table->string('escalation_action', 50)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ai_risk_rules')) {
            $existing = DB::table('ai_risk_rules')->count();
            if ($existing === 0) {
                $defaults = [
                    ['rule_name' => 'Refund above threshold', 'trigger_type' => 'refund_amount', 'trigger_operator' => '>', 'trigger_value' => '25', 'risk_level' => 'high', 'requires_approval' => true, 'escalation_action' => 'block_action'],
                    ['rule_name' => 'Medical courier exception', 'trigger_type' => 'medical_courier', 'trigger_operator' => null, 'trigger_value' => 'exception', 'risk_level' => 'critical', 'requires_approval' => true, 'escalation_action' => 'notify_admin'],
                    ['rule_name' => 'Payout change', 'trigger_type' => 'payout_change', 'trigger_operator' => null, 'trigger_value' => null, 'risk_level' => 'high', 'requires_approval' => true, 'escalation_action' => 'block_action'],
                    ['rule_name' => 'Virtual card funding', 'trigger_type' => 'card_funding', 'trigger_operator' => null, 'trigger_value' => null, 'risk_level' => 'high', 'requires_approval' => true, 'escalation_action' => 'block_action'],
                    ['rule_name' => 'Alcohol/THC compliance override', 'trigger_type' => 'compliance_override', 'trigger_operator' => null, 'trigger_value' => 'alcohol_thc', 'risk_level' => 'critical', 'requires_approval' => true, 'escalation_action' => 'notify_admin'],
                    ['rule_name' => 'Legal threat detected', 'trigger_type' => 'legal_threat', 'trigger_operator' => null, 'trigger_value' => null, 'risk_level' => 'critical', 'requires_approval' => true, 'escalation_action' => 'notify_admin'],
                    ['rule_name' => 'Fraud/safety issue', 'trigger_type' => 'fraud_safety', 'trigger_operator' => null, 'trigger_value' => null, 'risk_level' => 'critical', 'requires_approval' => true, 'escalation_action' => 'notify_admin'],
                    ['rule_name' => 'Partner status change', 'trigger_type' => 'partner_status', 'trigger_operator' => null, 'trigger_value' => null, 'risk_level' => 'high', 'requires_approval' => true, 'escalation_action' => 'flag_for_review'],
                    ['rule_name' => 'High-risk freight/load-board', 'trigger_type' => 'freight_high_value', 'trigger_operator' => '>', 'trigger_value' => '1000', 'risk_level' => 'high', 'requires_approval' => true, 'escalation_action' => 'flag_for_review'],
                    ['rule_name' => 'Age compliance exception', 'trigger_type' => 'age_compliance', 'trigger_operator' => null, 'trigger_value' => 'override', 'risk_level' => 'high', 'requires_approval' => true, 'escalation_action' => 'flag_for_review'],
                ];
                foreach ($defaults as $rule) {
                    $rule['created_at'] = now();
                    $rule['updated_at'] = now();
                    DB::table('ai_risk_rules')->insert($rule);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_risk_rules')) {
            Schema::dropIfExists('ai_risk_rules');
        }
    }
};
