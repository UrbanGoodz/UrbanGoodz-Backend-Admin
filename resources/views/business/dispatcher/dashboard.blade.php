@extends('business.layouts.dispatcher')
@section('title', translate('Dispatcher Dashboard'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Dispatcher Dashboard') }}</h1>
    <div class="d-flex gap-2">
        @if(auth('business')->user()->hasDispatchPermission('dispatch_loads_view'))
        <a href="{{ route('dispatcher.loads') }}" class="btn btn--primary">
            <i class="tio-truck"></i> {{ translate('View Loads') }}
        </a>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card stat-available">
            <div class="card-body text-center py-3">
                <div class="stat-number" style="color:#28a745;font-size:1.8rem;font-weight:700;">{{ $stats['available_loads'] }}</div>
                <small class="text-muted">{{ translate('Available Loads') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card stat-assigned">
            <div class="card-body text-center py-3">
                <div class="stat-number" style="color:#ffc107;font-size:1.8rem;font-weight:700;">{{ $stats['assigned_loads'] }}</div>
                <small class="text-muted">{{ translate('Assigned') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card stat-transit">
            <div class="card-body text-center py-3">
                <div class="stat-number" style="color:#17a2b8;font-size:1.8rem;font-weight:700;">{{ $stats['in_transit_loads'] }}</div>
                <small class="text-muted">{{ translate('In Transit') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card stat-commission">
            <div class="card-body text-center py-3">
                <div class="stat-number" style="color:#e94560;font-size:1.8rem;font-weight:700;">${{ number_format($stats['pending_commissions'], 2) }}</div>
                <small class="text-muted">{{ translate('Pending Commissions') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
        <div class="card">
            <div class="card-body text-center py-3">
                <div style="font-size:1.4rem;font-weight:700;">{{ $stats['delivered_loads_30d'] }}</div>
                <small class="text-muted">{{ translate('Delivered (30d)') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card">
            <div class="card-body text-center py-3">
                <div style="font-size:1.4rem;font-weight:700;">${{ number_format($stats['total_payout_30d'], 2) }}</div>
                <small class="text-muted">{{ translate('Total Payout (30d)') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center py-3">
                <div style="font-size:1.4rem;font-weight:700;">${{ number_format($stats['paid_commissions_30d'], 2) }}</div>
                <small class="text-muted">{{ translate('Paid Commissions (30d)') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ translate('Recent Loads') }}</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Load #') }}</th>
                        <th>{{ translate('Origin') }}</th>
                        <th>{{ translate('Destination') }}</th>
                        <th class="text-end">{{ translate('Payout') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th>{{ translate('Driver') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLoads as $load)
                    <tr>
                        <td>
                            <a href="{{ route('dispatcher.loads.show', $load->id) }}" class="fw-bold">
                                {{ $load->load_number ?? '#'.$load->id }}
                            </a>
                        </td>
                        <td>{{ $load->origin_full }}</td>
                        <td>{{ $load->destination_full }}</td>
                        <td class="text-end">${{ number_format($load->payout_amount, 2) }}</td>
                        <td class="text-center">
                            @php($badgeClass = match($load->status) { 'available' => 'success', 'assigned' => 'warning', 'in_transit' => 'info', 'delivered' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-soft-{{ $badgeClass }}">{{ $load->status_label }}</span>
                        </td>
                        <td>{{ $load->assignedDriver ? $load->assignedDriver->f_name.' '.$load->assignedDriver->l_name : '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ translate('No loads yet') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
