@extends('layouts.admin.master')
@section('title', 'Medical Courier Job — ' . $job->job_number)

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.medical-courier.index') }}">Medical Courier</a></li>
                        <li class="breadcrumb-item active">{{ $job->job_number }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">Job {{ $job->job_number }}</h1>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.urban-goodz.medical-courier.edit', $job->id) }}" class="btn btn-ghost-secondary"><i class="tio-edit mr-1"></i> Edit</a>
                <form method="POST" action="{{ route('admin.urban-goodz.medical-courier.destroy', $job->id) }}" class="d-inline" onsubmit="return confirm('Delete this job?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-ghost-danger"><i class="tio-delete mr-1"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        <!-- Job Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title">Job Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Status</span>
                            <span class="badge badge-soft-{{ $job->status === 'delivered' ? 'success' : ($job->status === 'cancelled' ? 'danger' : 'info') }} fs-sm">{{ $job->status_label }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Priority</span>
                            <span class="badge badge-soft-{{ $job->priority === 'urgent' ? 'danger' : ($job->priority === 'high' ? 'warning' : 'secondary') }} fs-sm">{{ $job->priority_label }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Specimen Type</span>
                            <span>{{ $job->specimen_type ?? '-' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Specimen Count</span>
                            <span>{{ $job->specimen_count ?? 1 }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Payout</span>
                            <span class="fw-bold">{{ $job->payout_amount ? '$' . number_format($job->payout_amount, 2) : '-' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Distance</span>
                            <span>{{ $job->distance_miles ? number_format($job->distance_miles, 1) . ' mi' : '-' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Refrigerated</span>
                            <span>{{ $job->requires_refrigeration ? 'Yes' : 'No' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block text-muted fs-sm">Biohazard</span>
                            <span>{{ $job->is_biological_hazard ? 'Yes' : 'No' }}</span>
                        </div>
                        @if($job->temperature_min_f || $job->temperature_max_f)
                        <div class="col-md-6">
                            <span class="d-block text-muted fs-sm">Temperature Range</span>
                            <span>{{ $job->temperature_min_f ?? '?' }}°F — {{ $job->temperature_max_f ?? '?' }}°F</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Locations -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title">Locations</h5></div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="tio-arrow-circle-up mr-1"></i> Pickup</h6>
                            <p class="mb-1 fw-semibold">{{ $job->pickup_facility_name ?? $job->pickup_location }}</p>
                            @if($job->pickup_facility_name && $job->pickup_location !== $job->pickup_facility_name)<p class="mb-1 fs-sm text-muted">{{ $job->pickup_location }}</p>@endif
                            @if($job->pickup_contact_name)<p class="mb-0 fs-sm">Contact: {{ $job->pickup_contact_name }} {{ $job->pickup_contact_phone ? '('.$job->pickup_contact_phone.')' : '' }}</p>@endif
                            @if($job->pickup_window_start || $job->pickup_window_end)
                            <p class="mb-0 fs-sm text-muted">Window: {{ $job->pickup_window_start?->format('M d, g:ia') ?? '?' }} — {{ $job->pickup_window_end?->format('g:ia') ?? '?' }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-danger"><i class="tio-arrow-circle-down mr-1"></i> Delivery</h6>
                            <p class="mb-1 fw-semibold">{{ $job->delivery_facility_name ?? $job->delivery_location }}</p>
                            @if($job->delivery_facility_name && $job->delivery_location !== $job->delivery_facility_name)<p class="mb-1 fs-sm text-muted">{{ $job->delivery_location }}</p>@endif
                            @if($job->delivery_contact_name)<p class="mb-0 fs-sm">Contact: {{ $job->delivery_contact_name }} {{ $job->delivery_contact_phone ? '('.$job->delivery_contact_phone.')' : '' }}</p>@endif
                            @if($job->delivery_window_start || $job->delivery_window_end)
                            <p class="mb-0 fs-sm text-muted">Window: {{ $job->delivery_window_start?->format('M d, g:ia') ?? '?' }} — {{ $job->delivery_window_end?->format('g:ia') ?? '?' }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custody Chain -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title">Custody Chain</h5></div>
                <div class="card-body">
                    @if($job->custodyLogs->isEmpty())
                    <p class="text-muted mb-0">No custody records yet.</p>
                    @else
                    <div class="timeline timeline-xs">
                        @foreach($job->custodyLogs->sortByDesc('logged_at') as $log)
                        <div class="timeline-item">
                            <div class="timeline-indicator">
                                <span class="btn btn-sm btn-icon btn-soft-{{ $log->action === 'delivered' ? 'success' : 'info' }}">
                                    <i class="tio-{{ $log->action === 'delivered' ? 'check' : 'time' }}"></i>
                                </span>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                    <small class="text-muted">{{ $log->logged_at?->format('M d, g:ia') }}</small>
                                </div>
                                <div class="fs-sm text-muted">
                                    {{ $log->handler_name }} ({{ $log->handler_role ?? 'system' }})
                                    @if($log->notes) — {{ $log->notes }}@endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            @if($job->admin_notes)
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title">Admin Notes</h5></div>
                <div class="card-body"><p class="mb-0">{{ $job->admin_notes }}</p></div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Status Actions -->
            @if(!in_array($job->status, ['delivered', 'cancelled']))
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title">Actions</h5></div>
                <div class="card-body">
                    @if($job->status === 'pending')
                    <form method="POST" action="{{ route('admin.urban-goodz.medical-courier.assign-driver', $job->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Assign Driver (ID)</label>
                            <input type="number" name="driver_id" class="form-control" required min="1">
                        </div>
                        <button class="btn btn-primary w-100">Assign Driver</button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('admin.urban-goodz.medical-courier.update-status', $job->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ match($job->status) { 'assigned' => 'picked_up', 'picked_up' => 'in_transit', 'in_transit' => 'delivered', default => 'cancelled' } }}">
                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary w-100">Mark as {{ match($job->status) { 'assigned' => 'Picked Up', 'picked_up' => 'In Transit', 'in_transit' => 'Delivered', default => 'Cancel' } }}</button>
                    </form>
                    <hr>
                    <form method="POST" action="{{ route('admin.urban-goodz.medical-courier.update-status', $job->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-danger w-100" onclick="return confirm('Cancel this job?')">Cancel Job</button>
                    </form>
                    @endif
                </div>
            </div>
            @endif

            <!-- Driver -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title">Assigned Driver</h5></div>
                <div class="card-body">
                    @if($job->assignedDriver)
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-soft-primary"><i class="tio-user"></i></div>
                        <div class="ml-3">
                            <span class="d-block fw-semibold">{{ $job->assignedDriver->name }}</span>
                            <span class="d-block fs-sm text-muted">#{{ $job->assignedDriver->id }}</span>
                        </div>
                    </div>
                    @else
                    <p class="text-muted mb-0">No driver assigned</p>
                    @endif
                </div>
            </div>

            <!-- Timestamps -->
            <div class="card">
                <div class="card-header"><h5 class="card-title">Timestamps</h5></div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-muted">Created:</span> {{ $job->created_at->format('M d, g:ia') }}</div>
                    @if($job->assigned_at)<div class="mb-2"><span class="text-muted">Assigned:</span> {{ $job->assigned_at->format('M d, g:ia') }}</div>@endif
                    @if($job->picked_up_at)<div class="mb-2"><span class="text-muted">Picked Up:</span> {{ $job->picked_up_at->format('M d, g:ia') }}</div>@endif
                    @if($job->delivered_at)<div class="mb-2"><span class="text-muted">Delivered:</span> {{ $job->delivered_at->format('M d, g:ia') }}</div>@endif
                    <div class="mb-0"><span class="text-muted">Updated:</span> {{ $job->updated_at->format('M d, g:ia') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
