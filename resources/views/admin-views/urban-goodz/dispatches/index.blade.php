@extends('layouts.admin.app')

@section('title', translate('AI Dispatches'))

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
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Breadcrumb & Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.index') }}">{{ translate('Dispatch Management') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('AI Dispatches') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('AI Dispatches') }}</h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.urban-goodz.dispatches.create') }}" class="btn btn-primary">
                    <i class="tio-add"></i> {{ translate('New Dispatch') }}
                </a>
            </div>
        </div>

        <!-- Safety Callout -->
        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="tio-shield-alert text-info mr-2"></i>
            <strong>{{ translate('Human-In-The-Loop Policy') }}:</strong>
            {{ translate('AI dispatches require admin review before being sent to drivers. Autonomous dispatch is locked pending authorization.') }}
        </div>

        <!-- Stats Overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-primary">{{ $stats['total'] ?? $dispatches->total() }}</div>
                        <small class="text-muted">{{ translate('Total') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-warning">{{ $stats['pending'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Pending Review') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-info">{{ $stats['sent'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Sent') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-success">{{ $stats['accepted'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Accepted') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-success">{{ $stats['completed'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Completed') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-danger">{{ $stats['cancelled'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Cancelled') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & Dispatch Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('All Dispatches') }}</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ translate('Search ID, load ref...') }}" value="{{ request('search') }}">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Statuses') }}</option>
                        @foreach(['draft', 'pending', 'sent', 'accepted', 'completed', 'cancelled', 'expired'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ translate(ucfirst(str_replace('_', ' ', $s))) }}</option>
                        @endforeach
                    </select>
                    <select name="driver_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Drivers') }}</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected(request('driver_id') == $driver->id)>{{ $driver->f_name . ' ' . $driver->l_name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="tio-search"></i>
                    </button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ translate('ID') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Load Reference') }}</th>
                                <th>{{ translate('Payout') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Dispatched At') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dispatches as $key => $dispatch)
                                <tr>
                                    <td>{{ $dispatches->firstItem() + $key }}</td>
                                    <td>
                                        <a href="{{ route('admin.urban-goodz.dispatches.show', $dispatch->id) }}" class="text-primary fw-semibold">
                                            #{{ $dispatch->id }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($dispatch->driver)
                                            {{ $dispatch->driver->f_name . ' ' . $dispatch->driver->l_name }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($dispatch->load)
                                            <a href="{{ route('admin.urban-goodz.load-board.show', $dispatch->load->id) }}" class="text-info">
                                                {{ $dispatch->load->reference_number ?? $dispatch->load->id }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($dispatch->driver_payout_amount)
                                            <strong class="text-success">${{ number_format($dispatch->driver_payout_amount, 2) }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusClassMap = [
                                                'draft' => 'secondary',
                                                'pending' => 'warning',
                                                'sent' => 'info',
                                                'accepted' => 'success',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                'expired' => 'danger',
                                            ];
                                        @endphp
                                        <span class="badge dispatch-badge-{{ $dispatch->status }}">
                                            {{ translate(ucfirst(str_replace('_', ' ', $dispatch->status))) }}
                                        </span>
                                    </td>
                                    <td>{{ $dispatch->dispatched_at?->format('M d, Y g:i A') ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('admin.urban-goodz.dispatches.show', $dispatch->id) }}" class="btn btn-sm btn-outline-info" title="{{ translate('View') }}">
                                                <i class="tio-visible"></i>
                                            </a>
                                            @if(in_array($dispatch->status, ['pending', 'draft']))
                                                <form method="POST" action="{{ route('admin.urban-goodz.dispatches.cancel', $dispatch->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Cancel') }}" onclick="return confirm('{{ translate('Cancel this dispatch?') }}')">
                                                        <i class="tio-clear"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if(in_array($dispatch->status, ['cancelled', 'expired']))
                                                <form method="POST" action="{{ route('admin.urban-goodz.dispatches.resend', $dispatch->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ translate('Resend') }}">
                                                        <i class="tio-refresh"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        {{ translate('No dispatches found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $dispatches->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
