@extends('layouts.admin.app')

@section('title', translate('AI Ops Copilot'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.6rem; font-weight: 700; }
    .rec-type-icon { font-size: 1.2rem; width: 32px; }
    .confidence-bar { height: 4px; border-radius: 2px; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('AI Ops Copilot') }}</h1>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.urban-goodz.ai-copilot.module-settings') }}" class="btn btn-outline--primary">
                    <i class="tio-settings-outlined"></i> {{ translate('Modules') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.risk-rules') }}" class="btn btn-outline--primary">
                    <i class="tio-shield"></i> {{ translate('Risk Rules') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.action-logs') }}" class="btn btn-outline--primary">
                    <i class="tio-list"></i> {{ translate('Logs') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.suppressed') }}" class="btn btn-outline-secondary">
                    <i class="tio-clear-circle-outlined"></i> {{ translate('Suppressed') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.load-board-analytics') }}" class="btn btn-outline--primary">
                    <i class="tio-truck"></i> {{ translate('Load Board Analytics') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.settings') }}" class="btn btn-outline--primary">
                    <i class="tio-settings"></i> {{ translate('Settings') }}
                </a>
                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.generate') }}" class="d-inline">
                    @csrf
                    @if(request('type'))
                        <input type="hidden" name="type" value="{{ request('type') }}">
                    @endif
                    <button type="submit" class="btn btn--primary" onclick="return confirm('Generate AI recommendations?')">
                        <i class="tio-refresh"></i> {{ translate('Generate Recommendations') }}
                    </button>
                </form>
            </div>
        </div>

        @php
            $modeLabels = ['off' => 'Off', 'recommend_only' => 'Recommend Only', 'supervised_automation' => 'Supervised Automation', 'full_low_risk_automation' => 'Full Automation', 'restricted_human_locked' => 'Restricted/Human Locked'];
            $modeBadges = ['off' => 'danger', 'recommend_only' => 'warning', 'supervised_automation' => 'info', 'full_low_risk_automation' => 'success', 'restricted_human_locked' => 'dark'];
        @endphp

        <div class="alert alert-{{ $modeBadges[$mode] ?? 'secondary' }} d-flex align-items-center justify-content-between mb-4">
            <div>
                <strong>{{ translate('Automation Mode') }}:</strong>
                <span class="badge badge-soft-{{ $modeBadges[$mode] ?? 'secondary' }} ms-2">{{ $modeLabels[$mode] ?? ucfirst($mode) }}</span>
            </div>
            @if($mode !== 'off')
                <small>{{ translate('Auto-dispatch') }}:
                    <strong>{{ ($settings['ai_auto_dispatch_enabled'] ?? '0') === '1' ? 'On' : 'Off' }}</strong>
                    &middot; {{ translate('Auto-triage') }}:
                    <strong>{{ ($settings['ai_auto_order_anywhere_triage_enabled'] ?? '0') === '1' ? 'On' : 'Off' }}</strong>
                    &middot; {{ translate('Audit log') }}:
                    <strong>{{ ($settings['ai_audit_log_enabled'] ?? '1') === '1' ? 'On' : 'Off' }}</strong>
                </small>
            @endif
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--warning">{{ $stats['total_pending'] }}</div>
                        <small class="text-muted">{{ translate('Pending Review') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--success">{{ $stats['total_accepted'] }}</div>
                        <small class="text-muted">{{ translate('Accepted') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--secondary">{{ $stats['total_dismissed'] }}</div>
                        <small class="text-muted">{{ translate('Dismissed Once') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--info">{{ $stats['total_snoozed'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Snoozed') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--danger">{{ $stats['total_dont_show_again'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Suppressed') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--primary">{{ $stats['total_resolved'] ?? 0 }}</div>
                        <small class="text-muted">{{ translate('Resolved') }}</small>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($stats['by_type']))
        <div class="row g-3 mb-4">
            @foreach($stats['by_type'] as $type => $count)
            <div class="col-md-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-copilot.index', ['type' => $type]) }}" class="card text-decoration-none">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="rec-type-icon text--primary">
                            @php $icons = ['dispatch_suggestion' => 'tio-delivery', 'stuck_order' => 'tio-alert', 'stuck_order_alert' => 'tio-alert', 'order_anywhere_triage' => 'tio-chat', 'package_monitoring' => 'tio-package', 'age_verification_alert' => 'tio-verified', 'load_board' => 'tio-truck', 'load_board_alert' => 'tio-truck', 'load_board_demand' => 'tio-chart-line', 'load_acceptance_suggestion' => 'tio-user-check', 'load_board_driver_match' => 'tio-user-check', 'load_pricing_anomaly' => 'tio-dollar', 'load_board_pricing' => 'tio-dollar']; @endphp
                            <i class="{{ $icons[$type] ?? 'tio-flag' }}"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ $count }}</div>
                            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $type)) }}</small>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
        @endif

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">{{ translate('Recommendations') }}</h5>
                    @if(request('type'))
                        <small class="text-primary font-weight-bold">{{ translate('Filtered by') }}: {{ ucwords(str_replace('_', ' ', request('type'))) }} ({{ $recommendations->total() }} {{ translate('found') }})</small>
                    @endif
                </div>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Types') }}</option>
                        @foreach([
                            'dispatch_suggestion' => 'Dispatch Suggestion',
                            'stuck_order_alert' => 'Stuck Order Alert',
                            'order_anywhere_triage' => 'Order Anywhere Triage',
                            'package_monitoring' => 'Package Monitoring',
                            'age_verification_alert' => 'Age Verification Alert',
                            'load_board_alert' => 'Load Board Alert',
                            'load_acceptance_suggestion' => 'Load Acceptance Suggestion',
                            'load_pricing_anomaly' => 'Load Pricing Anomaly'
                        ] as $tKey => $tVal)
                        <option value="{{ $tKey }}" {{ request('type') === $tKey ? 'selected' : '' }}>{{ translate($tVal) }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="pending" {{ request('status') === 'pending' || !request('status') ? 'selected' : '' }}>{{ translate('Pending Review') }}</option>
                        <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>{{ translate('Accepted') }}</option>
                        <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>{{ translate('Dismissed Once') }}</option>
                        <option value="snoozed" {{ request('status') === 'snoozed' ? 'selected' : '' }}>{{ translate('Snoozed') }}</option>
                        <option value="dont_show_again" {{ request('status') === 'dont_show_again' ? 'selected' : '' }}>{{ translate('Suppressed (Don\'t Show)') }}</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>{{ translate('Resolved') }}</option>
                        <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>{{ translate('All Statuses') }}</option>
                    </select>
                    @if(count(request()->query()) > 0)
                    <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Suggestion') }}</th>
                                <th>{{ translate('Confidence') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Created') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recommendations as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>
                                    @php
                                        $typeLabels = ['dispatch_suggestion' => 'Dispatch', 'stuck_order' => 'Stuck Order', 'stuck_order_alert' => 'Stuck Order', 'order_anywhere_triage' => 'OA Triage', 'package_monitoring' => 'Package', 'age_verification_alert' => 'Age Verify', 'load_board' => 'Load Board', 'load_board_alert' => 'Load Board', 'load_board_stale' => 'Load Board', 'load_board_demand' => 'Load Demand', 'load_acceptance_suggestion' => 'Driver Match', 'load_board_accept' => 'Driver Match', 'load_pricing_anomaly' => 'Pricing Anomaly', 'load_board_repricing' => 'Pricing Anomaly'];
                                        $typeBadges = ['dispatch_suggestion' => 'primary', 'stuck_order' => 'danger', 'stuck_order_alert' => 'danger', 'order_anywhere_triage' => 'info', 'package_monitoring' => 'warning', 'age_verification_alert' => 'dark', 'load_board' => 'success', 'load_board_alert' => 'success', 'load_board_stale' => 'success', 'load_board_demand' => 'info', 'load_acceptance_suggestion' => 'primary', 'load_board_accept' => 'primary', 'load_pricing_anomaly' => 'warning', 'load_board_repricing' => 'warning'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $typeBadges[$r->recommendation_type] ?? 'secondary' }}">
                                        {{ $typeLabels[$r->recommendation_type] ?? ucwords(str_replace('_', ' ', $r->recommendation_type)) }}
                                    </span>
                                </td>
                                <td style="max-width: 320px;">
                                    <div title="{{ $r->reason }}">
                                        <strong>{{ $r->suggested_action }}</strong>
                                        <br><small class="text-muted">{{ $r->reason }}</small>
                                    </div>
                                </td>
                                <td>
                                    @if($r->confidence_score)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1" style="max-width: 60px;">
                                            <div class="confidence-bar bg--primary" style="width: {{ $r->confidence_score * 100 }}%"></div>
                                        </div>
                                        <small>{{ number_format($r->confidence_score * 100) }}%</small>
                                    </div>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $sMap = ['pending' => 'warning', 'accepted' => 'success', 'dismissed' => 'secondary', 'snoozed' => 'info', 'dont_show_again' => 'danger', 'resolved' => 'primary'];
                                        $autoLabel = !empty($r->metadata['auto_executed']) ? ' (Auto)' : '';
                                    @endphp
                                    <span class="badge badge-soft-{{ $sMap[$r->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $r->status)) }}{{ $autoLabel }}</span>
                                    @if(!empty($r->metadata['suppressed_until']))
                                    <br><small class="text-muted" style="font-size: 0.7rem;">Until {{ \Carbon\Carbon::parse($r->metadata['suppressed_until'])->format('M d H:i') }}</small>
                                    @endif
                                </td>
                                <td><small>{{ $r->created_at->format('M d, h:i A') }}</small></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.urban-goodz.ai-copilot.show', $r->id) }}" class="btn btn-sm btn--primary" title="View">
                                            <i class="tio-visible"></i>
                                        </a>
                                        @if($r->status === 'pending')
                                        <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.accept', $r->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Accept" onclick="return confirm('Accept and execute this recommendation?')">
                                                <i class="tio-checkmark-circle"></i>
                                            </button>
                                        </form>

                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-toggle="dropdown">
                                                {{ translate('Actions') }}
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 200px;">
                                                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.dismiss', $r->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-1">
                                                        <i class="tio-clear text-muted"></i> {{ translate('Dismiss Once') }}
                                                    </button>
                                                </form>

                                                <div class="dropdown-divider"></div>
                                                <div class="dropdown-header px-2 py-1">{{ translate('Snooze') }}</div>
                                                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.snooze', $r->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="days" value="1">
                                                    <button type="submit" class="dropdown-item py-1"><i class="tio-time"></i> 1 {{ translate('Day') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.snooze', $r->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="days" value="7">
                                                    <button type="submit" class="dropdown-item py-1"><i class="tio-time"></i> 7 {{ translate('Days') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.snooze', $r->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="days" value="30">
                                                    <button type="submit" class="dropdown-item py-1"><i class="tio-time"></i> 30 {{ translate('Days') }}</button>
                                                </form>

                                                <div class="dropdown-divider"></div>
                                                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.resolve', $r->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-1 text-success">
                                                        <i class="tio-checkmark-circle"></i> {{ translate('Mark Resolved') }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.dont-show-again', $r->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-1 text-danger" onclick="return confirm('Permanently suppress this recommendation?')">
                                                        <i class="tio-block"></i> {{ translate('Don\'t Show Again') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        @else
                                        <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.restore', $r->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Restore to Pending">
                                                <i class="tio-redo"></i> {{ translate('Restore') }}
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ translate('No recommendations found matching selected filters.') }}
                                    <br>
                                    <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.generate') }}" class="d-inline-block mt-2">
                                        @csrf
                                        @if(request('type'))
                                            <input type="hidden" name="type" value="{{ request('type') }}">
                                        @endif
                                        <button type="submit" class="btn btn-sm btn--primary">
                                            <i class="tio-refresh"></i> {{ translate('Generate Now') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $recommendations->links() }}
            </div>
        </div>
    </div>
@endsection
