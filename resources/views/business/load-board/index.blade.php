@extends('business.layouts.app')
@section('title', translate('Business Load Board'))
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="page-header-title">{{ translate('Business Load Board') }}</h1>
        <p class="text-muted mb-0" style="color: #6c757d !important;">{{ translate('Manage and track your load delivery requests') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('business.load-board.create') }}" class="btn btn--primary" style="background-color: var(--ug-primary); color: #fff;">
            <i class="tio-add"></i> {{ translate('Create Load Request') }}
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #6c757d;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Total Requests') }}</h6>
                <h3>{{ $stats['total'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #28a745;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Available (Unassigned)') }}</h6>
                <h3>{{ $stats['available'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #17a2b8;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Active (In Transit)') }}</h6>
                <h3>{{ $stats['active'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #007bff;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Delivered') }}</h6>
                <h3>{{ $stats['completed'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #dc3545;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Cancelled') }}</h6>
                <h3>{{ $stats['cancelled'] }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('business.load-board.index') }}" class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="{{ translate('Search by Load #, Origin, Destination...') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">{{ translate('All Statuses') }}</option>
                    <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>{{ translate('Available') }}</option>
                    <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>{{ translate('Assigned') }}</option>
                    <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>{{ translate('In Transit') }}</option>
                    <option value="picked_up" {{ request('status') === 'picked_up' ? 'selected' : '' }}>{{ translate('Picked Up') }}</option>
                    <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>{{ translate('Delivered') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="origin_state" class="form-control" placeholder="{{ translate('Origin State (e.g. TX)') }}" value="{{ request('origin_state') }}" maxlength="2">
            </div>
            <div class="col-md-2">
                <input type="text" name="destination_state" class="form-control" placeholder="{{ translate('Dest State (e.g. CA)') }}" value="{{ request('destination_state') }}" maxlength="2">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn--primary flex-grow-1" style="background-color: var(--ug-primary); color: #fff;">
                    {{ translate('Filter') }}
                </button>
                <a href="{{ route('business.load-board.index') }}" class="btn btn-secondary">
                    {{ translate('Reset') }}
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Load #') }}</th>
                        <th>{{ translate('Created At') }}</th>
                        <th>{{ translate('Origin') }}</th>
                        <th>{{ translate('Destination') }}</th>
                        <th class="text-end">{{ translate('Distance') }}</th>
                        <th class="text-end">{{ translate('Payout') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th>{{ translate('Driver') }}</th>
                        <th class="text-center">{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loads as $load)
                    <tr>
                        <td>
                            <a href="{{ route('business.load-board.show', $load->id) }}" class="fw-bold">
                                {{ $load->load_number ?? '#'.$load->id }}
                            </a>
                        </td>
                        <td>{{ $load->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $load->origin_full }}</td>
                        <td>{{ $load->destination_full }}</td>
                        <td class="text-end">{{ $load->distance_miles ? number_format($load->distance_miles, 1).' mi' : '-' }}</td>
                        <td class="text-end">${{ number_format($load->payout_amount, 2) }}</td>
                        <td class="text-center">
                            @php($badgeClass = match($load->status) { 'available' => 'success', 'assigned' => 'warning', 'in_transit' => 'info', 'delivered' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-soft-{{ $badgeClass }}">{{ $load->status_label }}</span>
                        </td>
                        <td>{{ $load->assignedDriver ? $load->assignedDriver->f_name.' '.$load->assignedDriver->l_name : '-' }}</td>
                        <td class="text-center">
                            <a href="{{ route('business.load-board.show', $load->id) }}" class="btn btn-outline-info btn-xs p-1" title="{{ translate('View Details') }}">
                                <i class="tio-visible"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">{{ translate('No load requests found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($loads->hasPages())
    <div class="card-footer py-2">
        {!! $loads->withQueryString()->links() !!}
    </div>
    @endif
</div>
@endsection
