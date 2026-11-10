<?php

namespace Tests\Unit;

use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use PHPUnit\Framework\TestCase;

/** Reference numbers sit behind unique indexes; two created in the same second must not collide. */
class ReferenceNumbersAreCollisionSafeTest extends TestCase
{
    public function test_ten_thousand_numbers_generated_back_to_back_are_unique(): void
    {
        foreach ([[UrbanGoodzPaymentLedger::class, 'nextLedgerNumber', '/^UGL-\d{14}-[0-9A-F]{8}$/'], [OrderAnywhereRequest::class, 'nextRequestNumber', '/^OA-\d{14}-[0-9A-F]{8}$/']] as [$class, $method, $format]) {
            $seen = [];
            for ($i = 0; $i < 10000; $i++) {
                $n = $class::$method();
                $this->assertMatchesRegularExpression($format, $n);
                $seen[$n] = true;
            }
            $this->assertCount(10000, $seen, "{$class}::{$method} produced a duplicate");
        }
    }
}
