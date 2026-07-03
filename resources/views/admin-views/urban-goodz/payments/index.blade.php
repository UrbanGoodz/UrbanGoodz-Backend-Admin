@extends('layouts.admin.app')

@section('title', translate('Urban Goodz Payment Center'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Urban Goodz Payment Center') }}</h1>
            <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Urban Goodz') }}</a>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0">{{ translate('Payment readiness') }}</h5></div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($readiness as $module => $status)
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between border rounded p-2">
                                <span>{{ str($module)->replace('_', ' ')->title() }}</span>
                                <span class="badge badge-soft-info">{{ $status }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Ledger #') }}</th>
                        <th>{{ translate('Feature') }}</th>
                        <th>{{ translate('Event') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Reference') }}</th>
                        <th>{{ translate('Splits') }}</th>
                        <th>{{ translate('Created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($ledgers as $ledger)
                        <tr>
                            <td>{{ $ledger->ledger_number }}</td>
                            <td>{{ $ledger->feature }}</td>
                            <td>{{ $ledger->event_type }} / {{ $ledger->direction }}</td>
                            <td>{{ $ledger->currency }} {{ $ledger->amount }}</td>
                            <td><span class="badge badge-soft-info">{{ $ledger->payment_status }}</span></td>
                            <td>{{ $ledger->reference ?? '-' }}</td>
                            <td>{{ $ledger->splits->count() }}</td>
                            <td>{{ optional($ledger->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">{{ translate('No payment ledger records found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $ledgers->links() }}</div>
        </div>
    </div>
@endsection
