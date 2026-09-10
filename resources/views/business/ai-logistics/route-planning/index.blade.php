@extends('business.layouts.app')

@section('title', translate('Route Planning'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Route Planning') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Route Planning') }}</h1>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ translate('Active Routes') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Route') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Packages') }}</th></tr></thead>
                    <tbody>
                        @forelse($activeRoutes as $route)
                        <tr>
                            <td>{{ $route->route_name ?? ('#'.$route->id) }}</td>
                            <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_',' ', $route->status)) }}</span></td>
                            <td>{{ $route->packages->count() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">{{ translate('No active routes.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($activeRoutes instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $activeRoutes->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ translate('Unassigned Packages') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Tracking ID') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse($unassignedPackages as $pkg)
                        <tr><td>{{ $pkg->tracking_id ?? ('#'.$pkg->id) }}</td><td>{{ ucwords(str_replace('_',' ', $pkg->status)) }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">{{ translate('No unassigned packages.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
