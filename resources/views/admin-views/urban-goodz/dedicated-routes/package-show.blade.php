@extends('layouts.admin.app')

@section('title', translate('Package') . ' - ' . $package->tracking_id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Package') }}: {{ $package->tracking_id }}</h1>
            <div class="d-flex gap-1">
                <a href="{{ route('admin.urban-goodz.dedicated-routes.package-scans', [$route->id, $package->id]) }}" class="btn btn-outline-secondary">
                    <i class="tio-history"></i> {{ translate('View Scans') }}
                </a>
                <a href="{{ route('admin.urban-goodz.dedicated-routes.packages', $route->id) }}" class="btn btn-secondary">
                    <i class="tio-back"></i> {{ translate('Back to Packages') }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Package Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4"><strong>{{ translate('Tracking ID') }}:</strong> {{ $package->tracking_id }}</div>
                            <div class="col-md-4"><strong>{{ translate('External Ref') }}:</strong> {{ $package->external_reference ?? '—' }}</div>
                            <div class="col-md-4">
                                <strong>{{ translate('Status') }}:</strong>
                                @php $sMap = ['pending' => 'secondary', 'picked_up' => 'info', 'in_transit' => 'warning', 'delivered' => 'success', 'failed' => 'danger', 'returned' => 'dark']; @endphp
                                <span class="badge badge-soft-{{ $sMap[$package->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $package->status)) }}</span>
                            </div>
                            <div class="col-md-3"><strong>{{ translate('Package Type') }}:</strong> {{ ucfirst($package->package_type) }}</div>
                            <div class="col-md-3"><strong>{{ translate('Weight') }}:</strong> {{ $package->weight ? $package->weight . ' ' . $package->weight_unit : '—' }}</div>
                            <div class="col-md-3"><strong>{{ translate('Dimensions') }}:</strong> {{ $package->dimensions ?? '—' }}</div>
                            <div class="col-md-3">
                                <strong>{{ translate('Priority') }}:</strong>
                                @php $pMap = ['normal' => 'secondary', 'high' => 'info', 'urgent' => 'warning', 'medical' => 'danger']; @endphp
                                <span class="badge badge-soft-{{ $pMap[$package->priority] ?? 'secondary' }}">{{ ucfirst($package->priority) }}</span>
                            </div>
                            <div class="col-md-4"><strong>{{ translate('Temperature Requirement') }}:</strong> {{ $package->temperature_requirement ?? 'Ambient' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Signature Required') }}:</strong> {{ $package->requires_signature ? 'Yes' : 'No' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Photo Required') }}:</strong> {{ $package->requires_photo ? 'Yes' : 'No' }}</div>
                            <div class="col-md-4"><strong>{{ translate('Custody Required') }}:</strong> {{ $package->requires_custody ? 'Yes' : 'No' }}</div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Delivery Information') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6"><strong>{{ translate('Dropoff Name') }}:</strong> {{ $package->dropoff_name ?? 'N/A' }}</div>
                            <div class="col-md-6"><strong>{{ translate('Dropoff Phone') }}:</strong> {{ $package->dropoff_phone ?? '—' }}</div>
                            <div class="col-12"><strong>{{ translate('Dropoff Address') }}:</strong> {{ $package->dropoff_address }}</div>
                            @if($package->delivery_window_start)
                                <div class="col-md-6"><strong>{{ translate('Delivery Window') }}:</strong> {{ $package->delivery_window_start->format('M d, g:i A') }} - {{ $package->delivery_window_end?->format('g:i A') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Scan History') }}</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <thead class="thead-light">
                                    <tr><th>{{ translate('Type') }}</th><th>{{ translate('Scanner') }}</th><th>{{ translate('Timestamp') }}</th><th>{{ translate('Notes') }}</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($package->scans as $scan)
                                        <tr>
                                            <td><span class="badge badge-soft-{{ $scan->scan_type === 'dropoff' ? 'success' : ($scan->scan_type === 'exception' ? 'danger' : 'info') }}">{{ ucwords(str_replace('_', ' ', $scan->scan_type)) }}</span></td>
                                            <td>{{ $scan->scanner?->f_name ?? 'System' }}</td>
                                            <td>{{ $scan->created_at->format('M d, g:i A') }}</td>
                                            <td>{{ $scan->exception_reason ?? $scan->notes ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-2">{{ translate('No scans recorded') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($package->requires_custody && $package->custodyLogs->count() > 0)
                    <div class="card">
                        <div class="card-header"><h5>{{ translate('Custody Log') }}</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="thead-light">
                                        <tr><th>{{ translate('Event') }}</th><th>{{ translate('From') }}</th><th>{{ translate('To') }}</th><th>{{ translate('Temp') }}</th><th>{{ translate('Seal') }}</th><th>{{ translate('Time') }}</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($package->custodyLogs as $log)
                                            <tr>
                                                <td>{{ ucwords(str_replace('_', ' ', $log->custody_event)) }}</td>
                                                <td>{{ $log->from_user_type }} #{{ $log->from_user_id }}</td>
                                                <td>{{ $log->to_user_type }} #{{ $log->to_user_id }}</td>
                                                <td>{{ $log->temperature ? $log->temperature . '°F' : '—' }}</td>
                                                <td>{{ $log->seal_intact ? 'Intact' : 'Broken' }}</td>
                                                <td>{{ $log->created_at->format('M d, g:i A') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Update Status') }}</h5></div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.dedicated-routes.package-update-status', [$route->id, $package->id]) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <select name="status" class="form-control">
                                    @foreach(['pending', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned'] as $s)
                                        <option value="{{ $s }}" @selected($package->status == $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="exception_reason" class="form-control" placeholder="{{ translate('Exception reason (if failed)') }}">
                            </div>
                            <div class="mb-2">
                                <textarea name="notes" class="form-control" rows="2" placeholder="{{ translate('Notes') }}">{{ $package->notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-warning btn-block">{{ translate('Update Status') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Earnings') }}</h5></div>
                    <div class="card-body">
                        @forelse($package->earnings as $earning)
                            <div class="d-flex justify-content-between">
                                <span>{{ ucwords(str_replace('_', ' ', $earning->earning_type)) }}</span>
                                <strong>${{ number_format($earning->amount, 2) }} <span class="badge badge-soft-{{ $earning->status === 'paid' ? 'success' : 'secondary' }}">{{ $earning->status }}</span></strong>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ translate('No earnings recorded') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
