@extends('layouts.admin.app')

@section('title', translate('AI Module Automation Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Copilot') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Module Automation Settings') }}</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Module Automation Configuration') }}</h5>
                <small class="text-muted">{{ translate('Configure per-module automation behavior. Modules must be enabled here AND have the appropriate global mode to auto-execute.') }}</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Module') }}</th>
                                <th class="text-center">{{ translate('Enabled') }}</th>
                                <th>{{ translate('Mode') }}</th>
                                <th>{{ translate('Min Confidence') }}</th>
                                <th>{{ translate('Max Auto Amount') }}</th>
                                <th>{{ translate('Max Risk Level') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($modules as $mod)
                            <tr>
                                <td><strong>{{ ucwords(str_replace('_', ' ', $mod->module)) }}</strong></td>
                                <td class="text-center">
                                    @if($mod->enabled)
                                        <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td>{{ $mod->automation_mode ? ucwords(str_replace('_', ' ', $mod->automation_mode)) : '-' }}</td>
                                <td>{{ number_format($mod->min_confidence_score * 100) }}%</td>
                                <td>{{ $mod->max_auto_action_amount ? '$' . number_format($mod->max_auto_action_amount, 2) : '-' }}</td>
                                <td><span class="badge badge-soft-{{ $mod->max_risk_level === 'critical' ? 'danger' : ($mod->max_risk_level === 'high' ? 'warning' : 'info') }}">{{ ucfirst($mod->max_risk_level) }}</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn--primary" data-toggle="modal" data-target="#editModal-{{ $mod->id }}">
                                        <i class="tio-edit"></i>
                                    </button>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal-{{ $mod->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.module-settings.save') }}">
                                        @csrf
                                        <input type="hidden" name="module_id" value="{{ $mod->id }}">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ ucwords(str_replace('_', ' ', $mod->module)) }}</h5>
                                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="toggle-switch">
                                                                <input type="hidden" name="enabled" value="0">
                                                                <input type="checkbox" name="enabled" value="1" {{ $mod->enabled ? 'checked' : '' }}>
                                                                <span class="toggle-switch-slider"></span>
                                                                <span class="ml-2">{{ translate('Enable Module Automation') }}</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>{{ translate('Automation Mode') }}</label>
                                                            <select name="automation_mode" class="form-control">
                                                                <option value="">{{ translate('Inherit global') }}</option>
                                                                <option value="supervised_automation" {{ $mod->automation_mode === 'supervised_automation' ? 'selected' : '' }}>{{ translate('Supervised') }}</option>
                                                                <option value="full_low_risk_automation" {{ $mod->automation_mode === 'full_low_risk_automation' ? 'selected' : '' }}>{{ translate('Full Low-Risk') }}</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>{{ translate('Min Confidence Score') }}</label>
                                                            <input type="number" name="min_confidence_score" class="form-control" step="0.01" min="0" max="1" value="{{ $mod->min_confidence_score }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>{{ translate('Max Auto Action Amount ($)') }}</label>
                                                            <input type="number" name="max_auto_action_amount" class="form-control" step="0.01" min="0" value="{{ $mod->max_auto_action_amount }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>{{ translate('Max Risk Level') }}</label>
                                                            <select name="max_risk_level" class="form-control">
                                                                <option value="low" {{ $mod->max_risk_level === 'low' ? 'selected' : '' }}>{{ translate('Low') }}</option>
                                                                <option value="medium" {{ $mod->max_risk_level === 'medium' ? 'selected' : '' }}>{{ translate('Medium') }}</option>
                                                                <option value="high" {{ $mod->max_risk_level === 'high' ? 'selected' : '' }}>{{ translate('High') }}</option>
                                                                <option value="critical" {{ $mod->max_risk_level === 'critical' ? 'selected' : '' }}>{{ translate('Critical') }}</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                                                <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No module settings found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
