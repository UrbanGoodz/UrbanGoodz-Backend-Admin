<?php

namespace App\Services\UrbanGoodz\Agent;

class AgentToolRegistry
{
    // Risk Categories
    public const RISK_READ = 'READ';
    public const RISK_LOW_WRITE = 'LOW_RISK_WRITE';
    public const RISK_HIGH_WRITE = 'HIGH_RISK_WRITE';
    public const RISK_FINANCIAL = 'FINANCIAL';
    public const RISK_DESTRUCTIVE = 'DESTRUCTIVE';
    public const RISK_ADMIN = 'ADMIN';

    /**
     * Complete registry of authoritative native tools available to Monique.
     *
     * @var array<string, array<string, mixed>>
     */
    private const TOOLS = [
        'get_vendor_details' => [
            'name' => 'get_vendor_details',
            'description' => 'Retrieve comprehensive profile, stores, and compliance status for a specific vendor.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin', 'dispatcher'],
            'parameters' => [
                'vendor_id' => ['type' => 'integer', 'required' => false],
                'name' => ['type' => 'string', 'required' => false],
            ],
        ],

        'list_vendors' => [
            'name' => 'list_vendors',
            'description' => 'List vendors filtered by operational status, zone, or onboarding readiness.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin', 'dispatcher'],
            'parameters' => [
                'status' => ['type' => 'string', 'enum' => ['all', 'active', 'inactive', 'pending'], 'default' => 'all'],
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ],

