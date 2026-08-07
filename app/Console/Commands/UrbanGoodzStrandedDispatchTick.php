<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedNotifier;
use Illuminate\Console\Command;

/**
 * Keeps live Stranded requests moving.
 *
 * Nothing else advances a request once it has been broadcast: offers lapse,
 * the radius has to widen, and the community window has to hand over to
 * professionals. Run this on a short schedule -- every minute is appropriate,
 * given the answer window is measured in seconds.
 */
class UrbanGoodzStrandedDispatchTick extends Command
{
    protected $signature = 'stranded:dispatch-tick {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Expire lapsed Stranded offers, widen the broadcast radius, and escalate to professional providers';

    public function handle(UrbanGoodzStrandedDispatcher $dispatcher, UrbanGoodzStrandedNotifier $notifier): int
    {
        $dry = (bool) $this->option('dry-run');

        $expired = $this->expireLapsedOffers($dry);
        $this->info("Offers expired: {$expired}");

        // A responder who let an offer lapse without answering is released,
        // not penalised further -- an unanswered offer is usually somebody
        // driving, not somebody ignoring the network.
        $widened = 0;
        $escalated = 0;
        $exhausted = 0;

        $live = UrbanGoodzStrandedRequest::query()
            ->whereIn('status', ['broadcasting', 'awaiting_selection'])
            ->whereNull('selected_offer_id')
            ->get();

        foreach ($live as $request) {
            // Somebody has accepted and is waiting on the customer to choose.
            // Leave it alone; widening now would only add noise.
            $hasAccepted = UrbanGoodzStrandedOffer::where('request_id', $request->getKey())
                ->where('status', 'accepted')
                ->exists();

            if ($hasAccepted) {
                continue;
            }

            $windowLapsed = $request->broadcast_expires_at !== null
                && $request->broadcast_expires_at->isPast();

            if (!$windowLapsed) {
                continue;
            }

            $escalationDue = $request->escalation_due_at !== null
                && $request->escalation_due_at->isPast()
                && $request->escalated_at === null;

            if ($dry) {
                $this->line("  would " . ($escalationDue ? 'escalate' : 'widen') . " {$request->request_number}");
                continue;
            }

            if ($escalationDue) {
                $dispatcher->escalateToProfessionals($request);
                $escalated++;
                continue;
            }

            if ($dispatcher->widen($request)) {
                $widened++;
                continue;
            }

            // Ladder exhausted and nobody reachable. Escalate if we have not
            // already; otherwise tell the customer rather than leaving them
            // watching a spinner.
            if ($request->escalated_at === null) {
                $dispatcher->escalateToProfessionals($request);
                $escalated++;
                continue;
            }

            $request->update(['status' => 'no_responders']);
            $notifier->noRespondersFound($request->fresh());
            $exhausted++;
        }

        $this->info("Widened: {$widened} | Escalated: {$escalated} | Exhausted: {$exhausted}");

        return self::SUCCESS;
    }

    private function expireLapsedOffers(bool $dry): int
    {
        $query = UrbanGoodzStrandedOffer::where('status', 'offered')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($dry) {
            return $query->count();
        }

        $ids = $query->pluck('responder_id', 'id');

        $count = UrbanGoodzStrandedOffer::whereIn('id', $ids->keys())
            ->update(['status' => 'expired', 'responded_at' => now()]);

        // Track misses so trust scoring has something honest to work from.
        foreach ($ids->values()->unique() as $responderId) {
            UrbanGoodzStrandedResponder::where('user_id', $responderId)->increment('missed_jobs');
        }

        return $count;
    }
}
