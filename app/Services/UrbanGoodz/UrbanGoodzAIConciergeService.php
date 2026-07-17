<?php

namespace App\Services\UrbanGoodz;

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

class UrbanGoodzAIConciergeService
{
    private UrbanGoodzAIService $ai;

    public function __construct(UrbanGoodzAIService $ai)
    {
        $this->ai = $ai;
    }

    public function processQuery(string $queryText, ?int $customerId = null, string $source = 'customer_api'): UrbanGoodzAIConversation
    {
        $customerId = $customerId ?? auth('api')->id() ?? auth('customer')->id();

        $context = $this->buildCustomerContext($customerId);
        $systemPrompt = $this->buildSystemPrompt($context);

        if ($this->ai->isConfigured()) {
            $result = $this->processWithAI($queryText, $customerId, $systemPrompt, $context, $source);
        } else {
            $result = $this->processWithKeywords($queryText, $customerId, $source);
        }

        return $result;
    }

    private function processWithAI(string $queryText, ?int $customerId, string $systemPrompt, array $context, string $source): UrbanGoodzAIConversation
    {
        $possibleIntents = UrbanGoodzAIIntent::where('is_active', true)
            ->get()
            ->map(fn($i) => ['slug' => $i->slug, 'description' => $i->description ?? $i->name])
            ->toArray();

        $classification = $this->ai->classifyIntent($queryText, $possibleIntents);
        $intentSlug = $classification['intent'] ?? 'unknown';
        $confidence = $classification['confidence'] ?? 0;

        $intent = UrbanGoodzAIIntent::where('slug', $intentSlug)->first();
        $entities = $classification['entities'] ?? [];

        $enrichedContext = array_merge($context, [
            'detected_intent' => $intentSlug,
            'entities' => $entities,
        ]);

        $responseText = $this->ai->chat($systemPrompt, $queryText, $enrichedContext);

        $needsEscalation = $confidence < 0.5 || str_contains(strtolower($responseText), 'escalate') || str_contains(strtolower($responseText), 'human agent');
        $status = $needsEscalation ? 'pending' : 'resolved';

        return UrbanGoodzAIConversation::create([
            'customer_id' => $customerId,
            'query_text' => $queryText,
            'detected_intent_id' => $intent?->id,
            'confidence_score' => round($confidence * 100, 2),
            'response_text' => $responseText,
            'status' => $status,
            'source' => $source,
        ]);
    }

    private function processWithKeywords(string $queryText, ?int $customerId, string $source): UrbanGoodzAIConversation
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

        $responseText = $bestIntent?->response_template
            ?? $this->getContextualResponse($queryLower, $customerId);

