<?php

namespace App\Jobs;

use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Services\OrderAnywhereCardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class IssueOrderAnywherePurchaseCard implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 1800;

    public function __construct(public int $cardRequestId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return "order-anywhere-card:{$this->cardRequestId}";
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(OrderAnywhereCardService $cards): void
    {
        $card = $cards->issuePreparedCard($this->cardRequestId);
        if ($card->card_status === 'issuance_retry_pending') {
            throw new RuntimeException('Issuing provider request requires retry.');
        }
    }

    public function failed(Throwable $exception): void
    {
        $card = UrbanGoodzOrderAnywhereCardRequest::find($this->cardRequestId);
        if (! $card || in_array($card->card_status, ['issued', 'active', 'authorized'], true)) {
            return;
        }
        $card->update([
            'card_status' => 'failed',
            'failure_category' => $card->failure_category ?: 'retry_exhausted',
            'failure_reason' => 'Automatic issuance retries were exhausted. Owner review is required.',
            'final_failure_at' => now(),
            'retry_eligible_at' => now()->addHour(),
        ]);
        $card->orderAnywhereRequest?->logActivity(
            'driver_card_issuance_final_failure',
            'Automatic purchase-card issuance requires owner review.',
            [],
            ['failure_category' => $card->failure_category],
            ['card_request_id' => $card->id]
        );
        Log::critical('ORDER ANYWHERE CARD ISSUANCE RETRIES EXHAUSTED', [
            'card_request_id' => $card->id,
            'failure_category' => $card->failure_category,
        ]);
    }
}
