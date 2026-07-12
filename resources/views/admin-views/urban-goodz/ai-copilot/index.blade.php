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
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.ai-copilot.module-settings') }}" class="btn btn-outline--primary">
                    <i class="tio-settings-outlined"></i> {{ translate('Modules') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.risk-rules') }}" class="btn btn-outline--primary">
                    <i class="tio-shield"></i> {{ translate('Risk Rules') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.action-logs') }}" class="btn btn-outline--primary">
                    <i class="tio-list"></i> {{ translate('Logs') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.load-board-analytics') }}" class="btn btn-outline--primary">
                    <i class="tio-truck"></i> {{ translate('Load Board Analytics') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.settings') }}" class="btn btn-outline--primary">
                    <i class="tio-settings"></i> {{ translate('Settings') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-copilot.generate') }}" class="btn btn--primary" onclick="return confirm('Generate new AI recommendations?')">
                    <i class="tio-refresh"></i> {{ translate('Generate Recommendations') }}
                </a>
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
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--warning">{{ $stats['total_pending'] }}</div>
                        <small class="text-muted">{{ translate('Pending Review') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--success">{{ $stats['total_accepted'] }}</div>
                        <small class="text-muted">{{ translate('Accepted') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--secondary">{{ $stats['total_dismissed'] }}</div>
                        <small class="text-muted">{{ translate('Dismissed') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--info">{{ array_sum($stats['by_type']) }}</div>
                        <small class="text-muted">{{ translate('Active Issues') }}</small>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($stats['by_type']))
        <div class="row g-3 mb-4">
            @foreach($stats['by_type'] as $type => $count)
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="rec-type-icon text--primary">
                            @php $icons = ['dispatch_suggestion' => 'tio-delivery', 'stuck_order' => 'tio-alert', 'order_anywhere_triage' => 'tio-chat', 'package_monitoring' => 'tio-package', 'age_verification_alert' => 'tio-verified', 'load_board' => 'tio-truck', 'load_board_demand' => 'tio-chart-line', 'load_board_driver_match' => 'tio-user-check', 'load_board_pricing' => 'tio-dollar']; @endphp
                            <i class="{{ $icons[$type] ?? 'tio-flag' }}"></i>
                        </div>
                        <div>
                            <div class="fw-bold">{{ $count }}</div>
                            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $type)) }}</small>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Recommendations') }}</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Types') }}</option>
                        @foreach(['dispatch_suggestion', 'stuck_order', 'order_anywhere_triage', 'package_monitoring', 'age_verification_alert', 'load_board', 'load_board_demand', 'load_board_driver_match', 'load_board_pricing'] as $t)
                        <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Statuses') }}</option>
                        @foreach(['pending', 'accepted', 'dismissed'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
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
                                        $typeLabels = ['dispatch_suggestion' => 'Dispatch', 'stuck_order' => 'Stuck Order', 'order_anywhere_triage' => 'OA Triage', 'package_monitoring' => 'Package', 'age_verification_alert' => 'Age Verify', 'load_board' => 'Load Board', 'load_board_demand' => 'Load Demand', 'load_board_driver_match' => 'Driver Match', 'load_board_pricing' => 'Pricing'];
                                        $typeBadges = ['dispatch_suggestion' => 'primary', 'stuck_order' => 'danger', 'order_anywhere_triage' => 'info', 'package_monitoring' => 'warning', 'age_verification_alert' => 'dark', 'load_board' => 'success', 'load_board_demand' => 'info', 'load_board_driver_match' => 'primary', 'load_board_pricing' => 'warning'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $typeBadges[$r->recommendation_type] ?? 'secondary' }}">
                                        {{ $typeLabels[$r->recommendation_type] ?? $r->recommendation_type }}
                                    </span>
                                </td>
                                <td style="max-width: 300px;">
                                    <div class="text-truncate" title="{{ $r->reason }}">
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
                                        $sMap = ['pending' => 'warning', 'accepted' => 'success', 'dismissed' => 'secondary', 'expired' => 'danger'];
                                        $autoLabel = !empty($r->metadata['auto_executed']) ? ' (Auto)' : '';
                                    @endphp
                                    <span class="badge badge-soft-{{ $sMap[$r->status] ?? 'secondary' }}">{{ ucfirst($r->status) }}{{ $autoLabel }}</span>
                                    @if(!empty($r->metadata['automation_mode']))
                                    <br><small class="text-muted" style="font-size: 0.7rem;">via {{ str_replace('_', ' ', $r->metadata['automation_mode']) }}</small>
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
                                            <button type="submit" class="btn btn-sm btn-success" title="Accept" onclick="return confirm('Accept this recommendation?')">
                                                <i class="tio-checkmark-circle"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.dismiss', $r->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Dismiss" onclick="return confirm('Dismiss this recommendation?')">
                                                <i class="tio-clear"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No recommendations found. Click "Generate Recommendations" to scan for issues.') }}</td>
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
