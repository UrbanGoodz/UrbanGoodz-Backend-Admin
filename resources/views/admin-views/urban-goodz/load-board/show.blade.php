@extends('layouts.admin.app')

@section('title', translate('Load') . ' #' . $load->id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.load-board.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Load Board') }}
            </a>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.load-board.edit', $load->id) }}" class="btn btn-outline--primary">
                    <i class="tio-pen"></i> {{ translate('Edit') }}
                </a>
                @if(in_array($load->status, ['available']))
                <form method="POST" action="{{ route('admin.urban-goodz.load-board.destroy', $load->id) }}" onsubmit="return confirm('{{ translate('Delete this load?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="tio-delete"></i> {{ translate('Delete') }}</button>
                </form>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Load Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Load Number') }}</small>
                                <p class="fw-bold">{{ $load->load_number ?? '-' }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Provider') }}</small>
                                <p class="fw-bold">{{ ucfirst($load->provider) }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Status') }}</small>
                                <p>
                                    @php $statusColors = ['available' => 'success', 'assigned' => 'info', 'in_transit' => 'primary', 'picked_up' => 'warning', 'delivered' => 'secondary', 'cancelled' => 'danger']; @endphp
                                    <span class="badge badge-soft-{{ $statusColors[$load->status] ?? 'secondary' }}">{{ $load->status_label }}</span>
                                </p>
                            </div>
                            <div class="col-12"><hr></div>

                            <div class="col-md-6">
                                <h6>{{ translate('Origin') }}</h6>
                                <p class="mb-1"><strong>{{ $load->origin_name ?? '-' }}</strong></p>
                                <p class="mb-1">{{ $load->origin_city ?? '' }}, {{ $load->origin_state ?? '' }} {{ $load->origin_zip ?? '' }}</p>
                                @if($load->origin_ready_at)<p class="text-muted mb-0"><small>{{ translate('Ready') }}: {{ $load->origin_ready_at->format('M d, Y g:i A') }}</small></p>@endif
                            </div>
                            <div class="col-md-6">
                                <h6>{{ translate('Destination') }}</h6>
                                <p class="mb-1"><strong>{{ $load->destination_name ?? '-' }}</strong></p>
                                <p class="mb-1">{{ $load->destination_city ?? '' }}, {{ $load->destination_state ?? '' }} {{ $load->destination_zip ?? '' }}</p>
                                @if($load->destination_due_at)<p class="text-muted mb-0"><small>{{ translate('Due') }}: {{ $load->destination_due_at->format('M d, Y g:i A') }}</small></p>@endif
                            </div>
                            <div class="col-12"><hr></div>

                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Distance') }}</small>
                                <p class="fw-bold">{{ $load->distance_miles ? number_format($load->distance_miles, 0) . ' mi' : '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Est. Duration') }}</small>
                                <p class="fw-bold">{{ $load->estimated_duration_minutes ? number_format($load->estimated_duration_minutes / 60, 1) . ' hrs' : '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Weight') }}</small>
                                <p class="fw-bold">{{ $load->weight_lbs ? number_format($load->weight_lbs, 0) . ' lbs' : '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Pieces') }}</small>
                                <p class="fw-bold">{{ $load->pieces ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Load Specifications') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Load Type') }}</small>
                                <p class="fw-bold">{{ $load->load_type ?? '-' }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Equipment Type') }}</small>
                                <p class="fw-bold">{{ $load->equipment_type ?? '-' }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Commodity') }}</small>
                                <p class="fw-bold">{{ $load->commodity_description ?? '-' }}</p>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">{{ translate('Special Requirements') }}</small>
                                <p>{{ $load->special_requirements ?? '-' }}</p>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">{{ translate('Notes') }}</small>
                                <p>{{ $load->notes ?? '-' }}</p>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                @if($load->is_hazmat)<span class="badge badge-soft-danger">{{ translate('Hazmat') }}</span>@endif
                                @if($load->is_temperature_controlled)<span class="badge badge-soft-info">{{ translate('Temp Controlled') }} ({{ $load->temperature_min_f }}-{{ $load->temperature_max_f }}F)</span>@endif
                                @if($load->requires_liftgate)<span class="badge badge-soft-warning">{{ translate('Liftgate Required') }}</span>@endif
                                @if($load->requires_pallet_jack)<span class="badge badge-soft-warning">{{ translate('Pallet Jack Required') }}</span>@endif
                                @if($load->is_team_load)<span class="badge badge-soft-primary">{{ translate('Team Load') }}</span>@endif
                                @if($load->is_expedited)<span class="badge badge-soft-danger">{{ translate('Expedited') }}</span>@endif
                            </div>
                        </div>
                    </div>
                </div>

                @if($load->shipper_name || $load->consignee_name)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Contact Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6>{{ translate('Shipper') }}</h6>
                                <p class="mb-0">{{ $load->shipper_name ?? '-' }} @if($load->shipper_phone) &middot; {{ $load->shipper_phone }} @endif</p>
                            </div>
                            <div class="col-md-6">
                                <h6>{{ translate('Consignee') }}</h6>
                                <p class="mb-0">{{ $load->consignee_name ?? '-' }} @if($load->consignee_phone) &middot; {{ $load->consignee_phone }} @endif</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Admin Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($load->status === 'available')
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('{{ translate('Cancel this load?') }}')">
                                    <i class="tio-close-circle"></i> {{ translate('Cancel Load') }}
                                </button>
                            </form>
                        @endif

                        @if($load->status === 'assigned')
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="in_transit">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="tio-route"></i> {{ translate('Mark In Transit') }}
                                </button>
                            </form>
                        @endif

                        @if($load->status === 'in_transit')
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="picked_up">
                                <button type="submit" class="btn btn-warning btn-block">
                                    <i class="tio-inbox"></i> {{ translate('Mark Picked Up') }}
                                </button>
                            </form>
                        @endif

                        @if($load->status === 'picked_up')
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="delivered">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="tio-check-circle"></i> {{ translate('Mark Delivered') }}
                                </button>
                            </form>
                        @endif

                        @if(in_array($load->status, ['assigned', 'in_transit', 'picked_up']))
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('{{ translate('Cancel this load?') }}')">
                                    <i class="tio-close-circle"></i> {{ translate('Cancel Load') }}
                                </button>
                            </form>
                        @endif

                        @if(!in_array($load->status, ['available', 'cancelled', 'delivered']))
                            <hr>
                            <small class="text-muted">{{ translate('Current status') }}: <strong>{{ $load->status_label }}</strong></small>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Payout') }}</h5>
                    </div>
                    <div class="card-body text-center">
                        <h2 class="text-success">${{ number_format($load->payout_amount, 2) }}</h2>
                        <small class="text-muted">{{ ucfirst($load->payout_type) }}</small>
                        @if($load->rate_per_mile)
                        <p class="mb-0 mt-2"><small>${{ number_format($load->rate_per_mile, 2) }}/mi</small></p>
                        @endif
                    </div>
                </div>

                @if($load->assigned_driver_id)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Assigned Driver') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="fw-bold">{{ $load->assignedDriver?->f_name ?? '' }} {{ $load->assignedDriver?->l_name ?? '' }}</p>
                        @if($load->assigned_at)<p class="text-muted"><small>{{ translate('Assigned') }}: {{ $load->assigned_at->format('M d, Y g:i A') }}</small></p>@endif
                        @if($load->picked_up_at)<p class="text-muted"><small>{{ translate('Picked Up') }}: {{ $load->picked_up_at->format('M d, Y g:i A') }}</small></p>@endif
                        @if($load->delivered_at)<p class="text-muted"><small>{{ translate('Delivered') }}: {{ $load->delivered_at->format('M d, Y g:i A') }}</small></p>@endif
                    </div>
                </div>
                @endif

                @if($load->metadata)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Metadata') }}</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-2 rounded" style="font-size: 0.8rem; max-height: 200px; overflow-y: auto;">{{ json_encode($load->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
