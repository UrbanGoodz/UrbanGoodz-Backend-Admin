@extends('business.layouts.app')

@section('title', $manifest->manifest_name ?? translate('Manifest'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-header-title mb-0">{{ $manifest->manifest_name ?? translate('Unnamed Manifest') }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Created') }}: {{ $manifest->created_at?->format('M d, Y h:i A') }}
                &middot; {{ translate('Service Date') }}: {{ $manifest->service_date?->format('M d, Y') ?? '-' }}
            </p>
        </div>
        <div class="d-flex gap-1">
            @if($manifest->status === 'draft')
            <a href="{{ route('business.packages.scan', ['manifest_id' => $manifest->id]) }}" class="btn btn--primary">
                <i class="tio-barcode"></i> {{ translate('Scan Packages') }}
            </a>
            @endif
            <a href="{{ route('business.manifests.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back') }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h5 class="mb-0 text--primary">{{ $manifest->total_packages }}</h5>
                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('Total Packages') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h5 class="mb-0 text--success">{{ $manifest->scanned_packages }}</h5>
                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('Scanned') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h5 class="mb-0 text--info">{{ $packagesWithAddress ?? $manifest->valid_packages }}</h5>
                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('With Address') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h5 class="mb-0 text--danger">{{ $packagesMissingAddress ?? $manifest->invalid_packages }}</h5>
                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('Missing Address') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4 col-6">
            <div class="card">
                <div class="card-body text-center py-3">
                    @php
                        $statusMap = [
                            'draft' => 'secondary',
                            'importing' => 'info',
                            'import_complete' => 'primary',
                            'validating' => 'warning',
                            'validated' => 'success',
                            'grouping' => 'warning',
                            'grouped' => 'success',
                            'approved' => 'success',
                            'canceled' => 'danger',
                        ];
                        $badge = $statusMap[$manifest->status] ?? 'secondary';
                    @endphp
                    <span class="badge badge-soft-{{ $badge }}" style="font-size: 0.9rem;">
                        {{ ucwords(str_replace('_', ' ', $manifest->status)) }}
                    </span>
                    <div><small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h5 class="mb-0 text--primary">{{ $manifest->service_type ? ucwords(str_replace('_', ' ', $manifest->service_type)) : '-' }}</h5>
                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('Service Type') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block" style="color: #6c757d !important;">
                        {{ translate('Pickup') }}:
                        <strong>{{ $manifest->pickupLocation?->name ?? $manifest->pickupLocation?->address ?? $manifest->pickup_location_text ?? translate('Not specified') }}</strong>
                    </small>
                    @if($manifest->service_type)
                    <small class="text-muted d-block mt-1" style="color: #6c757d !important;">
                        {{ translate('Service') }}: <strong>{{ ucwords(str_replace('_', ' ', $manifest->service_type)) }}</strong>
                    </small>
                    @endif
                    @if($manifest->notes)
                    <small class="text-muted d-block mt-1" style="color: #6c757d !important;">
                        {{ translate('Notes') }}: {{ $manifest->notes }}
                    </small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">{{ translate('Packages') }}</h5>
            <div class="d-flex gap-1">
                @if($manifest->status === 'draft')
                <a href="{{ route('business.manifests.scan', $manifest->id) }}" class="btn btn-sm btn--primary">
                    <i class="tio-barcode"></i> {{ translate('Scan Packages') }}
                </a>
                @endif
                <a href="{{ route('business.manifests.packages', $manifest->id) }}" class="btn btn-sm btn-outline--primary">
                    <i class="tio-visible"></i> {{ translate('View All') }}
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Barcode') }}</th>
                            <th>{{ translate('Tracking') }}</th>
                            <th>{{ translate('Dropoff') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Scanned By') }}</th>
                            <th>{{ translate('Scanned At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($manifest->packages as $pkg)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td><code>{{ $pkg->barcode ?? '-' }}</code></td>
                            <td><code>{{ $pkg->tracking_id }}</code></td>
                            <td>
                                @if($pkg->dropoff_name)
                                <strong>{{ $pkg->dropoff_name }}</strong><br>
                                @endif
                                <small>{{ $pkg->dropoff_address ? Str::limit($pkg->dropoff_address, 50) : '-' }}</small>
                            </td>
                            <td>
                                @php $sMap = ['pending_review' => 'warning', 'pending' => 'secondary', 'ready_for_route' => 'info']; @endphp
                                <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">
                                    {{ ucwords(str_replace('_', ' ', $pkg->status)) }}
                                </span>
                            </td>
                            <td><small>{{ $pkg->scannedByUser?->name ?? '-' }}</small></td>
                            <td><small>{{ $pkg->scanned_at?->format('h:i A') ?? '-' }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                {{ translate('No packages in this manifest yet. Scan packages to add them.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
