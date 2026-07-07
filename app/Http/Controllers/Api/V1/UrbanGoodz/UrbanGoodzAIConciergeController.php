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

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'query' => $conversation->query_text,
                'response' => $conversation->response_text,
                'confidence_score' => $conversation->confidence_score,
                'detected_intent' => $conversation->detectedIntent?->name,
                'status' => $conversation->status,
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
