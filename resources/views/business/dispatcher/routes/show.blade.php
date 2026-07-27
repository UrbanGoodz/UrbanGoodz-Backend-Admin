@extends('business.layouts.dispatcher')

@section('title', $route->route_name)

@section('content')
<div class="page-header d-flex justify-content-between">
    <h1 class="page-header-title">{{ $route->route_name }}</h1>
    <a href="{{ url('business/dispatcher/routes') }}" class="btn btn-secondary">{{ translate('Back') }}</a>
</div>
<div class="card mb-3">
    <div class="card-body row">
        <div class="col-md-3"><strong>{{ translate('Status') }}:</strong> {{ $route->status }}</div>
        <div class="col-md-3"><strong>{{ translate('Optimization') }}:</strong> {{ ucwords(str_replace('_', ' ', $route->optimization_status ?? 'not_optimized')) }}</div>
        <div class="col-md-3"><strong>{{ translate('Distance') }}:</strong> {{ $route->optimized_distance_miles !== null ? number_format($route->optimized_distance_miles, 2).' mi' : '—' }}</div>
        <div class="col-md-3"><strong>{{ translate('Duration') }}:</strong> {{ $route->optimized_duration_minutes !== null ? $route->optimized_duration_minutes.' min' : '—' }}</div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h5>{{ translate('Persisted Stop Sequence') }}</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>{{ translate('Tracking ID') }}</th><th>{{ translate('Address') }}</th><th>{{ translate('Package Status') }}</th></tr></thead>
            <tbody>
            @forelse($route->optimizationStops->sortBy('stop_order') as $stop)
                <tr><td>{{ $stop->stop_order }}</td><td>{{ $stop->package?->tracking_id }}</td><td>{{ $stop->package?->dropoff_address }}</td><td>{{ $stop->package?->status }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center">{{ translate('No persisted optimized stops') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
