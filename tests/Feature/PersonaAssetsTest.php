<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The two characters must never be crossed again: Monique (Chief of Staff) is the composed executive,
 * Skylar (customer concierge) is the turned-up Southern star. Their image sets carry the persona's name.
 */
class PersonaAssetsTest extends TestCase
{
    private const FRAMES = ['neutral', 'speaking', 'thinking', 'listening', 'concerned', 'happy', 'laughing', 'confident', 'celebrating'];

    private function path(string $file): string
    {
        return public_path('assets/image/personas/'.$file);
    }

    public function test_both_personas_have_every_expression_frame_and_the_two_sets_are_different_people(): void
    {
        foreach (['monique', 'skylar'] as $who) {
            foreach (self::FRAMES as $frame) {
                $this->assertFileExists($this->path("{$who}_face_{$frame}.png"), "{$who} is missing the {$frame} frame");
            }
            foreach (['avatar', 'portrait', 'character_sheet'] as $kind) {
                $this->assertFileExists($this->path("{$who}_{$kind}.png"));
            }
        }
        $this->assertNotSame(
            md5_file($this->path('monique_face_neutral.png')),
            md5_file($this->path('skylar_face_neutral.png')),
            'Monique and Skylar must not share a face'
        );
    }

    public function test_admin_pages_show_monique_with_moniques_own_images(): void
    {
        $widget = file_get_contents(resource_path('views/admin-views/urban-goodz/ai-chief-of-staff/digital_human_widget.blade.php'));
        $dashboard = file_get_contents(resource_path('views/admin-views/dashboard.blade.php'));

        $this->assertStringContainsString('personas/monique_face_', $widget);
        $this->assertStringNotContainsString('personas/skylar_', $widget, 'the admin Monique widget must not show Skylar');
        $this->assertStringContainsString('personas/monique_avatar.png', $dashboard);
        $this->assertStringContainsString('ug-persona-animator.js', $widget, 'the widget must load the animator so she moves');
        $this->assertFileExists(public_path('assets/admin/js/ug-persona-animator.js'));
    }

    public function test_persona_config_points_each_persona_at_her_own_art(): void
    {
        $cos = config('urban_goodz_personas.personas.chief_of_staff.presentation');
        $concierge = config('urban_goodz_personas.personas.concierge.presentation');

        // The executive's art lives in the legacy-named skylar/ folder, the concierge's in monique/.
        $this->assertStringContainsString('digital_human/skylar/', $cos['avatar']);
        $this->assertStringContainsString('digital_human/monique/', $concierge['avatar']);
        $this->assertSame('Monique', $cos['display_name']);
        $this->assertSame('Skylar', $concierge['display_name']);
    }
}
