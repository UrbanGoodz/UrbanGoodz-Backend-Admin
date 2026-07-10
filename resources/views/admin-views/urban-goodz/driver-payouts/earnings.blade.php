@extends('layouts.admin.app')

@section('title', translate('Driver Earnings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Driver Earnings') }}</h1>
            <a href="{{ route('admin.urban-goodz.driver-payouts.index') }}" class="btn btn-outline-primary">
                <i class="tio-money"></i> {{ translate('View Payout Requests') }}
            </a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card bg-soft-warning">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($totals['pending'], 2) }}</h3>
                        <small>{{ translate('Pending') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-info">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($totals['approved'], 2) }}</h3>
                        <small>{{ translate('Approved') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-success">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($totals['paid'], 2) }}</h3>
                        <small>{{ translate('Paid') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-primary">
                    <div class="card-body text-center">
                        <h3 class="mb-0">${{ number_format($totals['total'], 2) }}</h3>
                        <small>{{ translate('Total All Time') }}</small>
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
                                <th>{{ translate('Amount') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Package') }}</th>
                                <th>{{ translate('Route') }}</th>
                                <th>{{ translate('Description') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($earnings as $key => $earning)
                                <tr>
                                    <td>{{ $earnings->firstItem() + $key }}</td>
                                    <td>{{ $earning->driver?->f_name . ' ' . $earning->driver?->l_name }}</td>
                                    <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $earning->earning_type)) }}</span></td>
                                    <td><strong>${{ number_format($earning->amount, 2) }}</strong></td>
                                    <td>
                                        @php $sMap = ['pending' => 'warning', 'approved' => 'info', 'paid' => 'success', 'held' => 'dark', 'disputed' => 'danger']; @endphp
                                        <span class="badge badge-soft-{{ $sMap[$earning->status] ?? 'secondary' }}">{{ ucfirst($earning->status) }}</span>
                                    </td>
                                    <td>{{ $earning->package?->tracking_id ?? '—' }}</td>
                                    <td>{{ $earning->route?->route_name ?? '—' }}</td>
                                    <td>{{ Str::limit($earning->description, 40) ?? '—' }}</td>
                                    <td>{{ $earning->created_at->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center py-4">{{ translate('No earnings recorded') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $earnings->links() }}
            </div>
        </div>
    </div>
@endsection
