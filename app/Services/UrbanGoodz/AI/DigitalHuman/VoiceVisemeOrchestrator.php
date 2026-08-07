<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

class VoiceVisemeOrchestrator
{
    /**
     * Generate timestamped viseme frames from text input for offline/real-time client lip-sync.
     *
     * @param string $text Spoken text string
     * @param string $personaKey Persona identifier
     * @param int $speechRateWpm Average words per minute (default 150)
     * @return array<string, mixed>
     */
    public function generateVisemeTimeline(string $text, string $personaKey, int $speechRateWpm = 150): array
    {
        $words = preg_split('/\s+/', trim($text));
        if (empty($words) || (count($words) === 1 && $words[0] === '')) {
            return [
                'total_duration_ms' => 0,
                'visemes' => [],
            ];
        }

        $msPerChar = (int) round((60000 / ($speechRateWpm * 5))); // Average 5 letters per word
        $currentTimeMs = 0;
        $visemes = [];

        // Initial silence
        $visemes[] = [
            'time_ms' => 0,
            'viseme' => 'sil',
            'intensity' => 0.0,
        ];

        foreach ($words as $word) {
            $cleanWord = strtolower(preg_replace('/[^a-zA-Z]/', '', $word));
            $chars = str_split($cleanWord);

            foreach ($chars as $char) {
                $viseme = $this->mapCharToViseme($char);
                $currentTimeMs += $msPerChar;

                $visemes[] = [
                    'time_ms' => $currentTimeMs,
                    'viseme' => $viseme,
                    'intensity' => min(1.0, 0.6 + (mt_rand(0, 4) / 10)),
                ];
            }

            // Word gap pause
            $currentTimeMs += 80;
            $visemes[] = [
                'time_ms' => $currentTimeMs,
                'viseme' => 'sil',
                'intensity' => 0.0,
            ];
        }

        return [
            'persona' => $personaKey,
            'speech_rate_wpm' => $speechRateWpm,
            'total_duration_ms' => $currentTimeMs,
            'visemes' => $visemes,
        ];
    }

    /**
     * Map a character to standard 6 viseme phoneme shape.
     */
    private function mapCharToViseme(string $char): string
    {
        return match ($char) {
            'a', 'h' => 'viseme_A',
            'e', 'i', 'y' => 'viseme_E',
            'o', 'w' => 'viseme_O',
            'u', 'q' => 'viseme_U',
            'm', 'b', 'p' => 'viseme_MBP',
            'f', 'v' => 'viseme_FV',
            'l', 'n', 'd', 't' => 'viseme_LNDT',
            's', 'z', 'c', 'k', 'g', 'j', 'x', 'r' => 'viseme_Neutral',
            default => 'viseme_Neutral',
        };
    }
}
