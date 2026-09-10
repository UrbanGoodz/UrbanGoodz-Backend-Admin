<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\PersonReferenceResolver;
use Tests\TestCase;

class PersonReferenceResolverTest extends TestCase
{
    private function resolver(): PersonReferenceResolver
    {
        return new PersonReferenceResolver();
    }

    // ── explicit pronoun usage ───────────────────────────────────────

    public function test_he_him_resolves_across_turns(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Marcus is my driver. He is picking up the order.');

        $this->assertEquals('Marcus', $r->resolve('he'));
        $this->assertEquals('he', $r->pronounsFor('Marcus')['subject']);

        // A later turn with only the pronoun still resolves.
        $later = $r->observeTurn('Where is he?');
        $this->assertEquals('Marcus', $later['he']);
    }

    public function test_she_her_resolves_across_turns(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Jennifer is my customer. She wants a refund.');

        $this->assertEquals('Jennifer', $r->resolve('she'));
        $this->assertEquals('her', $r->pronounsFor('Jennifer')['object']);
    }

    public function test_they_them_is_respected_not_treated_as_unknown(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Alex is my dispatcher. They are handling the route.');

        $this->assertEquals('Alex', $r->resolve('they'));
        $this->assertEquals('they', $r->pronounsFor('Alex')['subject']);
        $this->assertTrue($r->pronounsAreKnown('Alex'));
    }

    // ── the rule that matters most ───────────────────────────────────

    public function test_unknown_pronouns_default_to_they_them(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Taylor placed an order.');

        $this->assertFalse($r->pronounsAreKnown('Taylor'));
        $this->assertEquals('they', $r->pronounsFor('Taylor')['subject']);
        $this->assertEquals('them', $r->pronounsFor('Taylor')['object']);
    }

    public function test_pronouns_are_never_inferred_from_a_name(): void
    {
        $r = $this->resolver();
        // Names that are commonly gendered - the resolver must not care.
        $r->observeTurn('Jennifer and Marcus both placed orders.');

        $this->assertFalse($r->pronounsAreKnown('Jennifer'));
        $this->assertFalse($r->pronounsAreKnown('Marcus'));
        $this->assertEquals('they', $r->pronounsFor('Jennifer')['subject']);
        $this->assertEquals('they', $r->pronounsFor('Marcus')['subject']);
    }

    public function test_unknown_person_reports_unknown_source(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Sam is waiting.');

        $this->assertEquals(
            PersonReferenceResolver::PRONOUN_SOURCE_UNKNOWN,
            $r->pronounSource('Sam')
        );
    }

    // ── multiple people ──────────────────────────────────────────────

    public function test_two_people_with_distinct_pronouns_resolve_separately(): void
    {
        $r = $this->resolver();
        $out = $r->observeTurn("Jennifer's driver is Marcus. She called him this morning.");

        $this->assertEquals('Jennifer', $out['she']);
        $this->assertEquals('Marcus', $out['him']);
    }

    public function test_relationship_is_captured(): void
    {
        $r = $this->resolver();
        $r->observeTurn("Jennifer's driver is Marcus.");

        $context = collect($r->context())->keyBy('name');
        $this->assertStringContainsString('driver', $context['Marcus']['relationship'] ?? '');
    }

    public function test_repeated_reference_stays_with_the_same_person(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Marcus is my driver. He is late.');
        $r->observeTurn('Is he still on the route?');
        $third = $r->observeTurn('Call him please.');

        $this->assertEquals('Marcus', $third['him']);
    }

    // ── corrections ──────────────────────────────────────────────────

    public function test_explicit_declaration_overrides_earlier_usage(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Alex is my dispatcher. He is handling it.');
        $this->assertEquals('he', $r->pronounsFor('Alex')['subject']);

        // The user corrects themselves.
        $r->observeTurn('Actually Alex uses they/them.');

        $this->assertEquals('they', $r->pronounsFor('Alex')['subject']);
        $this->assertEquals(
            PersonReferenceResolver::PRONOUN_SOURCE_DECLARED,
            $r->pronounSource('Alex')
        );
    }

    public function test_declared_pronouns_beat_conversational_usage(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Jordan uses she/her.');
        $r->observeTurn('Jordan is my vendor.');

        $this->assertEquals('she', $r->pronounsFor('Jordan')['subject']);
        $this->assertEquals(
            PersonReferenceResolver::PRONOUN_SOURCE_DECLARED,
            $r->pronounSource('Jordan')
        );
    }

    public function test_profile_pronouns_are_used_when_supplied(): void
    {
        $r = $this->resolver();
        $r->registerFromRecord('u-1', 'Riley', 'customer', 'she');

        $this->assertTrue($r->pronounsAreKnown('Riley'));
        $this->assertEquals('she', $r->pronounsFor('Riley')['subject']);
        $this->assertEquals(
            PersonReferenceResolver::PRONOUN_SOURCE_PROFILE,
            $r->pronounSource('Riley')
        );
    }

    public function test_record_without_pronouns_leaves_them_unknown(): void
    {
        $r = $this->resolver();
        $r->registerFromRecord('u-2', 'Casey', 'driver');

        $this->assertFalse($r->pronounsAreKnown('Casey'));
        $this->assertEquals('they', $r->pronounsFor('Casey')['subject']);
    }

    // ── ambiguity must not be guessed ────────────────────────────────

    public function test_ambiguous_reference_returns_null_rather_than_guessing(): void
    {
        $r = $this->resolver();
        // Two people, both established with he/him, neither more recent.
        $r->observeTurn('Marcus uses he/him.');
        $r->observeTurn('Devin uses he/him.');

        // Same turn mentions both, so neither is more salient.
        $r->observeTurn('Marcus and Devin are both on shift.');

        $this->assertNull($r->resolve('he'), 'Must ask rather than pick one');
    }

    public function test_pronoun_with_no_established_person_returns_null(): void
    {
        $r = $this->resolver();
        $this->assertNull($r->resolve('she'));
    }

    // ── role references ──────────────────────────────────────────────

    public function test_role_reference_is_tracked_as_a_person(): void
    {
        $r = $this->resolver();
        $r->observeTurn('The driver is running late.');

        $names = array_column($r->context(), 'name');
        $this->assertContains('Driver', $names);
    }

    public function test_role_reference_pronouns_start_unknown(): void
    {
        $r = $this->resolver();
        $r->observeTurn('The customer wants a refund.');

        $this->assertFalse($r->pronounsAreKnown('Customer'));
        $this->assertEquals('they', $r->pronounsFor('Customer')['subject']);
    }

    // ── shared by both Digital Humans ────────────────────────────────

    public function test_context_is_shaped_for_grounding_either_persona(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Marcus is my driver. He is picking up the order.');
        $r->observeTurn('Taylor placed the order.');

        $context = collect($r->context())->keyBy('name');

        $this->assertEquals('he/him', $context['Marcus']['pronouns']);
        $this->assertEquals('unknown - use they/them', $context['Taylor']['pronouns']);
    }

    public function test_resolution_survives_many_turns(): void
    {
        $r = $this->resolver();
        $r->observeTurn('Marcus is my driver. He is picking up the order.');
        for ($i = 0; $i < 5; $i++) {
            $r->observeTurn('Any update?');
        }
        $out = $r->observeTurn('Has he arrived?');

        $this->assertEquals('Marcus', $out['he']);
    }
}
