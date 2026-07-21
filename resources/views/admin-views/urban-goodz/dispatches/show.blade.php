@extends('layouts.admin.app')

@section('title', translate('Dispatch') . ' #' . $dispatch->id)

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .dispatch-badge-draft { background-color: #e2e3e5; color: #41464b; }
        .dispatch-badge-pending { background-color: #fef7e0; color: #b06000; }
        .dispatch-badge-sent { background-color: #cff4fc; color: #055160; }
        .dispatch-badge-accepted { background-color: #e6f4ea; color: #137333; }
        .dispatch-badge-completed { background-color: #d1e7dd; color: #0f5132; }
        .dispatch-badge-cancelled { background-color: #f8d7da; color: #842029; }
        .dispatch-badge-expired { background-color: #f8d7da; color: #842029; }
        .timeline-item { position: relative; padding-left: 28px; padding-bottom: 20px; border-left: 2px solid #dee2e6; }
        .timeline-item:last-child { border-left: 2px solid transparent; }
        .timeline-dot { position: absolute; left: -9px; top: 0; width: 16px; height: 16px; border-radius: 50%; background: #6c757d; border: 2px solid #fff; }
        .timeline-dot.active { background: #0d6efd; }
        .timeline-dot.success { background: #198754; }
        .timeline-dot.danger { background: #dc3545; }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.dispatches.index') }}">{{ translate('AI Dispatches') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">#{{ $dispatch->id }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">
                    {{ translate('Dispatch') }} #{{ $dispatch->id }}
                    @php
                        $statusClassMap = [
                            'draft' => 'secondary', 'pending' => 'warning', 'sent' => 'info',
                            'accepted' => 'success', 'completed' => 'success',
                            'cancelled' => 'danger', 'expired' => 'danger',
                        ];
                    @endphp
                    <span class="badge dispatch-badge-{{ $dispatch->status }} ml-2" style="font-size: 0.7em; vertical-align: middle;">
                        {{ translate(ucfirst(str_replace('_', ' ', $dispatch->status))) }}
                    </span>
                </h1>
            </div>
            <div class="d-flex gap-1">
                <a href="{{ route('admin.urban-goodz.dispatches.index') }}" class="btn btn-secondary">
                    <i class="tio-back"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            <!-- Left Column: Details & Timeline -->
            <div class="col-lg-8">
                <!-- Dispatch Overview -->
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Dispatch Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <strong>{{ translate('Dispatch ID') }}:</strong>
                                <div>#{{ $dispatch->id }}</div>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Created At') }}:</strong>
                                <div>{{ $dispatch->created_at?->format('M d, Y g:i A') ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Dispatched At') }}:</strong>
                                <div>{{ $dispatch->dispatched_at?->format('M d, Y g:i A') ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Offer Expires') }}:</strong>
                                <div>{{ $dispatch->offer_expires_at?->format('M d, Y g:i A') ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Driver Payout') }}:</strong>
                                <div>
                                    @if($dispatch->driver_payout_amount)
                                        <strong class="text-success">${{ number_format($dispatch->driver_payout_amount, 2) }}</strong>
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Exception') }}:</strong>
                                <div>
                                    @if($dispatch->exception_reason)
                                        <span class="badge badge-soft-danger">{{ $dispatch->exception_reason }}</span>
                                    @else
                                        <span class="text-muted">{{ translate('None') }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($dispatch->notes)
                                <div class="col-12">
                                    <strong>{{ translate('Notes') }}:</strong>
                                    <div class="mt-1 p-2 bg-light rounded">{{ $dispatch->notes }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Driver Info -->
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Driver Information') }}</h5></div>
                    <div class="card-body">
                        @if($dispatch->driver)
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <strong>{{ translate('Name') }}:</strong>
                                    <div>{{ $dispatch->driver->f_name . ' ' . $dispatch->driver->l_name }}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Phone') }}:</strong>
                                    <div>{{ $dispatch->driver->phone ?? '—' }}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Email') }}:</strong>
                                    <div>{{ $dispatch->driver->email ?? '—' }}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Status') }}:</strong>
                                    <div>
                                        @if($dispatch->driver->is_active ?? true)
                                            <span class="badge badge-soft-success">{{ translate('Active') }}</span>
                                        @else
                                            <span class="badge badge-soft-secondary">{{ translate('Inactive') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="text-muted mb-0">{{ translate('No driver assigned.') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Load Info -->
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Load Information') }}</h5></div>
                    <div class="card-body">
                        @if($dispatch->load)
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <strong>{{ translate('Reference') }}:</strong>
                                    <div>
                                        <a href="{{ route('admin.urban-goodz.load-board.show', $dispatch->load->id) }}" class="text-primary">
                                            {{ $dispatch->load->reference_number ?? '#' . $dispatch->load->id }}
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Origin') }}:</strong>
                                    <div>{{ $dispatch->load->origin_city }}, {{ $dispatch->load->origin_state }}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Destination') }}:</strong>
                                    <div>{{ $dispatch->load->destination_city }}, {{ $dispatch->load->destination_state }}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Distance') }}:</strong>
                                    <div>{{ number_format($dispatch->load->distance_miles ?? 0) }} mi</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Equipment') }}:</strong>
                                    <div>{{ ucwords(str_replace('_', ' ', $dispatch->load->equipment_type ?? 'N/A')) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ translate('Payout') }}:</strong>
                                    <div><strong class="text-success">${{ number_format($dispatch->load->payout_amount ?? 0, 2) }}</strong></div>
                                </div>
                            </div>
                        @else
                            <p class="text-muted mb-0">{{ translate('No load associated with this dispatch.') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Status History / Timeline -->
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Status History') }}</h5></div>
                    <div class="card-body">
                        @forelse($dispatch->statusHistory->sortByDesc('created_at') as $history)
                            <div class="timeline-item">
                                @php
                                    $dotClass = 'active';
                                    if(in_array($history->status, ['completed', 'accepted'])) $dotClass = 'success';
                                    if(in_array($history->status, ['cancelled', 'expired', 'exception'])) $dotClass = 'danger';
                                @endphp
                                <div class="timeline-dot {{ $dotClass }}"></div>
                                <div class="d-flex justify-content-between">
                                    <strong>{{ translate(ucfirst(str_replace('_', ' ', $history->status))) }}</strong>
                                    <small class="text-muted">{{ $history->created_at?->format('M d, Y g:i A') }}</small>
                                </div>
                                @if($history->notes)
                                    <small class="text-muted">{{ $history->notes }}</small>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ translate('No status history recorded.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: Actions -->
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Actions') }}</h5></div>
                    <div class="card-body d-flex flex-column gap-2">
                        @if($dispatch->status === 'pending')
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.approve', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="tio-checkmark-circle"></i> {{ translate('Approve') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.send', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-info btn-block" onclick="return confirm('{{ translate('Send this dispatch to the driver now?') }}')">
                                    <i class="tio-send"></i> {{ translate('Send to Driver') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.cancel', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('{{ translate('Cancel this dispatch?') }}')">
                                    <i class="tio-clear"></i> {{ translate('Cancel') }}
                                </button>
                            </form>
                        @endif

                        @if($dispatch->status === 'sent' || $dispatch->status === 'accepted')
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.cancel', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('{{ translate('Cancel this dispatch?') }}')">
                                    <i class="tio-clear"></i> {{ translate('Cancel') }}
                                </button>
                            </form>
                        @endif

                        @if(in_array($dispatch->status, ['cancelled', 'expired']))
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.resend', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="tio-refresh"></i> {{ translate('Resend') }}
                                </button>
                            </form>
                        @endif

                        @if($dispatch->exception_reason && in_array($dispatch->status, ['sent', 'accepted', 'exception']))
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.resolve-exception', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-block">
                                    <i class="tio-check"></i> {{ translate('Resolve Exception') }}
                                </button>
                            </form>
                        @endif

                        @if(in_array($dispatch->status, ['accepted', 'completed']))
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.settle', $dispatch->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-block" onclick="return confirm('{{ translate('Settle this dispatch? This will finalize the payout.') }}')">
                                    <i class="tio-dollar-circle"></i> {{ translate('Settle') }}
                                </button>
                            </form>
                        @endif

                        @if(!in_array($dispatch->status, ['completed', 'cancelled', 'expired']))
                            <hr class="my-1">
                            <form method="POST" action="{{ route('admin.urban-goodz.dispatches.cancel', $dispatch->id) }}">
                                @csrf
                                <input type="hidden" name="force" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-block btn-sm" onclick="return confirm('{{ translate('Force cancel this dispatch?') }}')">
                                    <i class="tio-warning"></i> {{ translate('Force Cancel') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Exception Details -->
                @if($dispatch->exception_reason)
                    <div class="card mb-3 border-danger">
                        <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="tio-warning"></i> {{ translate('Exception') }}</h5></div>
                        <div class="card-body">
                            <strong>{{ translate('Reason') }}:</strong>
                            <div class="mb-2">{{ $dispatch->exception_reason }}</div>
                            @if($dispatch->exception_notes)
                                <strong>{{ translate('Notes') }}:</strong>
                                <div>{{ $dispatch->exception_notes }}</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
