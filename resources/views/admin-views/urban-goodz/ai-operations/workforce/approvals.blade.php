@extends('layouts.admin.app')

@section('title', translate('AI Approvals'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('AI Approvals') }}</h1>
                <p class="text-muted">{{ translate('Review pending and completed approvals for AI workforce actions.') }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-back"></i> {{ translate('Workforce Overview') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline-secondary">
                    <i class="tio-arrow-back"></i> {{ translate('AI Operations') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Action') }}</th>
                                <th>{{ translate('Agent') }}</th>
                                <th>{{ translate('Risk') }}</th>
                                <th>{{ translate('Decision') }}</th>
                                <th>{{ translate('Requested By') }}</th>
                                <th>{{ translate('Approved By') }}</th>
                                <th>{{ translate('Updated') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approvals as $approval)
                                <tr>
                                    <td>{{ $approval->action?->action_type ?? translate('Unknown') }}</td>
                                    <td>{{ $approval->action?->agent?->name ?? translate('Unknown') }}</td>
                                    <td>{{ ucfirst($approval->risk_level) }}</td>
                                    <td>{{ ucfirst($approval->decision) }}</td>
                                    <td>{{ $approval->requestedApprover?->name ?? translate('System') }}</td>
                                    <td>{{ $approval->approver?->name ?? translate('Pending') }}</td>
                                    <td>{{ $approval->decided_at?->diffForHumans() ?? translate('N/A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">{{ translate('No approvals found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $approvals->links() }}
        </div>
    </div>
@endsection
