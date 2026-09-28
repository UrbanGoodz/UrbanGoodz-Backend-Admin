<?php

namespace Tests\Unit;

use App\Console\Commands\UrbanGoodzPhase3Provision as Provision;
use PHPUnit\Framework\TestCase;

/**
 * Sourced stores are held by a UG-controlled vendor account until the business
 * is onboarded. Production already follows a convention for those accounts, so
 * these assertions pin it against a real row rather than inventing a new shape:
 *
 *   vendor 16 -> 'Urban' 'Goodz', phone 8000000035,
 *                houstonsaucepitfoodt35@urbangoodzdelivery.com,
 *                store "Houston Sauce Pit Food Truck" (sourced business id 35)
 *
 * The address is on a domain UG receives mail for, which is what makes
 * "issue credentials at onboarding" a normal password reset.
 */
class SourcedVendorIdentityTest extends TestCase
{
    public function test_the_email_matches_the_production_convention(): void
    {
        $this->assertSame(
            'houstonsaucepitfoodt35@urbangoodzdelivery.com',
            Provision::ugVendorEmail('Houston Sauce Pit Food Truck', 35)
        );
    }

    public function test_the_phone_matches_the_production_convention(): void
    {
        $this->assertSame('8000000035', Provision::ugVendorPhone(35));
    }

    public function test_punctuation_and_case_are_squashed_out_of_the_handle(): void
    {
        // "Ray's BBQ Shack!" must not leak an apostrophe or space into an address.
        $email = Provision::ugVendorEmail("Ray's BBQ Shack!", 7);

        $this->assertSame('raysbbqshack7@urbangoodzdelivery.com', $email);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+@urbangoodzdelivery\.com$/', $email);
    }

    public function test_a_long_name_is_truncated_but_the_id_still_makes_it_unique(): void
    {
        $long = 'The Extremely Long Neighbourhood Barbecue And Catering Company';

        $a = Provision::ugVendorEmail($long, 41);
        $b = Provision::ugVendorEmail($long, 42);

        $this->assertNotSame($a, $b, 'Two businesses sharing a 20-char prefix must not collide.');
        $this->assertStringEndsWith('41@urbangoodzdelivery.com', $a);
        $this->assertSame(20, strlen(explode('@', $a)[0]) - strlen('41'));
    }

    /**
     * Production already held 8000000023 from an earlier batch, whose ids
     * restart per import, so the first choice can be taken.
     */
    public function test_phone_candidates_start_with_the_convention_then_step_away(): void
    {
        $candidates = iterator_to_array(Provision::ugVendorPhoneCandidates(23));

        $this->assertSame('8000000023', $candidates[0]);
        $this->assertSame('8001000023', $candidates[1]);
        $this->assertSame(count($candidates), count(array_unique($candidates)));

        foreach ($candidates as $phone) {
            $this->assertSame(10, strlen($phone), "{$phone} left the 10-digit shape");
        }
    }

    /** Two different businesses must never be offered the same fallback. */
    public function test_candidate_bands_do_not_overlap_between_businesses(): void
    {
        $a = iterator_to_array(Provision::ugVendorPhoneCandidates(23));
        $b = iterator_to_array(Provision::ugVendorPhoneCandidates(24));

        $this->assertSame([], array_intersect($a, $b));
    }

    public function test_the_phone_stays_ten_digits_across_the_id_range(): void
    {
        foreach ([1, 35, 118, 999999999] as $id) {
            $this->assertSame(10, strlen(Provision::ugVendorPhone($id)), "id {$id}");
        }
    }
}
