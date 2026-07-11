<!DOCTYPE html>
<?php $log_email_succ = session()->get('log_email_succ'); ?>
<html dir="{{ $site_direction ?? 'ltr' }}" lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>2FA Verification | Urban Goodz</title>
    <link rel="shortcut icon" href="{{asset('public/favicon.ico')}}">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">
</head>
<body>
<main id="content" role="main" class="main">
    <div class="auth-wrapper">
        <div class="auth-wrapper-left">
            <div class="auth-left-cont">
                <img class="onerror-image" data-onerror-image="{{asset('/public/assets/admin/img/favicon.png')}}"
                     src="{{asset('/public/assets/admin/img/favicon.png')}}" alt="Urban Goodz">
                <h2 class="title">Urban Goodz <span class="d-block">Two-Factor Authentication</span></h2>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <div class="auth-header">
                    <div class="mb-5">
                        <h2 class="title">Verification Required</h2>
                        <div>Enter the 6-digit code from your authenticator app.</div>
                    </div>
                </div>
                <form action="{{route('admin.two-factor.verify-submit')}}" method="post">
                    @csrf
                    <div class="js-form-message form-group">
                        <label class="input-label">Authenticator Code</label>
                        <input type="text" class="form-control form-control-lg" name="otp_code"
                               placeholder="000000" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code"
                               required autofocus>
                    </div>
                    <button type="submit" class="btn btn-lg btn-block btn--primary mt-3">Verify</button>
                </form>
                <div class="mt-4 text-center">
                    <span class="text-muted">Lost your device?</span>
                    <a href="{{route('admin.two-factor.verify-recovery')}}" class="text-primary">Use a recovery code</a>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
{!! Toastr::message() !!}
@if ($errors->any())
    <script>
        "use strict";
        @foreach($errors->all() as $error)
        toastr.error('{{translate($error)}}', 'Error', { CloseButton: true, ProgressBar: true });
        @endforeach
    </script>
@endif
</body>
</html>
