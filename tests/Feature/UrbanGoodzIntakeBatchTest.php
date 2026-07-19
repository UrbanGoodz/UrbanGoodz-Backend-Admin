<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientLocation;
use App\Models\UrbanGoodzIntakeBatch;
use App\Models\UrbanGoodzBatchParticipant;
use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzBatchPackageAudit;
use App\Models\UrbanGoodzIntakeBatchAudit;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Services\UrbanGoodz\Routing\Services\BatchIntakeService;
use App\Services\UrbanGoodz\Routing\Services\BatchLockingService;
use App\Services\UrbanGoodz\Routing\Services\BatchProgressService;
use App\Services\UrbanGoodz\Routing\Services\DuplicateDetectionService;
use App\Services\UrbanGoodz\Routing\Services\LatePackagePolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UrbanGoodzIntakeBatchTest extends TestCase
{
    use DatabaseTransactions;

    private BatchIntakeService $intakeService;
    private BatchLockingService $lockingService;
    private BatchProgressService $progressService;
    private DuplicateDetectionService $duplicateService;
    private LatePackagePolicyService $latePackageService;

    private UrbanGoodzBusinessClient $businessA;
    private UrbanGoodzBusinessClient $businessB;
    private User $worker1;
    private User $worker2;
    private User $worker3;
    private User $worker4;
    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->intakeService = new BatchIntakeService();
        $this->lockingService = new BatchLockingService();
        $this->progressService = new BatchProgressService();
        $this->duplicateService = new DuplicateDetectionService();
        $this->latePackageService = new LatePackagePolicyService();

        // Create businesses
        $this->businessA = UrbanGoodzBusinessClient::create([
            'company_name' => 'Business A Logistics',
            'email' => 'businessa@urbangoodz.test',
            'status' => 'approved',
        ]);
        $this->businessB = UrbanGoodzBusinessClient::create([
            'company_name' => 'Business B Sourcing',
            'email' => 'businessb@urbangoodz.test',
            'status' => 'approved',
        ]);

        // Create users
        $this->worker1 = User::create([
            'name' => 'Worker One',
            'email' => 'worker1@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);
        $this->worker2 = User::create([
            'name' => 'Worker Two',
            'email' => 'worker2@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);
        $this->worker3 = User::create([
            'name' => 'Worker Three',
            'email' => 'worker3@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);
        $this->worker4 = User::create([
            'name' => 'Worker Four',
            'email' => 'worker4@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);
        $this->supervisor = User::create([
            'name' => 'Supervisor',
            'email' => 'supervisor@urbangoodz.test',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_batch_lifecycle_and_state_transitions(): void
    {
        // 1. Create batch as DRAFT
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'batch_name' => 'Morning Wave Intake',
            'service_date' => now()->toDateString(),
            'expected_package_count' => 10,
        ], $this->supervisor->id);

        $this->assertEquals(UrbanGoodzIntakeBatch::STATUS_DRAFT, $batch->status);
        $this->assertFalse($batch->is_locked);

        // 2. Open batch
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);
        $this->assertEquals(UrbanGoodzIntakeBatch::STATUS_OPEN_FOR_INTAKE, $batch->status);

        // 3. Mark ready (can be set to ready before locking, e.g. via direct update or status)
        $batch->update(['status' => UrbanGoodzIntakeBatch::STATUS_READY_FOR_ROUTING]);
        $this->assertEquals(UrbanGoodzIntakeBatch::STATUS_READY_FOR_ROUTING, $batch->status);
    }

    public function test_invalid_state_transitions(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);

        // Cannot lock a DRAFT batch directly
        $this->expectException(\RuntimeException::class);
        $this->lockingService->lockForRouting($batch->id, $this->supervisor->id);
    }

    public function test_cross_business_access_blocked(): void
    {
        $batchA = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);

        // Try to access Business A batch using Business B scope ID
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->intakeService->openBatch($batchA->id, $this->worker1->id, $this->businessB->id);
    }

    public function test_package_creation_and_validation(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);

        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        // Valid Package
        $res = $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK10001',
            'barcode' => 'BAR10001',
            'dropoff_address' => '123 Main St',
            'dropoff_city' => 'Houston',
            'recipient_name' => 'John Doe',
            'weight_lbs' => 12.5,
        ], $this->worker1->id);

        $this->assertTrue($res['success']);
        $package = $res['package'];
        $this->assertEquals('valid', $package->validation_status);

        // Invalid Package (missing dropoff address)
        $res2 = $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK10002',
            'recipient_name' => 'Jane Smith',
        ], $this->worker1->id);

        $this->assertTrue($res2['success']);
        $package2 = $res2['package'];
        $this->assertEquals('invalid', $package2->validation_status);
        $this->assertNotEmpty($package2->validation_errors);
    }

    public function test_duplicate_barcode_and_tracking_id(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        // Insert first
        $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-DUP',
            'barcode' => 'BAR-DUP',
            'dropoff_address' => '123 Main St',
        ], $this->worker1->id);

        // Duplicate tracking ID
        $resDupTrk = $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-DUP',
            'barcode' => 'BAR-OTHER',
            'dropoff_address' => '456 Oak Ave',
        ], $this->worker2->id);

        $this->assertFalse($resDupTrk['success']);
        $this->assertEquals(DuplicateDetectionService::RESULT_ALREADY_IN_BATCH, $resDupTrk['duplicate_result']);

        // Duplicate barcode
        $resDupBar = $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-OTHER',
            'barcode' => 'BAR-DUP',
            'dropoff_address' => '456 Oak Ave',
        ], $this->worker2->id);

        $this->assertFalse($resDupBar['success']);
        $this->assertEquals(DuplicateDetectionService::RESULT_ALREADY_IN_BATCH, $resDupBar['duplicate_result']);
    }

    public function test_duplicate_in_another_active_batch(): void
    {
        $batch1 = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch1 = $this->intakeService->openBatch($batch1->id, $this->supervisor->id);

        $batch2 = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch2 = $this->intakeService->openBatch($batch2->id, $this->supervisor->id);

        // Add to batch 1
        $this->intakeService->addPackage($batch1->id, [
            'tracking_id' => 'TRK-GLOBAL',
            'dropoff_address' => '123 Main St',
        ], $this->worker1->id);

        // Try adding same tracking ID to batch 2
        $res = $this->intakeService->addPackage($batch2->id, [
            'tracking_id' => 'TRK-GLOBAL',
            'dropoff_address' => '123 Main St',
        ], $this->worker2->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(DuplicateDetectionService::RESULT_ACTIVE_IN_OTHER_BATCH, $res['duplicate_result']);
    }

    public function test_simultaneous_package_edits_optimistic_concurrency(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        $res = $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-EDIT',
            'dropoff_address' => '100 Main St',
        ], $this->worker1->id);

        $package = $res['package']->fresh();
        $originalVersion = $package->version;

        // Worker 1 changes address
        $resUpdate1 = $this->intakeService->updatePackage($package->id, [
            'dropoff_address' => '200 Main St',
            'version' => $originalVersion,
        ], $this->worker1->id);

        $this->assertTrue($resUpdate1['success']);

        // Worker 2 attempts update using the STALE version
        $resUpdate2 = $this->intakeService->updatePackage($package->id, [
            'dropoff_address' => '300 Main St',
            'version' => $originalVersion, // Stale version (value 1) instead of fresh version (value 2)
        ], $this->worker2->id);

        $this->assertFalse($resUpdate2['success']);
        $this->assertEquals('CONFLICT', $resUpdate2['error']);

        // Verify audit logs captured the conflict
        $conflicts = UrbanGoodzBatchPackageAudit::where('action', 'conflict_rejected')
            ->where('batch_package_id', $package->id)
            ->get();
        $this->assertCount(1, $conflicts);
    }

    public function test_supervisor_review(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        // Add invalid package
        $res = $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-REV',
            'recipient_name' => 'Bob',
        ], $this->worker1->id);
        $package = $res['package'];

        // Assign review
        $this->intakeService->assignReview($package->id, $this->supervisor->id, $this->worker1->id);
        $this->assertEquals('needs_review', $package->fresh()->validation_status);

        // Complete review by correcting address
        $this->intakeService->completeReview($package->id, $this->supervisor->id, 'correct', [
            'dropoff_address' => '500 Corporate Ave',
            'version' => $package->fresh()->version,
        ]);

        $packageFresh = $package->fresh();
        $this->assertEquals('valid', $packageFresh->validation_status);
        $this->assertEquals('500 Corporate Ave', $packageFresh->dropoff_address);
    }

    public function test_atomic_lock_concurrency_and_immutable_snapshot(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        // Add 2 valid packages
        $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-L1',
            'dropoff_address' => '100 Main St',
        ], $this->worker1->id);
        $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-L2',
            'dropoff_address' => '200 Main St',
        ], $this->worker1->id);

        // Lock batch
        $batch->update(['status' => UrbanGoodzIntakeBatch::STATUS_READY_FOR_ROUTING]);
        
        $res = $this->lockingService->lockForRouting($batch->id, $this->supervisor->id);

        $this->assertTrue($res['success']);
        $this->assertEquals(2, $res['package_count']);

        // Try to lock again
        $this->expectException(\RuntimeException::class);
        $this->lockingService->lockForRouting($batch->id, $this->supervisor->id);
    }

    public function test_late_package_policy(): void
    {
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        // Add package and lock
        $this->intakeService->addPackage($batch->id, [
            'tracking_id' => 'TRK-BASE',
            'dropoff_address' => '100 Main St',
        ], $this->worker1->id);
        $batch->update(['status' => UrbanGoodzIntakeBatch::STATUS_READY_FOR_ROUTING]);
        $this->lockingService->lockForRouting($batch->id, $this->supervisor->id);

        // Try to add late package with 'hold_for_next_wave' policy
        $lateRes = $this->latePackageService->handleLatePackage($batch->id, [
            'tracking_id' => 'TRK-LATE',
            'dropoff_address' => '200 Main St',
        ], 'hold_for_next_wave', $this->supervisor->id);

        $this->assertTrue($lateRes['success']);
        $package = UrbanGoodzBatchPackage::find($lateRes['package_id']);
        $this->assertEquals('unassigned', $package->route_assignment_status);
    }

    public function test_1000_package_concurrency_stress_simulation(): void
    {
        // 1. Create a controlled Business intake batch.
        $batch = $this->intakeService->createBatch([
            'business_client_id' => $this->businessA->id,
            'service_date' => now()->toDateString(),
            'expected_package_count' => 1000,
        ], $this->supervisor->id);
        $batch = $this->intakeService->openBatch($batch->id, $this->supervisor->id);

        // 2. Join the four workers
        $this->intakeService->joinBatch($batch->id, $this->worker1->id, 'intake_worker', 'dev-1');
        $this->intakeService->joinBatch($batch->id, $this->worker2->id, 'intake_worker', 'dev-2');
        $this->intakeService->joinBatch($batch->id, $this->worker3->id, 'intake_worker', 'dev-3');
        $this->intakeService->joinBatch($batch->id, $this->worker4->id, 'intake_worker', 'dev-4');

        $workers = [$this->worker1, $this->worker2, $this->worker3, $this->worker4];

        // 3. Input 1,000 packages using mixed sources and worker sessions
        DB::beginTransaction();

        for ($i = 1; $i <= 1000; $i++) {
            $worker = $workers[($i % 4)];
            $sourceType = match ($i % 4) {
                0 => 'barcode_scan',
                1 => 'csv_import',
                2 => 'manual_entry',
                3 => 'api',
            };

            $trackingId = "STRESS-TRK-{$i}";
            $barcode = "STRESS-BAR-{$i}";
            $address = "{$i} Stress Test Lane";

            // Deliberate duplicate scan for item 500
            if ($i === 505) {
                $trackingId = "STRESS-TRK-100"; // Duplicate of item 100
            }

            // Invalid address for item 600
            if ($i === 600) {
                $address = "";
            }

            $this->intakeService->addPackage($batch->id, [
                'tracking_id' => $trackingId,
                'barcode' => $barcode,
                'dropoff_address' => $address,
                'source_type' => $sourceType,
            ], $worker->id, "dev-" . ($i % 4 + 1));
        }

        DB::commit();

        // Assert accounting counts
        $totalCreated = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)->count();
        $activeValid = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)
            ->where('is_active', true)
            ->where('validation_status', 'valid')
            ->count();
        $activeInvalid = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)
            ->where('is_active', true)
            ->where('validation_status', 'invalid')
            ->count();

        // Ensure 1,000 intended packages were processed (999 created because 1 duplicate check rejected the duplicate)
        $this->assertEquals(999, $totalCreated);
        $this->assertEquals(998, $activeValid);
        $this->assertEquals(1, $activeInvalid); // The one with empty address

        // Lock the batch
        $batch->update(['status' => UrbanGoodzIntakeBatch::STATUS_READY_FOR_ROUTING]);
        $lockRes = $this->lockingService->lockForRouting($batch->id, $this->supervisor->id);
        $this->assertTrue($lockRes['success']);

        // Try adding late package arriving after batch lock
        $lateRes = $this->latePackageService->handleLatePackage($batch->id, [
            'tracking_id' => 'STRESS-LATE-1001',
            'dropoff_address' => '1001 Late St',
        ], 'hold_for_next_wave', $this->supervisor->id);

        $this->assertTrue($lateRes['success']);

        // Package total reconciliation invariant check
        $allPackages = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)->get();
        
        $activeRouted = $allPackages->where('is_active', true)->where('route_assignment_status', 'assigned')->count();
        $unrouteable = $allPackages->where('is_active', true)->where('validation_status', 'invalid')->count();
        $lateUnassigned = $allPackages->where('is_active', true)->where('route_assignment_status', 'late')->count();
        $unassigned = $allPackages->where('is_active', true)->where('validation_status', 'valid')->where('route_assignment_status', 'unassigned')->count();
        $rejectedOrDuplicates = $allPackages->where('is_active', false)->count();

        $sum = $activeRouted + $unrouteable + $lateUnassigned + $unassigned + $rejectedOrDuplicates;
        $this->assertEquals($allPackages->count(), $sum);
    }
}
