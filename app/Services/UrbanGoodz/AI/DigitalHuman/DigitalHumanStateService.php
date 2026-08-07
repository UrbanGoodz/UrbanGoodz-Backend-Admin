<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;

class DigitalHumanStateService
{
    public function __construct(
        private readonly PersonaRegistry $personaRegistry,
        private readonly EmotionEngine $emotionEngine,
        private readonly VoiceVisemeOrchestrator $voiceVisemeOrchestrator
    ) {}

    /**
     * Compute a complete production-ready Digital Human state payload for a client surface.
     *
     * @param string $personaKey 'concierge' or 'chief_of_staff'
     * @param string $text Spoken message text
     * @param string $domain App domain ('food', 'fashion', 'logistics', etc.)
     * @param string|null $eventType Specific application event
     * @param string|null $intent User intent
     * @param bool $hasError Failure/error state
     * @return array<string, mixed>
     */
    public function buildStatePayload(
        string $personaKey,
        string $text = '',
        string $domain = 'general',
        ?string $eventType = null,
        ?string $intent = null,
        bool $hasError = false
    ): array {
        $persona = $this->personaRegistry->get($personaKey);
        $presentation = $persona->presentation;
        $digitalHumanConfig = (array) ($presentation['digital_human'] ?? []);

        $reactiveState = $this->emotionEngine->evaluate(
            personaKey: $personaKey,
            domain: $domain,
            eventType: $eventType,
            intent: $intent,
            hasError: $hasError
        );

        $visemeTimeline = ! empty($text)
            ? $this->voiceVisemeOrchestrator->generateVisemeTimeline($text, $personaKey)
            : ['total_duration_ms' => 0, 'visemes' => []];

        return [
            'success' => true,
            'data' => [
                'persona' => [
                    'key' => $persona->key,
                    'display_name' => $persona->displayName,
                    'role_title' => $persona->roleTitle,
                    'accent_color' => $presentation['accent'] ?? '#ED9914',
                    'avatar_url' => $presentation['avatar'] ?? null,
                    'initials' => $presentation['initials'] ?? substr($persona->displayName, 0, 1),
                ],
                'digital_human' => [
                    'voice_id' => $digitalHumanConfig['voice_id'] ?? 'default_voice',
                    'rive_asset' => asset($digitalHumanConfig['rive_asset'] ?? ''),
                    'environment_key' => $reactiveState['environment'],
                    'environment_asset' => asset($digitalHumanConfig['environment_asset'] ?? ''),
                    'mood' => $reactiveState['mood'],
                    'expression' => $reactiveState['expression'],
                    'gesture' => $reactiveState['gesture'],
                    'posture' => $reactiveState['posture'],
                    'lighting_theme' => $reactiveState['lighting_theme'],
                    'voice_tone' => $reactiveState['voice_tone'],
                ],
                'playback' => [
                    'spoken_text' => $text,
                    'speech_duration_ms' => $visemeTimeline['total_duration_ms'],
                    'viseme_timeline' => $visemeTimeline['visemes'],
                    'is_streaming_enabled' => (bool) ($digitalHumanConfig['supports_voice_stream'] ?? true),
                ],
            ],
        ];
    }
}
