@extends('layouts.admin.app')

@section('title', translate('Order Report'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-report"></i>
                </span>
                <span>{{ translate('Order Report') }}</span>
            </h1>
        </div>

        {{-- order_index() seeds from_date/to_date into the session and renders this
             page without passing any data of its own. The reports linked below are
             the ones that read those session dates, so this is the place the range
             gets chosen before opening any of them. --}}
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">{{ translate('Reporting period') }}</h5>
                <form action="{{ route('admin.transactions.report.order') }}" method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label class="form-label" for="from_date">{{ translate('From') }}</label>
                        <input type="date" id="from_date" name="from_date" class="form-control"
                               value="{{ session('from_date') }}">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" for="to_date">{{ translate('To') }}</label>
                        <input type="date" id="to_date" name="to_date" class="form-control"
                               value="{{ session('to_date') }}">
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn--primary">{{ translate('Apply') }}</button>
                    </div>
                </form>
                <p class="mb-0 mt-3 text-muted fs-12">
                    {{ translate('Currently showing') }}:
                    <strong>{{ session('from_date') ?: translate('not set') }}</strong>
                    &ndash;
                    <strong>{{ session('to_date') ?: translate('not set') }}</strong>
                </p>
            </div>
        </div>

        <div class="row g-3">
            @php($reports = [
                ['route' => 'admin.transactions.report.order-report',       'icon' => 'tio-shopping-cart',  'title' => translate('Order Report'),            'desc' => translate('Orders in the selected period, by status.')],
                ['route' => 'admin.transactions.report.order-transaction',  'icon' => 'tio-money',          'title' => translate('Order Transactions'),      'desc' => translate('Transaction lines behind those orders.')],
                ['route' => 'admin.transactions.report.store-order-report', 'icon' => 'tio-shop',           'title' => translate('Store Order Report'),      'desc' => translate('The same orders grouped by store.')],
            ])

            @foreach ($reports as $report)
                {{-- Guarded: a report route that is not registered should not take the
                     whole page down with a RouteNotFoundException. --}}
                @continue(!Route::has($report['route']))
                <div class="col-md-4">
                    <a href="{{ route($report['route']) }}" class="text-body">
                        <div class="card h-100 card-hover-shadow">
                            <div class="card-body">
                                <h5 class="mb-1">
                                    <i class="{{ $report['icon'] }} me-1"></i>
                                    {{ $report['title'] }}
                                </h5>
                                <p class="mb-0 text-muted fs-12">{{ $report['desc'] }}</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection
