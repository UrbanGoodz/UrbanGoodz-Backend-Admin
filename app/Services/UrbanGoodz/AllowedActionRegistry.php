<?php

namespace App\Services\UrbanGoodz;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AllowedActionRegistry
{
    private const ACTION_DEFINITIONS = [
        'create_order_anywhere_request' => [
            'roles' => ['customer', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'get_order_anywhere_status' => [
            'roles' => ['customer', 'admin', 'vendor', 'driver', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'authorize_payment' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 1,
            'timeout_seconds' => 60,
        ],
        'submit_stylist_request' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'search_stylists' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_stylist_requests' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'submit_book_anything_request' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'search_service_providers' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_book_anything_status' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'search_rental_assets' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'book_rental_asset' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'get_rental_booking_status' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'search_marketplace' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_marketplace_item' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'list_marketplace_item' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'search_medical_courier_jobs' => [
            'roles' => ['driver', 'admin', 'dispatcher', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_medical_courier_job' => [
            'roles' => ['driver', 'admin', 'dispatcher', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'create_medical_courier_job' => [
            'roles' => ['business_client', 'admin', 'dispatcher'],
            'requires_confirmation' => true,
            'requires_human_review' => true,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'get_medical_courier_status' => [
            'roles' => ['driver', 'admin', 'dispatcher', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'search_load_board' => [
            'roles' => ['driver', 'admin', 'dispatcher'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_load_board_load' => [
            'roles' => ['driver', 'admin', 'dispatcher'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'post_load_to_board' => [
            'roles' => ['admin', 'dispatcher'],
            'requires_confirmation' => true,
            'requires_human_review' => true,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'bid_on_load' => [
            'roles' => ['driver', 'admin', 'dispatcher'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'track_delivery' => [
            'roles' => ['customer', 'driver', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'get_delivery_status' => [
            'roles' => ['customer', 'driver', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'create_delivery_request' => [
            'roles' => ['customer', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'search_creators' => [
            'roles' => ['admin', 'vendor', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_creator_profile' => [
            'roles' => ['admin', 'vendor', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'apply_as_creator' => [
            'roles' => ['customer'],
            'requires_confirmation' => true,
            'requires_human_review' => true,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'get_creator_campaigns' => [
            'roles' => ['customer', 'admin', 'vendor'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'search_community_posts' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'create_community_post' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'get_community_post' => [
            'roles' => ['customer', 'admin'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'search_earn_money_opportunities' => [
            'roles' => ['customer', 'driver', 'admin'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_earn_money_opportunity' => [
            'roles' => ['customer', 'driver', 'admin'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'apply_earn_money_opportunity' => [
            'roles' => ['customer', 'driver'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
        'search_events' => [
            'roles' => ['customer', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 15,
        ],
        'get_event_detail' => [
            'roles' => ['customer', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => false,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
        'register_event_interest' => [
            'roles' => ['customer', 'admin', 'vendor', 'business_client'],
            'requires_confirmation' => true,
            'requires_human_review' => false,
            'idempotent' => true,
            'max_retries' => 3,
            'timeout_seconds' => 30,
        ],
    ];

    public function validateUserCanExecute(string $intentSlug, string $actionName, ?int $userId): array
    {
        if (!isset(self::ACTION_DEFINITIONS[$actionName])) {
            return [
                'allowed' => false,
                'reason' => "Action '{$actionName}' is not registered in the allowed action registry",
                'requires_confirmation' => false,
                'requires_human_review' => false,
            ];
        }

        $definition = self::ACTION_DEFINITIONS[$actionName];

        if ($userId === null) {
            return [
                'allowed' => false,
                'reason' => 'Authentication required',
                'requires_confirmation' => $definition['requires_confirmation'] ?? false,
                'requires_human_review' => $definition['requires_human_review'] ?? false,
            ];
        }

        $userRole = $this->getUserRole($userId);

        if (!in_array($userRole, $definition['roles'], true)) {
            return [
                'allowed' => false,
                'reason' => "Role '{$userRole}' not authorized for action '{$actionName}'",
                'requires_confirmation' => $definition['requires_confirmation'] ?? false,
                'requires_human_review' => $definition['requires_human_review'] ?? false,
            ];
        }

        $idempotencyKey = null;
        if ($definition['idempotent']) {
            $idempotencyKey = $this->generateIdempotencyKey($intentSlug, $actionName, $userId);
            
            $cacheKey = "idempotency:{$idempotencyKey}";
            if (Cache::has($cacheKey)) {
                return [
                    'allowed' => false,
                    'reason' => 'Duplicate action detected - this request was already processed',
                    'requires_confirmation' => $definition['requires_confirmation'] ?? false,
                    'requires_human_review' => $definition['requires_human_review'] ?? false,
                    'idempotency_key' => $idempotencyKey,
                    'duplicate' => true,
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
            'requires_confirmation' => $definition['requires_confirmation'] ?? false,
            'requires_human_review' => $definition['requires_human_review'] ?? false,
            'idempotency_key' => $idempotencyKey,
            'max_retries' => $definition['max_retries'] ?? 3,
            'timeout_seconds' => $definition['timeout_seconds'] ?? 30,
        ];
    }

    public function markIdempotencyKeyUsed(string $idempotencyKey, int $ttlSeconds = 3600): void
    {
        $cacheKey = "idempotency:{$idempotencyKey}";
        Cache::put($cacheKey, true, $ttlSeconds);
    }

    public function checkIdempotencyKey(string $idempotencyKey): bool
    {
        $cacheKey = "idempotency:{$idempotencyKey}";
        return Cache::has($cacheKey);
    }

    public function getActionDefinition(string $actionName): ?array
    {
        return self::ACTION_DEFINITIONS[$actionName] ?? null;
    }

    public function getAllowedActionsForRole(string $role): array
    {
        $allowed = [];
        foreach (self::ACTION_DEFINITIONS as $action => $definition) {
            if (in_array($role, $definition['roles'], true)) {
                $allowed[] = $action;
            }
        }
        return $allowed;
    }

    private function getUserRole(int $userId): string
    {
        $cacheKey = "user_role:{$userId}";
        return Cache::remember($cacheKey, 300, function () use ($userId) {
            if (Admin::where('id', $userId)->exists()) {
                return 'admin';
            }
            
            if (DeliveryMan::where('id', $userId)->exists()) {
                return 'driver';
            }
            
            if (Vendor::where('id', $userId)->exists()) {
                return 'vendor';
            }
            
            if (UrbanGoodzBusinessClientUser::where('id', $userId)->exists()) {
                return 'business_client';
            }
            
            if (User::where('id', $userId)->exists()) {
                return 'customer';
            }
            
            return 'unknown';
        });
    }

    private function generateIdempotencyKey(string $intentSlug, string $actionName, int $userId): string
    {
        $hash = md5("{$intentSlug}|{$actionName}|{$userId}|" . now()->format('Y-m-d H:i'));
        return "idemp_{$intentSlug}_{$actionName}_{$userId}_{$hash}";
    }

    public function validateIntentActionPair(string $intentSlug, string $actionName): bool
    {
        $actionIntents = $this->getIntentForAction($actionName);
        return in_array($intentSlug, $actionIntents, true);
    }

    private function getIntentForAction(string $actionName): array
    {
        $map = [
            'create_order_anywhere_request' => ['order-anywhere'],
            'get_order_anywhere_status' => ['order-anywhere'],
            'authorize_payment' => ['order-anywhere'],
            'submit_stylist_request' => ['fashion-fit'],
            'search_stylists' => ['fashion-fit'],
            'get_stylist_requests' => ['fashion-fit'],
            'submit_book_anything_request' => ['book-services'],
            'search_service_providers' => ['book-services'],
            'get_book_anything_status' => ['book-services'],
            'search_rental_assets' => ['rentals'],
            'book_rental_asset' => ['rentals'],
            'get_rental_booking_status' => ['rentals'],
            'search_marketplace' => ['marketplace-search'],
            'get_marketplace_item' => ['marketplace-search'],
            'list_marketplace_item' => ['marketplace-search'],
            'search_medical_courier_jobs' => ['medical-courier'],
            'get_medical_courier_job' => ['medical-courier'],
            'create_medical_courier_job' => ['medical-courier'],
            'get_medical_courier_status' => ['medical-courier'],
            'search_load_board' => ['load-board'],
            'get_load_board_load' => ['load-board'],
            'post_load_to_board' => ['load-board'],
            'bid_on_load' => ['load-board'],
            'track_delivery' => ['delivery'],
            'get_delivery_status' => ['delivery'],
            'create_delivery_request' => ['delivery'],
            'search_creators' => ['creator-commerce'],
            'get_creator_profile' => ['creator-commerce'],
            'apply_as_creator' => ['creator-commerce'],
            'get_creator_campaigns' => ['creator-commerce'],
            'search_community_posts' => ['community'],
            'create_community_post' => ['community'],
            'get_community_post' => ['community'],
            'search_earn_money_opportunities' => ['earn-money'],
            'get_earn_money_opportunity' => ['earn-money'],
            'apply_earn_money_opportunity' => ['earn-money'],
            'search_events' => ['events'],
            'get_event_detail' => ['events'],
            'register_event_interest' => ['events'],
        ];

        return $map[$actionName] ?? [];
    }
}