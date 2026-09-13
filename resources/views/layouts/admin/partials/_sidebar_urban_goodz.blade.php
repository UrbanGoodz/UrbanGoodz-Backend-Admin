{{--
    The Urban Goodz navigation, shared by every admin sidebar.

    It used to live only inside _sidebar.blade.php. app.blade.php picks a
    module-specific partial whenever one exists for the current $module_type,
    so as soon as an admin had a module selected - Demo Module, Restaurants,
    Grocery, any of them - they got _sidebar_<module>.blade.php, which carried
    no Urban Goodz links at all. An admin holding urban_goodz_view then had no
    way to reach Control Center, AI Ops Copilot, Load Board or anything else
    under it except by typing the URL.

    Every gate inside is unchanged, so this grants nothing: an admin without
    the matching module permission still sees nothing here.
--}}
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
