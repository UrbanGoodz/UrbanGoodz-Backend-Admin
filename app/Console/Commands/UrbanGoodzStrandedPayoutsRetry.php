<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedRequest;
use App\Services\UrbanGoodz\UrbanGoodzStrandedPaymentService;
use Illuminate\Console\Command;

/**
 * Pays responders whose payout could not go out at the moment they earned it.
 *
 * A rescue completes and escrow releases the instant the customer confirms.
 * Whether the responder can actually be paid at that instant is a separate
 * question: they may not have finished Stripe onboarding, Stripe may not have
 * cleared their bank details yet, or the API may simply have been unreachable.
 * None of those should block the customer's side of the transaction, so
 * `payoutResponder()` records the payout as pending and returns.
 *
 * This command is what turns "pending" into "paid" without anyone watching.
 * Before it existed, that reconciliation was done by hand -- which is the
 * actual reason responder payouts were described as manual even though escrow
 * worked correctly.
 *
 * Safe to run on a short schedule: the ledger's unique idempotency key and
 * Stripe's own Idempotency-Key header both guard against paying twice.
 */
class UrbanGoodzStrandedPayoutsRetry extends Command
{
    protected $signature = 'urbangoodz:stranded-payouts-retry
                            {--limit=100 : Maximum pending payouts to attempt in one run}
                            {--dry-run : Report what would be attempted without moving money}';

    protected $description = 'Retry Stranded responder payouts that could not be sent when the rescue completed';

    public function handle(UrbanGoodzStrandedPaymentService $payments): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        $pending = UrbanGoodzPaymentTransaction::query()
            ->where('transaction_type', 'responder_payout')
            ->where('internal_status', 'pending')
            ->where('payable_type', UrbanGoodzStrandedRequest::class)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending responder payouts.');

            return self::SUCCESS;
        }

        $this->info("Pending responder payouts: {$pending->count()}");

        $paid = 0;
        $stillBlocked = 0;
        $failed = 0;

        foreach ($pending as $row) {
            $request = UrbanGoodzStrandedRequest::find($row->payable_id);

            if (!$request) {
                // The ledger row outlived its request. Leave it alone rather
                // than deleting financial history.
                $this->warn("  request {$row->payable_id} missing for ledger row {$row->id}");
                $failed++;
                continue;
            }

            if ($dry) {
                $this->line("  would retry {$request->request_number} ({$row->amount_minor} minor, currently: {$row->provider_status})");
                continue;
            }

            $result = $payments->payoutResponder($request);

            if (!empty($result['paid'])) {
                $paid++;
                $this->info("  paid {$request->request_number} -> {$result['transfer_id']}");
                continue;
            }

            // "already_paid" means another run won the race; that is a success
            // for our purposes, not a failure.
            if (($result['reason'] ?? null) === 'already_paid') {
                $paid++;
                continue;
            }

            if (($result['reason'] ?? null) === 'responder_missing') {
                $failed++;
                $this->warn("  {$request->request_number}: responder no longer exists");
                continue;
            }

            $stillBlocked++;
            $this->line("  {$request->request_number}: still blocked ({$result['reason']})");
        }

        if ($dry) {
            $this->info('Dry run: nothing was sent.');

            return self::SUCCESS;
        }

        $this->info("Paid: {$paid}  Still blocked: {$stillBlocked}  Failed: {$failed}");

        // Blocked payouts are an expected steady state (responders mid
        // onboarding), so they must not make the scheduler report failure.
        return self::SUCCESS;
    }
}
