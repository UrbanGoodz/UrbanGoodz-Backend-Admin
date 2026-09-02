@extends('layouts.admin.app')

@section('title', translate('Monthly Snapshot Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', $configId) }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back to Reconstruction') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Snapshot') }}: {{ $snapshot->month_label }}</h1>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Operating Data') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <tr><td><strong>{{ translate('Reconstruction ID') }}</strong></td><td><code>{{ $snapshot->reconstruction_id }}</code></td></tr>
                                    <tr><td><strong>{{ translate('Month') }}</strong></td><td>{{ $snapshot->month_label }}</td></tr>
                                    <tr><td><strong>{{ translate('Estimated Orders') }}</strong></td><td>{{ number_format($snapshot->estimated_orders) }}</td></tr>
                                    <tr><td><strong>{{ translate('Avg Order Value') }}</strong></td><td>${{ number_format($snapshot->estimated_average_order_value, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Total Order Value') }}</strong></td><td>${{ number_format($snapshot->estimated_total_order_value, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Order Commission Revenue (23%)') }}</strong></td><td>${{ number_format($snapshot->estimated_order_commission_revenue, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Delivery Fee Revenue') }}</strong></td><td>${{ number_format($snapshot->estimated_delivery_fee_revenue, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Platform Delivery Fee Revenue (3%)') }}</strong></td><td>${{ number_format($snapshot->estimated_platform_delivery_fee_revenue, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Total Platform Revenue') }}</strong></td><td><strong>${{ number_format($snapshot->estimated_total_platform_revenue, 2) }}</strong></td></tr>
                                    <tr><td><strong>{{ translate('Active Drivers') }}</strong></td><td>{{ $snapshot->estimated_active_driver_count }}</td></tr>
                                    <tr><td><strong>{{ translate('Owner Deliveries') }}</strong></td><td>{{ $snapshot->estimated_owner_deliveries }}</td></tr>
                                    <tr><td><strong>{{ translate('Driver Payouts') }}</strong></td><td>${{ number_format($snapshot->estimated_driver_payouts, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Operating Expenses') }}</strong></td><td>${{ number_format($snapshot->estimated_operating_expenses, 2) }}</td></tr>
                                    <tr><td><strong>{{ translate('Estimated Net Income') }}</strong></td><td><strong>${{ number_format($snapshot->estimated_net_income, 2) }}</strong></td></tr>
                                    <tr><td><strong>{{ translate('Variance from Baseline') }}</strong></td><td>${{ number_format($snapshot->net_income_variance_from_baseline, 2) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Metadata') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tbody>
                                <tr><td><strong>{{ translate('Source Type') }}</strong></td><td>{{ $snapshot->source_type }}</td></tr>
                                <tr><td><strong>{{ translate('Method') }}</strong></td><td>{{ $snapshot->reconstruction_method }}</td></tr>
                                <tr><td><strong>{{ translate('Version') }}</strong></td><td>{{ $snapshot->reconstruction_version }}</td></tr>
                                <tr><td><strong>{{ translate('Confidence') }}</strong></td>
                                    <td>
                                        @if($snapshot->confidence === 'verified')
                                            <span class="badge badge-soft-success">{{ translate('VERIFIED BUSINESS ACTIVITY') }}</span>
                                        @elseif($snapshot->confidence === 'estimated')
                                            <span class="badge badge-soft-secondary">{{ translate('RECONSTRUCTED BUSINESS ACTIVITY') }}</span>
                                        @else
                                            <span class="badge badge-soft-info">{{ strtoupper($snapshot->confidence) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($sources->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Source Records') }}</h5>
                    </div>
                    <div class="card-body">
                        @foreach($sources as $source)
                        <div class="border-bottom pb-2 mb-2">
                            <strong>{{ $source->source_type_label }}</strong><br>
                            <small class="text-muted">{{ $source->source_description ?? $source->notes ?? '-' }}</small><br>
                            <span class="badge badge-soft-{{ $source->confidence_label === 'verified' ? 'success' : 'secondary' }}">{{ strtoupper($source->confidence_label) }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($snapshot->notes)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Notes') }}</h5>
                    </div>
                    <div class="card-body">
                        <p>{{ $snapshot->notes }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if($snapshot->assumptions_used)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Assumptions Used') }}</h5>
            </div>
            <div class="card-body">
                <pre class="mb-0" style="font-size:0.85em">{{ json_encode($snapshot->assumptions_used, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
        @endif

        @if($snapshot->calculation_log)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Calculation Log') }}</h5>
            </div>
            <div class="card-body">
                <pre class="mb-0" style="font-size:0.85em">{{ json_encode($snapshot->calculation_log, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
        @endif
    </div>
@endsection
