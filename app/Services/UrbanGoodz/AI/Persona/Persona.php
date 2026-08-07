<?php

namespace App\Services\UrbanGoodz\AI\Persona;

/**
 * One Urban Goodz AI personality.
 *
 * Identity, voice, and presentation travel together so that a surface which
 * renders the persona and a prompt which speaks as the persona can never drift
 * apart.
 */
final class Persona
{
    /**
     * @param  list<string>  $voice       Voice rules, stated as instructions.
     * @param  list<string>  $boundaries  Persona-specific limits on top of the platform rules.
     * @param  list<array{user: string, reply: string}>  $examples
     * @param  array<string, mixed>  $presentation
     */
    public function __construct(
        public readonly string $key,
        public readonly string $displayName,
        public readonly string $roleTitle,
        public readonly string $audience,
        public readonly string $identity,
        public readonly array $voice,
        public readonly array $boundaries,
        public readonly array $examples,
        public readonly array $presentation,
        public readonly string $register = BrandRegister::FULL,
    ) {}

    /**
     * Compose the full system prompt for one call.
     *
     * Order is fixed and load-bearing:
     *   IDENTITY -> REGISTER -> VOICE -> PLATFORM RULES (override voice)
     *   -> TASK -> CONTEXT
     *
     * @param  array<string, mixed>  $context
     */
    public function systemPrompt(string $taskPrompt = '', array $context = []): string
    {
        $sections = [
            $this->identityBlock(),
            BrandRegister::block($this->register),
            $this->voiceBlock(),
            PlatformInvariants::block(),
        ];

        if (trim($taskPrompt) !== '') {
            $sections[] = "YOUR TASK RIGHT NOW\n\n" . trim($taskPrompt);
        }

        if ($context !== []) {
            $sections[] = "GROUNDING CONTEXT — the only facts you may state\n\n"
                . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return implode("\n\n---\n\n", $sections);
    }

    private function identityBlock(): string
    {
        return "WHO YOU ARE\n\nYou are {$this->displayName}, the {$this->roleTitle} for Urban Goodz. "
            . "You serve {$this->audience}.\n\n{$this->identity}";
    }

    private function voiceBlock(): string
    {
        $voice = collect($this->voice)->map(fn (string $rule): string => "- {$rule}")->implode("\n");

        $block = "HOW YOU SPEAK\n\n{$voice}";

        if ($this->boundaries !== []) {
            $limits = collect($this->boundaries)->map(fn (string $rule): string => "- {$rule}")->implode("\n");
            $block .= "\n\nWHERE YOU STOP\n\n{$limits}";
        }

        if ($this->examples !== []) {
            $shown = collect($this->examples)
                ->map(fn (array $example): string => "User: {$example['user']}\nYou: {$example['reply']}")
                ->implode("\n\n");
            $block .= "\n\nEXAMPLES OF YOUR VOICE — match this register, do not reuse the wording\n\n{$shown}";
        }

        return $block;
    }
}
