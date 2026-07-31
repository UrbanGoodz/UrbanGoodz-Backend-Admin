<?php

namespace App\Services\UrbanGoodz\Payouts;

use App\Models\UrbanGoodzConnectedAccount;
use App\Models\UrbanGoodzFinancialLedgerEntry;
use App\Models\UrbanGoodzPayoutTransfer;
use App\Models\UrbanGoodzSettlementRecipient;
use App\Models\UrbanGoodzSettlementSnapshot;
use App\Services\Payments\StripeConnectGateway;
use App\Services\UrbanGoodz\FinancialControl\FinancialControlService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ConnectedPayoutService
{
    public function __construct(
        private readonly StripeConnectGateway $stripe,
        private readonly FinancialControlService $financialControl
    ) {}

    public function beginSetup(array $actor, array $identity): array
    {
        $account = UrbanGoodzConnectedAccount::firstOrCreate(
            ['owner_role' => $actor['role'], 'owner_id' => $actor['id']],
            [
                'environment' => 'sandbox',
                'country' => strtoupper($identity['country']),
                'currency' => strtoupper($identity['currency']),
                'status' => 'creating',
            ]
        );
        if ($account->wasRecentlyCreated) {
            $control = DB::table('urban_goodz_payout_role_controls')
                ->where('owner_role', $actor['role'])->first();
            if ($control) {
                $account->update([
                    'admin_payouts_enabled' => (bool) $control->payouts_enabled,
                    'minimum_payout_cents' => $control->minimum_payout_cents,
                    'payout_schedule' => $control->payout_schedule,
                    'payout_delay_days' => $control->payout_delay_days,
                    'refund_hold' => (bool) $control->refund_hold,
                    'instant_payout_eligible' => (bool) $control->instant_payout_allowed,
                ]);
            }
        }

        if (! $account->stripe_account_id) {
            try {
                $remote = $this->stripe->createRecipientAccount([
                    'role' => $actor['role'],
                    'id' => $actor['id'],
                    'email' => $identity['email'],
                    'display_name' => $identity['display_name'],
                    'country' => strtoupper($identity['country']),
                    'currency' => strtoupper($identity['currency']),
                    'entity_type' => $identity['entity_type'],
                ], "ug:connect-account:{$actor['role']}:{$actor['id']}:v1");
                $account->stripe_account_id = $remote['id'];
                $this->applyAccountStatus($account, $remote);
            } catch (Throwable $exception) {
                $account->update(['status' => 'creation_failed']);
                throw $exception;
            }
        }

        $this->audit($actor, 'connected_account.setup_begun', $account);

        return $this->onboardingLink($account, false);
    }

    public function continueSetup(UrbanGoodzConnectedAccount $account, array $actor): array
    {
        $this->assertOwnership($account, $actor);
        $this->audit($actor, 'connected_account.setup_continued', $account);

        return $this->onboardingLink($account, true);
    }

    public function managementLink(UrbanGoodzConnectedAccount $account, array $actor): array
    {
        $this->assertOwnership($account, $actor);
        abort_unless($account->stripe_account_id, 409, 'Payout account setup has not started.');
        $link = $this->stripe->createManagementLink($account->stripe_account_id);
        $this->audit($actor, 'connected_account.management_opened', $account);

        return ['url' => $link['url'], 'expires_at' => null];
    }

    public function refresh(UrbanGoodzConnectedAccount $account, array $actor): UrbanGoodzConnectedAccount
    {
        $this->assertOwnership($account, $actor);
        abort_unless($account->stripe_account_id, 409, 'Payout account setup has not started.');
        $this->applyAccountStatus($account, $this->stripe->retrieveAccount($account->stripe_account_id));
        $balance = $this->stripe->retrieveBalance($account->stripe_account_id);
        $currency = strtolower($account->currency);
        $account->update([
            'available_balance_cents' => $this->balanceFor($balance['available'] ?? [], $currency),
            'pending_balance_cents' => $this->balanceFor($balance['pending'] ?? [], $currency),
            'last_synced_at' => now(),
        ]);
        $this->audit($actor, 'connected_account.refreshed', $account);

        return $account->fresh();
    }

    public function status(UrbanGoodzConnectedAccount $account, array $actor): array
    {
        $this->assertOwnership($account, $actor);
        $requiredActions = [];
        if ($account->is_suspended || $account->manual_hold) {
            $requiredActions[] = 'contact_urban_goodz_support';
        }
        if (($account->requirements_currently_due ?? []) !== []) {
            $requiredActions[] = 'continue_stripe_verification';
        }
        if ($account->disabled_reason) {
            $requiredActions[] = 'resolve_stripe_restriction';
        }

        return [
            'account' => $account->only([
                'owner_role', 'owner_id', 'status', 'restriction_status',
                'disabled_reason', 'charges_enabled', 'payouts_enabled',
                'details_submitted', 'transfer_capability_status',
                'payout_capability_status', 'requirements_currently_due',
                'requirements_eventually_due', 'available_balance_cents',
                'pending_balance_cents', 'next_expected_payout_at',
                'instant_payout_eligible', 'minimum_payout_cents',
                'payout_schedule', 'payout_delay_days', 'last_synced_at',
            ]),
            'required_owner_actions' => array_values(array_unique($requiredActions)),
            'payouts' => $account->payouts()->latest()->limit(50)->get(),
            'transfers' => $account->transfers()->with('recipient.settlement')->latest()->limit(50)->get(),
            'settlements' => UrbanGoodzSettlementRecipient::query()
                ->where('owner_role', $actor['role'])->where('owner_id', $actor['id'])
                ->with('settlement.ledgerEntries')->latest()->limit(50)->get(),
        ];
    }

    public function transferConfirmedSettlement(
        UrbanGoodzSettlementSnapshot $snapshot,
        string $providerPaymentId,
        string $paymentStatus,
        ?array $recipients = null
    ): array {
        abort_unless(in_array($paymentStatus, ['succeeded', 'captured', 'paid'], true), 409,
            'No transfer is permitted before payment confirmation.');
        abort_unless($providerPaymentId !== '', 422, 'Provider payment id is required.');

        $allocations = $this->allocate($snapshot, $recipients);
        $transfers = [];
        foreach ($allocations as $recipient) {
            $account = UrbanGoodzConnectedAccount::where([
                'owner_role' => $recipient->owner_role,
                'owner_id' => $recipient->owner_id,
            ])->first();
            if (! $account) {
                $transfers[] = $this->blockedTransfer($recipient, $providerPaymentId, 'payout_account_missing');
                continue;
            }

            $key = "ug:transfer:{$snapshot->id}:{$recipient->id}:v1";
            $transfer = UrbanGoodzPayoutTransfer::firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'settlement_recipient_id' => $recipient->id,
                    'connected_account_id' => $account->id,
                    'provider_payment_id' => $providerPaymentId,
                    'amount_cents' => $recipient->net_amount_cents,
                    'currency' => $recipient->currency,
                    'status' => 'pending',
                ]
            );
            if ($transfer->stripe_transfer_id || $transfer->status === 'created') {
                $transfers[] = $transfer;
                continue;
            }
            if (! $account->canReceiveTransfers()) {
                $transfer->update([
                    'status' => 'blocked',
                    'blocked_reason' => $this->accountBlockReason($account),
                ]);
                $transfers[] = $transfer;
                continue;
            }

            try {
                $remote = $this->stripe->createTransfer([
                    'local_id' => $transfer->id,
                    'settlement_id' => $snapshot->id,
                    'idempotency_key' => $key,
                    'amount_cents' => $transfer->amount_cents,
                    'currency' => $transfer->currency,
                    'destination' => $account->stripe_account_id,
                    'source_transaction' => $providerPaymentId,
                    'transfer_group' => $snapshot->snapshot_number,
                ]);
                $transfer->update([
                    'stripe_transfer_id' => $remote['id'],
                    'status' => 'created',
                    'blocked_reason' => null,
                ]);
                $recipient->update(['connected_account_id' => $account->id, 'status' => 'transferred']);
                $this->writeTransferLedger($snapshot, $recipient, $transfer, false);
            } catch (Throwable $exception) {
                $transfer->update([
                    'status' => 'failed',
                    'failure_code' => 'stripe_transfer_failed',
                    'failure_message' => Str::limit($exception->getMessage(), 500),
                ]);
            }
            $transfers[] = $transfer->fresh();
        }
        $this->financialControl->reconcile($snapshot);

        return $transfers;
    }

    public function reverseRefund(
        UrbanGoodzSettlementSnapshot $snapshot,
        int $amountCents,
        string $reason,
        string $idempotencyKey
    ): UrbanGoodzSettlementSnapshot {
        $oldRefunded = $snapshot->refunded_cents;
        $snapshot = $this->financialControl->refund($snapshot, $amountCents, $reason, $idempotencyKey);
        $newRefunded = $snapshot->refunded_cents;

        foreach (UrbanGoodzPayoutTransfer::whereHas('recipient', fn ($query) =>
            $query->where('settlement_snapshot_id', $snapshot->id)
        )->with('recipient')->get() as $transfer) {
            if (! $transfer->stripe_transfer_id || ! in_array($transfer->status, ['created', 'partially_reversed'], true)) {
                continue;
            }
            $oldTarget = $this->roundDivide($transfer->amount_cents * $oldRefunded, $snapshot->shopper_total_cents);
            $newTarget = $this->roundDivide($transfer->amount_cents * $newRefunded, $snapshot->shopper_total_cents);
            $reverseCents = $newTarget - $oldTarget;
            if ($reverseCents <= 0) {
                continue;
            }
            $key = "ug:reversal:{$transfer->id}:{$idempotencyKey}";
            $reversal = DB::table('urban_goodz_transfer_reversals')->where('idempotency_key', $key)->first();
            if ($reversal?->status === 'succeeded') {
                continue;
            }
            $reversalId = $reversal?->id ?: DB::table('urban_goodz_transfer_reversals')->insertGetId([
                'payout_transfer_id' => $transfer->id,
                'refund_reference' => $idempotencyKey,
                'idempotency_key' => $key,
                'amount_cents' => $reverseCents,
                'status' => 'pending',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            try {
                $remote = $this->stripe->reverseTransfer($transfer->stripe_transfer_id, $reverseCents, $key, [
                    'urban_goodz_reversal_id' => (string) $reversalId,
                    'urban_goodz_refund_key' => $idempotencyKey,
                ]);
                DB::table('urban_goodz_transfer_reversals')->where('id', $reversalId)->update([
                    'stripe_reversal_id' => $remote['id'],
                    'status' => 'succeeded',
                    'updated_at' => now(),
                ]);
                $transfer->increment('reversed_amount_cents', $reverseCents, [
                    'status' => $transfer->reversed_amount_cents + $reverseCents === $transfer->amount_cents
                        ? 'reversed' : 'partially_reversed',
                ]);
                $transfer->recipient->increment('refunded_cents', $reverseCents);
                $this->writeTransferLedger($snapshot, $transfer->recipient, $transfer, true, $reverseCents, $key);
            } catch (Throwable $exception) {
                DB::table('urban_goodz_transfer_reversals')->where('id', $reversalId)->update([
                    'status' => 'failed',
                    'failure_code' => 'stripe_reversal_failed',
                    'failure_message' => Str::limit($exception->getMessage(), 500),
                    'updated_at' => now(),
                ]);
                $transfer->update(['status' => 'manual_review']);
            }
        }
        $this->financialControl->reconcile($snapshot);

        return $snapshot->fresh(['ledgerEntries', 'reconciliationRuns']);
    }

    public function applyAccountStatus(UrbanGoodzConnectedAccount $account, array $remote): void
    {
        $transfer = data_get($remote, 'configuration.recipient.capabilities.stripe_balance.stripe_transfers', []);
        $payout = data_get($remote, 'configuration.recipient.capabilities.stripe_balance.payouts', []);
        [$current, $eventual, $errors] = $this->requirements($remote);
        $transferStatus = data_get($transfer, 'status', 'pending');
        $payoutStatus = data_get($payout, 'status', 'pending');
        $disabled = data_get($remote, 'requirements.disabled_reason')
            ?? data_get($transfer, 'status_details.0.code')
            ?? data_get($payout, 'status_details.0.code');
        $enabled = $transferStatus === 'active' && $payoutStatus === 'active' && $disabled === null;

        $account->fill([
            'stripe_account_id' => $remote['id'] ?? $account->stripe_account_id,
            'status' => $enabled ? 'enabled' : ($current ? 'verification_required' : 'pending'),
            'restriction_status' => $enabled ? 'enabled' : ($disabled ? 'restricted' : 'requirements_due'),
            'disabled_reason' => $disabled,
            'transfer_capability_status' => $transferStatus,
            'payout_capability_status' => $payoutStatus,
            'charges_enabled' => (bool) ($remote['charges_enabled'] ?? false),
            'payouts_enabled' => $enabled || (bool) ($remote['payouts_enabled'] ?? false),
            'details_submitted' => (bool) ($remote['details_submitted'] ?? ($current === [])),
            'requirements_currently_due' => $current,
            'requirements_eventually_due' => $eventual,
            'requirement_errors' => $errors,
            'last_synced_at' => now(),
        ])->save();
    }

    private function allocate(UrbanGoodzSettlementSnapshot $snapshot, ?array $custom): array
    {
        $definitions = $custom ?? array_values(array_filter([
            $snapshot->provider_id ? [
                'role' => 'vendor', 'id' => $snapshot->provider_id,
                'gross' => $snapshot->merchandise_subtotal_cents,
                'commission' => $snapshot->business_commission_cents,
                'admin_fee' => 0, 'net' => $snapshot->provider_proceeds_cents,
            ] : null,
            $snapshot->driver_id ? [
                'role' => 'driver', 'id' => $snapshot->driver_id,
                'gross' => $snapshot->driver_compensation_cents,
                'commission' => 0, 'admin_fee' => $snapshot->driver_admin_fee_cents,
                'net' => $snapshot->driver_net_cents,
            ] : null,
        ]));
        $result = [];
        foreach ($definitions as $definition) {
            foreach (['gross', 'commission', 'admin_fee', 'net'] as $field) {
                if (! isset($definition[$field]) || ! is_int($definition[$field]) || $definition[$field] < 0) {
                    throw new InvalidArgumentException("Recipient {$field} must be non-negative integer cents.");
                }
            }
            if ($definition['gross'] - $definition['commission'] - $definition['admin_fee'] !== $definition['net']) {
                throw new InvalidArgumentException('Recipient split does not balance.');
            }
            $result[] = UrbanGoodzSettlementRecipient::firstOrCreate(
                [
                    'settlement_snapshot_id' => $snapshot->id,
                    'owner_role' => $definition['role'],
                    'owner_id' => $definition['id'],
                ],
                [
                    'gross_amount_cents' => $definition['gross'],
                    'commission_cents' => $definition['commission'],
                    'admin_fee_cents' => $definition['admin_fee'],
                    'net_amount_cents' => $definition['net'],
                    'currency' => $snapshot->currency,
                ]
            );
        }

        return $result;
    }

    private function blockedTransfer(UrbanGoodzSettlementRecipient $recipient, string $paymentId, string $reason)
    {
        return UrbanGoodzPayoutTransfer::firstOrCreate(
            ['idempotency_key' => "ug:transfer:{$recipient->settlement_snapshot_id}:{$recipient->id}:v1"],
            [
                'settlement_recipient_id' => $recipient->id,
                'connected_account_id' => 0,
                'provider_payment_id' => $paymentId,
                'amount_cents' => $recipient->net_amount_cents,
                'currency' => $recipient->currency,
                'status' => 'blocked',
                'blocked_reason' => $reason,
            ]
        );
    }

    private function onboardingLink(UrbanGoodzConnectedAccount $account, bool $continuation): array
    {
        abort_unless($account->stripe_account_id, 409, 'Payout account creation did not complete.');
        $base = rtrim((string) config('urban_goodz_payments.stripe.connect_return_base_url'), '/');
        abort_unless(str_starts_with($base, 'https://'), 503, 'Stripe Connect return URL must use HTTPS.');
        $link = $this->stripe->createOnboardingLink(
            $account->stripe_account_id,
            "{$base}/return",
            "{$base}/refresh",
            $continuation
        );

        return ['url' => $link['url'], 'expires_at' => $link['expires_at'] ?? null];
    }

    private function requirements(array $remote): array
    {
        if (isset($remote['requirements']['currently_due'])) {
            return [
                array_values($remote['requirements']['currently_due'] ?? []),
                array_values($remote['requirements']['eventually_due'] ?? []),
                array_map(fn ($error) => [
                    'code' => $error['code'] ?? 'requirement_error',
                    'requirement' => $error['requirement'] ?? null,
                    'reason' => Str::limit((string) ($error['reason'] ?? ''), 300),
                ], $remote['requirements']['errors'] ?? []),
            ];
        }
        $current = $eventual = $errors = [];
        foreach (data_get($remote, 'requirements.entries', []) ?? [] as $entry) {
            $key = data_get($entry, 'reference.resource') ?? data_get($entry, 'id') ?? 'identity_information';
            $status = data_get($entry, 'minimum_deadline.status')
                ?? data_get($entry, 'impact.restricts_capabilities.deadline.status');
            $status === 'eventually_due' ? $eventual[] = $key : $current[] = $key;
            foreach ($entry['errors'] ?? [] as $error) {
                $errors[] = ['code' => $error['code'] ?? 'requirement_error', 'requirement' => $key];
            }
        }

        return [array_values(array_unique($current)), array_values(array_unique($eventual)), $errors];
    }

    private function balanceFor(array $balances, string $currency): int
    {
        return (int) collect($balances)->where('currency', $currency)->sum('amount');
    }

    private function accountBlockReason(UrbanGoodzConnectedAccount $account): string
    {
        return match (true) {
            $account->is_suspended => 'account_suspended',
            $account->manual_hold => 'manual_payout_hold',
            ! $account->admin_payouts_enabled => 'role_payouts_disabled',
            $account->transfer_capability_status !== 'active' => 'transfers_restricted',
            ! $account->payouts_enabled => 'payouts_disabled',
            default => 'verification_required',
        };
    }

    private function writeTransferLedger(
        UrbanGoodzSettlementSnapshot $snapshot,
        UrbanGoodzSettlementRecipient $recipient,
        UrbanGoodzPayoutTransfer $transfer,
        bool $reversal,
        ?int $amount = null,
        ?string $key = null
    ): void {
        $amount ??= $transfer->amount_cents;
        $prefix = $key ?? $transfer->idempotency_key;
        $payable = $recipient->owner_role === 'driver' ? 'driver_payable' : 'provider_payable';
        $entries = $reversal
            ? [['connected_transfer_clearing', 'debit'], [$payable, 'credit']]
            : [[$payable, 'debit'], ['connected_transfer_clearing', 'credit']];
        foreach ($entries as $index => [$account, $direction]) {
            UrbanGoodzFinancialLedgerEntry::firstOrCreate(
                ['idempotency_key' => "{$prefix}:ledger:{$index}"],
                [
                    'entry_number' => 'UGL-'.now()->format('YmdHisv').'-'.strtoupper(Str::random(6)),
                    'settlement_snapshot_id' => $snapshot->id,
                    'event_type' => $reversal ? 'transfer_reversal' : 'transfer',
                    'account_code' => $account,
                    'party_type' => $recipient->owner_role,
                    'party_id' => $recipient->owner_id,
                    'direction' => $direction,
                    'amount_cents' => $amount,
                    'currency' => $snapshot->currency,
                    'reference' => $transfer->stripe_transfer_id,
                ]
            );
        }
    }

    private function audit(array $actor, string $action, UrbanGoodzConnectedAccount $account): void
    {
        DB::table('urban_goodz_payout_audit_events')->insert([
            'actor_type' => $actor['actor_type'],
            'actor_id' => $actor['actor_id'],
            'action' => $action,
            'auditable_type' => UrbanGoodzConnectedAccount::class,
            'auditable_id' => $account->id,
            'metadata' => json_encode(['owner_role' => $account->owner_role, 'owner_id' => $account->owner_id]),
            'created_at' => now(),
        ]);
    }

    private function assertOwnership(UrbanGoodzConnectedAccount $account, array $actor): void
    {
        abort_unless($account->owner_role === $actor['role'] && $account->owner_id === $actor['id'], 403);
    }

    private function roundDivide(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Settlement total must be positive.');
        }

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
