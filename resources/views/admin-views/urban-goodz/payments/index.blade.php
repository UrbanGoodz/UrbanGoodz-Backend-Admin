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

        <div class="card mb-3" id="platform-economics">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ translate('Platform Economics') }}</h4>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <small class="text-muted d-block">{{ translate('Effective Urban Goodz platform fee') }}</small>
                        <strong class="h3 mb-0" data-platform-fee-percent>
                            {{ rtrim(rtrim(number_format($platformFee['effective_percent'], 4, '.', ''), '0'), '.') }}%
                        </strong>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <small class="text-muted d-block">{{ translate('Source') }}</small>
                        <span class="badge {{ $platformFee['owner_configured'] ? 'badge-soft-success' : 'badge-soft-warning' }}"
                              data-platform-fee-source>
                            {{ $platformFee['source_label'] }}
                        </span>
                        <div class="small text-muted mt-1">
                            {{ translate('Configured') }}: {{ $platformFee['configured'] ? translate('Yes') : translate('No') }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        @if(auth('admin')->check() && (int) auth('admin')->user()->role_id === 1)
                            <form method="POST" action="{{ route('admin.urban-goodz.payments.platform-fee.update') }}">
                                @csrf
                                @method('PATCH')
                                <label for="platform_fee_percent">{{ translate('Owner-approved percentage') }}</label>
                                <div class="input-group">
                                    <input
                                        id="platform_fee_percent"
                                        name="platform_fee_percent"
                                        class="form-control"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        required
                                        value="{{ old('platform_fee_percent', $platformFee['effective_percent']) }}"
                                    >
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn--primary btn-sm mt-2">
                                    {{ translate('Save platform fee') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                @if($platformFee['owner_configured'])
                    <div class="small text-muted mt-3">
                        {{ translate('Changed by') }}: {{ $platformFee['changed_by'] ?? translate('Owner') }}
                        ·
                        {{ translate('Changed at') }}: {{ optional($platformFee['changed_at'])->format('M d, Y H:i:s T') }}
                    </div>
                @else
                    <div class="alert alert-warning mt-3 mb-0">
                        {{ translate('The current value is not owner-configured. Live-controlled payments cannot use this fallback.') }}
                    </div>
                @endif
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
