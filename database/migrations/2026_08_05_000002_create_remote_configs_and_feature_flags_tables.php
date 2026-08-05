<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('remote_configs')) {
            Schema::create('remote_configs', function (Blueprint $table) {
                $table->id();
                $table->string('app_name', 50)->default('all')->index();
                $table->string('platform', 20)->default('all')->index();
                $table->string('key', 100)->unique();
                $table->json('value');
                $table->string('type', 20)->default('json'); // boolean, string, integer, json
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });

            // Seed default remote config entries
            $now = now();
            DB::table('remote_configs')->insert([
                [
                    'app_name' => 'all',
                    'platform' => 'all',
                    'key' => 'fashion_fit',
                    'value' => json_encode([
                        'enabled' => true,
                        'ai_model' => 'silhouette_v1',
                        'confidence_threshold' => 0.70,
                        'min_width' => 720,
                        'min_height' => 1280,
                        'required_photo_count' => 2,
                        'front_photo_required' => true,
                        'side_photo_required' => true,
                        'max_upload_size_kb' => 10240,
                        'measurement_confirmation_rules' => ['confirm_below_confidence' => 0.85],
                        'provider_sharing_default' => false,
                        'photo_sharing_default' => false,
                        'privacy_defaults' => ['ai_consent_required' => true, 'auto_purge_days' => 90],
                        'image_validation_rules' => ['require_full_body' => true, 'lighting_check' => true],
                        'retake_threshold' => 0.60,
                    ]),
                    'type' => 'json',
                    'description' => 'Dynamic Fashion Fit behavior rules and constraints',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'app_name' => 'all',
                    'platform' => 'all',
                    'key' => 'marketplace_modules',
                    'value' => json_encode([
                        'stylist_marketplace' => true,
                        'creator_commerce' => true,
                        'events' => true,
                        'rentals' => true,
                        'order_anywhere' => true,
                        'medical_courier' => true,
                        'driver_scanner' => true,
                        'ai_features' => true,
                    ]),
                    'type' => 'json',
                    'description' => 'Global marketplace module configuration',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        if (!Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('enabled_globally')->default(true)->index();
                $table->json('rules')->nullable(); // Target rules
                $table->timestamps();
            });

            $now = now();
            $flags = [
                ['key' => 'fashion_fit', 'name' => 'Fashion Fit AI', 'enabled' => true],
                ['key' => 'virtual_try_on', 'name' => 'Virtual Try-On', 'enabled' => true],
                ['key' => 'stylist_requests', 'name' => 'Stylist Requests', 'enabled' => true],
                ['key' => 'creator_commerce', 'name' => 'Creator Commerce', 'enabled' => true],
                ['key' => 'events', 'name' => 'Events Ticket Hub', 'enabled' => true],
                ['key' => 'rentals', 'name' => 'Rentals Marketplace', 'enabled' => true],
                ['key' => 'order_anywhere', 'name' => 'Order Anywhere', 'enabled' => true],
                ['key' => 'ai_copilot', 'name' => 'AI Operations Copilot', 'enabled' => true],
                ['key' => 'ai_recommendations', 'name' => 'AI Size & Style Recs', 'enabled' => true],
                ['key' => 'medical_courier', 'name' => 'Medical Courier Fleet', 'enabled' => true],
                ['key' => 'driver_marketplace', 'name' => 'Driver Marketplace', 'enabled' => true],
                ['key' => 'load_board', 'name' => 'Best Loads Board', 'enabled' => true],
                ['key' => 'dispatcher', 'name' => 'Live Dispatcher', 'enabled' => true],
                ['key' => 'experimental_features', 'name' => 'Experimental Features', 'enabled' => false],
            ];

            foreach ($flags as $f) {
                DB::table('feature_flags')->insert([
                    'key' => $f['key'],
                    'name' => $f['name'],
                    'description' => 'Runtime feature toggle for ' . $f['name'],
                    'enabled_globally' => $f['enabled'],
                    'rules' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('remote_configs');
    }
};
