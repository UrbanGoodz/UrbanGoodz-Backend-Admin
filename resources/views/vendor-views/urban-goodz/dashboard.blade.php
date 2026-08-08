@extends('layouts.vendor.app')

@section('title', translate('Urban Goodz AI & Operations'))

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title d-flex align-items-center gap-2">
                        <img src="{{asset('public/assets/landing/img/logo.svg')}}" style="height:36px;width:auto;" alt="Urban Goodz">
                        <span>{{ translate('Urban Goodz AI Operating System') }}</span>
                    </h1>
                    <p class="text-muted mb-0">{{ translate('AI-driven insights, live store optimization, and dedicated Urban Goodz vendor tools.') }}</p>
                </div>
            </div>
        </div>

        <!-- AI Assistant Banner Card -->
        <div class="ug-ai-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="ug-ai-card-title mb-2">
                        <i class="tio-bot fz-24"></i>
                        <span>{{ translate('Urban Goodz AI Vendor Copilot') }}</span>
                        <span class="ug-ai-chip">{{ translate('Active AI Assistant') }}</span>
                    </div>
                    <p class="mb-0 text-white-70 max-w-700">
                        {{ translate('Your automated intelligence engine analyzing daily order throughput, inventory health, dynamic pricing, and promotional opportunities.') }}
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-ug-primary" onclick="loadAIDailyBrief()">
                        <i class="tio-flash mr-1"></i> {{ translate('Generate Daily Brief') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- AI Insights Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="ug-vendor-card h-100 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-soft-warning text-warning"><i class="tio-trending-up fz-20"></i></div>
                        <h5 class="ug-vendor-card-title fz-15">{{ translate('Sales Recommendations') }}</h5>
                    </div>
                    <p class="text-muted fz-13 mb-3">{{ translate('Optimize top-selling items and peak hour meal combos.') }}</p>
                    <a href="{{ route('vendor.dashboard') }}" class="text-primary font-weight-bold fz-13 d-inline-flex align-items-center gap-1">
                        {{ translate('View Recommendations') }} <i class="tio-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="ug-vendor-card h-100 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-soft-info text-info"><i class="tio-box fz-20"></i></div>
                        <h5 class="ug-vendor-card-title fz-15">{{ translate('Inventory Suggestions') }}</h5>
                    </div>
                    <p class="text-muted fz-13 mb-3">{{ translate('Automated low-stock alerts and predictive prep planning.') }}</p>
                    <a href="{{ route('vendor.item.stock-limit-list') }}" class="text-primary font-weight-bold fz-13 d-inline-flex align-items-center gap-1">
                        {{ translate('Check Stock Limits') }} <i class="tio-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="ug-vendor-card h-100 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-soft-success text-success"><i class="tio-gift fz-20"></i></div>
                        <h5 class="ug-vendor-card-title fz-15">{{ translate('Promotion Ideas') }}</h5>
                    </div>
                    <p class="text-muted fz-13 mb-3">{{ translate('Targeted campaigns and high-converting discount codes.') }}</p>
                    <a href="{{ route('vendor.coupon.add-new') }}" class="text-primary font-weight-bold fz-13 d-inline-flex align-items-center gap-1">
                        {{ translate('Create Promotion') }} <i class="tio-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="ug-vendor-card h-100 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-soft-danger text-danger"><i class="tio-notifications-alert fz-20"></i></div>
                        <h5 class="ug-vendor-card-title fz-15">{{ translate('Operational Alerts') }}</h5>
                    </div>
                    <p class="text-muted fz-13 mb-3">{{ translate('Real-time order delays, courier pickup flags, and status notifications.') }}</p>
                    <a href="{{ route('vendor.order.list', ['all']) }}" class="text-primary font-weight-bold fz-13 d-inline-flex align-items-center gap-1">
                        {{ translate('Inspect Orders') }} <i class="tio-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Operational Modules -->
        <h4 class="ug-heading mb-3">{{ translate('Urban Goodz Store Operations') }}</h4>
        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <div class="ug-vendor-card h-100">
                    <div class="ug-vendor-card-header">
                        <h5 class="ug-vendor-card-title"><i class="tio-shopping-cart-outlined mr-2 text-warning"></i>{{ translate('Order Anywhere') }}</h5>
                        <span class="badge badge-pill badge-soft-warning">{{ $orderAnywhereCount }} {{ translate('Active') }}</span>
                    </div>
                    <div class="p-3">
                        <p class="text-muted fz-13 mb-3">{{ translate('Custom quote and order requests assigned directly to your store.') }}</p>
                        <a href="{{ route('vendor.urban-goodz.order-anywhere.index') }}" class="btn btn-ug-primary w-100">{{ translate('Manage Order Anywhere') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="ug-vendor-card h-100">
                    <div class="ug-vendor-card-header">
                        <h5 class="ug-vendor-card-title"><i class="tio-money mr-2 text-success"></i>{{ translate('Urban Goodz Earnings') }}</h5>
                        <span class="badge badge-pill badge-soft-success">{{ translate('Live Splits') }}</span>
                    </div>
                    <div class="p-3">
                        <p class="text-muted fz-13 mb-3">{{ translate('Track payment splits, platform revenue share, and payout status.') }}</p>
                        <a href="{{ route('vendor.urban-goodz.payments.index') }}" class="btn btn-ug-dark w-100">{{ translate('View UG Earnings') }}</a>
                    </div>
                </div>
            </div>
            @foreach(['rentals','book-anything','events','creators','community','spotlight','logistics','load-board'] as $section)
                <div class="col-md-6 col-xl-4">
                    <div class="ug-vendor-card h-100">
                        <div class="ug-vendor-card-header">
                            <h5 class="ug-vendor-card-title">{{ str($section)->replace('-', ' ')->title() }}</h5>
                            <span class="badge badge-pill badge-soft-secondary">{{ translate('Capability') }}</span>
                        </div>
                        <div class="p-3">
                            <p class="text-muted fz-13 mb-3">{{ translate('Vendor-scoped operational module for Urban Goodz business capabilities.') }}</p>
                            <a href="{{ route('vendor.urban-goodz.section', $section) }}" class="btn btn-outline-secondary w-100">{{ translate('View Status') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
