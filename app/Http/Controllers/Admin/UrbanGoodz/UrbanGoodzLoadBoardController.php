<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\DeliveryMan;
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
            'status', 'search', 'origin_state', 'destination_state', 'load_type', 'equipment_type',
            'min_payout', 'max_distance_miles', 'is_hazmat', 'requires_liftgate', 'is_expedited',
            'provider', 'business_client_id', 'assigned_driver_id',
        ]);

        $result = $this->loadBoardService->listAvailable($filters, $request->input('page', 1));
        $stats = $this->loadBoardService->getStats();

        return view('admin-views.urban-goodz.load-board.index', [
            'loads' => $result['loads'],
            'meta' => $result['meta'],
            'stats' => $stats,
            'filters' => $filters,
            'statuses' => UrbanGoodzLoadBoardLoad::STATUSES,
        ]);
    }

    public function show($id)
    {
        $load = $this->loadBoardService->getById($id);
        if (!$load) {
            abort(404);
        }

        $eligibleDrivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->where('load_board_eligible', true)
            ->get();

        return view('admin-views.urban-goodz.load-board.show', [
            'load' => $load,
            'eligibleDrivers' => $eligibleDrivers,
        ]);
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
            'driver_payout_amount' => 'nullable|numeric|min:0',
            'customer_price' => 'nullable|numeric|min:0',
            'platform_margin' => 'nullable|numeric|min:0',
            'dispatcher_incentive' => 'nullable|numeric|min:0',
            'source_cost' => 'nullable|numeric|min:0',
            'processing_fee' => 'nullable|numeric|min:0',
            'accessorials' => 'nullable|numeric|min:0',
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
            'driver_payout_amount' => 'nullable|numeric|min:0',
            'customer_price' => 'nullable|numeric|min:0',
            'platform_margin' => 'nullable|numeric|min:0',
            'dispatcher_incentive' => 'nullable|numeric|min:0',
            'source_cost' => 'nullable|numeric|min:0',
            'processing_fee' => 'nullable|numeric|min:0',
            'accessorials' => 'nullable|numeric|min:0',
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

        $result = $this->loadBoardService->updateLoad($id, $validated, auth('admin')->id());
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

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:' . implode(',', UrbanGoodzLoadBoardLoad::STATUSES),
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $this->loadBoardService->updateStatus(
            $id,
            $request->status,
            auth('admin')->id(),
            'admin',
            $request->notes
        );

        if (!$result) {
            Toastr::error(translate('Invalid status transition'));
            return redirect()->back();
        }

        Toastr::success(translate('Load status updated'));
        return redirect()->route('admin.urban-goodz.load-board.show', $id);
    }

    public function assignDriver(Request $request, $id)
    {
        $request->validate([
            'driver_id' => 'required|integer|exists:delivery_men,id',
        ]);

        $result = $this->loadBoardService->acceptLoad($id, $request->driver_id, auth('admin')->id());
        if (!$result) {
            Toastr::error(translate('Unable to assign driver'));
            return redirect()->back();
        }

        Toastr::success(translate('Driver assigned successfully'));
        return redirect()->route('admin.urban-goodz.load-board.show', $id);
    }

    public function reassignDriver(Request $request, $id)
    {
        $request->validate([
            'driver_id' => 'required|integer|exists:delivery_men,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->loadBoardService->reassignLoad(
            $id,
            $request->driver_id,
            auth('admin')->id(),
            $request->reason
        );

        if (!$result) {
            Toastr::error(translate('Unable to reassign driver'));
            return redirect()->back();
        }

        Toastr::success(translate('Driver reassigned successfully'));
        return redirect()->route('admin.urban-goodz.load-board.show', $id);
    }

    public function review(Request $request, $id)
    {
        $request->validate([
            'decision' => 'required|string|in:approve,reject,send_to_board',
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $this->loadBoardService->reviewLoad(
            $id,
            $request->decision,
            auth('admin')->id(),
            $request->notes
        );

        if (!$result) {
            Toastr::error(translate('Unable to review load'));
            return redirect()->back();
        }

        Toastr::success(translate('Load review completed'));
        return redirect()->route('admin.urban-goodz.load-board.show', $id);
    }
}
