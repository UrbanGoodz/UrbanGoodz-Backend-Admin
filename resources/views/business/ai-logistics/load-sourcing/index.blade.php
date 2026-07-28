@extends('business.layouts.app')

@section('title', translate('Load Sourcing'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.5rem; font-weight: 700; }
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
                    <li class="breadcrumb-item active" aria-current="page">{{ translate('Load Sourcing') }}</li>
                </ol>
            </nav>
            <h1 class="page-header-title">{{ translate('Load Sourcing') }}</h1>
            <p class="text-muted mb-0">{{ translate('Find and source external loads for your fleet') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('business.ai-logistics.load-sourcing.search') }}" class="btn btn--primary" style="background-color: var(--ug-primary); color: #fff;">
                <i class="tio-search"></i> {{ translate('Search Loads') }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6" style="flex: 1; min-width: 180px;">
            <div class="card h-100" style="border-left: 4px solid #28a745;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">{{ translate('Available Loads') }}</h6>
                    <h3>{{ $availableCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6" style="flex: 1; min-width: 180px;">
            <div class="card h-100" style="border-left: 4px solid #17a2b8;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">{{ translate('My Fleet Matches') }}</h6>
                    <h3>{{ $fleetMatchCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6" style="flex: 1; min-width: 180px;">
            <div class="card h-100" style="border-left: 4px solid #ffc107;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">{{ translate('Saved Searches') }}</h6>
                    <h3>{{ $savedSearchCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6" style="flex: 1; min-width: 180px;">
            <div class="card h-100" style="border-left: 4px solid #007bff;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">{{ translate('Active Dispatches') }}</h6>
                    <h3>{{ $activeDispatchCount }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ translate('Available External Loads') }}</h5>
            <a href="{{ route('business.ai-logistics.load-sourcing.search') }}" class="btn btn-sm" style="background-color: var(--ug-primary); color: #fff;">
                {{ translate('Search More') }} <i class="tio-arrow-right"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ translate('Source') }}</th>
                            <th>{{ translate('Route') }}</th>
                            <th>{{ translate('Equipment') }}</th>
                            <th>{{ translate('Payout') }}</th>
                            <th>{{ translate('Rate/Mile') }}</th>
                            <th>{{ translate('Fleet Match') }}</th>
                            <th class="text-center">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($externalLoads as $load)
                        <tr>
                            <td><span class="badge badge-soft-info">{{ $load->source->name ?? $load->source_name ?? translate('External') }}</span></td>
                            <td>
                                {{ $load->origin_city }}, {{ $load->origin_state }}
                                &rarr;
                                {{ $load->destination_city }}, {{ $load->destination_state }}
                                <br><small class="text-muted">{{ $load->distance_loaded !== null ? number_format($load->distance_loaded) . ' mi' : translate('Distance unavailable') }}</small>
                            </td>
                            <td><small>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</small></td>
                            <td><strong class="text-success">${{ number_format($load->gross_rate ?? $load->payout_amount ?? 0, 2) }}</strong></td>
                            <td>{{ $load->rate_per_loaded_mile !== null ? '$' . number_format($load->rate_per_loaded_mile, 2) . '/mi' : translate('Unavailable') }}</td>
                            <td>
                                @if(($load->fleet_match_count ?? 0) > 0)
                                    <span class="fleet-match fleet-match-yes">{{ translate('Match') }}</span>
                                @else
                                    <span class="fleet-match fleet-match-no">{{ translate('Not recommended') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-outline-info btn-xs p-1" title="{{ translate('View') }}">
                                        <i class="tio-visible"></i>
                                    </button>
                                    <form method="POST" action="{{ route('business.ai-logistics.load-sourcing.search') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="save_load_id" value="{{ $load->id }}">
                                        <button type="submit" class="btn btn-outline-warning btn-xs p-1" title="{{ translate('Save Search') }}">
                                            <i class="tio-save"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                {{ translate('No external loads available. Try searching to source loads.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(isset($externalLoads) && $externalLoads instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">
            {{ $externalLoads->withQueryString()->links() }}
        </div>
        @endif
    </div>
@endsection
