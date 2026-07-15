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
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}">
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
<body>
<main id="content" role="main" class="main">
    <div class="auth-wrapper">
        <div class="auth-wrapper-left">
            <div class="auth-left-cont">
                <h1 class="title" style="font-size: 2.2rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 0.25rem;">
                    <strong>{{ translate('Urban Goodz') }}</strong>
                </h1>
                <p style="font-size: 1.15rem; opacity: 0.9; margin-bottom: 0.5rem; font-weight: 500;">{{ translate('Business Portal') }}</p>
                <p class="brand-tagline">{{ translate('Your logistics command center.') }}</p>
                <ul class="brand-features">
                    <li>{{ translate('Manage dedicated routes and deliveries') }}</li>
                    <li>{{ translate('Track packages and scan barcodes') }}</li>
                    <li>{{ translate('Upload documents and view invoices') }}</li>
                    <li>{{ translate('Coordinate with dispatchers in real time') }}</li>
                </ul>
                <p class="brand-copyright">&copy; {{ date('Y') }} Urban Goodz Delivery</p>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <div class="d-sm-none flex-grow-1 mb-3 mobile-logo">
                    <strong style="font-size: 1.3rem; color: var(--ug-orange);">Urban Goodz</strong>
                </div>

                <form action="{{ route('business.login.submit') }}" method="post">
                    @csrf
                    <div class="auth-header">
                        <div class="mb-5">
                            <h2 class="title" style="color: var(--ug-black); font-weight: 700;">{{ translate('Welcome Back') }}</h2>
                            <div style="color: #6c757d;">{{ translate('Sign in to your business account') }}.</div>
                        </div>
                    </div>

                    @if(session('status'))
                        <div class="alert alert-success" role="alert" style="font-size: 0.9rem;">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="js-form-message form-group">
                        <label class="input-label" for="email" style="color: var(--ug-black);">{{ translate('Email') }}</label>
                        <input type="email" class="form-control form-control-lg" name="email" id="email"
                               placeholder="email@address.com" value="{{ old('email') }}" required autofocus>
                    </div>

                    <div class="js-form-message form-group">
                        <label class="input-label" for="password" style="color: var(--ug-black);">{{ translate('Password') }}</label>
                        <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                   name="password" id="password" placeholder="******" required
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

                    <div class="d-flex justify-content-between mt-5">
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

                    <button type="submit" class="btn btn-lg btn-block btn--primary mt-xxl-3">
                        {{ translate('Sign In') }}
                    </button>
                </form>
            </div>
        </div>
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
