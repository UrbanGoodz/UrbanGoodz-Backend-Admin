@extends('business.layouts.dispatcher')
@section('title', translate('Load Detail'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('business.dispatcher.loads') }}" class="text-muted"><i class="tio-arrow-left"></i> {{ translate('Back to Loads') }}</a>
        <h1 class="page-header-title mt-1">{{ $load->load_number ?? 'Load #'.$load->id }}</h1>
    </div>
    <div class="d-flex gap-2">
        @php($badgeClass = match($load->status) { 'available' => 'success', 'assigned' => 'warning', 'in_transit' => 'info', 'delivered' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
        <span class="badge badge-soft-{{ $badgeClass }}" style="font-size:0.95rem;">{{ $load->status_label }}</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Load Details') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">{{ translate('Origin') }}</h6>
                        <p class="mb-0 fw-bold">{{ $load->origin_name ?: $load->origin_full }}</p>
                        <p class="mb-0 text-muted small">{{ $load->origin_city }}, {{ $load->origin_state }} {{ $load->origin_zip }}</p>
                        @if($load->origin_ready_at)<p class="mb-0 small">{{ translate('Ready') }}: {{ $load->origin_ready_at->format('M d, Y g:i A') }}</p>@endif
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">{{ translate('Destination') }}</h6>
                        <p class="mb-0 fw-bold">{{ $load->destination_name ?: $load->destination_full }}</p>
                        <p class="mb-0 text-muted small">{{ $load->destination_city }}, {{ $load->destination_state }} {{ $load->destination_zip }}</p>
                        @if($load->destination_due_at)<p class="mb-0 small">{{ translate('Due') }}: {{ $load->destination_due_at->format('M d, Y g:i A') }}</p>@endif
                    </div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">{{ translate('Distance') }}</small><br><strong>{{ $load->distance_miles ? number_format($load->distance_miles, 0).' mi' : '-' }}</strong></div>
                    <div class="col-md-3"><small class="text-muted">{{ translate('Payout') }}</small><br><strong>${{ number_format($load->payout_amount, 2) }}</strong></div>
                    <div class="col-md-3"><small class="text-muted">{{ translate('Rate/Mile') }}</small><br><strong>${{ $load->rate_per_mile ? number_format($load->rate_per_mile, 2) : '-' }}</strong></div>
                    <div class="col-md-3"><small class="text-muted">{{ translate('Load Type') }}</small><br><strong>{{ strtoupper($load->load_type ?? '-') }}</strong></div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">{{ translate('Equipment') }}</small><br><strong>{{ ucfirst($load->equipment_type ?? '-') }}</strong></div>
                    <div class="col-md-3"><small class="text-muted">{{ translate('Weight') }}</small><br><strong>{{ $load->weight_lbs ? number_format($load->weight_lbs, 0).' lbs' : '-' }}</strong></div>
                    <div class="col-md-3"><small class="text-muted">{{ translate('Pieces') }}</small><br><strong>{{ $load->pieces ?? '-' }}</strong></div>
                    <div class="col-md-3"><small class="text-muted">{{ translate('Length') }}</small><br><strong>{{ $load->length_ft ? $load->length_ft.' ft' : '-' }}</strong></div>
                </div>
                @if($load->commodity_description || $load->special_requirements || $load->notes)
                <hr>
                @if($load->commodity_description)<p><strong>{{ translate('Commodity') }}:</strong> {{ $load->commodity_description }}</p>@endif
                @if($load->special_requirements)<p><strong>{{ translate('Special Requirements') }}:</strong> {{ $load->special_requirements }}</p>@endif
                @if($load->notes)<p><strong>{{ translate('Notes') }}:</strong> {{ $load->notes }}</p>@endif
                @endif
                <hr>
                <div class="d-flex flex-wrap gap-2">
                    @if($load->is_hazmat)<span class="badge badge-danger">{{ translate('HAZMAT') }}</span>@endif
                    @if($load->is_temperature_controlled)<span class="badge badge-info">{{ translate('Temp Controlled') }} ({{ $load->temperature_min_f }}-{{ $load->temperature_max_f }}F)</span>@endif
                    @if($load->requires_liftgate)<span class="badge badge-warning">{{ translate('Liftgate') }}</span>@endif
                    @if($load->requires_pallet_jack)<span class="badge badge-warning">{{ translate('Pallet Jack') }}</span>@endif
                    @if($load->is_team_load)<span class="badge badge-primary">{{ translate('Team Load') }}</span>@endif
                    @if($load->is_expedited)<span class="badge badge-danger">{{ translate('Expedited') }}</span>@endif
                </div>
            </div>
        </div>

        @if($load->shipper_name || $load->consignee_name)
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Contacts') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    @if($load->shipper_name)
                    <div class="col-md-6">
                        <h6 class="text-muted">{{ translate('Shipper') }}</h6>
                        <p class="mb-0">{{ $load->shipper_name }}</p>
                        @if($load->shipper_phone)<p class="mb-0 small text-muted">{{ $load->shipper_phone }}</p>@endif
                    </div>
                    @endif
                    @if($load->consignee_name)
                    <div class="col-md-6">
                        <h6 class="text-muted">{{ translate('Consignee') }}</h6>
                        <p class="mb-0">{{ $load->consignee_name }}</p>
                        @if($load->consignee_phone)<p class="mb-0 small text-muted">{{ $load->consignee_phone }}</p>@endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        @if(in_array($load->status, ['available']))
        @if(auth('business')->user()->hasDispatchPermission('dispatch_drivers_assign'))
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Assign Driver') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('business.dispatcher.loads.assign-driver', $load->id) }}">
                    @csrf
                    <div class="form-group mb-3">
                        <select name="driver_id" class="form-control" required>
                            <option value="">{{ translate('Select Driver') }}</option>
                            @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->f_name }} {{ $driver->l_name }} ({{ $driver->vehicle_type ?? 'N/A' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn--primary w-100"><i class="tio-user-check"></i> {{ translate('Assign Driver') }}</button>
                </form>
            </div>
        </div>
        @endif
        @endif

        @if(in_array($load->status, ['assigned', 'in_transit', 'picked_up']))
        @if(auth('business')->user()->hasDispatchPermission('dispatch_status_update'))
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Update Status') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('business.dispatcher.loads.status', $load->id) }}">
                    @csrf
                    @method('PATCH')
                    <div class="form-group mb-3">
                        <select name="status" class="form-control" required>
                            @if($load->status === 'assigned')
                            <option value="in_transit">{{ translate('In Transit') }}</option>
                            @endif
                            @if($load->status === 'in_transit')
                            <option value="picked_up">{{ translate('Picked Up') }}</option>
                            @endif
                            @if(in_array($load->status, ['assigned', 'in_transit', 'picked_up']))
                            <option value="delivered">{{ translate('Delivered') }}</option>
                            <option value="cancelled">{{ translate('Cancelled') }}</option>
                            @endif
                        </select>
                    </div>
                    <button type="submit" class="btn btn--primary w-100"><i class="tio-refresh"></i> {{ translate('Update Status') }}</button>
                </form>
            </div>
        </div>
        @endif
        @endif

        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Assignment') }}</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ translate('Driver') }}:</strong> {{ $load->assignedDriver ? $load->assignedDriver->f_name.' '.$load->assignedDriver->l_name : translate('Unassigned') }}</p>
                <p class="mb-1"><strong>{{ translate('Assigned At') }}:</strong> {{ $load->assigned_at ? $load->assigned_at->format('M d, Y g:i A') : '-' }}</p>
                <p class="mb-1"><strong>{{ translate('Picked Up') }}:</strong> {{ $load->picked_up_at ? $load->picked_up_at->format('M d, Y g:i A') : '-' }}</p>
                <p class="mb-0"><strong>{{ translate('Delivered') }}:</strong> {{ $load->delivered_at ? $load->delivered_at->format('M d, Y g:i A') : '-' }}</p>
            </div>
        </div>

        @if($commissions->isNotEmpty())
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ translate('Commissions') }}</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ translate('Rate') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach($commissions as $c)
                        <tr>
                            <td>{{ $c->commission_rate }}%</td>
                            <td>${{ number_format($c->commission_amount, 2) }}</td>
                            <td><span class="badge badge-soft-{{ $c->status === 'paid' ? 'success' : ($c->status === 'approved' ? 'info' : 'warning') }}">{{ ucfirst($c->status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
