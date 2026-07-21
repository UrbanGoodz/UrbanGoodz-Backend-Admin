@extends('business.layouts.app')
@section('title', translate('Dispatch Details') . ' - #' . $dispatch->id)
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <a href="{{ route('business.ai-logistics.dispatches.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="tio-back-button"></i> {{ translate('Back to Dispatches') }}
        </a>
        <h1 class="page-header-title">
            {{ translate('Dispatch') }} #{{ $dispatch->id }}
            @php($badgeClass = match($dispatch->status) { 'pending' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'in_progress' => 'primary', 'completed' => 'dark', 'cancelled' => 'danger', default => 'secondary' })
            <span class="badge badge-soft-{{ $badgeClass }} ms-2" style="font-size: 0.95rem;">{{ ucfirst(str_replace('_', ' ', $dispatch->status)) }}</span>
        </h1>
    </div>
    <div class="d-flex gap-2">
        @if(!in_array($dispatch->status, ['cancelled', 'completed']))
        <form action="{{ route('business.ai-logistics.dispatches.cancel', $dispatch->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure you want to cancel this dispatch?') }}')">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="tio-clear"></i> {{ translate('Cancel Dispatch') }}
            </button>
        </form>
        @endif
        @if(in_array($dispatch->status, ['pending', 'sent', 'cancelled']))
        <form action="{{ route('business.ai-logistics.dispatches.resend', $dispatch->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="tio-refresh"></i> {{ translate('Resend Dispatch') }}
            </button>
        </form>
        @endif
    </div>
</div>

<div class="row g-3">
    <!-- Left Column: Dispatch & Load Info -->
    <div class="col-lg-8">
        <!-- Dispatch Details -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Dispatch Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Dispatch ID') }}</small>
                        <strong>#{{ $dispatch->id }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Created At') }}</small>
                        <strong>{{ $dispatch->created_at->format('Y-m-d H:i') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Updated At') }}</small>
                        <strong>{{ $dispatch->updated_at->format('Y-m-d H:i') }}</strong>
                    </div>
                    @if($dispatch->notes)
                    <div class="col-12">
                        <small class="text-muted d-block">{{ translate('Notes') }}</small>
                        <div class="bg-light p-2 rounded" style="font-size: 0.9rem;">
                            {{ $dispatch->notes }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Load Details -->
        @if($dispatch->load)
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Load Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 border-right">
                        <h6 class="text-muted"><i class="tio-poi" style="color: #28a745;"></i> {{ translate('PICKUP') }}</h6>
                        <h5 class="mb-1">{{ $dispatch->load->origin_name ?? '' }}</h5>
                        <p class="mb-1">{{ $dispatch->load->origin_city ?? '' }}, {{ $dispatch->load->origin_state ?? '' }} {{ $dispatch->load->origin_zip ?? '' }}</p>
                        @if($dispatch->load->origin_ready_at)
                        <small class="text-muted d-block mt-2">
                            <strong>{{ translate('Scheduled:') }}</strong> {{ $dispatch->load->origin_ready_at->format('Y-m-d H:i') }}
                        </small>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted"><i class="tio-poi" style="color: #007bff;"></i> {{ translate('DELIVERY') }}</h6>
                        <h5 class="mb-1">{{ $dispatch->load->destination_name ?? '' }}</h5>
                        <p class="mb-1">{{ $dispatch->load->destination_city ?? '' }}, {{ $dispatch->load->destination_state ?? '' }} {{ $dispatch->load->destination_zip ?? '' }}</p>
                        @if($dispatch->load->destination_due_at)
                        <small class="text-muted d-block mt-2">
                            <strong>{{ translate('Due Window:') }}</strong> {{ $dispatch->load->destination_due_at->format('Y-m-d H:i') }}
                        </small>
                        @endif
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Load Number') }}</small>
                        <strong>{{ $dispatch->load->load_number ?? '-' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Payout') }}</small>
                        <strong>${{ number_format($dispatch->load->payout_amount ?? 0, 2) }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ translate('Status') }}</small>
                        @php($loadBadge = match($dispatch->load->status ?? '') { 'available' => 'success', 'assigned' => 'warning', 'in_transit' => 'info', 'delivered' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
                        <span class="badge badge-soft-{{ $loadBadge }}">{{ $dispatch->load->status_label ?? ucfirst($dispatch->load->status ?? '') }}</span>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('business.load-board.show', $dispatch->load->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="tio-visible"></i> {{ translate('View Full Load Details') }}
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Right Column: Driver & Status History -->
    <div class="col-lg-4">
        <!-- Driver Info -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Driver Details') }}</h5>
            </div>
            <div class="card-body">
                @if($dispatch->driver)
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="mb-0">{{ $dispatch->driver->f_name }} {{ $dispatch->driver->l_name }}</h6>
                        <small class="text-muted">{{ translate('Assigned Driver') }}</small>
                    </div>
                </div>
                <div class="row g-2">
                    @if($dispatch->driver->phone)
                    <div class="col-12">
                        <small class="text-muted d-block">{{ translate('Phone') }}</small>
                        <strong>{{ $dispatch->driver->phone }}</strong>
                    </div>
                    @endif
                    @if($dispatch->driver->email)
                    <div class="col-12">
                        <small class="text-muted d-block">{{ translate('Email') }}</small>
                        <strong>{{ $dispatch->driver->email }}</strong>
                    </div>
                    @endif
                </div>
                @else
                <div class="text-center py-3 text-muted">
                    <i class="tio-user" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">{{ translate('No driver assigned') }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Status History -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ translate('Status History') }}</h5>
            </div>
            <div class="card-body">
                @if($dispatch->statusHistory && $dispatch->statusHistory->count())
                <div class="timeline">
                    @foreach($dispatch->statusHistory as $history)
                    <div class="timeline-item mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong class="d-block">{{ ucfirst(str_replace('_', ' ', $history->status)) }}</strong>
                                <small class="text-muted">{{ $history->created_at->format('M d, Y H:i') }}</small>
                                @if($history->notes)
                                <small class="text-muted d-block mt-1">{{ $history->notes }}</small>
                                @endif
                            </div>
                            @php($histBadge = match($history->status) { 'pending' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'in_progress' => 'primary', 'completed' => 'dark', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-soft-{{ $histBadge }}">{{ ucfirst(str_replace('_', ' ', $history->status)) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-3 text-muted">
                    <p class="mb-0">{{ translate('No status history available') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection