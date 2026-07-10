@extends('business.layouts.app')

@section('title', translate('Package Pool'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="page-header-title">{{ translate('Package Pool') }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Unassigned packages awaiting route assignment') }}
                <span class="badge badge-soft-warning ms-1">{{ $packages->total() }} {{ translate('total') }}</span>
            </p>
        </div>
        <div class="d-flex gap-1">
            <a href="{{ route('business.packages.scan') }}" class="btn btn--primary">
                <i class="tio-barcode"></i> {{ translate('Scan Packages') }}
            </a>
            <a href="{{ route('business.routes.index') }}" class="btn btn-secondary">
                {{ translate('Routes') }}
            </a>
        </div>
    </div>

    @if($packages->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No packages in the pool') }}</h5>
            <p class="text-muted mb-3" style="color: #6c757d !important; max-width: 450px; margin: 0 auto 1rem;">
                {{ translate('Scan packages first, then assign them to routes from here.') }}
            </p>
            <a href="{{ route('business.packages.scan') }}" class="btn btn--primary">
                {{ translate('Scan Packages') }}
            </a>
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
                            <th>{{ translate('Barcode') }}</th>
                            <th>{{ translate('Recipient') }}</th>
                            <th>{{ translate('Address') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Scanned By') }}</th>
                            <th>{{ translate('Scanned At') }}</th>
                            <th style="width: 200px;">{{ translate('Assign To Route') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($packages as $i => $pkg)
                        <tr>
                            <td>{{ $packages->firstItem() + $i }}</td>
                            <td><code class="small">{{ $pkg->tracking_id }}</code></td>
                            <td><code class="small">{{ $pkg->barcode ?? '-' }}</code></td>
                            <td>{{ $pkg->dropoff_name ?? '-' }}</td>
                            <td>
                                {{ $pkg->dropoff_address ?? '-' }}
                                @if($pkg->dropoff_city)
                                    <br><small class="text-muted">{{ $pkg->dropoff_city }}, {{ $pkg->dropoff_state }} {{ $pkg->dropoff_zip }}</small>
                                @endif
                            </td>
                            <td>{{ $pkg->package_type ? ucfirst($pkg->package_type) : '-' }}</td>
                            <td>
                                @php $sMap = ['pending_review' => 'warning', 'pending' => 'secondary', 'ready_for_route' => 'info']; @endphp
                                <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $pkg->status)) }}</span>
                            </td>
                            <td>
                                <small>{{ $pkg->scannedByUser?->name ?? '-' }}</small>
                            </td>
                            <td>
                                @if($pkg->scanned_at)
                                    <small>{{ $pkg->scanned_at->format('M d, h:i A') }}</small>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td>
                                @if($routes->count() > 0)
                                <form action="{{ route('business.packages.assign', $pkg->id) }}" method="POST" class="d-flex gap-1">
                                    @csrf
                                    <input type="hidden" name="route_id" id="route-select-{{ $pkg->id }}">
                                    <select class="form-control form-control-sm" onchange="document.getElementById('route-select-{{ $pkg->id }}').value=this.value" required>
                                        <option value="">{{ translate('Select route...') }}</option>
                                        @foreach($routes as $route)
                                            <option value="{{ $route->id }}">{{ $route->route_name }} ({{ $route->scheduled_date ? $route->scheduled_date->format('M d') : '' }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn--primary">{{ translate('Assign') }}</button>
                                </form>
                                @else
                                <span class="text-muted small">{{ translate('No active routes') }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($packages->hasPages())
        <div class="card-footer">
            {{ $packages->links() }}
        </div>
        @endif
    </div>
    @endif
@endsection
