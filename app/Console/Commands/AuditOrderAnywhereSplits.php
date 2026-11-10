<?php

namespace App\Console\Commands;

use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Console\Command;

/**
 * Read-only money audit: lists captured Order Anywhere requests whose split rows do not match the
 * request's own figures (a recipient with no row, or the wrong total). Changes nothing.
 */
class AuditOrderAnywhereSplits extends Command
{
    protected $signature = 'order-anywhere:audit-splits {--days=90}';
    protected $description = 'Report captured Order Anywhere requests whose split rows disagree with their totals';

    public function handle(UrbanGoodzPaymentService $payments): int
    {
        $bad = 0;
        OrderAnywhereRequest::whereIn('payment_status', ['captured', 'partially_captured'])
            ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->orderBy('id')
            ->chunkById(200, function ($requests) use ($payments, &$bad) {
                foreach ($requests as $request) {
                    $problems = $payments->splitRowMismatches($request);
                    if ($problems) {
                        $bad++;
                        $this->line("{$request->request_number} (#{$request->id}): " . implode('; ', $problems));
                    }
                }
            });

        $this->info("mismatched={$bad}");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
