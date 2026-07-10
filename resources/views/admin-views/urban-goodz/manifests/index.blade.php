@extends('layouts.admin.app')

@section('title', translate('Manifests'))

@push('css_or_js')
<style>
    .filter-card { background: #f8f9fa; border-radius: 8px; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Manifests') }}</h1>
        </div>

        <div class="card filter-card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ translate('Client') }}</label>
                        <select name="client_id" class="form-control">
                            <option value="">{{ translate('All Clients') }}</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->company_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ translate('Status') }}</label>
                        <select name="status" class="form-control">
                            <option value="">{{ translate('All Statuses') }}</option>
                            @foreach(['draft', 'importing', 'import_complete', 'validating', 'validated', 'grouping', 'grouped', 'approved', 'canceled'] as $s)
                            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $s)) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn--primary">
                            <i class="tio-filter"></i> {{ translate('Filter') }}
                        </button>
                        <a href="{{ route('admin.urban-goodz.manifests.index') }}" class="btn btn-outline-secondary">
                            {{ translate('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Manifest Name') }}</th>
                                <th>{{ translate('Client') }}</th>
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
                                <td>{{ $manifest->id }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.manifests.show', $manifest->id) }}" class="text--primary">
                                        {{ $manifest->manifest_name ?? translate('Unnamed Manifest') }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.business-clients.show', $manifest->business_client_id) }}" class="text--primary">
                                        {{ $manifest->client?->company_name ?? 'ID: ' . $manifest->business_client_id }}
                                    </a>
                                </td>
                                <td>{{ $manifest->service_date?->format('M d, Y') ?? '-' }}</td>
                                <td><span class="badge badge-soft-info">{{ $manifest->total_packages }}</span></td>
                                <td>
                                    @php
                                        $statusMap = [
                                            'draft' => 'secondary', 'importing' => 'info', 'import_complete' => 'primary',
                                            'validating' => 'warning', 'validated' => 'success', 'grouping' => 'warning',
                                            'grouped' => 'success', 'approved' => 'success', 'canceled' => 'danger',
                                        ];
                                        $badge = $statusMap[$manifest->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge badge-soft-{{ $badge }}">{{ ucwords(str_replace('_', ' ', $manifest->status)) }}</span>
                                </td>
                                <td>{{ $manifest->created_at?->format('M d, Y') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.manifests.show', $manifest->id) }}" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ translate('No manifests found') }}
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
    </div>
@endsection
