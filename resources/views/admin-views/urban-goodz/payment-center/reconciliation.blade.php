@extends('layouts.admin.app')

@section('title', translate('Reconciliation Report'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span style="color:#ED9914;">{{ translate('Reconciliation Report') }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.payment-center.index') }}" class="btn btn--secondary">{{ translate('Back to Payment Center') }}</a>
                </div>
            </div>
        </div>

        {{-- Ledger Summary --}}
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Captured') }}</small>
                        <div class="font-weight-bold h4 mb-0 text-success">${{ number_format($ledgerSummary['captured'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Pending') }}</small>
                        <div class="font-weight-bold h4 mb-0 text-warning">${{ number_format($ledgerSummary['pending'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Failed') }}</small>
                        <div class="font-weight-bold h4 mb-0 text-danger">${{ number_format($ledgerSummary['failed'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Refunded') }}</small>
                        <div class="font-weight-bold h4 mb-0">${{ number_format($ledgerSummary['refunded'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Disputed') }}</small>
                        <div class="font-weight-bold h4 mb-0">${{ number_format($ledgerSummary['disputed'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Unreconciled') }}</small>
                        <div class="font-weight-bold h4 mb-0 {{ $ledgerSummary['unreconciled'] > 0 ? 'text-danger' : '' }}">
                            ${{ number_format($ledgerSummary['unreconciled'], 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reconciliation Details --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">{{ translate('Duplicate Event Count') }}</h6>
                        <div class="font-weight-bold {{ $summary['duplicate_event_count'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $summary['duplicate_event_count'] }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">{{ translate('Ledger Imbalance') }}</h6>
                        <div class="font-weight-bold {{ $summary['ledger_imbalance'] != 0 ? 'text-danger' : 'text-success' }}">
                            ${{ number_format($summary['ledger_imbalance'], 2) }}
                        </div>
                        <small class="text-muted">{{ translate('Total ledger debits minus credits') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">{{ translate('Audit Warnings') }}</h6>
                        @if(count($summary['audit_warnings']) > 0)
                            @foreach($summary['audit_warnings'] as $warning)
                                <div class="text-danger small">
                                    <i class="tio-warning mr-1"></i> {{ $warning }}
                                </div>
                            @endforeach
                        @else
                            <div class="text-success small">
                                <i class="tio-check-circle mr-1"></i> {{ translate('No warnings') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Deficits --}}
        @if(count($summary['deficits']) > 0)
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">{{ translate('Deficits Found') }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('Feature') }}</th>
                                <th>{{ translate('Captured') }}</th>
                                <th>{{ translate('Split Total') }}</th>
                                <th>{{ translate('Deficit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summary['deficits'] as $deficit)
                                <tr>
                                    <td>{{ $deficit['feature'] }}</td>
                                    <td>${{ number_format($deficit['captured'], 2) }}</td>
                                    <td>${{ number_format($deficit['split_total'], 2) }}</td>
                                    <td class="font-weight-bold text-danger">${{ number_format($deficit['deficit'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
