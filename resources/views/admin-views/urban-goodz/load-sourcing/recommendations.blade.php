@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Recommendations'))

@push('css_or_js')
<style>
    .ai-explanation { background: #f8f9fa; border-radius: 8px; padding: 1rem; font-size: .85rem; }
    .ai-explanation dt { font-weight: 600; }
    .ai-explanation dd { margin-bottom: .5rem; }
    .score-badge { font-size: 1.1rem; font-weight: 700; }
    .confidence-high { color: #198754; }
    .confidence-medium { color: #fd7e14; }
    .confidence-low { color: #dc3545; }
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
                    <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn--primary btn-sm">
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Recommendations') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('AI Load Recommendations') }}</h1>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Driver') }}</label>
                        <select name="driver_id" class="form-control form-control-sm">
                            <option value="">{{ translate('All Drivers') }}</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ request('driver_id') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Min AI Score') }}</label>
                        <select name="min_score" class="form-control form-control-sm">
                            <option value="">{{ translate('Any') }}</option>
                            <option value="90" {{ request('min_score') === '90' ? 'selected' : '' }}>90+</option>
                            <option value="75" {{ request('min_score') === '75' ? 'selected' : '' }}>75+</option>
                            <option value="50" {{ request('min_score') === '50' ? 'selected' : '' }}>50+</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Status') }}</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                            <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>{{ translate('Dismissed') }}</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn--primary">
                            <i class="tio-filter"></i> {{ translate('Filter') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Recommendations --}}
        @forelse($recommendations as $rec)
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    {{-- Load Details --}}
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">
                                    {{ $rec->load->origin_city ?? '' }}, {{ $rec->load->origin_state ?? '' }}
                                    &rarr;
                                    {{ $rec->load->destination_city ?? '' }}, {{ $rec->load->destination_state ?? '' }}
                                </h5>
                                <span class="badge badge-soft-info">{{ $rec->load->source->name ?? translate('External') }}</span>
                                <code class="ms-1">{{ $rec->load->external_id ?? '' }}</code>
                            </div>
                            <div class="text-end">
                                <div class="score-badge {{ ($rec->ai_score ?? 0) >= 75 ? 'confidence-high' : (($rec->ai_score ?? 0) >= 50 ? 'confidence-medium' : 'confidence-low') }}">
                                    {{ $rec->ai_score ?? 0 }}
                                </div>
                                <small class="text-muted">{{ translate('AI Score') }}</small>
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Confidence') }}:</small>
                                <span class="badge badge-soft-{{ ($rec->confidence_level ?? '') === 'high' ? 'success' : (($rec->confidence_level ?? '') === 'medium' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($rec->confidence_level ?? translate('N/A')) }}
                                </span>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Est. Driver Net') }}:</small>
                                <strong class="text-success">${{ number_format($rec->estimated_driver_net ?? 0, 2) }}</strong>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Net/Mile') }}:</small>
                                <strong>${{ number_format($rec->net_per_mile ?? 0, 2) }}</strong>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Deadhead') }}:</small>
                                <span>{{ $rec->deadhead_miles ?? '—' }} mi</span>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Equipment Match') }}:</small>
                                {!! ($rec->equipment_match ?? false) ? '<span class="badge badge-soft-success">' . translate('Yes') . '</span>' : '<span class="badge badge-soft-danger">' . translate('No') . '</span>' !!}
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Certification Match') }}:</small>
                                {!! ($rec->certification_match ?? false) ? '<span class="badge badge-soft-success">' . translate('Yes') . '</span>' : '<span class="badge badge-soft-secondary">' . translate('No') . '</span>' !!}
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Schedule Feasible') }}:</small>
                                {!! ($rec->schedule_feasible ?? false) ? '<span class="badge badge-soft-success">' . translate('Yes') . '</span>' : '<span class="badge badge-soft-warning">' . translate('No') . '</span>' !!}
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">{{ translate('Broker Risk') }}:</small>
                                <span class="badge badge-soft-{{ ($rec->broker_risk ?? '') === 'low' ? 'success' : (($rec->broker_risk ?? '') === 'medium' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($rec->broker_risk ?? translate('N/A')) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="col-md-4 text-end">
                        <a href="{{ route('admin.urban-goodz.load-sourcing.show-load', $rec->load_id) }}" class="btn btn-sm btn-outline--primary mb-1">
                            <i class="tio-visible"></i> {{ translate('View Detail') }}
                        </a>
                        @if(($rec->status ?? 'pending') === 'pending')
                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.approve-recommendation', $rec->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success mb-1">
                                <i class="tio-checkmark-circle"></i> {{ translate('Approve') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.dismiss-recommendation', $rec->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger mb-1">
                                <i class="tio-clear"></i> {{ translate('Dismiss') }}
                            </button>
                        </form>
                        @endif
                        @php
                            $statusBadge = match($rec->status ?? 'pending') {
                                'approved' => 'badge-soft-success',
                                'dismissed' => 'badge-soft-secondary',
                                default => 'badge-soft-warning',
                            };
                        @endphp
                        <div class="mt-1">
                            <span class="badge {{ $statusBadge }}">{{ ucfirst($rec->status ?? translate('Pending')) }}</span>
                        </div>
                    </div>

                    {{-- AI Explanation --}}
                    <div class="col-12">
                        <div class="ai-explanation">
                            <h6 class="mb-2"><i class="tio-lightbulb"></i> {{ translate('AI Explanation') }}</h6>
                            <div class="row g-2">
                                @if($rec->why_recommended)
                                <div class="col-md-6">
                                    <dt>{{ translate('Why Recommended') }}</dt>
                                    <dd class="text-muted">{{ $rec->why_recommended }}</dd>
                                </div>
                                @endif
                                @if($rec->why_driver_eligible)
                                <div class="col-md-6">
                                    <dt>{{ translate('Why Driver Eligible') }}</dt>
                                    <dd class="text-muted">{{ $rec->why_driver_eligible }}</dd>
                                </div>
                                @endif
                                @if($rec->rate_quality_explanation)
                                <div class="col-md-6">
                                    <dt>{{ translate('Rate Quality') }}</dt>
                                    <dd class="text-muted">{{ $rec->rate_quality_explanation }}</dd>
                                </div>
                                @endif
                                @if($rec->deadhead_explanation)
                                <div class="col-md-6">
                                    <dt>{{ translate('Deadhead') }}</dt>
                                    <dd class="text-muted">{{ $rec->deadhead_explanation }}</dd>
                                </div>
                                @endif
                                @if($rec->equipment_fit_explanation)
                                <div class="col-md-6">
                                    <dt>{{ translate('Equipment Fit') }}</dt>
                                    <dd class="text-muted">{{ $rec->equipment_fit_explanation }}</dd>
                                </div>
                                @endif
                                @if($rec->schedule_fit_explanation)
                                <div class="col-md-6">
                                    <dt>{{ translate('Schedule Fit') }}</dt>
                                    <dd class="text-muted">{{ $rec->schedule_fit_explanation }}</dd>
                                </div>
                                @endif
                                @if($rec->missing_data_flags)
                                <div class="col-md-6">
                                    <dt>{{ translate('Missing Data') }}</dt>
                                    <dd>
                                        @foreach($rec->missing_data_flags as $flag)
                                            <span class="badge badge-soft-warning">{{ $flag }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                                @endif
                                @if($rec->risk_flags)
                                <div class="col-md-6">
                                    <dt>{{ translate('Risk Flags') }}</dt>
                                    <dd>
                                        @foreach($rec->risk_flags as $flag)
                                            <span class="badge badge-soft-danger">{{ $flag }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="tio-star" style="font-size: 3rem; opacity: .3;"></i>
                <h5 class="mt-3">{{ translate('No recommendations yet') }}</h5>
                <p class="text-muted">{{ translate('Run a search to generate AI-powered load recommendations.') }}</p>
                <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn--primary">
                    <i class="tio-search"></i> {{ translate('Search Loads') }}
                </a>
            </div>
        </div>
        @endforelse

        @if(isset($recommendations) && $recommendations instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="d-flex justify-content-end">
            {{ $recommendations->links() }}
        </div>
        @endif

    </div>
@endsection
