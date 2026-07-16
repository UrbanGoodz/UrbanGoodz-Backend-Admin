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

        $responseText = $bestIntent?->response_template ?? $this->defaultFallbackResponse();

        return UrbanGoodzAIConversation::create([
            'customer_id' => $customerId,
            'query_text' => $queryText,
            'detected_intent_id' => $bestIntent?->id,
            'confidence_score' => $bestScore > 0 ? $bestScore : null,
            'response_text' => $responseText,
            'status' => $bestIntent ? 'resolved' : 'pending',
            'source' => $source,
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
