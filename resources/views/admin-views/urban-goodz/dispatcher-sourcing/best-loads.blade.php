@extends('layouts.admin.app')

@section('title', translate('Dispatcher Load Sourcing — Best Loads'))

@push('css_or_js')
<style>
    .status-badge { font-size: 0.75rem; }
    .best-loads-table-wrap { overflow-x: auto; }
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
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search-blade') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.saved-blade') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Best Loads') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Best Loads') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Best Loads') }}</h1>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-2">
                        <select name="origin_state" class="form-control form-control-sm">
                            <option value="">{{ translate('All Origin States') }}</option>
                            @foreach($originStates as $state)
                                <option value="{{ $state }}" {{ request('origin_state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="destination_state" class="form-control form-control-sm">
                            <option value="">{{ translate('All Destination States') }}</option>
                            @foreach($destinationStates as $state)
                                <option value="{{ $state }}" {{ request('destination_state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="sort" class="form-control form-control-sm">
                            <option value="gross_rate" {{ request('sort', 'gross_rate') === 'gross_rate' ? 'selected' : '' }}>{{ translate('Gross Rate') }}</option>
                            <option value="rate_per_loaded_mile" {{ request('sort') === 'rate_per_loaded_mile' ? 'selected' : '' }}>{{ translate('Rate / Loaded Mile') }}</option>
                            <option value="estimated_driver_net" {{ request('sort') === 'estimated_driver_net' ? 'selected' : '' }}>{{ translate('Estimated Driver Net') }}</option>
                            <option value="distance_deadhead" {{ request('sort') === 'distance_deadhead' ? 'selected' : '' }}>{{ translate('Deadhead') }}</option>
                            <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>{{ translate('Newest') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="direction" class="form-control form-control-sm">
                            <option value="desc" {{ request('direction', 'desc') === 'desc' ? 'selected' : '' }}>{{ translate('Descending') }}</option>
                            <option value="asc" {{ request('direction') === 'asc' ? 'selected' : '' }}>{{ translate('Ascending') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn--primary btn-sm">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn-outline-secondary btn-sm">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">
                    {{ translate('Recommended Loads') }}
                    <span class="badge badge-soft-info ml-1">{{ $loads->total() }}</span>
                </h5>
            </div>

            @if($loads->isEmpty())
                <div class="card-body text-center py-5">
                    <i class="tio-search-off" style="font-size: 2.5rem; opacity: .4;"></i>
                    <h5 class="mt-3 mb-1">{{ translate('No recommended loads are currently available') }}</h5>
                    <p class="text-muted mb-3">
                        {{ translate('Loads appear here once sourcing partners return available, non-duplicate results. Adjust the filters or refresh to check again.') }}
                    </p>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-refresh"></i> {{ translate('Refresh') }}
                    </a>
                </div>
            @else
                <div class="card-body p-0 best-loads-table-wrap">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('Reference') }}</th>
                                <th>{{ translate('Origin') }}</th>
                                <th>{{ translate('Destination') }}</th>
                                <th>{{ translate('Pickup') }}</th>
                                <th>{{ translate('Equipment') }}</th>
                                <th class="text-right">{{ translate('Rate') }}</th>
                                <th class="text-right">{{ translate('Distance') }}</th>
                                <th class="text-right">{{ translate('Est. Driver Net') }}</th>
                                <th class="text-right">{{ translate('Rate / Mile') }}</th>
                                <th>{{ translate('Source') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loads as $load)
                                <tr>
                                    <td>{{ $load->external_id ?? $load->broker_reference ?? '#'.$load->id }}</td>
                                    <td>{{ trim(($load->origin_city ?? '').' '.($load->origin_state ?? '')) ?: translate('Unavailable') }}</td>
                                    <td>{{ trim(($load->destination_city ?? '').' '.($load->destination_state ?? '')) ?: translate('Unavailable') }}</td>
                                    <td>{{ $load->pickup_start?->format('M d, Y') ?? translate('Not scheduled') }}</td>
                                    <td>{{ $load->equipment_type ?? $load->trailer_type ?? translate('Not specified') }}</td>
                                    <td class="text-right">{{ $load->gross_rate !== null ? '$'.number_format($load->gross_rate, 2) : translate('N/A') }}</td>
                                    <td class="text-right">
                                        {{ $load->distance_loaded !== null ? number_format($load->distance_loaded, 0).' '.translate('mi') : translate('N/A') }}
                                        @if($load->distance_deadhead !== null)
                                            <small class="text-muted d-block">{{ translate('DH') }} {{ number_format($load->distance_deadhead, 0) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $load->estimated_driver_net !== null ? '$'.number_format($load->estimated_driver_net, 2) : translate('N/A') }}</td>
                                    <td class="text-right">{{ $load->rate_per_loaded_mile !== null ? '$'.number_format($load->rate_per_loaded_mile, 2) : translate('N/A') }}</td>
                                    <td>{{ $load->source?->name ?? translate('Unknown') }}</td>
                                    <td>
                                        <span class="badge badge-soft-success status-badge">{{ translate($load->status) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.driver-matches-blade', $load->id) }}"
                                           class="btn btn-outline--primary btn-sm">
                                            <i class="tio-user"></i> {{ translate('Driver Matches') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($loads->hasPages())
                    <div class="card-footer">
                        {!! $loads->links() !!}
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
