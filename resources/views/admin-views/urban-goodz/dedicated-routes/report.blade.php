@extends('layouts.admin.app')

@section('title', translate('Route Report') . ' - ' . $route->route_name)

@push('css_or_js')
<style media="print">
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { font-size: 12pt; }
</style>
<style>
    .print-only { display: none; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 no-print">
            <h1 class="page-header-title">{{ translate('Route Report') }}: {{ $route->route_name }}</h1>
            <div class="d-flex gap-1">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="tio-print"></i> {{ translate('Print') }}
                </button>
                <a href="{{ route('admin.urban-goodz.dedicated-routes.export-report', $route->id) }}?format=csv" class="btn btn-outline-secondary">
                    <i class="tio-download"></i> {{ translate('Export CSV') }}
                </a>
                <a href="{{ route('admin.urban-goodz.dedicated-routes.show', $route->id) }}" class="btn btn-secondary">
                    <i class="tio-back"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="print-only text-center mb-3">
                    <h2>{{ translate('Urban Goodz - Route Report') }}</h2>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-soft-primary">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $route->total_packages }}</h3>
                                <small>{{ translate('Total Packages') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-soft-success">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $route->completed_packages }}</h3>
                                <small>{{ translate('Delivered') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-soft-danger">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $route->failed_packages }}</h3>
                                <small>{{ translate('Failed') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-soft-info">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $route->batches->count() }}</h3>
                                <small>{{ translate('Batches') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <h5>{{ translate('Route Information') }}</h5>
                        <table class="table table-sm table-borderless">
                            <tr><td width="150"><strong>{{ translate('Route Name') }}:</strong></td><td>{{ $route->route_name }}</td></tr>
                            <tr><td><strong>{{ translate('Client') }}:</strong></td><td>{{ $route->client?->company_name ?? 'N/A' }}</td></tr>
                            <tr><td><strong>{{ translate('Type') }}:</strong></td><td>{{ ucwords(str_replace('_', ' ', $route->route_type)) }}</td></tr>
                            <tr><td><strong>{{ translate('Driver') }}:</strong></td><td>{{ $route->driver?->f_name . ' ' . $route->driver?->l_name ?? 'Unassigned' }}</td></tr>
                            <tr><td><strong>{{ translate('Pickup') }}:</strong></td><td>{{ $route->pickup_location ?? 'N/A' }}</td></tr>
                            <tr><td><strong>{{ translate('Scheduled') }}:</strong></td><td>{{ $route->scheduled_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
                            @if($route->route_started_at)<tr><td><strong>{{ translate('Started') }}:</strong></td><td>{{ $route->route_started_at->format('M d, Y g:i A') }}</td></tr>@endif
                            @if($route->route_completed_at)<tr><td><strong>{{ translate('Completed') }}:</strong></td><td>{{ $route->route_completed_at->format('M d, Y g:i A') }}</td></tr>@endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>{{ translate('Financial Summary') }}</h5>
                        <table class="table table-sm table-borderless">
                            <tr><td width="200"><strong>{{ translate('Driver Pay Per Package') }}:</strong></td><td>${{ number_format($route->driver_pay_per_package, 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Business Charge Per Package') }}:</strong></td><td>${{ number_format($route->business_charge_per_package, 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Projected Driver Total') }}:</strong></td><td>${{ number_format($route->total_packages * $route->driver_pay_per_package, 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Projected Business Total') }}:</strong></td><td>${{ number_format($route->total_packages * $route->business_charge_per_package, 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Projected UG Margin') }}:</strong></td><td>${{ number_format($route->total_packages * ($route->business_charge_per_package - $route->driver_pay_per_package), 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Bonuses') }}:</strong></td><td>${{ number_format($route->route_completion_bonus + $route->pickup_bonus, 2) }}</td></tr>
                        </table>
                    </div>
                </div>

                <h5>{{ translate('Package Detail') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Tracking ID') }}</th>
                                <th>{{ translate('Dropoff') }}</th>
                                <th>{{ translate('Address') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Priority') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Pickup Scan') }}</th>
                                <th>{{ translate('Dropoff Scan') }}</th>
                                <th>{{ translate('Exception') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($route->packages as $key => $pkg)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $pkg->tracking_id }}@if($pkg->external_reference)<br><small>Ref: {{ $pkg->external_reference }}</small>@endif</td>
                                    <td>{{ $pkg->dropoff_name ?? 'N/A' }}</td>
                                    <td>{{ $pkg->dropoff_address }}</td>
                                    <td>{{ ucfirst($pkg->package_type) }}</td>
                                    <td>
                                        @php $pMap = ['normal' => 'secondary', 'high' => 'info', 'urgent' => 'warning', 'medical' => 'danger']; @endphp
                                        <span class="badge badge-soft-{{ $pMap[$pkg->priority] ?? 'secondary' }}">{{ ucfirst($pkg->priority) }}</span>
                                    </td>
                                    <td>
                                        @php $sMap = ['pending' => 'secondary', 'picked_up' => 'info', 'in_transit' => 'warning', 'delivered' => 'success', 'failed' => 'danger', 'returned' => 'dark']; @endphp
                                        <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $pkg->status)) }}</span>
                                    </td>
                                    <td>{{ $pkg->pickup_scanned_at?->format('M d, g:i A') ?? '—' }}</td>
                                    <td>{{ $pkg->dropoff_scanned_at?->format('M d, g:i A') ?? '—' }}</td>
                                    <td>{{ $pkg->exception_reason ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center py-3">{{ translate('No packages') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($route->earnings->count() > 0)
                    <h5 class="mt-4">{{ translate('Earnings') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr><th>{{ translate('Type') }}</th><th>{{ translate('Driver ID') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach($route->earnings as $earning)
                                    <tr>
                                        <td>{{ ucwords(str_replace('_', ' ', $earning->earning_type)) }}</td>
                                        <td>{{ $earning->delivery_man_id }}</td>
                                        <td>${{ number_format($earning->amount, 2) }}</td>
                                        <td><span class="badge badge-soft-{{ $earning->status === 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($earning->status) }}</span></td>
                                        <td>{{ $earning->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
