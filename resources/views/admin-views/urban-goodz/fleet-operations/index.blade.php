@extends('layouts.admin.app')

@section('title', translate('Fleet Operations'))

@section('content')
    <div class="content container-fluid">
        <!-- Breadcrumb & Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Fleet Operations') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Fleet Operations') }}</h1>
            </div>
        </div>

        <!-- Command Pulse -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-primary">{{ $summary['drivers']['total'] }}</div>
                        <small class="text-muted">{{ translate('Total Drivers') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-success">{{ $summary['drivers']['available'] }}</div>
                        <small class="text-muted">{{ translate('Available') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-info">{{ $summary['drivers']['on_business_job'] }}</div>
                        <small class="text-muted">{{ translate('On Business Job') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-secondary">{{ $summary['drivers']['offline'] }}</div>
                        <small class="text-muted">{{ translate('Offline') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-warning">{{ $summary['drivers']['pending_approval'] }}</div>
                        <small class="text-muted">{{ translate('Pending Approval') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-danger">{{ $summary['drivers']['suspended'] }}</div>
                        <small class="text-muted">{{ translate('Suspended') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-primary">{{ $summary['deliveries_today']['total'] }}</div>
                        <small class="text-muted">{{ translate('Deliveries Today') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-success">{{ $summary['deliveries_today']['completed'] }}</div>
                        <small class="text-muted">{{ translate('Completed') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-info">{{ $summary['deliveries_today']['in_progress'] }}</div>
                        <small class="text-muted">{{ translate('In Progress') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text-danger">{{ $summary['deliveries_today']['failed'] }}</div>
                        <small class="text-muted">{{ translate('Failed / Canceled') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Deliveries -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Active Deliveries') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Order') }}</th>
                                <th>{{ translate('Store / Vendor') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Assigned') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeDeliveries as $key => $order)
                                <tr>
                                    <td>{{ $activeDeliveries->firstItem() + $key }}</td>
                                    <td>
                                        <a href="{{ route('admin.order.details', $order->id) }}" class="text-primary fw-semibold">
                                            #{{ $order->id }}
                                        </a>
                                    </td>
                                    <td>{{ $order->store->name ?? '—' }}</td>
                                    <td>
                                        @if($order->delivery_man)
                                            <a href="{{ route('admin.delivery-man.preview', $order->delivery_man->id) }}">
                                                {{ $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-info">
                                            {{ translate(ucfirst(str_replace('_', ' ', $order->order_status))) }}
                                        </span>
                                    </td>
                                    <td>{{ $order->updated_at?->format('M d, Y g:i A') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        {{ translate('No active deliveries.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $activeDeliveries->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
