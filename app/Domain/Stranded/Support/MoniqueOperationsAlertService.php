<?php

namespace App\Domain\Stranded\Support;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Models\UrbanGoodzStrandedRequest;

/**
 * Monique's operations-facing half for Stranded.
 *
 * config/urban_goodz_personas.php's "chief_of_staff" persona (display name
 * Monique) was presentation only -- a name, an avatar, a chat surface, no
 * behaviour. AiMoniqueNotification + MoniqueProactiveAttentionService
 * already exist as Monique's real, working ops-alert channel for
 * vendor/admin business-portal concerns (delayed orders, low stock, failed
 * jobs) -- this is that same channel, wired to Stranded, which had never
 * been connected to it.
 *
 * Deliberately event-driven, not polling: MoniqueProactiveAttentionService
 * observes on a schedule and infers what might be wrong. Stranded already
 * knows the instant something actually fails (a payout call, a dispatch
 * tick's stall check) -- calling straight from there is more accurate than
 * inferring it later from a sweep, and is what "event-driven... connected to
 * operational events" in the product spec means for this feature specifically.
 *
 * Three functions, matching the spec exactly:
 *   INFORM     an admin-facing notification exists at all
 *   COORDINATE the alert names what should happen next, not just what broke
 *   ESCALATE   priority + is_actionable surfaces it above routine notices
 */
class MoniqueOperationsAlertService
{
    public function payoutFailed(UrbanGoodzStrandedRequest $request, string $reason): void
    {
        $this->alertAdmins(
            'stranded_payout_failed',
            AiMoniqueNotification::PRIORITY_URGENT,
            'Stranded payout failed: ' . $request->request_number,
            "A responder payout could not be completed ({$reason}). The request is held in escrow_status = payout_held pending manual resolution."
        );
    }

    public function paymentFailed(UrbanGoodzStrandedRequest $request, string $context): void
    {
        $this->alertAdmins(
            'stranded_payment_failed',
            AiMoniqueNotification::PRIORITY_HIGH,
            'Stranded payment failed: ' . $request->request_number,
            "A customer charge failed during {$context}. The request may be stuck waiting on payment the customer believes already happened."
        );
    }

    public function noResponderFound(UrbanGoodzStrandedRequest $request): void
    {
        $this->alertAdmins(
            'stranded_no_responders',
            AiMoniqueNotification::PRIORITY_MEDIUM,
            'No responders found: ' . $request->request_number,
            'The broadcast radius ladder was exhausted with nobody available. The customer has been told; this may need manual outreach.'
        );
    }

    /** A responder accepted (and even started) but has not moved in the configured window. */
    public function responderStalled(UrbanGoodzStrandedRequest $request, int $minutesSinceAccept): void
    {
        $this->alertAdmins(
            'stranded_responder_stalled',
            AiMoniqueNotification::PRIORITY_HIGH,
            'Responder appears stalled: ' . $request->request_number,
            "Assigned responder has not progressed in {$minutesSinceAccept} minutes. Customer is waiting; consider checking in or reopening the request."
        );
    }

    public function disputeOpened(UrbanGoodzStrandedRequest $request, string $summary): void
    {
        $this->alertAdmins('stranded_dispute', AiMoniqueNotification::PRIORITY_HIGH, 'Dispute opened: ' . $request->request_number, $summary);
    }

    public function fraudRiskFlagged(UrbanGoodzStrandedRequest $request, string $signal): void
    {
        $this->alertAdmins(
            'stranded_fraud_risk',
            AiMoniqueNotification::PRIORITY_URGENT,
            'Risk signal flagged: ' . $request->request_number,
            "Automated risk signal: {$signal}. Review before this request's payout releases."
        );
    }

    /**
     * One row per active admin, matching AiCopilotService's own admin
     * fan-out. Deduped per admin/category within the window
     * MoniqueProactiveAttentionService itself uses (6 hours) -- a request
     * stuck in payout_held should not re-page every admin on every retry.
     */
    private function alertAdmins(string $category, string $priority, string $title, string $message): void
    {
        $adminIds = Admin::where('is_active', 1)->pluck('id');

        foreach ($adminIds as $adminId) {
            $recent = AiMoniqueNotification::forAccount('admin', $adminId)
                ->where('category', $category)
                ->where('title', $title)
                ->where('status', AiMoniqueNotification::STATUS_PENDING)
                ->where('created_at', '>=', now()->subHours(6))
                ->exists();

            if ($recent) {
                continue;
            }

            AiMoniqueNotification::create([
                'account_type' => 'admin',
                'account_id' => $adminId,
                'category' => $category,
                'priority' => $priority,
                'title' => $title,
                'message' => $message,
                'actions' => [
                    ['label' => 'Review', 'action' => 'review'],
                    ['label' => 'Dismiss', 'action' => 'dismiss'],
                ],
                'is_actionable' => true,
                'can_auto_resolve' => false,
                'auto_resolved' => false,
                'status' => AiMoniqueNotification::STATUS_PENDING,
                'delivered_channels' => ['in_app', 'notification_center'],
            ]);
        }
    }
}
