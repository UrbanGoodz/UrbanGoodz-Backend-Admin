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
    .section-card { margin-bottom: 1.5rem; }
    .section-card .card-header h5 { margin-bottom: 0; font-size: 1rem; }
    .badge-healthy { background: #28a745; color: #fff; }
    .badge-warning { background: #ffc107; color: #212529; }
    .badge-critical { background: #dc3545; color: #fff; }
    .badge-unavailable { background: #6c757d; color: #fff; }
    .badge-not-configured { background: #17a2b8; color: #fff; }
    .unavailable-note { font-style: italic; color: #6c757d; }
    .section-label { font-weight: 600; }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    {{-- 1. Executive Briefing --}}
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-user-big mr-1"></i> {{ translate('Urban Goodz AI Chief of Staff') }}
                    <span class="badge badge-ug ml-2">{{ translate('Active Copilot') }}</span>
                </h1>
                <p class="page-header-text">{{ translate('Executive Operations Center, Today\'s Brief, Action Center & Real-Time Record Links') }}</p>
                <small class="text-muted">
                    {{ translate('Grounded from live database records at') }}
                    {{ $brief['generated_at'] ?? now()->toIso8601String() }}
                </small>
            </div>
        </div>
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
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-agenda mr-1"></i> {{ translate('Executive Briefing') }} ({{ $brief['date'] ?? today()->toDateString() }})</h5>
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

    {{-- 2. Critical Alerts --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-warning mr-1"></i> {{ translate('Critical Alerts') }}</h5>
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
                                                <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($alert['available'])
                                                <a href="{{ url($alert['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View Records') }}</a>
                                            @else
                                                <span class="unavailable-note">{{ translate('Module table not deployed') }}</span>
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
    </div>

    {{-- 3. Orders and Fulfillment --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-shopping-cart mr-1"></i> {{ translate('Orders and Fulfillment') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Metric') }}</th><th>{{ translate('Count') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                            <tbody>
                                @foreach($orders_fulfillment as $item)
                                <tr>
                                    <td class="section-label">{{ translate($item['label']) }}</td>
                                    <td>
                                        @if($item['available'])
                                            <span class="badge badge-soft-{{ $item['count'] > 0 ? 'danger' : 'success' }}">{{ $item['count'] }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ url($item['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Routes and Exceptions --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-route mr-1"></i> {{ translate('Routes and Exceptions') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Metric') }}</th><th>{{ translate('Count') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                            <tbody>
                                @foreach($routes_exceptions as $item)
                                <tr>
                                    <td class="section-label">{{ translate($item['label']) }}</td>
                                    <td>
                                        @if($item['available'])
                                            <span class="badge badge-soft-{{ $item['count'] > 0 ? 'warning' : 'success' }}">{{ $item['count'] }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ url($item['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Driver Operations --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-user mr-1"></i> {{ translate('Driver Operations') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Metric') }}</th><th>{{ translate('Count') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                            <tbody>
                                @foreach($driver_issues as $item)
                                <tr>
                                    <td class="section-label">{{ translate($item['label']) }}</td>
                                    <td>
                                        @if($item['available'])
                                            <span class="badge badge-soft-{{ $item['count'] > 0 ? 'warning' : 'success' }}">{{ $item['count'] }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ url($item['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 6. Vendors and Businesses --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-shop mr-1"></i> {{ translate('Vendors and Businesses') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Metric') }}</th><th>{{ translate('Count') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                            <tbody>
                                @foreach($vendor_business as $item)
                                <tr>
                                    <td class="section-label">{{ translate($item['label']) }}</td>
                                    <td>
                                        @if($item['available'])
                                            <span class="badge badge-soft-{{ $item['count'] > 0 ? 'warning' : 'success' }}">{{ $item['count'] }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ url($item['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 7. Payments and Ledger --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-credit-card mr-1"></i> {{ translate('Payments and Ledger') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Metric') }}</th><th>{{ translate('Count') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                            <tbody>
                                @foreach($payments_ledger as $item)
                                <tr>
                                    <td class="section-label">{{ translate($item['label']) }}</td>
                                    <td>
                                        @if($item['available'])
                                            <span class="badge badge-soft-{{ $item['count'] > 0 ? 'danger' : 'success' }}">{{ $item['count'] }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ url($item['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 8. Load Sourcing --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-local-shipping mr-1"></i> {{ translate('Load Sourcing') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Metric') }}</th><th>{{ translate('Count') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                            <tbody>
                                @foreach($load_sourcing as $item)
                                <tr>
                                    <td class="section-label">{{ translate($item['label']) }}</td>
                                    <td>
                                        @if($item['available'])
                                            <span class="badge badge-soft-{{ $item['count'] > 0 ? 'warning' : 'success' }}">{{ $item['count'] }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ url($item['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 9. AI Provider Health --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-smart-toy mr-1"></i> {{ translate('AI Provider Health') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>{{ translate('Property') }}</th><th>{{ translate('Value') }}</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td class="section-label">{{ translate('Status') }}</td>
                                    <td>
                                        @if($provider_health['available'] && $provider_health['configured'] && $provider_health['healthy'])
                                            <span class="badge badge-healthy">{{ translate('Healthy') }}</span>
                                        @elseif($provider_health['available'] && $provider_health['configured'] && !$provider_health['healthy'])
                                            <span class="badge badge-critical">{{ translate('Unhealthy') }}</span>
                                        @elseif($provider_health['available'] && !$provider_health['configured'])
                                            <span class="badge badge-not-configured">{{ translate('Not Configured') }}</span>
                                        @else
                                            <span class="badge badge-unavailable">{{ translate('Unavailable') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="section-label">{{ translate('Provider') }}</td>
                                    <td>{{ $provider_health['provider'] ?? translate('N/A') }}</td>
                                </tr>
                                <tr>
                                    <td class="section-label">{{ translate('Model') }}</td>
                                    <td>{{ $provider_health['model'] ?? translate('N/A') }}</td>
                                </tr>
                                <tr>
                                    <td class="section-label">{{ translate('Checked At') }}</td>
                                    <td>{{ $provider_health['checked_at'] ?? translate('N/A') }}</td>
                                </tr>
                                @if($provider_health['error_code'] ?? false)
                                <tr>
                                    <td class="section-label">{{ translate('Error') }}</td>
                                    <td class="text-danger">{{ $provider_health['error_code'] }}</td>
                                </tr>
                                @endif
                                @if($provider_health['reason'] ?? false)
                                <tr>
                                    <td class="section-label">{{ translate('Details') }}</td>
                                    <td class="unavailable-note">{{ $provider_health['reason'] }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 10. Recommended Actions --}}
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 section-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="tio-lightbulb mr-1"></i> {{ translate('Recommended Actions') }}</h5>
                </div>
                <div class="card-body">
                    <h6>{{ translate('Deterministic Recommendations') }} <small class="text-muted">({{ translate('from operational data') }})</small></h6>
                    @if(count($recommendations['deterministic'] ?? []) > 0)
                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead><tr><th>{{ translate('Recommendation') }}</th><th>{{ translate('Detail') }}</th><th>{{ translate('Priority') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                                <tbody>
                                    @foreach($recommendations['deterministic'] as $rec)
                                    <tr>
                                        <td class="section-label">{{ $rec['title'] }}</td>
                                        <td>{{ $rec['detail'] }}</td>
                                        <td><span class="badge badge-soft-{{ $rec['priority'] === 'high' ? 'danger' : ($rec['priority'] === 'medium' ? 'warning' : 'info') }}">{{ ucfirst($rec['priority']) }}</span></td>
                                        <td><a href="{{ url($rec['url']) }}" class="btn btn-xs btn-outline-primary">{{ translate('View') }}</a></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-soft-success mb-4">{{ translate('No high-priority deterministic recommendations.') }}</div>
                    @endif

                    <h6>{{ translate('AI-Generated Analysis') }}</h6>
                    @if(($recommendations['ai_analysis']['available'] ?? true) === false)
                        <div class="alert alert-soft-secondary">{{ translate('AI analysis unavailable') }}: {{ $recommendations['ai_analysis']['error'] ?? translate('Provider not configured or health check failed') }}</div>
                    @elseif(isset($recommendations['ai_analysis']['items']) && count($recommendations['ai_analysis']['items']) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead><tr><th>{{ translate('Recommendation') }}</th><th>{{ translate('Detail') }}</th><th>{{ translate('Priority') }}</th></tr></thead>
                                <tbody>
                                    @foreach($recommendations['ai_analysis']['items'] as $aiRec)
                                    <tr>
                                        <td class="section-label">{{ $aiRec['title'] }}</td>
                                        <td>{{ $aiRec['detail'] }}</td>
                                        <td><span class="badge badge-soft-{{ $aiRec['priority'] === 'high' ? 'danger' : ($aiRec['priority'] === 'medium' ? 'warning' : 'info') }}">{{ ucfirst($aiRec['priority']) }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">{{ translate('Provider') }}: {{ $recommendations['ai_analysis']['provider'] ?? 'N/A' }} | {{ translate('Model') }}: {{ $recommendations['ai_analysis']['model'] ?? 'N/A' }}</small>
                    @else
                        <div class="alert alert-soft-secondary">{{ translate('No AI-generated recommendations available. AI provider may not be configured.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
