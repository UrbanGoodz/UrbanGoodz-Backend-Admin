@extends('layouts.admin.app')

@section('title', translate('Truck Purchase Timeline'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', request()->route('id') ?? 0) }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Truck Purchase Timeline Analysis') }}</h1>
        </div>

        <div class="alert alert-info mb-3">
            <strong>{{ translate('Important') }}:</strong>
            {{ translate('This timeline provides the underlying historical business evidence. It does NOT claim that the historical reconstruction proves why the truck was purchased. The evidence can be evaluated for that purpose.') }}
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Truck Purchase Date') }}</div>
                    <div class="h5">{{ $truck_purchase_date }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Pre-Purchase Avg Orders') }}</div>
                    <div class="h5">{{ $pre_purchase_avg_orders ? number_format($pre_purchase_avg_orders) : '-' }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Post-Purchase Avg Orders') }}</div>
                    <div class="h5">{{ $post_purchase_avg_orders ? number_format($post_purchase_avg_orders) : '-' }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Order Volume Change') }}</div>
                    <div class="h5">
                        @if($pre_purchase_avg_orders && $post_purchase_avg_orders)
                            @php $change = (($post_purchase_avg_orders - $pre_purchase_avg_orders) / $pre_purchase_avg_orders) * 100; @endphp
                            <span class="{{ $change >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 1) }}%
                            </span>
                        @else
                            -
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Timeline Sequence') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge badge-soft-primary me-2">{{ translate('BUSINESS OPERATIONS') }}</div>
                            <i class="tio-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge badge-soft-info me-2">{{ translate('DELIVERY VOLUME') }}</div>
                            <i class="tio-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge badge-soft-info me-2">{{ translate('DRIVER ACTIVITY') }}</div>
                            <i class="tio-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge badge-soft-info me-2">{{ translate('OWNER\'S DELIVERY ACTIVITY') }}</div>
                            <i class="tio-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge badge-soft-warning me-2"><i class="tio-car"></i> {{ translate('TRUCK PURCHASE — October 8, 2025') }}</div>
                            <i class="tio-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge badge-soft-success me-2">{{ translate('POST-PURCHASE DELIVERY ACTIVITY') }}</div>
                            <i class="tio-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="badge badge-soft-secondary me-2">{{ translate('TRUCK REPAIR PERIOD') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($pre_purchase_months->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Pre-Purchase Months') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Month') }}</th>
                                <th class="text-end">{{ translate('Orders') }}</th>
                                <th class="text-end">{{ translate('Platform Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pre_purchase_months as $m)
                            <tr>
                                <td>{{ $m->snapshot_month->format('M Y') }}</td>
                                <td class="text-end">{{ number_format($m->estimated_orders) }}</td>
                                <td class="text-end">${{ number_format($m->estimated_total_platform_revenue, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($purchase_month)
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">{{ translate('Purchase Month') }}: {{ $purchase_month->snapshot_month->format('M Y') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><strong>{{ translate('Orders') }}:</strong> {{ number_format($purchase_month->estimated_orders) }}</div>
                    <div class="col-md-4"><strong>{{ translate('Platform Revenue') }}:</strong> ${{ number_format($purchase_month->estimated_total_platform_revenue, 2) }}</div>
                    <div class="col-md-4"><strong>{{ translate('Net Income') }}:</strong> ${{ number_format($purchase_month->estimated_net_income, 2) }}</div>
                </div>
            </div>
        </div>
        @endif

        @if($post_purchase_months->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Post-Purchase Months') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Month') }}</th>
                                <th class="text-end">{{ translate('Orders') }}</th>
                                <th class="text-end">{{ translate('Platform Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($post_purchase_months as $m)
                            <tr>
                                <td>{{ $m->snapshot_month->format('M Y') }}</td>
                                <td class="text-end">{{ number_format($m->estimated_orders) }}</td>
                                <td class="text-end">${{ number_format($m->estimated_total_platform_revenue, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
