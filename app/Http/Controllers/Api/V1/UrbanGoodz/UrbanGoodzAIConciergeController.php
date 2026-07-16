<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\UrbanGoodzAIConciergeService;
use Illuminate\Http\Request;

class UrbanGoodzAIConciergeController extends Controller
{
    public function query(Request $request, UrbanGoodzAIConciergeService $concierge)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
        ]);

        $customerId = $request->user()?->id;

        $conversation = $concierge->processQuery(
            queryText: $data['query'],
            customerId: $customerId,
            source: 'customer_api',
        );

        $intent = $conversation->detectedIntent;
        $suggestedAction = null;
        $suggestedRoute = null;

        if ($intent) {
            $routeMap = [
                'order_anywhere' => ['label' => 'Open Order Anywhere', 'route' => 'order-anywhere'],
                'fashion_fit' => ['label' => 'Open Fashion Fit', 'route' => 'fashion-measurements'],
                'logistics_freight' => ['label' => 'View Load Board', 'route' => 'load-board'],
                'medical_courier' => ['label' => 'View Medical Courier', 'route' => 'medical-courier'],
                'creator_commerce' => ['label' => 'Explore Creators', 'route' => 'creator-commerce'],
                'book_anything' => ['label' => 'Book a Service', 'route' => 'book-services'],
                'events' => ['label' => 'Browse Events', 'route' => 'events-creators'],
                'account_support' => ['label' => 'View Support Options', 'route' => 'support'],
            ];

            $mapped = $routeMap[$intent->slug] ?? null;
            if ($mapped) {
                $suggestedAction = $mapped['label'];
                $suggestedRoute = $mapped['route'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'query' => $conversation->query_text,
                'response' => $conversation->response_text,
                'confidence_score' => $conversation->confidence_score,
                'detected_intent' => $intent?->name,
                'status' => $conversation->status,
                'suggested_action' => $suggestedAction,
                'suggested_route' => $suggestedRoute,
                'created_at' => $conversation->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $customerId = $request->user()?->id;

        $conversations = \App\Models\UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->with('detectedIntent')
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $conversations->map(fn($c) => [
                'id' => $c->id,
                'query' => $c->query_text,
                'response' => $c->response_text,
                'intent' => $c->detectedIntent?->name,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }
}
