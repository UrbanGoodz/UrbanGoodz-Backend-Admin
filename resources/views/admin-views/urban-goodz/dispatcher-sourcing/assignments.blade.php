@extends('layouts.admin.app')

@section('title', translate('Dispatcher Load Sourcing — My Assignments'))

@push('css_or_js')
<style>
    .status-badge { font-size: 0.75rem; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">

        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Dashboard') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('My Assignments') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.index') }}">{{ translate('Dispatcher') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('My Assignments') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('My Assignments') }}</h1>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ translate('Search by route, load ID...') }}" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ translate('Assigned') }}</option>
                            <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>{{ translate('Accepted') }}</option>
                            <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>{{ translate('In Transit') }}</option>
                            <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>{{ translate('Delivered') }}</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn--primary btn-sm">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn-outline-secondary btn-sm">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card" style="border-left: 4px solid #ffc107;">
                    <div class="card-body py-3">
                        <h6 class="text-muted mb-1">{{ translate('Assigned') }}</h6>
                        <h3>{{ $statusCounts['pending'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card" style="border-left: 4px solid #007bff;">
                    <div class="card-body py-3">
                        <h6 class="text-muted mb-1">{{ translate('Accepted') }}</h6>
                        <h3>{{ $statusCounts['accepted'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card" style="border-left: 4px solid #17a2b8;">
                    <div class="card-body py-3">
                        <h6 class="text-muted mb-1">{{ translate('In Transit') }}</h6>
                        <h3>{{ $statusCounts['in_transit'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card" style="border-left: 4px solid #28a745;">
                    <div class="card-body py-3">
                        <h6 class="text-muted mb-1">{{ translate('Delivered') }}</h6>
                        <h3>{{ $statusCounts['delivered'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ translate('Load') }}</th>
                                <th>{{ translate('Route') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Score') }}</th>
                                <th class="text-center">{{ translate('Status') }}</th>
                                <th>{{ translate('Assigned At') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $rec)
                            @php($load = $rec->externalLoad)
                            <tr>
                                <td>
                                    <strong>{{ $load->external_reference_id ?? 'N/A' }}</strong>
                                </td>
                                <td>
                                    {{ $load->origin_city }}, {{ $load->origin_state }}
                                    &rarr;
                                    {{ $load->destination_city }}, {{ $load->destination_state }}
                                    <br><small class="text-muted">{{ number_format($load->distance_miles ?? 0) }} mi</small>
                                </td>
                                <td>{{ $rec->deliveryMan->f_name ?? '' }} {{ $rec->deliveryMan->l_name ?? '-' }}</td>
                                <td><strong>{{ $rec->score ?? 0 }}</strong></td>
                                <td class="text-center">
                                    @php
                                        $badgeMap = ['pending' => 'warning', 'accepted' => 'primary', 'in_transit' => 'info', 'delivered' => 'success', 'cancelled' => 'danger'];
                                        $labelMap = ['pending' => 'Assigned', 'accepted' => 'Accepted', 'in_transit' => 'In Transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $badgeMap[$rec->status] ?? 'secondary' }} status-badge">
                                        {{ $labelMap[$rec->status] ?? ucfirst(str_replace('_', ' ', $rec->status)) }}
                                    </span>
                                </td>
                                <td><small class="text-muted">{{ $rec->created_at->diffForHumans() }}</small></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        @if($load->source_url || ($load->source && $load->source->deep_link_template))
                                        <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.open-external', $load->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="{{ translate('Track') }}">
                                            <i class="tio-location"></i>
                                        </a>
                                        @endif
                                        @if(in_array($rec->status, ['pending', 'accepted']))
                                        <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.assign', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ translate('Reassign') }}">
                                                <i class="tio-repeat"></i>
                                            </button>
                                        </form>
                                        @endif
                                        @if(in_array($rec->status, ['pending']))
                                        <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.cancel-assignment', $rec->id) }}" class="d-inline" onsubmit="return confirm('{{ translate('Cancel this assignment?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Cancel') }}">
                                                <i class="tio-clear"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ translate('No assignments yet.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
