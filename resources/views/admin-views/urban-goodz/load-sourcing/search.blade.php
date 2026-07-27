@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Search Loads'))

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

        {{-- Sub-Navigation --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Overview') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-link"></i> {{ translate('Sources') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn--primary btn-sm">
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Search Loads') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Search Loads') }}</h1>
            </div>
        </div>

        {{-- Search Form --}}
        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.search-all') }}" id="loadSearchForm">
            @csrf
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-search mr-1"></i> {{ translate('Search Criteria') }}</h5>
                </div>
                <div class="card-body search-filters">
                    {{-- Origin --}}
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
                            <label class="form-label fw-bold">{{ translate('Origin Radius (mi)') }}</label>
                            <input type="number" name="origin_radius" class="form-control form-control-sm" placeholder="100" value="{{ request('origin_radius', 100) }}">
                        </div>
                        {{-- Destination --}}
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Destination City') }}</label>
                            <input type="text" name="destination_city" class="form-control form-control-sm" placeholder="{{ translate('e.g. Los Angeles') }}" value="{{ request('destination_city') }}">
                        </div>
                        <div class="col-md-1 col-6">
                            <label class="form-label fw-bold">{{ translate('Dest State') }}</label>
                            <input type="text" name="destination_state" class="form-control form-control-sm" placeholder="{{ translate('e.g. CA') }}" value="{{ request('destination_state') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Dest Radius (mi)') }}</label>
                            <input type="number" name="destination_radius" class="form-control form-control-sm" placeholder="100" value="{{ request('destination_radius', 100) }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Pickup From') }}</label>
                            <input type="date" name="pickup_from" class="form-control form-control-sm" value="{{ request('pickup_from') }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Pickup To') }}</label>
                            <input type="date" name="pickup_to" class="form-control form-control-sm" value="{{ request('pickup_to') }}">
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
                                <option value="step_deck" {{ request('equipment_type') === 'step_deck' ? 'selected' : '' }}>{{ translate('Step Deck') }}</option>
                                <option value="lowboy" {{ request('equipment_type') === 'lowboy' ? 'selected' : '' }}>{{ translate('Lowboy') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Vehicle Type') }}</label>
                            <select name="vehicle_type" class="form-control form-control-sm">
                                <option value="">{{ translate('All Vehicle Types') }}</option>
                                <option value="solo" {{ request('vehicle_type') === 'solo' ? 'selected' : '' }}>{{ translate('Solo') }}</option>
                                <option value="team" {{ request('vehicle_type') === 'team' ? 'selected' : '' }}>{{ translate('Team') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Min Rate ($)') }}</label>
                            <input type="number" step="50" name="min_rate" class="form-control form-control-sm" placeholder="500" value="{{ request('min_rate') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Min Rate/Mile ($)') }}</label>
                            <input type="number" step="0.05" name="min_rate_per_mile" class="form-control form-control-sm" placeholder="1.50" value="{{ request('min_rate_per_mile') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Max Deadhead (mi)') }}</label>
                            <input type="number" name="max_deadhead" class="form-control form-control-sm" placeholder="100" value="{{ request('max_deadhead', 100) }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Weight (lbs)') }}</label>
                            <input type="number" name="weight" class="form-control form-control-sm" placeholder="{{ translate('Max weight') }}" value="{{ request('weight') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Load Type') }}</label>
                            <div class="d-flex gap-3 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="load_type" value="full" id="loadTypeFull" {{ request('load_type', 'full') === 'full' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="loadTypeFull">{{ translate('Full') }}</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="load_type" value="partial" id="loadTypePartial" {{ request('load_type') === 'partial' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="loadTypePartial">{{ translate('Partial') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Load Category') }}</label>
                            <select name="load_category" class="form-control form-control-sm">
                                <option value="">{{ translate('All Categories') }}</option>
                                <option value="general" {{ request('load_category') === 'general' ? 'selected' : '' }}>{{ translate('General Freight') }}</option>
                                <option value="expedited" {{ request('load_category') === 'expedited' ? 'selected' : '' }}>{{ translate('Expedited') }}</option>
                                <option value="hazmat" {{ request('load_category') === 'hazmat' ? 'selected' : '' }}>{{ translate('Hazmat') }}</option>
                                <option value="tanker" {{ request('load_category') === 'tanker' ? 'selected' : '' }}>{{ translate('Tanker') }}</option>
                                <option value="oversize" {{ request('load_category') === 'oversize' ? 'selected' : '' }}>{{ translate('Oversize') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-bold">{{ translate('Medical/Compliance') }}</label>
                            <select name="medical_compliance" class="form-control form-control-sm">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="none" {{ request('medical_compliance') === 'none' ? 'selected' : '' }}>{{ translate('None Required') }}</option>
                                <option value="medical_courier" {{ request('medical_compliance') === 'medical_courier' ? 'selected' : '' }}>{{ translate('Medical Courier') }}</option>
                                <option value="pharma" {{ request('medical_compliance') === 'pharma' ? 'selected' : '' }}>{{ translate('Pharmaceutical') }}</option>
                                <option value="temp_controlled" {{ request('medical_compliance') === 'temp_controlled' ? 'selected' : '' }}>{{ translate('Temp Controlled') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- Preferred Sources --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">{{ translate('Preferred Sources') }}</label>
                            <div class="d-flex flex-wrap gap-3">
                                @php
                                    $sourceOptions = ['internal' => 'Internal', 'manual' => 'Manual', 'email' => 'Email', 'dat' => 'DAT', 'truckstop' => 'Truckstop', 'trulos' => 'Trulos', 'tb_load' => 'TB Load', 'direct_freight' => 'Direct Freight', 'truckerpath' => 'TruckerPath', 'trucksmarter' => 'TruckSmarter'];
                                @endphp
                                @foreach($sourceOptions as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="preferred_sources[]" value="{{ $key }}" id="src_{{ $key }}" {{ in_array($key, request('preferred_sources', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="src_{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Exclusion Toggles --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="exclude_missing_rate" value="1" id="excludeMissingRate" {{ request('exclude_missing_rate') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="excludeMissingRate">{{ translate('Exclude Missing Rate') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="exclude_stale_loads" value="1" id="excludeStaleLoads" {{ request('exclude_stale_loads') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="excludeStaleLoads">{{ translate('Exclude Stale Loads') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="exclude_duplicates" value="1" id="excludeDuplicates" {{ request('exclude_duplicates') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="excludeDuplicates">{{ translate('Exclude Duplicates') }}</label>
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn--primary">
                            <i class="tio-search"></i> {{ translate('Search Now') }}
                        </button>
                        <button type="submit" formaction="{{ route('admin.urban-goodz.load-sourcing.save-search') }}" class="btn btn-outline--primary">
                            <i class="tio-save"></i> {{ translate('Save Search') }}
                        </button>
                        <button type="submit" formaction="{{ route('admin.urban-goodz.load-sourcing.schedule-search') }}" class="btn btn-outline--primary">
                            <i class="tio-calendar"></i> {{ translate('Schedule Search') }}
                        </button>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-outline-secondary">
                            <i class="tio-clear"></i> {{ translate('Reset') }}
                        </a>
                    </div>
                </div>
            </div>
        </form>

        {{-- Results Table --}}
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
                                <th>{{ translate('Pickup Date') }}</th>
                                <th>{{ translate('Equipment') }}</th>
                                <th>{{ translate('Rate') }}</th>
                                <th>{{ translate('Mileage') }}</th>
                                <th>{{ translate('Rate/Mile') }}</th>
                                <th>{{ translate('Deadhead') }}</th>
                                <th>{{ translate('Age') }}</th>
                                <th>{{ translate('Duplicate') }}</th>
                                <th>{{ translate('Validation') }}</th>
                                <th>{{ translate('AI Score') }}</th>
                                <th>{{ translate('Driver Matches') }}</th>
                                <th>{{ translate('Approval') }}</th>
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
                                <td><strong class="text-success">${{ number_format($load->payout_amount ?? 0, 2) }}</strong></td>
                                <td>{{ number_format($load->distance_loaded ?? 0) }} mi</td>
                                <td>${{ number_format($load->rate_per_loaded_mile ?? 0, 2) }}/mi</td>
                                <td>{{ $load->deadhead_miles ?? '—' }} mi</td>
                                <td><small class="text-muted">{{ $load->created_at ? $load->created_at->diffForHumans() : '—' }}</small></td>
                                <td>
                                    @if($load->is_duplicate)
                                        <span class="badge badge-soft-warning">{{ translate('Duplicate') }}</span>
                                    @else
                                        <span class="badge badge-soft-success">{{ translate('Unique') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($load->validation_status ?? '') === 'passed')
                                        <span class="badge badge-soft-success">{{ translate('Passed') }}</span>
                                    @elseif(($load->validation_status ?? '') === 'failed')
                                        <span class="badge badge-soft-danger">{{ translate('Failed') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('Pending') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $score = $load->ai_score ?? 0;
                                        $scoreClass = $score >= 75 ? 'ai-score-high' : ($score >= 50 ? 'ai-score-medium' : 'ai-score-low');
                                    @endphp
                                    <span class="ai-score {{ $scoreClass }}">{{ $score }}</span>
                                </td>
                                <td class="text-center">{{ $load->driver_matches_count ?? 0 }}</td>
                                <td>
                                    @php
                                        $approvalBadges = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'draft' => 'secondary'];
                                        $approval = $load->approval_status ?? 'pending';
                                    @endphp
                                    <span class="badge badge-soft-{{ $approvalBadges[$approval] ?? 'secondary' }}">{{ ucfirst($approval) }}</span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="{{ route('admin.urban-goodz.load-sourcing.show-load', $load->id) }}" class="btn btn-sm btn-outline--primary" title="{{ translate('View') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                        @if(($load->approval_status ?? 'pending') === 'pending')
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.approve-load', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="{{ translate('Approve') }}">
                                                <i class="tio-checkmark-circle"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.reject-load', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Reject') }}">
                                                <i class="tio-clear"></i>
                                            </button>
                                        </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.import-load', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ translate('Import') }}">
                                                <i class="tio-download"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.publish-load', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="{{ translate('Publish to Load Board') }}">
                                                <i class="tio-send"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.assign-dispatcher', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ translate('Assign to Dispatcher') }}">
                                                <i class="tio-user"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.recommend-driver', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ translate('Recommend Driver') }}">
                                                <i class="tio-star"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.archive-load', $load->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-dark" title="{{ translate('Archive') }}">
                                                <i class="tio-archive"></i>
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

    </div>
@endsection
