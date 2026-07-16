@extends('layouts.admin.app')

@section('title', translate('AI Action Logs'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to AI Operations') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Request & Action Logs') }}</h1>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Logs') }}</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="module" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Modules') }}</option>
                        @foreach($modules as $mod)
                        <option value="{{ $mod }}" {{ request('module') === $mod ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $mod)) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="action_taken" class="form-control form-control-sm" placeholder="{{ translate('Search action...') }}" value="{{ request('action_taken') }}">
                    <button type="submit" class="btn btn-sm btn--primary">{{ translate('Filter') }}</button>
                    @if(count(request()->query()) > 0)
                    <a href="{{ route('admin.urban-goodz.ai-operations.logs') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Action') }}</th>
                                <th>{{ translate('Module') }}</th>
                                <th>{{ translate('Reason') }}</th>
                                <th>{{ translate('Mode') }}</th>
                                <th>{{ translate('Approved By') }}</th>
                                <th>{{ translate('Rollback') }}</th>
                                <th>{{ translate('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td>
                                    <span class="badge badge-soft-primary">{{ $log->action_taken }}</span>
                                </td>
                                <td>
                                    @if($log->module)
                                    <span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $log->module)) }}</span>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td style="max-width: 250px;">
                                    <small class="text-truncate d-block" title="{{ $log->reason }}">{{ $log->reason ?? '-' }}</small>
                                </td>
                                <td>
                                    @if($log->automation_mode)
                                    <small>{{ str_replace('_', ' ', $log->automation_mode) }}</small>
                                    @else
                                    <small class="text-muted">manual</small>
                                    @endif
                                </td>
                                <td>
                                    @if($log->approver)
                                    <small>{{ $log->approver->name ?? 'Admin #' . $log->approved_by }}</small>
                                    @else
                                    <small class="text-muted">-</small>
                                    @endif
                                </td>
                                <td>
                                    @if($log->rollback_available)
                                    <span class="badge badge-soft-warning">{{ translate('Available') }}</span>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $log->created_at->format('M d, h:i A') }}</small></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">{{ translate('No action logs found.') }}</td>
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
