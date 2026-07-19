<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzIntakeBatch;
use App\Models\UrbanGoodzIntakeBatchAudit;
use App\Services\UrbanGoodz\Routing\Services\BatchIntakeService;
use App\Services\UrbanGoodz\Routing\Services\BatchLockingService;
use App\Services\UrbanGoodz\Routing\Services\BatchProgressService;
use App\Services\UrbanGoodz\Routing\Services\LatePackagePolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BatchIntakeController extends Controller
{
    private BatchIntakeService $intake;
    private BatchProgressService $progress;
    private BatchLockingService $locking;
    private LatePackagePolicyService $latePolicy;

    public function __construct(
        BatchIntakeService $intake,
        BatchProgressService $progress,
        BatchLockingService $locking,
        LatePackagePolicyService $latePolicy
    ) {
        $this->intake = $intake;
        $this->progress = $progress;
        $this->locking = $locking;
        $this->latePolicy = $latePolicy;
    }

    private function getScopedBusinessId(): ?int
    {
        if (auth('business')->check()) {
            return (int)auth('business')->user()->business_client_id;
        }
        return null;
    }

    private function verifyBatchAccess(UrbanGoodzIntakeBatch $batch): void
    {
        $scopedId = $this->getScopedBusinessId();
        if ($scopedId !== null && (int)$batch->business_client_id !== $scopedId) {
            abort(403, "Access denied to batch.");
        }
    }

    private function verifyPackageAccess(UrbanGoodzBatchPackage $package): void
    {
        $scopedId = $this->getScopedBusinessId();
        if ($scopedId !== null && (int)$package->business_client_id !== $scopedId) {
            abort(403, "Access denied to package.");
        }
    }

    // --- Batch CRUD ---

    public function store(Request $request)
    {
        $scopedId = $this->getScopedBusinessId();

        $rules = [
            'batch_name' => 'nullable|string|max:255',
            'service_date' => 'required|date',
            'intake_start_time' => 'nullable|date_format:H:i',
            'intake_cutoff_time' => 'nullable|date_format:H:i|after:intake_start_time',
            'expected_package_count' => 'nullable|integer|min:0',
            'routing_policy' => 'nullable|string|in:standard,time_window_priority,cluster_first,manual_assignment,driver_preference',
            'supervisor_user_id' => 'nullable|exists:users,id',
            'dispatcher_user_id' => 'nullable|exists:users,id',
        ];

        if ($scopedId === null) {
            $rules['business_client_id'] = 'required|exists:urban_goodz_business_clients,id';
        }

        $data = $request->validate($rules);

        if ($scopedId !== null) {
            $data['business_client_id'] = $scopedId;
        }

        $batch = $this->intake->createBatch($data, auth()->id() ?? auth('business')->id(), $scopedId);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'batch' => $batch,
        ], 201);
    }

    public function show(int $batchId)
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->verifyBatchAccess($batch);

        return response()->json([
            'batch' => $batch,
            'progress' => $this->progress->getProgress($batchId, $this->getScopedBusinessId()),
        ]);
    }

    public function index(Request $request)
    {
        $query = UrbanGoodzIntakeBatch::query();
        $scopedId = $this->getScopedBusinessId();

        if ($scopedId !== null) {
            $query->where('business_client_id', $scopedId);
        } elseif ($request->has('business_client_id')) {
            $query->where('business_client_id', $request->input('business_client_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('service_date')) {
            $query->where('service_date', $request->input('service_date'));
        }

        $batches = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($batches);
    }

    public function open(int $batchId)
    {
        $batch = $this->intake->openBatch($batchId, auth()->id() ?? auth('business')->id(), $this->getScopedBusinessId());
        return response()->json(['success' => true, 'batch' => $batch]);
    }

    public function cancel(int $batchId)
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->verifyBatchAccess($batch);

        $batch->cancel();

        UrbanGoodzIntakeBatchAudit::log($batchId, 'cancelled', auth()->id() ?? auth('business')->id());

        return response()->json(['success' => true, 'batch' => $batch]);
    }

    // --- Participants ---

    public function join(Request $request, int $batchId)
    {
        $data = $request->validate([
            'role' => 'required|string|in:intake_worker,intake_supervisor,dispatcher,admin',
            'device_session_id' => 'nullable|string',
            'source_portal' => 'nullable|string',
        ]);

        $participant = $this->intake->joinBatch(
            $batchId,
            auth()->id() ?? auth('business')->id(),
            $data['role'],
            $data['device_session_id'] ?? null,
            $data['source_portal'] ?? null,
            $this->getScopedBusinessId()
        );

        return response()->json(['success' => true, 'participant' => $participant]);
    }

    public function participants(int $batchId)
    {
        return response()->json([
            'participants' => $this->progress->getParticipants($batchId, $this->getScopedBusinessId()),
        ]);
    }

    // --- Package intake ---

    public function addPackage(Request $request, int $batchId)
    {
        $data = $request->validate([
            'tracking_id' => 'nullable|string|max:100',
            'external_package_id' => 'nullable|string|max:100',
            'order_reference_number' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'source_type' => 'nullable|string|in:barcode_scan,qr_scan,manual_entry,csv_import,spreadsheet_import,api,edi_manifest,existing_pool',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'pickup_address' => 'nullable|string',
            'pickup_city' => 'nullable|string',
            'pickup_state' => 'nullable|string|max:10',
            'pickup_zip' => 'nullable|string|max:20',
            'dropoff_lat' => 'nullable|numeric',
            'dropoff_lng' => 'nullable|numeric',
            'dropoff_address' => 'required|string',
            'dropoff_city' => 'nullable|string',
            'dropoff_state' => 'nullable|string|max:10',
            'dropoff_zip' => 'nullable|string|max:20',
            'recipient_name' => 'nullable|string',
            'recipient_phone' => 'nullable|string',
            'recipient_email' => 'nullable|email',
            'priority' => 'nullable|string|in:normal,urgent,medical',
            'delivery_window_start' => 'nullable|date',
            'delivery_window_end' => 'nullable|date|after:delivery_window_start',
            'weight_lbs' => 'nullable|numeric|min:0',
            'volume_cubic_ft' => 'nullable|numeric|min:0',
            'package_type' => 'nullable|string',
            'age_restricted' => 'nullable|boolean',
            'requires_signature' => 'nullable|boolean',
            'requires_photo' => 'nullable|boolean',
            'requires_custody' => 'nullable|boolean',
            'special_instructions' => 'nullable|string',
        ]);

        $result = $this->intake->addPackage(
            $batchId,
            $data,
            auth()->id() ?? auth('business')->id(),
            $request->input('device_session_id'),
            $this->getScopedBusinessId()
        );

        $statusCode = $result['success'] ? 201 : 409;
        return response()->json($result, $statusCode);
    }

    public function updatePackage(Request $request, int $packageId)
    {
        $data = $request->validate([
            'tracking_id' => 'nullable|string|max:100',
            'dropoff_address' => 'nullable|string',
            'dropoff_city' => 'nullable|string',
            'dropoff_state' => 'nullable|string|max:10',
            'dropoff_zip' => 'nullable|string|max:20',
            'dropoff_lat' => 'nullable|numeric',
            'dropoff_lng' => 'nullable|numeric',
            'recipient_name' => 'nullable|string',
            'recipient_phone' => 'nullable|string',
            'priority' => 'nullable|string|in:normal,urgent,medical',
            'delivery_window_start' => 'nullable|date',
            'delivery_window_end' => 'nullable|date',
            'weight_lbs' => 'nullable|numeric|min:0',
            'special_instructions' => 'nullable|string',
        ]);

        $result = $this->intake->updatePackage(
            $packageId,
            $data,
            auth()->id() ?? auth('business')->id(),
            $request->input('device_session_id'),
            $this->getScopedBusinessId()
        );

        $statusCode = $result['success'] ? 200 : 409;
        return response()->json($result, $statusCode);
    }

    public function bulkImport(Request $request, int $batchId)
    {
        $request->validate([
            'packages' => 'required|array|min:1',
            'source_type' => 'nullable|string',
            'file_ref' => 'nullable|string',
        ]);

        $result = $this->intake->bulkImport(
            $request->input('packages'),
            $batchId,
            auth()->id() ?? auth('business')->id(),
            $request->input('source_type', 'csv_import'),
            $request->input('file_ref'),
            $this->getScopedBusinessId()
        );

        return response()->json(['success' => true, 'import_result' => $result]);
    }

    // --- Review queue ---

    public function validationQueue(int $batchId)
    {
        return response()->json([
            'queue' => $this->progress->getValidationQueue($batchId, $this->getScopedBusinessId()),
        ]);
    }

    public function assignReview(Request $request, int $packageId)
    {
        $request->validate([
            'assignee_id' => 'required|exists:users,id',
        ]);

        $this->intake->assignReview(
            $packageId,
            $request->input('assignee_id'),
            auth()->id() ?? auth('business')->id(),
            $this->getScopedBusinessId()
        );

        return response()->json(['success' => true]);
    }

    public function completeReview(Request $request, int $packageId)
    {
        $data = $request->validate([
            'resolution' => 'required|string|in:approve,correct,reject',
            'corrected_data' => 'nullable|array',
        ]);

        $this->intake->completeReview(
            $packageId,
            auth()->id() ?? auth('business')->id(),
            $data['resolution'],
            $data['corrected_data'] ?? null,
            $this->getScopedBusinessId()
        );

        return response()->json(['success' => true]);
    }

    // --- Progress ---

    public function progress(int $batchId)
    {
        return response()->json([
            'progress' => $this->progress->getProgress($batchId, $this->getScopedBusinessId()),
        ]);
    }

    public function workerActivity(int $batchId, Request $request)
    {
        $minutes = $request->input('minutes', 60);

        return response()->json([
            'activity' => $this->progress->getWorkerActivity($batchId, $minutes, $this->getScopedBusinessId()),
        ]);
    }

    // --- Locking & routing ---

    public function lockForRouting(Request $request, int $batchId)
    {
        $data = $request->validate([
            'requested_route_count' => 'nullable|integer|min:1',
            'target_packages_per_route' => 'nullable|integer|min:1',
            'maximum_packages_per_route' => 'nullable|integer|min:1',
            'maximum_cluster_radius_miles' => 'nullable|numeric|min:1',
            'respect_time_windows' => 'nullable|boolean',
            'preserve_locked_stops' => 'nullable|boolean',
            'return_to_origin' => 'nullable|boolean',
        ]);

        $result = $this->locking->lockForRouting(
            $batchId,
            auth()->id() ?? auth('business')->id(),
            $data,
            $this->getScopedBusinessId()
        );

        return response()->json($result);
    }

    public function unlockBatch(int $batchId, Request $request)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $batch = $this->locking->unlockBatch(
            $batchId,
            auth()->id() ?? auth('business')->id(),
            $data['reason'] ?? null,
            $this->getScopedBusinessId()
        );

        return response()->json(['success' => true, 'batch' => $batch]);
    }

    // --- Late packages ---

    public function addLatePackage(Request $request, int $batchId)
    {
        $data = $request->validate([
            'policy' => 'required|string|in:add_to_unlocked_route,create_overflow_route,hold_for_next_wave,dispatcher_review,reoptimize_affected_routes,reoptimize_full_batch',
            'package' => 'required|array',
            'package.tracking_id' => 'nullable|string',
            'package.dropoff_address' => 'required|string',
            'package.dropoff_city' => 'nullable|string',
            'package.dropoff_state' => 'nullable|string',
            'package.dropoff_zip' => 'nullable|string',
            'package.dropoff_lat' => 'nullable|numeric',
            'package.dropoff_lng' => 'nullable|numeric',
            'package.recipient_name' => 'nullable|string',
            'package.priority' => 'nullable|string',
        ]);

        $result = $this->latePolicy->handleLatePackage(
            $batchId,
            $data['package'],
            $data['policy'],
            auth()->id() ?? auth('business')->id(),
            $request->input('device_session_id'),
            $this->getScopedBusinessId()
        );

        $statusCode = $result['success'] ? 200 : 409;
        return response()->json($result, $statusCode);
    }

    // --- Package list ---

    public function packages(Request $request, int $batchId)
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->verifyBatchAccess($batch);

        $query = UrbanGoodzBatchPackage::where('intake_batch_id', $batchId);

        if ($request->has('validation_status')) {
            $query->where('validation_status', $request->input('validation_status'));
        }

        if ($request->has('duplicate_status')) {
            $query->where('duplicate_status', $request->input('duplicate_status'));
        }

        if ($request->has('route_assignment_status')) {
            $query->where('route_assignment_status', $request->input('route_assignment_status'));
        }

        if ($request->has('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $packages = $query->orderByDesc('created_at')->paginate(50);

        return response()->json($packages);
    }

    public function showPackage(int $packageId)
    {
        $package = UrbanGoodzBatchPackage::with(['createdBy:id,name', 'modifiedBy:id,name', 'duplicateOf:id,tracking_id'])
            ->findOrFail($packageId);
        $this->verifyPackageAccess($package);

        $audits = $package->audits()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'package' => $package,
            'audits' => $audits,
        ]);
    }
}
