@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Overview'))

@push('css_or_js')
<style>
    .stat-card { border-radius: 8px; transition: box-shadow .2s; }
    .stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .stat-number { font-size: 1.6rem; font-weight: 700; }
    .bar-chart .bar-row { display: flex; align-items: center; margin-bottom: .4rem; }
    .bar-chart .bar-label { width: 120px; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-chart .bar-track { flex: 1; height: 22px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
    .bar-chart .bar-fill { height: 100%; border-radius: 4px; display: flex; align-items: center; padding-left: 6px; font-size: .72rem; font-weight: 600; color: #fff; min-width: 24px; }
    .ls-empty { padding: 1.25rem; text-align: center; color: #8a94a6; font-size: .85rem; }
    .ls-table td, .ls-table th { font-size: .82rem; vertical-align: middle; }
    .ls-section-title { font-size: 1rem; margin-bottom: 0; }
    .cred-pill { font-size: .7rem; padding: .15rem .45rem; border-radius: 10px; }
    .audit-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: .4rem; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">

        {{-- ── Sub-Navigation ─────────────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($nav ?? [] as $item)
                        <a href="{{ route($item['route']) }}"
                           class="btn btn-sm {{ $item['active'] ? 'btn--primary' : 'btn-outline--primary' }}">
                            {{ translate($item['label']) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Breadcrumb & Header ────────────────────────────────────── --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Overview') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Load Sourcing Overview') }}</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn--primary">
                    <i class="tio-search"></i> {{ translate('Source Loads') }}
                </a>
                <a href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" class="btn btn-outline--primary">
                    <i class="tio-settings-outlined"></i> {{ translate('Settings') }}
                </a>
            </div>
        </div>

        {{-- ── Error / status states ──────────────────────────────────── --}}
        @if(!empty($overviewError))
            <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                <i class="tio-warning mr-2"></i>
                <div><strong>{{ translate('Overview degraded') }}:</strong> {{ $overviewError }}</div>
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info mb-3">{{ session('info') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div id="ls-ajax-alert" class="alert d-none mb-3" role="alert"></div>

        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="tio-shield-alert text-info mr-2"></i>
            <div>
                <strong>{{ translate('Human-In-The-Loop Mode') }}:</strong>
                {{ translate('External load sourcing operates under manual review. Autonomous booking and auto-dispatch are locked pending admin authorization.') }}
            </div>
        </div>

        {{-- ── Overview statistics ────────────────────────────────────── --}}
        <div class="row g-3 mb-3">
            @php
                $cards = [
                    ['label' => 'Total Loads',    'value' => $stats['total_loads'] ?? 0,    'class' => 'text-primary', 'status' => null],
                    ['label' => 'Sourced',        'value' => $stats['sourced'] ?? 0,        'class' => 'text-secondary', 'status' => 'sourced'],
                    ['label' => 'Pending Review', 'value' => $stats['pending_review'] ?? 0, 'class' => 'text-warning', 'status' => 'pending_review'],
                    ['label' => 'Approved',       'value' => $stats['approved'] ?? 0,       'class' => 'text-info', 'status' => 'approved'],
                    ['label' => 'Available',      'value' => $stats['available'] ?? 0,      'class' => 'text-success', 'status' => 'available'],
                    ['label' => 'Booked',         'value' => $stats['booked'] ?? 0,         'class' => 'text-dark', 'status' => 'booked'],
                    ['label' => 'Expired',        'value' => $stats['expired'] ?? 0,        'class' => 'text-muted', 'status' => 'expired'],
                    ['label' => 'Cancelled',      'value' => $stats['cancelled'] ?? 0,      'class' => 'text-danger', 'status' => 'cancelled'],
                ];
            @endphp
            @foreach($cards as $card)
                <div class="col-md-3 col-6">
                    <a href="{{ $card['status'] ? route('admin.urban-goodz.load-sourcing.sourced-loads', ['status' => $card['status']]) : route('admin.urban-goodz.load-sourcing.sourced-loads') }}"
                       class="text-decoration-none">
                        <div class="card stat-card h-100">
                            <div class="card-body text-center py-3">
                                <div class="stat-number {{ $card['class'] }}">{{ $card['value'] }}</div>
                                <small class="text-muted">{{ translate($card['label']) }}</small>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card"><div class="card-body py-3">
                    <small class="text-muted d-block">{{ translate('Total Payout Value') }}</small>
                    <div class="stat-number text-success">${{ number_format($stats['total_payout'] ?? 0, 2) }}</div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card"><div class="card-body py-3">
                    <small class="text-muted d-block">{{ translate('Avg Rate / Loaded Mile') }}</small>
                    <div class="stat-number text-primary">${{ number_format($stats['avg_rate_per_mile'] ?? 0, 2) }}</div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card"><div class="card-body py-3">
                    <small class="text-muted d-block">{{ translate('Unassigned (Available)') }}</small>
                    <div class="stat-number text-warning">{{ $stats['unassigned_count'] ?? 0 }}</div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card"><div class="card-body py-3">
                    <small class="text-muted d-block">{{ translate('Duplicates Detected') }}</small>
                    <div class="stat-number text-danger">{{ $stats['duplicates'] ?? 0 }}</div>
                </div></div>
            </div>
        </div>

        {{-- ── Sync status & global scheduling ────────────────────────── --}}
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="ls-section-title">{{ translate('Sync Status') }}</h5>
                        <button type="button" class="btn btn-sm btn--primary" id="ls-sync-all">
                            <i class="tio-refresh"></i> {{ translate('Run Manual Sync (All Enabled Sources)') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted d-block">{{ translate('Last Sync') }}</small>
                                <strong>{{ $lastSyncAt ? $lastSyncAt->format('M d, Y H:i') : translate('Never') }}</strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">{{ translate('Next Scheduled Sync') }}</small>
                                <strong>{{ $nextScheduledSync ? $nextScheduledSync->format('M d, Y H:i') : translate('Not scheduled') }}</strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">{{ translate('Refresh Interval') }}</small>
                                <strong>{{ $refreshMinutes }} {{ translate('minutes') }}</strong>
                            </div>
                        </div>
                        @if(($sourceSummary['enabled'] ?? 0) === 0)
                            <div class="alert alert-warning mt-3 mb-0 py-2">
                                <i class="tio-warning"></i>
                                {{ translate('No load source is enabled, so no sync will run. Enable a source below to begin sourcing.') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Global Scheduling Settings') }}</h5></div>
                    <div class="card-body">
                        @php
                            $schedKeys = [
                                'default_source_refresh_minutes' => 'Source refresh (min)',
                                'saved_search_refresh_minutes'   => 'Saved search refresh (min)',
                                'max_load_age_hours'             => 'Max load age (hrs)',
                                'auto_approve_threshold'         => 'Auto-approve threshold',
                            ];
                        @endphp
                        <table class="table table-sm mb-2 ls-table">
                            <tbody>
                            @foreach($schedKeys as $key => $label)
                                <tr>
                                    <td class="text-muted">{{ translate($label) }}</td>
                                    <td class="text-right">
                                        <strong>{{ $settings[$key] ?? translate('default') }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" class="btn btn-sm btn-outline--primary btn-block">
                            <i class="tio-settings-outlined"></i> {{ translate('Edit Scheduling Settings') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Source health / enabled-disabled / credentials / config ─── --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="ls-section-title">{{ translate('Load Source Health') }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge badge-soft-primary">{{ translate('Total') }}: {{ $sourceSummary['total'] ?? 0 }}</span>
                    <span class="badge badge-soft-success">{{ translate('Enabled') }}: {{ $sourceSummary['enabled'] ?? 0 }}</span>
                    <span class="badge badge-soft-secondary">{{ translate('Disabled') }}: {{ $sourceSummary['disabled'] ?? 0 }}</span>
                    <span class="badge badge-soft-info">{{ translate('Connected') }}: {{ $sourceSummary['connected'] ?? 0 }}</span>
                    <span class="badge badge-soft-danger">{{ translate('Errored') }}: {{ $sourceSummary['errored'] ?? 0 }}</span>
                    <span class="badge badge-soft-warning">{{ translate('No Credentials') }}: {{ $sourceSummary['no_credentials'] ?? 0 }}</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 ls-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Source') }}</th>
                            <th>{{ translate('Enabled') }}</th>
                            <th>{{ translate('API Status') }}</th>
                            <th>{{ translate('Credentials') }}</th>
                            <th>{{ translate('Last Sync') }}</th>
                            <th>{{ translate('Last Success') }}</th>
                            <th>{{ translate('Next Due') }}</th>
                            <th class="text-right">{{ translate('Loads') }}</th>
                            <th class="text-right">{{ translate('Errors') }}</th>
                            <th class="text-right">{{ translate('Controls') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($sourceHealth as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['source']->name }}</strong>
                                <br><small class="text-muted">{{ $row['source']->source_key }} · {{ $row['source']->type }}</small>
                                @if($row['last_error_message'])
                                    <br><small class="text-danger">{{ \Illuminate\Support\Str::limit($row['last_error_message'], 70) }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-soft-{{ $row['enabled'] ? 'success' : 'secondary' }}">
                                    {{ $row['enabled'] ? translate('Enabled') : translate('Disabled') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-soft-{{ $row['api_status'] === 'connected' ? 'success' : ($row['api_status'] === 'error' ? 'danger' : 'warning') }}">
                                    {{ str_replace('_', ' ', (string) $row['api_status']) }}
                                </span>
                            </td>
                            <td>
                                @forelse($row['credentials'] as $cred)
                                    {{-- Secret values are never loaded or rendered — status metadata only. --}}
                                    <span class="cred-pill badge badge-soft-{{ $cred['is_expired'] ? 'danger' : ($cred['status'] === 'active' ? 'success' : 'warning') }}"
                                          title="{{ translate('Last validated') }}: {{ $cred['last_validated_at']?->format('M d, Y H:i') ?? translate('never') }}">
                                        {{ $cred['key'] }}: {{ $cred['is_expired'] ? translate('expired') : $cred['status'] }}
                                    </span>
                                @empty
                                    <small class="text-muted">{{ translate('None configured') }}</small>
                                @endforelse
                            </td>
                            <td>{{ $row['last_sync_at']?->format('M d H:i') ?? '—' }}</td>
                            <td>{{ $row['last_success_at']?->format('M d H:i') ?? '—' }}</td>
                            <td>
                                @if($row['next_sync_due_at'])
                                    <span class="{{ $row['is_overdue'] ? 'text-danger' : '' }}">{{ $row['next_sync_due_at']->format('M d H:i') }}</span>
                                @else
                                    <small class="text-muted">{{ translate('n/a') }}</small>
                                @endif
                            </td>
                            <td class="text-right">{{ $row['loads_sourced'] }}</td>
                            <td class="text-right">
                                <span class="{{ $row['error_count'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $row['error_count'] }}</span>
                            </td>
                            <td class="text-right text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline--primary ls-action"
                                        data-url="{{ route('admin.urban-goodz.load-sourcing.api.toggle-source', $row['source']->id) }}"
                                        title="{{ translate('Enable / disable this source') }}">
                                    <i class="tio-toggle-on"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline--primary ls-action"
                                        data-url="{{ route('admin.urban-goodz.load-sourcing.api.test-connection', $row['source']->id) }}"
                                        title="{{ translate('Test connection') }}">
                                    <i class="tio-checkmark-circle-outlined"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn--primary ls-action ls-sync-one"
                                        data-url="{{ route('admin.urban-goodz.load-sourcing.api.sync-source', $row['source']->id) }}"
                                        data-enabled="{{ $row['enabled'] ? 1 : 0 }}"
                                        title="{{ translate('Run a manual sync now') }}">
                                    <i class="tio-refresh"></i>
                                </button>
                                <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}"
                                   class="btn btn-sm btn-outline--primary" title="{{ translate('Configure source') }}">
                                    <i class="tio-settings-outlined"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="ls-empty">
                            {{ translate('No load sources have been configured yet.') }}
                            <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}">{{ translate('Add a source') }}</a>
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Distribution charts ────────────────────────────────────── --}}
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Loads by Origin State') }}</h5></div>
                    <div class="card-body">
                        @php
                            $originStates = $stats['loads_by_origin_state'] ?? collect();
                            $maxOrigin = $originStates->max('count') ?: 1;
                        @endphp
                        <div class="bar-chart">
                            @forelse($originStates as $row)
                                <div class="bar-row">
                                    <span class="bar-label fw-semibold">{{ $row->origin_state }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bg-primary" style="width: {{ ($row->count / $maxOrigin) * 100 }}%;">{{ $row->count }}</div>
                                    </div>
                                </div>
                            @empty
                                <p class="ls-empty mb-0">{{ translate('No load data available yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Loads by Equipment Type') }}</h5></div>
                    <div class="card-body">
                        @php
                            $equipTypes = $stats['loads_by_equipment_type'] ?? collect();
                            $maxEquip = $equipTypes->max('count') ?: 1;
                            $equipColors = ['dry_van' => 'primary', 'reefer' => 'info', 'flatbed' => 'warning', 'box_truck' => 'success', 'power_only' => 'secondary', 'step_deck' => 'danger', 'lowboy' => 'dark'];
                        @endphp
                        <div class="bar-chart">
                            @forelse($equipTypes as $row)
                                @php $color = $equipColors[$row->equipment_type] ?? 'primary'; @endphp
                                <div class="bar-row">
                                    <span class="bar-label fw-semibold">{{ ucwords(str_replace('_', ' ', (string) $row->equipment_type)) }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bg-{{ $color }}" style="width: {{ ($row->count / $maxEquip) * 100 }}%;">{{ $row->count }}</div>
                                    </div>
                                </div>
                            @empty
                                <p class="ls-empty mb-0">{{ translate('No load data available yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── External loads: search / filter + pagination ────────────── --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="ls-section-title">{{ translate('External Loads') }}</h5></div>
            <div class="card-body pb-0">
                <form method="GET" action="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="{{ translate('Broker, city, commodity, external ID') }}"
                               value="{{ $filters['q'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('All statuses') }}</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                    {{ ucwords(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="source_id" class="form-control form-control-sm">
                            <option value="">{{ translate('All sources') }}</option>
                            @foreach($sources as $src)
                                <option value="{{ $src->id }}" @selected((string) ($filters['source_id'] ?? '') === (string) $src->id)>
                                    {{ $src->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="equipment_type" class="form-control form-control-sm"
                               placeholder="{{ translate('Equipment') }}" value="{{ $filters['equipment_type'] ?? '' }}">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn--primary"><i class="tio-filter-list"></i> {{ translate('Filter') }}</button>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn-sm btn-outline--primary">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 ls-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Source') }}</th>
                            <th>{{ translate('Broker') }}</th>
                            <th>{{ translate('Origin') }}</th>
                            <th>{{ translate('Destination') }}</th>
                            <th>{{ translate('Equipment') }}</th>
                            <th class="text-right">{{ translate('Miles') }}</th>
                            <th class="text-right">{{ translate('Rate') }}</th>
                            <th class="text-right">{{ translate('$/Loaded Mi') }}</th>
                            <th>{{ translate('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($recentLoads as $load)
                        <tr>
                            <td>{{ $load->id }}</td>
                            <td>{{ $load->source->name ?? '—' }}</td>
                            <td>{{ $load->broker_name ?? '—' }}</td>
                            <td>{{ $load->origin_full ?? '—' }}</td>
                            <td>{{ $load->destination_full ?? '—' }}</td>
                            <td>{{ $load->equipment_type ?? '—' }}</td>
                            <td class="text-right">{{ number_format($load->distance_loaded ?? 0) }}</td>
                            <td class="text-right">${{ number_format($load->gross_rate ?? 0, 2) }}</td>
                            <td class="text-right">${{ number_format($load->rate_per_loaded_mile ?? 0, 2) }}</td>
                            <td><span class="badge badge-soft-info">{{ $load->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="ls-empty">
                            @if(array_filter($filters ?? []))
                                {{ translate('No external loads match the current filters.') }}
                            @else
                                {{ translate('No external loads have been sourced yet.') }}
                            @endif
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($recentLoads->hasPages())
                <div class="card-footer">{{ $recentLoads->links() }}</div>
            @endif
        </div>

        {{-- ── Recent searches + recent sync runs ─────────────────────── --}}
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="ls-section-title">{{ translate('Recent Searches') }}</h5>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-sm btn-outline--primary">{{ translate('New Search') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>{{ translate('When') }}</th><th>{{ translate('Source') }}</th>
                                <th>{{ translate('Scope') }}</th><th class="text-right">{{ translate('Results') }}</th><th>{{ translate('OK') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($recentSearches as $s)
                                <tr>
                                    <td>{{ $s->created_at?->format('M d H:i') ?? '—' }}</td>
                                    <td>{{ $s->source->name ?? translate('All') }}</td>
                                    <td>{{ $s->search_scope ?? '—' }}</td>
                                    <td class="text-right">{{ (int) $s->result_count }}</td>
                                    <td><span class="badge badge-soft-{{ $s->completed ? 'success' : 'danger' }}">{{ $s->completed ? translate('yes') : translate('no') }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ls-empty">{{ translate('No searches have been run yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="ls-section-title">{{ translate('Recent Sync Runs') }}</h5>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn-sm btn-outline--primary">{{ translate('View All') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>{{ translate('When') }}</th><th>{{ translate('Source') }}</th><th>{{ translate('Status') }}</th>
                                <th class="text-right">{{ translate('New') }}</th><th class="text-right">{{ translate('Dup') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($recentSyncRuns as $run)
                                <tr>
                                    <td>{{ $run->created_at?->format('M d H:i') ?? '—' }}</td>
                                    <td>{{ $run->source->name ?? '—' }}</td>
                                    <td><span class="badge badge-soft-{{ $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning') }}">{{ $run->status }}</span></td>
                                    <td class="text-right">{{ (int) $run->loads_new }}</td>
                                    <td class="text-right">{{ (int) $run->loads_duplicate }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ls-empty">{{ translate('No sync runs recorded yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Sync failures + duplicates ─────────────────────────────── --}}
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="ls-section-title">{{ translate('Unresolved Sync Failures') }}</h5>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn-sm btn-outline--primary">{{ translate('View All') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>{{ translate('When') }}</th><th>{{ translate('Source') }}</th>
                                <th>{{ translate('Code') }}</th><th>{{ translate('Message') }}</th><th></th>
                            </tr></thead>
                            <tbody>
                            @forelse($syncFailures as $err)
                                <tr>
                                    <td>{{ $err->created_at?->format('M d H:i') ?? '—' }}</td>
                                    <td>{{ $err->source->name ?? '—' }}</td>
                                    <td><code>{{ $err->error_code }}</code></td>
                                    <td>{{ \Illuminate\Support\Str::limit((string) $err->error_message, 60) }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.resolve-error', $err->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline--primary">{{ translate('Resolve') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ls-empty">{{ translate('No unresolved sync failures. All clear.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Detected Duplicates') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>#</th><th>{{ translate('Source') }}</th><th>{{ translate('Lane') }}</th>
                                <th>{{ translate('Canonical') }}</th><th>{{ translate('Detected') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($duplicates as $dup)
                                <tr>
                                    <td>{{ $dup->id }}</td>
                                    <td>{{ $dup->source->name ?? '—' }}</td>
                                    <td>{{ $dup->origin_full ?? '—' }} → {{ $dup->destination_full ?? '—' }}</td>
                                    <td>{{ $dup->deduplicated_to_id ? '#' . $dup->deduplicated_to_id : '—' }}</td>
                                    <td>{{ $dup->created_at?->format('M d H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ls-empty">{{ translate('No duplicate loads detected.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Imported loads + recommendations ───────────────────────── --}}
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Imported Loads') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>{{ translate('When') }}</th><th>{{ translate('Method') }}</th><th>{{ translate('Source') }}</th>
                                <th class="text-right">{{ translate('OK / Total') }}</th><th>{{ translate('Status') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($recentImports as $imp)
                                <tr>
                                    <td>{{ $imp->created_at?->format('M d H:i') ?? '—' }}</td>
                                    <td>{{ $imp->import_method ?? '—' }}</td>
                                    <td>{{ $imp->source->name ?? '—' }}</td>
                                    <td class="text-right">{{ (int) $imp->successful_rows }} / {{ (int) $imp->total_rows }}</td>
                                    <td><span class="badge badge-soft-{{ $imp->status === 'completed' ? 'success' : 'warning' }}">{{ $imp->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ls-empty">{{ translate('No loads have been imported yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="ls-section-title">{{ translate('Top Recommendations') }}</h5>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-sm btn-outline--primary">{{ translate('View All') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>{{ translate('Load') }}</th><th>{{ translate('Driver') }}</th>
                                <th class="text-right">{{ translate('Score') }}</th><th>{{ translate('Confidence') }}</th><th>{{ translate('Status') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($recommendations as $rec)
                                <tr>
                                    <td>#{{ $rec->external_load_id }}
                                        <br><small class="text-muted">{{ $rec->externalLoad?->origin_full ?? '—' }}</small>
                                    </td>
                                    <td>{{ trim(($rec->driver->f_name ?? '') . ' ' . ($rec->driver->l_name ?? '')) ?: '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $rec->score, 1) }}</td>
                                    <td>{{ $rec->confidence_level ?? '—' }}</td>
                                    <td><span class="badge badge-soft-info">{{ $rec->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="ls-empty">{{ translate('No recommendations generated yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Matching drivers + audit history ───────────────────────── --}}
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Matching Drivers') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 ls-table">
                            <thead class="thead-light"><tr>
                                <th>{{ translate('Driver') }}</th><th class="text-right">{{ translate('Min $/mi') }}</th>
                                <th class="text-right">{{ translate('Max Deadhead') }}</th><th>{{ translate('Equipment') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($matchingDrivers as $pref)
                                <tr>
                                    <td>{{ trim(($pref->driver->f_name ?? '') . ' ' . ($pref->driver->l_name ?? '')) ?: ('#' . $pref->delivery_man_id) }}</td>
                                    <td class="text-right">${{ number_format((float) $pref->min_rate_per_mile, 2) }}</td>
                                    <td class="text-right">{{ (int) $pref->max_deadhead_miles }} mi</td>
                                    <td>
                                        @php $eq = $pref->preferred_equipment; @endphp
                                        {{ is_array($eq) ? implode(', ', $eq) : ($eq ?: '—') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ls-empty">{{ translate('No drivers have configured load-matching preferences yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="ls-section-title">{{ translate('Audit History') }}</h5></div>
                    <div class="card-body">
                        @forelse($auditTrail as $event)
                            <div class="d-flex align-items-start mb-2">
                                <span class="audit-dot mt-2 bg-{{ $event['ok'] ? 'success' : 'danger' }}"></span>
                                <div class="flex-grow-1">
                                    <div style="font-size:.83rem;">{{ $event['summary'] }}</div>
                                    <small class="text-muted">
                                        {{ $event['at']?->format('M d, Y H:i') ?? '—' }}
                                        · {{ $event['type'] }} · {{ $event['actor'] }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <p class="ls-empty mb-0">{{ translate('No sourcing activity has been recorded yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('script_2')
<script>
(function () {
    var alertBox = document.getElementById('ls-ajax-alert');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : '{{ csrf_token() }}';

    function notify(ok, message) {
        if (!alertBox) return;
        alertBox.className = 'alert mb-3 alert-' + (ok ? 'success' : 'danger');
        alertBox.textContent = message;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function post(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json().catch(function () { return { success: false, message: 'HTTP ' + r.status }; }); });
    }

    document.querySelectorAll('.ls-action').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            post(btn.dataset.url)
                .then(function (data) {
                    notify(data.success !== false, data.message || '{{ translate('Done. Reloading…') }}');
                    setTimeout(function () { window.location.reload(); }, 900);
                })
                .catch(function (e) { notify(false, e.message); btn.disabled = false; });
        });
    });

    var syncAll = document.getElementById('ls-sync-all');
    if (syncAll) {
        syncAll.addEventListener('click', function () {
            var buttons = Array.prototype.slice.call(document.querySelectorAll('.ls-sync-one'))
                .filter(function (b) { return b.dataset.enabled === '1'; });
            if (!buttons.length) {
                notify(false, '{{ translate('There are no enabled sources to sync.') }}');
                return;
            }
            syncAll.disabled = true;
            notify(true, '{{ translate('Manual sync started for') }} ' + buttons.length + ' {{ translate('source(s)…') }}');
            Promise.all(buttons.map(function (b) { return post(b.dataset.url); }))
                .then(function () { window.location.reload(); })
                .catch(function (e) { notify(false, e.message); syncAll.disabled = false; });
        });
    }
})();
</script>
@endpush
