<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\UrbanGoodzMedicalCourierService;
use Illuminate\Http\Request;

class UrbanGoodzMedicalCourierController extends Controller
{
    private UrbanGoodzMedicalCourierService $service;

    public function __construct(UrbanGoodzMedicalCourierService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'status', 'specimen_type', 'priority', 'assigned_driver_id',
            'requires_refrigeration', 'is_biological_hazard', 'search',
        ]);
        $result = $this->service->listJobs($filters, $request->input('page', 1));
        $stats = $this->service->getStats();

        return view('admin-views.urban-goodz.medical-courier.index', array_merge($result, ['stats' => $stats]));
    }

    public function show(int $id)
    {
        $job = $this->service->getById($id);
        if (!$job) {
            return redirect()->route('admin.urban-goodz.medical-courier.index')
                ->with('error', 'Job not found');
        }

        return view('admin-views.urban-goodz.medical-courier.show', ['job' => $job]);
    }

    public function create()
    {
        return view('admin-views.urban-goodz.medical-courier.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pickup_location' => 'required|string|max:255',
            'pickup_facility_name' => 'nullable|string|max:255',
            'pickup_contact_name' => 'nullable|string|max:255',
            'pickup_contact_phone' => 'nullable|string|max:50',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'delivery_location' => 'required|string|max:255',
            'delivery_facility_name' => 'nullable|string|max:255',
            'delivery_contact_name' => 'nullable|string|max:255',
            'delivery_contact_phone' => 'nullable|string|max:50',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'distance_miles' => 'nullable|numeric|min:0',
            'payout_amount' => 'nullable|numeric|min:0',
            'payout_type' => 'nullable|string|in:flat,per_mile,per_specimen',
            'specimen_type' => 'nullable|string|max:255',
            'specimen_count' => 'nullable|integer|min:1',
            'requires_refrigeration' => 'nullable|boolean',
            'is_biological_hazard' => 'nullable|boolean',
            'temperature_min_f' => 'nullable|numeric',
            'temperature_max_f' => 'nullable|numeric',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'pickup_window_start' => 'nullable|date',
            'pickup_window_end' => 'nullable|date|after_or_equal:pickup_window_start',
            'delivery_window_start' => 'nullable|date',
            'delivery_window_end' => 'nullable|date|after_or_equal:delivery_window_start',
            'admin_notes' => 'nullable|string',
        ]);

        $job = $this->service->createJob($validated);

        return redirect()->route('admin.urban-goodz.medical-courier.show', $job->id)
            ->with('success', 'Medical courier job created: ' . $job->job_number);
    }

    public function edit(int $id)
    {
        $job = $this->service->getById($id);
        if (!$job) {
            return redirect()->route('admin.urban-goodz.medical-courier.index')
                ->with('error', 'Job not found');
        }

        return view('admin-views.urban-goodz.medical-courier.edit', ['job' => $job]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'pickup_location' => 'sometimes|required|string|max:255',
            'pickup_facility_name' => 'nullable|string|max:255',
            'pickup_contact_name' => 'nullable|string|max:255',
            'pickup_contact_phone' => 'nullable|string|max:50',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'delivery_location' => 'sometimes|required|string|max:255',
            'delivery_facility_name' => 'nullable|string|max:255',
            'delivery_contact_name' => 'nullable|string|max:255',
            'delivery_contact_phone' => 'nullable|string|max:50',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'distance_miles' => 'nullable|numeric|min:0',
            'payout_amount' => 'nullable|numeric|min:0',
            'payout_type' => 'nullable|string|in:flat,per_mile,per_specimen',
            'specimen_type' => 'nullable|string|max:255',
            'specimen_count' => 'nullable|integer|min:1',
            'requires_refrigeration' => 'nullable|boolean',
            'is_biological_hazard' => 'nullable|boolean',
            'temperature_min_f' => 'nullable|numeric',
            'temperature_max_f' => 'nullable|numeric',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'pickup_window_start' => 'nullable|date',
            'pickup_window_end' => 'nullable|date',
            'delivery_window_start' => 'nullable|date',
            'delivery_window_end' => 'nullable|date',
            'admin_notes' => 'nullable|string',
        ]);

        $job = $this->service->updateJob($id, $validated);
        if (!$job) {
            return redirect()->route('admin.urban-goodz.medical-courier.index')
                ->with('error', 'Job not found');
        }

        return redirect()->route('admin.urban-goodz.medical-courier.show', $job->id)
            ->with('success', 'Job updated successfully');
    }

    public function destroy(int $id)
    {
        $deleted = $this->service->deleteJob($id);
        if (!$deleted) {
            return redirect()->back()->with('error', 'Cannot delete job — it is already in progress or does not exist');
        }

        return redirect()->route('admin.urban-goodz.medical-courier.index')
            ->with('success', 'Job deleted successfully');
    }

    public function assignDriver(Request $request, int $id)
    {
        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:delivery_men,id',
        ]);

        $job = $this->service->assignDriver($id, $validated['driver_id']);
        if (!$job) {
            return redirect()->back()->with('error', 'Cannot assign driver — job not found, not pending, or driver lacks medical courier training');
        }

        return redirect()->route('admin.urban-goodz.medical-courier.show', $job->id)
            ->with('success', 'Driver assigned successfully');
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:assigned,picked_up,in_transit,delivered,cancelled',
            'notes' => 'nullable|string',
        ]);

        $job = $this->service->updateStatus($id, $validated['status'], null, $validated['notes'] ?? null);
        if (!$job) {
            return redirect()->back()->with('error', 'Invalid status transition or job not found');
        }

        return redirect()->route('admin.urban-goodz.medical-courier.show', $job->id)
            ->with('success', 'Status updated to: ' . $job->status_label);
    }
}
