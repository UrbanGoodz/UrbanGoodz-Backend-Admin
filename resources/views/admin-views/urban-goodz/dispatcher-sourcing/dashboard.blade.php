@extends('layouts.admin.app')

@section('title', translate('Dispatcher Load Sourcing — Dashboard'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.5rem; font-weight: 700; }
    .ai-score { font-weight: 700; }
    .ai-score-high { color: #198754; }
    .ai-score-medium { color: #fd7e14; }
    .ai-score-low { color: #dc3545; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">

        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}" class="btn btn--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Dashboard') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Assignments') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Load Sourcing') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Dispatcher Load Sourcing Dashboard') }}</h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="btn btn--primary">
                    <i class="tio-search"></i> {{ translate('Search Loads') }}
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-primary">{{ $availableLoads }}</div>
                        <small class="text-muted">{{ translate('Available Loads') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-info">{{ $savedSearchCount }}</div>
                        <small class="text-muted">{{ translate('My Saved Searches') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-warning">{{ $assignmentCount }}</div>
                        <small class="text-muted">{{ translate('My Assignments') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-success">{{ $activeLoadCount }}</div>
                        <small class="text-muted">{{ translate('Active Loads') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="tio-search mr-1"></i> {{ translate('Quick Search') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Origin State') }}</label>
                        <input type="text" name="origin_state" class="form-control form-control-sm" placeholder="e.g. TX">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Destination State') }}</label>
                        <input type="text" name="destination_state" class="form-control form-control-sm" placeholder="e.g. CA">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Equipment Type') }}</label>
                        <select name="equipment_type" class="form-control form-control-sm">
                            <option value="">{{ translate('All Equipment') }}</option>
                            <option value="dry_van">{{ translate('Dry Van') }}</option>
                            <option value="reefer">{{ translate('Reefer') }}</option>
                            <option value="flatbed">{{ translate('Flatbed') }}</option>
                            <option value="box_truck">{{ translate('Box Truck') }}</option>
                            <option value="power_only">{{ translate('Power Only') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Min Rate ($)') }}</label>
                        <input type="number" step="50" name="min_rate" class="form-control form-control-sm" placeholder="500">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Max Deadhead (mi)') }}</label>
                        <input type="number" name="max_deadhead" class="form-control form-control-sm" placeholder="100" value="100">
                    </div>
                    <div class="col-md-2 col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn--primary btn-sm w-100">
                            <i class="tio-search"></i> {{ translate('Search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center py-3">
                        <i class="tio-search" style="font-size:1.5rem;color:var(--ug-primary);"></i>
                        <h6 class="mt-2 mb-0">{{ translate('Search') }}</h6>
                        <small class="text-muted">{{ translate('Find new loads') }}</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.saved-searches') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center py-3">
                        <i class="tio-save" style="font-size:1.5rem;color:#17a2b8;"></i>
                        <h6 class="mt-2 mb-0">{{ translate('Saved Searches') }}</h6>
                        <small class="text-muted">{{ translate('Manage saved criteria') }}</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center py-3">
                        <i class="tio-star" style="font-size:1.5rem;color:#ffc107;"></i>
                        <h6 class="mt-2 mb-0">{{ translate('My Assignments') }}</h6>
                        <small class="text-muted">{{ translate('Track assignments') }}</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-for-driver', ['driverId' => 0]) }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center py-3">
                        <i class="tio-users" style="font-size:1.5rem;color:#28a745;"></i>
                        <h6 class="mt-2 mb-0">{{ translate('Driver Matches') }}</h6>
                        <small class="text-muted">{{ translate('AI-matched drivers') }}</small>
                    </div>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ translate('Best Available Loads') }}</h5>
                <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn-sm btn-outline--primary">
                    {{ translate('View All') }} <i class="tio-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Source') }}</th>
                                <th>{{ translate('Route') }}</th>
                                <th>{{ translate('Equipment') }}</th>
                                <th>{{ translate('Payout') }}</th>
                                <th>{{ translate('Rate/Mile') }}</th>
                                <th>{{ translate('Deadhead') }}</th>
                                <th>{{ translate('AI Score') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topRecommendations as $rec)
                            @php($load = $rec->externalLoad)
                            <tr>
                                <td><span class="badge badge-soft-info">{{ $load->source->name ?? translate('External') }}</span></td>
                                <td>
                                    {{ $load->origin_city }}, {{ $load->origin_state }}
                                    &rarr;
                                    {{ $load->destination_city }}, {{ $load->destination_state }}
                                    <br><small class="text-muted">{{ number_format($load->distance_miles ?? 0) }} mi</small>
                                </td>
                                <td><small>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</small></td>
                                <td><strong class="text-success">${{ number_format($load->gross_rate ?? 0, 2) }}</strong></td>
                                <td>${{ number_format($load->rate_per_loaded_mile ?? 0, 2) }}/mi</td>
                                <td>{{ $load->distance_deadhead ?? '—' }} mi</td>
                                <td>
                                    @php
                                        $score = $rec->score ?? 0;
                                        $scoreClass = $score >= 75 ? 'ai-score-high' : ($score >= 50 ? 'ai-score-medium' : 'ai-score-low');
                                    @endphp
                                    <span class="ai-score {{ $scoreClass }}">{{ $score }}</span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline--primary" data-toggle="modal" data-target="#assignModal{{ $load->id }}" title="{{ translate('Assign to Driver') }}">
                                            <i class="tio-user"></i>
                                        </button>
                                        @if($load->source_url || ($load->source && $load->source->deep_link_template))
                                        <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.open-external', $load->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="{{ translate('Open External URL') }}">
                                            <i class="tio-launch"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ translate('No recommendations available. Run a search to find loads.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @foreach($topRecommendations as $rec)
    @php($load = $rec->externalLoad)
    <div class="modal fade" id="assignModal{{ $load->id }}" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.assign', $load->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ translate('Assign Load to Driver') }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>{{ $load->origin_city }}, {{ $load->origin_state }} &rarr; {{ $load->destination_city }}, {{ $load->destination_state }}</strong></p>
                        <div class="form-group">
                            <label class="form-label fw-bold">{{ translate('Select Driver') }}</label>
                            <select name="driver_id" class="form-control" required>
                                <option value="">{{ translate('Choose a driver...') }}</option>
                                @foreach($eligibleDrivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->f_name }} {{ $driver->l_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn--primary">{{ translate('Assign') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
@endsection
