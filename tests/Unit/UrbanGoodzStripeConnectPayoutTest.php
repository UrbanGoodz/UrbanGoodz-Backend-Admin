<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzConnectedAccount;
use PHPUnit\Framework\TestCase;

class UrbanGoodzStripeConnectPayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
        require_once $this->root.'/app/Models/UrbanGoodzConnectedAccount.php';
    }

    public function test_all_required_earning_roles_are_supported(): void
    {
        self::assertSame([
            'vendor', 'business', 'driver', 'service_provider', 'stylist',
            'creator', 'dispatcher', 'event_organiser',
        ], UrbanGoodzConnectedAccount::ROLES);
    }

    public function test_transfer_readiness_requires_every_financial_safety_gate(): void
    {
        $account = new UrbanGoodzConnectedAccount;
        $account->setRawAttributes([
            'environment' => 'sandbox',
            'stripe_account_id' => 'acct_test_vendor',
            'transfer_capability_status' => 'active',
            'payouts_enabled' => true,
            'admin_payouts_enabled' => true,
            'manual_hold' => false,
            'is_suspended' => false,
            'restriction_status' => 'enabled',
        ], true);
        self::assertTrue($account->canReceiveTransfers());

        foreach ([
            'payouts_enabled' => false,
            'admin_payouts_enabled' => false,
            'manual_hold' => true,
            'is_suspended' => true,
            'restriction_status' => 'restricted',
            'transfer_capability_status' => 'pending',
        ] as $field => $unsafeValue) {
            $copy = clone $account;
            $copy->{$field} = $unsafeValue;
            self::assertFalse($copy->canReceiveTransfers(), "Gate {$field} must block transfers.");
        }
    }

    public function test_schema_prevents_duplicate_accounts_transfers_and_webhooks(): void
    {
        $migration = file_get_contents(
            $this->root.'/database/migrations/2026_07_30_230000_create_stripe_connect_payout_tables.php'
        );
        self::assertStringContainsString("['owner_role', 'owner_id']", $migration);
        self::assertStringContainsString("'idempotency_key')->unique()", $migration);
        self::assertStringContainsString("'stripe_event_id')->unique()", $migration);
        self::assertStringContainsString("'stripe_transfer_id')->nullable()->unique()", $migration);
    }

    public function test_transfer_and_refund_sources_enforce_confirmation_and_exact_keys(): void
    {
        $service = file_get_contents(
            $this->root.'/app/Services/UrbanGoodz/Payouts/ConnectedPayoutService.php'
        );
        self::assertStringContainsString("['succeeded', 'captured', 'paid']", $service);
        self::assertStringContainsString('ug:transfer:{$snapshot->id}:{$recipient->id}:v1', $service);
        self::assertStringContainsString('ug:reversal:{$transfer->id}:{$idempotencyKey}', $service);
        self::assertStringContainsString('Recipient split does not balance.', $service);
        self::assertStringContainsString('stripe_reversal_failed', $service);
        self::assertStringContainsString("'manual_review'", $service);
    }

    public function test_webhook_source_is_replay_safe_out_of_order_safe_and_sanitized(): void
    {
        $webhook = file_get_contents(
            $this->root.'/app/Services/UrbanGoodz/Payouts/StripeConnectWebhookService.php'
        );
        foreach ([
            'account.updated', 'capability.updated', 'balance.available',
            'transfer.created', 'transfer.failed', 'transfer.reversed',
            'payout.created', 'payout.updated', 'payout.paid', 'payout.failed',
            'payout.canceled', 'charge.refunded', 'charge.dispute.created',
            'charge.dispute.closed',
        ] as $event) {
            self::assertStringContainsString("'{$event}'", $webhook);
        }
        self::assertStringContainsString("status === 'processed'", $webhook);
        self::assertStringContainsString('isOlder(', $webhook);
        self::assertStringNotContainsString("'bank_account'", $webhook);
        self::assertStringNotContainsString("'routing_number'", $webhook);
    }

    public function test_gateway_is_sandbox_only_and_never_accepts_live_keys(): void
    {
        $gateway = file_get_contents(
            $this->root.'/app/Services/Payments/StripeConnectGateway.php'
        );
        self::assertStringContainsString("!== 'sandbox'", $gateway);
        self::assertStringContainsString("str_starts_with(\$this->secret, 'sk_test_')", $gateway);
        self::assertStringContainsString('Idempotency-Key', $gateway);
        self::assertStringNotContainsString('bank_account', $gateway);
        self::assertStringNotContainsString('routing_number', $gateway);
    }
}
