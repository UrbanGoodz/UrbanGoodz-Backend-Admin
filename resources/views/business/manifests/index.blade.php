@extends('business.layouts.app')

@section('title', translate('Manifests'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-header-title mb-0">{{ translate('Manifests') }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Manage package intake manifests for dedicated route processing') }}
            </p>
        </div>
        <a href="{{ route('business.manifests.create') }}" class="btn btn--primary">
            <i class="tio-add"></i> {{ translate('New Manifest') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Manifest Name') }}</th>
                            <th>{{ translate('Service Date') }}</th>
                            <th>{{ translate('Packages') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($manifests as $manifest)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <a href="{{ route('business.manifests.show', $manifest->id) }}" class="text--primary">
                                    {{ $manifest->manifest_name ?? translate('Unnamed Manifest') }}
                                </a>
                            </td>
                            <td>{{ $manifest->service_date?->format('M d, Y') ?? '-' }}</td>
                            <td>
                                <span class="badge badge-soft-info">{{ $manifest->total_packages }}</span>
                            </td>
                            <td>
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
                                <span class="badge badge-soft-{{ $badge }}">{{ ucwords(str_replace('_', ' ', $manifest->status)) }}</span>
                            </td>
                            <td>{{ $manifest->created_at?->format('M d, Y h:i A') ?? '-' }}</td>
                            <td>
                                <a href="{{ route('business.manifests.show', $manifest->id) }}" class="btn btn-sm btn--primary">
                                    {{ translate('View') }}
                                </a>
                                @if($manifest->status === 'draft')
                                <a href="{{ route('business.manifests.scan', $manifest->id) }}" class="btn btn-sm btn-outline--primary">
                                    <i class="tio-barcode"></i> {{ translate('Scan') }}
                                </a>
                                @endif
                                <a href="{{ route('business.manifests.packages', $manifest->id) }}" class="btn btn-sm btn-outline-info">
                                    {{ translate('Packages') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                {{ translate('No manifests yet. Create one to start importing packages.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $manifests->links() }}
        </div>
    </div>
@endsection
