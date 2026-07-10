@extends('layouts.admin.app')

@section('title', translate('AI Ops Copilot Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Copilot') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Ops Copilot Settings') }}</h1>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.ai-copilot.module-settings') }}" class="btn btn-outline--primary">
                        <i class="tio-settings-outlined"></i> {{ translate('Module Settings') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.ai-copilot.risk-rules') }}" class="btn btn-outline--primary">
                        <i class="tio-shield"></i> {{ translate('Risk Rules') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.ai-copilot.action-logs') }}" class="btn btn-outline--primary">
                        <i class="tio-list"></i> {{ translate('Action Logs') }}
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.settings.save') }}">
            @csrf

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Automation Mode') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">{{ translate('AI Ops Mode') }}</label>
                        <select name="ai_ops_enabled" class="form-control">
                            <option value="off" {{ ($allSettings['ai_ops_enabled']->value ?? 'recommend_only') === 'off' ? 'selected' : '' }}>{{ translate('Off') }}</option>
                            <option value="recommend_only" {{ ($allSettings['ai_ops_enabled']->value ?? 'recommend_only') === 'recommend_only' ? 'selected' : '' }}>{{ translate('Recommend Only — AI suggests, human approves') }}</option>
                            <option value="supervised_automation" {{ ($allSettings['ai_ops_enabled']->value ?? 'recommend_only') === 'supervised_automation' ? 'selected' : '' }}>{{ translate('Supervised Automation — AI acts, logged for review') }}</option>
                            <option value="full_low_risk_automation" {{ ($allSettings['ai_ops_enabled']->value ?? 'recommend_only') === 'full_low_risk_automation' ? 'selected' : '' }}>{{ translate('Full Low-Risk Automation — AI handles routine ops automatically') }}</option>
                            <option value="restricted_human_locked" {{ ($allSettings['ai_ops_enabled']->value ?? 'recommend_only') === 'restricted_human_locked' ? 'selected' : '' }}>{{ translate('Restricted — Human Locked (AI recommendations only, no automation)') }}</option>
                        </select>
                        <small class="text-muted">
                            {{ translate('Off') }}: No AI recommendations generated.<br>
                            {{ translate('Recommend Only') }}: AI finds issues, creates recommendations, admin reviews and acts.<br>
                            {{ translate('Supervised Automation') }}: AI takes action on low-risk items (confidence >= 70%), logs for audit.<br>
                            {{ translate('Full Low-Risk Automation') }}: AI handles all routine operational tasks automatically.<br>
                            {{ translate('Restricted Human Locked') }}: AI generates recommendations only — all automation is blocked, high-risk items flagged.
                        </small>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Auto-Execute Permissions') }}</h5>
                    <small class="text-muted">{{ translate('These settings only apply when automation mode is not "Recommend Only" or "Off".') }}</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th>{{ translate('Feature') }}</th>
                                    <th class="text-center" style="width: 100px;">{{ translate('Enabled') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Auto Dispatch') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_dispatch_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_dispatch_enabled" value="1" {{ ($allSettings['ai_auto_dispatch_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can assign drivers to orders and routes when conditions are low-risk') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Customer Support Automation') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_customer_support_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_customer_support_enabled" value="1" {{ ($allSettings['ai_auto_customer_support_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can respond to common customer support inquiries') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Driver Support Automation') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_driver_support_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_driver_support_enabled" value="1" {{ ($allSettings['ai_auto_driver_support_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can respond to common driver support inquiries') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Vendor/Business Support Automation') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_vendor_support_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_vendor_support_enabled" value="1" {{ ($allSettings['ai_auto_vendor_support_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can respond to common vendor/business portal inquiries') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Order Anywhere Auto-Triage') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_order_anywhere_triage_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_order_anywhere_triage_enabled" value="1" {{ ($allSettings['ai_auto_order_anywhere_triage_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can categorize and suggest actions for Order Anywhere requests') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Package Route Assignment') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_package_route_assignment_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_package_route_assignment_enabled" value="1" {{ ($allSettings['ai_auto_package_route_assignment_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can assign unassigned packages to appropriate routes/manifests') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Business Courier Assignment') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_auto_business_courier_assignment_enabled" value="0">
                                            <input type="checkbox" name="ai_auto_business_courier_assignment_enabled" value="1" {{ ($allSettings['ai_auto_business_courier_assignment_enabled']->value ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI can assign business courier jobs to available drivers') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Safety & Audit') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th>{{ translate('Setting') }}</th>
                                    <th class="text-center" style="width: 100px;">{{ translate('Enabled') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Escalate High-Risk to Admin') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_escalate_high_risk_to_admin" value="0">
                                            <input type="checkbox" name="ai_escalate_high_risk_to_admin" value="1" {{ ($allSettings['ai_escalate_high_risk_to_admin']->value ?? '1') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('AI must escalate compliance, payment, and high-risk decisions to admin') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Audit Log Enabled') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="ai_audit_log_enabled" value="0">
                                            <input type="checkbox" name="ai_audit_log_enabled" value="1" {{ ($allSettings['ai_audit_log_enabled']->value ?? '1') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('Log all AI recommendations and auto-executed actions for audit trail') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Human Approval Still Required For') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Refunds above configured threshold</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Payout changes</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Virtual card funding</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Customer charge increases</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Alcohol/THC compliance exceptions</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Medical courier exceptions</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">PHI/sensitive medical info</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Legal threats</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Fraud/safety issues</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Partner status changes</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">High-risk freight/load-board jobs</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">Anything outside configured AI rules</span></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn--primary">{{ translate('Save Settings') }}</button>
            </div>
        </form>
    </div>
@endsection
