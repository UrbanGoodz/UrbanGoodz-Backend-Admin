@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Overview'))

@push('css_or_js')
<style>
    .stat-card { border-radius: 8px; transition: box-shadow .2s; }
    .stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .stat-number { font-size: 1.6rem; font-weight: 700; }
    .bar-chart .bar-row { display: flex; align-items: center; margin-bottom: .4rem; }
    .bar-chart .bar-label { width: 120px; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-chart .bar-track { flex: 1; height: 22px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
    .bar-chart .bar-fill { height: 100%; border-radius: 4px; display: flex; align-items: center; padding-left: 6px; font-size: .72rem; font-weight: 600; color: #fff; min-width: 24px; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">

        {{-- Sub-Navigation --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Overview') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-link"></i> {{ translate('Sources') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Recommendations') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-refresh"></i> {{ translate('Sync Runs') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-warning"></i> {{ translate('Errors') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-settings-outlined"></i> {{ translate('Settings') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Breadcrumb & Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="#">{{ translate('AI Operations') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Overview') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Load Sourcing Overview') }}</h1>
            </div>
            <div>
                <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn--primary">
                    <i class="tio-search"></i> {{ translate('Source Loads with AI') }}
                </a>
            </div>
        </div>

        {{-- Safety Callout --}}
        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="tio-shield-alert text-info mr-2"></i>
            <div>
                <strong>{{ translate('Human-In-The-Loop Mode') }}:</strong>
                {{ translate('External load sourcing operates under manual review. Autonomous booking and auto-dispatch are locked pending admin authorization.') }}
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center py-3">
                            <div class="stat-number text-primary">{{ $stats['total_loads'] ?? 0 }}</div>
                            <small class="text-muted">{{ translate('Total Loads') }}</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-4">
                <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads', ['status' => 'available']) }}" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center py-3">
                            <div class="stat-number text-success">{{ $stats['available'] ?? 0 }}</div>
                            <small class="text-muted">{{ translate('Available') }}</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-4">
                <a href="{{ route('admin.urban-goodz.load-board.index', ['status' => 'assigned']) }}" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center py-3">
                            <div class="stat-number text-info">{{ $stats['assigned'] ?? 0 }}</div>
                            <small class="text-muted">{{ translate('Assigned') }}</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-4">
                <a href="{{ route('admin.urban-goodz.load-board.index', ['status' => 'in_transit']) }}" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center py-3">
                            <div class="stat-number text-warning">{{ $stats['in_transit'] ?? 0 }}</div>
                            <small class="text-muted">{{ translate('In Transit') }}</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-4">
                <a href="{{ route('admin.urban-goodz.load-board.index', ['status' => 'delivered']) }}" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center py-3">
                            <div class="stat-number" style="color:#0d6efd;">{{ $stats['delivered'] ?? 0 }}</div>
                            <small class="text-muted">{{ translate('Delivered') }}</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-4">
                <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads', ['status' => 'cancelled']) }}" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center py-3">
                            <div class="stat-number text-danger">{{ $stats['cancelled'] ?? 0 }}</div>
                            <small class="text-muted">{{ translate('Cancelled') }}</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        {{-- Financial Summary --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body py-3">
                        <small class="text-muted d-block">{{ translate('Total Payout Value') }}</small>
                        <div class="stat-number text-success">${{ number_format($stats['total_payout'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body py-3">
                        <small class="text-muted d-block">{{ translate('Average Rate Per Mile') }}</small>
                        <div class="stat-number text-primary">${{ number_format($stats['avg_rate_per_mile'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="row g-3 mb-4">
            {{-- Loads by Origin State --}}
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Loads by Origin State') }}</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $originStates = $stats['loads_by_origin_state'] ?? collect();
                            $maxOrigin = $originStates->max('count') ?: 1;
                        @endphp
                        <div class="bar-chart">
                            @forelse($originStates as $row)
                                <div class="bar-row">
                                    <span class="bar-label fw-semibold">{{ $row->origin_state }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bg-primary" style="width: {{ ($row->count / $maxOrigin) * 100 }}%;">
                                            {{ $row->count }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ translate('No data available yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Loads by Equipment Type --}}
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Loads by Equipment Type') }}</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $equipTypes = $stats['loads_by_equipment_type'] ?? collect();
                            $maxEquip = $equipTypes->max('count') ?: 1;
                            $equipColors = ['dry_van' => 'primary', 'reefer' => 'info', 'flatbed' => 'warning', 'box_truck' => 'success', 'power_only' => 'secondary', 'step_deck' => 'danger', 'lowboy' => 'dark'];
                        @endphp
                        <div class="bar-chart">
                            @forelse($equipTypes as $row)
                                @php $color = $equipColors[$row->equipment_type] ?? 'primary'; @endphp
                                <div class="bar-row">
                                    <span class="bar-label fw-semibold">{{ ucwords(str_replace('_', ' ', $row->equipment_type)) }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bg-{{ $color }}" style="width: {{ ($row->count / $maxEquip) * 100 }}%;">
                                            {{ $row->count }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ translate('No data available yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
