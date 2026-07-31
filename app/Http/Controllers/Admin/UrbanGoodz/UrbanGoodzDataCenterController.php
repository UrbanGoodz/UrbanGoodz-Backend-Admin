<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzImportBatch;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedImage;
use App\Models\UrbanGoodzSourcedProduct;
use App\Services\UrbanGoodzDataCenterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class UrbanGoodzDataCenterController extends Controller
{
    public function __construct(private readonly UrbanGoodzDataCenterService $dataCenter)
    {
    }

    public function index(Request $request)
    {
        $batches = UrbanGoodzImportBatch::query()
            ->when($request->filled('queue_type'), fn ($query) => $query->where('queue_type', $request->input('queue_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('priority')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'queued' => UrbanGoodzImportBatch::where('status', 'queued')->count(),
            'review_required' => UrbanGoodzImportBatch::where('status', 'review_required')->count(),
            'failed' => UrbanGoodzImportBatch::whereIn('status', ['failed', 'partially_failed'])->count(),
            'approved' => UrbanGoodzImportBatch::where('status', 'approved')->count(),
            'api_visible' => UrbanGoodzSourcedBusiness::where('api_visible', true)->count(),
            'shopper_visible' => UrbanGoodzSourcedBusiness::where('shopper_visible', true)->count(),
        ];

        return view('admin-views.urban-goodz.data-center.index', compact('batches', 'stats'));
    }

    public function stage(Request $request)
    {
        $validated = $request->validate([
            'queue_type' => ['required', Rule::in(['import', 'sourcing'])],
            'category' => ['required', 'string', 'max:255'],
            'module' => ['required', 'string', 'max:255'],
            'location.city' => ['required', 'string', 'max:255'],
            'location.state' => ['required', 'string', 'max:50'],
            'source_name' => ['required', 'string', 'max:255'],
            'source_platforms' => ['required', 'array', 'min:1'],
            'businesses' => ['required', 'array', 'min:1', 'max:500'],
        ]);

        $manifest = $request->only([
            'queue_type',
            'category',
            'module',
            'location',
            'source_name',
            'source_query',
            'source_platforms',
            'max_attempts',
            'businesses',
        ]);
        $batch = $this->dataCenter->stage(array_merge($manifest, $validated), $this->adminId());

        return response()->json([
            'success' => $batch->status !== 'failed',
            'message' => 'Import staged for review. No live store or item was created.',
            'data' => $this->dataCenter->preview($batch),
        ], $batch->status === 'failed' ? 422 : 201);
    }

    public function preview(UrbanGoodzImportBatch $batch)
    {
        return response()->json([
            'success' => true,
            'data' => $this->dataCenter->preview($batch),
        ]);
    }

    public function retry(UrbanGoodzImportBatch $batch)
    {
        return $this->run(fn () => [
            'message' => 'Bounded import retry completed.',
            'data' => $this->dataCenter->preview($this->dataCenter->retry($batch)),
        ]);
    }

    public function reviewBusiness(Request $request, UrbanGoodzSourcedBusiness $business)
    {
        $data = $request->validate([
            'admin_review_status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'merge_required'])],
            'source_verified' => ['required', 'boolean'],
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'min:2'],
        ]);

        return $this->run(fn () => [
            'message' => 'Business review saved; visibility remains private.',
            'data' => $this->dataCenter->reviewBusiness(
                $business,
                $data['admin_review_status'],
                (bool) $data['source_verified'],
                $data['category_ids'],
                $this->adminId()
            ),
        ]);
    }

    public function reviewProduct(Request $request, UrbanGoodzSourcedProduct $product)
    {
        $data = $request->validate([
            'admin_review_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'category_id' => ['nullable', 'integer', 'min:2'],
        ]);

        return $this->run(fn () => [
            'message' => 'Catalog product review saved; visibility remains private.',
            'data' => $this->dataCenter->reviewProduct(
                $product,
                $data['admin_review_status'],
                isset($data['category_id']) ? (int) $data['category_id'] : null
            ),
        ]);
    }

    public function reviewImage(Request $request, UrbanGoodzSourcedImage $image)
    {
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'rights_status' => [
                'required',
                Rule::in(['vendor_owned', 'public_official', 'customer_uploaded', 'generated_placeholder', 'unknown_review_required']),
            ],
        ]);

        return $this->run(fn () => [
            'message' => 'Product image review saved; visibility remains private.',
            'data' => $this->dataCenter->reviewImage($image, $data['review_status'], $data['rights_status']),
        ]);
    }

    public function approve(UrbanGoodzImportBatch $batch)
    {
        return $this->run(fn () => [
            'message' => 'Batch approved. API and Shopper visibility remain disabled until explicitly enabled.',
            'data' => $this->dataCenter->approveBatch($batch, $this->adminId()),
        ]);
    }

    public function visibility(Request $request, UrbanGoodzImportBatch $batch)
    {
        $data = $request->validate([
            'api_visible' => ['required', 'boolean'],
            'shopper_visible' => ['required', 'boolean'],
        ]);

        return $this->run(fn () => [
            'message' => 'Marketplace visibility updated through the verified review gate.',
            'data' => $this->dataCenter->setVisibility(
                $batch,
                (bool) $data['api_visible'],
                (bool) $data['shopper_visible'],
                $this->adminId()
            ),
        ]);
    }

    public function rollback(Request $request, UrbanGoodzImportBatch $batch)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->run(fn () => [
            'message' => 'Latest data center revision rolled back.',
            'data' => $this->dataCenter->rollback($batch, $data['reason'], $this->adminId()),
        ]);
    }

    private function adminId(): ?int
    {
        return auth('admin')->id();
    }

    private function run(callable $operation)
    {
        try {
            $result = $operation();

            return response()->json(array_merge(['success' => true], $result));
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
