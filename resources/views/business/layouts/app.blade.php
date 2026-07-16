<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ translate('Business Portal') }}</title>
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/fonts.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/vendor.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/vendor/icon-set/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}">
    <style>
        .navbar-business {
            background: var(--ug-white) !important;
            border-bottom: 1px solid rgba(0,0,0,.06) !important;
            box-shadow: 0 2px 8px rgba(0,0,0,.04) !important;
        }
        .navbar-business .navbar-brand {
            color: var(--ug-black) !important;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .navbar-business .navbar-brand strong {
            color: var(--ug-orange) !important;
        }
        .navbar-business .nav-link {
            color: var(--ug-black) !important;
            font-weight: 500;
        }
        .navbar-business .nav-link:hover,
        .navbar-business .nav-link.active {
            color: var(--ug-orange) !important;
        }
        .navbar-business .dropdown-item:hover {
            background: rgba(var(--ug-orange-rgb), .06) !important;
            color: var(--ug-orange) !important;
        }
        .navbar-business .client-name {
            color: var(--ug-black) !important;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .navbar-business .user-name {
            color: #6c757d !important;
            font-size: 0.85rem;
        }
        .navbar-business .dropdown-toggle::after {
            color: var(--ug-black) !important;
        }
    </style>
    @stack('css_or_js')
</head>
<body class="footer-offset">
    @if(Session::get('impersonation_active'))
    <div style="background: #dc3545; color: white; padding: 8px 16px; display: flex; align-items: center; justify-content: space-between; font-size: 14px; font-weight: 600;">
        <div>
            <i class="tio-shield-warning mr-1"></i>
            Admin Viewing Business: {{ App\Models\UrbanGoodzBusinessClient::find(Session::get('impersonation_client_id'))->name ?? 'Unknown' }}
            <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; margin-left: 8px; font-size: 12px;">
                {{ ucfirst(str_replace('_', ' ', Session::get('impersonation_mode', 'read_only'))) }} Mode
            </span>
        </div>
        <div>
            <a href="{{ route('business.dashboard') }}" style="color: white; margin-right: 12px;">Return to Admin</a>
            <form action="{{ route('admin.urban-goodz.business-clients.impersonation.exit') }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" style="background: white; color: #dc3545; border: none; padding: 4px 12px; border-radius: 4px; font-weight: 600; cursor: pointer;">
                    Exit Business View
                </button>
            </form>
        </div>
    </div>
    @endif
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

    <nav class="navbar navbar-expand-lg navbar-business">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('business.dashboard') }}">
                <strong>{{ translate('Urban Goodz') }}</strong> {{ translate('Business') }}
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#businessNavbar" style="border-color: rgba(0,0,0,.1);">
                <span class="navbar-toggler-icon" style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%280,0,0,0.5%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e&quot;);"></span>
            </button>
            <div class="collapse navbar-collapse" id="businessNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.dashboard') }}">{{ translate('Dashboard') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.routes.index') }}">{{ translate('Routes') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.packages.pool') }}">{{ translate('Packages') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.manifests.index') }}">{{ translate('Manifests') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.locations.index') }}">{{ translate('Locations') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.users.index') }}">{{ translate('Users') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.documents.index') }}">{{ translate('Documents') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('business.invoices.index') }}">{{ translate('Invoices') }}</a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-lg-center">
                    @php($businessClientUser = auth('business')->user())
                    @php($businessClient = $businessClientUser?->client)
                    @if($businessClient)
                    <li class="nav-item d-none d-lg-block me-3">
                        <span class="client-name">{{ $businessClient->company_name }}</span>
                    </li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-toggle="dropdown">
                            <span class="user-name d-none d-lg-inline">{{ $businessClientUser?->name ?? $businessClientUser?->email }}</span>
                            <span class="d-lg-none">{{ $businessClientUser?->name ?? $businessClientUser?->email }}</span>
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
