@extends('business.layouts.app')

@section('title', translate('Dynamic Pricing'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Dynamic Pricing') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Dynamic Pricing') }}</h1>
        <p class="text-muted mb-0">{{ translate('Lane-level rate analysis from your last 50 rated loads') }}</p>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ translate('Lane Analysis') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr>
                        <th>{{ translate('Lane') }}</th><th>{{ translate('Loads') }}</th>
                        <th>{{ translate('Avg Payout') }}</th><th>{{ translate('Min') }}</th>
                        <th>{{ translate('Max') }}</th><th>{{ translate('Avg Distance') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse($laneAnalysis as $lane)
                        <tr>
                            <td>{{ $lane['lane'] }}</td>
                            <td>{{ $lane['count'] }}</td>
                            <td>${{ number_format($lane['avg_rate'] ?? 0, 2) }}</td>
                            <td>${{ number_format($lane['min_rate'] ?? 0, 2) }}</td>
                            <td>${{ number_format($lane['max_rate'] ?? 0, 2) }}</td>
                            <td>{{ number_format($lane['avg_distance'] ?? 0) }} mi</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('Not enough rated loads yet to analyze lanes.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
