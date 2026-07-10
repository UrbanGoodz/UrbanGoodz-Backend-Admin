@extends('layouts.admin.app')

@section('title', $manifest->manifest_name ?? translate('Manifest'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title mb-0">{{ $manifest->manifest_name ?? translate('Unnamed Manifest') }}</h1>
                <p class="text-muted mb-0" style="color: #6c757d !important;">
                    <a href="{{ route('admin.urban-goodz.business-clients.show', $manifest->business_client_id) }}" class="text--primary">
                        {{ $manifest->client?->company_name ?? 'Client #' . $manifest->business_client_id }}
                    </a>
                    &middot; {{ translate('Created') }}: {{ $manifest->created_at?->format('M d, Y h:i A') }}
                </p>
            </div>
            <a href="{{ route('admin.urban-goodz.manifests.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back') }}
            </a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--primary">{{ $manifest->total_packages }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Total') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--success">{{ $manifest->scanned_packages }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Scanned') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--info">{{ $packagesWithAddress ?? $manifest->valid_packages }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Valid Address') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--danger">{{ $packagesMissingAddress ?? $manifest->invalid_packages }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('No Address') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column justify-content-center py-3">
                        <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-1">
                            <span class="text-muted small" style="color: #6c757d !important;">
                                @php
                                    $statusMap = [
                                        'draft' => 'secondary', 'importing' => 'info', 'import_complete' => 'primary',
                                        'validating' => 'warning', 'validated' => 'success', 'grouping' => 'warning',
                                        'grouped' => 'success', 'approved' => 'success', 'canceled' => 'danger',
                                    ];
                                    $badge = $statusMap[$manifest->status] ?? 'secondary';
                                @endphp
                                <span class="badge badge-soft-{{ $badge }}" style="font-size: 0.9rem;">
                                    {{ ucwords(str_replace('_', ' ', $manifest->status)) }}
                                </span>
                            </span>
                            <small class="text-muted" style="color: #6c757d !important;">
                                {{ translate('Service') }}:
                                <strong>{{ $manifest->service_type ? ucwords(str_replace('_', ' ', $manifest->service_type)) : '-' }}</strong>
                            </small>
                        </div>
                        <small class="text-muted d-block" style="color: #6c757d !important;">
                            {{ translate('Service Date') }}: <strong>{{ $manifest->service_date?->format('M d, Y') ?? '-' }}</strong>
                        </small>
                        <small class="text-muted d-block" style="color: #6c757d !important;">
                            {{ translate('Pickup') }}:
                            <strong>{{ $manifest->pickupLocation?->name ?? $manifest->pickupLocation?->address ?? $manifest->pickup_location_text ?? translate('Not specified') }}</strong>
                        </small>
                        @if($readyForOptimization ?? false)
                        <span class="badge badge-soft-success mt-1" style="width: fit-content;">
                            <i class="tio-checkmark-circle"></i> {{ translate('Ready for Optimization') }}
                        </span>
                        @endif
                        @if($manifest->notes)
                        <small class="text-muted d-block mt-1" style="color: #6c757d !important;">
                            {{ translate('Notes') }}: {{ $manifest->notes }}
                        </small>
                        @endif
                        @if($manifest->manifest_session_id)
                        <small class="text-muted d-block mt-1" style="color: #6c757d !important;">
                            {{ translate('Session') }}: <code>{{ $manifest->manifest_session_id }}</code>
                        </small>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Packages') }} ({{ $manifest->packages->count() }})</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Barcode') }}</th>
                                <th>{{ translate('Tracking') }}</th>
                                <th>{{ translate('Dropoff Name') }}</th>
                                <th>{{ translate('Dropoff Address') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Scanned By') }}</th>
                                <th>{{ translate('Scanned At') }}</th>
                                <th>{{ translate('Route') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($manifest->packages as $pkg)
                            <tr>
                                <td>{{ $pkg->id }}</td>
                                <td><code>{{ $pkg->barcode ?? '-' }}</code></td>
                                <td><code>{{ $pkg->tracking_id }}</code></td>
                                <td>{{ $pkg->dropoff_name ?? '-' }}</td>
                                <td><small>{{ $pkg->dropoff_address ? Str::limit($pkg->dropoff_address, 40) : '-' }}</small></td>
                                <td>
                                    @php $sMap = ['pending_review' => 'warning', 'pending' => 'secondary', 'ready_for_route' => 'info']; @endphp
                                    <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">
                                        {{ ucwords(str_replace('_', ' ', $pkg->status)) }}
                                    </span>
                                </td>
                                <td><small>{{ $pkg->scannedByUser?->name ?? '-' }}</small></td>
                                <td><small>{{ $pkg->scanned_at?->format('M d, h:i A') ?? '-' }}</small></td>
                                <td>
                                    @if($pkg->dedicated_route_id)
                                    <a href="{{ route('admin.urban-goodz.dedicated-routes.show', $pkg->dedicated_route_id) }}" class="badge badge-soft-info">
                                        {{ translate('Assigned') }}
                                    </a>
                                    @else
                                    <span class="badge badge-soft-secondary">{{ translate('Unassigned') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    {{ translate('No packages in this manifest') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
