<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier;
use App\Domain\Stranded\Support\MoniqueOperationsAlertService;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSettings;
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

    public function handle(
        UrbanGoodzStrandedDispatcher $dispatcher,
        UrbanGoodzStrandedNotifier $notifier,
        MoniqueOperationsAlertService $alerts
    ): int {
        $dry = (bool) $this->option('dry-run');

        $stalled = $this->alertStalledAssignments($dry, $alerts);
        $this->info("Stalled assignments flagged: {$stalled}");

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
            $fresh = $request->fresh();
            $notifier->noRespondersFound($fresh);
            $alerts->noResponderFound($fresh);
            $exhausted++;
        }

        $this->info("Widened: {$widened} | Escalated: {$escalated} | Exhausted: {$exhausted}");

        return self::SUCCESS;
    }

    /**
     * A responder was assigned but has not sent an en-route update within the
     * configured window. This is an ops alert, not a customer notification --
     * repeating "help is on the way" to someone whose help may not actually
     * be coming would be worse than saying nothing. One alert per assignment:
     * stall_alert_sent_at stops this from paging ops again every tick.
     */
    private function alertStalledAssignments(bool $dry, MoniqueOperationsAlertService $alerts): int
    {
        $threshold = now()->subMinutes(UrbanGoodzStrandedSettings::responderStallMinutes());

        $stalled = UrbanGoodzStrandedRequest::query()
            ->where('status', 'assigned')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<', $threshold)
            ->whereNull('en_route_at')
            ->whereNull('stall_alert_sent_at')
            ->get();

        if ($dry) {
            return $stalled->count();
        }

        foreach ($stalled as $request) {
            $minutes = (int) $request->assigned_at->diffInMinutes(now());
            $alerts->responderStalled($request, $minutes);
            $request->update(['stall_alert_sent_at' => now()]);
        }

        return $stalled->count();
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
