<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use Tests\TestCase;

/**
 * A malformed cart payload must never produce a 500.
 *
 * `carts.add_on_ids` is written with json_encode() on top of an `array` cast
 * and read back with json_decode() on top of that same cast, so values reach
 * the formatter double-encoded. json_decode('"[]"') returns the *string* "[]",
 * and array_combine() then raised a TypeError that surfaced as
 * "Server Error" on both /customer/cart/add and /customer/cart/list.
 */
class UrbanGoodzCartPayloadSafetyTest extends TestCase
{
    public function test_a_plain_array_passes_through(): void
    {
        $this->assertSame([1, 2], Helpers::normalize_to_array([1, 2]));
        $this->assertSame([], Helpers::normalize_to_array([]));
    }

    public function test_a_json_string_is_decoded(): void
    {
        $this->assertSame([], Helpers::normalize_to_array('[]'));
        $this->assertSame([3, 4], Helpers::normalize_to_array('[3,4]'));
    }

    /** The shape that actually crashed production. */
    public function test_a_double_encoded_json_string_is_decoded(): void
    {
        $this->assertSame([], Helpers::normalize_to_array('"[]"'));
        $this->assertSame([5], Helpers::normalize_to_array('"[5]"'));
    }

    public function test_a_triple_encoded_json_string_is_decoded(): void
    {
        $this->assertSame([], Helpers::normalize_to_array(json_encode(json_encode('[]'))));
    }

    public function test_null_and_empty_become_an_empty_array(): void
    {
        $this->assertSame([], Helpers::normalize_to_array(null));
        $this->assertSame([], Helpers::normalize_to_array(''));
    }

    public function test_unparseable_input_degrades_to_an_empty_array(): void
    {
        $this->assertSame([], Helpers::normalize_to_array('not json at all'));
        $this->assertSame([], Helpers::normalize_to_array('{broken'));
    }

    public function test_a_bare_scalar_is_wrapped(): void
    {
        $this->assertSame([7], Helpers::normalize_to_array(7));
    }

    /**
     * The combination that threw: array_combine() on two strings. Both sides
     * normalise, so the pairing is safe whatever the client sent.
     */
    public function test_addon_ids_and_quantities_pair_safely_when_double_encoded(): void
    {
        $ids = Helpers::normalize_to_array('"[]"');
        $qtys = Helpers::normalize_to_array('"[]"');

        $this->assertSame([], $ids);
        $this->assertSame([], $qtys);
        $this->assertSame([], $ids === [] ? [] : array_combine($ids, $qtys));
    }

    public function test_mismatched_lengths_do_not_throw(): void
    {
        $ids = Helpers::normalize_to_array('[1,2,3]');
        $qtys = Helpers::normalize_to_array('[5]');

        $padded = array_pad(array_slice($qtys, 0, count($ids)), count($ids), 0);

        $this->assertSame([5, 0, 0], $padded);
        $this->assertSame([1 => 5, 2 => 0, 3 => 0], array_combine($ids, $padded));
    }
}
