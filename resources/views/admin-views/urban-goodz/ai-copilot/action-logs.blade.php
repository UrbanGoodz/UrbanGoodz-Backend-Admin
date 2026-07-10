@extends('layouts.admin.app')

@section('title', translate('AI Action Logs'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Copilot') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Action Logs') }}</h1>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Audit Trail') }}</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="module" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Modules') }}</option>
                        @foreach($modules as $m)
                        <option value="{{ $m }}" {{ request('module') === $m ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $m)) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="action_taken" class="form-control form-control-sm" placeholder="{{ translate('Search action...') }}" value="{{ request('action_taken') }}">
                    <button type="submit" class="btn btn-sm btn--primary"><i class="tio-search"></i></button>
                    @if(count(request()->query()) > 0)
                    <a href="{{ route('admin.urban-goodz.ai-copilot.action-logs') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Action') }}</th>
                                <th>{{ translate('Module') }}</th>
                                <th>{{ translate('Entity') }}</th>
                                <th>{{ translate('Mode') }}</th>
                                <th>{{ translate('Timestamp') }}</th>
                                <th>{{ translate('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td>
                                    <strong>{{ $log->action_taken }}</strong>
                                    @if($log->reason)
                                    <br><small class="text-muted" style="max-width: 200px; display: inline-block;" class="text-truncate">{{ $log->reason }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($log->module)
                                    <span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $log->module)) }}</span>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->affected_user_type)
                                    <small>
                                        {{ class_basename($log->affected_user_type) }} #{{ $log->affected_user_id }}
                                    </small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->automation_mode)
                                    <span class="badge badge-soft-secondary">{{ str_replace('_', ' ', $log->automation_mode) }}</span>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $log->created_at->format('M d, h:i A') }}</small></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline--primary" data-toggle="modal" data-target="#logModal-{{ $log->id }}">
                                        <i class="tio-visible"></i>
                                    </button>
                                </td>
                            </tr>

                            <div class="modal fade" id="logModal-{{ $log->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">{{ translate('Action Log') }} #{{ $log->id }}</h5>
                                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-4">
                                                    <strong>{{ translate('Action') }}:</strong>
                                                    <p>{{ $log->action_taken }}</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>{{ translate('Module') }}:</strong>
                                                    <p>{{ $log->module ? ucwords(str_replace('_', ' ', $log->module)) : '-' }}</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>{{ translate('Automation Mode') }}:</strong>
                                                    <p>{{ $log->automation_mode ? str_replace('_', ' ', $log->automation_mode) : '-' }}</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>{{ translate('Reason') }}:</strong>
                                                    <p>{{ $log->reason ?? '-' }}</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>{{ translate('Approved By') }}:</strong>
                                                    <p>{{ $log->approver ? $log->approver->name : 'AI (auto)' }}</p>
                                                </div>
                                            </div>
                                            @if($log->before_value)
                                            <div class="mb-3">
                                                <strong>{{ translate('Before') }}:</strong>
                                                <pre class="bg-light p-2 rounded" style="font-size: 0.8rem; max-height: 150px; overflow-y: auto;">{{ json_encode(json_decode($log->before_value), JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                            @endif
                                            @if($log->after_value)
                                            <div class="mb-3">
                                                <strong>{{ translate('After') }}:</strong>
                                                <pre class="bg-light p-2 rounded" style="font-size: 0.8rem; max-height: 150px; overflow-y: auto;">{{ json_encode(json_decode($log->after_value), JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                            @endif
                                            @if($log->rollback_available)
                                            <div class="alert alert-info mb-0">
                                                <i class="tio-refresh"></i>
                                                {{ translate('Rollback is available for this action') }}
                                            </div>
                                            @endif
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No action logs recorded yet') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
