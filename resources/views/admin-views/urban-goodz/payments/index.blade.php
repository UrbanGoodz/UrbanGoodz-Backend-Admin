@extends('layouts.admin.app')

@section('title', translate('Payment Center'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Payment Center') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $ledgers->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        @php
            $routeMap = [
                'order_anywhere' => 'admin.urban-goodz.payments.order-anywhere',
                'fashion_fit' => 'admin.urban-goodz.payments.fashion-fit',
                'earn_money' => 'admin.urban-goodz.payments.earn-money',
                'logistics' => 'admin.urban-goodz.payments.logistics',
                'load_board' => 'admin.urban-goodz.payments.load-board',
                'medical_courier' => 'admin.urban-goodz.payments.medical-courier',
                'book_anything' => 'admin.urban-goodz.payments.book-anything',
                'rentals' => 'admin.urban-goodz.payments.rentals',
                'events' => 'admin.urban-goodz.payments.events',
                'creator_commerce' => 'admin.urban-goodz.payments.creator-commerce',
                'community_marketplace' => null,
                'discovery' => null,
                'ask_urban_goodz' => null,
                'urban_goodz_plus' => null,
                'spotlight' => null,
            ];

            $badgeMap = [
                'payment_ready' => ['badge-soft-success', 'Payment Ready'],
                'payment_partial' => ['badge-soft-warning', 'Partial'],
                'payment_pending' => ['badge-soft-secondary', 'Payment Pending'],
                'no_payment_needed' => ['badge-soft-info', 'No Payment Needed'],
            ];
        @endphp

        @if(isset($readiness))
            <div class="row g-2 mb-3">
                @foreach($readiness as $key => $value)
                    @php
                        $routeName = $routeMap[$key] ?? null;
                        $badge = $badgeMap[$value] ?? ['badge-soft-dark', $value];
                        $label = ucwords(str_replace('_', ' ', $key));
                    @endphp
                    <div class="col-md-3 col-6">
                        @if($routeName)
                        <a href="{{ route($routeName) }}" class="text-decoration-none">
                        @endif
                            <div class="card h-100">
                                <div class="card-body text-center py-2">
                                    <small class="text-muted">{{ translate($label) }}</small>
                                    <div>
                                        <span class="badge {{ $badge[0] }}">{{ translate($badge[1]) }}</span>
                                    </div>
                                </div>
                            </div>
                        @if($routeName)
                        </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Ledger #') }}</th>
                            <th>{{ translate('Feature') }}</th>
                            <th>{{ translate('Event') }}</th>
                            <th>{{ translate('Direction') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Reference') }}</th>
                            <th>{{ translate('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ledgers as $key => $l)
                            <tr>
                                <td>{{ $ledgers->firstItem() + $key }}</td>
                                <td class="font-weight-bold">{{ $l->ledger_number }}</td>
                                <td>{{ $l->feature }}</td>
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
                        @endforeach
                        @if(count($ledgers) === 0)
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No ledgers found') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $ledgers->links() }}
            </div>
        </div>
    </div>
@endsection