<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzAppointment;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class UrbanGoodzAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzAppointment::with(['serviceRequest', 'serviceProvider']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('notes', 'like', '%' . $request->search . '%')
                    ->orWhere('status', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderByDesc('scheduled_at')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.appointments.index', compact('appointments'));
    }

    public function show($id)
    {
        $appointment = UrbanGoodzAppointment::with(['serviceRequest', 'serviceProvider'])->findOrFail($id);

        return view('admin-views.urban-goodz.appointments.show', compact('appointment'));
    }

    public function create()
    {
        $serviceRequests = UrbanGoodzServiceRequest::orderBy('customer_name')->get();
        $serviceProviders = UrbanGoodzServiceProvider::orderBy('business_name')->get();

        return view('admin-views.urban-goodz.appointments.create', compact('serviceRequests', 'serviceProviders'));
    }

    public function store(Request $request)
    {
        if (!Helpers::module_permission_check('urban_goodz_appointment_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $data = $request->validate([
            'service_provider_id' => ['nullable', 'integer', 'exists:urban_goodz_service_providers,id'],
            'scheduled_at' => ['required', 'date'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        UrbanGoodzAppointment::create($data);

        return redirect()->route('admin.urban-goodz.appointments.index')
            ->with('success', translate('Appointment created successfully.'));
    }

    public function edit($id)
    {
        $appointment = UrbanGoodzAppointment::findOrFail($id);
        $serviceRequests = UrbanGoodzServiceRequest::orderBy('customer_name')->get();
        $serviceProviders = UrbanGoodzServiceProvider::orderBy('business_name')->get();

        return view('admin-views.urban-goodz.appointments.edit', compact('appointment', 'serviceRequests', 'serviceProviders'));
    }

    public function update(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_appointment_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $appointment = UrbanGoodzAppointment::findOrFail($id);

        $data = $request->validate([
            'service_request_id' => ['nullable', 'integer', 'exists:urban_goodz_service_requests,id'],
            'service_provider_id' => ['nullable', 'integer', 'exists:urban_goodz_service_providers,id'],
            'scheduled_at' => ['required', 'date'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment->update($data);

        return redirect()->route('admin.urban-goodz.appointments.index')
            ->with('success', translate('Appointment updated successfully.'));
    }

    public function destroy($id)
    {
        if (!Helpers::module_permission_check('urban_goodz_appointment_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $appointment = UrbanGoodzAppointment::findOrFail($id);
        $appointment->delete();

        return back()->with('success', translate('Appointment deleted successfully.'));
    }

    public function status($id, $status)
    {
        $appointment = UrbanGoodzAppointment::findOrFail($id);
        $appointment->status = $status;
        $appointment->save();

        return back()->with('success', translate('Appointment status updated successfully.'));
    }
}
