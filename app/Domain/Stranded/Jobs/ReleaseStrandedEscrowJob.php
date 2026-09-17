<?php

namespace App\Domain\Stranded\Jobs;

use App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier;
use App\Domain\Stranded\Payments\UrbanGoodzStrandedPaymentService;
use App\Domain\Stranded\Support\MoniqueOperationsAlertService;
use App\Models\UrbanGoodzStrandedRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Captures the reward hold and transfers it to the responder, off the request
 * thread that told the customer their confirmation succeeded.
 *
 * releaseEscrow() itself never throws for a Stripe-side failure -- it catches
 * everything and leaves the request in escrow_status = 'payout_held', which
 * is what makes retrying this job safe. The Stripe calls inside are also
 * individually idempotent (capture is skipped if already captured, the
 * transfer carries a Stripe idempotency key), so a retry after a partial
 * failure only completes whichever step did not finish, never repeats a step
 * that already moved money.
 *
 * Same discipline as SendFirebaseNotification: on the sync connection this
 * runs inline and any exception would propagate straight through the
 * customer's already-committed "confirmed" request, so failures are handled
 * (logged) rather than thrown wherever a retry cannot help.
 */
class ReleaseStrandedEscrowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $strandedRequestId)
    {
        $this->onQueue('payouts');
        $this->afterCommit();
    }

    public function handle(
        UrbanGoodzStrandedPaymentService $payments,
        UrbanGoodzStrandedNotifier $notifier,
        MoniqueOperationsAlertService $alerts
    ): void {
        $request = UrbanGoodzStrandedRequest::find($this->strandedRequestId);

        if (!$request) {
            Log::warning('Stranded escrow release job found no request', [
                'request_id' => $this->strandedRequestId,
            ]);
            return;
        }

        $result = $payments->releaseEscrow($request);

        if ($result['released']) {
            if ($result['amount_minor'] > 0) {
                $notifier->payoutReleased($request);
            }
            return;
        }

        if (in_array($result['reason'], ['already_released', 'nothing_held'], true)) {
            return;
        }

        Log::warning('Stranded escrow release did not complete', [
            'request' => $request->request_number,
            'reason' => $result['reason'],
        ]);

        // Only alert ops once retries are exhausted (see failed() below) --
        // an alert on the first of three tries would fire for transient
        // Stripe hiccups that resolve on their own on retry.
        if ($this->attempts() >= $this->tries) {
            $alerts->payoutFailed($request, $result['reason']);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Stranded escrow release job exhausted retries', [
            'request_id' => $this->strandedRequestId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        $request = UrbanGoodzStrandedRequest::find($this->strandedRequestId);
        if ($request) {
            app(MoniqueOperationsAlertService::class)->payoutFailed($request, class_basename($exception));
        }
    }
}
