@extends('layouts.admin.app')

@section('title', translate('AI Workforce Actions'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('AI Workforce Actions') }}</h1>
                <p class="text-muted">{{ translate('Audit log of all executed operations by AI agents.') }}</p>
            </div>
            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-back"></i> {{ translate('Back to Workforce') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-styled table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('ID') }}</th>
                                <th>{{ translate('Agent') }}</th>
                                <th>{{ translate('Action Type') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Approval Status') }}</th>
                                <th>{{ translate('Tokens Used') }}</th>
                                <th>{{ translate('Estimated Cost') }}</th>
                                <th>{{ translate('Timestamp') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($actions as $action)
                                <tr>
                                    <td>#{{ $action->id }}</td>
                                    <td>
                                        @if($action->agent)
                                            <span class="badge badge-soft-info">{{ $action->agent->name }}</span>
                                        @else
                                            <span class="text-muted">{{ translate('System') }}</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $action->action_type }}</code></td>
                                    <td>
                                        <span class="badge badge-soft-{{ $action->status === 'completed' ? 'success' : 'danger' }}">
                                            {{ $action->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $action->approval_status === 'approved' ? 'success' : ($action->approval_status === 'rejected' ? 'danger' : 'warning') }}">
                                            {{ $action->approval_status ?: translate('none') }}
                                        </span>
                                    </td>
                                    <td>{{ $action->tokens_used ?? 0 }}</td>
                                    <td>${{ number_format($action->estimated_cost ?? 0, 4) }}</td>
                                    <td>{{ $action->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center p-4">
                                        <p class="text-muted mb-0">{{ translate('No actions recorded yet.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($actions->hasPages())
                <div class="card-footer">
                    {!! $actions->links() !!}
                </div>
            @endif
        </div>
    </div>
@endsection
