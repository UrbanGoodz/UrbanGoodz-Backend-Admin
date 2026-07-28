@extends('layouts.admin.app')

@section('title', translate('Transaction Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span style="color:#ED9914;">{{ translate('Transaction Detail') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $ledger->ledger_number }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.payment-center.index') }}" class="btn btn--secondary">{{ translate('Back to Payment Center') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Ledger Entry') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width:40%;">{{ translate('Ledger Number') }}</td>
                                <td class="font-weight-bold">{{ $ledger->ledger_number }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Feature') }}</td>
                                <td>{{ $ledger->feature }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Event Type') }}</td>
                                <td>
                                    <span class="badge badge-soft-info">{{ $ledger->event_type }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Direction') }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $ledger->direction === 'credit' ? 'success' : ($ledger->direction === 'debit' ? 'danger' : 'secondary') }}">
                                        {{ $ledger->direction }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Amount') }}</td>
                                <td class="font-weight-bold h5">${{ number_format($ledger->amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Currency') }}</td>
                                <td>{{ $ledger->currency }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Payment Status') }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $ledger->payment_status === 'captured' ? 'success' : ($ledger->payment_status === 'pending' ? 'warning' : 'danger') }}">
                                        {{ $ledger->payment_status }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Reference') }}</td>
                                <td>{{ $ledger->reference ?? translate('N/A') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Payment Method') }}</td>
                                <td>{{ $ledger->payment_method ?? translate('N/A') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ translate('Created') }}</td>
                                <td>{{ $ledger->created_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Splits') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($ledger->splits->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ translate('Recipient') }}</th>
                                            <th>{{ translate('Type') }}</th>
                                            <th>{{ translate('Amount') }}</th>
                                            <th>{{ translate('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($ledger->splits as $split)
                                            <tr>
                                                <td>{{ $split->recipient_type }} #{{ $split->recipient_id ?? 'N/A' }}</td>
                                                <td><span class="badge badge-soft-dark">{{ $split->split_type }}</span></td>
                                                <td class="font-weight-bold">${{ number_format($split->amount, 2) }}</td>
                                                <td>
                                                    <span class="badge badge-soft-{{ $split->status === 'released' ? 'success' : 'warning' }}">
                                                        {{ $split->status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted text-center mb-0">{{ translate('No splits recorded for this ledger entry.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            {{ translate('Provider credentials, raw webhook bodies, and confidential metadata are never displayed.') }}
        </div>
    </div>
@endsection
