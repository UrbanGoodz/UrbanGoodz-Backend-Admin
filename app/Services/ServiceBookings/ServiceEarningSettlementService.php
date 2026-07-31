<?php

namespace App\Services\ServiceBookings;

use App\Models\UrbanGoodzServiceProviderEarning;
use App\Services\UrbanGoodzNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moves provider earnings through the settlement lifecycle.
 *
 * pending -> approved -> settled, with `void` available from either
 * pre-settlement state. Settlement is the only irreversible step, so it is the
 * only one that stamps a batch reference.
 */
class ServiceEarningSettlementService
{
    private const TRANSITIONS = [
        'pending' => ['approved', 'void'],
        'approved' => ['settled', 'void'],
        'settled' => [],
        'void' => [],
    ];

    public function __construct(private UrbanGoodzNotificationService $notifications)
    {
    }

    public function adjust(UrbanGoodzServiceProviderEarning $earning, int $adjustmentMinor, string $reason): UrbanGoodzServiceProviderEarning
    {
        return DB::transaction(function () use ($earning, $adjustmentMinor, $reason) {
            $locked = UrbanGoodzServiceProviderEarning::whereKey($earning->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'settled', 409, 'A settled earning can no longer be adjusted.');
            abort_if($locked->status === 'void', 409, 'A void earning can no longer be adjusted.');
            abort_if(
                (int) $locked->provider_amount_minor + $adjustmentMinor < 0,
                422,
                'An adjustment cannot reduce a provider payout below zero.'
            );

            $locked->update([
                'adjustment_minor' => $adjustmentMinor,
                'adjustment_reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    public function transition(UrbanGoodzServiceProviderEarning $earning, string $to, ?string $batch = null): UrbanGoodzServiceProviderEarning
    {
        return DB::transaction(function () use ($earning, $to, $batch) {
            $locked = UrbanGoodzServiceProviderEarning::whereKey($earning->id)->lockForUpdate()->firstOrFail();
            $from = (string) $locked->status;
            abort_unless(
                in_array($to, self::TRANSITIONS[$from] ?? [], true),
                409,
                "Illegal settlement transition: {$from} -> {$to}."
            );

            $updates = ['status' => $to];
            if ($to === 'approved') {
                $updates['approved_at'] = now();
            }
            if ($to === 'settled') {
                $updates['settled_at'] = now();
                $updates['settlement_batch'] = $batch ?: 'batch-'.now()->format('Ymd').'-'.Str::lower(Str::random(6));
            }
            $locked->update($updates);
            $fresh = $locked->fresh();

            if ($to === 'settled') {
                $this->notifications->notifyVendor(
                    (int) ($fresh->provider?->vendor_id ?? 0),
                    'Service earnings settled',
                    'A service payout of '.number_format($fresh->payableAmountMinor() / 100, 2).' '.$fresh->currency.' has been settled.',
                    [
                        'type' => 'service_earning_settled',
                        'earning_id' => $fresh->id,
                        'settlement_batch' => $fresh->settlement_batch,
                    ]
                );
            }

            return $fresh;
        });
    }

    /**
     * Settle every approved earning for the given providers as one batch and
     * return the batch reference alongside the settled records.
     *
     * @param  Collection<int, UrbanGoodzServiceProviderEarning>  $earnings
     * @return array{batch: string, settled: int, amount_minor: int}
     */
    public function settleBatch(Collection $earnings): array
    {
        $batch = 'batch-'.now()->format('Ymd').'-'.Str::lower(Str::random(6));
        $amount = 0;
        $count = 0;

        foreach ($earnings as $earning) {
            if ($earning->status !== 'approved') {
                continue;
            }
            $settled = $this->transition($earning, 'settled', $batch);
            $amount += $settled->payableAmountMinor();
            $count++;
        }

        return ['batch' => $batch, 'settled' => $count, 'amount_minor' => $amount];
    }
}
