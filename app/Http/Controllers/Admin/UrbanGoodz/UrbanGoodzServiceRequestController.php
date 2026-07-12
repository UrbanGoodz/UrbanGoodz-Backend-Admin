<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\UrbanGoodzServiceProvider;
use Illuminate\Http\Request;

class UrbanGoodzServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzServiceRequest::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', '%' . $request->search . '%')
                    ->orWhere('customer_email', 'like', '%' . $request->search . '%')
                    ->orWhere('service_type', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.service-requests.index', compact('requests'));
    }

    public function show($id)
    {
        $serviceRequest = UrbanGoodzServiceRequest::findOrFail($id);

        return view('admin-views.urban-goodz.service-requests.show', compact('serviceRequest'));
    }

    public function create()
    {
        return view('admin-views.urban-goodz.service-requests.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'service_type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:50'],
            'assigned_vendor_id' => ['nullable', 'integer'],
            'admin_notes' => ['nullable', 'string'],
            'preferred_dates' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $data['preferred_dates'] = $data['preferred_dates'] ? array_map('trim', explode("\n", $data['preferred_dates'])) : [];

        UrbanGoodzServiceRequest::create($data);

        return redirect()->route('admin.urban-goodz.service-requests.index')
            ->with('success', translate('Service request created successfully.'));
    }

    public function edit($id)
    {
        $serviceRequest = UrbanGoodzServiceRequest::findOrFail($id);

        return view('admin-views.urban-goodz.service-requests.edit', compact('serviceRequest'));
    }

    public function update(Request $request, $id)
    {
        $serviceRequest = UrbanGoodzServiceRequest::findOrFail($id);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'service_type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:50'],
            'assigned_vendor_id' => ['nullable', 'integer'],
            'admin_notes' => ['nullable', 'string'],
            'preferred_dates' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $data['preferred_dates'] = $data['preferred_dates'] ? array_map('trim', explode("\n", $data['preferred_dates'])) : [];

        $serviceRequest->update($data);

        return redirect()->route('admin.urban-goodz.service-requests.index')
            ->with('success', translate('Service request updated successfully.'));
    }

    public function destroy($id)
    {
        $serviceRequest = UrbanGoodzServiceRequest::findOrFail($id);
        $serviceRequest->delete();

        return back()->with('success', translate('Service request deleted successfully.'));
    }

    public function status($id, $status)
    {
        $serviceRequest = UrbanGoodzServiceRequest::findOrFail($id);
        $serviceRequest->status = $status;
        $serviceRequest->save();

        return back()->with('success', translate('Service request status updated successfully.'));
    }
}
