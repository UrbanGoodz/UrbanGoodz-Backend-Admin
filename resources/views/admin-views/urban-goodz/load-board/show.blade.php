@extends('layouts.admin.app')

@section('title', translate('Load') . ' #' . ($load->load_number ?? $load->id))

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
                @if(in_array($load->status, ['available', 'sourced', 'draft']))
                <form method="POST" action="{{ route('admin.urban-goodz.load-board.destroy', $load->id) }}" onsubmit="return confirm('{{ translate('Delete this load?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="tio-delete"></i> {{ translate('Delete') }}</button>
                </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Load Details') }}</h5>
                        <span class="badge {{ $load->status_badge_class }}">{{ $load->status_label }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Load Number') }}</small>
                                <p class="fw-bold mb-0">{{ $load->load_number ?? '-' }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Provider') }}</small>
                                <p class="fw-bold mb-0">{{ ucfirst($load->provider) }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Source External ID') }}</small>
                                <p class="fw-bold mb-0">{{ $load->external_id ?? '-' }}</p>
                            </div>
                            @if($load->businessClient)
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Business/Customer') }}</small>
                                <p class="fw-bold mb-0">{{ $load->businessClient->company_name ?? '-' }}</p>
                            </div>
                            @endif
                            <div class="col-12"><hr></div>

                            <div class="col-md-6">
                                <h6><i class="tio-location"></i> {{ translate('Origin') }}</h6>
                                <p class="mb-1"><strong>{{ $load->origin_name ?? '-' }}</strong></p>
                                <p class="mb-1">{{ $load->origin_city ?? '' }}, {{ $load->origin_state ?? '' }} {{ $load->origin_zip ?? '' }}</p>
                                @if($load->origin_ready_at)<p class="text-muted mb-0"><small>{{ translate('Ready') }}: {{ $load->origin_ready_at->format('M d, Y g:i A') }}</small></p>@endif
                            </div>
                            <div class="col-md-6">
                                <h6><i class="tio-location"></i> {{ translate('Destination') }}</h6>
                                <p class="mb-1"><strong>{{ $load->destination_name ?? '-' }}</strong></p>
                                <p class="mb-1">{{ $load->destination_city ?? '' }}, {{ $load->destination_state ?? '' }} {{ $load->destination_zip ?? '' }}</p>
                                @if($load->destination_due_at)<p class="text-muted mb-0"><small>{{ translate('Due') }}: {{ $load->destination_due_at->format('M d, Y g:i A') }}</small></p>@endif
                            </div>
                            <div class="col-12"><hr></div>

                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Distance') }}</small>
                                <p class="fw-bold mb-0">{{ $load->distance_miles ? number_format($load->distance_miles, 0) . ' mi' : '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Est. Duration') }}</small>
                                <p class="fw-bold mb-0">{{ $load->estimated_duration_minutes ? number_format($load->estimated_duration_minutes / 60, 1) . ' hrs' : '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Weight') }}</small>
                                <p class="fw-bold mb-0">{{ $load->weight_lbs ? number_format($load->weight_lbs, 0) . ' lbs' : '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Pieces') }}</small>
                                <p class="fw-bold mb-0">{{ $load->pieces ?? '-' }}</p>
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
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Load Type') }}</small>
                                <p class="fw-bold mb-0">{{ $load->load_type ?? '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Equipment') }}</small>
                                <p class="fw-bold mb-0">{{ $load->equipment_type ?? '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Commodity') }}</small>
                                <p class="fw-bold mb-0">{{ $load->commodity_description ?? '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">{{ translate('Length') }}</small>
                                <p class="fw-bold mb-0">{{ $load->length_ft ? number_format($load->length_ft, 0) . ' ft' : '-' }}</p>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">{{ translate('Special Requirements') }}</small>
                                <p class="mb-0">{{ $load->special_requirements ?? '-' }}</p>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">{{ translate('Notes') }}</small>
                                <p class="mb-0">{{ $load->notes ?? '-' }}</p>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                @if($load->is_hazmat)<span class="badge badge-soft-danger">{{ translate('Hazmat') }}</span>@endif
                                @if($load->is_temperature_controlled)<span class="badge badge-soft-info">{{ translate('Temp Controlled') }} ({{ $load->temperature_min_f }}-{{ $load->temperature_max_f }}F)</span>@endif
                                @if($load->requires_liftgate)<span class="badge badge-soft-warning">{{ translate('Liftgate') }}</span>@endif
                                @if($load->requires_pallet_jack)<span class="badge badge-soft-warning">{{ translate('Pallet Jack') }}</span>@endif
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

                @if($load->assigned_driver_id)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Assigned Driver') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Driver') }}</small>
                                <p class="fw-bold mb-0">{{ $load->assignedDriver?->f_name ?? '' }} {{ $load->assignedDriver?->l_name ?? '' }}</p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Assigned') }}</small>
                                <p class="mb-0">{{ $load->assigned_at?->format('M d, Y g:i A') ?? '-' }}</p>
                            </div>
                            @if($load->picked_up_at)
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Picked Up') }}</small>
                                <p class="mb-0">{{ $load->picked_up_at->format('M d, Y g:i A') }}</p>
                            </div>
                            @endif
                            @if($load->delivered_at)
                            <div class="col-md-4">
                                <small class="text-muted">{{ translate('Delivered') }}</small>
                                <p class="mb-0">{{ $load->delivered_at->format('M d, Y g:i A') }}</p>
                            </div>
                            @endif
                        </div>
                        @if($load->status === 'assigned')
                        <hr>
                        <form method="POST" action="{{ route('admin.urban-goodz.load-board.reassign', $load->id) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label form-label-sm">{{ translate('Reassign to Driver') }}</label>
                                <select name="driver_id" class="form-control form-control-sm" required>
                                    <option value="">{{ translate('Select Driver') }}</option>
                                    @foreach($eligibleDrivers as $driver)
                                    <option value="{{ $driver->id }}" {{ $driver->id == $load->assigned_driver_id ? 'selected' : '' }}>
                                        {{ $driver->f_name }} {{ $driver->l_name }} ({{ $driver->phone ?? $driver->email }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ translate('Reason for reassignment') }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-warning w-100" onclick="return confirm('{{ translate('Reassign this load?') }}')">
                                    {{ translate('Reassign') }}
                                </button>
                            </div>
                        </form>
                        @endif
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
                        @if(in_array($load->status, ['under_review', 'sourced', 'draft']))
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.review', $load->id) }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="tio-check-circle"></i> {{ translate('Approve & Recommend') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.review', $load->id) }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="decision" value="send_to_board">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="tio-send"></i> {{ translate('Send to Board') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.review', $load->id) }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="decision" value="reject">
                                <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('{{ translate('Reject this load?') }}')">
                                    <i class="tio-close-circle"></i> {{ translate('Reject') }}
                                </button>
                            </form>
                        @endif

                        @if($load->status === 'available')
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.review', $load->id) }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="decision" value="send_to_board">
                                <button type="submit" class="btn btn-outline-primary btn-block">
                                    <i class="tio-send"></i> {{ translate('Publish to Board') }}
                                </button>
                            </form>
                        @endif

                        @if(!in_array($load->status, ['completed', 'cancelled', 'available', 'sourced', 'draft', 'under_review']))
                            <hr>
                            <small class="text-muted d-block mb-2">{{ translate('Status workflow') }}:</small>

                            @if($load->status === 'assigned')
                                <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="status" value="in_transit">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="tio-route"></i> {{ translate('Start Transit') }}
                                    </button>
                                </form>
                            @endif

                            @if($load->status === 'in_transit')
                                <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="status" value="picked_up">
                                    <button type="submit" class="btn btn-warning btn-block">
                                        <i class="tio-inbox"></i> {{ translate('Mark Picked Up') }}
                                    </button>
                                </form>
                            @endif

                            @if($load->status === 'picked_up')
                                <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="tio-check-circle"></i> {{ translate('Mark Delivered') }}
                                    </button>
                                </form>
                            @endif

                            @if($load->status === 'delivered')
                                <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="tio-check-double"></i> {{ translate('Complete & Settle') }}
                                    </button>
                                </form>
                            @endif

                            @if(in_array($load->status, ['recommended', 'offered']))
                                @if(!$load->assigned_driver_id && count($eligibleDrivers) > 0)
                                <form method="POST" action="{{ route('admin.urban-goodz.load-board.assign', $load->id) }}" class="mb-2">
                                    @csrf
                                    <div class="mb-2">
                                        <select name="driver_id" class="form-control form-control-sm" required>
                                            <option value="">{{ translate('Select Driver') }}</option>
                                            @foreach($eligibleDrivers as $driver)
                                            <option value="{{ $driver->id }}">
                                                {{ $driver->f_name }} {{ $driver->l_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-info btn-block">
                                        <i class="tio-user-check"></i> {{ translate('Assign Driver') }}
                                    </button>
                                </form>
                                @endif
                            @endif

                            @if(in_array($load->status, ['recommended', 'offered']))
                                <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('{{ translate('Cancel this load?') }}')">
                                        <i class="tio-close-circle"></i> {{ translate('Cancel Load') }}
                                    </button>
                                </form>
                            @endif
                        @endif

                        @if(in_array($load->status, ['assigned', 'in_transit', 'picked_up']))
                            <hr>
                            <form method="POST" action="{{ route('admin.urban-goodz.load-board.status', $load->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <div class="mb-2">
                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ translate('Cancellation reason') }}">
                                </div>
                                <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('{{ translate('Cancel this load?') }}')">
                                    <i class="tio-close-circle"></i> {{ translate('Cancel Load') }}
                                </button>
                            </form>
                        @endif

                        @if(!in_array($load->status, ['available', 'cancelled', 'completed']))
                            <hr>
                            <small class="text-muted">{{ translate('Current status') }}: <strong>{{ $load->status_label }}</strong></small>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Pricing & Payment') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Customer Price') }}</small>
                                <p class="fw-bold text-primary mb-1">${{ number_format($load->customer_price ?? 0, 2) }}</p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Driver Payout') }}</small>
                                <p class="fw-bold text-success mb-1">${{ number_format($load->effective_driver_payout, 2) }}</p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Dispatcher Incentive') }}</small>
                                <p class="fw-bold text-info mb-1">${{ number_format($load->dispatcher_incentive ?? 0, 2) }}</p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Platform Margin') }}</small>
                                <p class="fw-bold text-warning mb-1">${{ number_format($load->effective_margin, 2) }}</p>
                            </div>
                            @if($load->source_cost)
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Source Cost') }}</small>
                                <p class="mb-1">${{ number_format($load->source_cost, 2) }}</p>
                            </div>
                            @endif
                            @if($load->processing_fee)
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Processing Fee') }}</small>
                                <p class="mb-1">${{ number_format($load->processing_fee, 2) }}</p>
                            </div>
                            @endif
                            @if($load->accessorials)
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Accessorials') }}</small>
                                <p class="mb-1">${{ number_format($load->accessorials, 2) }}</p>
                            </div>
                            @endif
                        </div>
                        <hr>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Payout Type') }}</small>
                                <p class="mb-0">{{ ucfirst($load->payout_type) }}</p>
                            </div>
                            @if($load->rate_per_mile)
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Rate/Mile') }}</small>
                                <p class="mb-0">${{ number_format($load->rate_per_mile, 2) }}/mi</p>
                            </div>
                            @endif
                            @if($load->commission_rate)
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Commission Rate') }}</small>
                                <p class="mb-0">{{ number_format($load->commission_rate, 1) }}%</p>
                            </div>
                            @endif
                            @if($load->commission_amount)
                            <div class="col-6">
                                <small class="text-muted">{{ translate('Commission Amount') }}</small>
                                <p class="mb-0">${{ number_format($load->commission_amount, 2) }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($load->dispatchCompany || $load->dispatcherUser)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Dispatch') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($load->dispatchCompany)
                        <p class="mb-1"><strong>{{ $load->dispatchCompany->company_name }}</strong></p>
                        @endif
                        @if($load->dispatcherUser)
                        <p class="mb-0 text-muted">{{ $load->dispatcherUser->name ?? '-' }}</p>
                        @endif
                        <p class="mb-0"><small>{{ translate('Status') }}: {{ ucfirst(str_replace('_', ' ', $load->dispatch_status)) }}</small></p>
                    </div>
                </div>
                @endif

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Metadata') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-1">
                            <div class="col-12"><small class="text-muted">Created: {{ $load->created_at->format('M d, Y g:i A') }}</small></div>
                            <div class="col-12"><small class="text-muted">Updated: {{ $load->updated_at->format('M d, Y g:i A') }}</small></div>
                            @if($load->reviewed_at)<div class="col-12"><small class="text-muted">Reviewed: {{ $load->reviewed_at->format('M d, Y g:i A') }}</small></div>@endif
                            @if($load->cancelled_at)<div class="col-12"><small class="text-muted">Cancelled: {{ $load->cancelled_at->format('M d, Y g:i A') }}</small></div>@endif
                            @if($load->metadata)
                            <div class="col-12 mt-2">
                                <pre class="bg-light p-2 rounded" style="font-size: 0.75rem; max-height: 150px; overflow-y: auto;">{{ json_encode($load->metadata, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($load->auditLogs->count())
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Audit History') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Time') }}</th>
                                <th>{{ translate('Event') }}</th>
                                <th>{{ translate('From') }}</th>
                                <th>{{ translate('To') }}</th>
                                <th>{{ translate('Actor') }}</th>
                                <th>{{ translate('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($load->auditLogs->sortByDesc('created_at') as $log)
                            <tr>
                                <td><small>{{ $log->created_at->format('M d, Y g:i A') }}</small></td>
                                <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', ucfirst($log->event_type)) }}</span></td>
                                <td><small>{{ $log->old_value ?? '-' }}</small></td>
                                <td><small>{{ $log->new_value ?? '-' }}</small></td>
                                <td><small>{{ $log->actor_type }} #{{ $log->actor_id ?? '-' }}</small></td>
                                <td><small>{{ $log->notes ?? '-' }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
