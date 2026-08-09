<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\AI\DigitalHuman\DigitalHumanStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DigitalHumanStateController extends Controller
{
    public function __construct(
        private readonly DigitalHumanStateService $digitalHumanStateService
    ) {}

    /**
     * Compute and return current digital human avatar state, mood, lighting, and viseme playback timeline.
     */
    public function getState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona' => ['sometimes', 'string', 'in:concierge,chief_of_staff,monique,skylar,ebony'],
            'text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'domain' => ['sometimes', 'string', 'max:100'],
            'event_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'intent' => ['sometimes', 'nullable', 'string', 'max:100'],
            'has_error' => ['sometimes', 'boolean'],
        ]);

        $rawPersona = strtolower($validated['persona'] ?? 'concierge');
        $personaKey = match ($rawPersona) {
            'chief_of_staff', 'skylar' => 'chief_of_staff',
            'concierge', 'monique', 'ebony' => 'concierge',
            default => 'concierge',
        };

        $payload = $this->digitalHumanStateService->buildStatePayload(
            personaKey: $personaKey,
            text: $validated['text'] ?? '',
            domain: $validated['domain'] ?? 'general',
            eventType: $validated['event_type'] ?? null,
            intent: $validated['intent'] ?? null,
            hasError: (bool) ($validated['has_error'] ?? false)
        );

        return response()->json($payload, 200);
    }
}
