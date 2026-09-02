<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <!-- Logo -->
                <a class="navbar-brand" href="{{ route('admin.dispatch.dashboard') }}" aria-label="Urban Goodz">
                       <img class="navbar-brand-logo initial--36"
                    src="{{asset('public/assets/landing/img/logo.svg')}}"
                    alt="Urban Goodz" style="height:32px;width:auto;">
                    <img class="navbar-brand-logo-mini initial--36"
                    src="{{asset('public/assets/admin/svg/logos/logo-short.svg')}}"
                    alt="UG">
                </a>
                <!-- End Logo -->

                <!-- Navbar Vertical Toggle -->
                <button type="button" class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                    <i class="tio-clear tio-lg"></i>
                </button>
                <!-- End Navbar Vertical Toggle -->

                <div class="navbar-nav-wrap-content-left">
                    <!-- Navbar Vertical Toggle -->
                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                        data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                        data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'></i>
                    </button>
                    <!-- End Navbar Vertical Toggle -->
                </div>

            </div>

            <!-- Content -->
            <div class="navbar-vertical-content bg--005555" id="navbar-vertical-content">
                <form class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input type="text" class="form-control form--control" placeholder="{{ translate('Search Menu...') }}" id="search-sidebar-menu">
                    </div>
                </form>
                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    <!-- Dashboards -->
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.dashboard') }}?module_id={{Config::get('module.current_module_id')}}" title="{{ translate('messages.dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.dashboard') }}
                            </span>
                        </a>
                    </li>
                    <!-- End Dashboards -->

                    <!-- Urban Goodz Full Ecosystem -->
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_view'))
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Urban Goodz">Urban Goodz</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    {{-- Core Platform --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Core Platform">Core Platform</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.index') }}" title="Control Center">
                            <i class="tio-dashboard-outlined nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Control Center</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/ai-chief-of-staff*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.ai-chief-of-staff') }}" title="AI Chief of Staff">
                            <i class="tio-robot nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">AI Chief of Staff</span>
                            <span class="badge badge-warning badge-pill ml-1" style="font-size: 0.6rem; background-color: #ED9914; color: #fff;">AI</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/mobile-releases*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.mobile-releases.index') }}" title="Mobile In-App Updates">
                            <i class="tio-download-to nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Mobile In-App Updates</span>
                            <span class="badge badge-soft-info badge-pill ml-1" style="font-size: 0.6rem;">v1.3.0</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/data-center*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.data-center.index') }}" title="Marketplace Data Center">
                            <i class="tio-database nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Marketplace Data Center</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ url('/business/login') }}" target="_blank" title="Open Business Vendor Portal">
                            <i class="tio-business-bag nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Business Portal</span>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate" style="font-size:0.6rem;display:block;color:rgba(255,255,255,0.6);margin-top:-2px;">Opens in new tab</span>
                        </a>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_business_types_view'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/business-types*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.business-types.index') }}" title="Business Types">
                            <i class="tio-category nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Business Types</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_capabilities_view'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/capabilities*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.capabilities.index') }}" title="Capabilities">
                            <i class="tio-slider nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Capabilities</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_files'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/files*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.files.index') }}" title="File Library">
                            <i class="tio-folder-open nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">File Library</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_payments_view'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/payments*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.payments.index') }}" title="Payment Center">
                            <i class="tio-money nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Payment Center</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_financial_control_view') || \App\CentralLogics\Helpers::module_permission_check('urban_goodz_financial_control_manage') || \App\CentralLogics\Helpers::module_permission_check('urban_goodz_payments_view'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/financial-control*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.financial-control.index') }}" title="Financial Control Center">
                            <i class="tio-chart-pie-1 nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Financial Control Center</span>
                        </a>
                    </li>
                    @endif
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/historical-reconstruction*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.historical-reconstruction.index') }}" title="Historical Reconstruction">
                            <i class="tio-history nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Historical Reconstruction</span>
                        </a>
                    </li>

                    {{-- Commerce --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Commerce">Commerce</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @php $ugModules = \App\Services\UrbanGoodzModuleStatusService::all(); @endphp
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_order_anywhere_view') && ($ugModules['order-anywhere']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/order-anywhere*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.order-anywhere.index') }}" title="Order Anywhere">
                            <i class="tio-shopping-cart nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Order Anywhere</span>
                        </a>
                    </li>
                    @endif

                    {{-- Services --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Services">Services</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_fashion_fit_view') && ($ugModules['fashion-fit']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/fashion-fit*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.fashion-fit.index') }}" title="Fashion Fit">
                            <i class="tio-tshirt nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Fashion Fit</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_medical_courier_view') && ($ugModules['medical-courier']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/medical-courier*') || Request::is('admin/urban-goodz/medical-courier*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'medical-courier') }}" title="Medical Courier">
                            <i class="tio-medical nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Medical Courier</span>
                            @if(($ugModules['medical-courier']['record_count'] ?? 0) > 0)
                            <span class="badge badge-pill badge-soft-secondary ml-1" style="font-size: 0.65rem;">{{ $ugModules['medical-courier']['record_count'] }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    {{-- Rentals --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Rentals">Rentals</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_rentals_view'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.dashboard') }}" title="Rentals Dashboard">
                            <i class="tio-dashboard-outlined nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Rentals Dashboard</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/assets*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.assets.index') }}" title="All Rental Assets">
                            <i class="tio-car nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">All Assets</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/assets*') && request('business_type_slug') === 'car_rental' ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.assets.index', ['business_type_slug' => 'car_rental']) }}" title="Car Rental">
                            <i class="tio-car nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Car Rental</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/assets*') && request('business_type_slug') === 'vehicle_rental' ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.assets.index', ['business_type_slug' => 'vehicle_rental']) }}" title="Vehicle Rental">
                            <i class="tio-car nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Vehicle Rental</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/assets*') && request('business_type_slug') === 'equipment_rental' ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.assets.index', ['business_type_slug' => 'equipment_rental']) }}" title="Equipment Rental">
                            <i class="tio-buildings nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Equipment Rental</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/bookings*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.bookings.index') }}" title="Rental Calendar">
                            <i class="tio-calendar nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Rental Calendar</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/bookings*') && request('deposit_status') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.bookings.index') }}" title="Deposit / Verification">
                            <i class="tio-shield nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Deposit / Verification</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/bookings*') && (request('status') === 'picked_up' || request('status') === 'returned') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.bookings.index', ['status' => 'picked_up']) }}" title="Pickup / Return">
                            <i class="tio-arrows-horizontal nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Pickup / Return</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/rentals/inspections*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.rentals.inspections.index') }}" title="Damage Reports">
                            <i class="tio-warning nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Damage Reports</span>
                        </a>
                    </li>
                    @endif

                    {{-- Social / Creator --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Social / Creator">Social / Creator</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_community'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/community*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'community') }}" title="Community Marketplace">
                            <i class="tio-users nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Community Marketplace</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_creator_commerce_view') || \App\CentralLogics\Helpers::module_permission_check('urban_goodz_creator_commerce'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/creator-commerce*') || Request::is('admin/urban-goodz/modules/creator-commerce*') || Request::is('admin/urban-goodz/section/creators') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.creator.dashboard') }}" title="Creator Commerce">
                            <i class="tio-star nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Creator Space</span>
                        </a>
                    </li>
                    @endif

                    {{-- Delivery / Driver --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Delivery / Driver">Delivery / Driver</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_logistics_view') && ($ugModules['logistics']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/logistics*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'logistics') }}" title="Logistics">
                            <i class="tio-truck nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Logistics</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_earn_money_view') && ($ugModules['earn-money']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/earn-money*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'earn-money') }}" title="Earn Money">
                            <i class="tio-money nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Earn Money</span>
                        </a>
                    </li>
                    @endif

                    {{-- AI Services --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="AI Services">AI Services</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_ai_concierge_view') && ($ugModules['ai-concierge']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/ai-concierge*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.ai-concierge.intents') }}" title="AI Concierge">
                            <i class="tio-robot nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">AI Concierge</span>
                        </a>
                    </li>
                    @endif
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/ai-operations*') || Request::is('admin/urban-goodz/load-sourcing*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="AI Operations">
                            <i class="tio-auto-flash nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">AI Operations</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="{{ Request::is('admin/urban-goodz/ai-operations*') || Request::is('admin/urban-goodz/load-sourcing*') ? 'display-block' : 'display-none' }}">
                            @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_ai_settings_view'))
                            <li class="nav-item {{ Request::is('admin/urban-goodz/ai-operations') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.urban-goodz.ai-operations.index') }}" title="AI Operations Center">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">AI Operations Center</span>
                                </a>
                            </li>
                            @endif
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/load-sourcing*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="Load Sourcing">
                                    <span class="tio-truck nav-indicator-icon"></span>
                                    <span class="text-truncate">Load Sourcing</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="{{ Request::is('admin/urban-goodz/load-sourcing*') ? 'display-block' : 'display-none' }}">
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/overview') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" title="Overview">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Overview</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/sources') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" title="Sources">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Sources</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/search') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.search') }}" title="Search Loads">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Search Loads</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/saved-searches') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" title="Saved Searches">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Saved Searches</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/sourced-loads') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" title="Sourced Loads">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Sourced Loads</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/recommendations') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" title="Recommendations">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Recommendations</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/sync-runs') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" title="Sync Runs">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Sync Runs</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/errors') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" title="Errors">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Errors</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/urban-goodz/load-sourcing/settings') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" title="Settings">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">Settings</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_ai_copilot_use') && ($ugModules['ai-copilot']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/ai-copilot*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.ai-copilot.index') }}" title="AI Ops Copilot">
                            <i class="tio-robot nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">AI Ops Copilot</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_load_board_view') && ($ugModules['load-board']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/load-board*') || (Request::is('admin/urban-goodz/driver-pricing*') && request('type') === 'logistics_loads') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="Load Board">
                            <i class="tio-truck nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Load Board</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="{{ Request::is('admin/urban-goodz/load-board*') || (Request::is('admin/urban-goodz/driver-pricing*') && request('type') === 'logistics_loads') ? 'display-block' : 'display-none' }}">
                            <li class="nav-item {{ Request::is('admin/urban-goodz/load-board') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.urban-goodz.load-board.index') }}" title="All Loads">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">All Loads</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/urban-goodz/driver-pricing*') && request('type') === 'logistics_loads' ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.urban-goodz.driver-pricing.index', ['type' => 'logistics_loads']) }}" title="Pricing Rules">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">Pricing Rules</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_discovery_view') && ($ugModules['discovery']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/discovery*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'discovery') }}" title="Discovery">
                            <i class="tio-search nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Discovery</span>
                        </a>
                    </li>
                    @endif

                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/dispatcher-sourcing*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard-blade') }}" title="Dispatcher Sourcing">
                            <i class="tio-filter-arrow-alt nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Dispatcher Sourcing</span>
                        </a>
                    </li>

                    {{-- Marketing / Subscription --}}
                    <li class="nav-item">
                        <small class="nav-subtitle" title="Marketing / Subscription">Marketing / Subscription</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_plus') && ($ugModules['plus']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/plus*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'plus') }}" title="Urban Goodz+">
                            <i class="tio-crown nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Urban Goodz+</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_spotlight') && ($ugModules['spotlight']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/spotlight*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'spotlight') }}" title="Black-Owned Spotlight">
                            <i class="tio-star nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Black-Owned Spotlight</span>
                        </a>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_events') && ($ugModules['events']['readiness'] ?? '') !== 'no_table')
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/urban-goodz/modules/events*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.urban-goodz.modules.index', 'events') }}" title="Events">
                            <i class="tio-calendar nav-icon" style="color: #ED9914;"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Events</span>
                            @if(($ugModules['events']['record_count'] ?? 0) > 0)
                            <span class="badge badge-pill badge-soft-secondary ml-1" style="font-size: 0.65rem;">{{ $ugModules['events']['record_count'] }}</span>
                            @endif
                        </a>
                    </li>
                    @endif
                    @endif

                    <!-- Marketing section -->
                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.employee_handle') }}">{{ translate('pos section') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    <!-- Pos -->
                    @if(\App\CentralLogics\Helpers::module_permission_check('pos'))
                    <li class="navbar-vertical-aside-has-menu {{Request::is('admin/pos*')?'active':''}}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link " href="{{route('admin.pos.index')}}" title="{{translate('New Sale')}}">
                            <i class="tio-shopping-basket-outlined nav-icon"></i>
                            <span class="text-truncate">{{translate('New Sale')}}</span>
                        </a>
                    </li>
                    @endif
                    <!-- Pos -->

                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.module_section') }}">{{ translate('messages.module_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('zone'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/zone*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.business-settings.zone.home') }}" title="{{ translate('messages.zone_setup') }}">
                            <i class="tio-city nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.zone_setup') }} </span>
                        </a>
                    </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/module') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.system_module_setup') }}">
                            <i class="tio-globe nav-icon"></i>
                            <span class="text-truncate">{{ translate('messages.system_module_setup') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/module*') ? 'block' : 'none' }}">
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/module/create') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.business-settings.module.create') }}" title="{{ translate('messages.add_module') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">
                                        {{ translate('messages.add_module') }}
                                    </span>
                                </a>
                            </li>
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/module') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.business-settings.module.index') }}" title="{{ translate('messages.modules') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">
                                        {{ translate('messages.modules') }}
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                <!-- Marketing section -->
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.employee_handle') }}">{{ translate('Promotions') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                <!-- Campaign -->
                @if (\App\CentralLogics\Helpers::module_permission_check('campaign'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/campaign') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.campaigns') }}">
                        <i class="tio-layers-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.campaigns') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/campaign*') ? 'block' : 'none' }}">

                        <li class="nav-item {{ Request::is('admin/campaign/basic/*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.campaign.list', 'basic') }}" title="{{ translate('messages.basic_campaigns') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.basic_campaigns') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/campaign/item/*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.campaign.list', 'item') }}" title="{{ translate('messages.item_campaigns') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.item_campaigns') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                <!-- End Campaign -->
                <!-- Banner -->
                @if (\App\CentralLogics\Helpers::module_permission_check('banner'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/banner*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.banner.add-new') }}" title="{{ translate('messages.banners') }}">
                        <i class="tio-image nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.banners') }}</span>
                    </a>
                </li>
                @endif
                <!-- End Banner -->
                <!-- Coupon -->
                @if (\App\CentralLogics\Helpers::module_permission_check('coupon'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/coupon*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.coupon.add-new') }}" title="{{ translate('messages.coupons') }}">
                        <i class="tio-gift nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.coupons') }}</span>
                    </a>
                </li>
                @endif
                <!-- End Coupon -->
                 @if (\App\CentralLogics\Helpers::module_permission_check('cashback'))
                 <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/cashback*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.cashback.add-new') }}" title="{{ translate('messages.cashback') }}">
                        <i class="tio-settings-back nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.cashback') }}</span>
                    </a>
                </li>
                @endif
                <!-- Notification -->
                @if (\App\CentralLogics\Helpers::module_permission_check('notification'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/notification*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.notification.add-new') }}" title="{{ translate('messages.push_notification') }}">
                        <i class="tio-notifications nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.push_notification') }}
                        </span>
                    </a>
                </li>
                @endif
                <!-- End Notification -->

                <!-- End marketing section -->
                    <!-- Orders -->
                    @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('messages.order_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/order') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.orders') }}">
                            <i class="tio-shopping-cart nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.orders') }}
                            </span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/order*') ? 'block' : 'none' }}">
                            <li class="nav-item {{ Request::is('admin/order/list/all') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['all']) }}" title="{{ translate('messages.all_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.all') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/scheduled') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['scheduled']) }}" title="{{ translate('messages.scheduled_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.scheduled') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::Scheduled()->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/pending') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['pending']) }}" title="{{ translate('messages.pending_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.pending') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::Pending()->OrderScheduledIn(30)->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>

                            <li class="nav-item {{ Request::is('admin/order/list/accepted') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['accepted']) }}" title="{{ translate('messages.accepted_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.accepted') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">
                                            {{ \App\Models\Order::AccepteByDeliveryman()->OrderScheduledIn(30)->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/processing') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['processing']) }}" title="{{ translate('messages.processing_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.processing') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">
                                            {{ \App\Models\Order::Preparing()->OrderScheduledIn(30)->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/item_on_the_way') ? 'active' : '' }}">
                                <a class="nav-link text-capitalize" href="{{ route('admin.order.list', ['item_on_the_way']) }}" title="{{ translate('messages.order_on_the_way') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.order_on_the_way') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">
                                            {{ \App\Models\Order::ItemOnTheWay()->OrderScheduledIn(30)->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/delivered') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['delivered']) }}" title="{{ translate('messages.delivered_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.delivered') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">
                                            {{ \App\Models\Order::Delivered()->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/canceled') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['canceled']) }}" title="{{ translate('messages.canceled_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.canceled') }}
                                        <span class="badge badge-soft-warning bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::Canceled()->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/failed') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['failed']) }}" title="{{ translate('messages.payment_failed_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container text-capitalize">
                                        {{ translate('messages.payment_failed') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::failed()->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/refunded') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['refunded']) }}" title="{{ translate('messages.refunded_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.refunded') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::Refunded()->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/offline/payment/list*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.offline_verification_list', ['all']) }}" title="{{ translate('Offline_Payments') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.Offline_Payments') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::where('payment_method', 'offline_payments')->whereHas('offline_payments')->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Order refund -->
                    <li
                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/refund/*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                        title="{{ translate('Order Refunds') }}">
                        <i class="tio-receipt nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('Order Refunds') }}
                        </span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                        style="display: {{ Request::is('admin/refund*') ? 'block' : 'none' }}">

                        <li class="nav-item {{ Request::is('admin/refund/requested') ||  Request::is('admin/refund/rejected') ||Request::is('admin/refund/refunded') ? 'active' : '' }}">
                            <a class="nav-link "
                                href="{{ route('admin.refund.refund_attr', ['requested']) }}"
                                title="{{ translate('Refund Requests') }} ">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate sidebar--badge-container">
                                    {{ translate('Refund Requests') }}
                                    <span class="badge badge-soft-danger badge-pill ml-1">
                                        {{ \App\Models\Order::Refund_requested()->StoreOrder()->count() }}
                                    </span>
                                </span>
                            </a>
                        </li>
                    </ul>
                    </li>
                    <!-- Order refund End-->

                    <!-- Order dispachment -->
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/dispatch/*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.dispatch') }}">
                            <i class="tio-clock nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.dispatch') }}
                            </span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="{{ Request::is('admin/dispatch*') ? 'display-block' : 'display-none' }}">
                            <li class="nav-item {{ Request::is('admin/dispatch/list/searching_for_deliverymen') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.dispatch.list', ['searching_for_deliverymen']) }}" title="{{ translate('messages.unassigned_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{translate('messages.unassigned_orders')}}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::SearchingForDeliveryman()->OrderScheduledIn(30)->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/dispatch/list/on_going') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.dispatch.list', ['on_going']) }}" title="{{ translate('messages.ongoingOrders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.ongoingOrders') }}
                                        <span class="badge badge-soft-light badge-pill ml-1">
                                            {{ \App\Models\Order::Ongoing()->OrderScheduledIn(30)->StoreOrder()->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            @if(\App\CentralLogics\Helpers::module_permission_check('urban_goodz_driver_payouts_view'))
                                <li class="nav-item {{ Request::is('admin/urban-goodz/driver-pricing*') || Request::is('admin/urban-goodz/driver-payouts*') || Request::is('admin/urban-goodz/driver-earnings*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.urban-goodz.driver-pricing.index') }}" title="{{ translate('Driver Pricing & Payouts') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Driver Pricing & Payouts') }}</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                    <!-- Order dispachment End-->
                    @endif
                    <!-- End Orders -->



                    <!-- Parcel Section -->
                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.parcel_section') }}">{{ translate('messages.parcel_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('parcel'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.parcel') }}">
                            <i class="tio-bus nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.parcel') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/parcel*') ? 'block' : 'none' }}">
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel/category') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.parcel.category.index') }}" title="{{ translate('messages.parcel_category') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">
                                        {{ translate('messages.parcel_category') }}
                                    </span>
                                </a>
                            </li>
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel/orders*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.parcel.orders', ['all']) }}" title="{{ translate('messages.parcel_orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">
                                        {{ translate('messages.parcel_orders') }}
                                    </span>
                                </a>
                            </li>

                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel/settings') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.parcel.settings') }}" title="{{ translate('messages.parcel_settings') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">
                                        {{ translate('messages.parcel_settings') }}
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    <!--End Parcel Section -->

                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.item_section') }}">{{ translate('messages.item_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    <!-- Category -->
                    @if (\App\CentralLogics\Helpers::module_permission_check('category'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/category*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.categories') }}">
                            <i class="tio-category nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.categories') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/category*') ? 'block' : 'none' }}">
                            <li class="nav-item @yield('main_category') {{ Request::is('admin/category/add') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.category.add') }}" title="{{ translate('messages.category') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.category') }}</span>
                                </a>
                            </li>

                            {{--
                            Sub-category and sub-sub-category used to be separate pages; category
                            management is now a single unified page (admin.category.add) that
                            handles the full hierarchy via the position param on store, so these
                            dedicated nav entries were left pointing at routes that no longer exist.
                            --}}
                            {{-- <li class="nav-item {{Request::is('admin/category/add-sub-sub-category')?'active':''}}">
                            <a class="nav-link " href="{{route('admin.category.add-sub-sub-category')}}" title="add new sub sub category">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">Sub-Sub-Category</span>
                            </a>
                        </li> --}}
                        <li class="nav-item {{ Request::is('admin/category/bulk-import') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.category.bulk-import') }}" title="{{ translate('messages.bulk_import') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.bulk_import') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/category/bulk-export') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.category.bulk-export-index') }}" title="{{ translate('messages.bulk_export') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.bulk_export') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                <!-- End Category -->

                <!-- Attributes -->
                @if (\App\CentralLogics\Helpers::module_permission_check('attribute'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/attribute*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.attribute.add-new') }}" title="{{ translate('messages.attributes') }}">
                        <i class="tio-apps nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.attributes') }}
                        </span>
                    </a>
                </li>
                @endif
                <!-- End Attributes -->

                <!-- Unit -->
                @if (\App\CentralLogics\Helpers::module_permission_check('unit'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/unit*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.unit.index') }}" title="{{ translate('messages.units') }}">
                        <i class="tio-ruler nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate text-capitalize">
                            {{ translate('messages.units') }}
                        </span>
                    </a>
                </li>
                @endif
                <!-- End Unit -->

                <!-- AddOn -->
                @if (\App\CentralLogics\Helpers::module_permission_check('addon'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/addon*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.addons') }}">
                        <i class="tio-add-circle-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.addons') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/addon*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/addon/add-new') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.addon.add-new') }}" title="{{ translate('messages.addon_list') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.list') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/addon/bulk-import') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.addon.bulk-import') }}" title="{{ translate('messages.bulk_import') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.bulk_import') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/addon/bulk-export') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.addon.bulk-export-index') }}" title="{{ translate('messages.bulk_export') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.bulk_export') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                <!-- End AddOn -->
                <!-- Food -->
                @if (\App\CentralLogics\Helpers::module_permission_check('item'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/item*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.items') }}">
                        <i class="tio-premium-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate text-capitalize">{{ translate('messages.items') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/item*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/item/add-new') || (Request::is('admin/item/edit/*') && strpos(request()->fullUrl(), 'product_gellary=1') !== false  )  ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.add-new') }}" title="{{ translate('messages.add_new') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.add_new') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/item/list') || (Request::is('admin/item/edit/*') && (strpos(request()->fullUrl(), 'temp_product=1') == false && strpos(request()->fullUrl(), 'product_gellary=1') == false  ) ) ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.list') }}" title="{{ translate('messages.food_list') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.list') }}</span>
                            </a>
                        </li>
                        {{-- @if (\App\CentralLogics\Helpers::get_mail_status('product_gallery')) --}}
                        <li class="nav-item {{  Request::is('admin/item/product-gallery') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.product_gallery') }}" title="{{ translate('messages.Product_Gallery') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.Product_Gallery') }}</span>
                            </a>
                        </li>
                        {{-- @endif --}}
                        @if (\App\CentralLogics\Helpers::get_mail_status('product_approval'))
                        <li class="nav-item {{ Request::is('admin/item/new/item/list') || (Request::is('admin/item/edit/*') && strpos(request()->fullUrl(), 'temp_product=1') !== false  ) ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.approval_list') }}" title="{{ translate('messages.New_Item_Request') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.New_Item_Request') }}</span>
                            </a>
                        </li>
                        @endif
                        <li class="nav-item {{ Request::is('admin/item/reviews') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.reviews') }}" title="{{ translate('messages.review_list') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.review') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/item/bulk-import') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.bulk-import') }}" title="{{ translate('messages.bulk_import') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.bulk_import') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/item/bulk-export') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.item.bulk-export-index') }}" title="{{ translate('messages.bulk_export') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.bulk_export') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                <!-- End Food -->

                    <!-- Store Store -->
                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.store_section') }}">{{ translate('messages.store_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('store'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store*') && !Request::is('admin/store/withdraw_list') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.stores') }}">
                            <i class="tio-filter-list nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.stores') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/store*') ? 'block' : 'none' }}">
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/add') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.add') }}" title="{{ translate('messages.add_store') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">
                                        {{ translate('messages.add_store') }}
                                    </span>
                                </a>
                            </li>

                            <li class="navbar-item {{ Request::is('admin/store/list') ||  Request::is('admin/store/view/*')  ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.list') }}" title="{{ translate('messages.stores_list') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.stores') }}
                                        {{ translate('list') }}</span>
                                </a>
                            </li>
                            <li class="navbar-item {{ Request::is('admin/store/pending-requests') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.pending-requests') }}" title="{{ translate('messages.pending_requests') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate text-capitalize">{{ translate('new_joining_requests') }}</span>
                                </a>
                            </li>

                            <li class="navbar-item {{ Request::is('admin/store/recommended-store') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.recommended_store') }}" title="{{ translate('messages.pending_requests') }}">
                                    <span class="tio-hot"></span>
                                    <span class="text-truncate text-capitalize">{{ translate('Recommended_Store') }}</span>
                                </a>
                            </li>


                            <li class="nav-item {{ Request::is('admin/store/bulk-import') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.store.bulk-import') }}" title="{{ translate('messages.bulk_import') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate text-capitalize">{{ translate('messages.bulk_import') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/store/bulk-export') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.store.bulk-export-index') }}" title="{{ translate('messages.bulk_export') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate text-capitalize">{{ translate('messages.bulk_export') }}</span>
                                </a>
                            </li>

                        </ul>
                    </li>
                    @endif
                    <!-- End Store -->
                <!-- DeliveryMan -->
                @if (\App\CentralLogics\Helpers::module_permission_check('deliveryman'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.deliveryman_section') }}">{{ translate('messages.deliveryman_management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/delivery-man/add') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.add') }}" title="{{ translate('messages.add_delivery_man') }}">
                        <i class="tio-running nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.add_delivery_man') }}
                        </span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/delivery-man/new') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link text-capitalize" href="{{ route('admin.users.delivery-man.new') }}" title="{{ translate('messages.new_joining_requests') }}">
                        <i class="tio-man nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.new_joining_requests') }}
                        </span>
                    </a>
                </li>


                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/delivery-man/list') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.list') }}" title="{{ translate('messages.deliveryman_list') }}">
                        <i class="tio-filter-list nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.deliveryman_list') }}
                        </span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/delivery-man/reviews/list') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.reviews.list') }}" title="{{ translate('messages.reviews') }}">
                        <i class="tio-star-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.reviews') }}
                        </span>
                    </a>
                </li>
                @endif
                <!-- End DeliveryMan -->

                <!-- Customer Section -->
                @if (\App\CentralLogics\Helpers::module_permission_check('customer_management'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.customer_section') }}">{{ translate('messages.customer_management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                <!-- Custommer -->

                <li class="navbar-vertical-aside-has-menu {{ (Request::is('admin/customer/list') || Request::is('admin/customer/view*')) ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.customer.list') }}" title="{{ translate('messages.customers') }}">
                        <i class="tio-poi-user nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.customers') }}
                        </span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/customer/wallet*') ? 'active' : '' }}">

                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.customer_wallet') }}">
                        <i class="tio-wallet nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate  text-capitalize">
                            {{ translate('messages.customer_wallet') }}
                        </span>
                    </a>

                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/customer/wallet*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/customer/wallet/add-fund') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.customer.wallet.add-fund') }}" title="{{ translate('messages.add_fund') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.add_fund') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/customer/wallet/report*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.customer.wallet.report') }}" title="{{ translate('messages.report') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.report') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/customer/loyalty-point*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link  nav-link-toggle" href="javascript:" title="{{ translate('messages.customer_loyalty_point') }}">
                        <i class="tio-medal nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate  text-capitalize">
                            {{ translate('messages.customer_loyalty_point') }}
                        </span>
                    </a>

                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/customer/loyalty-point*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/customer/loyalty-point/report*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.customer.loyalty-point.report') }}" title="{{ translate('messages.report') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.report') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- End Custommer -->
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/customer/subscribed') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.customer.subscribed') }}" title="{{translate('subscribed_emails')}}">
                        <i class="tio-email-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.subscribed_mail_list') }}
                        </span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/contact/contact-list') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.users.contact.contact-list') }}" title="{{ translate('messages.contact_messages') }}">
                        <span class="tio-message nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.contact_messages') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/customer/settings') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.customer.settings') }}" title="{{ translate('messages.Customer_settings') }}">
                        <i class="tio-settings nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.Customer_settings') }}
                        </span>
                    </a>
                </li>
                <li
                class="navbar-vertical-aside-has-menu {{ Request::is('admin/message/list') ? 'active' : '' }}">
                <a class="js-navbar-vertical-aside-menu-link nav-link"
                    href="{{ route('admin.message.list') }}"
                    title="{{ translate('messages.customer_chat') }}">
                    <i class="tio-chat nav-icon"></i>
                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                        {{ translate('messages.Customer_Chat') }}
                    </span>
                </a>
            </li>
                @endif
                <!-- End customer Section -->

                <!-- Business Section-->
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.business_section') }}">{{ translate('messages.business_management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                <!-- withdraw -->
                @if (\App\CentralLogics\Helpers::module_permission_check('withdraw_list'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/withdraw*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.withdraw_list') }}" title="{{ translate('messages.store_withdraws') }}">
                        <i class="tio-table nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.store_withdraws') }}</span>
                    </a>
                </li>
                @endif
                <!-- End withdraw -->
                <!-- account -->
                @if (\App\CentralLogics\Helpers::module_permission_check('collect_cash'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/account-transaction*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.transactions.account-transaction.index') }}" title="{{ translate('messages.collect_cash') }}">
                        <i class="tio-money nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.collect_cash') }}</span>
                    </a>
                </li>
                @endif
                <!-- End account -->

                <!-- provide_dm_earning -->
                @if (\App\CentralLogics\Helpers::module_permission_check('provide_dm_earning'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/provide-deliveryman-earnings*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.transactions.provide-deliveryman-earnings.index') }}" title="{{ translate('messages.deliverymen_earning_provide') }}">
                        <i class="tio-send nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.deliverymen_earning_provide') }}</span>
                    </a>
                </li>
                @endif
                <!-- End provide_dm_earning -->

                <!-- Business Settings -->
                @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.business_settings') }}">{{ translate('messages.business_settings') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/business-setup') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.business-setup') }}" title="{{ translate('messages.business_setup') }}">
                        <span class="tio-settings nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.business_setup') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{Request::is('admin/business-settings/social-media')?'active':''}}">
                    <a class="nav-link " href="{{route('admin.business-settings.social-media.index')}}" title="{{translate('messages.Social Media')}}">
                        <span class="tio-facebook nav-icon"></span>
                        <span class="text-truncate">{{translate('messages.Social Media')}}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/payment-method') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.third-party.payment-method') }}" title="{{ translate('messages.payment_methods') }}">
                        <span class="tio-atm nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.payment_methods') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/mail-config') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.third-party.mail-config') }}" title="{{ translate('messages.mail_config') }}">
                        <span class="tio-email nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.mail_config') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/sms-module') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.third-party.sms-module') }}" title="{{ translate('messages.sms_system_module') }}">
                        <span class="tio-message nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.sms_system_module') }}</span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/fcm-index') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.fcm-index') }}" title="{{ translate('messages.notification_settings') }}">
                        <span class="tio-notifications nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.notification_settings') }}</span>
                    </a>
                </li>

                @endif
                <!-- End Business Settings -->

                <!-- web & adpp Settings -->
                @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.business_settings') }}">{{ translate('messages.web_and_app_settings') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/app-settings*') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.app-settings') }}" title="{{ translate('messages.app_settings') }}">
                        <span class="tio-android nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.app_settings') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/landing-page-settings*') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.admin-landing-page-settings', 'index') }}" title="{{ translate('messages.landing_page_settings') }}">
                        <span class="tio-website nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.landing_page_settings') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/config*') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.third-party.config-setup') }}" title="{{ translate('messages.third_party_apis') }}">
                        <span class="tio-key nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.third_party_apis') }}</span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.pages_setup') }}">
                        <i class="tio-pages nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.pages_setup') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/business-settings/pages*') ? 'block' : 'none' }}">

                        <li class="nav-item {{ Request::is('admin/business-settings/pages/terms-and-conditions') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.terms-and-conditions') }}" title="{{ translate('messages.terms_and_condition') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.terms_and_condition') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/business-settings/pages/privacy-policy') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.privacy-policy') }}" title="{{ translate('messages.privacy_policy') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.privacy_policy') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/business-settings/pages/about-us') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.about-us') }}" title="{{ translate('messages.about_us') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.about_us') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/business-settings/pages/refund') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.refund') }}" title="{{ translate('messages.Refund Policy') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('Refund Policy') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/business-settings/pages/cancelation') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.cancelation') }}" title="{{ translate('messages.Cancelation Policy') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('Cancelation Policy') }}</span>
                            </a>
                        </li>


                        <li class="nav-item {{ Request::is('admin/business-settings/pages/shipping-policy') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.shipping-policy') }}" title="{{ translate('messages.shipping_policy') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('Shipping Policy') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/file-manager*') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.file-manager.index') }}" title="{{ translate('messages.gallery') }}">
                        <span class="tio-album nav-icon"></span>
                        <span class="text-truncate text-capitalize">{{ translate('messages.gallery') }}</span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{Request::is('admin/social-login/view')?'active':''}}">
                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{route('admin.social-login.view')}}">
                    <i class="tio-twitter nav-icon"></i>
                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                        {{translate('messages.social_login')}}
                    </span>
                </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/recaptcha*') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.business-settings.third-party.recaptcha_index') }}" title="{{ translate('messages.reCaptcha') }}">
                        <span class="tio-top-security-outlined nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.reCaptcha') }}</span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{Request::is('admin/business-settings/db-index')?'active':''}}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{route('admin.business-settings.db-index')}}" title="{{translate('messages.clean_database')}}">
                        <i class="tio-cloud nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{translate('messages.clean_database')}}
                        </span>
                    </a>
                </li>
                @endif
                <!-- End web & adpp Settings -->

                <!-- Report -->
                @if (\App\CentralLogics\Helpers::module_permission_check('report'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.report_and_analytics') }}">{{ translate('messages.report_and_analytics') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report/item-wise-report') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.transactions.report.item-wise-report') }}" title="{{ translate('messages.item_report') }}">
                        <span class="tio-chart-bar-1 nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.item_report') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report/stock-report') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.report.stock-report') }}" title="{{ translate('messages.limited_stock_item') }}">
                        <span class="tio-chart-bar-4 nav-icon"></span>
                        <span class="text-truncate text-capitalize">{{ translate('messages.limited_stock_item') }}</span>
                    </a>
                </li>


                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report/store-wise-report') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.transactions.report.store-summary-report') }}" title="{{ translate('messages.store_wise_report') }}">
                        <span class="tio-home nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.store_report') }}</span>
                    </a>
                </li>


                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report/order-report') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.transactions.report.order-report') }}" title="{{ translate('messages.order_report') }}">
                        <span class="tio-voice nav-icon"></span>
                        <span class="text-truncate text-capitalize">{{ translate('messages.order_report') }}</span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report/transaction-report') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.transactions.report.day-wise-report') }}" title="{{ translate('messages.transaction_report') }}">
                        <span class="tio-chart-pie-1 nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.transaction_report') }}</span>
                    </a>
                </li>


                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report/expense-report') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.transactions.report.expense-report') }}" title="{{ translate('messages.expense_report') }}">
                        <span class="tio-money nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.expense_report') }}</span>
                    </a>
                </li>

                @endif

                <!-- Employee-->

                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.employee_handle') }}">{{ translate('messages.employee') }}
                        {{ translate('management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                @if (\App\CentralLogics\Helpers::module_permission_check('custom_role'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/custom-role*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.custom-role.create') }}" title="{{ translate('messages.employee_Role') }}">
                        <i class="tio-incognito nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.employee_Role') }}</span>
                    </a>
                </li>
                @endif

                @if (\App\CentralLogics\Helpers::module_permission_check('employee'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/employee*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.Employee') }}">
                        <i class="tio-user nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.employees') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/employee*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/employee/add-new') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.employee.add-new') }}" title="{{ translate('messages.add_new_Employee') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.add_new') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/employee/list') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.employee.list') }}" title="{{ translate('messages.Employee_list') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.list') }}</span>
                            </a>
                        </li>

                    </ul>
                </li>
                @endif
                <!-- End Employee -->


                <li class="nav-item py-5">

                </li>


                    @includeIf('layouts.admin.partials._logout_modal')
                </ul>
            </div>
            <!-- End Content -->
        </div>
    </aside>
</div>

<div id="sidebarCompact" class="d-none">

</div>


@push('script_2')
<script>
    $(window).on('load' , function() {
        if($(".navbar-vertical-content li.active").length) {
            $('.navbar-vertical-content').animate({
                scrollTop: $(".navbar-vertical-content li.active").offset().top - 150
            }, 10);
        }
    });

    var $rows = $('#navbar-vertical-content li');
    $('#search-sidebar-menu').keyup(function() {
        var val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();

        $rows.show().filter(function() {
            var text = $(this).text().replace(/\s+/g, ' ').toLowerCase();
            return !~text.indexOf(val);
        }).hide();
    });
</script>
@endpush
