<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzHistoricalReconstructionConfiguration;
use App\Models\UrbanGoodzHistoricalMonthlySnapshot;
use App\Models\UrbanGoodzHistoricalSourceRecord;
use App\Models\UrbanGoodzHistoricalReconstructionAuditTrail;
use App\Services\UrbanGoodz\HistoricalReconstructionService;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class UrbanGoodzHistoricalReconstructionController extends Controller
{
    protected HistoricalReconstructionService $service;

    public function __construct(HistoricalReconstructionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $configs = UrbanGoodzHistoricalReconstructionConfiguration::withCount('snapshots')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('configuration_name', 'like', "%{$request->search}%");
            })
            ->latest()
            ->paginate(15);

        return view('admin-views.urban-goodz.historical-reconstruction.index', compact('configs'));
    }

    public function create()
    {
        return view('admin-views.urban-goodz.historical-reconstruction.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'configuration_name' => 'required|string|max:255',
            'reconstruction_start_date' => 'required|date',
            'reconstruction_end_date' => 'required|date|after:reconstruction_start_date',
            'owner_name' => 'nullable|string|max:255',
            'owner_non_delivery_months' => 'nullable|string',
            'baseline_monthly_orders' => 'required|numeric|min:0',
            'baseline_average_order_value' => 'required|numeric|min:0',
            'baseline_order_commission_pct' => 'required|numeric|min:0|max:100',
            'baseline_delivery_fee' => 'required|numeric|min:0',
            'baseline_platform_delivery_fee_pct' => 'required|numeric|min:0|max:100',
            'baseline_active_drivers' => 'required|integer|min:1',
            'baseline_avg_monthly_net' => 'required|numeric',
            'orders_variation_pct' => 'required|numeric|min:0|max:100',
            'aov_variation_pct' => 'required|numeric|min:0|max:100',
            'delivery_fee_variation_pct' => 'required|numeric|min:0|max:100',
            'driver_count_variation_pct' => 'required|numeric|min:0|max:100',
            'operating_expense_ratio' => 'required|numeric|min:0|max:100',
        ]);

        if (!empty($validated['owner_non_delivery_months'])) {
            $validated['owner_non_delivery_months'] = array_map('intval', array_filter(explode(',', $validated['owner_non_delivery_months'])));
        } else {
            $validated['owner_non_delivery_months'] = [12, 1, 2];
        }

        $config = $this->service->createConfiguration($validated, auth()->id());

        return redirect()
            ->route('admin.urban-goodz.historical-reconstruction.show', $config->id)
            ->with('success', 'Configuration created. Run reconstruction to generate monthly snapshots.');
    }

    public function show(int $id)
    {
        $data = $this->service->getReconstructionSummary($id);

        return view('admin-views.urban-goodz.historical-reconstruction.show', $data);
    }

    public function edit(int $id)
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($id);

        return view('admin-views.urban-goodz.historical-reconstruction.edit', compact('config'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'configuration_name' => 'required|string|max:255',
            'reconstruction_start_date' => 'required|date',
            'reconstruction_end_date' => 'required|date|after:reconstruction_start_date',
            'owner_name' => 'nullable|string|max:255',
            'owner_non_delivery_months' => 'nullable|string',
            'baseline_monthly_orders' => 'required|numeric|min:0',
            'baseline_average_order_value' => 'required|numeric|min:0',
            'baseline_order_commission_pct' => 'required|numeric|min:0|max:100',
            'baseline_delivery_fee' => 'required|numeric|min:0',
            'baseline_platform_delivery_fee_pct' => 'required|numeric|min:0|max:100',
            'baseline_active_drivers' => 'required|integer|min:1',
            'baseline_avg_monthly_net' => 'required|numeric',
            'orders_variation_pct' => 'required|numeric|min:0|max:100',
            'aov_variation_pct' => 'required|numeric|min:0|max:100',
            'delivery_fee_variation_pct' => 'required|numeric|min:0|max:100',
            'driver_count_variation_pct' => 'required|numeric|min:0|max:100',
            'operating_expense_ratio' => 'required|numeric|min:0|max:100',
        ]);

        if (!empty($validated['owner_non_delivery_months'])) {
            $validated['owner_non_delivery_months'] = array_map('intval', array_filter(explode(',', $validated['owner_non_delivery_months'])));
        } else {
            $validated['owner_non_delivery_months'] = [12, 1, 2];
        }

        $this->service->updateConfiguration($id, $validated, auth()->id());

        return redirect()
            ->route('admin.urban-goodz.historical-reconstruction.show', $id)
            ->with('success', 'Configuration updated.');
    }

    public function runReconstruction(int $id)
    {
        $snapshots = $this->service->runFullReconstruction($id, auth()->id());

        return redirect()
            ->route('admin.urban-goodz.historical-reconstruction.show', $id)
            ->with('success', "Reconstruction complete. {$snapshots->count()} months generated.");
    }

    public function snapshotDetail(int $configId, int $snapshotId)
    {
        $snapshot = UrbanGoodzHistoricalMonthlySnapshot::findOrFail($snapshotId);
        $sources = UrbanGoodzHistoricalSourceRecord::where('snapshot_id', $snapshotId)->get();

        return view('admin-views.urban-goodz.historical-reconstruction.snapshot', compact('snapshot', 'sources', 'configId'));
    }

    public function sourceRecords(int $id)
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($id);
        $records = UrbanGoodzHistoricalSourceRecord::where('configuration_id', $id)
            ->with('snapshot')
            ->latest()
            ->paginate(25);

        return view('admin-views.urban-goodz.historical-reconstruction.source-records', compact('config', 'records'));
    }

    public function importSourceRecord(Request $request, int $id)
    {
        $validated = $request->validate([
            'source_type' => 'required|string|in:' . implode(',', array_keys(UrbanGoodzHistoricalSourceRecord::SOURCE_TYPES)),
            'source_description' => 'nullable|string|max:255',
            'source_date' => 'nullable|date',
            'snapshot_month' => 'nullable|date_format:Y-m',
            'confidence_label' => 'required|string|in:verified,high,medium,estimated',
            'notes' => 'nullable|string',
            'orders' => 'nullable|numeric|min:0',
            'average_order_value' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'active_drivers' => 'nullable|integer|min:0',
            'owner_deliveries' => 'nullable|integer|min:0',
        ]);

        $snapshotId = null;
        if (!empty($validated['snapshot_month'])) {
            $snapshot = UrbanGoodzHistoricalMonthlySnapshot::where('configuration_id', $id)
                ->whereYear('snapshot_month', substr($validated['snapshot_month'], 0, 4))
                ->whereMonth('snapshot_month', substr($validated['snapshot_month'], 5, 2))
                ->first();
            $snapshotId = $snapshot?->id;
        }

        $sourceData = array_filter([
            'orders' => $validated['orders'] ?? null,
            'average_order_value' => $validated['average_order_value'] ?? null,
            'delivery_fee' => $validated['delivery_fee'] ?? null,
            'active_drivers' => $validated['active_drivers'] ?? null,
            'owner_deliveries' => $validated['owner_deliveries'] ?? null,
        ], fn ($v) => $v !== null);

        $this->service->importSourceRecord($id, [
            'source_type' => $validated['source_type'],
            'source_description' => $validated['source_description'] ?? null,
            'source_date' => $validated['source_date'] ?? null,
            'source_data' => !empty($sourceData) ? $sourceData : null,
            'confidence_label' => $validated['confidence_label'],
            'confidence_score' => UrbanGoodzHistoricalSourceRecord::CONFIDENCE_LABELS[$validated['confidence_label']] ?? 0.5,
            'notes' => $validated['notes'] ?? null,
            'overrides_reconstruction' => true,
        ], $snapshotId, auth()->id());

        return redirect()
            ->route('admin.urban-goodz.historical-reconstruction.source-records', $id)
            ->with('success', 'Source record imported successfully.');
    }

    public function auditTrail(int $id)
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($id);
        $trail = UrbanGoodzHistoricalReconstructionAuditTrail::where('configuration_id', $id)
            ->latest()
            ->paginate(50);

        return view('admin-views.urban-goodz.historical-reconstruction.audit-trail', compact('config', 'trail'));
    }

    public function exportCsv(int $id)
    {
        $csv = $this->service->exportCsv($id);
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($id);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="urban-goodz-reconstruction-' . $config->configuration_name . '.csv"');
    }

    public function exportJson(int $id)
    {
        $data = $this->service->exportJson($id);

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="urban-goodz-reconstruction-' . $data['configuration']['configuration_name'] . '.json"');
    }

    public function exportPdf(int $id)
    {
        $data = $this->service->getReconstructionSummary($id);
        $configuration = $data['configuration'];
        $snapshots = $data['snapshots'];
        $summary = $data['summary'];

        $pdfView = View::make('admin-views.urban-goodz.historical-reconstruction.pdf-report', compact('configuration', 'snapshots', 'summary'));

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $configuration->configuration_name);
        Helpers::gen_mpdf($pdfView, 'UrbanGoodz_Reconstruction_' . $safeName, '');
    }

    public function truckPurchaseTimeline(int $id)
    {
        $timeline = $this->service->getTruckPurchaseTimeline($id);

        return view('admin-views.urban-goodz.historical-reconstruction.truck-timeline', $timeline);
    }

    public function destroy(int $id)
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($id);
        $config->delete();

        return redirect()
            ->route('admin.urban-goodz.historical-reconstruction.index')
            ->with('success', 'Configuration deleted.');
    }
}
