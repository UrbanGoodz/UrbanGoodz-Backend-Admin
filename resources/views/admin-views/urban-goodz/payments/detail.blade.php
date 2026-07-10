@extends('layouts.admin.app')

@section('title', $info['label'] . ' ' . translate('Payments'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ $info['label'] }} {{ translate('Payments') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $ledgerCount }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.payments.index') }}" class="btn btn--secondary">{{ translate('Back to Payment Center') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Status') }}</small>
                        @php
                            $badgeMap = [
                                'payment_ready' => 'badge-soft-success',
                                'payment_partial' => 'badge-soft-warning',
                                'payment_pending' => 'badge-soft-secondary',
                                'no_payment_needed' => 'badge-soft-info',
                            ];
                        @endphp
                        <div><span class="badge {{ $badgeMap[$moduleReadiness] ?? 'badge-soft-dark' }}">{{ translate(ucwords(str_replace('_', ' ', $moduleReadiness))) }}</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Total Revenue') }}</small>
                        <div class="font-weight-bold h4 mb-0">${{ number_format($totalRevenue, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Total Refunds') }}</small>
                        <div class="font-weight-bold h4 mb-0">${{ number_format($totalRefunds, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Pending Payouts') }}</small>
                        <div class="font-weight-bold h4 mb-0">{{ $pendingPayouts }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ translate('Recent Ledgers') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Ledger #') }}</th>
                            <th>{{ translate('Event') }}</th>
                            <th>{{ translate('Direction') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Reference') }}</th>
                            <th>{{ translate('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLedgers as $idx => $l)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td class="font-weight-bold">{{ $l->ledger_number }}</td>
                                <td>{{ $l->event_type }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $l->direction === 'inflow' ? 'success' : 'danger' }}">
                                        {{ $l->direction }}
                                    </span>
                                </td>
                                <td>${{ number_format($l->amount, 2) }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $l->payment_status === 'completed' ? 'success' : 'warning' }}">
                                        {{ $l->payment_status }}
                                    </span>
                                </td>
                                <td>{{ $l->reference ?? translate('N/A') }}</td>
                                <td>{{ $l->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                            @if($l->splits->count() > 0)
                                @foreach($l->splits as $split)
                                <tr class="table-active">
                                    <td></td>
                                    <td colspan="7">
                                        <small class="text-muted">
                                            {{ translate('Split') }}: {{ $split->recipient_type }} —
                                            ${{ number_format($split->amount, 2) }}
                                            ({{ $split->split_type }})
                                            <span class="badge badge-soft-{{ $split->status === 'completed' ? 'success' : 'warning' }}">{{ $split->status }}</span>
                                        </small>
                                    </td>
                                </tr>
                                @endforeach
                            @endif
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">{{ translate('No ledgers found for this module') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ $adminPageUrl }}" class="btn btn--primary">{{ translate('View') }} {{ $info['label'] }} {{ translate('Admin Page') }}</a>
        </div>
    </div>
@endsection