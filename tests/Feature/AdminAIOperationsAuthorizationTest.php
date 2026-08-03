<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AiWorkforceSettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAIOperationsAuthorizationTest extends TestCase
{
    public function test_ai_operations_routes_enforce_the_established_granular_permissions(): void
    {
        $expected = [
            'admin.urban-goodz.ai-operations.index' => 'module:urban_goodz_ai_settings_view',
            'admin.urban-goodz.ai-operations.feature-controls' => 'module:urban_goodz_ai_settings_view',
            'admin.urban-goodz.ai-operations.feature-controls.update' => 'module:urban_goodz_ai_settings_manage',
            'admin.urban-goodz.ai-operations.logs' => 'module:urban_goodz_ai_usage_view',
            'admin.urban-goodz.ai-operations.usage' => 'module:urban_goodz_ai_usage_view',
            'admin.urban-goodz.ai-operations.test' => 'module:urban_goodz_ai_copilot_use',
            'admin.urban-goodz.ai-operations.test.run' => 'module:urban_goodz_ai_copilot_use',
            'admin.urban-goodz.ai-operations.load-sourcing' => 'module:urban_goodz_ai_copilot_use',
            'admin.urban-goodz.ai-operations.workforce.index' => 'module:urban_goodz_ai_copilot_use',
            'admin.urban-goodz.ai-operations.workforce.settings.update' => 'module:urban_goodz_ai_settings_manage',
            'admin.urban-goodz.ai-chief-of-staff' => 'module:urban_goodz_ai_copilot_use',
        ];

        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains($middleware, $route->gatherMiddleware(), "{$name} lacks {$middleware}");
        }
    }

    public function test_ai_operations_write_routes_have_unique_names_and_correct_methods(): void
    {
        $featureUpdate = Route::getRoutes()->getByName('admin.urban-goodz.ai-operations.feature-controls.update');
        $testRun = Route::getRoutes()->getByName('admin.urban-goodz.ai-operations.test.run');

        $this->assertSame(['POST'], $featureUpdate->methods());
        $this->assertSame(['POST'], $testRun->methods());

        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => str_starts_with((string) $name, 'admin.urban-goodz.ai-operations'));

        $this->assertSame($names->count(), $names->unique()->count(), 'AI Operations contains duplicate route names.');
        $this->assertFalse($names->contains('admin.urban-goodz.ai-operations.'));
    }

    public function test_copilot_generation_is_post_only_and_protected(): void
    {
        $generate = Route::getRoutes()->getByName('admin.urban-goodz.ai-copilot.generate');

        $this->assertSame(['POST'], $generate->methods());
        $this->assertContains('module:urban_goodz_ai_copilot_use', $generate->gatherMiddleware());

        $blockedGetRoute = collect(Route::getRoutes()->getRoutes())->first(function ($route) {
            return $route->uri() === 'admin/urban-goodz/ai-copilot/generate'
                && in_array('GET', $route->methods(), true);
        });

        $this->assertNotNull($blockedGetRoute);
        $this->assertSame(
            'App\\Http\\Controllers\\Admin\\UrbanGoodz\\AiCopilotController@generateMethodNotAllowed',
            $blockedGetRoute->getActionName()
        );
    }

    public function test_copilot_configuration_writes_require_ai_settings_manage(): void
    {
        foreach ([
            'admin.urban-goodz.ai-copilot.module-settings.save',
            'admin.urban-goodz.ai-copilot.risk-rules.save',
            'admin.urban-goodz.ai-copilot.risk-rules.update',
            'admin.urban-goodz.ai-copilot.risk-rules.delete',
            'admin.urban-goodz.ai-copilot.risk-rules.toggle',
            'admin.urban-goodz.ai-copilot.action-logs.rollback',
            'admin.urban-goodz.ai-copilot.settings.save',
        ] as $name) {
            $this->assertContains(
                'module:urban_goodz_ai_settings_manage',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "{$name} lacks the manage boundary"
            );
        }
    }

    public function test_copilot_page_renders_post_forms_instead_of_mutating_links(): void
    {
        $blade = file_get_contents(resource_path('views/admin-views/urban-goodz/ai-copilot/index.blade.php'));

        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'method="POST" action="{{ route(\'admin.urban-goodz.ai-copilot.generate\') }}"'));
        $this->assertGreaterThanOrEqual(2, substr_count($blade, '@csrf'));
        $this->assertStringNotContainsString('href="{{ route(\'admin.urban-goodz.ai-copilot.generate', $blade);
    }

    public function test_workforce_settings_are_persisted_and_applied_with_environment_kill_switch_precedence(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('ai_copilot_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Config::set('urban_goodz.ai_workforce', [
            'enabled' => false,
            'global_kill_switch' => false,
            'demand_thresholds' => [],
            'outreach' => [],
        ]);

        $service = app(AiWorkforceSettingsService::class);
        $saved = $service->save([
            'enabled' => true,
            'global_kill_switch' => false,
            'demand_min_requests' => 7,
            'demand_min_customers' => 4,
            'demand_window_days' => 14,
            'demand_cooldown_days' => 5,
            'sender_name' => 'Urban Goodz AI',
            'sender_email' => 'ai-test@urban-goodz.test',
            'max_attempts' => 3,
            'hours_start' => '09:30',
            'hours_end' => '16:30',
        ]);

        $this->assertTrue($saved['enabled']);
        $this->assertSame(7, $saved['demand_thresholds']['min_requests']);
        $this->assertSame('Urban Goodz AI', $saved['outreach']['sender_name']);
        $this->assertSame('1', DB::table('ai_copilot_settings')->where('key', 'ai_workforce_enabled')->value('value'));

        Config::set('urban_goodz.ai_workforce.global_kill_switch', true);
        $this->assertTrue($service->save(['global_kill_switch' => false])['global_kill_switch']);
    }
}
