@extends('business.layouts.app')

@section('title', translate('Package Clustering'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Package Clustering') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Package Clustering') }}</h1>
        <p class="text-muted mb-0">{{ translate('Group pool packages into efficient routes') }}</p>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ translate('Pool Packages') }} ({{ $poolPackages->count() }})</h5>
            <button type="button" id="clusterBtn" class="btn btn-sm" style="background-color: var(--ug-primary); color: #fff;">{{ translate('Cluster into Routes') }}</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th></th><th>{{ translate('Tracking ID') }}</th><th>{{ translate('Pickup') }}</th><th>{{ translate('Delivery') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse($poolPackages as $pkg)
                        <tr>
                            <td><input type="checkbox" class="cluster-pkg" value="{{ $pkg->id }}"></td>
                            <td>{{ $pkg->tracking_id ?? ('#'.$pkg->id) }}</td>
                            <td><small>{{ $pkg->pickupLocation->name ?? '—' }}</small></td>
                            <td><small>{{ $pkg->deliveryLocation->name ?? '—' }}</small></td>
                            <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_',' ', $pkg->status)) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No packages waiting to be clustered.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div id="clusterResult" class="mt-3"></div>
@endsection

@push('script')
<script>
document.getElementById('clusterBtn')?.addEventListener('click', function () {
    var ids = Array.from(document.querySelectorAll('.cluster-pkg:checked')).map(function (c) { return c.value; });
    fetch("{{ route('business.ai-logistics.package-cluster') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ package_ids: ids }),
    }).then(function (r) { return r.json(); }).then(function (data) {
        document.getElementById('clusterResult').innerHTML = '<div class="alert alert-info">' +
            (data.groups ? data.groups.length : 0) + ' {{ translate("route groups suggested") }}</div>';
    });
});
</script>
@endpush
