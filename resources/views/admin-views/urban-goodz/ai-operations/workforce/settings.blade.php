@extends('layouts.admin.app')

@section('title', translate('AI Workforce Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('AI Workforce Settings') }}</h1>
                <p class="text-muted">{{ translate('Configure global AI constraints, demand thresholds, and outreach sequence timing.') }}</p>
            </div>
            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-back"></i> {{ translate('Back to Workforce') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.ai-operations.workforce.settings.update') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">{{ translate('Global Workforce Controls') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="toggle-switch d-flex align-items-center mb-3">
                                    <input type="checkbox" class="toggle-switch-input" name="enabled" value="1" {{ ($settings['enabled'] ?? false) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                    <span class="toggle-switch-content ml-3">
                                        <span class="d-block font-weight-bold">{{ translate('Enable AI Workforce') }}</span>
                                        <span class="text-muted small">{{ translate('Allow AI agents to scan, trigger research, and draft emails.') }}</span>
                                    </span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="toggle-switch d-flex align-items-center mb-3">
                                    <input type="checkbox" class="toggle-switch-input" name="global_kill_switch" value="1" {{ ($settings['global_kill_switch'] ?? false) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text-danger">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                    <span class="toggle-switch-content ml-3">
                                        <span class="d-block font-weight-bold text-danger">{{ translate('Global Kill Switch') }}</span>
                                        <span class="text-muted small">{{ translate('Immediately halt all AI agent activities and API recommendations.') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">{{ translate('Order Anywhere Demand Thresholds') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label class="form-label">{{ translate('Min Requests') }}</label>
                                    <input type="number" class="form-control" name="demand_min_requests" value="{{ $settings['demand_thresholds']['min_requests'] ?? 3 }}">
                                </div>
                                <div class="col-6 form-group">
                                    <label class="form-label">{{ translate('Min Unique Customers') }}</label>
                                    <input type="number" class="form-control" name="demand_min_customers" value="{{ $settings['demand_thresholds']['min_unique_customers'] ?? 2 }}">
                                </div>
                                <div class="col-6 form-group">
                                    <label class="form-label">{{ translate('Rolling Window (Days)') }}</label>
                                    <input type="number" class="form-control" name="demand_window_days" value="{{ $settings['demand_thresholds']['rolling_window_days'] ?? 30 }}">
                                </div>
                                <div class="col-6 form-group">
                                    <label class="form-label">{{ translate('Cooldown Window (Days)') }}</label>
                                    <input type="number" class="form-control" name="demand_cooldown_days" value="{{ $settings['demand_thresholds']['cooldown_days'] ?? 30 }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ translate('Personalized Outreach Sequence & Safety') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="tio-info"></i>
                                <strong>{{ translate('Safety Mode Active:') }}</strong>
                                {{ translate('Outbound messages are generated as drafts and require explicit manual approval. No real emails will be sent.') }}
                            </div>

                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label class="form-label">{{ translate('Outreach Sender Name') }}</label>
                                    <input type="text" class="form-control" name="sender_name" value="{{ $settings['outreach']['sender_name'] ?? 'Urban Goodz' }}">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">{{ translate('Sender Email Address') }}</label>
                                    <input type="email" class="form-control" name="sender_email" value="{{ $settings['outreach']['sender_email'] ?? '' }}" placeholder="partnerships@urbangoodzdelivery.com">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">{{ translate('Max Followup Attempts') }}</label>
                                    <input type="number" class="form-control" name="max_attempts" value="{{ $settings['outreach']['max_contact_attempts'] ?? 4 }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">{{ translate('Active Hours (Start)') }}</label>
                                    <input type="time" class="form-control" name="hours_start" value="{{ $settings['outreach']['sending_hours_start'] ?? '09:00' }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">{{ translate('Active Hours (End)') }}</label>
                                    <input type="time" class="form-control" name="hours_end" value="{{ $settings['outreach']['sending_hours_end'] ?? '17:00' }}">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn--primary">{{ translate('Save Settings') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
