@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Settings'))

@section('content')
    <div class="content container-fluid">

        {{-- Sub-Navigation --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Overview') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-link"></i> {{ translate('Sources') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Recommendations') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-refresh"></i> {{ translate('Sync Runs') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-warning"></i> {{ translate('Errors') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" class="btn btn--primary btn-sm">
                        <i class="tio-settings-outlined"></i> {{ translate('Settings') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Breadcrumb & Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="#">{{ translate('AI Operations') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Settings') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Load Sourcing Settings') }}</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.update-settings') }}">
            @csrf
            @method('PUT')

            {{-- Sourcing Behavior --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-settings-outlined mr-1"></i> {{ translate('Sourcing Behavior') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">{{ translate('Setting') }}</th>
                                    <th style="width: 35%;">{{ translate('Value') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Auto Sourcing Enabled') }}</td>
                                    <td>
                                        <label class="toggle-switch mb-0">
                                            <input type="hidden" name="auto_sourcing_enabled" value="0">
                                            <input type="checkbox" name="auto_sourcing_enabled" value="1" {{ ($settings['auto_sourcing_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('Automatically trigger syncs on the configured interval.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Default Refresh Interval (minutes)') }}</td>
                                    <td>
                                        <input type="number" name="default_refresh_interval" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['default_refresh_interval'] ?? 30 }}" min="5">
                                    </td>
                                    <td><small class="text-muted">{{ translate('How often to refresh sources if auto-sourcing is enabled.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Per-Source Refresh Interval (minutes)') }}</td>
                                    <td>
                                        <input type="number" name="per_source_refresh_interval" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['per_source_refresh_interval'] ?? 60 }}" min="5">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Override interval for individual sources.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Per-Saved-Search Interval (minutes)') }}</td>
                                    <td>
                                        <input type="number" name="per_saved_search_interval" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['per_saved_search_interval'] ?? 120 }}" min="10">
                                    </td>
                                    <td><small class="text-muted">{{ translate('How often to re-run saved searches with auto-alert enabled.') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Load Lifecycle --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-time mr-1"></i> {{ translate('Load Lifecycle') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">{{ translate('Setting') }}</th>
                                    <th style="width: 35%;">{{ translate('Value') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Maximum Load Age (hours)') }}</td>
                                    <td>
                                        <input type="number" name="max_load_age_hours" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['max_load_age_hours'] ?? 72 }}" min="1">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Loads older than this are excluded from search results.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Stale-Load Expiration (hours)') }}</td>
                                    <td>
                                        <input type="number" name="stale_load_expiration_hours" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['stale_load_expiration_hours'] ?? 168 }}" min="1">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Sourced loads are marked expired after this duration if not acted upon.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Duplicate Suppression') }}</td>
                                    <td>
                                        <label class="toggle-switch mb-0">
                                            <input type="hidden" name="duplicate_suppression" value="0">
                                            <input type="checkbox" name="duplicate_suppression" value="1" {{ ($settings['duplicate_suppression'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('Automatically mark duplicate loads detected across sources.') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Financial --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-money mr-1"></i> {{ translate('Financial') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">{{ translate('Setting') }}</th>
                                    <th style="width: 35%;">{{ translate('Value') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Missing-Rate Policy') }}</td>
                                    <td>
                                        <select name="missing_rate_policy" class="form-control form-control-sm" style="max-width: 200px;">
                                            <option value="exclude" {{ ($settings['missing_rate_policy'] ?? 'exclude') === 'exclude' ? 'selected' : '' }}>{{ translate('Exclude from results') }}</option>
                                            <option value="flag" {{ ($settings['missing_rate_policy'] ?? '') === 'flag' ? 'selected' : '' }}>{{ translate('Show but flag as incomplete') }}</option>
                                            <option value="include" {{ ($settings['missing_rate_policy'] ?? '') === 'include' ? 'selected' : '' }}>{{ translate('Include anyway') }}</option>
                                        </select>
                                    </td>
                                    <td><small class="text-muted">{{ translate('How to handle loads where the rate information is missing.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Platform Fee Percent (%)') }}</td>
                                    <td>
                                        <input type="number" step="0.1" name="platform_fee_percent" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['platform_fee_percent'] ?? 0 }}" min="0" max="100">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Platform fee deducted when calculating net driver payout.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Fuel Cost Per Mile ($)') }}</td>
                                    <td>
                                        <input type="number" step="0.01" name="fuel_cost_per_mile" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['fuel_cost_per_mile'] ?? 0.65 }}" min="0">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Estimated fuel cost used in net payout calculations.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Toll Estimation Per Mile ($)') }}</td>
                                    <td>
                                        <input type="number" step="0.01" name="toll_estimation_per_mile" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['toll_estimation_per_mile'] ?? 0.05 }}" min="0">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Estimated toll cost per mile for route calculations.') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Publishing --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-send mr-1"></i> {{ translate('Publishing') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">{{ translate('Setting') }}</th>
                                    <th style="width: 35%;">{{ translate('Value') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Auto-Publish Trusted Source') }}</td>
                                    <td>
                                        <label class="toggle-switch mb-0">
                                            <input type="hidden" name="auto_publish_trusted_source" value="0">
                                            <input type="checkbox" name="auto_publish_trusted_source" value="1" {{ ($settings['auto_publish_trusted_source'] ?? '0') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('Automatically publish loads from trusted sources to the load board.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Human Approval Required') }}</td>
                                    <td>
                                        <label class="toggle-switch mb-0">
                                            <input type="hidden" name="human_approval_required" value="0">
                                            <input type="checkbox" name="human_approval_required" value="1" {{ ($settings['human_approval_required'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('Require admin approval before loads are published or assigned.') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Limits --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-limit mr-1"></i> {{ translate('Limits') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">{{ translate('Setting') }}</th>
                                    <th style="width: 35%;">{{ translate('Value') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Maximum Results Per Run') }}</td>
                                    <td>
                                        <input type="number" name="max_results_per_run" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['max_results_per_run'] ?? 200 }}" min="10">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Maximum number of loads to return per sync or search run.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Retry Count') }}</td>
                                    <td>
                                        <input type="number" name="retry_count" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['retry_count'] ?? 3 }}" min="0" max="10">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Number of retries for failed sync operations.') }}</small></td>
                                </tr>
                                <tr>
                                    <td>{{ translate('Failure Pause Threshold') }}</td>
                                    <td>
                                        <input type="number" name="failure_pause_threshold" class="form-control form-control-sm" style="max-width: 140px;" value="{{ $settings['failure_pause_threshold'] ?? 5 }}" min="1">
                                    </td>
                                    <td><small class="text-muted">{{ translate('Pause source after this many consecutive failures.') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Alerting --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-notifications mr-1"></i> {{ translate('Alerting') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">{{ translate('Setting') }}</th>
                                    <th style="width: 35%;">{{ translate('Value') }}</th>
                                    <th>{{ translate('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Source Health Alerting') }}</td>
                                    <td>
                                        <label class="toggle-switch mb-0">
                                            <input type="hidden" name="source_health_alerting" value="0">
                                            <input type="checkbox" name="source_health_alerting" value="1" {{ ($settings['source_health_alerting'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td><small class="text-muted">{{ translate('Send alerts when sources experience failures, rate limits, or credential issues.') }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- AI Scoring Weights --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="tio-star mr-1"></i> {{ translate('AI Scoring Weights') }}</h5>
                    <small class="text-muted ms-2">{{ translate('Must total 100') }}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @php
                            $weightKeys = [
                                'profit' => 'Profit',
                                'rate_per_mile' => 'Rate/Mile',
                                'deadhead' => 'Deadhead',
                                'equipment_match' => 'Equipment Match',
                                'schedule_feasibility' => 'Schedule Feasibility',
                                'broker_quality' => 'Broker Quality',
                                'return_load' => 'Return Load',
                                'driver_preference' => 'Driver Preference',
                            ];
                        @endphp

                        @foreach($weightKeys as $key => $label)
                        <div class="col-md-3 col-6">
                            <label class="form-label fw-bold">{{ translate($label) }}</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="weight_{{ $key }}" class="form-control" value="{{ $settings['weight_' . $key] ?? 0 }}" min="0" max="100" step="1" onchange="updateWeightTotal()">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        @endforeach

                        <div class="col-12 mt-2">
                            <div class="d-flex align-items-center gap-3">
                                <strong>{{ translate('Total') }}:</strong>
                                <span id="weightTotal" class="fw-bold">0%</span>
                                <span id="weightWarning" class="badge badge-soft-danger d-none">{{ translate('Must equal 100%') }}</span>
                                <span id="weightOk" class="badge badge-soft-success d-none">{{ translate('Valid') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Save Button --}}
            <div class="d-flex justify-content-end mb-4">
                <button type="submit" class="btn btn--primary btn-lg">
                    <i class="tio-save"></i> {{ translate('Save Settings') }}
                </button>
            </div>

        </form>

    </div>

    @push('script')
    <script>
        function updateWeightTotal() {
            let total = 0;
            document.querySelectorAll('input[name^="weight_"]').forEach(function(input) {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('weightTotal').textContent = total + '%';
            document.getElementById('weightTotal').className = 'fw-bold ' + (total === 100 ? 'text-success' : 'text-danger');
            document.getElementById('weightWarning').classList.toggle('d-none', total === 100);
            document.getElementById('weightOk').classList.toggle('d-none', total !== 100);
        }
        updateWeightTotal();
    </script>
    @endpush
@endsection
