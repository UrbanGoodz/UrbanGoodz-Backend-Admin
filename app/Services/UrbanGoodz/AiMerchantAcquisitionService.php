<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiAgent;
use App\Models\AiApproval;
use App\Models\AiOutreachMessage;
use App\Models\AiOutreachTemplate;
use App\Models\AiTask;
use App\Models\MerchantProspect;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\Vendor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiMerchantAcquisitionService
{
    private AiWorkforceAutonomyService $autonomyService;

    public function __construct(AiWorkforceAutonomyService $autonomyService)
    {
        $this->autonomyService = $autonomyService;
    }

    public function scanOrderAnywhereDemand(): array
    {
        $cfg = Config::get('urban_goodz.ai_workforce.demand_thresholds', [
            'rolling_window_days' => 30,
            'min_requests' => 3,
            'min_unique_customers' => 2,
            'min_estimated_value' => 0,
            'valid_statuses' => ['pending_review', 'sourcing', 'quote_ready', 'awaiting_payment', 'approved', 'completed'],
        ]);

        $windowDays = $cfg['rolling_window_days'] ?? 30;
        $minRequests = $cfg['min_requests'] ?? 3;
        $minCustomers = $cfg['min_unique_customers'] ?? 2;
        $validStatuses = $cfg['valid_statuses'] ?? ['pending_review', 'sourcing', 'quote_ready', 'awaiting_payment', 'approved', 'completed'];

        $cutoff = now()->subDays($windowDays);

        $requests = OrderAnywhereRequest::where('created_at', '>=', $cutoff)
            ->whereIn('status', $validStatuses)
            ->whereNotNull('store_vendor_name')
            ->where('store_vendor_name', '!=', '')
            ->get();

        $grouped = [];
        foreach ($requests as $req) {
            $normName = $this->normalizeBusinessName($req->store_vendor_name);
            if (empty($normName)) continue;

            if (!isset($grouped[$normName])) {
                $grouped[$normName] = [
                    'original_name' => $req->store_vendor_name,
                    'normalized_name' => $normName,
                    'address' => $req->store_vendor_address_or_website,
                    'requests' => [],
                    'customer_ids' => [],
                    'total_value' => 0,
                ];
            }
            $grouped[$normName]['requests'][] = $req;
            if ($req->customer_id) {
                $grouped[$normName]['customer_ids'][$req->customer_id] = true;
            } elseif ($req->customer_email) {
                $grouped[$normName]['customer_ids'][$req->customer_email] = true;
            }
            $val = $req->final_amount ?: ($req->quote_amount ?: ($req->budget_estimate ?: 0));
            $grouped[$normName]['total_value'] += (float)$val;
        }

        $createdProspects = [];
        $merchantAgent = AiAgent::where('slug', 'merchant_acquisition_employee')->first()
            ?? AiAgent::where('role', 'Merchant Acquisition')->first();

        foreach ($grouped as $normName => $data) {
            $reqCount = count($data['requests']);
            $custCount = count($data['customer_ids']);

            if ($reqCount >= $minRequests && $custCount >= $minCustomers) {
                // Check if existing vendor store exists
                $existingStore = Store::where(DB::raw('LOWER(name)'), $normName)->exists();
                if ($existingStore) continue;

                // Check opt out or do not contact
                $existingProspect = MerchantProspect::where('business_name_normalized', $normName)->first();
                if ($existingProspect && ($existingProspect->opt_out || $existingProspect->do_not_contact)) {
                    continue;
                }

                $prospect = MerchantProspect::updateOrCreate(
                    ['business_name_normalized' => $normName],
                    [
                        'business_name' => $data['original_name'],
                        'address' => $data['address'],
                        'data_source' => 'order_anywhere',
                        'prospect_status' => $existingProspect ? $existingProspect->prospect_status : 'new',
                        'order_anywhere_request_count' => $reqCount,
                        'unique_customer_count' => $custCount,
                        'estimated_demand_value' => $data['total_value'],
                        'first_demand_date' => $existingProspect ? $existingProspect->first_demand_date : now(),
                        'latest_demand_date' => now(),
                        'ai_agent_id' => $merchantAgent ? $merchantAgent->id : null,
                    ]
                );

                // Attach requests (without exposing private customer data)
                foreach ($data['requests'] as $req) {
                    DB::table('merchant_prospect_order_anywhere')->insertOrIgnore([
                        'merchant_prospect_id' => $prospect->id,
                        'order_anywhere_request_id' => $req->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Create AI research/scoring task
                if ($merchantAgent) {
                    $task = AiTask::create([
                        'ai_agent_id' => $merchantAgent->id,
                        'task_type' => 'score',
                        'source_type' => 'merchant_prospect',
                        'source_id' => $prospect->id,
                        'objective' => "Research and score prospect {$prospect->business_name} based on demand",
                        'status' => 'pending',
                        'input' => [
                            'prospect_id' => $prospect->id,
                            'request_count' => $reqCount,
                            'customer_count' => $custCount,
                            'estimated_value' => $data['total_value'],
                        ],
                    ]);

                    // Score prospect
                    $score = min(100, ($reqCount * 15) + ($custCount * 20) + ($data['total_value'] > 100 ? 25 : 10));
                    $prospect->update([
                        'prospect_score' => $score,
                        'confidence_score' => 0.85,
                        'prospect_status' => 'qualified',
                    ]);

                    $task->markCompleted(['prospect_score' => $score], 0.85);

                    // Draft outreach
                    $this->draftOutreach($prospect, 'demand_introduction');
                }

                $createdProspects[] = $prospect;
            }
        }

        return [
            'status' => 'success',
            'scanned_groups' => count($grouped),
            'qualifying_prospects' => count($createdProspects),
            'prospects' => $createdProspects,
        ];
    }

    public function draftOutreach(MerchantProspect $prospect, string $templateSlug = 'demand_introduction'): ?AiOutreachMessage
    {
        if ($prospect->opt_out || $prospect->do_not_contact) {
            return null;
        }

        $merchantAgent = AiAgent::where('slug', 'merchant_acquisition_employee')->first();
        $template = AiOutreachTemplate::where('slug', $templateSlug)->first();

        $subject = $template ? $template->subject : "Partnership Opportunity for {$prospect->business_name}";
        $body = $template ? $template->body : "Hi {{business_name}},\n\nWe have received {{request_count}} delivery requests from {{customer_count}} customers looking to order from your business on Urban Goodz.\n\nJoin our platform today: {{onboarding_url}}";

        $body = str_replace([
            '{{business_name}}',
            '{{request_count}}',
            '{{customer_count}}',
            '{{estimated_value}}',
            '{{onboarding_url}}'
        ], [
            $prospect->business_name,
            $prospect->order_anywhere_request_count,
            $prospect->unique_customer_count,
            '$' . number_format($prospect->estimated_demand_value, 2),
            Config::get('urban_goodz.ai_workforce.outreach.onboarding_url', 'https://urbangoodzdelivery.com/vendor/register')
        ], $body);

        $idempotencyKey = 'outreach_p' . $prospect->id . '_step0_' . date('Ymd');

        $existingMsg = AiOutreachMessage::where('idempotency_key', $idempotencyKey)->first();
        if ($existingMsg) {
            return $existingMsg;
        }

        $message = AiOutreachMessage::create([
            'merchant_prospect_id' => $prospect->id,
            'ai_agent_id' => $merchantAgent ? $merchantAgent->id : null,
            'ai_outreach_template_id' => $template ? $template->id : null,
            'direction' => 'outbound',
            'channel' => 'email',
            'to_email' => $prospect->public_email ?: 'contact@' . ($prospect->domain ?: 'example.com'),
            'from_email' => Config::get('urban_goodz.ai_workforce.outreach.sender_email', 'partnerships@urbangoodzdelivery.com'),
            'subject' => $subject,
            'body' => $body,
            'status' => 'draft', // MUST remain draft / requiring approval
            'idempotency_key' => $idempotencyKey,
            'sequence_step' => 0,
            'scheduled_at' => now(),
        ]);

        $prospect->update([
            'campaign_status' => 'pending_approval',
        ]);

        if ($merchantAgent) {
            $this->autonomyService->createApprovalRequest(
                $merchantAgent,
                null,
                'send_outreach',
                ['message_id' => $message->id, 'prospect_id' => $prospect->id, 'to' => $message->to_email],
                "Outreach draft for {$prospect->business_name} requires human review before sending."
            );
        }

        return $message;
    }

    public function recordReply(MerchantProspect $prospect, string $classification, ?string $replyContent = null): void
    {
        $prospect->update([
            'reply_status' => $classification,
            'last_contacted_at' => now(),
        ]);

        if (in_array($classification, ['remove_me', 'opt_out', 'complaint'])) {
            $prospect->update([
                'opt_out' => true,
                'prospect_status' => 'opted_out',
                'campaign_status' => 'stopped',
            ]);
        } elseif ($classification === 'interested' || $classification === 'ready_to_apply') {
            $prospect->update([
                'prospect_status' => 'engaged',
                'campaign_status' => 'active',
            ]);
        }
    }

    public function normalizeBusinessName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }
}
