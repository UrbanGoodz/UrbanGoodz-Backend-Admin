@extends('layouts.admin.app')

@section('title', translate('Scans') . ' - ' . $package->tracking_id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Scan History') }}: {{ $package->tracking_id }}</h1>
            <a href="{{ route('admin.urban-goodz.dedicated-routes.package-show', [$route->id, $package->id]) }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back to Package') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('#') }}</th>
                                <th>{{ translate('Scan Type') }}</th>
                                <th>{{ translate('Scanned By') }}</th>
                                <th>{{ translate('Scanner Type') }}</th>
                                <th>{{ translate('Location') }}</th>
                                <th>{{ translate('Exception') }}</th>
                                <th>{{ translate('Notes') }}</th>
                                <th>{{ translate('Timestamp') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($package->scans as $key => $scan)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $scan->scan_type === 'pickup' ? 'info' : ($scan->scan_type === 'dropoff' ? 'success' : ($scan->scan_type === 'exception' ? 'danger' : 'secondary')) }}">
                                            {{ ucwords(str_replace('_', ' ', $scan->scan_type)) }}
                                        </span>
                                    </td>
                                    <td>{{ $scan->scanner?->f_name . ' ' . $scan->scanner?->l_name ?? 'System' }}</td>
                                    <td>{{ ucfirst($scan->scanner_type) }}</td>
                                    <td>
                                        @if($scan->latitude && $scan->longitude)
                                            {{ number_format($scan->latitude, 4) }}, {{ number_format($scan->longitude, 4) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $scan->exception_reason ?? '—' }}</td>
                                    <td>{{ $scan->notes ?? '—' }}</td>
                                    <td>{{ $scan->created_at->format('M d, Y g:i A') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-4">{{ translate('No scans recorded for this package') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($package->proof_photo || $package->recipient_signature)
            <div class="row g-3 mt-2">
                @if($package->proof_photo)
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h5>{{ translate('Proof Photo') }}</h5></div>
                            <div class="card-body text-center">
                                <img src="{{ $package->proof_photo }}" alt="Proof" style="max-width: 100%; max-height: 300px;" class="img-fluid rounded">
                            </div>
                        </div>
                    </div>
                @endif
                @if($package->recipient_signature)
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h5>{{ translate('Recipient Signature') }}</h5></div>
                            <div class="card-body text-center">
                                <img src="{{ $package->recipient_signature }}" alt="Signature" style="max-width: 100%; max-height: 150px;" class="img-fluid rounded border">
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
