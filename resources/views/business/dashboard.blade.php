@extends('business.layouts.app')

@section('title', translate('Dashboard') . ' - ' . ($client->company_name ?? ''))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-header-title">{{ translate('Dashboard') }}</h1>
            @if($client)
            <p class="text-muted mb-0" style="color: #6c757d !important;">{{ $client->company_name }}</p>
            @endif
        </div>
    </div>

    @if($routes_count === 0 && $locations_count === 0 && $users_count === 0 && $documents_count === 0)
    <div class="card mb-3">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('Welcome to Your Business Portal') }}</h5>
            <p class="text-muted mb-0" style="color: #6c757d !important; max-width: 500px; margin: 0 auto;">
                {{ translate('From here you can manage courier routes, add locations, invite team members, and upload documents. Use the navigation above to get started or contact support if you need assistance.') }}
            </p>
        </div>
    </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Routes') }}</h6>
                    <h3>{{ $routes_count }}</h3>
                    @if($active_routes_count > 0)
                    <span class="badge badge-soft-success">{{ $active_routes_count }} {{ translate('Active') }}</span>
                    @endif
                    <a href="{{ route('business.routes.index') }}" class="small d-block mt-1">{{ translate('View All') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Locations') }}</h6>
                    <h3>{{ $locations_count }}</h3>
                    @if($locations_count === 0)
                    <span class="text-muted small" style="color: #6c757d !important;">{{ translate('No locations added yet') }}</span>
                    @endif
                    <a href="{{ route('business.locations.index') }}" class="small d-block mt-1">{{ translate('Manage') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Packages') }}</h6>
                    <h3>{{ $packages_count }}</h3>
                    @if($pool_count > 0)
                    <span class="badge badge-soft-warning">{{ $pool_count }} {{ translate('unassigned') }}</span>
                    @endif
                    <div class="d-flex gap-2 mt-1">
                        <a href="{{ route('business.packages.scan') }}" class="small">{{ translate('Scan') }}</a>
                        <a href="{{ route('business.packages.pool') }}" class="small">{{ translate('Pool') }}</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Manifests') }}</h6>
                    <h3>{{ $manifests_count ?? 0 }}</h3>
                    @if(($active_manifests_count ?? 0) > 0)
                    <span class="badge badge-soft-warning">{{ $active_manifests_count }} {{ translate('Active') }}</span>
                    @endif
                    <div class="d-flex gap-2 mt-1">
                        <a href="{{ route('business.manifests.index') }}" class="small">{{ translate('View All') }}</a>
                        <a href="{{ route('business.manifests.create') }}" class="small">{{ translate('New') }}</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Users') }}</h6>
                    <h3>{{ $users_count }}</h3>
                    @if($users_count === 0)
                    <span class="text-muted small" style="color: #6c757d !important;">{{ translate('No team members added yet') }}</span>
                    @endif
                    <a href="{{ route('business.users.index') }}" class="small d-block mt-1">{{ translate('Manage') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Documents') }}</h6>
                    <h3>{{ $documents_count }}</h3>
                    @if($documents_count === 0)
                    <span class="text-muted small" style="color: #6c757d !important;">{{ translate('No documents uploaded yet') }}</span>
                    @endif
                    <a href="{{ route('business.documents.index') }}" class="small d-block mt-1">{{ translate('Manage') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6>{{ translate('Invoices') }}</h6>
                    <h3>{{ $invoices_count }}</h3>
                    @if($invoices_count === 0)
                    <span class="text-muted small" style="color: #6c757d !important;">{{ translate('No invoices yet') }}</span>
                    @endif
                    <a href="{{ route('business.invoices.index') }}" class="small d-block mt-1">{{ translate('View') }}</a>
                </div>
            </div>
        </div>
    </div>

    @if($recent_routes->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <h5>{{ translate('Recent Routes') }}</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Route') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Scheduled') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent_routes as $route)
                        <tr>
                            <td>{{ $route->route_name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $route->route_type)) }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $route->status === 'active' ? 'success' : ($route->status === 'in_progress' ? 'info' : ($route->status === 'canceled' ? 'danger' : 'secondary')) }}">
                                    {{ ucfirst($route->status) }}
                                </span>
                            </td>
                            <td>{{ $route->scheduled_date?->format('M d, Y') ?? '-' }}</td>
                            <td>
                                <a href="{{ route('business.routes.show', $route->id) }}" class="btn btn-sm btn--primary">
                                    {{ translate('View') }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endsection
