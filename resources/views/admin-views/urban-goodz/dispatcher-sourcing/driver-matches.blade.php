@extends('layouts.admin.app')

@section('title', translate('Dispatcher Load Sourcing — AI Driver Matches'))

@push('css_or_js')
<style>
    .match-score { font-weight: 700; font-size: 1.2rem; }
    .match-score-high { color: #198754; }
    .match-score-medium { color: #fd7e14; }
    .match-score-low { color: #dc3545; }
    .match-explanation { font-size: 0.85rem; color: #6c757d; border-left: 3px solid #dee2e6; padding-left: 10px; margin: 4px 0; }
    .match-explanation.positive { border-left-color: #198754; }
    .match-explanation.negative { border-left-color: #dc3545; }
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('AI Driver Matches') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('AI Driver Matches') }}</h1>
            </div>
            <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}" class="btn btn-outline-secondary">
                <i class="tio-arrow-left"></i> {{ translate('Back to Dashboard') }}
            </a>
        </div>

        @if($load)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="tio-truck mr-1"></i> {{ translate('Load Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Origin') }}</small>
                        <strong>{{ $load->origin_city }}, {{ $load->origin_state }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Destination') }}</small>
                        <strong>{{ $load->destination_city }}, {{ $load->destination_state }}</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">{{ translate('Distance') }}</small>
                        <strong>{{ number_format($load->distance_miles ?? 0) }} mi</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">{{ translate('Payout') }}</small>
                        <strong class="text-success">${{ number_format($load->gross_rate ?? $load->payout_amount ?? 0, 2) }}</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">{{ translate('Equipment') }}</small>
                        <strong>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ translate('Ranked Eligible Drivers') }} <span class="badge badge-soft-info">{{ count($driverMatches) }}</span></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Vehicle') }}</th>
                                <th>{{ translate('Current Zone') }}</th>
                                <th>{{ translate('Active Dispatches') }}</th>
                                <th>{{ translate('AI Score') }}</th>
                                <th>{{ translate('AI Explanation') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($driverMatches as $index => $match)
                            @php($driver = $match['driver'] ?? null)
                            @php($score = $match['score'] ?? 0)
                            @php($reasons = $match['reasons'] ?? [])
                            <tr>
                                <td><strong>{{ $index + 1 }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="mr-2">
                                            @if(isset($driver->image) && $driver->image)
                                            <img src="{{ asset('storage/' . $driver->image) }}" alt="" class="avatar avatar-sm avatar-circle">
                                            @else
                                            <div class="avatar avatar-sm avatar-circle bg-light"><i class="tio-user"></i></div>
                                            @endif
                                        </div>
                                        <div>
                                            <strong>{{ $driver->f_name ?? '' }} {{ $driver->l_name ?? '' }}</strong>
                                            <br><small class="text-muted">{{ $driver->phone ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><small>{{ ucwords(str_replace('_', ' ', $driver->vehicle_type ?? 'N/A')) }}</small></td>
                                <td><small>{{ $driver->zone_id ?? '—' }}</small></td>
                                <td class="text-center">{{ $match['active_dispatches'] ?? 0 }}</td>
                                <td>
                                    @php
                                        $scoreClass = $score >= 75 ? 'match-score-high' : ($score >= 50 ? 'match-score-medium' : 'match-score-low');
                                    @endphp
                                    <span class="match-score {{ $scoreClass }}">{{ $score }}</span>
                                </td>
                                <td style="max-width: 300px;">
                                    @if(!empty($reasons))
                                        @foreach($reasons as $reason)
                                        <div class="match-explanation {{ str_contains(strtolower($reason), ['match', 'near', 'eligible', 'good']) ? 'positive' : 'negative' }}">
                                            {{ $reason }}
                                        </div>
                                        @endforeach
                                    @else
                                        <small class="text-muted">{{ translate('No detailed explanation available') }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        @if($load)
                                        <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.assign', $load->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
                                            <button type="submit" class="btn btn-sm btn--primary" title="{{ translate('Send Offer') }}">
                                                <i class="tio-send"></i> {{ translate('Send Offer') }}
                                            </button>
                                        </form>
                                        @endif
                                        <a href="#" class="btn btn-sm btn-outline--primary" title="{{ translate('View Driver Profile') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ translate('No eligible drivers found for this load.') }}
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
