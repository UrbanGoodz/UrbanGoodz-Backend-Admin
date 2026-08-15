<?php

namespace App\Services\UrbanGoodz;

use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;
use App\Models\Order;
use App\Models\User;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use Illuminate\Support\Facades\DB;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\Item;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;

class UrbanGoodzAIConciergeService
{
    private UrbanGoodzAIService $ai;

    /** Prior turns sent to the model for real conversational memory. */
    private const HISTORY_TURNS = 6;

    /** Rows kept per customer; older ones are pruned to cap prompt/DB growth. */
    private const MAX_STORED_PER_CUSTOMER = 30;

    public function __construct(UrbanGoodzAIService $ai)
    {
        $this->ai = $ai;
    }

    public function processQuery(string $queryText, ?int $customerId = null, string $source = 'customer_api', ?string $sessionId = null): UrbanGoodzAIConversation
    {
        if (!$customerId && $source !== 'admin_test') {
            throw new AuthenticationException('Customer authentication is required.');
        }

        $context = $this->buildCustomerContext($customerId);
        $systemPrompt = $this->buildSystemPrompt($context);
        $history = $this->recentHistory($customerId, $sessionId);

        if ($this->ai->isConfigured()) {
            $result = $this->processWithAI($queryText, $customerId, $systemPrompt, $context, $source, $sessionId, $history);
        } else {
            $result = $this->processWithKeywords($queryText, $customerId, $source, $sessionId);
        }

        $this->pruneOldConversations($customerId);

        return $result;
    }

    /**
     * Last few turns for this customer's session, oldest first, formatted for
     * the provider. Bounded so a long-running conversation cannot silently
     * balloon the token bill.
     *
     * @return list<array{role: string, content: string}>
     */
    private function recentHistory(?int $customerId, ?string $sessionId): array
    {
        if (!$customerId || !$sessionId) {
            return [];
        }

        $rows = UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->where('session_id', $sessionId)
            ->whereNotNull('response_text')
            ->latest()
            ->limit(self::HISTORY_TURNS)
            ->get(['query_text', 'response_text'])
            ->reverse();

        $history = [];
        foreach ($rows as $row) {
            $history[] = ['role' => 'user', 'content' => $row->query_text];
            $history[] = ['role' => 'assistant', 'content' => $row->response_text];
        }

        return $history;
    }

