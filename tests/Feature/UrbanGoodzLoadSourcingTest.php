<?php

namespace Tests\Feature;

use App\Models\ExternalLoad;
use App\Models\LoadDuplicate;
use App\Models\LoadEmailIngestion;
use App\Models\LoadImport;
use App\Models\LoadPartnerReferral;
use App\Models\LoadRecommendation;
use App\Models\LoadSource;
use App\Models\LoadSourceError;
use App\Models\LoadSourceSyncRun;
use App\Models\LoadSourcingSetting;
use App\Services\UrbanGoodz\LoadSource\DirectFreightLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\EmailLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\LoadNormalizer;
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use App\Services\UrbanGoodz\LoadSource\ManualLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TruckSmarterLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TruckerPathLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TruckstopLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TrulosLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TbLoadLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\UrbanGoodzInternalLoadSourceAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UrbanGoodzLoadSourcingTest extends TestCase
{
    use DatabaseTransactions;

    private LoadSourcingService $service;
    private LoadNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LoadSourcingService();
        $this->normalizer = new LoadNormalizer();
    }

    public function test_adapter_interface_contract_compliance(): void
    {
        $internalAdapter = new UrbanGoodzInternalLoadSourceAdapter();
        $this->assertInstanceOf(\App\Contracts\LoadSource\LoadSourceAdapter::class, $internalAdapter);
        $this->assertEquals('urban_goodz_internal', $internalAdapter->sourceKey());
        $this->assertTrue($internalAdapter->isConfigured());
        $this->assertTrue($internalAdapter->supportsBidding());
        $this->assertTrue($internalAdapter->supportsBooking());

        $emailAdapter = new EmailLoadSourceAdapter();
        $this->assertInstanceOf(\App\Contracts\LoadSource\LoadSourceAdapter::class, $emailAdapter);
        $this->assertEquals('email_inbox', $emailAdapter->sourceKey());
        $this->assertTrue($emailAdapter->isConfigured());

        $manualAdapter = new ManualLoadSourceAdapter();
        $this->assertEquals('manual_import', $manualAdapter->sourceKey());
        $this->assertTrue($manualAdapter->isConfigured());
    }

    public function test_unconfigured_adapter_fails_closed(): void
    {
        $trulos = new TrulosLoadSourceAdapter();
        $this->assertEquals('trulos', $trulos->sourceKey());
        $this->assertFalse($trulos->isConfigured());

        $result = $trulos->search(['origin_state' => 'TX']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not yet authorized', $result['error']);
        $this->assertEmpty($result['loads']);

        $loadResult = $trulos->getLoad('anything');
        $this->assertFalse($loadResult['success']);

        $refreshResult = $trulos->refreshStatus('anything');
        $this->assertFalse($refreshResult['success']);
    }

    public function test_all_disabled_providers_fail_closed(): void
    {
        $adapters = [
            new TbLoadLoadSourceAdapter(),
            new DirectFreightLoadSourceAdapter(),
            new TruckerPathLoadSourceAdapter(),
            new TruckSmarterLoadSourceAdapter(),
            new TrulosLoadSourceAdapter(),
        ];

        foreach ($adapters as $adapter) {
            $this->assertFalse($adapter->isConfigured(), "Adapter {$adapter->sourceKey()} should not be configured");
            $result = $adapter->search([]);
            $this->assertFalse($result['success'], "Adapter {$adapter->sourceKey()} should fail closed");
            $this->assertEmpty($result['loads'], "Adapter {$adapter->sourceKey()} should return no loads");
        }
    }

    public function test_normalizer_generates_correct_fingerprint(): void
    {
        $data1 = [
            'origin_city' => 'Houston',
            'origin_state' => 'TX',
            'destination_city' => 'Dallas',
            'destination_state' => 'TX',
            'equipment_type' => 'van',
            'weight' => '10000',
            'broker_name' => 'ABC Freight',
            'gross_rate' => '1500.00',
            'broker_reference' => 'REF-001',
            'pickup_start' => '2026-07-15 08:00:00',
        ];

        $data2 = [
            'origin_city' => 'Houston',
            'origin_state' => 'TX',
            'destination_city' => 'Dallas',
            'destination_state' => 'TX',
            'equipment_type' => 'van',
            'weight' => '10000',
            'broker_name' => 'ABC Freight',
            'gross_rate' => '1500.00',
            'broker_reference' => 'REF-001',
            'pickup_start' => '2026-07-15 08:00:00',
        ];

        $fp1 = ExternalLoad::generateFingerprint($data1);
        $fp2 = ExternalLoad::generateFingerprint($data2);
        $this->assertEquals($fp1, $fp2, 'Identical data should produce identical fingerprints');

        $data3 = array_merge($data2, ['gross_rate' => '1600.00']);
        $fp3 = ExternalLoad::generateFingerprint($data3);
        $this->assertNotEquals($fp1, $fp3, 'Different rate should produce different fingerprint');
    }

    public function test_normalizer_persists_loads_with_fingerprints(): void
    {
        $source = LoadSource::create([
            'source_key' => 'urban_goodz_internal',
            'name' => 'Test Internal',
            'type' => 'internal',
            'enabled' => true,
            'api_status' => 'connected',
        ]);

        $normalized = $this->normalizer->normalize([
            'external_id' => 'TEST-001',
            'origin_city' => 'Houston',
            'origin_state' => 'tx',
            'destination_city' => 'Dallas',
            'destination_state' => 'texas',
            'equipment_type' => 'Van',
            'gross_rate' => '1500',
            'weight' => '10000',
            'broker_name' => 'Test Broker',
        ], $source->id);

        $this->assertEquals('TX', $normalized['origin_state'], 'State should be normalized to uppercase 2-letter abbreviation');
        $this->assertEquals('van', $normalized['equipment_type'], 'Equipment should be normalized');

        $load = $this->normalizer->persistNormalized($normalized);
        $this->assertNotNull($load->id);
        $this->assertNotNull($load->fingerprint);
        $this->assertEquals('TEST-001', $load->external_id);
        $this->assertEquals('TX', $load->origin_state);
        $this->assertEquals('TX', $load->destination_state);
        $this->assertEquals('van', $load->equipment_type);
    }

    public function test_deduplication_detects_fingerprints(): void
    {
        $source = LoadSource::create([
            'source_key' => 'urban_goodz_internal',
            'name' => 'Test',
            'type' => 'internal',
            'enabled' => true,
            'api_status' => 'connected',
        ]);

        $loadData = [
            'external_id' => 'DUP-001',
            'origin_city' => 'Houston',
            'origin_state' => 'TX',
            'destination_city' => 'Dallas',
            'destination_state' => 'TX',
            'equipment_type' => 'van',
            'weight' => '10000',
            'broker_name' => 'Test',
            'gross_rate' => '1500',
        ];

        $load1 = $this->normalizer->persistNormalized($this->normalizer->normalize($loadData, $source->id));
        $this->assertFalse($load1->is_duplicate);

        $load2 = $this->normalizer->persistNormalized($this->normalizer->normalize(array_merge($loadData, ['external_id' => 'DUP-002']), $source->id));
        $this->assertTrue($load2->is_duplicate);
        $this->assertEquals($load1->id, $load2->deduplicated_to_id);

        $dup = LoadDuplicate::where('fingerprint', $load1->fingerprint)->first();
        $this->assertNotNull($dup);
        $this->assertEquals($load1->id, $dup->canonical_load_id);
    }

    public function test_profit_calculation(): void
    {
        $source = LoadSource::create([
            'source_key' => 'urban_goodz_internal',
            'name' => 'Test',
            'type' => 'internal',
            'enabled' => true,
            'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id,
            'external_id' => 'PROFIT-001',
            'fingerprint' => 'profit-test-001',
            'origin_city' => 'Houston',
            'origin_state' => 'TX',
            'destination_city' => 'Dallas',
            'destination_state' => 'TX',
            'gross_rate' => 2000.00,
            'rate_per_loaded_mile' => 2.50,
            'distance_loaded' => 800,
            'distance_deadhead' => 50,
            'equipment_type' => 'van',
            'status' => 'available',
            'compliance_status' => 'internal',
        ]);

        $driver = \App\Models\DeliveryMan::create([
            'f_name' => 'Test', 'l_name' => 'Driver', 'email' => 'test@test.com',
            'phone' => '555-0100', 'password' => 'test',
            'vehicle_type' => 'van', 'load_board_eligible' => true,
            'has_hazmat' => false, 'max_weight_lbs' => 45000,
        ]);

        $result = $this->service->scoreLoad($load, $driver, null, $this->service->getWeights());

        $this->assertGreaterThan(0, $result['estimated_driver_net']);
        $this->assertGreaterThan(0, $result['net_per_total_mile']);
        $this->assertGreaterThanOrEqual(0, $result['total_score']);
        $this->assertLessThanOrEqual(100, $result['total_score']);
        $this->assertContains($result['confidence_level'], ['low', 'medium', 'high']);
    }

    public function test_missing_data_reduces_confidence(): void
    {
        $source = LoadSource::create([
            'source_key' => 'urban_goodz_internal',
            'name' => 'Test',
            'type' => 'internal',
            'enabled' => true,
            'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id,
            'external_id' => 'MISSING-001',
            'fingerprint' => 'missing-test-001',
            'origin_city' => 'Houston',
            'origin_state' => 'TX',
            'destination_city' => 'Dallas',
            'destination_state' => 'TX',
            'status' => 'available',
            'compliance_status' => 'internal',
        ]);

        $driver = \App\Models\DeliveryMan::create([
            'f_name' => 'Test', 'l_name' => 'Driver', 'email' => 'test2@test.com',
            'phone' => '555-0102', 'password' => 'test',
            'vehicle_type' => 'van', 'load_board_eligible' => true,
        ]);

        $result = $this->service->scoreLoad($load, $driver, null, $this->service->getWeights());

        $this->assertEquals('low', $result['confidence_level']);
        $this->assertNotEmpty($result['reasons_penalized']);
    }

    public function test_equipment_match_scoring(): void
    {
        $source = LoadSource::create([
            'source_key' => 'test', 'name' => 'Test', 'type' => 'internal',
            'enabled' => true, 'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'EQ-001',
            'fingerprint' => 'eq-test-001', 'equipment_type' => 'van',
            'origin_state' => 'TX', 'destination_state' => 'TX',
            'status' => 'available', 'compliance_status' => 'internal',
        ]);

        $vanDriver = \App\Models\DeliveryMan::create([
            'f_name' => 'Van', 'l_name' => 'Driver', 'email' => 'van@test.com',
            'phone' => '555-1', 'password' => 'test', 'vehicle_type' => 'van',
            'load_board_eligible' => true,
        ]);

        $flatDriver = \App\Models\DeliveryMan::create([
            'f_name' => 'Flat', 'l_name' => 'Driver', 'email' => 'flat@test.com',
            'phone' => '555-2', 'password' => 'test', 'vehicle_type' => 'flatbed',
            'load_board_eligible' => true,
        ]);

        $vanResult = $this->service->scoreLoad($load, $vanDriver, null, $this->service->getWeights());
        $this->assertTrue($vanResult['equipment_match']);

        $flatResult = $this->service->scoreLoad($load, $flatDriver, null, $this->service->getWeights());
        $this->assertFalse($flatResult['equipment_match']);
        $this->assertNotEmpty(array_filter($flatResult['reasons_penalized'], fn($r) => str_contains($r, 'Equipment mismatch')));
    }

    public function test_driver_eligibility_rejects_non_eligible(): void
    {
        $source = LoadSource::create([
            'source_key' => 'test', 'name' => 'Test', 'type' => 'internal',
            'enabled' => true, 'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'ELIG-001',
            'fingerprint' => 'elig-test-001', 'equipment_type' => 'reefer',
            'weight' => 50000, 'origin_state' => 'TX', 'destination_state' => 'TX',
            'certifications_required' => ['hazmat'],
            'status' => 'available', 'compliance_status' => 'internal',
        ]);

        $ineligibleDriver = \App\Models\DeliveryMan::create([
            'f_name' => 'No', 'l_name' => 'Hazmat', 'email' => 'no@hazmat.com',
            'phone' => '555-3', 'password' => 'test', 'vehicle_type' => 'van',
            'load_board_eligible' => false, 'has_hazmat' => false,
            'max_weight_lbs' => 20000,
        ]);

        $this->assertFalse($this->service->isEligible($load, $ineligibleDriver));
    }

    public function test_driver_eligibility_accepts_eligible(): void
    {
        $source = LoadSource::create([
            'source_key' => 'test', 'name' => 'Test', 'type' => 'internal',
            'enabled' => true, 'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'ELIG-002',
            'fingerprint' => 'elig-test-002', 'equipment_type' => 'van',
            'weight' => 10000, 'origin_state' => 'TX', 'destination_state' => 'TX',
            'status' => 'available', 'compliance_status' => 'internal',
        ]);

        $eligibleDriver = \App\Models\DeliveryMan::create([
            'f_name' => 'Good', 'l_name' => 'Driver', 'email' => 'good@driver.com',
            'phone' => '555-4', 'password' => 'test', 'vehicle_type' => 'van',
            'load_board_eligible' => true, 'has_hazmat' => true,
            'max_weight_lbs' => 45000,
        ]);

        $this->assertTrue($this->service->isEligible($load, $eligibleDriver));
    }

    public function test_scheduling_conflict_rejects_expired_pickup(): void
    {
        $source = LoadSource::create([
            'source_key' => 'test', 'name' => 'Test', 'type' => 'internal',
            'enabled' => true, 'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'SCHED-001',
            'fingerprint' => 'sched-test-001', 'equipment_type' => 'van',
            'origin_state' => 'TX', 'destination_state' => 'TX',
            'pickup_end' => now()->subDay(),
            'status' => 'available', 'compliance_status' => 'internal',
        ]);

        $driver = \App\Models\DeliveryMan::create([
            'f_name' => 'Test', 'l_name' => 'Driver', 'email' => 'sched@test.com',
            'phone' => '555-5', 'password' => 'test', 'vehicle_type' => 'van',
            'load_board_eligible' => true,
        ]);

        $result = $this->service->scoreLoad($load, $driver, null, $this->service->getWeights());
        $this->assertLessThanOrEqual(20, $result['total_score']);
    }

    public function test_scoring_weights_are_configurable(): void
    {
        $defaultWeights = $this->service->getWeights();
        $this->assertEquals(25, $defaultWeights['profit']);
        $this->assertEquals(15, $defaultWeights['rate_per_mile']);
        $this->assertEquals(100, array_sum($defaultWeights));

        LoadSourcingSetting::set('scoring_weights', [
            'profit' => 30, 'rate_per_mile' => 20, 'deadhead' => 10,
            'equipment_match' => 10, 'schedule_feasibility' => 10,
            'broker_quality' => 10, 'return_load' => 5, 'driver_preference' => 5,
        ], 'json');

        $customWeights = $this->service->getWeights();
        $this->assertEquals(30, $customWeights['profit']);
        $this->assertEquals(20, $customWeights['rate_per_mile']);
    }

    public function test_email_extraction_confidence_scoring(): void
    {
        $emailService = new \App\Services\UrbanGoodz\LoadSource\LoadEmailIngestionService();

        $result = $emailService->ingestEmail([
            'source_email_id' => 'email-001',
            'from' => 'loads@broker.com',
            'subject' => 'Load from Houston, TX to Dallas, TX',
            'body' => 'Rate: $1500. Weight: 10000 lbs. Equipment: van. Broker: ABC Freight. Ref: REF-001',
        ]);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_email_duplicate_prevention(): void
    {
        $emailService = new \App\Services\UrbanGoodz\LoadSource\LoadEmailIngestionService();

        $result1 = $emailService->ingestEmail([
            'source_email_id' => 'email-dup-001',
            'subject' => 'Test Load',
        ]);

        $result2 = $emailService->ingestEmail([
            'source_email_id' => 'email-dup-001',
            'subject' => 'Test Load Again',
        ]);

        $this->assertTrue($result1['success']);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('Duplicate', $result2['error']);
    }

    public function test_manual_import_single_load(): void
    {
        $importService = new \App\Services\UrbanGoodz\LoadSource\LoadManualImportService();

        $result = $importService->importSingle([
            'external_id' => 'MANUAL-001',
            'origin_city' => 'Austin',
            'origin_state' => 'TX',
            'destination_city' => 'San Antonio',
            'destination_state' => 'TX',
            'equipment_type' => 'van',
            'gross_rate' => 500,
        ], 1, 'admin');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['external_load_id']);
    }

    public function test_external_handoff_recorded(): void
    {
        $source = LoadSource::create([
            'source_key' => 'dat', 'name' => 'DAT', 'type' => 'api',
            'enabled' => false, 'api_status' => 'awaiting_credentials',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'HANDOFF-001',
            'fingerprint' => 'handoff-test-001', 'source_url' => 'https://dat.com/load/123',
            'origin_state' => 'TX', 'destination_state' => 'NY',
            'status' => 'available', 'compliance_status' => 'authorized_partner',
        ]);

        $result = $this->service->recordExternalHandoff(
            $load->id, $source->id, 1, 'driver', 'open_source', 'https://dat.com/load/123'
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['referral']);

        $referral = LoadPartnerReferral::find($result['referral']->id);
        $this->assertEquals('open_source', $referral->referral_action);
        $this->assertFalse($referral->user_confirmed_booked);
    }

    public function test_booking_confirmation(): void
    {
        $source = LoadSource::create([
            'source_key' => 'dat', 'name' => 'DAT', 'type' => 'api',
            'enabled' => false, 'api_status' => 'awaiting_credentials',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'BOOK-001',
            'fingerprint' => 'book-test-001', 'source_url' => 'https://dat.com/load/456',
            'origin_state' => 'TX', 'destination_state' => 'NY',
            'status' => 'available', 'compliance_status' => 'authorized_partner',
        ]);

        $referral = LoadPartnerReferral::create([
            'external_load_id' => $load->id,
            'source_id' => $source->id,
            'referred_by' => 1,
            'referred_by_type' => 'driver',
            'referral_action' => 'open_source',
        ]);

        $result = $this->service->recordBookingConfirmation($referral->id, true, 'Booked at $2000');
        $this->assertTrue($result['success']);

        $referral->refresh();
        $this->assertTrue($referral->user_confirmed_booked);
        $this->assertEquals('booked', $referral->booking_status);

        $load->refresh();
        $this->assertEquals('booked', $load->status);
    }

    public function test_load_source_credential_encryption(): void
    {
        $source = LoadSource::create([
            'source_key' => 'dat', 'name' => 'DAT', 'type' => 'api',
            'enabled' => true, 'api_status' => 'configured',
        ]);

        $source->setCredential('api_key', 'secret-api-key-12345');

        $this->assertEquals('secret-api-key-12345', $source->getCredentialValue('api_key'));

        $cred = $source->credentials()->where('credential_key', 'api_key')->first();
        $this->assertNotEquals('secret-api-key-12345', $cred->encrypted_value);
        $this->assertNotEmpty($cred->encrypted_value);

        $apiResponse = $source->toArray();
        $this->assertArrayNotHasKey('credentials', $apiResponse);
    }

    public function test_source_status_labels(): void
    {
        $source = LoadSource::create([
            'source_key' => 'test', 'name' => 'Test', 'type' => 'api',
            'enabled' => true, 'api_status' => 'awaiting_credentials',
        ]);
        $this->assertEquals('Awaiting Partner API Access', $source->status_label);

        $source->update(['api_status' => 'connected']);
        $this->assertEquals('Live', $source->status_label);

        $source->update(['api_status' => 'error', 'last_error_message' => 'Timeout']);
        $this->assertStringContainsString('Timeout', $source->status_label);
    }

    public function test_scoring_components_are_all_present(): void
    {
        $source = LoadSource::create([
            'source_key' => 'test', 'name' => 'Test', 'type' => 'internal',
            'enabled' => true, 'api_status' => 'connected',
        ]);

        $load = ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'SCORE-001',
            'fingerprint' => 'score-test-001',
            'origin_city' => 'Houston', 'origin_state' => 'TX',
            'destination_city' => 'Dallas', 'destination_state' => 'TX',
            'equipment_type' => 'van', 'weight' => 10000,
            'gross_rate' => 2000, 'rate_per_loaded_mile' => 2.50,
            'distance_loaded' => 800, 'distance_deadhead' => 30,
            'broker_name' => 'Test Broker', 'broker_rating' => 4.5,
            'broker_credit_status' => 'good',
            'pickup_start' => now()->addDays(2),
            'status' => 'available', 'compliance_status' => 'internal',
        ]);

        $driver = \App\Models\DeliveryMan::create([
            'f_name' => 'Score', 'l_name' => 'Test', 'email' => 'score@test.com',
            'phone' => '555-6', 'password' => 'test', 'vehicle_type' => 'van',
            'load_board_eligible' => true,
        ]);

        $result = $this->service->scoreLoad($load, $driver, null, $this->service->getWeights());

        $this->assertArrayHasKey('total_score', $result);
        $this->assertArrayHasKey('confidence_level', $result);
        $this->assertArrayHasKey('estimated_driver_net', $result);
        $this->assertArrayHasKey('net_per_total_mile', $result);
        $this->assertArrayHasKey('equipment_match', $result);
        $this->assertArrayHasKey('certification_match', $result);
        $this->assertArrayHasKey('schedule_feasible', $result);
        $this->assertArrayHasKey('broker_risk', $result);
        $this->assertArrayHasKey('reasons_recommended', $result);
        $this->assertArrayHasKey('reasons_penalized', $result);
        $this->assertArrayHasKey('component_scores', $result);
    }

    public function test_load_source_settings_persist(): void
    {
        LoadSourcingSetting::set('platform_fee_percent', 15.0, 'decimal');
        $this->assertEquals(15.0, LoadSourcingSetting::get('platform_fee_percent'));

        LoadSourcingSetting::set('test_key', 'test_value');
        $this->assertEquals('test_value', LoadSourcingSetting::get('test_key'));

        LoadSourcingSetting::set('test_bool', true, 'boolean');
        $this->assertTrue(LoadSourcingSetting::get('test_bool'));
    }

    public function test_generate_recommendations_only_eligible(): void
    {
        $source = LoadSource::create([
            'source_key' => 'urban_goodz_internal', 'name' => 'Internal', 'type' => 'internal',
            'enabled' => true, 'api_status' => 'connected',
        ]);

        ExternalLoad::create([
            'source_id' => $source->id, 'external_id' => 'REC-001',
            'fingerprint' => 'rec-test-001',
            'origin_city' => 'Houston', 'origin_state' => 'TX',
            'destination_city' => 'Dallas', 'destination_state' => 'TX',
            'equipment_type' => 'van', 'weight' => 10000,
            'gross_rate' => 2000, 'distance_loaded' => 800, 'distance_deadhead' => 30,
            'rate_per_loaded_mile' => 2.50,
            'pickup_start' => now()->addDays(3),
            'status' => 'available', 'compliance_status' => 'internal',
        ]);

        $driver = \App\Models\DeliveryMan::create([
            'f_name' => 'Rec', 'l_name' => 'Test', 'email' => 'rec@test.com',
            'phone' => '555-7', 'password' => 'test', 'vehicle_type' => 'van',
            'load_board_eligible' => true, 'max_weight_lbs' => 45000,
        ]);

        $result = $this->service->generateRecommendations($driver->id);
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['count']);
    }
}
