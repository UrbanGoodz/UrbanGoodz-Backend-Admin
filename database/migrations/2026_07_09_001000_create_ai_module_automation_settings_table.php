<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_module_automation_settings')) {
            Schema::create('ai_module_automation_settings', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50)->unique();
                $table->boolean('enabled')->default(false);
                $table->string('automation_mode', 50)->nullable();
                $table->decimal('min_confidence_score', 5, 2)->default(0.70);
                $table->decimal('max_auto_action_amount', 12, 2)->nullable();
                $table->json('allowed_zones')->nullable();
                $table->json('allowed_categories')->nullable();
                $table->string('max_risk_level', 20)->default('low');
                $table->json('escalation_rules')->nullable();
                $table->json('approval_required_rules')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ai_module_automation_settings')) {
            $existing = DB::table('ai_module_automation_settings')->count();
            if ($existing === 0) {
                $modules = ['dispatch', 'order_anywhere', 'support', 'package_routes', 'business_courier', 'vendor_ops', 'driver_ops', 'reporting'];
                foreach ($modules as $mod) {
                    DB::table('ai_module_automation_settings')->insert([
                        'module' => $mod,
                        'enabled' => false,
                        'automation_mode' => null,
                        'min_confidence_score' => 0.70,
                        'max_auto_action_amount' => null,
                        'allowed_zones' => null,
                        'allowed_categories' => null,
                        'max_risk_level' => 'low',
                        'escalation_rules' => null,
                        'approval_required_rules' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_module_automation_settings')) {
            Schema::dropIfExists('ai_module_automation_settings');
        }
    }
};