    /**
     * Keeps only the most recent rows per customer -- old conversation turns
     * are deleted rather than kept forever, so the history window built above
     * (and the table itself) stays cheap regardless of how long an account
     * has been active.
     */
    private function pruneOldConversations(?int $customerId): void
    {
        if (!$customerId) {
            return;
        }

        $keepIds = UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->latest()
            ->limit(self::MAX_STORED_PER_CUSTOMER)
            ->pluck('id');

        UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function processWithAI(string $queryText, ?int $customerId, string $systemPrompt, array $context, string $source, ?string $sessionId, array $history): UrbanGoodzAIConversation
    {
        $possibleIntents = UrbanGoodzAIIntent::where('is_active', true)
            ->get()
            ->map(fn($i) => ['slug' => $i->slug, 'description' => $i->description ?? $i->name])
            ->toArray();

        $classification = $this->ai->classifyIntent($queryText, $possibleIntents);
        $intentSlug = $classification['intent'] ?? 'unknown';
        $confidence = $classification['confidence'] ?? 0;

        // Safety-critical override: the AI's own free-form classification can
        // miss or mis-rank this one, and the "Get Help Now" hand-off button
        // must not depend on the model getting it right every time. A plain
        // keyword match always wins here, regardless of what the AI returned.
        if ($this->looksStranded($queryText)) {
            $intentSlug = 'stranded';
            $confidence = max($confidence, 0.9);
        }

        $intent = UrbanGoodzAIIntent::where('slug', $intentSlug)->first();
        $entities = $classification['entities'] ?? [];

        $groundingDelta = [
            'detected_intent' => $intentSlug,
            'entities' => $entities,
        ];

        $marketplaceResults = $this->searchMarketplaceIfRelevant($intentSlug, $queryText, $entities);
        if ($marketplaceResults !== null) {
            $groundingDelta['marketplace_results'] = $marketplaceResults;
        }

        // The customer context is already grounded into the persona system
        // prompt; only the classification delta (and any live search results)
        // is added here, plus the bounded prior-turn history for real memory.
        $providerResult = $this->ai->chatResult($systemPrompt, $queryText, $groundingDelta, $history);
        $responseText = $providerResult['response'];

        $needsEscalation = !$providerResult['success']
            || $confidence < 0.5
            || str_contains(strtolower($responseText), 'escalate')
            || str_contains(strtolower($responseText), 'human agent');
        $status = $providerResult['success'] ? ($needsEscalation ? 'pending' : 'resolved') : 'failed';

        return UrbanGoodzAIConversation::create([
            'customer_id' => $customerId,
            'session_id' => $sessionId,
            'query_text' => $queryText,
            'detected_intent_id' => $intent?->id,
            'confidence_score' => round($confidence * 100, 2),
            'response_text' => $responseText,
            'status' => $status,
            'source' => $source,
            'metadata' => [
                'response_source' => 'ai_provider',
                'provider_success' => $providerResult['success'],
                'provider_error_code' => $providerResult['error_code'],
                'marketplace_result_count' => $marketplaceResults !== null ? count($marketplaceResults) : null,
            ],
        ]);
    }

    /**
     * Real, live inventory lookup -- not invented product names or prices.
     * Runs only when the classified intent or extracted entities indicate the
     * customer is actually looking for something to buy. Returns null (not an
     * empty array) when no search was warranted, so the prompt only claims
     * "these are the real results" when a real search actually ran.
     *
     * @return list<array<string, mixed>>|null
     */
    private function searchMarketplaceIfRelevant(string $intentSlug, string $queryText, array $entities): ?array
    {
        $searchTerm = $entities['search_query'] ?? $entities['items'] ?? null;
        $shouldSearch = in_array($intentSlug, ['marketplace-search', 'marketplace_search', 'order_anywhere', 'order-anywhere'], true)
            || $searchTerm !== null;

        if (!$shouldSearch) {
            return null;
        }

        $term = trim((string) ($searchTerm ?? $queryText));
        if ($term === '') {
            return null;
        }

        $keywords = array_filter(preg_split('/\s+/', $term) ?: []);
        if ($keywords === []) {
            return null;
        }

        try {
            $items = Item::active()
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('name', 'like', "%{$keyword}%");
                    }
                })
                ->when(isset($entities['budget_max']), fn ($q) => $q->where('price', '<=', (float) $entities['budget_max']))
                ->with('store:id,name')
                ->limit(5)
                ->get(['id', 'name', 'price', 'store_id']);
        } catch (\Throwable $e) {
            return [];
        }

        return $items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => (float) $item->price,
            'store' => $item->store?->name,
        ])->toArray();
    }

    private function processWithKeywords(string $queryText, ?int $customerId, string $source, ?string $sessionId = null): UrbanGoodzAIConversation
    {
        $queryLower = strtolower(trim($queryText));
        $intents = UrbanGoodzAIIntent::where('is_active', true)->orderBy('sort_order')->get();

        $bestIntent = null;
        $bestScore = 0;

        foreach ($intents as $intent) {
            $keywords = $intent->keywords ?? [];
            foreach ($keywords as $keyword) {
                $keywordLower = strtolower(trim($keyword));
                if (str_contains($queryLower, $keywordLower)) {
                    $score = $this->scoreKeyword($queryLower, $keywordLower);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestIntent = $intent;
                    }
                }
            }
        }

        // A recognized emergency always gets its calm, specific hand-off line
        // rather than the generic order/payment fallback below.
        $responseText = $bestIntent?->slug === 'stranded' && $bestIntent->response_template
            ? $bestIntent->response_template
            : $this->getContextualResponse($queryLower, $customerId);
        $grounded = $customerId && ($bestIntent?->slug === 'stranded' || $this->isGroundedCustomerQuery($queryLower));

        return UrbanGoodzAIConversation::create([
            'customer_id' => $customerId,
            'session_id' => $sessionId,
            'query_text' => $queryText,
            'detected_intent_id' => $bestIntent?->id,
            'confidence_score' => $bestScore > 0 ? $bestScore : null,
            'response_text' => $responseText,
            'status' => $grounded ? 'resolved' : 'pending',
            'source' => $source,
            'metadata' => [
                'matched_intent' => $bestIntent?->intent_name ?? null,
                'query_length' => strlen($queryText),
                'source' => $source,
                'response_source' => $grounded ? 'deterministic_database' : 'static_help',
            ],
        ]);
    }

    /**
     * The Concierge persona supplies identity, voice, and the platform rule
     * block. This method supplies only the job and the grounding facts, so the
     * customer-facing voice stays defined in exactly one place.
     *
     * @see \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry
     * @see docs/URBAN_GOODZ_AI_PERSONALITIES.md
     */
    private function buildSystemPrompt(array $context): string
    {
        $customerInfo = $context['customer'] ?? null;

        $task = "Help this customer with discovery, shopping, orders, deliveries, payments,
account questions, and platform features. Look up their real data to answer
personally, explain what they can do next, and open the correct workflow.

Escalate to a human agent when you cannot resolve the issue, when the customer
asks for one, or when the matter is legal or safety related.

Urban Goodz capabilities you can point them to:
- Order Anywhere: request items from any store for delivery
- Fashion Fit: connect with tailors and stylists
- Community Marketplace: buy and sell within the community
- Earn Money: referral and affiliate programs
- Book Anything: schedule any service
- Creator Commerce: influencer and creator partnerships
- Medical Courier: prescription and medical deliveries
- Logistics: freight and load management
- Load Board: browse and accept delivery loads

Use their name when you have it. Give specific details — order numbers, dates,
amounts — whenever the context supplies them.

If the customer describes being stranded, broken down, stuck, or unsafe on the
road, drop the personality immediately. Respond with calm, focused concern,
ask if they are somewhere safe, and tell them you're connecting them to Urban
Goodz Stranded so a nearby Goodz Samaritan or professional can reach them. Do
not joke, and do not delay the hand-off with small talk.

If `marketplace_results` is present in the application data below, those are
the only real, currently-available items you may recommend — use their actual
names, prices, and store names verbatim. Never invent a product, price, or
store that is not in that list. If `marketplace_results` is absent or empty,
say plainly that you couldn't find a matching item right now rather than
guessing at one.";

        $grounding = [
            'customer_id' => $context['customer_id'],
            'customer_name' => $customerInfo['name'] ?? null,
            'account_since' => $customerInfo['created_at'] ?? null,
            'total_orders' => $context['total_orders'] ?? 0,
            'total_spent' => number_format((float) ($context['total_spent'] ?? 0), 2),
            'active_deliveries' => $context['active_deliveries'] ?? 0,
            'recent_orders' => $context['recent_orders'] ?? [],
            'recent_deliveries' => $context['recent_deliveries'] ?? [],
        ];

        return $this->ai->persona(PersonaRegistry::CONCIERGE)->systemPrompt($task, $grounding);
    }

    private function buildCustomerContext(?int $customerId): array
    {
        $context = [
            'customer_id' => $customerId,
            'customer' => null,
            'recent_orders' => [],
            'recent_deliveries' => [],
            'total_orders' => 0,
            'total_spent' => 0,
            'active_deliveries' => 0,
        ];

        if (!$customerId) return $context;

        try {
            $customer = User::find($customerId);
            if ($customer) {
                $context['customer'] = [
                    'name' => $customer->name ?? $customer->f_name . ' ' . $customer->l_name,
                    'created_at' => $customer->created_at instanceof \Carbon\Carbon ? $customer->created_at->format('M Y') : ($customer->created_at ?? 'Unknown'),
                ];

                $context['total_orders'] = Order::where('user_id', $customerId)->count();
                $context['total_spent'] = (float) Order::where('user_id', $customerId)->sum('order_amount');

                $context['recent_orders'] = Order::withoutGlobalScope('storage')
                    ->where('user_id', $customerId)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'order_status', 'order_amount', 'created_at'])
                    ->map(fn($o) => [
                        'id' => $o->id,
                        'status' => $o->order_status,
                        'amount' => number_format($o->order_amount, 2),
                        'date' => $o->created_at instanceof \Carbon\Carbon ? $o->created_at->format('M d, Y') : ($o->created_at ?? 'Unknown'),
                    ])->toArray();

                $context['active_deliveries'] = Order::where('user_id', $customerId)
                    ->whereIn('order_status', ['confirmed', 'processing', 'picked_up', 'on_the_way'])
                    ->count();
            }
        } catch (\Throwable $e) {
            // Graceful fallback — return empty context
        }

        return $context;
    }

    private function getContextualResponse(string $queryLower, ?int $customerId): string
    {
        if ($customerId && (
            str_contains($queryLower, 'track')
            || str_contains($queryLower, 'delivery')
            || str_contains($queryLower, 'order status')
        )) {
            return $this->getCustomerOrderResponse($customerId);
        }

        if ($customerId && (
            str_contains($queryLower, 'invoice')
            || str_contains($queryLower, 'payment')
            || str_contains($queryLower, 'billing')
            || str_contains($queryLower, 'refund')
        )) {
            return $this->getCustomerPaymentResponse($customerId);
        }

        if (str_contains($queryLower, 'help') || str_contains($queryLower, 'support') || str_contains($queryLower, 'contact')) {
            return "I can help you with:\n"
                . "- Your order and delivery status\n"
                . "- Your payment and refund status\n"
                . "- Product, service, event, and creator discovery\n"
                . "- Order Anywhere and courier request workflows\n\n"
                . "Please describe what you need, or contact support at support@urbangoodz.com for urgent matters.";
        }

        return "I could not complete a database-backed action from that request. No action was taken. "
            . "Please ask about your orders or payments, or open the relevant Urban Goodz workflow.";
    }

    private function getCustomerOrderResponse(int $customerId): string
    {
        $orders = Order::withoutGlobalScope('storage')
            ->where('user_id', $customerId)
            ->latest()
            ->limit(5)
            ->get(['id', 'order_status', 'order_amount']);

        if ($orders->isEmpty()) {
            return 'I found no orders for your authenticated account. No delivery status was changed.';
        }

        $lines = $orders->map(
            fn(Order $order) => sprintf(
                '- Order #%d: %s ($%s)',
                $order->id,
                $order->order_status,
                number_format((float) $order->order_amount, 2)
            )
        )->implode("\n");

        return "Your latest orders:\n{$lines}";
    }

    private function getCustomerPaymentResponse(int $customerId): string
    {
        if (!Schema::hasTable('urban_goodz_payment_ledgers')) {
            return 'Payment history is not available from the ledger right now. No payment or refund action was taken.';
        }

        $entries = UrbanGoodzPaymentLedger::where('customer_id', $customerId)
            ->latest()
            ->limit(5)
            ->get(['ledger_number', 'event_type', 'amount', 'currency', 'payment_status']);

        if ($entries->isEmpty()) {
            return 'I found no payment ledger entries for your authenticated account. No payment or refund action was taken.';
        }

        $lines = $entries->map(
            fn(UrbanGoodzPaymentLedger $entry) => sprintf(
                '- %s: %s %s %s (%s)',
                $entry->ledger_number,
                $entry->currency,
                number_format((float) $entry->amount, 2),
                $entry->event_type,
                $entry->payment_status
            )
        )->implode("\n");

        return "Your latest payment records:\n{$lines}";
    }

    private function looksStranded(string $queryText): bool
    {
        $normalized = strtolower($queryText);
        foreach ([
            'stranded', 'broke down', 'broken down', "i'm stuck", 'im stuck', 'stuck on the',
            'flat tire', 'dead battery', 'jump start', "won't start", 'wont start', 'roadside',
            'need a tow', 'car trouble', 'stuck on the highway', 'stuck on the side of the road',
        ] as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }

    private function isGroundedCustomerQuery(string $queryLower): bool
    {
        foreach (['track', 'delivery', 'order status', 'invoice', 'payment', 'billing', 'refund'] as $term) {
            if (str_contains($queryLower, $term)) {
                return true;
            }
        }

        return false;
    }

    private function getLoadBoardStatusResponse(): string
    {
        $availableCount = UrbanGoodzLoadBoardLoad::where('status', 'available')->count();
        $assignedCount = UrbanGoodzLoadBoardLoad::where('status', 'assigned')->count();
        $inTransitCount = UrbanGoodzLoadBoardLoad::where('status', 'in_transit')->count();
        $avgRate = UrbanGoodzLoadBoardLoad::where('status', 'available')->avg('rate_per_mile');

        return "Load Board Status:\n"
            . "- Available loads: {$availableCount}\n"
            . "- Assigned loads: {$assignedCount}\n"
            . "- In transit: {$inTransitCount}\n"
            . "- Average rate/mile: $" . number_format($avgRate ?? 0, 2) . "\n\n"
            . "All loads are actively managed. For specific load inquiries, please provide the load number.";
    }

    private function getTrackingResponse(string $queryLower): string
    {
        $pendingCount = UrbanGoodzRoutePackage::where('status', 'pending_review')->count();
        $inTransitCount = UrbanGoodzRoutePackage::where('status', 'in_transit')->count();
        $deliveredToday = UrbanGoodzRoutePackage::where('status', 'delivered')
            ->whereDate('updated_at', today())
            ->count();

        return "Package Tracking Overview:\n"
            . "- Pending review: {$pendingCount}\n"
            . "- Currently in transit: {$inTransitCount}\n"
            . "- Delivered today: {$deliveredToday}\n\n"
            . "For specific package tracking, please provide your tracking ID (e.g., UG-XXXXXX).";
    }

    private function getDriverStatusResponse(): string
    {
        $activeDrivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->count();
        $availableDrivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->where('current_orders', '<', 3)
            ->count();

        return "Driver Fleet Status:\n"
            . "- Active drivers: {$activeDrivers}\n"
            . "- Available for dispatch: {$availableDrivers}\n\n"
            . "All drivers are background-checked and insured. For urgent driver needs, contact dispatch@urbangoodz.com.";
    }

    private function getPricingResponse(): string
    {
        $avgRate = UrbanGoodzLoadBoardLoad::where('status', 'available')->avg('rate_per_mile');
        $avgPayout = UrbanGoodzLoadBoardLoad::where('status', 'available')->avg('payout_amount');

        return "Current Pricing Overview:\n"
            . "- Average rate per mile: $" . number_format($avgRate ?? 0, 2) . "\n"
            . "- Average load payout: $" . number_format($avgPayout ?? 0, 2) . "\n\n"
            . "Rates vary by lane, equipment type, and load characteristics. For a specific quote, please provide origin/destination and equipment type.";
    }

    private function getPaymentResponse(): string
    {
        return "Payment & Billing:\n"
            . "- Invoices are generated upon load completion\n"
            . "- Payment terms: Net 15 for business clients, immediate for individual loads\n"
            . "- Accepted: ACH, wire transfer, credit card\n\n"
            . "For invoice questions, contact billing@urbangoodz.com with your account number.";
    }

    private function getRouteResponse(): string
    {
        $activeRoutes = \App\Models\UrbanGoodzDedicatedRoute::where('status', 'active')->count();
        $pendingRoutes = \App\Models\UrbanGoodzDedicatedRoute::where('status', 'pending')->count();

        return "Dedicated Routes:\n"
            . "- Active routes: {$activeRoutes}\n"
            . "- Pending setup: {$pendingRoutes}\n\n"
            . "Dedicated routes offer priority scheduling and consistent pricing. Contact sales@urbangoodz.com to set up a new route.";
    }

    private function getMedicalCourierResponse(): string
    {
        $activeJobs = UrbanGoodzMedicalCourierJob::where('status', 'in_progress')->count();
        $completedToday = UrbanGoodzMedicalCourierJob::where('status', 'completed')
            ->whereDate('updated_at', today())
            ->count();

        return "Medical Courier Services:\n"
            . "- Active deliveries: {$activeJobs}\n"
            . "- Completed today: {$completedToday}\n\n"
            . "All medical couriers are HIPAA-compliant with temperature-controlled vehicles. For urgent medical deliveries, call our 24/7 line.";
    }

    private function getBusinessClientResponse(): string
    {
        $activeClients = \App\Models\UrbanGoodzBusinessClient::where('status', 'active')->count();
        $activeJobs = UrbanGoodzBusinessClientJob::where('status', 'in_progress')->count();

        return "Business Client Portal:\n"
            . "- Active accounts: {$activeClients}\n"
            . "- Jobs in progress: {$activeJobs}\n\n"
            . "Business clients have access to dedicated account managers, volume discounts, and priority support. Contact your account manager or sales@urbangoodz.com.";
    }

    private function scoreKeyword(string $query, string $keyword): float
    {
        $baseScore = 50;
        $wordCount = str_word_count($keyword);
        $exactMatch = str_contains($query, $keyword);
        if ($exactMatch && $wordCount > 1) return $baseScore + 30;
        if ($exactMatch) return $baseScore + 10;
        return 0;
    }
    private function defaultFallbackResponse(): string
    {
        return "Thanks for reaching out to Urban Goodz! I'm not quite sure how to help with that yet. "
            . "A customer service representative will review your query and get back to you soon. "
            . "In the meantime, you can check our Help section in the app for common questions.";
    }
}
