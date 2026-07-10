@extends('business.layouts.app')

@section('title', translate('Packages') . ' - ' . ($manifest->manifest_name ?? ''))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-header-title mb-0">{{ translate('Manifest Packages') }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Manifest') }}: <strong>{{ $manifest->manifest_name ?? translate('Unnamed Manifest') }}</strong>
                &middot; {{ translate('Service Date') }}: {{ $manifest->service_date?->format('M d, Y') ?? '-' }}
            </p>
        </div>
        <div class="d-flex gap-1">
            @if($manifest->status === 'draft')
            <a href="{{ route('business.manifests.scan', $manifest->id) }}" class="btn btn--primary">
                <i class="tio-barcode"></i> {{ translate('Scan Packages') }}
            </a>
            @endif
            <a href="{{ route('business.manifests.show', $manifest->id) }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back') }}
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Barcode') }}</th>
                            <th>{{ translate('Tracking') }}</th>
                            <th>{{ translate('Recipient') }}</th>
                            <th>{{ translate('Dropoff Address') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Scanned By') }}</th>
                            <th>{{ translate('Scanned At') }}</th>
                            <th>{{ translate('Route') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $pkg)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td><code>{{ $pkg->barcode ?? '-' }}</code></td>
                            <td><code>{{ $pkg->tracking_id }}</code></td>
                            <td>{{ $pkg->dropoff_name ?? '-' }}</td>
                            <td>
                                <small>
                                    {{ $pkg->dropoff_address ? Str::limit($pkg->dropoff_address, 40) : '-' }}
                                    @if($pkg->dropoff_city || $pkg->dropoff_state)
                                    <br>{{ $pkg->dropoff_city ?? '' }}{{ $pkg->dropoff_city && $pkg->dropoff_state ? ', ' : '' }}{{ $pkg->dropoff_state ?? '' }}
                                    @endif
                                </small>
                            </td>
                            <td>
                                @php $sMap = ['pending_review' => 'warning', 'pending' => 'secondary', 'ready_for_route' => 'info', 'assigned' => 'primary']; @endphp
                                <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">
                                    {{ ucwords(str_replace('_', ' ', $pkg->status)) }}
                                </span>
                            </td>
                            <td><small>{{ $pkg->scannedByUser?->name ?? '-' }}</small></td>
                            <td><small>{{ $pkg->scanned_at?->format('M d, h:i A') ?? '-' }}</small></td>
                            <td>
                                @if($pkg->dedicated_route_id)
                                <span class="badge badge-soft-info">{{ translate('Assigned') }}</span>
                                @else
                                <span class="badge badge-soft-secondary">{{ translate('Unassigned') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                {{ translate('No packages in this manifest.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $packages->links() }}
        </div>
    </div>
@endsection
