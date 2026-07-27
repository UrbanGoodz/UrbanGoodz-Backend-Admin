<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ translate('Dispatcher Portal') }}</title>
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/fonts.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/vendor.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/vendor/icon-set/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}">
    <style>
        .navbar-dispatcher {
            background: #1a1a2e !important;
            border-bottom: 2px solid #e94560 !important;
            box-shadow: 0 2px 8px rgba(0,0,0,.12) !important;
        }
        .navbar-dispatcher .navbar-brand {
            color: #fff !important;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .navbar-dispatcher .navbar-brand strong {
            color: #e94560 !important;
        }
        .navbar-dispatcher .nav-link {
            color: rgba(255,255,255,.8) !important;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .navbar-dispatcher .nav-link:hover,
        .navbar-dispatcher .nav-link.active {
            color: #e94560 !important;
        }
        .navbar-dispatcher .dropdown-item {
            color: #333 !important;
        }
        .navbar-dispatcher .dropdown-item:hover {
            background: rgba(233,69,96,.08) !important;
            color: #e94560 !important;
        }
        .navbar-dispatcher .client-name {
            color: #fff !important;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .navbar-dispatcher .user-name {
            color: rgba(255,255,255,.7) !important;
            font-size: 0.85rem;
        }
        .dispatch-stat-card {
            border-left: 4px solid #e94560;
            border-radius: 8px;
        }
        .dispatch-stat-card.stat-available { border-left-color: #28a745; }
        .dispatch-stat-card.stat-assigned { border-left-color: #ffc107; }
        .dispatch-stat-card.stat-transit { border-left-color: #17a2b8; }
        .dispatch-stat-card.stat-commission { border-left-color: #e94560; }
        .workspace-badge {
            background: #e94560;
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
    </style>
    @stack('css_or_js')
</head>
<body class="footer-offset">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div id="loading" class="initial-hidden">
                    <div class="loader--inner">
                        <img width="80" src="{{asset('public/assets/admin/img/loader.gif')}}" alt="image">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dispatcher">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('business.dispatcher.dashboard') }}">
                <strong>{{ translate('Urban Goodz') }}</strong> {{ translate('Dispatcher') }}
                <span class="workspace-badge ms-2">{{ translate('WORKSPACE') }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#dispatcherNavbar" style="border-color: rgba(255,255,255,.2);">
                <span class="navbar-toggler-icon" style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255,255,255,0.5%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e&quot;);"></span>
            </button>
            <div class="collapse navbar-collapse" id="dispatcherNavbar">
                <ul class="navbar-nav me-auto">
                    @php($user = auth('business')->user())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('business.dispatcher.dashboard') ? 'active' : '' }}" href="{{ route('business.dispatcher.dashboard') }}">{{ translate('Dashboard') }}</a>
                    </li>
                    @if($user->hasDispatchPermission('dispatch_loads_view'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('business.dispatcher.loads.*') ? 'active' : '' }}" href="{{ route('business.dispatcher.loads') }}">{{ translate('Loads') }}</a>
                    </li>
                    @endif
                    @if($user->hasDispatchPermission('dispatch_drivers_view'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('business.dispatcher.drivers') ? 'active' : '' }}" href="{{ route('business.dispatcher.drivers') }}">{{ translate('Drivers') }}</a>
                    </li>
                    @endif
                    @if($user->hasDispatchPermission('dispatch_commissions_view'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('business.dispatcher.commissions') ? 'active' : '' }}" href="{{ route('business.dispatcher.commissions') }}">{{ translate('Commissions') }}</a>
                    </li>
                    @endif
                    @if($user->hasDispatchPermission('dispatch_territory_manage'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('business.dispatcher.territory') ? 'active' : '' }}" href="{{ route('business.dispatcher.territory') }}">{{ translate('Territory') }}</a>
                    </li>
                    @endif
                    @if($user->hasDispatchPermission('dispatch_users_manage'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('business.dispatcher.users.*') ? 'active' : '' }}" href="{{ route('business.dispatcher.users') }}">{{ translate('Team') }}</a>
                    </li>
                    @endif
                </ul>
                <ul class="navbar-nav align-items-lg-center">
                    @php($businessClient = $user?->client)
                    @if($businessClient)
                    <li class="nav-item d-none d-lg-block me-3">
                        <span class="client-name">{{ $businessClient->company_name }}</span>
                    </li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-toggle="dropdown">
                            <span class="user-name d-none d-lg-inline">{{ $user->name }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{ route('business.profile') }}">{{ translate('Profile') }}</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                {{ translate('Logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('business.logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="content" role="main" class="main pointer-event">
        <div class="content container-fluid">
            @yield('content')
        </div>
    </main>

    <script src="{{asset('public/assets/admin/js/vendor.min.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/theme.min.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/toastr.js')}}"></script>
    {!! Toastr::message() !!}
    @stack('script')
</body>
</html>
