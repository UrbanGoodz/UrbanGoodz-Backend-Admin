@extends('layouts.vendor.app')

@section('title', translate('Urban Goodz'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Urban Goodz Vendor Panel') }}</h1>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5>{{ translate('Order Anywhere') }}</h5>
                        <h3>{{ $orderAnywhereCount }}</h3>
                        <p class="text-muted">{{ translate('Assigned requests only') }}</p>
                        <a href="{{ route('vendor.urban-goodz.order-anywhere.index') }}" class="btn btn--primary">{{ translate('Open') }}</a>
                    </div>
                </div>
            </div>
            @foreach(['rentals','book-anything','events','creators','community','spotlight','logistics','load-board'] as $section)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5>{{ str($section)->replace('-', ' ')->title() }}</h5>
                            <p class="text-muted">{{ translate('Vendor scoped route added. Real database workflow still needs feature tables.') }}</p>
                            <a href="{{ route('vendor.urban-goodz.section', $section) }}" class="btn btn--secondary">{{ translate('View status') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
