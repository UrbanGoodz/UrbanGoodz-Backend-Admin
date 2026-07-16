@extends('layouts.admin.app')

@section('title', translate('AI Operations'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.5rem; font-weight: 700; }
    .provider-badge { font-size: 0.85rem; }
    .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px; }
    .toggle-switch-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
    .toggle-switch input:checked + .toggle-switch-slider { background-color: #28a745; }
    .toggle-switch input:checked + .toggle-switch-slider:before { transform: translateX(20px); }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('AI Operations Center') }}</h1>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.urban-goodz.ai-operations.feature-controls') }}" class="btn btn-outline--primary">
                    <i class="tio-settings-outlined"></i> {{ translate('Feature Controls') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.logs') }}" class="btn btn-outline--primary">
                    <i class="tio-list"></i> {{ translate('Logs') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.usage') }}" class="btn btn-outline--primary">
                    <i class="tio-chart-line"></i> {{ translate('Usage') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.test') }}" class="btn btn-outline--primary">
                    <i class="tio-terminal"></i> {{ translate('Test AI') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.load-sourcing') }}" class="btn btn-outline--primary">
                    <i class="tio-truck"></i> {{ translate('Load Sourcing') }}
                </a>
            </div>
        </div>

        @php
            $modeLabels = [
                'off' => 'Off',
                'recommend_only' => 'Recommend Only',
                'supervised_automation' => 'Supervised Automation',
                'full_low_risk_automation' => 'Full Automation',
                'restricted_human_locked' => 'Restricted/Human Locked',
            ];
            $modeBadges = [
                'off' => 'danger',
                'recommend_only' => 'warning',
                'supervised_automation' => 'info',
                'full_low_risk_automation' => 'success',
                'restricted_human_locked' => 'dark',
            ];
        @endphp

        {{-- Provider Status --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ translate('AI Provider Status') }}</h5>
                <span class="badge badge-soft-{{ $providerStatus['openai_configured'] ? 'success' : 'danger' }} provider-badge">
                    {{ $providerStatus['openai_configured'] ? translate('Configured') : translate('Not Configured') }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Provider') }}</small>
                        <strong>OpenAI</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Model') }}</small>
                        <strong>{{ $providerStatus['model'] }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Base URL') }}</small>
                        <code style="font-size: 0.8rem;">{{ $providerStatus['base_url'] }}</code>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Timeout') }}</small>
                        <strong>{{ $providerStatus['timeout'] }}s</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- Automation Mode --}}
        <div class="alert alert-{{ $modeBadges[$copilotMode] ?? 'secondary' }} d-flex align-items-center justify-content-between mb-4">
            <div>
                <strong>{{ translate('Automation Mode') }}:</strong>
                <span class="badge badge-soft-{{ $modeBadges[$copilotMode] ?? 'secondary' }} ms-2">{{ $modeLabels[$copilotMode] ?? ucfirst($copilotMode) }}</span>
            </div>
            <a href="{{ route('admin.urban-goodz.ai-copilot.settings') }}" class="btn btn-sm btn-outline-secondary">
                <i class="tio-settings"></i> {{ translate('Change') }}
            </a>
        </div>

        {{-- Stats Overview --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--primary">{{ $conversationsToday }}</div>
                        <small class="text-muted">{{ translate('Conversations Today') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--info">{{ $actionsToday }}</div>
                        <small class="text-muted">{{ translate('Actions Today') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--warning">{{ $recommendationsPending }}</div>
                        <small class="text-muted">{{ translate('Pending Recommendations') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--success">{{ $modulesEnabled }}/{{ $modulesTotal }}</div>
                        <small class="text-muted">{{ translate('Modules Enabled') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Feature Toggles --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Feature Toggles') }}</h5>
                        <a href="{{ route('admin.urban-goodz.ai-operations.feature-controls') }}" class="btn btn-sm btn-outline--primary">
                            {{ translate('Manage') }}
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                @php
                                    $toggleLabels = [
                                        'ai_auto_dispatch_enabled' => translate('Auto Dispatch'),
                                        'ai_auto_customer_support_enabled' => translate('Customer Support'),
                                        'ai_auto_driver_support_enabled' => translate('Driver Support'),
                                        'ai_auto_vendor_support_enabled' => translate('Vendor Support'),
                                        'ai_auto_order_anywhere_triage_enabled' => translate('Order Anywhere Triage'),
                                        'ai_auto_package_route_assignment_enabled' => translate('Package Route Assignment'),
                                        'ai_auto_business_courier_assignment_enabled' => translate('Business Courier'),
                                        'ai_escalate_high_risk_to_admin' => translate('Escalate High-Risk'),
                                        'ai_audit_log_enabled' => translate('Audit Logging'),
                                    ];
                                @endphp
                                @foreach($toggleLabels as $key => $label)
                                <tr>
                                    <td class="ps-3">{{ $label }}</td>
                                    <td class="text-end pe-3">
                                        @if(($featureToggles[$key] ?? '0') === '1')
                                            <span class="badge badge-soft-success">{{ translate('ON') }}</span>
                                        @else
                                            <span class="badge badge-soft-secondary">{{ translate('OFF') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Load Sourcing Status --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Load Sourcing Status') }}</h5>
                        <a href="{{ route('admin.urban-goodz.ai-operations.load-sourcing') }}" class="btn btn-sm btn-outline--primary">
                            {{ translate('Details') }}
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="ps-3">{{ translate('Total Loads') }}</td>
                                    <td class="text-end pe-3 fw-bold">{{ number_format($loadStats['total']) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3">{{ translate('Available') }}</td>
                                    <td class="text-end pe-3"><span class="badge badge-soft-success">{{ number_format($loadStats['available']) }}</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">{{ translate('Assigned') }}</td>
                                    <td class="text-end pe-3"><span class="badge badge-soft-primary">{{ number_format($loadStats['assigned']) }}</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">{{ translate('In Transit') }}</td>
                                    <td class="text-end pe-3"><span class="badge badge-soft-info">{{ number_format($loadStats['in_transit']) }}</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">{{ translate('Delivered') }}</td>
                                    <td class="text-end pe-3"><span class="badge badge-soft-secondary">{{ number_format($loadStats['delivered']) }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Risk Rules & Quick Links --}}
        <div class="row g-4 mt-2">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Risk Rules') }}</h5>
                        <a href="{{ route('admin.urban-goodz.ai-copilot.risk-rules') }}" class="btn btn-sm btn-outline--primary">
                            {{ translate('Manage') }}
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-number text--danger">{{ $riskRulesActive }}</div>
                            <div>
                                <small class="text-muted">{{ translate('Active rules out of') }} {{ $riskRulesTotal }} {{ translate('total') }}</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('admin.urban-goodz.ai-copilot.risk-rules') }}" class="btn btn-sm btn-outline-secondary">
                                {{ translate('View & Edit Risk Rules') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Quick Links') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary btn-sm">
                                <i class="tio-dashboard"></i> {{ translate('AI Copilot') }}
                            </a>
                            <a href="{{ route('admin.urban-goodz.ai-concierge.intents') }}" class="btn btn-outline--primary btn-sm">
                                <i class="tio-tag"></i> {{ translate('AI Intents') }}
                            </a>
                            <a href="{{ route('admin.urban-goodz.ai-concierge.conversations') }}" class="btn btn-outline--primary btn-sm">
                                <i class="tio-chat"></i> {{ translate('Conversations') }}
                            </a>
                            <a href="{{ route('admin.urban-goodz.ai-copilot.module-settings') }}" class="btn btn-outline--primary btn-sm">
                                <i class="tio-settings-outlined"></i> {{ translate('Module Settings') }}
                            </a>
                            <a href="{{ route('admin.urban-goodz.ai-copilot.load-board-analytics') }}" class="btn btn-outline--primary btn-sm">
                                <i class="tio-chart-bar"></i> {{ translate('Load Board Analytics') }}
                            </a>
                            <a href="{{ route('admin.business-settings.business-settings.openAI') }}" class="btn btn-outline--primary btn-sm">
                                <i class="tio-key"></i> {{ translate('OpenAI Config') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
