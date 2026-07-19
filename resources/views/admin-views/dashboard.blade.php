@extends('layouts.admin.app')

@section('title',\App\Models\BusinessSetting::where(['key'=>'business_name'])->first()->value??translate('messages.dashboard'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @if(auth('admin')->user()->role_id == 1)
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center py-2">
                <div class="col-sm mb-2 mb-sm-0">
                    <div class="d-flex align-items-center">
                        <img src="{{asset('/public/assets/admin/img/grocery.svg')}}" alt="img">
                        <div class="w-0 flex-grow pl-2">
                            <h1 class="page-header-title mb-0">{{translate('messages.welcome')}}, {{auth('admin')->user()->f_name}}.</h1>
                            <p class="page-header-text m-0">{{translate('messages.welcome_message')}}</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-auto min--280">
                    <select name="zone_id" class="form-control js-select2-custom fetch_data_zone_wise">
                        <option value="all">{{ translate('messages.All_Zones') }}</option>
                        @foreach(\App\Models\Zone::orderBy('name')->get() as $zone)
                            <option
                                value="{{$zone['id']}}" {{$params['zone_id'] == $zone['id']?'selected':''}}>
                                {{$zone['name']}}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <!-- End Page Header -->

        <!-- Urban Goodz Command Center -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-header-title">{{ translate('Urban Goodz Command Center') }}</h3>
                <span class="badge badge-soft-primary">{{ translate('Platform Operations') }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">{{ translate('Manage business clients, orders, routes, drivers, vendors, creators, payments, and platform operations.') }}</p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="{{ route('admin.urban-goodz.business-clients.index') }}" class="btn btn-primary btn-sm">{{ translate('Business Clients') }}</a>
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Command Center') }}</a>
                    <a href="{{ route('admin.store.list') }}" class="btn btn-outline-primary btn-sm">{{ translate('Vendors') }}</a>
                    <a href="{{ route('admin.delivery-man.list') }}" class="btn btn-outline-primary btn-sm">{{ translate('Drivers') }}</a>
                    <a href="{{ route('admin.order.list', ['all']) }}" class="btn btn-outline-primary btn-sm">{{ translate('Orders') }}</a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}" class="btn btn-outline-primary btn-sm">{{ translate('Dispatcher') }}</a>
                    <a href="{{ route('admin.urban-goodz.load-board.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Load Board') }}</a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Load Sourcing') }}</a>
                    <a href="{{ route('admin.urban-goodz.driver-pricing.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Driver Pricing') }}</a>
                    <a href="{{ route('admin.urban-goodz.payments.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Payment Center') }}</a>
                    <a href="{{ route('admin.urban-goodz.dedicated-routes.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Dedicated Routes') }}</a>
                    <a href="{{ route('admin.urban-goodz.driver-payouts.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Driver Payouts') }}</a>
                    <a href="{{ route('admin.urban-goodz.fashion-fit.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Fashion Fit') }}</a>
                    <a href="{{ route('admin.urban-goodz.order-anywhere.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Order Anywhere') }}</a>
                    <a href="{{ route('admin.urban-goodz.ai-concierge.conversations') }}" class="btn btn-outline-primary btn-sm">{{ translate('AI Concierge') }}</a>
                    <a href="{{ route('admin.urban-goodz.creator.dashboard') }}" class="btn btn-outline-primary btn-sm">{{ translate('Creator Commerce') }}</a>
                    <a href="{{ route('admin.urban-goodz.service-requests.index') }}" class="btn btn-outline-primary btn-sm">{{ translate('Services') }}</a>
                    <a href="{{ route('admin.notification.add-new') }}" class="btn btn-outline-primary btn-sm">{{ translate('Notifications') }}</a>
                    <a href="{{ route('admin.report.item-wise-report') }}" class="btn btn-outline-primary btn-sm">{{ translate('Reports') }}</a>
                    <a href="{{ route('admin.business-settings.business-setup') }}" class="btn btn-outline-primary btn-sm">{{ translate('Settings') }}</a>
                    <a href="{{ url('/business/login') }}" class="btn btn-outline-secondary btn-sm" target="_blank">{{ translate('Business Portal Login') }}</a>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.business-clients.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Business Clients') }}</h6>
                                <span class="card-title text-info">{{ $ugData['business_clients_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="order--card h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Business Portal Users') }}</h6>
                                <span class="card-title text-info">{{ $ugData['business_portal_users_count'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.dedicated-routes.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Dedicated Routes') }}</h6>
                                <span class="card-title text-info">{{ $ugData['dedicated_routes_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="order--card h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Route Packages') }}</h6>
                                <span class="card-title text-info">{{ $ugData['route_packages_count'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.driver-earnings.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Driver Earnings') }}</h6>
                                <span class="card-title text-success">{{ $ugData['driver_earnings_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.driver-payouts.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Driver Payouts') }}</h6>
                                <span class="card-title text-warning">{{ $ugData['driver_payouts_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.order-anywhere.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Order Anywhere') }}</h6>
                                <span class="card-title text-info">{{ $ugData['order_anywhere_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.payments.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Payment Center') }}</h6>
                                <span class="card-title text-primary">{{ $ugData['payment_ledgers_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.creator.dashboard') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Creator Space') }}</h6>
                                <span class="card-title text-info">{{ $ugData['creator_applications_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.fashion-fit.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Fashion Fit') }}</h6>
                                <span class="card-title text-info">{{ $ugData['fashion_fit_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.ai-concierge.conversations') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('AI Concierge') }}</h6>
                                <span class="card-title text-info">{{ $ugData['ai_conversations_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'discovery') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Discovery Searches') }}</h6>
                                <span class="card-title text-info">{{ $ugData['discovery_searches_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'logistics') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Logistics') }}</h6>
                                <span class="card-title text-info">{{ $ugData['logistics_jobs_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'medical-courier') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Medical Courier') }}</h6>
                                <span class="card-title text-info">{{ $ugData['medical_courier_jobs_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.load-board.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Load Board') }}</h6>
                                <span class="card-title text-info">{{ $ugData['load_board_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.payments.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Total Revenue') }}</h6>
                                <span class="card-title text-success">{{ \App\CentralLogics\Helpers::format_currency($ugData['total_revenue'] ?? 0) }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.payments.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Pending Refunds') }} <span class="text-danger">*</span></h6>
                                <span class="card-title text-danger">{{ $ugData['pending_refunds'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.store.list') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Vendors') }}</h6>
                                <span class="card-title text-info">{{ $data['total_stores'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.delivery-man.list') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Drivers') }}</h6>
                                <span class="card-title text-info">{{ $data['delivery_man'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.order.list', ['all']) }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Orders') }}</h6>
                                <span class="card-title text-info">{{ $data['total_orders'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Dispatcher') }}</h6>
                                <span class="card-title text-info">{{ $ugData['dedicated_routes_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.load-sourcing.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Load Sourcing') }}</h6>
                                <span class="card-title text-info">{{ $ugData['load_board_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.driver-pricing.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Driver Pricing') }}</h6>
                                <span class="card-title text-info">{{ $ugData['driver_pricing_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.customer.wallet.add-fund') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Customer Wallets') }}</h6>
                                <span class="card-title text-info">{{ $data['customer'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.service-requests.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Services') }}</h6>
                                <span class="card-title text-info">{{ $ugData['service_requests_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.notification.add-new') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Push Notifications') }}</h6>
                                <span class="card-title text-info">{{ $ugData['notifications_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.report.item-wise-report') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Reports') }}</h6>
                                <span class="card-title text-info">{{ translate('View') }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.business-settings.business-setup') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Settings') }}</h6>
                                <span class="card-title text-info">{{ translate('Configure') }}</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Urban Goodz Command Center -->

        <div class="card mt-4">
            <div class="card-header">
                <h4 class="card-header-title">{{ translate('Urban Goodz Revenue Command Center') }}</h4>
                <span class="badge badge-soft-primary">{{ translate('Marketplace Expansion') }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">{{ translate('Monitor marketplace expansion, Order Anywhere, rentals, services, creator commerce, logistics, medical courier, AI demand, and partner revenue.') }}</p>
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Total Revenue') }}</h6>
                                <span class="card-title text-success">
                                    {{ \App\CentralLogics\Helpers::format_currency($ugData['total_revenue'] ?? 0) }}
                                </span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.order-anywhere.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Order Anywhere') }}</h6>
                                <span class="card-title text-info">{{ $ugData['order_anywhere_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.payments.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Payment Ledgers') }}</h6>
                                <span class="card-title text-info">{{ $ugData['payment_ledgers_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'rentals') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Rental Bookings') }}</h6>
                                <span class="card-title text-info">{{ $ugData['rental_bookings_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'rentals') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Rental Assets') }}</h6>
                                <span class="card-title text-info">{{ $ugData['rental_assets_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.fashion-fit.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Fashion Fit') }}</h6>
                                <span class="card-title text-info">{{ $ugData['fashion_fit_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.ai-concierge.conversations') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('AI Conversations') }}</h6>
                                <span class="card-title text-info">{{ $ugData['ai_conversations_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'logistics') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Logistics Jobs') }}</h6>
                                <span class="card-title text-info">{{ $ugData['logistics_jobs_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'medical-courier') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Medical Courier Jobs') }}</h6>
                                <span class="card-title text-info">{{ $ugData['medical_courier_jobs_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'events') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Events') }}</h6>
                                <span class="card-title text-info">{{ $ugData['events_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.creator.dashboard') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Creator Commerce') }}</h6>
                                <span class="card-title text-info">{{ $ugData['creator_applications_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.creator.campaigns') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Creator Campaigns') }}</h6>
                                <span class="card-title text-warning">{{ $ugData['creator_campaigns_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.creator.earnings') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Creator Revenue') }}</h6>
                                <span class="card-title text-success">{{ \App\CentralLogics\Helpers::format_currency($ugData['creator_revenue'] ?? 0) }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.creator.leads') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Business Leads') }}</h6>
                                <span class="card-title text-info">{{ $ugData['creator_business_leads_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.payments.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Pending Refunds') }} <span class="text-danger">*</span></h6>
                                <span class="card-title text-danger">{{ $ugData['pending_refunds'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.business-clients.index') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Business Clients') }}</h6>
                                <span class="card-title text-info">{{ $ugData['business_clients_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'earn-money') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Earn Opportunities') }}</h6>
                                <span class="card-title text-info">{{ $ugData['earn_opportunities_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'community') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Community Posts') }}</h6>
                                <span class="card-title text-info">{{ $ugData['community_posts_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="order--card h-100" href="{{ route('admin.urban-goodz.section', 'discovery') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">{{ translate('Discovery Searches') }}</h6>
                                <span class="card-title text-info">{{ $ugData['discovery_searches_count'] ?? 0 }}</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legacy Marketplace Metrics -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-header-title">{{ translate('Legacy Marketplace Metrics') }}</h5>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex flex-wrap align-items-center justify-content-end">
                    <div class="status-filter-wrap">
                        <div class="statistics-btn-grp">
                            <label>
                                <input type="radio" name="statistics" hidden checked>
                                <span>{{ translate('This_Year') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="statistics" hidden>
                                <span>{{ translate('This_Month') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="statistics" hidden>
                                <span>{{ translate('This_Week') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="row g-2" id="order_stats">
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{asset('/public/assets/admin/img/dashboard/food/items.svg')}}" alt="dashboard/grocery">
                            <h6 class="name">{{ translate('Catalog Items') }}</h6>
                            <h3 class="count">{{ $data['total_items'] ?? 0 }}</h3>
                            <div class="subtxt">{{ $data['new_items'] ?? 0 }} {{ translate('newly added') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{asset('/public/assets/admin/img/dashboard/food/orders.svg')}}" alt="dashboard/grocery">
                            <h6 class="name">{{ translate('Marketplace Orders') }}</h6>
                            <h3 class="count">{{ $data['total_orders'] ?? 0 }}</h3>
                            <div class="subtxt">{{ $data['new_orders'] ?? 0 }} {{ translate('newly added') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{asset('/public/assets/admin/img/dashboard/food/stores.svg')}}" alt="dashboard/grocery">
                            <h6 class="name">{{ translate('Vendors / Providers') }}</h6>
                            <h3 class="count">{{ $data['total_stores'] ?? 0 }}</h3>
                            <div class="subtxt">{{ $data['new_stores'] ?? 0 }} {{ translate('newly added') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{asset('/public/assets/admin/img/dashboard/food/customers.svg')}}" alt="dashboard/grocery">
                            <h6 class="name">{{ translate('Customers') }}</h6>
                            <h3 class="count">{{ $data['total_customers'] ?? 0 }}</h3>
                            <div class="subtxt">{{ $data['new_customers'] ?? 0 }} {{ translate('newly added') }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['delivered'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/dashboard/food/unassigned.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('messages.unassigned_orders')}}</span>
                                        </h6>
                                        <span class="card-title text-3F8CE8">
                                            {{$data['searching_for_dm']}}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['refunded'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/dashboard/food/accepted.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('Accepted by Delivery Man')}}</span>
                                        </h6>
                                        <span class="card-title text-success">
                                            {{$data['accepted_by_dm']}}
                                        </span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['canceled'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/dashboard/food/packaging.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('Packaging')}}</span>
                                        </h6>
                                        <span class="card-title text-FFA800">
                                            {{$data['preparing_in_rs']}}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['failed'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/dashboard/food/out-for.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('Out for Delivery')}}</span>
                                        </h6>
                                        <span class="card-title text-success">
                                            {{$data['picked_up']}}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['delivered'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/dashboard/grocery/delivered.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('messages.delivered')}}</span>
                                        </h6>
                                        <span class="card-title text-success">
                                            {{$data['delivered']}}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['canceled'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/order-status/canceled.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('messages.canceled')}}</span>
                                        </h6>
                                        <span class="card-title text-danger">
                                            {{$data['canceled']}}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['refunded'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/order-status/refunded.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('messages.refunded')}}</span>
                                        </h6>
                                        <span class="card-title text-danger">
                                            {{$data['refunded']}}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{route('admin.order.list',['failed'])}}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center text-hover--primary">
                                            <img src="{{asset('/public/assets/admin/img/order-status/payment-failed.svg')}}" alt="dashboard" class="oder--card-icon">
                                            <span>{{translate('messages.payment_failed')}}</span>
                                        </h6>
                                        <span class="card-title text-danger">
                                            {{$data['refund_requested']}}
                                        </span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!-- End Stats -->

        <div class="row g-2">
            <div class="col-lg-8 col--xl-8">
                <div class="card h-100">
                    <div class="card-body" id="sales-chart-section">
                        <div class="d-flex flex-wrap justify-content-between align-items-center __gap-12px">
                            <div class="__gross-amount" id="gross_sale">
                                <h6>{{\App\CentralLogics\Helpers::format_currency(array_sum($total_sell))}}</h6>
                                <span>{{ translate('messages.Gross Sale') }}</span>
                            </div>
                            <div class="chart--label __chart-label p-0 move-left-100 ml-auto">
                                <span class="indicator chart-bg-2"></span>
                                <span class="info">
                                    {{ translate('sale') }} ({{ date("Y") }})
                                </span>
                            </div>
                            <select class="custom-select border-0 text-center w-auto ml-auto commission_overview_stats_update" name="commission_overview">
                                <option
                                value="this_year" {{$params['commission_overview'] == 'this_year'?'selected':''}}>
                                {{translate('This year')}}
                            </option>
                                <option
                                value="this_month" {{$params['commission_overview'] == 'this_month'?'selected':''}}>
                                {{translate('This month')}}
                            </option>
                                <option
                                value="this_week" {{$params['commission_overview'] == 'this_week'?'selected':''}}>
                                {{translate('This week')}}
                            </option>
                            </select>
                        </div>
                        <div id="commission-overview-board">
                            <div id="grow-sale-chart"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col--xl-4">
                <!-- Card -->
                <div class="card h-100">
                    <!-- Header -->
                    <div class="card-header border-0">
                        <h5 class="card-header-title">
                            {{translate('User Statistics')}}
                        </h5>
                        <select class="custom-select border-0 text-center w-auto user_overview_stats_update" name="user_overview">
                            <option
                                value="this_month" {{$params['user_overview'] == 'this_month'?'selected':''}}>
                                {{translate('This month')}}
                            </option>
                            <option
                                value="overall" {{$params['user_overview'] == 'overall'?'selected':''}}>
                                {{translate('messages.Overall')}}
                            </option>
                        </select>
                    </div>
                    <!-- End Header -->

                    <!-- Body -->
                    <div class="card-body" id="user-overview-board">
                        <div class="position-relative pie-chart">
                            <div id="dognut-pie"></div>
                            <!-- Total Orders -->
                            <div class="total--orders">
                                <h3 class="text-uppercase mb-xxl-2">{{ $data['customer'] + $data['stores'] + $data['delivery_man'] }}</h3>
                                <span class="text-capitalize">{{translate('messages.total_users')}}</span>
                            </div>
                            <!-- Total Orders -->
                        </div>
                        <div class="d-flex flex-wrap justify-content-center mt-4">
                            <div class="chart--label">
                                <span class="indicator chart-bg-1"></span>
                                <span class="info">
                                    {{translate('messages.customer')}} {{$data['customer']}}
                                </span>
                            </div>
                            <div class="chart--label">
                                <span class="indicator chart-bg-2"></span>
                                <span class="info">
                                    {{translate('messages.store')}} {{$data['stores']}}
                                </span>
                            </div>
                            <div class="chart--label">
                                <span class="indicator chart-bg-3"></span>
                                <span class="info">
                                    {{translate('messages.delivery_man')}} {{$data['delivery_man']}}
                                </span>
                            </div>
                        </div>

                    </div>
                    <!-- End Body -->
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- Card -->
                <div class="card h-100" id="top-restaurants-view">
                    @include('admin-views.partials._top-restaurants',['top_restaurants'=>$data['top_restaurants']])
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- Card -->
                <div class="card h-100" id="popular-restaurants-view">
                    @include('admin-views.partials._popular-restaurants',['popular'=>$data['popular']])
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- Card -->
                <div class="card h-100" id="top-selling-foods-view">
                    @include('admin-views.partials._top-selling-foods',['top_sell'=>$data['top_sell']])
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- Card -->
                <div class="card h-100" id="top-rated-foods-view">
                    @include('admin-views.partials._top-rated-foods',['top_rated_foods'=>$data['top_rated_foods']])
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- Card -->
                <div class="card h-100" id="top-deliveryman-view">
                    @include('admin-views.partials._top-deliveryman',['top_deliveryman'=>$data['top_deliveryman']])
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- Card -->
                <div class="card h-100" id="top-customer-view">
                    @include('admin-views.partials._top-customer',['top_customers'=>$data['top_customers']])
                </div>
                <!-- End Card -->
            </div>

        </div>

        @else
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{translate('messages.welcome')}}, {{auth('admin')->user()->f_name}}.</h1>
                    <p class="page-header-text">{{translate('messages.employee_welcome_message')}}</p>
                </div>
            </div>
        </div>
        <!-- End Page Header -->
        @endif
    </div>
@endsection

@push('script')
    <script src="{{asset('public/assets/admin')}}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/vendor/chart.js.extensions/chartjs-extensions.js"></script>
    <script src="{{asset('public/assets/admin')}}/vendor/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js"></script>

    <!-- Apex Charts -->
    <script src="{{asset('/public/assets/admin/js/apex-charts/apexcharts.js')}}"></script>
    <!-- Apex Charts -->

@endpush


@push('script_2')

    <!-- Dognut Pie Chart -->
    <script>
        "use strict";
        let options;
        let chart;
        options = {
            series: [{{ $data['customer']}}, {{$data['stores']}}, {{$data['delivery_man']}}],
            chart: {
                width: 320,
                type: 'donut',
            },
            labels: ['{{ translate('Customer') }}', '{{ translate('Store') }}', '{{ translate('Delivery man') }}'],
            dataLabels: {
                enabled: false,
                style: {
                    colors: ['#005555', '#00aa96', '#b9e0e0',]
                }
            },
            responsive: [{
                breakpoint: 1650,
                options: {
                    chart: {
                        width: 250
                    },
                }
            }],
            colors: ['#005555','#00aa96', '#111'],
            fill: {
                colors: ['#005555','#00aa96', '#b9e0e0']
            },
            legend: {
                show: false
            },
        };

        chart = new ApexCharts(document.querySelector("#dognut-pie"), options);
        chart.render();

        options = {
            series: [{
                name: '{{ translate('Gross Sale') }}',
                data: [{{ implode(",",$total_sell) }}]
            },{
                name: '{{ translate('Admin Comission') }}',
                data: [{{ implode(",",$commission) }}]
            },{
                name: '{{ translate('Delivery Comission') }}',
                data: [{{ implode(",",$delivery_commission) }}]
            }],
            chart: {
                height: 350,
                type: 'area',
                toolbar: {
                    show:false
                },
                colors: ['#76ffcd','#ff6d6d', '#005555'],
            },
            colors: ['#76ffcd','#ff6d6d', '#005555'],
            dataLabels: {
                enabled: false,
                colors: ['#76ffcd','#ff6d6d', '#005555'],
            },
            stroke: {
                curve: 'smooth',
                width: 2,
                colors: ['#76ffcd','#ff6d6d', '#005555'],
            },
            fill: {
                type: 'gradient',
                colors: ['#76ffcd','#ff6d6d', '#005555'],
            },
            xaxis: {
                //   type: 'datetime',
                categories: [{!! implode(",",$label) !!}]
            },
            tooltip: {
                x: {
                    format: 'dd/MM/yy HH:mm'
                },
            },
        };

        chart = new ApexCharts(document.querySelector("#grow-sale-chart"), options);
        chart.render();

    <!-- Dognut Pie Chart -->
        // INITIALIZATION OF CHARTJS
        // =======================================================
        Chart.plugins.unregister(ChartDataLabels);

        $('.js-chart').each(function () {
            $.HSCore.components.HSChartJS.init($(this));
        });

        let updatingChart = $.HSCore.components.HSChartJS.init($('#updatingData'));

        function order_stats_update(type) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.dashboard-stats.order')}}',
                data: {
                    statistics_type: type
                },
                beforeSend: function () {
                    $('#loading').show()
                },
                success: function (data) {
                    insert_param('statistics_type',type);
                    $('#order_stats').html(data.view)
                },
                complete: function () {
                    $('#loading').hide()
                }
            });
        }

        $('.fetch_data_zone_wise').on('change', function (){
            let zone_id = $(this).val();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.dashboard-stats.zone')}}',
                data: {
                    zone_id: zone_id
                },
                beforeSend: function () {
                    $('#loading').show()
                },
                success: function (data) {
                    insert_param('zone_id', zone_id);
                    $('#order_stats').html(data.order_stats);
                    $('#user-overview-board').html(data.user_overview);
                    $('#monthly-earning-graph').html(data.monthly_graph);
                    $('#popular-restaurants-view').html(data.popular_restaurants);
                    $('#top-deliveryman-view').html(data.top_deliveryman);
                    $('#top-rated-foods-view').html(data.top_rated_foods);
                    $('#top-restaurants-view').html(data.top_restaurants);
                    $('#top-selling-foods-view').html(data.top_selling_foods);
                    $('#stat_zone').html(data.stat_zone);
                },
                complete: function () {
                    $('#loading').hide()
                }
            });
        })

        $('.user_overview_stats_update').on('change', function (){
            let type = $(this).val();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.dashboard-stats.user-overview')}}',
                data: {
                    user_overview: type
                },
                beforeSend: function () {
                    $('#loading').show()
                },
                success: function (data) {
                    insert_param('user_overview',type);
                    $('#user-overview-board').html(data.view)
                },
                complete: function () {
                    $('#loading').hide()
                }
            });
        })

        $('.commission_overview_stats_update').on('change', function (){
            let type = $(this).val();
            commission_overview_stats_update(type);
        })

        function commission_overview_stats_update(type) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.dashboard-stats.commission-overview')}}',
                data: {
                    commission_overview: type
                },
                beforeSend: function () {
                    $('#loading').show()
                },
                success: function (data) {
                    insert_param('commission_overview',type);
                    $('#commission-overview-board').html(data.view)
                    $('#gross_sale').html(data.gross_sale)
                },
                complete: function () {
                    $('#loading').hide()
                }
            });
        }

        function insert_param(key, value) {
            key = encodeURIComponent(key);
            value = encodeURIComponent(value);
            // kvp looks like ['key1=value1', 'key2=value2', ...]
            let kvp = document.location.search.substr(1).split('&');
            let i = 0;

            for (; i < kvp.length; i++) {
                if (kvp[i].startsWith(key + '=')) {
                    let pair = kvp[i].split('=');
                    pair[1] = value;
                    kvp[i] = pair.join('=');
                    break;
                }
            }
            if (i >= kvp.length) {
                kvp[kvp.length] = [key, value].join('=');
            }
            // can return this or...
            let params = kvp.join('&');
            // change url page with new params
            window.history.pushState('page2', 'Title', '{{url()->current()}}?' + params);
        }
    </script>
@endpush
