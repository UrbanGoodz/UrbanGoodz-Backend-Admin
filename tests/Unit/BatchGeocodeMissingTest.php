<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\Routing\Services\AddressGeocoder;
use App\Services\UrbanGoodz\Routing\Services\BatchToRouteService;
use PHPUnit\Framework\TestCase;

class BatchGeocodeMissingTest extends TestCase
{
    private function pkg(?float $lat, ?string $address): object
    {
        return new class($lat, $address) {
            public $dropoff_lat; public $dropoff_lng; public $dropoff_address; public $dropoff_city = 'Houston';
            public $dropoff_state = 'TX'; public $dropoff_zip = null; public $pickup_lat = 29.76; public $pickup_lng = -95.37;
            public $pickup_address = 'Depot'; public $geocoding_status = 'pending'; public $geocoding_result = null;
            public function __construct($lat, $address) { $this->dropoff_lat = $lat; $this->dropoff_lng = $lat === null ? null : -95.0; $this->dropoff_address = $address; }
            public function forceFill(array $a) { foreach ($a as $k => $v) { $this->$k = $v; } return $this; }
            public function save() { return true; }
        };
    }

    private function runGeocode(array $packages, AddressGeocoder $geo): void
    {
        $svc = new BatchToRouteService($geo);
        $m = new \ReflectionMethod($svc, 'geocodeMissing');
        $m->setAccessible(true);
        $m->invoke($svc, collect($packages), []);
    }

    public function test_missing_coordinates_are_filled_and_failures_recorded_not_guessed(): void
    {
        $geo = new class extends AddressGeocoder {
            public ?string $why = null;
            public function __construct() {}
            public function isConfigured(): bool { return true; }
            public function lastFailureReason(): ?string { return $this->why; }
            public function geocode(string $address, ?array $anchor = null): ?array
            {
                if (str_contains($address, '901 Bagby')) { return ['lat' => 29.7589, 'lng' => -95.3677, 'label' => '901 Bagby St', 'confidence' => 1.0]; }
                $this->why = 'no_match';
                return null;
            }
        };
        $found = $this->pkg(null, '901 Bagby St');
        $missed = $this->pkg(null, 'Nowhere Lane 99999');
        $already = $this->pkg(29.8, '1 Main St');
        $noAddr = $this->pkg(null, null);

        $this->runGeocode([$found, $missed, $already, $noAddr], $geo);

        $this->assertSame('geocoded', $found->geocoding_status);
        $this->assertEqualsWithDelta(29.7589, $found->dropoff_lat, 0.0001);
        $this->assertSame('failed', $missed->geocoding_status);
        $this->assertSame('no_match', $missed->geocoding_result['reason']);
        $this->assertNull($missed->dropoff_lat);
        $this->assertSame('pending', $already->geocoding_status, 'packages that already have coordinates are left alone');
        $this->assertSame('pending', $noAddr->geocoding_status);
    }

    public function test_it_does_nothing_when_the_geocoder_is_not_configured(): void
    {
        $geo = new class extends AddressGeocoder {
            public function __construct() {}
            public function isConfigured(): bool { return false; }
        };
        $p = $this->pkg(null, '901 Bagby St');
        $this->runGeocode([$p], $geo);
        $this->assertSame('pending', $p->geocoding_status);
    }
}
