@extends('layouts.admin.app')

@section('title', translate('Urban Goodz AI Chief of Staff'))

@push('css_or_js')
<style>
    .card-stat {
        border-radius: 12px;
        padding: 20px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .badge-ug {
        background: #ED9914;
        color: #fff;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }

</style>
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-user-big mr-1"></i> {{ translate('Urban Goodz AI Chief of Staff') }}
                    <span class="badge badge-ug ml-2">{{ translate('Active Copilot') }}</span>
                </h1>
                <p class="page-header-text">{{ translate('Executive Operations Center, Today’s Brief, Action Center & Real-Time Record Links') }}</p>
                <small class="text-muted">
                    {{ translate('Grounded from live database records at') }}
                    {{ $brief['generated_at'] ?? now()->toIso8601String() }}
                </small>
            </div>
        </div>
    </div>

    {{-- Skylar's executive presence. The digital-human host is the single
         header for this page; a second persona panel would duplicate it. --}}
    <div class="mb-4">
        @include('admin-views.urban-goodz.ai-chief-of-staff.digital_human_widget')

        @if(($narration['reason'] ?? null) === 'provider_not_configured' && \Illuminate\Support\Facades\Route::has('admin.business-settings.openAI'))
            <div class="alert alert-soft-warning mt-2 mb-0">
                {{ translate('No AI provider is configured, so Skylar cannot brief you.') }}
                <a href="{{ route('admin.business-settings.openAI') }}">{{ translate('Configure the AI provider') }}</a>
            </div>
        @endif
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card-stat">
                <h5>{{ translate('Completed Tasks') }}</h5>
                <h2 class="text-success">{{ $summary['completed'] ?? 0 }}</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-stat">
                <h5>{{ translate('In Progress') }}</h5>
                <h2 class="text-primary">{{ $summary['in_progress'] ?? 0 }}</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-stat">
                <h5>{{ translate('Human Actions Required') }}</h5>
                <h2 class="text-warning">{{ $summary['human_actions_required'] ?? 0 }}</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-stat">
                <h5>{{ translate('Pending Approvals') }}</h5>
                <h2 class="text-info">{{ $summary['approvals'] ?? 0 }}</h2>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-warning mr-1"></i> {{ translate('Grounded Operational Alerts') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ translate('Condition') }}</th>
                                    <th>{{ translate('Count') }}</th>
                                    <th>{{ translate('Source') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($brief['operational_alerts'] ?? []) as $alert)
                                    <tr>
                                        <td>{{ $alert['label'] }}</td>
                                        <td>
                                            @if($alert['available'])
                                                <span class="badge badge-soft-{{ $alert['count'] > 0 ? ($alert['severity'] === 'high' ? 'danger' : 'warning') : 'success' }}">
                                                    {{ $alert['count'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-soft-secondary">{{ translate('Unavailable') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($alert['available'])
                                                <a href="{{ url($alert['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View Records') }}</a>
                                            @else
                                                <span class="text-muted">{{ translate('Module table not deployed') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-agenda mr-1"></i> {{ translate('Today’s Executive Brief') }} ({{ $brief['date'] ?? today()->toDateString() }})</h5>
                </div>
                <div class="card-body">
                    <h6>{{ translate('Active Business Needs') }}</h6>
                    @if(empty($brief['business_needs']) || count($brief['business_needs']) == 0)
                        <div class="alert alert-soft-success">{{ translate('No critical business needs detected. System operating smoothly.') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Title') }}</th>
                                        <th>{{ translate('Priority') }}</th>
                                        <th>{{ translate('Role') }}</th>
                                        <th>{{ translate('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($brief['business_needs'] as $need)
                                    <tr>
                                        <td><strong>{{ $need->title }}</strong><br><small>{{ $need->description }}</small></td>
                                        <td><span class="badge badge-soft-{{ $need->priority == 'high' ? 'danger' : 'warning' }}">{{ ucfirst($need->priority) }}</span></td>
                                        <td>{{ $need->assigned_human_role }}</td>
                                        <td><a href="{{ route('admin.urban-goodz.ai-operations.workforce.business-needs') }}" class="btn btn-xs btn-outline-primary">{{ translate('View Record') }}</a></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-flash mr-1"></i> {{ translate('Action Center') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.tasks') }}">{{ translate('Review Pending AI Tasks') }}</a>
                            <span class="badge badge-primary badge-pill">{{ $summary['planned'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.approvals') }}">{{ translate('Supervised Approvals Queue') }}</a>
                            <span class="badge badge-info badge-pill">{{ $summary['approvals'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.prospects') }}">{{ translate('Merchant Prospects Qualified') }}</a>
                            <span class="badge badge-success badge-pill">{{ $summary['results']['prospects_qualified'] ?? 0 }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
