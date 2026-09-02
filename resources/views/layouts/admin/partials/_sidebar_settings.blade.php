<div id="sidebarMain" class="d-none">
    <aside
        class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <!-- Logo -->
                @php($store_logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
                <a class="navbar-brand" href="{{ route('admin.business-settings.business-setup') }}" aria-label="Front">
                    <img class="navbar-brand-logo initial--36 onerror-image onerror-image"
                        data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                        src="{{ \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value ?? '', $store_logo?->storage[0]?->value ?? 'public', 'favicon') }}"
                        alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image onerror-image"
                        data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                        src="{{ \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value ?? '', $store_logo?->storage[0]?->value ?? 'public', 'favicon') }}"
                        alt="Logo">
                </a>
                <!-- End Logo -->

                <!-- Navbar Vertical Toggle -->
                <button type="button"
                    class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
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
                <form autocomplete="off" class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input autocomplete="false" name="qq" type="text" class="form-control form--control"
                            placeholder="{{ translate('Search Menu...') }}" id="search">
                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>
                <ul class="navbar-nav navbar-nav-lg nav-tabs">

                    {{-- Every admin/urban-goodz* request resolves to module_type
                         "settings" (CurrentModule), so this is the sidebar the
                         Urban Goodz surfaces actually render. This whole block is
                         ported from layouts.admin.partials._sidebar.blade.php,
                         which contains the full Urban Goodz nav tree but is never
                         @include'd by anything - layouts.admin.app only includes
                         _sidebar_{module_type}, so that file's markup, permission
                         gates and route names never actually ran anywhere. Keep
                         this in sync with that file rather than @include-ing it
                         directly: it also carries ~24 dead route names pointing
                         at routes that were renamed/reorganized elsewhere in the
                         app (see routes/admin.php, routes/admin/routes.php). --}}
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
                    @php($ugModules = \App\Services\UrbanGoodzModuleStatusService::all())
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

                    <!-- Business Settings -->
                    <li class="nav-item">
                        <small class="nav-subtitle"
                            title="{{ translate('messages.business_settings') }}">{{ translate('messages.business_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>


                    @if (\App\CentralLogics\Helpers::module_permission_check('zone'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/zone*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('admin.business-settings.zone.home') }}"
                                title="{{ translate('messages.zone_setup') }}">
                                <i class="tio-city nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.zone_setup') }} </span>
                            </a>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/module') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                                href="javascript:" title="{{ translate('messages.system_module_setup') }}">
                                <i class="tio-globe nav-icon"></i>
                                <span class="text-truncate">{{ translate('messages.module_setup') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/module*') ? 'block' : 'none' }}">
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/module/store') ? 'active' : '' }}">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                        href="{{ route('admin.business-settings.module.create') }}"
                                        title="{{ translate('messages.add_Business_Module') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                            {{ translate('messages.add_Business_Module') }}
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="navbar-vertical-aside-has-menu @yield('edit_module')  {{ Request::is('admin/business-settings/module') ? 'active' : '' }}">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                        href="{{ route('admin.business-settings.module.index') }}"
                                        title="{{ translate('messages.modules') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                            {{ translate('messages.modules') }}
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/business-setup*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.business-setup') }}"
                                title="{{ translate('messages.business_setup') }}">
                                <span class="tio-settings nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.business_settings') }}</span>
                            </a>
                        </li>
                        @if (addon_published_status('TaxModule'))
                            <li class="navbar-vertical-aside-has-menu @yield('taxmodule')">

                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                                    href="javascript:" title="{{ translate('System_Tax') }}">
                                    <i class="tio-wallet nav-icon"></i>
                                    <span class="text-truncate">{{ translate('System_Tax') }}</span>
                                </a>


                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display: @yield('taxmoduleDisplay', 'none')">

                                    <li class="navbar-vertical-aside-has-menu @yield('tax_setup')">
                                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                                            href="{{ route('taxvat.index') }}" title="{{ translate('Create_Taxes') }}">
                                            <i class="tio-chart-line-up nav-icon"></i>
                                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                                {{ translate('Create_Taxes') }}
                                            </span>
                                        </a>
                                    </li>
                                    <li class="navbar-vertical-aside-has-menu @yield('tax_system_setup')">
                                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                                            href="{{ route('taxvat.systemTaxvat', ['type' => 'vendor']) }}"
                                            title="{{ translate('Setup_Taxes') }}">
                                            <i class="tio-calculator nav-icon"></i>
                                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                                {{ translate('Setup_Taxes') }}
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif
                    @endif








                    @if (\App\CentralLogics\Helpers::module_permission_check('subscription'))
                        <li class="navbar-vertical-aside-has-menu @yield('subscription')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                                href="javascript:" title="{{ translate('messages.subscription_management') }}">
                                <i class="tio-crown nav-icon"></i>
                                <span class="text-truncate">{{ translate('messages.subscription_management') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/subscription*') ? 'block' : 'none' }}">
                                <li class="navbar-vertical-aside-has-menu @yield('subscription_index')">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                        href="{{ route('admin.business-settings.subscriptionackage.index') }}"
                                        title="{{ translate('messages.subscription_Package') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                            {{ translate('messages.subscription_Package') }}
                                        </span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu  @yield('subscriberList')">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                        href="{{ route('admin.business-settings.subscriptionackage.subscriberList') }}"
                                        title="{{ translate('messages.Subscriber_List') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                            {{ translate('messages.Subscriber_List') }}
                                        </span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu  @yield('subscription_settings')">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                        href="{{ route('admin.business-settings.subscriptionackage.settings') }}"
                                        title="{{ translate('messages.settings') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                            {{ translate('messages.settings') }}
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.pages_setup') }}">
                                <i class="tio-pages nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.pages_&_social_media') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/pages*') ? 'block' : 'none' }}">

                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/social-media') ? 'active' : '' }}">
                                    <a class="nav-link "
                                        href="{{ route('admin.business-settings.social-media.index') }}"
                                        title="{{ translate('messages.Social Media') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.Social Media') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/admin-landing-page-settings*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                        href="{{ route('admin.business-settings.admin-landing-page-settings', 'setup') }}"
                                        title="{{ translate('messages.admin_landing_page_settings') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.admin_landing_page') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/react-landing-page-settings*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                        href="{{ route('admin.business-settings.react-landing-page-settings', 'header') }}"
                                        title="{{ translate('messages.react_landing_page') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.react_landing_page') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                        href="{{ route('admin.business-settings.flutter-landing-page-settings', 'fixed-data') }}"
                                        title="{{ translate('messages.flutter_landing_page') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.flutter_landing_page') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/business-page*') ? 'active' : '' }}">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle"
                                        href="javascript:" title="{{ translate('messages.business_pages') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.business_pages') }}</span>
                                    </a>
                                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                        style="display:{{ Request::is('admin/business-settings/pages/business-page*') ? 'block' : 'none' }}">
                                        <li
                                            class="nav-item {{ Request::is('admin/business-settings/pages/business-page/terms-and-conditions') ? 'active' : '' }}">
                                            <a class="nav-link "
                                                href="{{ route('admin.business-settings.terms-and-conditions') }}"
                                                title="{{ translate('messages.terms_and_condition') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span
                                                    class="text-truncate">{{ translate('messages.terms_and_condition') }}</span>
                                            </a>
                                        </li>

                                        <li
                                            class="nav-item {{ Request::is('admin/business-settings/pages/business-page/privacy-policy') ? 'active' : '' }}">
                                            <a class="nav-link "
                                                href="{{ route('admin.business-settings.privacy-policy') }}"
                                                title="{{ translate('messages.privacy_policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span
                                                    class="text-truncate">{{ translate('messages.privacy_policy') }}</span>
                                            </a>
                                        </li>

                                        <li
                                            class="nav-item {{ Request::is('admin/business-settings/pages/business-page/about-us') ? 'active' : '' }}">
                                            <a class="nav-link "
                                                href="{{ route('admin.business-settings.about-us') }}"
                                                title="{{ translate('messages.about_us') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span
                                                    class="text-truncate">{{ translate('messages.about_us') }}</span>
                                            </a>
                                        </li>
                                        <li
                                            class="nav-item {{ Request::is('admin/business-settings/pages/business-page/refund') ? 'active' : '' }}">
                                            <a class="nav-link " href="{{ route('admin.business-settings.refund') }}"
                                                title="{{ translate('messages.Refund Policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Refund Policy') }}</span>
                                            </a>
                                        </li>

                                        <li
                                            class="nav-item {{ Request::is('admin/business-settings/pages/business-page/cancelation') ? 'active' : '' }}">
                                            <a class="nav-link "
                                                href="{{ route('admin.business-settings.cancelation') }}"
                                                title="{{ translate('messages.Cancelation Policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span
                                                    class="text-truncate">{{ translate('Cancelation Policy') }}</span>
                                            </a>
                                        </li>


                                        <li
                                            class="nav-item {{ Request::is('admin/business-settings/pages/business-page/shipping-policy') ? 'active' : '' }}">
                                            <a class="nav-link "
                                                href="{{ route('admin.business-settings.shipping-policy') }}"
                                                title="{{ translate('messages.shipping_policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Shipping Policy') }}</span>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/file-manager*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.file-manager.index') }}"
                                title="{{ translate('messages.gallery') }}">
                                <span class="tio-album nav-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.gallery') }}</span>
                            </a>
                        </li>

                        {{--
                        RideShare addon nav (ride-fare penalty, safety precaution) - no
                        controller, route, or model for either exists anywhere in this
                        codebase, so both links 500'd the moment addon_published_status
                        ever returned true for 'RideShare'. Left disabled rather than
                        pointed at invented routes.
                        @if (addon_published_status('RideShare'))
                            <li class="nav-item">
                                <small class="nav-subtitle"
                                    title="{{ translate('messages.ride_share_settings') }}">{{ translate('messages.ride_share_settings') }}</small>
                                <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                            </li>
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/ride-share*') || Request::is('admin/business-settings/ride-fare*') || Request::is('admin/business-settings/rider') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.business-settings.ride-fare.penalty') }}"
                                title="{{ translate('messages.additional_settings') }}">
                                    <span class="tio-settings nav-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.additional_settings') }}</span>
                                </a>
                            </li>
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/safety-precaution/*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.business-settings.safety-precaution.index', SAFETY_ALERT) }}"
                                title="{{ translate('messages.Safety_&_Precaution') }}">
                                    <span class="tio-settings nav-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.Safety_&_Precaution') }}</span>
                                </a>
                            </li>
                        @endif
                        --}}

                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('messages.business_settings') }}">{{ translate('messages.system_management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/third-party*') || Request::is('admin/business-settings/fcm*') || Request::is('admin/business-settings/offline-payment*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.3rd_party_&_configurations') }}">
                                <span class="nav-icon tio-account-square-outlined"></span>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.3rd_party_&_configurations') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/third-party*') || Request::is('admin/business-settings/fcm*') || Request::is('admin/business-settings/login-url-setup*') || Request::is('admin/business-settings/offline-payment*')|| Request::is('admin/business-settings/marketing/*') || Request::is('admin/business-settings/open-ai') || Request::is('admin/business-settings/open-ai-settings') ? 'block' : 'none' }}">
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/third-party*') && !Request::is('admin/business-settings/third-party/payment-method*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                        href="{{ route('admin.business-settings.third-party.sms-module') }}"
                                        title="{{ translate('messages.3rd_party') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.3rd_party') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/fcm*') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.business-settings.fcm-index') }}"
                                        title="{{ translate('messages.firebase_notification') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.firebase_notification') }}</span>
                                    </a>
                                </li>

                                <li
                                        class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/third-party/payment-method') || Request::is('admin/business-settings/offline-payment') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.business-settings.third-party.payment-method') }}"
                                            title="{{ translate('Payment Setup') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate">{{ translate('Payment Setup') }}</span>
                                        </a>
                                </li>


                                <li class="nav-item @yield('analytics_Script')">
                                    <a class="nav-link " href="{{ route('admin.business-settings.marketing.analytic') }}"
                                        title="{{ translate('Analytics_Script') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Analytics_Script') }}</span>
                                    </a>
                                </li>

                                <li class="nav-item @yield('openAI')">
                                    <a class="nav-link " href="{{route('admin.business-settings.openAI')}}"
                                        title="{{ translate('AI_Setup') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('AI_Setup') }}</span>
                                    </a>
                                </li>


                            </ul>
                        </li>
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/language*') ?'active':'' }}">
                            <a class="nav-link " href="{{route('admin.business-settings.language.index')}}"
                                title="{{ translate('Language Setup') }}">
                                <span class="tio-keyboard nav-icon"></span>
                                <span class="text-truncate">{{ translate('Language Setup') }}</span>
                            </a>
                        </li>
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/login-settings*') || Request::is('admin/business-settings/login-url-setup*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.login-settings.index') }}"
                                title="{{ translate('messages.login_setup') }}">
                                <span class="tio-devices-apple nav-icon"></span>
                                <span
                                    class="text-truncate text-capitalize">{{ translate('messages.login_setup') }}</span>
                            </a>
                        </li>


                        @if (addon_published_status('Rental'))
                            <li
                                class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/rental-email-setup*') || Request::is('admin/business-settings/email-setup*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                                    href="javascript:" title="{{ translate('messages.email_setup') }}">
                                    <i class="tio-email nav-icon"></i>
                                    <span class="text-truncate">{{ translate('messages.email_setup') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display:{{ Request::is('admin/business-settings/rental-email-setup*') || Request::is('admin/business-settings/email-setup*') ? 'block' : 'none' }}">

                                    <li
                                        class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/email-setup*') ? 'active' : '' }}">
                                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                                            href="{{ route('admin.business-settings.email-setup', ['admin', 'forgot-password']) }}"
                                            title="{{ translate('messages.All_Modules') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">
                                                {{ translate('messages.All_Modules') }}
                                            </span>
                                        </a>
                                    </li>
                                    <li
                                        class="navbar-vertical-aside-has-menu  {{ Request::is('admin/business-settings/rental-email-setup*') ? 'active' : '' }}">
                                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                                            href="{{ route('admin.business-settings.rental-email-setup', ['admin', 'provider-registration']) }}"
                                            title="{{ translate('messages.Rental_Module') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">
                                                {{ translate('messages.Rental_Module') }}
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li
                                class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/email-setup*') ? 'active' : '' }}">
                                <a class="nav-link "
                                    href="{{ route('admin.business-settings.email-setup', ['admin', 'forgot-password']) }}"
                                    title="{{ translate('messages.email_template') }}">
                                    <span class="tio-email nav-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.email_template') }}</span>
                                </a>
                            </li>
                        @endif

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/page-meta-data*') || Request::is('admin/business-settings/login-url-setup*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.seo-settings.pageMetaData') }}"
                                title="{{ translate('messages.page_meta_data') }}">
                                <span class="tio-share-message nav-icon"></span>
                                <span
                                    class="text-truncate text-capitalize">{{ translate('messages.page_meta_data') }}</span>
                            </a>
                        </li>

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/app-settings*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.app-settings') }}"
                                title="{{ translate('messages.app_settings') }}">
                                <span class="tio-android nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.app_settings') }}</span>
                            </a>
                        </li>

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/websocket') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.websocket') }}"
                                title="{{ translate('messages.websocket') }}">
                                <span class="tio-link nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.websocket') }}</span>
                            </a>
                        </li>

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/addon-activation*') ? 'active' : '' }}">
                            <a class="nav-link "
                                href="{{ route('admin.business-settings.addon-activation.index') }}"
                                title="{{ translate('messages.Addon_Activation') }}">
                                <span class="tio-appointment nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.Addon_Activation') }}</span>
                            </a>
                        </li>


                        @if (addon_published_status('Rental'))
                            <li class="navbar-vertical-aside-has-menu @yield('notification_setup_type')">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                                    href="javascript:" title="{{ translate('messages.notification_setup') }}">
                                    <i class="tio-crown nav-icon"></i>
                                    <span class="text-truncate">{{ translate('messages.notification_setup') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display:{{ Request::is('admin/business-settings/notification-setup*') ? 'block' : 'none' }}">

                                    <li class="navbar-vertical-aside-has-menu @yield('notification_setup')">
                                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                                            href="{{ route('admin.business-settings.notification_setup') }}"
                                            title="{{ translate('messages.All_Modules') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">
                                                {{ translate('messages.All_Modules') }}
                                            </span>
                                        </a>
                                    </li>
                                    <li class="navbar-vertical-aside-has-menu  @yield('notification_setup_rental')">
                                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                                            href="{{ route('admin.business-settings.notification_setup', ['module' => 'rental']) }}"
                                            title="{{ translate('messages.Rental_Module') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">
                                                {{ translate('messages.Rental_Module') }}
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="navbar-vertical-aside-has-menu  @yield('notification_setup')">
                                <a class="nav-link "
                                    href="{{ route('admin.business-settings.notification_setup') }}"
                                    title="{{ translate('messages.Notification_Channels') }} ">
                                    <span class="tio-snooze-notification  nav-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.Notification_Channels') }}
                                    </span>
                                </a>
                            </li>
                        @endif




                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/db-index') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('admin.business-settings.db-index') }}"
                                title="{{ translate('messages.clean_database') }}">
                                <i class="tio-cloud nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.clean_database') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    <!-- Dashboards -->
                    <li
                        class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/system-addon') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                            href="{{ route('admin.business-settings.system-addon.index') }}"
                            title="{{ translate('system_addons') }}">
                            <i class="tio-add-circle-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('system_addons') }}
                            </span>
                        </a>
                    </li>
                    <!-- End Dashboards -->


                    @if (count(config('addon_admin_routes')) > 0)
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('messages.addon_menus') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('admin/payment/configuration/*') || Request::is('admin/sms/configuration/*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:">
                                <i class="tio-puzzle nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Addon Menus') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('admin/payment/configuration/*') || Request::is('admin/sms/configuration/*') ? 'block' : 'none' }}">
                                @foreach (config('addon_admin_routes') as $routes)
                                    @foreach ($routes as $route)
                                        <li
                                            class="navbar-vertical-aside-has-menu {{ Request::is($route['path']) ? 'active' : '' }}">
                                            <a class="js-navbar-vertical-aside-menu-link nav-link "
                                                href="{{ $route['url'] }}"
                                                title="{{ translate($route['name']) }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate($route['name']) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    <!--addon end-->
                    <!-- End web & adpp Settings -->

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

<script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/rental-sidebar.js') }}"></script>


@endpush
