@extends('layouts.admin.app')

@section('title', translate('Load Board Analytics'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.5rem; font-weight: 700; }
    .trend-up { color: #28a745; }
    .trend-down { color: #dc3545; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Load Board Analytics') }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-left"></i> {{ translate('Back to Copilot') }}
                </a>
                <a href="{{ route('admin.urban-goodz.load-board.index') }}" class="btn btn-outline--primary">
                    <i class="tio-truck"></i> {{ translate('View Load Board') }}
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">{{ translate('Total Loads') }}</div>
                        <div class="stat-number">{{ number_format($totalLoads) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">{{ translate('Active Loads') }}</div>
                        <div class="stat-number">{{ number_format($activeLoads) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">{{ translate('Avg Rate/Mi') }}</div>
                        <div class="stat-number">${{ number_format($avgRate ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">{{ translate('Total Payout') }}</div>
                        <div class="stat-number">${{ number_format($totalPayout, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">{{ translate('Pending Recs') }}</div>
                        <div class="stat-number">{{ $loadRecsPending }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">{{ translate('Executed Recs') }}</div>
                        <div class="stat-number">{{ $loadRecsExecuted }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Loads by Origin State') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>{{ translate('State') }}</th>
                                        <th class="text-end">{{ translate('Count') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($loadsByState as $row)
                                        <tr>
                                            <td>{{ $row->origin_state }}</td>
                                            <td class="text-end">{{ $row->count }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">{{ translate('No data') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Loads by Equipment Type') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>{{ translate('Equipment') }}</th>
                                        <th class="text-end">{{ translate('Count') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($loadsByEquipment as $row)
                                        <tr>
                                            <td>{{ ucfirst($row->equipment_type) }}</td>
                                            <td class="text-end">{{ $row->count }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">{{ translate('No data') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($weeklyTrend->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">{{ translate('Weekly Volume Trend') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm text-center mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Week') }}</th>
                                @foreach($weeklyTrend as $week)
                                    <th>{{ $week->w }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-muted">{{ translate('Loads') }}</td>
                                @foreach($weeklyTrend as $week)
                                    <td class="stat-number">{{ $week->count }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($loadRecStats->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">{{ translate('Load Board Recommendation Breakdown') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ translate('Type') }}</th>
                                <th class="text-center">{{ translate('Pending') }}</th>
                                <th class="text-center">{{ translate('Accepted') }}</th>
                                <th class="text-center">{{ translate('Dismissed') }}</th>
                                <th class="text-end">{{ translate('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loadRecStats as $type => $rows)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $type)) }}</td>
                                    <td class="text-center">{{ $rows->where('status', 'pending')->sum('count') }}</td>
                                    <td class="text-center">{{ $rows->where('status', 'accepted')->sum('count') }}</td>
                                    <td class="text-center">{{ $rows->where('status', 'dismissed')->sum('count') }}</td>
                                    <td class="text-end fw-bold">{{ $rows->sum('count') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ translate('Load Board Recommendations') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ translate('ID') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Subtype') }}</th>
                                <th>{{ translate('Action') }}</th>
                                <th class="text-center">{{ translate('Confidence') }}</th>
                                <th class="text-center">{{ translate('Status') }}</th>
                                <th>{{ translate('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loadRecs as $rec)
                                <tr>
                                    <td>#{{ $rec->id }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $rec->recommendation_type)) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $rec->recommendation_subtype)) }}</td>
                                    <td>{{ Str::limit($rec->suggested_action, 60) }}</td>
                                    <td class="text-center">
                                        @php $conf = $rec->confidence_score; @endphp
                                        <span class="badge badge-soft-{{ $conf >= 0.8 ? 'success' : ($conf >= 0.6 ? 'warning' : 'danger') }}">
                                            {{ number_format($conf * 100, 0) }}%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $statusClass = match($rec->status) {
                                                'pending' => 'warning',
                                                'accepted' => 'success',
                                                'dismissed' => 'secondary',
                                                'executed' => 'info',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="badge badge-soft-{{ $statusClass }}">{{ ucfirst($rec->status) }}</span>
                                    </td>
                                    <td>{{ $rec->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        {{ translate('No load board recommendations yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($loadRecs->hasPages())
                    <div class="card-footer">
                        {{ $loadRecs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
