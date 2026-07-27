<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AIActionValidator
{
    private const ACTION_SCHEMA = [
        'type' => 'object',
        'required' => ['intent', 'confidence', 'entities', 'proposed_action', 'requires_confirmation', 'requires_human_review', 'explanation'],
        'properties' => [
            'intent' => [
                'type' => 'string',
                'pattern' => '^[a-z0-9\-_]+$',
                'maxLength' => 64,
            ],
            'confidence' => [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 1,
            ],
            'entities' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'proposed_action' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'requires_confirmation' => [
                'type' => 'boolean',
            ],
            'requires_human_review' => [
                'type' => 'boolean',
            ],
            'explanation' => [
                'type' => 'string',
                'maxLength' => 1000,
            ],
            'idempotency_key' => [
                'type' => ['string', 'null'],
                'pattern' => '^[a-zA-Z0-9\-_]{1,128}$',
            ],
            'confirmation_token' => [
                'type' => ['string', 'null'],
                'pattern' => '^[a-zA-Z0-9\-_]{1,128}$',
            ],
        ],
        'additionalProperties' => false,
    ];

    private const INTENT_ALLOWLIST = [
        'order-anywhere' => [
            'create_order_anywhere_request',
            'get_order_anywhere_status',
            'authorize_payment',
        ],
        'fashion-fit' => [
            'submit_stylist_request',
            'search_stylists',
            'get_stylist_requests',
        ],
        'book-services' => [
            'submit_book_anything_request',
            'search_service_providers',
            'get_book_anything_status',
        ],
        'rentals' => [
            'search_rental_assets',
            'book_rental_asset',
            'get_rental_booking_status',
        ],
        'marketplace-search' => [
            'search_marketplace',
            'get_marketplace_item',
            'list_marketplace_item',
        ],
        'medical-courier' => [
            'search_medical_courier_jobs',
            'get_medical_courier_job',
            'create_medical_courier_job',
            'get_medical_courier_status',
        ],
        'load-board' => [
            'search_load_board',
            'get_load_board_load',
            'post_load_to_board',
            'bid_on_load',
        ],
        'delivery' => [
            'track_delivery',
            'get_delivery_status',
            'create_delivery_request',
        ],
        'creator-commerce' => [
            'search_creators',
            'get_creator_profile',
            'apply_as_creator',
            'get_creator_campaigns',
        ],
        'community' => [
            'search_community_posts',
            'create_community_post',
            'get_community_post',
        ],
        'earn-money' => [
            'search_earn_money_opportunities',
            'get_earn_money_opportunity',
            'apply_earn_money_opportunity',
        ],
        'events' => [
            'search_events',
            'get_event_detail',
            'register_event_interest',
        ],
    ];

    public function validateActionResult(array $aiResult): array
    {
        $errors = [];

        if (!isset($aiResult['intent']) || !is_string($aiResult['intent'])) {
            $errors[] = 'Missing or invalid "intent" field';
        } elseif (!isset(self::INTENT_ALLOWLIST[$aiResult['intent']])) {
            $errors[] = "Intent '{$aiResult['intent']}' is not in the allowed intent registry";
        }

        if (!isset($aiResult['confidence']) || !is_numeric($aiResult['confidence'])) {
            $errors[] = 'Missing or invalid "confidence" field (must be number 0-1)';
        } elseif ($aiResult['confidence'] < 0 || $aiResult['confidence'] > 1) {
            $errors[] = 'Confidence must be between 0 and 1';
        }

        if (!isset($aiResult['entities']) || !is_array($aiResult['entities'])) {
            $errors[] = 'Missing or invalid "entities" field (must be object)';
        }

        if (!isset($aiResult['proposed_action']) || !is_array($aiResult['proposed_action'])) {
            $errors[] = 'Missing or invalid "proposed_action" field (must be object)';
        } elseif (isset($aiResult['intent']) && isset(self::INTENT_ALLOWLIST[$aiResult['intent']])) {
            $allowedActions = self::INTENT_ALLOWLIST[$aiResult['intent']];
            $proposedAction = $aiResult['proposed_action']['action'] ?? null;
            if ($proposedAction && !in_array($proposedAction, $allowedActions, true)) {
                $errors[] = "Proposed action '{$proposedAction}' not allowed for intent '{$aiResult['intent']}'. Allowed: " . implode(', ', $allowedActions);
            }
        }

        if (!isset($aiResult['requires_confirmation']) || !is_bool($aiResult['requires_confirmation'])) {
            $errors[] = 'Missing or invalid "requires_confirmation" field (must be boolean)';
        }

        if (!isset($aiResult['requires_human_review']) || !is_bool($aiResult['requires_human_review'])) {
            $errors[] = 'Missing or invalid "requires_human_review" field (must be boolean)';
        }

        if (!isset($aiResult['explanation']) || !is_string($aiResult['explanation'])) {
            $errors[] = 'Missing or invalid "explanation" field (must be string)';
        }

        if (isset($aiResult['idempotency_key']) && $aiResult['idempotency_key'] !== null) {
            if (!is_string($aiResult['idempotency_key']) || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $aiResult['idempotency_key'])) {
                $errors[] = 'Invalid "idempotency_key" format';
            }
        }

        if (isset($aiResult['confirmation_token']) && $aiResult['confirmation_token'] !== null) {
            if (!is_string($aiResult['confirmation_token']) || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $aiResult['confirmation_token'])) {
                $errors[] = 'Invalid "confirmation_token" format';
            }
        }

        if (!empty($errors)) {
            Log::warning('AIActionValidator: Validation failed', [
                'errors' => $errors,
                'received_fields' => array_keys($aiResult),
            ]);
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return [
            'valid' => true,
            'sanitized' => $this->sanitize($aiResult),
        ];
    }

    public function validateIntentActionPair(string $intent, string $action): bool
    {
        if (!isset(self::INTENT_ALLOWLIST[$intent])) {
            return false;
        }
        return in_array($action, self::INTENT_ALLOWLIST[$intent], true);
    }

    public function getAllowedActionsForIntent(string $intent): array
    {
        return self::INTENT_ALLOWLIST[$intent] ?? [];
    }

    public function getAllRegisteredIntents(): array
    {
        return array_keys(self::INTENT_ALLOWLIST);
    }

    private function sanitize(array $data): array
    {
        return [
            'intent' => $data['intent'],
            'confidence' => (float) $data['confidence'],
            'entities' => $this->sanitizeEntities($data['entities']),
            'proposed_action' => $this->sanitizeAction($data['proposed_action']),
            'requires_confirmation' => (bool) $data['requires_confirmation'],
            'requires_human_review' => (bool) $data['requires_human_review'],
            'explanation' => trim(strip_tags($data['explanation'])),
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'confirmation_token' => $data['confirmation_token'] ?? null,
        ];
    }

    private function sanitizeEntities(array $entities): array
    {
        $sanitized = [];
        foreach ($entities as $key => $value) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            if ($cleanKey === '') continue;
            
            if (is_string($value)) {
                $sanitized[$cleanKey] = strip_tags($value);
            } elseif (is_numeric($value) || is_bool($value) || is_null($value)) {
                $sanitized[$cleanKey] = $value;
            } elseif (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeEntities($value);
            }
        }
        return $sanitized;
    }

    private function sanitizeAction(array $action): array
    {
        $sanitized = [];
        foreach ($action as $key => $value) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            if ($cleanKey === '') continue;
            
            if (is_string($value)) {
                $sanitized[$cleanKey] = strip_tags($value);
            } elseif (is_numeric($value) || is_bool($value) || is_null($value)) {
                $sanitized[$cleanKey] = $value;
            } elseif (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeAction($value);
            }
        }
        return $sanitized;
    }
}
