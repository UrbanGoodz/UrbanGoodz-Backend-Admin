<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\UrbanGoodzAIConciergeService;
use App\Services\UrbanGoodz\UrbanGoodzTavusService;
use Illuminate\Http\Request;

class UrbanGoodzAIConciergeController extends Controller
{
    public function query(Request $request, UrbanGoodzAIConciergeService $concierge)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        $customerId = $request->user()?->id;

        $conversation = $concierge->processQuery(
            queryText: $data['query'],
            customerId: $customerId,
            source: 'customer_api',
            sessionId: $data['session_id'] ?? null,
        );

        $intent = $conversation->detectedIntent;
        $suggestedAction = null;
        $suggestedRoute = null;

        if ($intent) {
            $routeMap = [
                'stranded' => ['label' => 'Get Help Now', 'route' => 'stranded'],
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

        $successful = $conversation->status !== 'failed';

        return response()->json([
            'success' => $successful,
            'data' => [
                'conversation_id' => $conversation->id,
                'query' => $conversation->query_text,
                'response' => $conversation->response_text,
                'confidence_score' => $conversation->confidence_score,
                'detected_intent' => $intent?->name,
                'status' => $conversation->status,
                'suggested_action' => $suggestedAction,
                'suggested_route' => $suggestedRoute,
                'discovered_options' => $conversation->metadata['discovered_options'] ?? [],
                'created_at' => $conversation->created_at?->toIso8601String(),
            ],
        ], $successful ? 200 : 503);
    }

    /**
     * Lets the client know, cheaply, whether it's worth showing the "Video
     * call Skylar" entry point at all -- never claims the feature is live
     * when it isn't configured.
     */
    public function videoAvatarStatus(UrbanGoodzTavusService $tavus)
    {
        return response()->json([
            'success' => true,
            'data' => ['available' => $tavus->isConfigured()],
        ]);
    }

    public function startVideoAvatar(Request $request, UrbanGoodzTavusService $tavus)
    {
        $user = $request->user();
        $name = $user?->f_name ? "{$user->f_name} {$user->l_name}" : "Customer {$user?->id}";

        $result = $tavus->startConversation("Skylar with {$name}");

        return response()->json([
            'success' => $result['success'],
            'data' => [
                'conversation_id' => $result['conversation_id'],
                'conversation_url' => $result['conversation_url'],
            ],
            'error_code' => $result['error_code'],
        ], $result['success'] ? 200 : 503);
    }

    public function endVideoAvatar(string $conversationId, UrbanGoodzTavusService $tavus)
    {
        $ended = $tavus->endConversation($conversationId);

        return response()->json(['success' => $ended]);
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
