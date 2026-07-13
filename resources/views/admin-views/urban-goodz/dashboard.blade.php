@extends('layouts.admin.app')

@section('title', translate('Urban Goodz Command Center'))

@php
    $statusBadgeMap = [
        'Live' => 'badge-soft-success',
        'DB-Backed' => 'badge-soft-info',
        'Payment Ready' => 'badge-soft-primary',
        'Admin Workflow Pending' => 'badge-soft-warning',
        'API Connected, Admin Workflow Pending' => 'badge-soft-primary',
        'Workflow Pending' => 'badge-soft-secondary',
        'Ready for Configuration' => 'badge-soft-warning',
    ];
@endphp

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Urban Goodz Command Center') }}</h1>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Order Anywhere') }}</h6>
                        <h3>{{ $counts['order_anywhere'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Fashion Fit') }}</h6>
                        <h3>{{ $counts['fashion_fit'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Payment Ledgers') }}</h6>
                        <h3>{{ $counts['payments'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Rental Assets') }}</h6>
                        <h3>{{ $counts['rental_assets'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Rental Bookings') }}</h6>
                        <h3>{{ $counts['rental_bookings'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('AI Conversations') }}</h6>
                        <h3>{{ $counts['ai_conversations'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('AI Intents') }}</h6>
                        <h3>{{ $counts['ai_intents'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Business Types') }}</h6>
                        <h3>{{ $counts['business_types'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Capabilities') }}</h6>
                        <h3>{{ $counts['capabilities'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="card h-100" href="{{ route('admin.urban-goodz.load-board.index') }}" style="text-decoration:none;color:inherit;">
                    <div class="card-body">
                        <h6>{{ translate('Logistics Jobs') }}</h6>
                        <h3>{{ $counts['logistics_jobs'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="card h-100" href="{{ route('admin.urban-goodz.load-board.index') }}" style="text-decoration:none;color:inherit;">
                    <div class="card-body">
                        <h6>{{ translate('Load Board') }}</h6>
                        <h3>{{ \App\Models\UrbanGoodzLoadBoardLoad::count() }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="card h-100" href="{{ route('admin.urban-goodz.medical-courier.index') }}" style="text-decoration:none;color:inherit;">
                    <div class="card-body">
                        <h6>{{ translate('Medical Courier Jobs') }}</h6>
                        <h3>{{ $counts['medical_courier_jobs'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="card h-100" href="{{ route('admin.urban-goodz.section', 'events') }}" style="text-decoration:none;color:inherit;">
                    <div class="card-body">
                        <h6>{{ translate('Events') }}</h6>
                        <h3>{{ $counts['events'] ?? 0 }}</h3>
                        <span class="badge badge-soft-info">{{ translate('DB-Backed') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Earn Opportunities') }}</h6>
                        <h3>{{ $counts['earn_opportunities'] ?? 0 }}</h3>
                        <span class="badge badge-soft-info">{{ translate('DB-Backed') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="card h-100" href="{{ route('admin.urban-goodz.creator.dashboard') }}" style="text-decoration:none;color:inherit;">
                    <div class="card-body">
                        <h6>{{ translate('Creator Commerce') }}</h6>
                        <h3>{{ $counts['creator_applications'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Community Posts') }}</h6>
                        <h3>{{ $counts['community_posts'] ?? 0 }}</h3>
                        <span class="badge badge-soft-info">{{ translate('DB-Backed') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Discovery Searches') }}</h6>
                        <h3>{{ $counts['discovery_searches'] ?? 0 }}</h3>
                        <span class="badge badge-soft-primary">{{ translate('API Connected, Admin Workflow Pending') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="card h-100" href="{{ route('admin.urban-goodz.business-clients.index') }}" style="text-decoration:none;color:inherit;">
                    <div class="card-body">
                        <h6>{{ translate('Business Clients') }}</h6>
                        <h3>{{ $counts['business_clients'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('Live') }}</span>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-3">
            @foreach($sections as $key => $section)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0">{{ $section['title'] }}</h5>
                                <span class="badge {{ $statusBadgeMap[$section['status']] ?? 'badge-soft-secondary' }}">
                                    {{ $section['status'] }}
                                </span>
                            </div>
                            <p class="text-muted mb-2">{{ $section['admin_workflow'] }}</p>
                            @if($section['revenue'] ?? false)
                                <span class="badge badge-soft-primary mb-2" style="align-self: flex-start;">{{ translate('Revenue') }}</span>
                            @endif
                            @php
                                $routeExists = Route::has(
                                    str_replace(url('/'), '', $section['url'])
                                        ? ltrim(parse_url($section['url'], PHP_URL_PATH), '/')
                                        : 'admin.urban-goodz.section'
                                );
                            @endphp
                            @if($section['status'] === 'Live')
                                <a href="{{ $section['url'] }}" class="btn btn--primary mt-auto">{{ translate('Open') }}</a>
                            @else
                                <button class="btn btn-secondary mt-auto" disabled title="{{ translate('Coming in next workflow package') }}">
                                    {{ translate($section['status'] === 'Admin Workflow Pending' ? 'Workflow Pending' : 'Coming Soon') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection