@extends('layouts.admin.app')

@section('title', translate('Load Board Sourcing'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.5rem; font-weight: 700; }
    .source-badge-connected { background-color: #e6f4ea; color: #137333; }
    .source-badge-pending { background-color: #fef7e0; color: #b06000; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Breadcrumb & Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.index') }}">{{ translate('Dispatch Management') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-board.index') }}">{{ translate('Load Board') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Load Sourcing') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Load Sourcing Engine') }}</h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.urban-goodz.load-board.index') }}" class="btn btn-outline-secondary">
                    <i class="tio-truck"></i> {{ translate('Load Board') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.load-board-analytics') }}" class="btn btn-outline--primary">
                    <i class="tio-chart-bar-4"></i> {{ translate('Load Board Analytics') }}
                </a>
            </div>
        </div>

        <!-- Safety Callout Alert -->
        <div class="alert alert-info d-flex align-items-center justify-content-between mb-4">
            <div>
                <i class="tio-shield-alert text-info mr-2"></i>
                <strong>{{ translate('Manual Review & Dispatch Policy') }}:</strong>
                {{ translate('External load sourcing operates in Human-In-The-Loop mode. Autonomous load booking and auto-dispatch are strictly locked pending admin authorization.') }}
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-primary">{{ $stats['total_sources'] ?? count($sources) }}</div>
                        <small class="text-muted">{{ translate('Total Sources') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-success">{{ $stats['active_sources'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Active Connectors') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-info">{{ $stats['available_loads'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Sourced Available Loads') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-warning">{{ $stats['pending_review'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Pending Admin Review') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configured Sourcing Connectors -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ translate('Configured Load Sources & Connectors') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Source Name') }}</th>
                                <th>{{ translate('Key') }}</th>
                                <th>{{ translate('API Status') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Rate Limit') }}</th>
                                <th>{{ translate('Sourced Loads') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sources as $src)
                            <tr>
                                <td>
                                    <strong>{{ $src->name }}</strong>
                                    <br><small class="text-muted">{{ $src->description }}</small>
                                </td>
                                <td><code>{{ $src->source_key }}</code></td>
                                <td>
                                    @if($src->api_status === 'configured' || $src->api_status === 'connected')
                                        <span class="badge source-badge-connected"><i class="tio-checkmark-circle"></i> {{ ucfirst($src->api_status) }}</span>
                                    @else
                                        <span class="badge source-badge-pending"><i class="tio-time"></i> {{ ucfirst($src->api_status ?? 'pending') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($src->enabled)
                                        <span class="badge badge-soft-success">{{ translate('Enabled') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('Disabled') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $src->rate_limit_per_minute }}/min</small></td>
                                <td><strong>{{ $src->external_loads_count ?? 0 }}</strong></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.source-search', $src->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Trigger manual sync">
                                            <i class="tio-refresh"></i> {{ translate('Sync Source') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">{{ translate('No load sources configured.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sourcing Search Controls -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="tio-search mr-1"></i> {{ translate('Search & Ingest External Loads') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.search-all') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2 col-6">
                        <label class="form-label font-weight-bold">{{ translate('Origin State') }}</label>
                        <input type="text" name="origin_state" class="form-control form-control-sm" placeholder="e.g. TX" value="{{ request('origin_state') }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label font-weight-bold">{{ translate('Destination State') }}</label>
                        <input type="text" name="destination_state" class="form-control form-control-sm" placeholder="e.g. CA" value="{{ request('destination_state') }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label font-weight-bold">{{ translate('Equipment Type') }}</label>
                        <select name="equipment_type" class="form-control form-control-sm">
                            <option value="">{{ translate('All Equipment') }}</option>
                            <option value="dry_van">Dry Van</option>
                            <option value="reefer">Reefer</option>
                            <option value="flatbed">Flatbed</option>
                            <option value="box_truck">Box Truck</option>
                            <option value="power_only">Power Only</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label font-weight-bold">{{ translate('Min Rate ($)') }}</label>
                        <input type="number" step="50" name="min_rate" class="form-control form-control-sm" placeholder="500" value="{{ request('min_rate') }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label font-weight-bold">{{ translate('Max Deadhead (mi)') }}</label>
                        <input type="number" name="max_deadhead" class="form-control form-control-sm" placeholder="100" value="{{ request('max_deadhead', 100) }}">
                    </div>
                    <div class="col-md-2 col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn--primary btn-sm w-100">
                            <i class="tio-search"></i> {{ translate('Source Loads Now') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sourced External Loads Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Sourced External Loads') }}</h5>
                <form method="GET" class="d-flex gap-2">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Statuses') }}</option>
                        <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>{{ translate('Available') }}</option>
                        <option value="pending_review" {{ request('status') === 'pending_review' ? 'selected' : '' }}>{{ translate('Pending Review') }}</option>
                        <option value="booked" {{ request('status') === 'booked' ? 'selected' : '' }}>{{ translate('Booked') }}</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Ref ID</th>
                                <th>{{ translate('Source') }}</th>
                                <th>{{ translate('Route') }}</th>
                                <th>{{ translate('Equipment') }}</th>
                                <th>{{ translate('Payout') }}</th>
                                <th>{{ translate('Rate/Mile') }}</th>
                                <th>{{ translate('Compliance') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($externalLoads as $load)
                            <tr>
                                <td><strong>{{ $load->external_reference_id }}</strong></td>
                                <td><span class="badge badge-soft-info">{{ $load->source->name ?? 'External' }}</span></td>
                                <td>
                                    {{ $load->origin_city }}, {{ $load->origin_state }}
                                    &rarr;
                                    {{ $load->destination_city }}, {{ $load->destination_state }}
                                    <br><small class="text-muted">{{ number_format($load->distance_loaded ?? 0) }} mi</small>
                                </td>
                                <td><small>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</small></td>
                                <td><strong class="text-success">${{ number_format($load->payout_amount, 2) }}</strong></td>
                                <td><small>${{ number_format($load->rate_per_loaded_mile ?? 0, 2) }}/mi</small></td>
                                <td>
                                    @if($load->is_duplicate)
                                        <span class="badge badge-soft-warning">{{ translate('Duplicate') }}</span>
                                    @else
                                        <span class="badge badge-soft-success">{{ translate('Verified') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusBadges = ['available' => 'success', 'pending_review' => 'warning', 'booked' => 'info', 'cancelled' => 'danger'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $statusBadges[$load->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $load->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        @if($load->status === 'pending_review')
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.approve-load', $load->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Approve Load">
                                                <i class="tio-checkmark-circle"></i> {{ translate('Approve') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.reject-load', $load->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject Load">
                                                <i class="tio-clear"></i>
                                            </button>
                                        </form>
                                        @else
                                        <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    {{ translate('No external loads sourced yet. Click "Source Loads Now" to initiate API search.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $externalLoads->links() }}
            </div>
        </div>
    </div>
@endsection
