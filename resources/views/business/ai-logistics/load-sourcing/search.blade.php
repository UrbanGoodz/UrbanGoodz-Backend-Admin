@extends('business.layouts.app')

@section('title', translate('Load Sourcing — Search'))

@push('css_or_js')
<style>
    .search-filters { background: #f8f9fa; border-radius: 8px; }
    .results-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
    .results-table td { font-size: .84rem; vertical-align: middle; }
    .fleet-match { font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; }
    .fleet-match-yes { background: #d4edda; color: #155724; }
    .fleet-match-no { background: #f8d7da; color: #721c24; }
</style>
@endpush

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
                    <li class="breadcrumb-item"><a href="#">{{ translate('AI Logistics') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('business.ai-logistics.load-sourcing.index') }}">{{ translate('Load Sourcing') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ translate('Search') }}</li>
                </ol>
            </nav>
            <h1 class="page-header-title">{{ translate('Search External Loads') }}</h1>
            <p class="text-muted mb-0">{{ translate('Search available loads from external load boards') }}</p>
        </div>
        <a href="{{ route('business.ai-logistics.load-sourcing.index') }}" class="btn btn-outline-secondary">
            <i class="tio-arrow-left"></i> {{ translate('Back to Sourcing') }}
        </a>
    </div>

    <form method="POST" action="{{ route('business.ai-logistics.load-sourcing.search') }}">
        @csrf
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="tio-search mr-1"></i> {{ translate('Search Criteria') }}</h5>
            </div>
            <div class="card-body search-filters">
                <div class="row g-3 mb-3">
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Origin City') }}</label>
                        <input type="text" name="origin_city" class="form-control form-control-sm" placeholder="{{ translate('e.g. Dallas') }}" value="{{ request('origin_city') }}">
                    </div>
                    <div class="col-md-1 col-6">
                        <label class="form-label fw-bold">{{ translate('Origin State') }}</label>
                        <input type="text" name="origin_state" class="form-control form-control-sm" placeholder="{{ translate('e.g. TX') }}" value="{{ request('origin_state') }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Destination City') }}</label>
                        <input type="text" name="destination_city" class="form-control form-control-sm" placeholder="{{ translate('e.g. Los Angeles') }}" value="{{ request('destination_city') }}">
                    </div>
                    <div class="col-md-1 col-6">
                        <label class="form-label fw-bold">{{ translate('Dest State') }}</label>
                        <input type="text" name="destination_state" class="form-control form-control-sm" placeholder="{{ translate('e.g. CA') }}" value="{{ request('destination_state') }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Equipment Type') }}</label>
                        <select name="vehicle_type" class="form-control form-control-sm">
                            <option value="">{{ translate('All Equipment') }}</option>
                            <option value="dry_van" {{ request('vehicle_type') === 'dry_van' ? 'selected' : '' }}>{{ translate('Dry Van') }}</option>
                            <option value="reefer" {{ request('vehicle_type') === 'reefer' ? 'selected' : '' }}>{{ translate('Reefer') }}</option>
                            <option value="flatbed" {{ request('vehicle_type') === 'flatbed' ? 'selected' : '' }}>{{ translate('Flatbed') }}</option>
                            <option value="box_truck" {{ request('vehicle_type') === 'box_truck' ? 'selected' : '' }}>{{ translate('Box Truck') }}</option>
                            <option value="power_only" {{ request('vehicle_type') === 'power_only' ? 'selected' : '' }}>{{ translate('Power Only') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Min Rate ($)') }}</label>
                        <input type="number" step="50" name="min_rate" class="form-control form-control-sm" placeholder="500" value="{{ request('min_rate') }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Max Distance (mi)') }}</label>
                        <input type="number" name="max_distance" class="form-control form-control-sm" placeholder="{{ translate('No limit') }}" value="{{ request('max_distance') }}">
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn--primary" style="background-color: var(--ug-primary); color: #fff;">
                        <i class="tio-search"></i> {{ translate('Search Now') }}
                    </button>
                    <a href="{{ route('business.ai-logistics.load-sourcing.search') }}" class="btn btn-outline-secondary">
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
                            <th>{{ translate('Origin') }}</th>
                            <th>{{ translate('Destination') }}</th>
                            <th>{{ translate('Equipment') }}</th>
                            <th>{{ translate('Rate') }}</th>
                            <th>{{ translate('Mileage') }}</th>
                            <th>{{ translate('Rate/Mile') }}</th>
                            <th>{{ translate('Fleet Match') }}</th>
                            <th class="text-center">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($searchResults as $load)
                        <tr>
                            <td><span class="badge badge-soft-info">{{ $load->source->name ?? translate('External') }}</span></td>
                            <td>{{ $load->origin_city }}, {{ $load->origin_state }}</td>
                            <td>{{ $load->destination_city }}, {{ $load->destination_state }}</td>
                            <td><small>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</small></td>
                            <td><strong class="text-success">${{ number_format($load->gross_rate ?? $load->payout_amount ?? 0, 2) }}</strong></td>
                            <td>{{ number_format($load->distance_miles ?? 0) }} mi</td>
                            <td>${{ number_format($load->rate_per_loaded_mile ?? $load->rate_per_mile ?? 0, 2) }}/mi</td>
                            <td>
                                @if($load->fleet_match ?? false)
                                    <span class="fleet-match fleet-match-yes">{{ translate('Match') }}</span>
                                @else
                                    <span class="fleet-match fleet-match-no">{{ translate('No Match') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-outline-info btn-xs p-1" title="{{ translate('View') }}">
                                        <i class="tio-visible"></i>
                                    </button>
                                    <form method="POST" action="{{ route('business.ai-logistics.dispatch.match-route') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="load_id" value="{{ $load->id }}">
                                        <button type="submit" class="btn btn-xs p-1" style="background-color: var(--ug-primary); color: #fff;" title="{{ translate('Request Dispatch') }}">
                                            <i class="tio-send"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @elseif(isset($searchResults) && count($searchResults) === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="tio-search" style="font-size: 3rem; opacity: .3;"></i>
            <h5 class="mt-3">{{ translate('No loads found') }}</h5>
            <p class="text-muted">{{ translate('Try adjusting your search criteria or broadening your filters.') }}</p>
        </div>
    </div>
    @endif
@endsection
