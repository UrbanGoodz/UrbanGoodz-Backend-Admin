<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzBusinessClientLocation;
use App\Models\UrbanGoodzBusinessClientDocument;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\DeliveryMan;
use App\Models\Admin;
use App\Services\UrbanGoodzDriverDispatchNotificationService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UrbanGoodzBusinessClientController extends Controller
{
    // ===== BUSINESS CLIENTS =====

    public function index()
    {
        $clients = UrbanGoodzBusinessClient::withCount(['users', 'locations', 'jobs'])->latest()->paginate(25);
        return view('admin-views.urban-goodz.business-clients.index', compact('clients'));
    }

    public function create()
    {
        $statuses = UrbanGoodzBusinessClient::STATUSES;
        $billingTerms = UrbanGoodzBusinessClient::BILLING_TERMS;
        $paymentMethodStatuses = UrbanGoodzBusinessClient::PAYMENT_METHOD_STATUSES;
        return view('admin-views.urban-goodz.business-clients.create', compact('statuses', 'billingTerms', 'paymentMethodStatuses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:urban_goodz_business_clients,email'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(UrbanGoodzBusinessClient::STATUSES)],
            'notes' => ['nullable', 'string'],
            'billing_terms' => ['nullable', Rule::in(UrbanGoodzBusinessClient::BILLING_TERMS)],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_method_status' => ['nullable', Rule::in(UrbanGoodzBusinessClient::PAYMENT_METHOD_STATUSES)],
        ]);

        if ($data['status'] === 'approved') {
            $data['approved_by'] = auth('admin')->id();
            $data['approved_at'] = now();
        }

        UrbanGoodzBusinessClient::create($data);

        Toastr::success(translate('Business client created successfully'));
        return redirect()->route('admin.urban-goodz.business-clients.index');
    }

    public function show($id)
    {
        $client = UrbanGoodzBusinessClient::with(['users', 'locations', 'documents', 'jobs'])->findOrFail($id);
        $drivers = DeliveryMan::where('application_status', 'approved')->where('active', 1)->get();
        $statuses = UrbanGoodzBusinessClient::STATUSES;
        return view('admin-views.urban-goodz.business-clients.show', compact('client', 'drivers', 'statuses'));
    }

    public function edit($id)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);
        $statuses = UrbanGoodzBusinessClient::STATUSES;
        $billingTerms = UrbanGoodzBusinessClient::BILLING_TERMS;
        $paymentMethodStatuses = UrbanGoodzBusinessClient::PAYMENT_METHOD_STATUSES;
        return view('admin-views.urban-goodz.business-clients.edit', compact('client', 'statuses', 'billingTerms', 'paymentMethodStatuses'));
    }

    public function update($id, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('urban_goodz_business_clients', 'email')->ignore($client->id)],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(UrbanGoodzBusinessClient::STATUSES)],
            'notes' => ['nullable', 'string'],
            'billing_terms' => ['nullable', Rule::in(UrbanGoodzBusinessClient::BILLING_TERMS)],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_method_status' => ['nullable', Rule::in(UrbanGoodzBusinessClient::PAYMENT_METHOD_STATUSES)],
        ]);

        if ($data['status'] === 'approved' && !$client->approved_at) {
            $data['approved_by'] = auth('admin')->id();
            $data['approved_at'] = now();
        }

        $client->update($data);

        Toastr::success(translate('Business client updated successfully'));
        return redirect()->route('admin.urban-goodz.business-clients.show', $client->id);
    }

    public function approve($id, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);
        $client->status = 'approved';
        $client->approved_by = auth('admin')->id();
        $client->approved_at = now();
        $client->notes = $request->notes ?? $client->notes;
        $client->save();

        Toastr::success(translate('Business client approved'));
        return back();
    }

    public function suspend($id)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);
        $client->status = 'suspended';
        $client->save();

        Toastr::success(translate('Business client suspended'));
        return back();
    }

    public function reactivate($id)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);
        $client->status = 'approved';
        $client->save();

        Toastr::success(translate('Business client reactivated'));
        return back();
    }

    public function destroy($id)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);
        $client->delete();

        Toastr::success(translate('Business client removed'));
        return redirect()->route('admin.urban-goodz.business-clients.index');
    }

    // ===== BUSINESS CLIENT USERS =====

    public function users($clientId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $users = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)->latest()->paginate(25);
        return view('admin-views.urban-goodz.business-clients.users.index', compact('client', 'users'));
    }

    public function userCreate($clientId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $roles = UrbanGoodzBusinessClientUser::ROLES;
        $permissions = UrbanGoodzBusinessClientUser::PERMISSIONS;
        return view('admin-views.urban-goodz.business-clients.users.create', compact('client', 'roles', 'permissions'));
    }

    public function userStore($clientId, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('urban_goodz_business_client_users', 'email')->where(function ($q) use ($clientId) {
                $q->where('business_client_id', $clientId);
            })],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(UrbanGoodzBusinessClientUser::ROLES)],
            'permissions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(UrbanGoodzBusinessClientUser::STATUSES)],
        ]);

        $data['business_client_id'] = $client->id;
        $data['password'] = Hash::make($data['password']);
        $data['permissions'] = $data['permissions'] ?? [];
        $data['is_active'] = $request->boolean('is_active', true);

        UrbanGoodzBusinessClientUser::create($data);

        Toastr::success(translate('User created successfully'));
        return redirect()->route('admin.urban-goodz.business-clients.users.index', $client->id);
    }

    public function userEdit($clientId, $userId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $user = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)->findOrFail($userId);
        $roles = UrbanGoodzBusinessClientUser::ROLES;
        $permissions = UrbanGoodzBusinessClientUser::PERMISSIONS;
        return view('admin-views.urban-goodz.business-clients.users.edit', compact('client', 'user', 'roles', 'permissions'));
    }

    public function userUpdate($clientId, $userId, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $user = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)->findOrFail($userId);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('urban_goodz_business_client_users', 'email')->where(function ($q) use ($clientId) {
                $q->where('business_client_id', $clientId);
            })->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(UrbanGoodzBusinessClientUser::ROLES)],
            'permissions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(UrbanGoodzBusinessClientUser::STATUSES)],
        ]);

        if ($request->filled('new_password')) {
            $data['password'] = Hash::make($data['new_password']);
        }
        unset($data['new_password']);

        $data['permissions'] = $data['permissions'] ?? [];
        $data['is_active'] = $request->boolean('is_active', $user->is_active);
        $data['status'] = $data['status'] ?? $user->status;

        $user->update($data);

        Toastr::success(translate('User updated successfully'));
        return redirect()->route('admin.urban-goodz.business-clients.users.index', $client->id);
    }

    public function userDestroy($clientId, $userId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $user = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)->findOrFail($userId);
        $user->delete();

        Toastr::success(translate('User removed'));
        return back();
    }

    // ===== BUSINESS CLIENT LOCATIONS =====

    public function locations($clientId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)->latest()->paginate(25);
        return view('admin-views.urban-goodz.business-clients.locations.index', compact('client', 'locations'));
    }

    public function locationCreate($clientId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $types = UrbanGoodzBusinessClientLocation::TYPES;
        $statuses = UrbanGoodzBusinessClientLocation::STATUSES;
        return view('admin-views.urban-goodz.business-clients.locations.create', compact('client', 'types', 'statuses'));
    }

    public function locationStore($clientId, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(UrbanGoodzBusinessClientLocation::TYPES)],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'operating_hours' => ['nullable', 'string'],
            'pickup_instructions' => ['nullable', 'string'],
            'delivery_instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['business_client_id'] = $client->id;
        $data['is_active'] = $request->boolean('is_active', true);

        UrbanGoodzBusinessClientLocation::create($data);

        Toastr::success(translate('Location created successfully'));
        return redirect()->route('admin.urban-goodz.business-clients.locations.index', $client->id);
    }

    public function locationEdit($clientId, $locationId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $location = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)->findOrFail($locationId);
        $types = UrbanGoodzBusinessClientLocation::TYPES;
        $statuses = UrbanGoodzBusinessClientLocation::STATUSES;
        return view('admin-views.urban-goodz.business-clients.locations.edit', compact('client', 'location', 'types', 'statuses'));
    }

    public function locationUpdate($clientId, $locationId, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $location = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)->findOrFail($locationId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(UrbanGoodzBusinessClientLocation::TYPES)],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'operating_hours' => ['nullable', 'string'],
            'pickup_instructions' => ['nullable', 'string'],
            'delivery_instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', $location->is_active);

        $location->update($data);

        Toastr::success(translate('Location updated successfully'));
        return redirect()->route('admin.urban-goodz.business-clients.locations.index', $client->id);
    }

    public function locationDestroy($clientId, $locationId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $location = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)->findOrFail($locationId);
        $location->delete();

        Toastr::success(translate('Location removed'));
        return back();
    }

    // ===== BUSINESS CLIENT DOCUMENTS =====

    public function documents($clientId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $documents = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)->latest()->paginate(25);
        $types = UrbanGoodzBusinessClientDocument::TYPES;
        $statuses = UrbanGoodzBusinessClientDocument::STATUSES;
        return view('admin-views.urban-goodz.business-clients.documents.index', compact('client', 'documents', 'types', 'statuses'));
    }

    public function documentUpload($clientId, Request $request)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);

        $data = $request->validate([
            'document_type' => ['required', Rule::in(UrbanGoodzBusinessClientDocument::TYPES)],
            'document_name' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $path = $file->store('urban-goodz/documents/' . $client->id, 'public');

        UrbanGoodzBusinessClientDocument::create([
            'business_client_id' => $client->id,
            'uploaded_by' => auth('admin')->id(),
            'document_type' => $data['document_type'],
            'document_name' => $data['document_name'] ?? $originalName,
            'file_path' => $path,
            'file_type' => $mimeType,
            'file_size' => $fileSize,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        Toastr::success(translate('Document uploaded successfully'));
        return back();
    }

    public function documentDownload($clientId, $documentId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $document = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)->findOrFail($documentId);

        if (!Storage::disk('public')->exists($document->file_path)) {
            Toastr::error(translate('File not found'));
            return back();
        }

        return Storage::disk('public')->download($document->file_path, $document->document_name);
    }

    public function documentDestroy($clientId, $documentId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $document = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)->findOrFail($documentId);

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        Toastr::success(translate('Document removed'));
        return back();
    }

    // ===== BUSINESS CLIENT JOBS =====

    public function jobs($id)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($id);
        $jobs = UrbanGoodzBusinessClientJob::where('business_client_id', $id)->latest()->paginate(25);
        return view('admin-views.urban-goodz.business-clients.jobs', compact('client', 'jobs'));
    }

    public function jobShow($clientId, $jobId)
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);
        $job = UrbanGoodzBusinessClientJob::with(['pickupLocation', 'dropoffLocation', 'assignedDriver', 'creator'])->findOrFail($jobId);
        $drivers = DeliveryMan::where('application_status', 'approved')->where('active', 1)->get();
        return view('admin-views.urban-goodz.business-clients.job-show', compact('client', 'job', 'drivers'));
    }

    public function jobUpdateStatus($clientId, $jobId, Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(UrbanGoodzBusinessClientJob::STATUSES)],
        ]);

        $job = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)->findOrFail($jobId);
        $job->status = $data['status'];
        $job->reviewed_by = auth('admin')->id();
        $job->reviewed_at = now();

        if ($data['status'] === 'assigned' && $request->assigned_delivery_man_id) {
            $job->assigned_delivery_man_id = $request->assigned_delivery_man_id;
            $job->assigned_at = now();
        }

        if ($data['status'] === 'picked_up') {
            $job->picked_up_at = now();
        }

        if ($data['status'] === 'delivered') {
            $job->delivered_at = now();
        }

        $job->save();

        if ($job->assigned_delivery_man_id) {
            $notify = app(UrbanGoodzDriverDispatchNotificationService::class);
            if ($data['status'] === 'assigned') {
                $notify->notifyBusinessCourierAssigned($job);
            } else {
                $notify->notifyBusinessCourierUpdated($job);
            }
        }

        Toastr::success(translate('Job status updated'));
        return back();
    }

    public function jobAssignDriver($clientId, $jobId, Request $request)
    {
        $data = $request->validate([
            'assigned_delivery_man_id' => ['required', 'integer'],
        ]);

        $job = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)->findOrFail($jobId);
        $job->assigned_delivery_man_id = $data['assigned_delivery_man_id'];
        $job->assigned_at = now();

        if ($job->status === 'submitted' || $job->status === 'accepted' || $job->status === 'quote_accepted') {
            $job->status = 'assigned';
        }

        $job->save();

        if ($job->assigned_delivery_man_id) {
            app(UrbanGoodzDriverDispatchNotificationService::class)
                ->notifyBusinessCourierAssigned($job);
        }

        Toastr::success(translate('Driver assigned to job'));
        return back();
    }

    public function jobQuote($clientId, $jobId, Request $request)
    {
        $data = $request->validate([
            'quoted_amount' => ['required', 'numeric', 'min:0.01'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $job = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)->findOrFail($jobId);
        $job->quoted_amount = $data['quoted_amount'];
        $job->admin_notes = $data['admin_notes'] ?? $job->admin_notes;
        $job->reviewed_by = auth('admin')->id();
        $job->reviewed_at = now();

        if ($job->status === 'submitted' || $job->status === 'under_review') {
            $job->status = 'quoted';
        }

        $job->save();

        Toastr::success(translate('Job quoted successfully'));
        return back();
    }
}
