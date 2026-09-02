@extends('layouts.admin.app')

@section('title', translate('Reconstruction Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ $configuration->configuration_name }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.export.csv', $configuration->id) }}" class="btn btn-sm btn-outline--primary">
                    <i class="tio-download"></i> {{ translate('Export CSV') }}
                </a>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.export.json', $configuration->id) }}" class="btn btn-sm btn-outline--primary">
                    <i class="tio-download"></i> {{ translate('Export JSON') }}
                </a>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.export.pdf', $configuration->id) }}" class="btn btn-sm btn-outline--primary">
                    <i class="tio-download"></i> {{ translate('Export PDF') }}
                </a>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.truck-timeline', $configuration->id) }}" class="btn btn-sm btn-outline--info">
                    <i class="tio-car"></i> {{ translate('Truck Purchase Timeline') }}
                </a>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.audit-trail', $configuration->id) }}" class="btn btn-sm btn-outline--secondary">
                    <i class="tio-history"></i> {{ translate('Audit Trail') }}
                </a>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.source-records', $configuration->id) }}" class="btn btn-sm btn-outline--secondary">
                    <i class="tio-folder-open"></i> {{ translate('Source Records') }}
                </a>
                <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.run', $configuration->id) }}" class="d-inline" onsubmit="return confirm('{{ translate('Run reconstruction? This will regenerate all monthly snapshots.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn--warning"><i class="tio-play"></i> {{ translate('Run Reconstruction') }}</button>
                </form>
            </div>
        </div>

        <div class="alert alert-warning mb-3">
            <strong>{{ translate('Evidentiary Disclosure') }}:</strong>
            {{ $configuration->evidentiary_disclaimer }}
        </div>

        @if($configuration->owner_name)
        <div class="alert alert-info mb-3">
            <strong>{{ translate('Owner/Founder') }}:</strong> {{ $configuration->owner_name }}
            — {{ translate('Also served as active delivery driver. Earnings as a driver are included in the $5,700/month net income baseline.') }}
            @if($configuration->owner_non_delivery_months)
            <br><strong>{{ translate('Non-Delivery Months') }}:</strong>
            {{ collect($configuration->owner_non_delivery_months)->map(fn($m) => \Carbon\Carbon::create()->month($m)->format('F'))->implode(', ') }}
            — {{ translate('Owner did not deliver during these months. Owner deliveries set to 0.') }}
            @endif
        </div>
        @endif

        @if($summary)
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Total Orders') }}</div>
                    <div class="h4">{{ number_format($summary['total_orders']) }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Avg Monthly Orders') }}</div>
                    <div class="h4">{{ number_format($summary['average_monthly_orders']) }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Total Platform Revenue') }}</div>
                    <div class="h4">${{ number_format($summary['total_platform_revenue'], 0) }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Total Est. Net Income') }}</div>
                    <div class="h4">${{ number_format($summary['total_estimated_net'], 0) }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Avg Monthly Net') }}</div>
                    <div class="h4">${{ number_format($summary['average_monthly_net'], 0) }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Total Owner Deliveries') }}</div>
                    <div class="h4">{{ number_format($summary['total_owner_deliveries']) }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('24-Month Validation Against Owner Baseline') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ translate('Metric') }}</th>
                                <th>{{ translate('Reconstructed') }}</th>
                                <th>{{ translate('Baseline') }}</th>
                                <th>{{ translate('Variance %') }}</th>
                                <th>{{ translate('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summary['reconciliation'] as $key => $check)
                                @if($key !== 'overall')
                                <tr>
                                    <td><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}</strong></td>
                                    <td>{{ is_numeric($check['reconstructed']) ? number_format($check['reconstructed'], 2) : $check['reconstructed'] }}</td>
                                    <td>{{ is_numeric($check['baseline']) ? number_format($check['baseline'], 2) : $check['baseline'] }}</td>
                                    <td>{{ $check['variance_pct'] }}%</td>
                                    <td>
                                        @if($check['status'] === 'MATCH')
                                            <span class="badge badge-soft-success">{{ translate('MATCH') }}</span>
                                        @elseif($check['status'] === 'CLOSE')
                                            <span class="badge badge-soft-warning">{{ translate('CLOSE') }}</span>
                                        @else
                                            <span class="badge badge-soft-danger">{{ translate('DOES NOT RECONCILE') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td><strong>{{ translate('Overall') }}</strong></td>
                                <td colspan="3"></td>
                                <td>
                                    @if($summary['reconciliation']['overall'] === 'MATCH')
                                        <span class="badge badge-soft-success">{{ translate('MATCH') }}</span>
                                    @elseif($summary['reconciliation']['overall'] === 'CLOSE')
                                        <span class="badge badge-soft-warning">{{ translate('CLOSE') }}</span>
                                    @else
                                        <span class="badge badge-soft-danger">{{ translate('DOES NOT RECONCILE') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Monthly Reconstruction Data') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Month') }}</th>
                                <th class="text-end">{{ translate('Orders') }}</th>
                                <th class="text-end">{{ translate('Avg Order') }}</th>
                                <th class="text-end">{{ translate('Gross Value') }}</th>
                                <th class="text-end">{{ translate('23% Revenue') }}</th>
                                <th class="text-end">{{ translate('Delivery Fees') }}</th>
                                <th class="text-end">{{ translate('3% Del. Revenue') }}</th>
                                <th class="text-end">{{ translate('Total Revenue') }}</th>
                                <th class="text-end">{{ translate('Drivers') }}</th>
                                <th class="text-end">{{ translate('Owner Del.') }}</th>
                                <th class="text-end">{{ translate('Driver Payouts') }}</th>
                                <th class="text-end">{{ translate('Op. Expenses') }}</th>
                                <th class="text-end">{{ translate('Est. Net') }}</th>
                                <th>{{ translate('Confidence') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($snapshots as $s)
                            <tr>
                                <td><a href="{{ route('admin.urban-goodz.historical-reconstruction.snapshot', [$configuration->id, $s->id]) }}">{{ $s->snapshot_month->format('M Y') }}</a></td>
                                <td class="text-end">{{ number_format($s->estimated_orders) }}</td>
                                <td class="text-end">${{ number_format($s->estimated_average_order_value, 2) }}</td>
                                <td class="text-end">${{ number_format($s->estimated_total_order_value, 2) }}</td>
                                <td class="text-end">${{ number_format($s->estimated_order_commission_revenue, 2) }}</td>
                                <td class="text-end">${{ number_format($s->estimated_delivery_fee_revenue, 2) }}</td>
                                <td class="text-end">${{ number_format($s->estimated_platform_delivery_fee_revenue, 2) }}</td>
                                <td class="text-end"><strong>${{ number_format($s->estimated_total_platform_revenue, 2) }}</strong></td>
                                <td class="text-end">{{ $s->estimated_active_driver_count }}</td>
                                <td class="text-end">{{ $s->estimated_owner_deliveries }}</td>
                                <td class="text-end">${{ number_format($s->estimated_driver_payouts, 2) }}</td>
                                <td class="text-end">${{ number_format($s->estimated_operating_expenses, 2) }}</td>
                                <td class="text-end"><strong>${{ number_format($s->estimated_net_income, 2) }}</strong></td>
                                <td>
                                    @if($s->confidence === 'verified')
                                        <span class="badge badge-soft-success">{{ translate('VERIFIED') }}</span>
                                    @elseif($s->confidence === 'high')
                                        <span class="badge badge-soft-info">{{ translate('HIGH') }}</span>
                                    @elseif($s->confidence === 'medium')
                                        <span class="badge badge-soft-warning">{{ translate('MEDIUM') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('ESTIMATED') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td><strong>{{ translate('TOTALS') }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($summary['total_orders']) }}</strong></td>
                                <td class="text-end"><strong>${{ number_format($summary['average_order_value'], 2) }}</strong></td>
                                <td class="text-end" colspan="3"></td>
                                <td class="text-end" colspan="2"><strong>${{ number_format($summary['total_platform_revenue'], 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($summary['average_active_drivers']) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($summary['total_owner_deliveries']) }}</strong></td>
                                <td class="text-end" colspan="2"></td>
                                <td class="text-end"><strong>${{ number_format($summary['total_estimated_net'], 2) }}</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body text-center py-5">
                <h4>{{ translate('No reconstruction data yet') }}</h4>
                <p class="text-muted">{{ translate('Click "Run Reconstruction" to generate monthly snapshots based on the configured assumptions.') }}</p>
                <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.run', $configuration->id) }}">
                    @csrf
                    <button type="submit" class="btn btn--primary"><i class="tio-play"></i> {{ translate('Run Reconstruction') }}</button>
                </form>
            </div>
        </div>
        @endif
    </div>
@endsection
