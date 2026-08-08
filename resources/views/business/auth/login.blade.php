<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ translate('Urban Goodz Business Portal') }}</title>
    <link rel="shortcut icon" href="{{asset('public/favicon.ico')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/fonts.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/vendor.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/vendor/icon-set/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}?v={{ filemtime(public_path('assets/admin/css/ug-admin.css')) }}">
    <style>
        .auth-wrapper-left .auth-left-cont .brand-tagline {
            font-size: 1rem;
            color: rgba(255,255,255,.75);
            margin-top: 0.5rem;
            line-height: 1.6;
        }
        .auth-wrapper-left .auth-left-cont .brand-features {
            list-style: none;
            padding: 0;
            margin-top: 1.5rem;
        }
        .auth-wrapper-left .auth-left-cont .brand-features li {
            color: rgba(255,255,255,.85);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            padding-left: 1.25rem;
            position: relative;
        }
        .auth-wrapper-left .auth-left-cont .brand-features li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.45rem;
            width: 8px;
            height: 8px;
            background: var(--ug-dijon);
            border-radius: 50%;
        }
        .mobile-logo {
            display: none;
        }
        @media (max-width: 1300px) {
            .mobile-logo {
                display: block;
                margin-bottom: 1.5rem;
            }
            .mobile-logo img {
                max-height: 36px;
            }
        }
        .brand-copyright {
            font-size: 0.8rem;
            color: rgba(255,255,255,.5);
            margin-top: 2rem;
        }
    </style>
</head>
<body class="ug-command-page">
<main id="content" role="main" class="main">
    <div class="ug-command-login ug-command-login--business">
        <aside class="ug-command-visual" aria-label="Urban Goodz Business Operations Hub">
            <img src="{{ asset('public/assets/admin/img/business-operations-hub-reference.png') }}"
                alt="Urban Goodz Business Operations Hub for locations, courier routes, and package management">
        </aside>
        <section class="ug-command-auth">
            <div class="ug-command-card">
                <div class="ug-command-mobile-brand">
                    <img src="{{ asset('public/assets/admin/svg/logos/urban-goodz.svg') }}" alt="Urban Goodz">
                </div>
                <header class="ug-command-header">
                    <h1>Business Portal Login</h1>
                    <p>Welcome back. Sign in to manage your Urban Goodz business operations.</p>
                </header>

                <form action="{{ route('business.login.submit') }}" method="post">
                    @csrf

                    @if(session('status'))
                        <div class="alert alert-success" role="alert" style="font-size: 0.9rem;">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="js-form-message form-group ug-command-group">
                        <label class="input-label" for="email">Business Email</label>
                        <div class="ug-command-field">
                            <span class="ug-command-field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M3 5h18v14H3V5Zm1.5 1.5 7.5 6 7.5-6M4.5 17.5l5.4-5m9.6 5-5.4-5"/></svg>
                        <input type="email" class="form-control form-control-lg" name="email" id="email"
                               placeholder="Enter your business email" value="{{ old('email') }}" required autofocus>
                        </div>
                    </div>

                    <div class="js-form-message form-group ug-command-group">
                        <label class="input-label" for="password">Password</label>
                        <div class="input-group input-group-merge ug-command-field">
                            <span class="ug-command-field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M7 10V8a5 5 0 0 1 10 0v2m-12 0h14v10H5V10Zm7 4v3"/></svg>
                            </span>
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                   name="password" id="password" placeholder="Enter your password" required
                                   data-hs-toggle-password-options='{
                                       "target": "#changePassTarget",
                                       "defaultClass": "tio-hidden-outlined",
                                       "showClass": "tio-visible-outlined",
                                       "classChangeTarget": "#changePassIcon"
                                   }'>
                            <div id="changePassTarget" class="input-group-append">
                                <a class="input-group-text" href="javascript:">
                                    <i id="changePassIcon" class="tio-visible-outlined"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="ug-command-options">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="remember" name="remember">
                                <label class="custom-control-label text-muted" for="remember">
                                    {{ translate('Remember me') }}
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <a href="{{ route('business.password.request') }}" style="color: var(--ug-orange); font-weight: 500; font-size: 0.9rem;">
                                {{ translate('Forgot Password') }}?
                            </a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-lg btn-block btn--primary ug-command-submit">
                        SIGN IN
                    </button>
                </form>

                <div class="ug-command-notice">
                    <span class="ug-command-notice-icon" aria-hidden="true">⌾</span>
                    <span>Secure business access&nbsp; • &nbsp;Authorized users only</span>
                </div>
                <p class="ug-command-support">
                    <span aria-hidden="true">◉</span>
                    Need help? Contact <a href="{{ url('/contact-us') }}">Urban Goodz Business Support</a>
                </p>
            </div>
        </section>
    </div>
</main>
<script src="{{asset('public/assets/admin/js/vendor.min.js')}}"></script>
<script src="{{asset('public/assets/admin/js/theme.min.js')}}"></script>
<script src="{{asset('public/assets/admin/js/toastr.js')}}"></script>
{!! Toastr::message() !!}
@if ($errors->any())
    <script>
        @foreach($errors->all() as $error)
        toastr.error('{{ $error }}', 'Error', { CloseButton: true, ProgressBar: true });
        @endforeach
    </script>
@endif
<script>
    $(document).on('ready', function () {
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init()
        });
    });
</script>
</body>
</html>
