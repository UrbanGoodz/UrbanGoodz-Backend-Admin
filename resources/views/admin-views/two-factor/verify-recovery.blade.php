<!DOCTYPE html>
<html dir="{{ $site_direction ?? 'ltr' }}" lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>2FA Recovery | Urban Goodz</title>
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
                <h2 class="title">Urban Goodz <span class="d-block">Recovery Code</span></h2>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <div class="auth-header">
                    <div class="mb-5">
                        <h2 class="title">Use Recovery Code</h2>
                        <div>Enter one of your 10-character recovery codes.</div>
                    </div>
                </div>
                <form action="{{route('admin.two-factor.verify-recovery-submit')}}" method="post">
                    @csrf
                    <div class="js-form-message form-group">
                        <label class="input-label">Recovery Code</label>
                        <input type="text" class="form-control form-control-lg" name="recovery_code"
                               placeholder="XXXXX-XXXXX" maxlength="11" autocomplete="off" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-lg btn-block btn--primary mt-3">Verify</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="{{route('login', ['admin'])}}" class="text-primary">Back to login</a>
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