        return UrbanGoodzAIConversation::create([
            'customer_id' => $customerId,
            'query_text' => $queryText,
            'detected_intent_id' => $bestIntent?->id,
            'confidence_score' => $bestScore > 0 ? $bestScore : null,
            'response_text' => $responseText,
            'status' => $bestIntent ? 'resolved' : 'pending',
            'source' => $source,
            'metadata' => [
                'matched_intent' => $bestIntent?->intent_name ?? null,
                'query_length' => strlen($queryText),
                'source' => $source,
            ],
        ]);
    }

    private function buildSystemPrompt(array $context): string
    {
        $customerInfo = $context['customer'] ?? null;
        $recentOrders = $context['recent_orders'] ?? [];
        $recentDeliveries = $context['recent_deliveries'] ?? [];

        return "You are Urban Goodz AI Assistant — a helpful, professional customer service representative for Urban Goodz, a delivery and logistics platform.

Your role:
- Help customers with orders, deliveries, payments, account questions, and platform features
- Look up real data to provide personalized answers (order status, tracking, payment history)
- Take actions when possible: initiate refunds, schedule pickups, update information
- Escalate to a human agent when you cannot resolve the issue, when the customer requests it, or when the issue involves legal/safety matters

Platform capabilities you can reference:
- Order Anywhere: Request items from any store for delivery
- Fashion Fit: Connect with tailors and stylists
- Community Marketplace: Buy/sell within the community
- Earn Money: Referral and affiliate programs
- Book Anything: Schedule any service
- Creator Commerce: Influencer/creator partnerships
- Medical Courier: Prescription and medical deliveries
- Logistics: Freight and load management
- Load Board: Browse and accept delivery loads

Rules:
- Be warm, professional, and solution-oriented
- Always provide specific details when you have them (order numbers, dates, amounts)
- Never make up data — only use what's provided in the context
- If you cannot find specific data, say so honestly and offer alternatives
- Keep responses concise but complete
- Use the customer's name when available

Current customer context:
- Customer ID: {$context['customer_id']}
- Customer Name: " . ($customerInfo['name'] ?? 'Valued Customer') . "
- Account since: " . ($customerInfo['created_at'] ?? 'Unknown') . "
- Total orders: {$context['total_orders']}
- Total spent: $" . number_format($context['total_spent'] ?? 0, 2) . "
- Active deliveries: {$context['active_deliveries']}";
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
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? null,
                    'created_at' => $customer->created_at instanceof \Carbon\Carbon ? $customer->created_at->format('M Y') : ($customer->created_at ?? 'Unknown'),
                ];

                $context['total_orders'] = Order::where('user_id', $customerId)->count();
                $context['total_spent'] = (float) Order::where('user_id', $customerId)->sum('order_amount');

                $context['recent_orders'] = Order::where('user_id', $customerId)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'order_status', 'order_amount', 'created_at', 'delivery_address'])
                    ->map(fn($o) => [
                        'id' => $o->id,
                        'status' => $o->order_status,
                        'amount' => number_format($o->order_amount, 2),
                        'date' => $o->created_at instanceof \Carbon\Carbon ? $o->created_at->format('M d, Y') : ($o->created_at ?? 'Unknown'),
                        'address' => $o->delivery_address,
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
        // Load board queries
        if (str_contains($queryLower, 'load') && (str_contains($queryLower, 'board') || str_contains($queryLower, 'available') || str_contains($queryLower, 'status'))) {
            return $this->getLoadBoardStatusResponse();
        }

        if (str_contains($queryLower, 'track') && (str_contains($queryLower, 'package') || str_contains($queryLower, 'delivery') || str_contains($queryLower, 'shipment'))) {
            return $this->getTrackingResponse($queryLower);
        }

        if (str_contains($queryLower, 'driver') && (str_contains($queryLower, 'assign') || str_contains($queryLower, 'available') || str_contains($queryLower, 'status'))) {
            return $this->getDriverStatusResponse();
        }

        if (str_contains($queryLower, 'price') || str_contains($queryLower, 'rate') || str_contains($queryLower, 'cost') || str_contains($queryLower, 'quote')) {
            return $this->getPricingResponse();
        }

        if (str_contains($queryLower, 'invoice') || str_contains($queryLower, 'payment') || str_contains($queryLower, 'billing')) {
            return $this->getPaymentResponse();
        }

        if (str_contains($queryLower, 'route') && (str_contains($queryLower, 'dedicated') || str_contains($queryLower, 'schedule') || str_contains($queryLower, 'plan'))) {
            return $this->getRouteResponse();
        }

        if (str_contains($queryLower, 'medical') || str_contains($queryLower, 'courier') || str_contains($queryLower, 'healthcare')) {
            return $this->getMedicalCourierResponse();
        }

        if (str_contains($queryLower, 'business') || str_contains($queryLower, 'client') || str_contains($queryLower, 'enterprise')) {
            return $this->getBusinessClientResponse();
        }

        if (str_contains($queryLower, 'help') || str_contains($queryLower, 'support') || str_contains($queryLower, 'contact')) {
            return "I can help you with:\n"
                . "- Load board status and available loads\n"
                . "- Package tracking and delivery status\n"
                . "- Driver availability and assignments\n"
                . "- Pricing quotes and rate information\n"
                . "- Invoice and payment inquiries\n"
                . "- Dedicated route scheduling\n"
                . "- Medical courier services\n"
                . "- Business client account management\n\n"
                . "Please describe what you need, or contact support at support@urbangoodz.com for urgent matters.";
        }

        return "Thanks for reaching out to Urban Goodz! I can help with load board inquiries, package tracking, driver assignments, pricing, payments, and more. "
            . "Please describe what you need and I'll route your request to the right team. For urgent matters, contact support@urbangoodz.com.";
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
