<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ translate('Urban Goodz Business Portal') }}</title>
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/fonts.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/vendor.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/vendor/icon-set/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}">
</head>
<body>
<main id="content" role="main" class="main">
    <div class="auth-wrapper">
        <div class="auth-wrapper-left">
            <div class="auth-left-cont">
                <h1 class="title" style="font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 0.25rem;">
                    <strong>{{ translate('Urban Goodz') }}</strong>
                </h1>
                <p style="font-size: 1.1rem; opacity: 0.8; margin-bottom: 2rem;">{{ translate('Business Portal') }}</p>
                <p>{{ translate('Manage routes, locations, documents, users, and service requests from one secure workspace.') }}</p>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <form action="{{ route('business.login.submit') }}" method="post">
                    @csrf
                    <div class="auth-header">
                        <div class="mb-5">
                            <h2 class="title" style="color: var(--ug-black); font-weight: 700;">{{ translate('Welcome Back') }}</h2>
                            <div style="color: #6c757d;">{{ translate('Sign in to your business account') }}.</div>
                        </div>
                    </div>

                    <div class="js-form-message form-group">
                        <label class="input-label" for="email" style="color: var(--ug-black);">{{ translate('Email') }}</label>
                        <input type="email" class="form-control form-control-lg" name="email" id="email"
                               placeholder="email@address.com" value="{{ old('email') }}" required>
                    </div>

                    <div class="js-form-message form-group">
                        <label class="input-label" for="password" style="color: var(--ug-black);">{{ translate('Password') }}</label>
                        <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                   name="password" id="password" placeholder="******" required>
                            <div class="input-group-append">
                                <a class="input-group-text" href="javascript:">
                                    <i class="tio-visible-outlined"></i>
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
</body>
</html>
