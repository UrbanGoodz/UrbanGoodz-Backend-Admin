@extends('layouts.admin.app')

@section('title', translate('AI Workforce Overview'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('AI Workforce Overview') }}</h1>
                <p class="text-muted">{{ translate('Monitor AI agents, tasks, approvals, and outreach prospects in one place.') }}</p>
            </div>
            <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-back"></i> {{ translate('Back to AI Operations') }}
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $agentCount }}</h2>
                        <p class="mb-0 text-muted">{{ translate('AI Agents') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $activeAgentCount }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Active Agents') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $pendingTaskCount }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Pending Tasks') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $awaitingApprovalCount }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Tasks Awaiting Approval') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $pendingActionCount }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Pending Actions') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $pendingApprovalCount }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Pending Approvals') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $contactableProspects }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Contactable Prospects') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h2 class="mb-1">{{ $activeTemplates }}</h2>
                        <p class="mb-0 text-muted">{{ translate('Active Outreach Templates') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.agents') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('View Agents') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Review current AI workforce capabilities and status.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.tasks') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('View Tasks') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Inspect pending and completed AI tasks.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.actions') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('View Actions') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Audit executed actions and token/cost metrics.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.approvals') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('View Approvals') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Track pending and completed AI approvals.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.prospects') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('View Prospects') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Manage AI-generated merchant prospect outreach.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.business-needs') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('Business Needs') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Review shortages, low stock, and delivery alerts.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.human-action-items') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('Human Action Items') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Inspect tasks assigned to operational roles.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.briefs') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('Role & Executive Briefs') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Generate customized briefings for Owner/Dispatcher/Finance.') }}</p>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.settings') }}" class="card card-body text-center text-decoration-none h-100">
                    <h5 class="mb-2">{{ translate('Settings') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Configure global kill switches and limits.') }}</p>
                </a>
            </div>
        </div>
    </div>
@endsection
