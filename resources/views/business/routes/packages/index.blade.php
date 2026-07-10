@extends('business.layouts.app')

@section('title', translate('Route Packages') . ' - ' . $route->route_name)

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="page-header-title">{{ translate('Packages') }}: {{ $route->route_name }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ $route->packages->where('status', 'pending')->count() }} {{ translate('pending') }},
                {{ $route->packages->whereIn('status', ['picked_up', 'in_transit'])->count() }} {{ translate('in transit') }},
                {{ $route->packages->where('status', 'delivered')->count() }} {{ translate('delivered') }},
                {{ $route->packages->where('status', 'failed')->count() }} {{ translate('failed') }}
            </p>
        </div>
        <div class="d-flex gap-1">
            <a href="{{ route('business.routes.packages.create', $route->id) }}" class="btn btn--primary">
                {{ translate('Add Package') }}
            </a>
            <a href="{{ route('business.routes.packages.upload', $route->id) }}" class="btn btn-outline--primary">
                {{ translate('Upload CSV') }}
            </a>
            <a href="{{ route('business.routes.show', $route->id) }}" class="btn btn-secondary">
                {{ translate('Back to Route') }}
            </a>
        </div>
    </div>

    @if($route->packages->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No packages on this route yet') }}</h5>
            <p class="text-muted mb-3" style="color: #6c757d !important; max-width: 450px; margin: 0 auto 1rem;">
                {{ translate('Add packages one at a time or upload a CSV file with all drop-off details.') }}
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="{{ route('business.routes.packages.create', $route->id) }}" class="btn btn--primary">
                    {{ translate('Add Package') }}
                </a>
                <a href="{{ route('business.routes.packages.upload', $route->id) }}" class="btn btn-outline--primary">
                    {{ translate('Upload CSV') }}
                </a>
            </div>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>{{ translate('Tracking') }}</th>
                            <th>{{ translate('Recipient') }}</th>
                            <th>{{ translate('Drop-off Address') }}</th>
                            <th>{{ translate('Phone') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Priority') }}</th>
                            <th>{{ translate('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($route->packages->sortBy('stop_order') as $i => $package)
                        <tr>
                            <td class="text-center">{{ $package->stop_order ?: $loop->iteration }}</td>
                            <td><code class="small">{{ $package->tracking_id ?? '-' }}</code></td>
                            <td>{{ $package->dropoff_name ?? '-' }}</td>
                            <td>
                                {{ $package->dropoff_address }}
                                @if($package->dropoff_city)
                                    <br><small class="text-muted">{{ $package->dropoff_city }}{{ $package->dropoff_state ? ', ' . $package->dropoff_state : '' }}{{ $package->dropoff_zip ? ' ' . $package->dropoff_zip : '' }}</small>
                                @endif
                            </td>
                            <td>{{ $package->dropoff_phone ?? '-' }}</td>
                            <td>{{ ucfirst($package->package_type) }}</td>
                            <td>
                                @php $pMap = ['normal' => 'secondary', 'high' => 'info', 'urgent' => 'warning', 'medical' => 'danger']; @endphp
                                <span class="badge badge-soft-{{ $pMap[$package->priority] ?? 'secondary' }}">{{ ucfirst($package->priority) }}</span>
                            </td>
                            <td>
                                @php $sMap = ['pending' => 'secondary', 'pending_review' => 'light', 'ready_for_route' => 'info', 'picked_up' => 'primary', 'in_transit' => 'warning', 'delivered' => 'success', 'failed' => 'danger', 'returned' => 'dark']; @endphp
                                <span class="badge badge-soft-{{ $sMap[$package->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $package->status)) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted">{{ $route->packages->count() }} {{ translate('total packages') }}</span>
        </div>
    </div>
    @endif
@endsection
