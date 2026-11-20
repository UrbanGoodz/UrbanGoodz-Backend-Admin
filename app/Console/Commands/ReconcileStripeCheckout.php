<?php

namespace App\Console\Commands;

use App\Http\Controllers\StripePaymentController;
use App\Models\PaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Store checkout (the legacy Stripe Checkout gateway) only learns a payment succeeded when the customer's browser
 * returns to the success page. If they pay and then close the tab or lose signal, Stripe has the money and the order
 * stays unpaid. This asks Stripe about every recent unpaid Checkout Session and finishes the ones that were in fact
 * paid, using the same idempotent completion the success page uses.
 */
class ReconcileStripeCheckout extends Command
{
    protected $signature = 'payments:reconcile-stripe-checkout {--hours=48 : How far back to look} {--dry-run : Report only}';

    protected $description = 'Finish store-checkout payments that Stripe took but the customer\'s browser never reported';

    public function handle(): int
    {
        $controller = app(StripePaymentController::class);
        $since = now()->subHours((int) $this->option('hours'));
        $dry = (bool) $this->option('dry-run');
        $checked = 0;
        $completed = 0;

        PaymentRequest::query()
            ->where('is_paid', 0)
            ->where('created_at', '>=', $since)
            ->where('additional_data', 'like', '%stripe_session_id%')
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->each(function (PaymentRequest $payment) use ($controller, $dry, &$checked, &$completed) {
                $extra = json_decode((string) $payment->additional_data, true) ?: [];
                $sessionId = $extra['stripe_session_id'] ?? null;
                if (! $sessionId) {
                    return;
                }
                $checked++;
                try {
                    $session = $controller->retrieveSession($sessionId);
                    if ($session->payment_status === 'paid' && $session->status === 'complete') {
                        if ($dry) {
                            $this->line("Would complete payment {$payment->id} (session {$sessionId})");
                            $completed++;

                            return;
                        }
                        if ($controller->completePaidSession((string) $payment->id, $session)) {
                            $completed++;
                            Log::warning('Reconciled a store payment the customer never returned for', ['payment_id' => $payment->id]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not reconcile a Stripe Checkout session', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
                }
            });

        $this->info("Checked {$checked}, completed {$completed}.");

        return self::SUCCESS;
    }
}
