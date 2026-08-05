@extends('layouts.admin.app')
@section('title', 'Medical Courier Jobs')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Home') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Medical Courier</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">Medical Courier Jobs</h1>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.urban-goodz.medical-courier.create') }}" class="btn btn-primary">
                    <i class="tio-add mr-1"></i> New Job
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-soft-primary"><i class="tio-clock-avatar"></i></div>
                        <div class="ml-3">
                            <span class="d-block fs-sm text-muted">Pending</span>
                            <span class="d-block fs-lg fw-bold">{{ $stats['total_pending'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-soft-info"><i class="tio-user-avatar"></i></div>
                        <div class="ml-3">
                            <span class="d-block fs-sm text-muted">Assigned</span>
                            <span class="d-block fs-lg fw-bold">{{ $stats['total_assigned'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-soft-warning"><i class="tio-directions"></i></div>
                        <div class="ml-3">
                            <span class="d-block fs-sm text-muted">In Transit</span>
                            <span class="d-block fs-lg fw-bold">{{ $stats['total_in_transit'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-soft-success"><i class="tio-checkmark-circle"></i></div>
                        <div class="ml-3">
                            <span class="d-block fs-sm text-muted">Delivered (30d)</span>
                            <span class="d-block fs-lg fw-bold">{{ $stats['total_delivered_30d'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.urban-goodz.medical-courier.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach(['pending','assigned','picked_up','in_transit','delivered','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Priority</label>
                        <select name="priority" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach(['urgent','high','normal','low'] as $p)
                            <option value="{{ $p }}" {{ request('priority') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Specimen Type</label>
                        <select name="specimen_type" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach(['Blood Samples','Urine Samples','Tissue','Pharmaceutical','Lab Specimens','Organ Transport'] as $st)
                            <option value="{{ $st }}" {{ request('specimen_type') === $st ? 'selected' : '' }}>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Job #, location..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="tio-search"></i></button>
                    </div>
                    <div class="col-md-1">
                        <a href="{{ route('admin.urban-goodz.medical-courier.index') }}" class="btn btn-sm btn-ghost-secondary w-100">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Jobs ({{ $meta['total'] ?? 0 }})</h5>
        </div>
        <div class="table-responsive datatable-custom">
            <table class="table table-hover table-nowrap">
                <thead>
                    <tr>
                        <th>Job #</th>
                        <th>Pickup</th>
                        <th>Delivery</th>
                        <th>Specimen</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Driver</th>
                        <th>Payout</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    <tr>
                        <td><a href="{{ route('admin.urban-goodz.medical-courier.show', $job->id) }}">{{ $job->job_number }}</a></td>
                        <td>{{ $job->pickup_facility_name ?? Str::limit($job->pickup_location, 30) }}</td>
                        <td>{{ $job->delivery_facility_name ?? Str::limit($job->delivery_location, 30) }}</td>
                        <td>{{ $job->specimen_type ?? '-' }}</td>
                        <td><span class="badge badge-soft-{{ $job->priority === 'urgent' ? 'danger' : ($job->priority === 'high' ? 'warning' : 'secondary') }}">{{ $job->priority_label }}</span></td>
                        <td><span class="badge badge-soft-{{ $job->status === 'delivered' ? 'success' : ($job->status === 'cancelled' ? 'danger' : 'info') }}">{{ $job->status_label }}</span></td>
                        <td>{{ $job->assignedDriver->name ?? '-' }}</td>
                        <td>{{ $job->payout_amount ? '$' . number_format($job->payout_amount, 2) : '-' }}</td>
                        <td>
                            <a href="{{ route('admin.urban-goodz.medical-courier.show', $job->id) }}" class="btn btn-sm btn-ghost-secondary" title="View"><i class="tio-visible"></i></a>
                            <a href="{{ route('admin.urban-goodz.medical-courier.edit', $job->id) }}" class="btn btn-sm btn-ghost-secondary" title="Edit"><i class="tio-edit"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No medical courier jobs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($meta['last_page']) && $meta['last_page'] > 1)
        <div class="card-footer d-flex justify-content-center">
            {{ $jobs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
