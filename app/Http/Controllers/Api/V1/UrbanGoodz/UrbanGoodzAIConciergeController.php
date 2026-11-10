<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\UrbanGoodzAIConciergeService;
use App\Services\UrbanGoodz\UrbanGoodzTavusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UrbanGoodzAIConciergeController extends Controller
{
    public function query(Request $request, UrbanGoodzAIConciergeService $concierge)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            // The session id is the only key to a guest's chat history, so it must be hard to
            // guess: a short or patterned id let one guest read another's conversation.
            'session_id' => ['nullable', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        // This route is open to guests, so there is no auth middleware to resolve
        // the caller for us. Ask the api guard directly: it returns the user when a
        // valid bearer token is present and null otherwise, which is exactly the
        // optional behaviour we want -- a signed-in customer keeps personalisation,
        // a guest is simply recorded with a null customer_id.
        $customerId = auth('api')->user()?->id ?? $request->user()?->id;

        $conversation = $concierge->processQuery(
            queryText: $data['query'],
            customerId: $customerId,
            source: $customerId ? 'customer_api' : 'customer_guest',
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

    /** A live room is billed per minute and nobody needs two at once. */
    private const VIDEO_SESSION_TTL_MINUTES = 30;

    public function startVideoAvatar(Request $request, UrbanGoodzTavusService $tavus)
    {
        $user = $request->user();

        // Resume instead of opening a second billed room (double tap, app restart).
        $existing = Cache::get("tavus:active:{$user->id}");
        if (is_array($existing) && ! empty($existing['conversation_id'])) {
            return response()->json([
                'success' => true,
                'resumed' => true,
                'data' => [
                    'conversation_id' => $existing['conversation_id'],
                    'conversation_url' => $existing['conversation_url'],
                ],
                'error_code' => null,
            ]);
        }

        $name = $user->f_name ? "{$user->f_name} {$user->l_name}" : "Customer {$user->id}";

        $result = $tavus->startConversation("Skylar with {$name}");

        if ($result['success']) {
            $ttl = now()->addMinutes(self::VIDEO_SESSION_TTL_MINUTES);
            Cache::put("tavus:active:{$user->id}", [
                'conversation_id' => $result['conversation_id'],
                'conversation_url' => $result['conversation_url'],
            ], $ttl);
            // Remembered so only the person who started a room can end it.
            Cache::put("tavus:owner:{$result['conversation_id']}", $user->id, $ttl);
        }

        return response()->json([
            'success' => $result['success'],
            'data' => [
                'conversation_id' => $result['conversation_id'],
                'conversation_url' => $result['conversation_url'],
            ],
            'error_code' => $result['error_code'],
        ], $result['success'] ? 200 : 503);
    }

    public function endVideoAvatar(Request $request, string $conversationId, UrbanGoodzTavusService $tavus)
    {
        // Any signed-in customer could previously end any room by id.
        if ((int) Cache::get("tavus:owner:{$conversationId}") !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $ended = $tavus->endConversation($conversationId);

        Cache::forget("tavus:owner:{$conversationId}");
        Cache::forget("tavus:active:{$request->user()->id}");

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
