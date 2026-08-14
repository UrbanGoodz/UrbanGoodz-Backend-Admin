<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\AI\DigitalHuman\DigitalHumanStateService;
use App\Services\UrbanGoodz\AI\DigitalHuman\ElevenLabsVoiceService;
use App\Services\UrbanGoodz\AI\DigitalHuman\VoiceVisemeOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DigitalHumanController extends Controller
{
    public function __construct(
        private readonly DigitalHumanStateService $digitalHumanStateService,
        private readonly VoiceVisemeOrchestrator $voiceVisemeOrchestrator,
        private readonly ElevenLabsVoiceService $elevenLabsVoiceService
    ) {}

    /**
     * Compute full Digital Human state payload for a client turn.
     */
    public function getState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona' => 'required|string|in:concierge,chief_of_staff',
            'text' => 'nullable|string',
            'domain' => 'nullable|string',
            'event_type' => 'nullable|string',
            'intent' => 'nullable|string',
            'has_error' => 'nullable|boolean',
        ]);

        $payload = $this->digitalHumanStateService->buildStatePayload(
            personaKey: $validated['persona'],
            text: $validated['text'] ?? '',
            domain: $validated['domain'] ?? 'general',
            eventType: $validated['event_type'] ?? null,
            intent: $validated['intent'] ?? null,
            hasError: (bool) ($validated['has_error'] ?? false)
        );

        return response()->json($payload);
    }

    /**
     * Generate phoneme viseme timeline for a given text snippet.
     */
    public function getVisemes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona' => 'required|string|in:concierge,chief_of_staff',
            'text' => 'required|string',
            'wpm' => 'nullable|integer|min:80|max:240',
        ]);

        $timeline = $this->voiceVisemeOrchestrator->generateVisemeTimeline(
            text: $validated['text'],
            personaKey: $validated['persona'],
            speechRateWpm: (int) ($validated['wpm'] ?? 150)
        );

        return response()->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }

    /**
     * Synthesize speech audio server-side via ElevenLabs. The API key never
     * reaches the client; this endpoint returns raw audio bytes only.
     */
    public function speak(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'persona' => 'required|string|in:concierge,chief_of_staff',
            'text' => 'required|string|max:2000',
        ]);

        $result = $this->elevenLabsVoiceService->synthesize(
            personaKey: $validated['persona'],
            text: $validated['text']
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error_code' => $result['error_code'],
                'message' => $result['message'],
            ], $result['error_code'] === 'not_configured' ? 503 : 502);
        }

        return response($result['audio'], 200)
            ->header('Content-Type', $result['mime']);
    }
}
