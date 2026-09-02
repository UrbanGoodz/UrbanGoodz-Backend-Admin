<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Urban Goodz — Historical Operations Reconstruction</title>
    <style>
        body { font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif; font-size: 10px; color: #222; margin: 20px; }
        h1 { font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        h2 { font-size: 14px; margin-top: 20px; color: #444; }
        h3 { font-size: 12px; margin-top: 14px; color: #555; }
        .disclaimer { background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; font-size: 9px; }
        .owner-info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; margin: 10px 0; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 8.5px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th { background: #f0f0f0; font-weight: bold; }
        td:first-child, th:first-child { text-align: left; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .total-row { background: #e9ecef; font-weight: bold; }
        .match { color: #28a745; font-weight: bold; }
        .close { color: #ffc107; font-weight: bold; }
        .reconcile { color: #dc3545; font-weight: bold; }
        .summary-grid { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
        .summary-box { border: 1px solid #ccc; padding: 8px; flex: 1; min-width: 140px; }
        .summary-box .label { font-size: 8px; color: #666; text-transform: uppercase; }
        .summary-box .value { font-size: 14px; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 8px; color: #888; border-top: 1px solid #ccc; padding-top: 8px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<h1>Urban Goodz — 24-Month Historical Operations Reconstruction</h1>

<div class="disclaimer">
    <strong>IMPORTANT:</strong>
    The original production database was lost during a subsequent application rebuild. This report reconstructs historical business operations using surviving business records and owner-provided historical operating assumptions. Reconstructed values are estimates and are not represented as recovered original database records.
</div>

@if($configuration->owner_name)
<div class="owner-info">
    <strong>Owner/Founder:</strong> {{ $configuration->owner_name }}
    — Also served as active delivery driver. Earnings as a driver are included in the $5,700/month net income baseline.
    @if($configuration->owner_non_delivery_months)
    <br><strong>Non-Delivery Months:</strong>
    {{ collect($configuration->owner_non_delivery_months)->map(fn($m) => \Carbon\Carbon::create()->month($m)->format('F'))->implode(', ') }}
    — Owner did not deliver during these months. Owner deliveries set to 0.
    @endif
</div>
@endif

<h2>Reconstruction Configuration</h2>
<table>
    <tr><td style="width:200px"><strong>Configuration</strong></td><td>{{ $configuration->configuration_name }}</td></tr>
    <tr><td><strong>Date Range</strong></td><td>{{ $configuration->reconstruction_start_date->format('M Y') }} — {{ $configuration->reconstruction_end_date->format('M Y') }}</td></tr>
    <tr><td><strong>Total Months</strong></td><td>{{ $configuration->month_count }}</td></tr>
    <tr><td><strong>Baseline Orders/Month</strong></td><td>~{{ number_format($configuration->baseline_monthly_orders) }}</td></tr>
    <tr><td><strong>Baseline Avg Order Value</strong></td><td>${{ number_format($configuration->baseline_average_order_value, 2) }}</td></tr>
    <tr><td><strong>Order Commission</strong></td><td>{{ $configuration->baseline_order_commission_pct }}%</td></tr>
    <tr><td><strong>Baseline Delivery Fee</strong></td><td>${{ number_format($configuration->baseline_delivery_fee, 2) }}</td></tr>
    <tr><td><strong>Platform Share of Delivery Fee</strong></td><td>{{ $configuration->baseline_platform_delivery_fee_pct }}%</td></tr>
    <tr><td><strong>Baseline Active Drivers</strong></td><td>~{{ $configuration->baseline_active_drivers }}</td></tr>
    <tr><td><strong>Baseline Monthly Net</strong></td><td>~${{ number_format($configuration->baseline_avg_monthly_net, 2) }}</td></tr>
</table>

@if($summary)

<h2>Summary Statistics</h2>
<div class="summary-grid">
    <div class="summary-box"><div class="label">Total Orders</div><div class="value">{{ number_format($summary['total_orders']) }}</div></div>
    <div class="summary-box"><div class="label">Avg Monthly Orders</div><div class="value">{{ number_format($summary['average_monthly_orders']) }}</div></div>
    <div class="summary-box"><div class="label">Total Platform Revenue</div><div class="value">${{ number_format($summary['total_platform_revenue'], 0) }}</div></div>
    <div class="summary-box"><div class="label">Total Est. Net Income</div><div class="value">${{ number_format($summary['total_estimated_net'], 0) }}</div></div>
    <div class="summary-box"><div class="label">Avg Monthly Net</div><div class="value">${{ number_format($summary['average_monthly_net'], 0) }}</div></div>
    <div class="summary-box"><div class="label">Total Owner Deliveries</div><div class="value">{{ number_format($summary['total_owner_deliveries']) }}</div></div>
</div>

<h2>24-Month Validation Against Owner Baseline</h2>
<table>
    <thead>
        <tr>
            <th class="text-left">Metric</th>
            <th>Reconstructed</th>
            <th>Baseline</th>
            <th>Variance %</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($summary['reconciliation'] as $key => $check)
            @if($key !== 'overall')
            <tr>
                <td class="text-left"><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}</strong></td>
                <td>{{ is_numeric($check['reconstructed']) ? number_format($check['reconstructed'], 2) : $check['reconstructed'] }}</td>
                <td>{{ is_numeric($check['baseline']) ? number_format($check['baseline'], 2) : $check['baseline'] }}</td>
                <td>{{ $check['variance_pct'] }}%</td>
                <td class="text-center">
                    @if($check['status'] === 'MATCH')
                        <span class="match">MATCH</span>
                    @elseif($check['status'] === 'CLOSE')
                        <span class="close">CLOSE</span>
                    @else
                        <span class="reconcile">DOES NOT RECONCILE</span>
                    @endif
                </td>
            </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td class="text-left"><strong>Overall</strong></td>
            <td colspan="3"></td>
            <td class="text-center">
                @if($summary['reconciliation']['overall'] === 'MATCH')
                    <span class="match">MATCH</span>
                @elseif($summary['reconciliation']['overall'] === 'CLOSE')
                    <span class="close">CLOSE</span>
                @else
                    <span class="reconcile">DOES NOT RECONCILE</span>
                @endif
            </td>
        </tr>
    </tfoot>
</table>

<div class="page-break"></div>

<h2>Monthly Reconstruction Data</h2>
<table>
    <thead>
        <tr>
            <th class="text-left">Month</th>
            <th>Orders</th>
            <th>Avg Order</th>
            <th>Gross Value</th>
            <th>23% Revenue</th>
            <th>Del. Fees</th>
            <th>3% Del. Rev</th>
            <th>Total Revenue</th>
            <th>Drivers</th>
            <th>Owner Del.</th>
            <th>Driver Payouts</th>
            <th>Op. Expenses</th>
            <th>Est. Net</th>
            <th>Confidence</th>
        </tr>
    </thead>
    <tbody>
        @foreach($snapshots as $s)
        <tr>
            <td class="text-left">{{ $s->snapshot_month->format('M Y') }}</td>
            <td>{{ number_format($s->estimated_orders) }}</td>
            <td>${{ number_format($s->estimated_average_order_value, 2) }}</td>
            <td>${{ number_format($s->estimated_total_order_value, 2) }}</td>
            <td>${{ number_format($s->estimated_order_commission_revenue, 2) }}</td>
            <td>${{ number_format($s->estimated_delivery_fee_revenue, 2) }}</td>
            <td>${{ number_format($s->estimated_platform_delivery_fee_revenue, 2) }}</td>
            <td><strong>${{ number_format($s->estimated_total_platform_revenue, 2) }}</strong></td>
            <td>{{ $s->estimated_active_driver_count }}</td>
            <td>{{ $s->estimated_owner_deliveries }}</td>
            <td>${{ number_format($s->estimated_driver_payouts, 2) }}</td>
            <td>${{ number_format($s->estimated_operating_expenses, 2) }}</td>
            <td><strong>${{ number_format($s->estimated_net_income, 2) }}</strong></td>
            <td class="text-center">
                @if($s->confidence === 'verified')
                    <span class="match">VERIFIED</span>
                @elseif($s->confidence === 'estimated')
                    ESTIMATED
                @else
                    {{ strtoupper($s->confidence) }}
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td class="text-left"><strong>TOTALS</strong></td>
            <td><strong>{{ number_format($summary['total_orders']) }}</strong></td>
            <td><strong>${{ number_format($summary['average_order_value'], 2) }}</strong></td>
            <td colspan="3"></td>
            <td colspan="2"><strong>${{ number_format($summary['total_platform_revenue'], 2) }}</strong></td>
            <td><strong>{{ number_format($summary['average_active_drivers']) }}</strong></td>
            <td><strong>{{ number_format($summary['total_owner_deliveries']) }}</strong></td>
            <td colspan="2"></td>
            <td><strong>${{ number_format($summary['total_estimated_net'], 2) }}</strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>

@endif

<div class="footer">
    <strong>Urban Goodz Historical Reconstruction Report</strong> — Generated {{ now()->format('M d, Y h:i A') }}<br>
    Reconstruction Version: {{ $configuration->snapshots->first()->reconstruction_version ?? 'N/A' }} |
    Source: historical_reconstruction |
    Confidence: estimated<br>
    {{ $configuration->evidentiary_disclaimer }}
</div>

</body>
</html>
