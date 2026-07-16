@extends('layouts.admin.app')

@section('title', translate('AI Feature Controls'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to AI Operations') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Feature Controls') }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.ai-operations.feature-controls') }}">
            @csrf

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Automation Feature Toggles') }}</h5>
                    <small class="text-muted">{{ translate('Enable or disable individual AI automation features across the platform.') }}</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">{{ translate('Feature') }}</th>
                                    <th class="text-center" style="width: 100px;">{{ translate('Enabled') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $features = [
                                        'ai_auto_dispatch_enabled' => [translate('Auto Dispatch'), translate('AI assigns drivers to orders and routes when conditions are low-risk')],
                                        'ai_auto_customer_support_enabled' => [translate('Customer Support'), translate('AI responds to common customer support inquiries automatically')],
                                        'ai_auto_driver_support_enabled' => [translate('Driver Support'), translate('AI responds to common driver support inquiries automatically')],
                                        'ai_auto_vendor_support_enabled' => [translate('Vendor/Business Support'), translate('AI responds to common vendor and business portal inquiries')],
                                        'ai_auto_order_anywhere_triage_enabled' => [translate('Order Anywhere Triage'), translate('AI categorizes and suggests actions for Order Anywhere requests')],
                                        'ai_auto_package_route_assignment_enabled' => [translate('Package Route Assignment'), translate('AI assigns unassigned packages to appropriate routes and manifests')],
                                        'ai_auto_business_courier_assignment_enabled' => [translate('Business Courier Assignment'), translate('AI assigns business courier jobs to available drivers')],
                                        'ai_escalate_high_risk_to_admin' => [translate('Escalate High-Risk to Admin'), translate('AI must escalate compliance, payment, and high-risk decisions to admin for review')],
                                        'ai_audit_log_enabled' => [translate('Audit Logging'), translate('Log all AI recommendations and auto-executed actions for audit trail')],
                                    ];
                                @endphp
                                @foreach($features as $key => [$label, $desc])
                                <tr>
                                    <td class="ps-3"><strong>{{ $label }}</strong></td>
                                    <td class="text-center">
                                        <label class="toggle-switch">
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <input type="checkbox" name="{{ $key }}" value="1" {{ ($featureToggles[$key] ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ $desc }}</small></td>
                                </tr>
                                @endforeach
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
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Refunds above threshold') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Payout changes') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Virtual card funding') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Alcohol/THC compliance overrides') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Medical courier exceptions') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Legal threats') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Fraud/safety issues') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('High-risk freight jobs') }}</span></div>
                        <div class="col-md-4 col-6"><span class="badge badge-soft-danger">{{ translate('Partner status changes') }}</span></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn--primary">{{ translate('Save Feature Controls') }}</button>
            </div>
        </form>
    </div>
@endsection
