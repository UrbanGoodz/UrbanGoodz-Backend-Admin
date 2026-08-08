<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ translate('Reset Password') }} - {{ translate('Urban Goodz Business Portal') }}</title>
    <link rel="shortcut icon" href="{{asset('public/favicon.ico')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/fonts.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/vendor.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/vendor/icon-set/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}?v={{ filemtime(public_path('assets/admin/css/ug-admin.css')) }}">
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
                <p style="color: rgba(255,255,255,.85);">{{ translate('Create a new password for your account.') }}</p>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <div class="auth-header">
                    <div class="mb-5">
                        <h2 class="title" style="color: var(--ug-black); font-weight: 700;">{{ translate('Reset Password') }}</h2>
                        <div style="color: #6c757d;">{{ translate('Enter your new password below') }}.</div>
                    </div>
                </div>

                <form action="{{ route('business.password.reset') }}" method="post">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">

                    <div class="js-form-message form-group">
                        <label class="input-label" for="password" style="color: var(--ug-black);">{{ translate('New Password') }}</label>
                        <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                   name="password" id="password" placeholder="Min. 8 characters" required
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

                    <div class="js-form-message form-group">
                        <label class="input-label" for="password_confirmation" style="color: var(--ug-black);">{{ translate('Confirm Password') }}</label>
                        <input type="password" class="form-control form-control-lg"
                               name="password_confirmation" id="password_confirmation"
                               placeholder="Confirm your password" required>
                    </div>

                    <button type="submit" class="btn btn-lg btn-block btn--primary mt-xxl-3">
                        {{ translate('Reset Password') }}
                    </button>

                    <div class="text-center mt-4">
                        <a href="{{ route('business.login') }}" style="color: var(--ug-orange); font-weight: 500;">
                            {{ translate('Back to Login') }}
                        </a>
                    </div>
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
