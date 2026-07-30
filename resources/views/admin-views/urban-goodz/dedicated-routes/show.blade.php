@extends('layouts.admin.app')

@section('title', $route->route_name)

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ $route->route_name }}</h1>
            <div class="d-flex gap-1">
                <a href="{{ route('admin.urban-goodz.dedicated-routes.packages', $route->id) }}" class="btn btn-outline-primary">
                    <i class="tio-parcel"></i> {{ translate('Packages') }}
                </a>
                <form action="{{ route('admin.urban-goodz.dedicated-routes.optimize', $route->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Optimize and persist this route stop order?') }}')">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="tio-route"></i> {{ translate('Optimize') }}
                    </button>
                </form>
                <a href="{{ route('admin.urban-goodz.dedicated-routes.report', $route->id) }}" class="btn btn-outline-secondary">
                    <i class="tio-document"></i> {{ translate('Report') }}
                </a>
                <a href="{{ route('admin.urban-goodz.dedicated-routes.index') }}" class="btn btn-secondary">
                    <i class="tio-back"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Route Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4"><strong>{{ translate('Client') }}:</strong> {{ $route->client?->company_name ?? 'N/A' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Type') }}:</strong> {{ ucwords(str_replace('_', ' ', $route->route_type)) }}</div>
                            <div class="col-md-4">
                                <strong>{{ translate('Status') }}:</strong>
                                @php $statusMap = ['pending' => 'secondary', 'active' => 'info', 'in_progress' => 'warning', 'completed' => 'success', 'canceled' => 'danger']; @endphp
                                <span class="badge badge-soft-{{ $statusMap[$route->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $route->status)) }}</span>
                            </div>
                            <div class="col-md-4"><strong>{{ translate('Pickup') }}:</strong> {{ $route->pickup_location ?? 'Not set' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Scheduled') }}:</strong> {{ $route->scheduled_date?->format('M d, Y') ?? '—' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Vehicle') }}:</strong> {{ $route->vehicle_type_required ?? 'Any' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Optimization') }}:</strong> {{ ucwords(str_replace('_', ' ', $route->optimization_status ?? 'not_optimized')) }}</div>
                            <div class="col-md-4"><strong>{{ translate('Method') }}:</strong> {{ $route->optimization_method ?? '—' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Provider') }}:</strong> {{ $route->optimization_provider ?? '—' }}</div>
                            @php($ugCalcMode = $route->optimization_calculation_mode)
                            <div class="col-md-4"><strong>{{ translate('Distance Basis') }}:</strong>
                                <span class="badge badge-{{ $ugCalcMode === 'ROAD_NETWORK' ? 'success' : ($ugCalcMode === 'MANUAL_ORDER' ? 'info' : 'warning') }}">
                                    {{ translate(\App\Services\UrbanGoodz\Routing\DTOs\DistanceResult::labelForCalculationMode($ugCalcMode)) }}
                                </span>
                                @if($ugCalcMode !== 'ROAD_NETWORK')
                                    <small class="d-block text-muted">{{ translate('Not a road-network distance.') }}</small>
                                @endif
                            </div>
                            <div class="col-md-4"><strong>{{ translate('Original Distance') }}:</strong> {{ $route->original_distance_miles !== null ? number_format($route->original_distance_miles, 2).' mi' : '—' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Optimized Distance') }}:</strong> {{ $route->optimized_distance_miles !== null ? number_format($route->optimized_distance_miles, 2).' mi' : '—' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Estimated Duration') }}:</strong> {{ $route->optimized_duration_minutes !== null ? $route->optimized_duration_minutes.' min' : '—' }}</div>
                            <div class="col-12"><strong>{{ translate('Progress') }}:</strong>
                                <div class="progress" style="height: 10px; max-width: 300px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $route->progressPercent() }}%">{{ $route->progressPercent() }}%</div>
                                </div>
                                <small>{{ $route->completed_packages }}/{{ $route->total_packages }} delivered, {{ $route->failed_packages }} failed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Optimized Stops') }}</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless table-nowrap mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ translate('Tracking ID') }}</th>
                                        <th>{{ translate('Dropoff') }}</th>
                                        <th>{{ translate('Priority') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Window') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($route->optimizationStops->sortBy('stop_order') as $stop)
                                        <tr>
                                            <td>{{ $stop->stop_order }}</td>
                                            <td>
                                                <a href="{{ route('admin.urban-goodz.dedicated-routes.package-show', [$route->id, $stop->package_id]) }}" class="text-primary">
                                                    {{ $stop->package?->tracking_id ?? 'N/A' }}
                                                </a>
                                            </td>
                                            <td>{{ $stop->package?->dropoff_name ?? $stop->package?->dropoff_address }}</td>
                                            <td>
                                                @php $priorityMap = ['normal' => 'secondary', 'high' => 'info', 'urgent' => 'warning', 'medical' => 'danger']; @endphp
                                                <span class="badge badge-soft-{{ $priorityMap[$stop->package?->priority ?? 'normal'] }}">{{ ucwords($stop->package?->priority ?? 'normal') }}</span>
                                            </td>
                                            <td>{{ ucfirst($stop->package?->status ?? 'pending') }}</td>
                                            <td>
                                                @if($stop->package?->delivery_window_start)
                                                    {{ $stop->package->delivery_window_start->format('g:i A') }}
                                                    @if($stop->package->delivery_window_end)
                                                        - {{ $stop->package->delivery_window_end->format('g:i A') }}
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-3">{{ translate('No stops optimized yet. Click Optimize to generate.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Assign Driver') }}</h5></div>
                    <div class="card-body">
                        @if($route->assigned_driver_id)
                            <p><strong>{{ translate('Current Driver') }}:</strong> {{ $route->driver?->f_name . ' ' . $route->driver?->l_name }}</p>
                        @endif
                        <form action="{{ route('admin.urban-goodz.dedicated-routes.assign-driver', $route->id) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <select name="assigned_driver_id" class="form-control" required>
                                    <option value="">{{ translate('Select Driver') }}</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->id }}" @selected($route->assigned_driver_id == $driver->id)>
                                            {{ $driver->f_name . ' ' . $driver->l_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">{{ translate('Assign Driver') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Financial Summary') }}</h5></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1"><span>{{ translate('Driver Pay/Pkg') }}:</span><strong>${{ number_format($route->driver_pay_per_package, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-1"><span>{{ translate('Business Charge/Pkg') }}:</span><strong>${{ number_format($route->business_charge_per_package, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-1"><span>{{ translate('Pickup Bonus') }}:</span><strong>${{ number_format($route->pickup_bonus, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-1"><span>{{ translate('Completion Bonus') }}:</span><strong>${{ number_format($route->route_completion_bonus, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-1"><span>{{ translate('Priority Bonus') }}:</span><strong>${{ number_format($route->priority_package_bonus, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-1"><span>{{ translate('Partial Pay (Failed)') }}:</span><strong>${{ number_format($route->failed_delivery_partial_pay, 2) }}</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between"><span>{{ translate('Projected Driver Total') }}:</span><strong>${{ number_format($route->total_packages * $route->driver_pay_per_package + $route->route_completion_bonus, 2) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Projected Business Total') }}:</span><strong>${{ number_format($route->total_packages * $route->business_charge_per_package, 2) }}</strong></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Batches') }}</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <thead class="thead-light">
                                    <tr><th>{{ translate('Batch') }}</th><th>{{ translate('Packages') }}</th><th>{{ translate('Status') }}</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($route->batches as $batch)
                                        <tr>
                                            <td>{{ $batch->batch_number }}</td>
                                            <td>{{ $batch->package_count }}</td>
                                            <td><span class="badge badge-soft-{{ $batch->status === 'completed' ? 'success' : 'secondary' }}">{{ ucfirst($batch->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center py-2">{{ translate('Run optimization to create batches') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Update Status') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.dedicated-routes.update', $route->id) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <select name="status" class="form-control">
                                    @foreach(['pending', 'active', 'in_progress', 'completed', 'canceled'] as $s)
                                        <option value="{{ $s }}" @selected($route->status == $s)>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning btn-block">{{ translate('Update Status') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
