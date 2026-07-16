@extends('layouts.admin.app')

@section('title', translate('AI Load Sourcing Status'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.4rem; font-weight: 700; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to AI Operations') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Load Sourcing Status') }}</h1>
        </div>

        {{-- Load Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--primary">{{ number_format($stats['total_loads']) }}</div>
                        <small class="text-muted">{{ translate('Total') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--success">{{ number_format($stats['available']) }}</div>
                        <small class="text-muted">{{ translate('Available') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--info">{{ number_format($stats['assigned']) }}</div>
                        <small class="text-muted">{{ translate('Assigned') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--warning">{{ number_format($stats['in_transit']) }}</div>
                        <small class="text-muted">{{ translate('In Transit') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--secondary">{{ number_format($stats['delivered']) }}</div>
                        <small class="text-muted">{{ translate('Delivered') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--danger">{{ number_format($stats['cancelled']) }}</div>
                        <small class="text-muted">{{ translate('Cancelled') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Financials --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Financial Summary') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ translate('Total Payout Value') }}</small>
                            <strong style="font-size: 1.3rem;">${{ number_format($stats['total_payout'] ?? 0, 2) }}</strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ translate('Avg Rate Per Mile') }}</small>
                            <strong>${{ number_format($stats['avg_rate_per_mile'] ?? 0, 2) }}</strong>
                        </div>
                        <div>
                            <small class="text-muted d-block">{{ translate('Unassigned Available Loads') }}</small>
                            <strong class="{{ $stats['unassigned_count'] > 0 ? 'text--warning' : '' }}">{{ number_format($stats['unassigned_count']) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- By State --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Loads by Origin State') }}</h5>
                    </div>
                    <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                        @forelse($stats['by_state'] as $state => $count)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ $state }}</small>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: {{ min($count * 2, 150) }}px; height: 8px; background: #6f42c1; border-radius: 2px;"></div>
                                <small class="fw-bold">{{ $count }}</small>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted text-center py-3">{{ translate('No state data available.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- By Equipment --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Loads by Equipment Type') }}</h5>
                    </div>
                    <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                        @forelse($stats['by_equipment'] as $equip => $count)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ $equip }}</small>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: {{ min($count * 2, 150) }}px; height: 8px; background: #fd7e14; border-radius: 2px;"></div>
                                <small class="fw-bold">{{ $count }}</small>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted text-center py-3">{{ translate('No equipment data available.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Recommendations --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">
                    {{ translate('Pending Load Board Recommendations') }}
                    <span class="badge badge-soft-warning ms-2">{{ $pendingRecommendations }}</span>
                </h5>
                <a href="{{ route('admin.urban-goodz.ai-copilot.index', ['type' => 'load_board']) }}" class="btn btn-sm btn-outline--primary">
                    {{ translate('View in Copilot') }}
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Action') }}</th>
                                <th>{{ translate('Confidence') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Created') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRecommendations as $rec)
                            <tr>
                                <td>{{ $rec->id }}</td>
                                <td>
                                    <span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $rec->recommendation_type)) }}</span>
                                </td>
                                <td style="max-width: 250px;">
                                    <small class="text-truncate d-block">{{ $rec->suggested_action }}</small>
                                </td>
                                <td>
                                    @if($rec->confidence_score)
                                    <small>{{ number_format($rec->confidence_score * 100) }}%</small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php $sColors = ['pending' => 'warning', 'accepted' => 'success', 'dismissed' => 'secondary']; @endphp
                                    <span class="badge badge-soft-{{ $sColors[$rec->status] ?? 'secondary' }}">{{ ucfirst($rec->status) }}</span>
                                </td>
                                <td><small>{{ $rec->created_at->format('M d, h:i A') }}</small></td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.ai-copilot.show', $rec->id) }}" class="btn btn-sm btn--primary">
                                        <i class="tio-visible"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No recent load board recommendations.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
