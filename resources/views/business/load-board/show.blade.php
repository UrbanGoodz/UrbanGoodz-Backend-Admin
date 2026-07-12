@extends('business.layouts.app')
@section('title', translate('Load Details') . ' - ' . ($load->load_number ?? '#'.$load->id))
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <a href="{{ route('business.load-board.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="tio-back-button"></i> {{ translate('Back to Load Board') }}
        </a>
        <h1 class="page-header-title">
            {{ translate('Load') }} {{ $load->load_number ?? '#'.$load->id }}
            @php($badgeClass = match($load->status) { 'available' => 'success', 'assigned' => 'warning', 'in_transit' => 'info', 'delivered' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
            <span class="badge badge-soft-{{ $badgeClass }} ms-2" style="font-size: 0.95rem;">{{ $load->status_label }}</span>
        </h1>
    </div>
    <div class="d-flex gap-2">
        @if(!in_array($load->status, ['delivered', 'cancelled']))
        <form action="{{ route('business.load-board.cancel', $load->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure you want to cancel this load request?') }}')">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="tio-clear"></i> {{ translate('Cancel Load Request') }}
            </button>
        </form>
        @endif
    </div>
</div>

<div class="row g-3">
    <!-- Left Column: Route, Stops and Details -->
    <div class="col-lg-8">
        <!-- Route Segment -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Route Segment') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 border-right">
                        <h6 class="text-muted"><i class="tio-poi" style="color: #28a745;"></i> {{ translate('PICKUP') }}</h6>
                        <h5 class="mb-1">{{ $load->origin_name }}</h5>
                        <p class="mb-1">{{ $load->origin_city }}, {{ $load->origin_state }} {{ $load->origin_zip }}</p>
                        @if($load->origin_ready_at)
                        <small class="text-muted d-block mt-2">
                            <strong>{{ translate('Scheduled:') }}</strong> {{ $load->origin_ready_at->format('Y-m-d H:i') }}
                        </small>
                        @endif
                        @if($load->shipper_name)
                        <small class="text-muted d-block">
                            <strong>{{ translate('Contact:') }}</strong> {{ $load->shipper_name }} {{ $load->shipper_phone ? '('.$load->shipper_phone.')' : '' }}
                        </small>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted"><i class="tio-poi" style="color: #007bff;"></i> {{ translate('DELIVERY') }}</h6>
                        <h5 class="mb-1">{{ $load->destination_name }}</h5>
                        <p class="mb-1">{{ $load->destination_city }}, {{ $load->destination_state }} {{ $load->destination_zip }}</p>
                        @if($load->destination_due_at)
                        <small class="text-muted d-block mt-2">
                            <strong>{{ translate('Due Window:') }}</strong> {{ $load->destination_due_at->format('Y-m-d H:i') }}
                        </small>
                        @endif
                        @if($load->consignee_name)
                        <small class="text-muted d-block">
                            <strong>{{ translate('Contact:') }}</strong> {{ $load->consignee_name }} {{ $load->consignee_phone ? '('.$load->consignee_phone.')' : '' }}
                        </small>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Specifications & Cargo -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Cargo & Equipment Requirements') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Commodity') }}</small>
                        <strong>{{ $load->commodity_description ?? translate('Not specified') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Load Type') }}</small>
                        <strong>{{ $load->load_type ?? '-' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Required Vehicle') }}</small>
                        <strong>{{ $load->equipment_type ?? '-' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Weight') }}</small>
                        <strong>{{ $load->weight_lbs ? number_format($load->weight_lbs, 1).' lbs' : '-' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Length') }}</small>
                        <strong>{{ $load->length_ft ? number_format($load->length_ft, 1).' ft' : '-' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Pieces') }}</small>
                        <strong>{{ $load->pieces ?? '-' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Hazmat') }}</small>
                        <strong>{{ $load->is_hazmat ? translate('YES') : translate('NO') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Requires Liftgate') }}</small>
                        <strong>{{ $load->requires_liftgate ? translate('YES') : translate('NO') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Expedited') }}</small>
                        <strong>{{ $load->is_expedited ? translate('YES') : translate('NO') }}</strong>
                    </div>
                </div>

                @if($load->special_requirements)
                <div class="mt-4">
                    <h6 class="text-muted mb-1">{{ translate('Special Requirements') }}</h6>
                    <div class="bg-light p-2 rounded" style="font-size: 0.9rem;">
                        {{ $load->special_requirements }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Assignment Details -->
    <div class="col-lg-4">
        <!-- Execution Panel -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Driver / Fulfillment') }}</h5>
            </div>
            <div class="card-body">
                @if($load->assignedDriver)
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="mb-0">{{ $load->assignedDriver->f_name }} {{ $load->assignedDriver->l_name }}</h6>
                        <small class="text-muted">{{ translate('Assigned Driver') }}</small>
                        @if($load->assigned_at)
                        <small class="text-muted d-block">{{ translate('Assigned At:') }} {{ $load->assigned_at->format('Y-m-d H:i') }}</small>
                        @endif
                    </div>
                </div>
                @else
                <div class="text-center py-3 text-muted">
                    <i class="tio-hourglass-loop" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">{{ translate('Waiting for driver assignment...') }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Pricing Panel -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Financials') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ translate('Payout rate:') }}</span>
                    <strong>${{ number_format($load->payout_amount, 2) }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ translate('Pricing type:') }}</span>
                    <strong>{{ ucfirst($load->payout_type ?? 'flat') }}</strong>
                </div>
                @if($load->rate_per_mile)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ translate('Rate per mile:') }}</span>
                    <strong>${{ number_format($load->rate_per_mile, 2) }}/mi</strong>
                </div>
                @endif
                <hr>
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0">{{ translate('Total Estimate:') }}</h5>
                    <h4 class="mb-0" style="color: var(--ug-primary); font-weight: 700;">${{ number_format($load->payout_amount, 2) }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