        'audit_vendor_onboarding' => [
            'name' => 'audit_vendor_onboarding',
            'description' => 'Audit vendor onboarding backlog: analyze all registered vendors for incomplete documentation, missing store details, or unapproved status.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin'],
            'parameters' => [
                'scope' => ['type' => 'string', 'enum' => ['all', 'incomplete_only'], 'default' => 'incomplete_only'],
            ],
        ],

        'update_vendor_status' => [
            'name' => 'update_vendor_status',
            'description' => 'Modify vendor operational status (active, inactive, suspended). Requires confirmation.',
            'risk_level' => self::RISK_HIGH_WRITE,
            'requires_confirmation' => true,
            'roles' => ['admin'],
            'parameters' => [
                'vendor_id' => ['type' => 'integer', 'required' => true],
                'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'suspended'], 'required' => true],
                'reason' => ['type' => 'string', 'required' => false],
            ],
        ],

        'get_order_details' => [
            'name' => 'get_order_details',
            'description' => 'Retrieve detailed information for an order including items, delivery status, and assigned courier.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin', 'dispatcher'],
            'parameters' => [
                'order_id' => ['type' => 'integer', 'required' => true],
            ],
        ],

        'assign_order_courier' => [
            'name' => 'assign_order_courier',
            'description' => 'Assign an active courier to a pending order. Requires confirmation.',
            'risk_level' => self::RISK_HIGH_WRITE,
            'requires_confirmation' => true,
            'roles' => ['admin', 'dispatcher'],
            'parameters' => [
                'order_id' => ['type' => 'integer', 'required' => true],
                'driver_id' => ['type' => 'integer', 'required' => true],
            ],
        ],

        'cancel_order' => [
            'name' => 'cancel_order',
            'description' => 'Cancel an active customer order with justification. Requires confirmation.',
            'risk_level' => self::RISK_HIGH_WRITE,
            'requires_confirmation' => true,
            'roles' => ['admin'],
            'parameters' => [
                'order_id' => ['type' => 'integer', 'required' => true],
                'reason' => ['type' => 'string', 'required' => false],
            ],
        ],

        'get_out_of_stock_inventory' => [
            'name' => 'get_out_of_stock_inventory',
            'description' => 'Query currently out-of-stock items grouped by vendor store.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin', 'dispatcher'],
            'parameters' => [
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ],

        'retry_failed_queue_job' => [
            'name' => 'retry_failed_queue_job',
            'description' => 'Re-run a failed Laravel background queue job by UUID. Requires confirmation.',
            'risk_level' => self::RISK_ADMIN,
            'requires_confirmation' => true,
            'roles' => ['admin'],
            'parameters' => [
                'job_uuid' => ['type' => 'string', 'required' => true],
            ],
        ],

        'get_command_center_metrics' => [
            'name' => 'get_command_center_metrics',
            'description' => 'Retrieve live executive operational summary metrics (tasks, approvals, alerts, orders).',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin'],
            'parameters' => [],
        ],

        // ─── VENDOR APP TOOLS ──────────────────────────────────────────
        'vendor_review_orders' => [
            'name' => 'vendor_review_orders',
            'description' => 'Review active orders and orders needing urgent attention for the authenticated vendor.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['vendor', 'admin'],
            'parameters' => [
                'status' => ['type' => 'string', 'enum' => ['pending', 'processing', 'all_active'], 'default' => 'all_active'],
            ],
        ],

        'vendor_performance_summary' => [
            'name' => 'vendor_performance_summary',
            'description' => 'Retrieve sales revenue, order fulfillment rate, and product performance trends for the authenticated vendor.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['vendor', 'admin'],
            'parameters' => [],
        ],

        'vendor_operational_alerts' => [
            'name' => 'vendor_operational_alerts',
            'description' => 'Identify vendor operational problems, delayed preparations, or stockouts.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['vendor', 'admin'],
            'parameters' => [],
        ],

        'vendor_promotions_summary' => [
            'name' => 'vendor_promotions_summary',
            'description' => 'Review promotional campaigns and AI-suggested discounts for the authenticated vendor.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['vendor', 'admin'],
            'parameters' => [],
        ],

        'vendor_update_item' => [
            'name' => 'vendor_update_item',
            'description' => 'Update item price or availability in vendor store catalog. Requires confirmation.',
            'risk_level' => self::RISK_HIGH_WRITE,
            'requires_confirmation' => true,
            'roles' => ['vendor', 'admin'],
            'parameters' => [
                'item_id' => ['type' => 'integer', 'required' => true],
                'price' => ['type' => 'numeric', 'required' => false],
                'status' => ['type' => 'integer', 'required' => false],
            ],
        ],

        // ─── DRIVER NETWORK & ACQUISITION TOOLS ────────────────────────
        'analyze_driver_shortage' => [
            'name' => 'analyze_driver_shortage',
            'description' => 'Analyze market driver shortages and generate actionable fleet mobilization recommendations.',
            'risk_level' => self::RISK_READ,
            'requires_confirmation' => false,
            'roles' => ['admin', 'dispatcher'],
            'parameters' => [
                'market' => ['type' => 'string', 'required' => true],
                'shortage_count' => ['type' => 'integer', 'required' => true],
            ],
        ],

        'add_vendor_driver' => [
            'name' => 'add_vendor_driver',
            'description' => 'Register a new driver for a business/vendor awaiting Urban Goodz approval.',
            'risk_level' => self::RISK_LOW_WRITE,
            'requires_confirmation' => false,
            'roles' => ['vendor', 'admin'],
            'parameters' => [
                'f_name' => ['type' => 'string', 'required' => true],
                'l_name' => ['type' => 'string', 'required' => false],
                'phone' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => false],
                'pay_model' => ['type' => 'string', 'default' => 'per_order'],
                'pay_rate' => ['type' => 'numeric', 'default' => 15.00],
                'available_for_marketplace' => ['type' => 'boolean', 'default' => false],
            ],
        ],

        'configure_driver_pay' => [
            'name' => 'configure_driver_pay',
            'description' => 'Configure driver compensation model (per order, per mile, hourly) and shared marketplace availability.',
            'risk_level' => self::RISK_LOW_WRITE,
            'requires_confirmation' => false,
            'roles' => ['vendor', 'admin'],
            'parameters' => [
                'driver_id' => ['type' => 'integer', 'required' => true],
                'pay_model' => ['type' => 'string', 'enum' => ['per_order', 'per_mile', 'flat_route', 'hourly', 'percentage']],
                'pay_rate' => ['type' => 'numeric', 'required' => false],
                'available_for_marketplace' => ['type' => 'boolean', 'required' => false],
            ],
        ],

        'approve_driver' => [
            'name' => 'approve_driver',
            'description' => 'Grant Urban Goodz administrative verification and approval for a driver. Requires confirmation.',
            'risk_level' => self::RISK_HIGH_WRITE,
            'requires_confirmation' => true,
            'roles' => ['admin'],
            'parameters' => [
                'driver_id' => ['type' => 'integer', 'required' => true],
            ],
        ],

        'suspend_driver' => [
            'name' => 'suspend_driver',
            'description' => 'Suspend a driver across all dispatches. Requires confirmation.',
            'risk_level' => self::RISK_HIGH_WRITE,
            'requires_confirmation' => true,
            'roles' => ['admin'],
            'parameters' => [
                'driver_id' => ['type' => 'integer', 'required' => true],
                'reason' => ['type' => 'string', 'required' => false],
            ],
        ],
    ];

    /**
     * Get definition for a tool.
     */
    public function getTool(string $name): ?array
    {
        return self::TOOLS[$name] ?? null;
    }

    /**
     * Check if a tool is registered.
     */
    public function hasTool(string $name): bool
    {
        return isset(self::TOOLS[$name]);
    }

    /**
     * Return all tool definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allTools(): array
    {
        return self::TOOLS;
    }

    /**
     * Check if a user role is permitted to execute a specific tool.
     */
    public function isAuthorized(string $toolName, string $role): bool
    {
        $tool = $this->getTool($toolName);
        if (!$tool) {
            return false;
        }

        return in_array(strtolower($role), $tool['roles'] ?? [], true);
    }

    /**
     * Determine if a tool requires explicit human confirmation before execution.
     */
    public function requiresConfirmation(string $toolName): bool
    {
        $tool = $this->getTool($toolName);
        return (bool) ($tool['requires_confirmation'] ?? false);
    }

    /**
     * Get the risk category of a tool.
     */
    public function getRiskLevel(string $toolName): string
    {
        $tool = $this->getTool($toolName);
        return $tool['risk_level'] ?? self::RISK_HIGH_WRITE;
    }
}
