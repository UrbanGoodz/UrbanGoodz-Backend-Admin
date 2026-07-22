@extends('layouts.admin.app')

@section('title', translate('Dispatcher Load Sourcing — Search'))

@push('css_or_js')
<style>
    .search-filters { background: #f8f9fa; border-radius: 8px; }
    .results-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
    .results-table td { font-size: .84rem; vertical-align: middle; }
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
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Dashboard') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="btn btn--primary btn-sm">
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
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Search') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Search Loads') }}</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}">
            @csrf
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-search mr-1"></i> {{ translate('Search Criteria') }}</h5>
                </div>
                <div class="card-body search-filters">
                    <div class="row g-3 mb-3">
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Origin State') }}</label>
                            <input type="text" name="origin_state" class="form-control form-control-sm" placeholder="e.g. TX" value="{{ request('origin_state') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Destination State') }}</label>
                            <input type="text" name="destination_state" class="form-control form-control-sm" placeholder="e.g. CA" value="{{ request('destination_state') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Equipment Type') }}</label>
                            <select name="equipment_type" class="form-control form-control-sm">
                                <option value="">{{ translate('All Equipment') }}</option>
                                <option value="dry_van" {{ request('equipment_type') === 'dry_van' ? 'selected' : '' }}>{{ translate('Dry Van') }}</option>
                                <option value="reefer" {{ request('equipment_type') === 'reefer' ? 'selected' : '' }}>{{ translate('Reefer') }}</option>
                                <option value="flatbed" {{ request('equipment_type') === 'flatbed' ? 'selected' : '' }}>{{ translate('Flatbed') }}</option>
                                <option value="box_truck" {{ request('equipment_type') === 'box_truck' ? 'selected' : '' }}>{{ translate('Box Truck') }}</option>
                                <option value="power_only" {{ request('equipment_type') === 'power_only' ? 'selected' : '' }}>{{ translate('Power Only') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Min Rate ($)') }}</label>
                            <input type="number" step="50" name="min_rate" class="form-control form-control-sm" placeholder="500" value="{{ request('min_rate') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Max Deadhead (mi)') }}</label>
                            <input type="number" name="max_deadhead" class="form-control form-control-sm" placeholder="100" value="{{ request('max_deadhead', 100) }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Weight (lbs)') }}</label>
                            <input type="number" name="weight_max" class="form-control form-control-sm" placeholder="{{ translate('Max weight') }}" value="{{ request('weight_max') }}">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Pickup From') }}</label>
                            <input type="date" name="pickup_date_from" class="form-control form-control-sm" value="{{ request('pickup_date_from') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Pickup To') }}</label>
                            <input type="date" name="pickup_date_to" class="form-control form-control-sm" value="{{ request('pickup_date_to') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Max Results') }}</label>
                            <input type="number" name="max_results" class="form-control form-control-sm" placeholder="50" value="{{ request('max_results', 50) }}">
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn--primary">
                            <i class="tio-search"></i> {{ translate('Search Now') }}
                        </button>
                        <button type="submit" formaction="{{ route('admin.urban-goodz.dispatcher-sourcing.save-search') }}" class="btn btn-outline--primary">
                            <i class="tio-save"></i> {{ translate('Save Search') }}
                        </button>
                        <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="btn btn-outline-secondary">
                            <i class="tio-clear"></i> {{ translate('Reset') }}
                        </a>
                    </div>
                </div>
            </div>
        </form>

        @if(isset($searchResults) && count($searchResults) > 0)
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ translate('Search Results') }} <span class="badge badge-soft-info">{{ count($searchResults) }}</span></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 results-table">
                        <thead>
                            <tr>
                                <th>{{ translate('Source') }}</th>
                                <th>{{ translate('External ID') }}</th>
                                <th>{{ translate('Origin') }}</th>
                                <th>{{ translate('Destination') }}</th>
                                <th>{{ translate('Pickup') }}</th>
                                <th>{{ translate('Equipment') }}</th>
                                <th>{{ translate('Rate') }}</th>
                                <th>{{ translate('Mileage') }}</th>
                                <th>{{ translate('Rate/Mile') }}</th>
                                <th>{{ translate('Deadhead') }}</th>
                                <th>{{ translate('Duplicate') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($searchResults as $load)
                            <tr>
                                <td><span class="badge badge-soft-info">{{ $load->source->name ?? translate('External') }}</span></td>
                                <td><code>{{ $load->external_reference_id }}</code></td>
                                <td>{{ $load->origin_city }}, {{ $load->origin_state }}</td>
                                <td>{{ $load->destination_city }}, {{ $load->destination_state }}</td>
                                <td><small>{{ $load->pickup_date ? \Carbon\Carbon::parse($load->pickup_date)->format('M d, Y') : translate('N/A') }}</small></td>
                                <td><small>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</small></td>
                                <td><strong class="text-success">${{ number_format($load->gross_rate ?? $load->payout_amount ?? 0, 2) }}</strong></td>
                                <td>{{ number_format($load->distance_miles ?? 0) }} mi</td>
                                <td>${{ number_format($load->rate_per_loaded_mile ?? $load->rate_per_mile ?? 0, 2) }}/mi</td>
                                <td>{{ $load->distance_deadhead ?? $load->deadhead_miles ?? '—' }} mi</td>
                                <td>
                                    @if($load->is_duplicate)
                                        <span class="badge badge-soft-warning">{{ translate('Duplicate') }}</span>
                                    @else
                                        <span class="badge badge-soft-success">{{ translate('Unique') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @foreach($searchResults as $load)
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
                                    @foreach($eligibleDrivers ?? [] as $driver)
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

        @elseif(isset($searchResults) && count($searchResults) === 0)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="tio-search" style="font-size: 3rem; opacity: .3;"></i>
                <h5 class="mt-3">{{ translate('No loads found') }}</h5>
                <p class="text-muted">{{ translate('Try adjusting your search criteria or broadening your filters.') }}</p>
            </div>
        </div>
        @endif

    </div>
@endsection
