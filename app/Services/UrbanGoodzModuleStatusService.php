<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UrbanGoodzModuleStatusService
{
    private const MODULE_REGISTRY = [
        'order-anywhere' => [
            'label' => 'Order Anywhere',
            'table' => 'order_anywhere_requests',
            'permission' => 'urban_goodz_order_anywhere_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'payments' => [
            'label' => 'Payment Center',
            'table' => 'urban_goodz_payment_ledgers',
            'permission' => 'urban_goodz_payments_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'fashion-fit' => [
            'label' => 'Fashion Fit',
            'table' => 'urban_goodz_measurement_requests',
            'permission' => 'urban_goodz_fashion_fit_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'rentals' => [
            'label' => 'Rentals',
            'table' => 'urban_goodz_rental_bookings',
            'permission' => 'urban_goodz_rentals_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'ai-concierge' => [
            'label' => 'AI Concierge',
            'table' => 'urban_goodz_ai_conversations',
            'permission' => 'urban_goodz_ai_concierge_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'ai-copilot' => [
            'label' => 'AI Ops Copilot',
            'table' => 'ai_copilot_recommendations',
            'permission' => 'urban_goodz_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'load-board' => [
            'label' => 'Load Board',
            'table' => 'urban_goodz_load_board_loads',
            'permission' => 'urban_goodz_load_board_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'medical-courier' => [
            'label' => 'Medical Courier',
            'table' => 'urban_goodz_medical_courier_jobs',
            'permission' => 'urban_goodz_medical_courier_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'logistics' => [
            'label' => 'Logistics',
            'table' => 'urban_goodz_logistics_jobs',
            'permission' => 'urban_goodz_logistics_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'earn-money' => [
            'label' => 'Earn Money',
            'table' => 'urban_goodz_earn_money_opportunities',
            'permission' => 'urban_goodz_earn_money_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'events' => [
            'label' => 'Events',
            'table' => 'urban_goodz_events',
            'permission' => 'urban_goodz_events',
            'has_controller' => true,
            'has_views' => false,
            'has_api' => true,
        ],
        'book-anything' => [
            'label' => 'Book Anything',
            'table' => 'urban_goodz_service_requests',
            'permission' => 'urban_goodz_book_anything',
            'has_controller' => true,
            'has_views' => false,
            'has_api' => true,
        ],
        'discovery' => [
            'label' => 'Business Discovery',
            'table' => 'urban_goodz_discovery_searches',
            'permission' => 'urban_goodz_discovery_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'business-types' => [
            'label' => 'Business Types',
            'table' => 'urban_goodz_business_types',
            'permission' => 'urban_goodz_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'capabilities' => [
            'label' => 'Capabilities',
            'table' => 'urban_goodz_capabilities',
            'permission' => 'urban_goodz_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'community' => [
            'label' => 'Community Marketplace',
            'table' => 'urban_goodz_community_posts',
            'permission' => 'urban_goodz_community',
            'has_controller' => true,
            'has_views' => false,
            'has_api' => false,
        ],
        'creators' => [
            'label' => 'Creator Commerce',
            'table' => 'urban_goodz_creator_applications',
            'permission' => 'urban_goodz_creator_commerce_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
        'spotlight' => [
            'label' => 'Black-Owned Spotlight',
            'table' => 'urban_goodz_spotlight_businesses',
            'permission' => 'urban_goodz_spotlight',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'plus' => [
            'label' => 'Urban Goodz+',
            'table' => 'urban_goodz_plus_memberships',
            'permission' => 'urban_goodz_plus',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => false,
        ],
        'business-clients' => [
            'label' => 'Business Clients',
            'table' => 'urban_goodz_business_clients',
            'permission' => 'urban_goodz_business_clients_view',
            'has_controller' => true,
            'has_views' => true,
            'has_api' => true,
        ],
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $results = [];
        foreach (self::MODULE_REGISTRY as $slug => $config) {
            $results[$slug] = self::check($slug, $config);
        }

        self::$cache = $results;
        return $results;
    }

    public static function get(string $slug): ?array
    {
        $all = self::all();
        return $all[$slug] ?? null;
    }

    public static function isLive(string $slug): bool
    {
        $status = self::get($slug);
        return $status && in_array($status['readiness'], ['live', 'db_backed']);
    }

    public static function isAvailable(string $slug): bool
    {
        $status = self::get($slug);
        return $status && $status['table_exists'] && $status['has_controller'];
    }

    public static function liveModules(): array
    {
        return array_filter(self::all(), fn($m) => $m['readiness'] === 'live');
    }

    public static function dbBackedModules(): array
    {
        return array_filter(self::all(), fn($m) => $m['readiness'] === 'db_backed');
    }

    public static function stubModules(): array
    {
        return array_filter(self::all(), fn($m) => in_array($m['readiness'], ['stub', 'no_table']));
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    private static function check(string $slug, array $config): array
    {
        $tableExists = Schema::hasTable($config['table']);
        $recordCount = 0;
        if ($tableExists) {
            try {
                $recordCount = DB::table($config['table'])->count();
            } catch (\Exception $e) {
                $recordCount = 0;
            }
        }

        $hasCustomViews = $config['has_views'];
        $hasApi = $config['has_api'];
        $hasController = $config['has_controller'];

        if (!$tableExists) {
            $readiness = 'no_table';
        } elseif ($recordCount > 0 && $hasCustomViews && $hasController) {
            $readiness = 'live';
        } elseif ($tableExists && $hasController) {
            $readiness = 'db_backed';
        } elseif ($tableExists) {
            $readiness = 'stub';
        } else {
            $readiness = 'stub';
        }

        return [
            'slug' => $slug,
            'label' => $config['label'],
            'table' => $config['table'],
            'permission' => $config['permission'],
            'table_exists' => $tableExists,
            'record_count' => $recordCount,
            'has_controller' => $hasController,
            'has_views' => $hasCustomViews,
            'has_api' => $hasApi,
            'readiness' => $readiness,
            'status_label' => self::readinessLabel($readiness),
        ];
    }

    private static function readinessLabel(string $readiness): string
    {
        return match ($readiness) {
            'live' => 'Live',
            'db_backed' => 'DB-Backed',
            'stub' => 'Stub',
            'no_table' => 'No Table',
            default => 'Unknown',
        };
    }
}
