<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\AI\DigitalHuman\DigitalHumanStateService;
use App\Services\UrbanGoodz\AI\DigitalHuman\ElevenLabsVoiceService;
use App\Services\UrbanGoodz\AI\DigitalHuman\QwenVoiceService;
use App\Services\UrbanGoodz\AI\DigitalHuman\VoiceVisemeOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class DigitalHumanController extends Controller
{
    public function __construct(
        private readonly DigitalHumanStateService $digitalHumanStateService,
        private readonly VoiceVisemeOrchestrator $voiceVisemeOrchestrator,
        private readonly ElevenLabsVoiceService $elevenLabsVoiceService,
        private readonly QwenVoiceService $qwenVoiceService,
        private readonly \App\Services\UrbanGoodz\AI\DigitalHuman\CloudflareVoiceService $cloudflareVoiceService
    ) {}

    /**
     * Compute full Digital Human state payload for a client turn.
     */
    public function getState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona' => 'required|string|in:concierge,chief_of_staff',
            'text' => 'nullable|string|max:2000',
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
            'text' => 'required|string|max:2000',
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
     * Synthesize speech audio server-side. Routes to the configured voice
     * provider (Qwen3-TTS clone by default, ElevenLabs as fallback).
     */
    public function speak(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'persona' => 'required|string|in:concierge,chief_of_staff',
            'text' => 'required|string|max:2000',
        ]);

        // Everyone has a daily budget of synthesized characters, because every character is billed. Guests share ONE
        // platform-wide budget (the route is open on purpose and its throttle is per client, so a spread of clients could
        // otherwise run the provider bill without limit); each signed-in user has their own.
        $guest = ! auth('api')->check();
        $chars = mb_strlen($validated['text']);
        $budgetKey = $guest
            ? 'ug:guest-speech-chars:' . now()->toDateString()
            : 'ug:user-speech-chars:' . auth('api')->id() . ':' . now()->toDateString();
        $budget = $guest
            ? (int) config('urban_goodz_personas.digital_human_global.guest_daily_char_budget', 100000)
            : (int) config('urban_goodz_personas.digital_human_global.user_daily_char_budget', 60000);
        Cache::add($budgetKey, 0, now()->endOfDay());
        if ((int) Cache::increment($budgetKey, $chars) > $budget) {
            Cache::decrement($budgetKey, $chars);

            return response()->json([
                'success' => false,
                'error_code' => $guest ? 'guest_budget_exhausted' : 'user_budget_exhausted',
                'message' => $guest
                    ? 'Voice is unavailable right now. Sign in to keep using it.'
                    : 'You have used today\'s voice allowance. It resets tomorrow.',
            ], 429);
        }

        $primary = strtolower(trim((string) config('urban_goodz_personas.digital_human_global.voice_provider', 'qwen')));
        // Try the configured provider, then the other one: if the voice machine is down or a billing plan lapses, the
        // character still speaks. (UG_VOICE_FALLBACK=false turns this off.)
        $order = $primary === 'qwen' ? ['qwen', 'elevenlabs', 'cloudflare'] : ['elevenlabs', 'qwen', 'cloudflare'];
        if (! (bool) config('urban_goodz_personas.digital_human_global.voice_fallback', true)) {
            $order = [$order[0]];
        }

        $result = null;
        $firstFailure = null;
        foreach ($order as $name) {
            $attempt = match ($name) {
                'qwen' => $this->qwenVoiceService->synthesize(personaKey: $validated['persona'], text: $validated['text']),
                'cloudflare' => $this->cloudflareVoiceService->synthesize($validated['persona'], $validated['text']),
                default => $this->elevenLabsVoiceService->synthesize(personaKey: $validated['persona'], text: $validated['text']),
            };
            if ($attempt['success']) {
                $result = $attempt;
                break;
            }
            $firstFailure ??= $attempt;
        }
        $result ??= $firstFailure;

        if (! $result['success']) {
            // Nothing was synthesized, so nothing is spent from the budget.
            Cache::decrement($budgetKey, $chars);

            // 503 + Retry-After (not a bare 502): clients and Cloudflare treat it as "try again shortly", and the
            // app falls back to the device voice instead of showing a gateway error.
            $outage = in_array($result['error_code'], ['recently_down', 'provider_exception', 'provider_error', 'not_configured'], true);

            return response()->json([
                'success' => false,
                'error_code' => $result['error_code'],
                'message' => $outage ? 'Her voice is resting for a moment. You can still read her reply.' : $result['message'],
            ], 503)->header('Retry-After', '60');
        }

        return response($result['audio'], 200)
            ->header('Content-Type', $result['mime']);
    }
}
