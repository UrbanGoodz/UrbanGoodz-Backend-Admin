<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\UrbanGoodzPackageScanRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Package scans are the verified operational events driver pay is calculated
 * from, so they have to survive the two things the field throws at them: a
 * double tap on the scanner, and an offline queue that flushes twice.
 */
class UrbanGoodzPackageScanRecorderTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzPackageScanRecorder $recorder;
    private UrbanGoodzBusinessClient $business;
    private UrbanGoodzDedicatedRoute $route;
    private UrbanGoodzDedicatedRoute $otherRoute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new UrbanGoodzPackageScanRecorder();

        $this->business = UrbanGoodzBusinessClient::create([
            'company_name' => 'Scan Test Co',
            'email' => 'scan-' . random_int(1000, 9999) . '@urbangoodz.test',
            'status' => 'approved',
        ]);

        $this->route = $this->makeRoute('Scan Route A');
        $this->otherRoute = $this->makeRoute('Scan Route B');
    }

    private function makeRoute(string $name): UrbanGoodzDedicatedRoute
    {
        return UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $this->business->id,
            'route_name' => $name,
            'route_label' => 'A',
            'total_packages' => 1,
            'estimated_miles' => 5.0,
            'estimated_duration' => 20,
            'scheduled_date' => now()->toDateString(),
            'route_type' => 'bulk_delivery',
            'status' => 'planned',
            'pickup_lat' => 29.76,
            'pickup_lng' => -95.36,
            'pickup_location' => 'Pickup Hub',
        ]);
    }

    private function package(array $overrides = []): UrbanGoodzRoutePackage
    {
        $package = new UrbanGoodzRoutePackage();
        $package->forceFill(array_merge([
            'dedicated_route_id' => $this->route->id,
            'business_client_id' => $this->business->id,
            'tracking_id' => 'TRK-SCAN-1',
            'barcode' => 'BAR-SCAN-1',
            'dropoff_name' => 'Alice',
            'dropoff_address' => '100 Main St',
            'status' => 'pending',
            'stop_order' => 1,
        ], $overrides));
        $package->save();

        return $package;
    }

    public function test_a_scan_records_the_full_operational_context(): void
    {
        $package = $this->package();

        $scan = $this->recorder->record($package, 'pickup', [
            'scanned_by' => 20,
            'scanner_type' => 'driver',
            'identifier_type' => UrbanGoodzPackageScanRecorder::IDENTIFIER_BARCODE,
            'identifier_value' => 'BAR-SCAN-1',
            'status_before' => 'pending',
            'status_after' => 'picked_up',
            'latitude' => 29.75,
            'longitude' => -95.36,
            'device_source' => 'moto-g-ZT42268MG6',
            'proof_reference' => 'proof/abc.jpg',
            'metadata' => ['app_version' => '3.9.1'],
        ]);

        $this->assertSame($package->id, $scan->package_id);
        $this->assertSame($this->route->id, $scan->route_id);
        $this->assertSame($this->business->id, $scan->business_client_id);
        $this->assertSame('pickup', $scan->scan_type);
        $this->assertSame('barcode', $scan->identifier_type);
        $this->assertSame('BAR-SCAN-1', $scan->identifier_value);
        $this->assertSame('pending', $scan->status_before);
        $this->assertSame('picked_up', $scan->status_after);
        $this->assertSame('moto-g-ZT42268MG6', $scan->device_source);
        $this->assertSame('proof/abc.jpg', $scan->proof_reference);
        $this->assertSame('3.9.1', $scan->metadata['app_version']);
        $this->assertNotNull($scan->occurred_at);
    }

    /** A double tap on the scanner must not create a second event. */
    public function test_a_duplicate_scan_returns_the_original_event(): void
    {
        $package = $this->package();
        $attributes = ['scanned_by' => 20, 'idempotency_key' => 'pkg:1:pickup:device-a'];

        $first = $this->recorder->record($package, 'pickup', $attributes);
        $second = $this->recorder->record($package, 'pickup', $attributes);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            UrbanGoodzPackageScan::where('idempotency_key', 'pkg:1:pickup:device-a')->count()
        );
    }

    /** An offline queue that flushes twice must synchronise once. */
    public function test_a_replayed_offline_scan_keeps_the_original_device_timestamp(): void
    {
        $package = $this->package();
        $occurredAt = now()->subHours(3)->startOfSecond();

        $first = $this->recorder->record($package, 'dropoff', [
            'scanned_by' => 20,
            'occurred_at' => $occurredAt,
            'idempotency_key' => 'pkg:1:dropoff:queued',
        ]);

        // The queue flushes again much later.
        $second = $this->recorder->record($package, 'dropoff', [
            'scanned_by' => 20,
            'occurred_at' => now(),
            'idempotency_key' => 'pkg:1:dropoff:queued',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            $occurredAt->toDateTimeString(),
            $second->occurred_at->toDateTimeString(),
            'the device timestamp of the original scan must survive the replay'
        );
    }

    public function test_scans_without_a_key_are_recorded_independently(): void
    {
        $package = $this->package();

        $this->recorder->record($package, 'custody_check', ['scanned_by' => 20]);
        $this->recorder->record($package, 'custody_check', ['scanned_by' => 20]);

        $this->assertSame(
            2,
            UrbanGoodzPackageScan::where('package_id', $package->id)
                ->where('scan_type', 'custody_check')->count(),
            'clients that send no key keep the previous behaviour'
        );
    }

    public function test_already_recorded_detects_a_replayed_key(): void
    {
        $package = $this->package();

        $this->assertFalse($this->recorder->alreadyRecorded('pkg:1:pickup:x'));
        $this->assertFalse($this->recorder->alreadyRecorded(null));

        $this->recorder->record($package, 'pickup', [
            'scanned_by' => 20,
            'idempotency_key' => 'pkg:1:pickup:x',
        ]);

        $this->assertTrue($this->recorder->alreadyRecorded('pkg:1:pickup:x'));
    }

    public function test_identifier_classification_covers_every_supported_input(): void
    {
        $this->assertSame(
            ['barcode', 'BAR-1'],
            $this->recorder->classifyIdentifier(['barcode' => 'BAR-1'])
        );
        $this->assertSame(
            ['qr', 'QR-1'],
            $this->recorder->classifyIdentifier(['qr_code' => 'QR-1'])
        );
        $this->assertSame(
            ['tracking_id', 'TRK-1'],
            $this->recorder->classifyIdentifier(['tracking_id' => 'TRK-1'])
        );
        $this->assertSame(
            ['package_id', '99'],
            $this->recorder->classifyIdentifier(['package_id' => 99])
        );
        $this->assertSame(
            ['manual', 'TYPED-1'],
            $this->recorder->classifyIdentifier(['manual_code' => 'TYPED-1'])
        );
        $this->assertSame([null, null], $this->recorder->classifyIdentifier([]));
    }

    public function test_a_scan_falls_back_to_the_packages_own_route_and_business(): void
    {
        $package = $this->package(['dedicated_route_id' => $this->otherRoute->id]);

        $scan = $this->recorder->record($package, 'pickup', ['scanned_by' => 20]);

        $this->assertSame($this->otherRoute->id, $scan->route_id);
        $this->assertSame($this->business->id, $scan->business_client_id);
    }

    /** Every event type in the spec must be expressible. */
    public function test_the_scan_type_vocabulary_covers_the_required_events(): void
    {
        foreach ([
            'pickup', 'dropoff', 'exception', 'return_scan',
            'business_package_scan', 'delivery_attempt', 'failed_delivery',
            'proof_uploaded', 'custody_check', 'return_to_sender', 'admin_override',
        ] as $type) {
            $this->assertContains($type, UrbanGoodzPackageScan::SCAN_TYPES);
        }
    }
}
