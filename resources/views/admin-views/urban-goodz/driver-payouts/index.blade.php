@extends('layouts.admin.app')

@section('title', translate('Driver Payouts'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Driver Payouts') }}</h1>
            <a href="{{ route('admin.urban-goodz.driver-earnings.index') }}" class="btn btn-outline-primary">
                <i class="tio-money"></i> {{ translate('View All Earnings') }}
            </a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card bg-soft-warning">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($stats['total_pending'], 2) }}</h3>
                        <small>{{ translate('Pending Payouts') }} ({{ $stats['pending_count'] }})</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-success">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($stats['total_paid'], 2) }}</h3>
                        <small>{{ translate('Total Paid') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-info">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($stats['total_fees'], 2) }}</h3>
                        <small>{{ translate('Instant Fees Collected') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-nowrap">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('#') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Requested') }}</th>
                                <th>{{ translate('Fee') }}</th>
                                <th>{{ translate('Net') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Requested At') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payouts as $key => $payout)
                                <tr>
                                    <td>{{ $payouts->firstItem() + $key }}</td>
                                    <td>{{ $payout->driver?->f_name . ' ' . $payout->driver?->l_name }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $payout->payout_type === 'instant' ? 'warning' : 'primary' }}">
                                            {{ ucfirst($payout->payout_type) }}
                                        </span>
                                    </td>
                                    <td>${{ number_format($payout->requested_amount, 2) }}</td>
                                    <td>${{ number_format($payout->instant_fee, 2) }}</td>
                                    <td><strong>${{ number_format($payout->net_amount, 2) }}</strong></td>
                                    <td>
                                        @php $sMap = ['pending' => 'warning', 'approved' => 'info', 'processing' => 'secondary', 'paid' => 'success', 'rejected' => 'danger', 'held' => 'dark']; @endphp
                                        <span class="badge badge-soft-{{ $sMap[$payout->status] ?? 'secondary' }}">{{ ucfirst($payout->status) }}</span>
                                    </td>
                                    <td>{{ $payout->created_at->format('M d, Y g:i A') }}</td>
                                    <td>
                                        <a href="{{ route('admin.urban-goodz.driver-payouts.show', $payout->id) }}" class="btn btn-sm btn-outline-info">
                                            <i class="tio-visible"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center py-4">{{ translate('No payout requests yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $payouts->links() }}
            </div>
        </div>
    </div>
@endsection
