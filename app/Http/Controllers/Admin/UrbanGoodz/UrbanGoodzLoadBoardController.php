<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class UrbanGoodzLoadBoardController extends Controller
{
    public function __construct(
        protected UrbanGoodzLoadBoardService $loadBoardService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'origin_state', 'destination_state', 'load_type', 'equipment_type',
            'min_payout', 'max_distance_miles', 'is_hazmat', 'requires_liftgate', 'is_expedited',
        ]);

        $result = $this->loadBoardService->listAvailable($filters, $request->input('page', 1));
        $stats = $this->loadBoardService->getStats();

        return view('admin-views.urban-goodz.load-board.index', [
            'loads' => $result['loads'],
            'meta' => $result['meta'],
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    public function show($id)
    {
        $load = $this->loadBoardService->getById($id);
        if (!$load) {
            abort(404);
        }

        return view('admin-views.urban-goodz.load-board.show', ['load' => $load]);
    }

    public function create()
    {
        return view('admin-views.urban-goodz.load-board.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'load_number' => 'nullable|string|max:100',
            'origin_name' => 'nullable|string|max:255',
            'origin_city' => 'nullable|string|max:100',
            'origin_state' => 'nullable|string|max:2',
            'origin_zip' => 'nullable|string|max:10',
            'destination_name' => 'nullable|string|max:255',
            'destination_city' => 'nullable|string|max:100',
            'destination_state' => 'nullable|string|max:2',
            'destination_zip' => 'nullable|string|max:10',
            'distance_miles' => 'nullable|numeric|min:0',
            'payout_amount' => 'required|numeric|min:0',
            'payout_type' => 'nullable|string|in:flat,per_mile,per_hour',
            'rate_per_mile' => 'nullable|numeric|min:0',
            'load_type' => 'nullable|string|max:50',
            'equipment_type' => 'nullable|string|max:50',
            'weight_lbs' => 'nullable|numeric|min:0',
            'length_ft' => 'nullable|numeric|min:0',
            'pieces' => 'nullable|integer|min:0',
            'commodity_description' => 'nullable|string|max:500',
            'special_requirements' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'is_hazmat' => 'boolean',
            'is_temperature_controlled' => 'boolean',
            'temperature_min_f' => 'nullable|numeric',
            'temperature_max_f' => 'nullable|numeric',
            'requires_liftgate' => 'boolean',
            'requires_pallet_jack' => 'boolean',
            'is_team_load' => 'boolean',
            'is_expedited' => 'boolean',
            'shipper_name' => 'nullable|string|max:255',
            'shipper_phone' => 'nullable|string|max:20',
            'consignee_name' => 'nullable|string|max:255',
            'consignee_phone' => 'nullable|string|max:20',
            'origin_ready_at' => 'nullable|date',
            'destination_due_at' => 'nullable|date',
        ]);

        $validated['provider'] = 'internal';

        $this->loadBoardService->createLoad($validated);

        Toastr::success(translate('Load created successfully'));
        return redirect()->route('admin.urban-goodz.load-board.index');
    }

    public function edit($id)
    {
        $load = $this->loadBoardService->getById($id);
        if (!$load) {
            abort(404);
        }

        return view('admin-views.urban-goodz.load-board.edit', ['load' => $load]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'load_number' => 'nullable|string|max:100',
            'origin_name' => 'nullable|string|max:255',
            'origin_city' => 'nullable|string|max:100',
            'origin_state' => 'nullable|string|max:2',
            'origin_zip' => 'nullable|string|max:10',
            'destination_name' => 'nullable|string|max:255',
            'destination_city' => 'nullable|string|max:100',
            'destination_state' => 'nullable|string|max:2',
            'destination_zip' => 'nullable|string|max:10',
            'distance_miles' => 'nullable|numeric|min:0',
            'payout_amount' => 'required|numeric|min:0',
            'payout_type' => 'nullable|string|in:flat,per_mile,per_hour',
            'rate_per_mile' => 'nullable|numeric|min:0',
            'load_type' => 'nullable|string|max:50',
            'equipment_type' => 'nullable|string|max:50',
            'weight_lbs' => 'nullable|numeric|min:0',
            'length_ft' => 'nullable|numeric|min:0',
            'pieces' => 'nullable|integer|min:0',
            'commodity_description' => 'nullable|string|max:500',
            'special_requirements' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'is_hazmat' => 'boolean',
            'is_temperature_controlled' => 'boolean',
            'temperature_min_f' => 'nullable|numeric',
            'temperature_max_f' => 'nullable|numeric',
            'requires_liftgate' => 'boolean',
            'requires_pallet_jack' => 'boolean',
            'is_team_load' => 'boolean',
            'is_expedited' => 'boolean',
            'shipper_name' => 'nullable|string|max:255',
            'shipper_phone' => 'nullable|string|max:20',
            'consignee_name' => 'nullable|string|max:255',
            'consignee_phone' => 'nullable|string|max:20',
            'origin_ready_at' => 'nullable|date',
            'destination_due_at' => 'nullable|date',
        ]);

        $result = $this->loadBoardService->updateLoad($id, $validated);
        if (!$result) {
            Toastr::error(translate('Load not found'));
            return redirect()->back();
        }

        Toastr::success(translate('Load updated successfully'));
        return redirect()->route('admin.urban-goodz.load-board.show', $id);
    }

    public function destroy($id)
    {
        $result = $this->loadBoardService->deleteLoad($id);
        if (!$result) {
            Toastr::error(translate('Load cannot be deleted (may be assigned or in transit)'));
            return redirect()->back();
        }

        Toastr::success(translate('Load deleted successfully'));
        return redirect()->route('admin.urban-goodz.load-board.index');
    }
}
