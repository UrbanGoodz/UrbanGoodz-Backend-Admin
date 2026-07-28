<!DOCTYPE html>
<?php

    $log_email_succ = session()->get('log_email_succ');
?>

<html dir="{{ $site_direction }}" lang="{{ $locale }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <!-- Required Meta Tags Always Come First -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Title -->
    <title>{{ config('urban_goodz.brand_name', 'Urban Goodz') }} | {{translate('messages.login')}}</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('public/assets/admin/svg/logos/urban-goodz.svg') }}">

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <!-- CSS Implementing Plugins -->
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    <!-- CSS Front Template -->
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/ug-admin.css')}}">
</head>

<body class="ug-command-page">
<!-- ========== MAIN CONTENT ========== -->
<main id="content" role="main" class="main">
    <div class="ug-command-login ug-command-login--admin">
        <aside class="ug-command-visual" aria-label="Urban Goodz Admin Command Center">
            <img src="{{ asset('public/assets/admin/img/admin-command-center-reference.png') }}"
                alt="Urban Goodz Admin Command Center powering commerce, delivery, logistics, and growth">
        </aside>
        <section class="ug-command-auth">
            <div class="ug-command-card">
                <div class="ug-command-mobile-brand">
                    <img src="{{ asset('public/assets/admin/svg/logos/urban-goodz.svg') }}" alt="Urban Goodz">
                </div>
                <header class="ug-command-header">
                    <h1>Admin Login</h1>
                    <p>Welcome back. Sign in to manage the Urban Goodz network.</p>
                </header>

                <form action="{{route('login_post')}}" method="post" id="form-id">
                    @csrf
                    <input type="hidden" name="role" value="{{  $role ?? null }}">
                    <div class="js-form-message form-group ug-command-group">
                        <label class="input-label" for="signinSrEmail">Email Address</label>
                        <div class="ug-command-field">
                            <span class="ug-command-field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M3 5h18v14H3V5Zm1.5 1.5 7.5 6 7.5-6M4.5 17.5l5.4-5m9.6 5-5.4-5"/></svg>
                            </span>
                        <input type="email" class="form-control form-control-lg" name="email" id="signinSrEmail"
                                tabindex="1" placeholder="Enter your email" value="{{ $email ?? '' }}" aria-label="Enter your email"
                                required data-msg="{{ translate('Please_enter_a_valid_email_address.') }}">
                        </div>
                    </div>

                    <div class="js-form-message form-group ug-command-group">
                        <label class="input-label" for="signupSrPassword" tabindex="0">
                            Password
                        </label>

                        <div class="input-group input-group-merge ug-command-field">
                            <span class="ug-command-field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M7 10V8a5 5 0 0 1 10 0v2m-12 0h14v10H5V10Zm7 4v3"/></svg>
                            </span>
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                    name="password" id="signupSrPassword" placeholder="Enter your password" value="{{ $password ?? '' }}"
                                    aria-label="Enter your password" required
                                    data-msg="{{translate('messages.invalid_password_warning')}}"
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

                    <div class="ug-command-captcha">
                        <label class="input-label" for="custome_recaptcha">Security Verification</label>
                        @include('admin-views.partials._recaptcha')
                    </div>

                    <div class="ug-command-options">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="termsCheckbox" {{ $password ? 'checked' : '' }}
                                        name="remember">
                                <label class="custom-control-label text-muted" for="termsCheckbox">
                                    {{translate('messages.remember_me')}}
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="forget-password" style="display: {{ $role == 'admin' ? '' : 'none' }};">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" class="text-primary text-hover--primary" data-target="#forgetPassModal">{{ translate('Forget Password') }}?</span>
                            </div>
                        </div>
                        <div class="form-group" id="forget-password1" style="display: {{ $role == 'vendor' ? '' : 'none' }};">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" class="text-primary text-hover--primary" data-target="#forgetPassModal1">{{ translate('messages.Forget Password') }}?</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-lg btn-block btn--primary ug-command-submit" id="signInBtn">SIGN IN</button>
                </form>

                <div class="ug-command-notice">
                    <span class="ug-command-notice-icon" aria-hidden="true">⌾</span>
                    <span>Secure access&nbsp; • &nbsp;Authorized personnel only</span>
                </div>
                <p class="ug-command-support">
                    <span aria-hidden="true">◉</span>
                    Need help? Contact <a href="{{ url('/contact-us') }}">Urban Goodz Support</a>
                </p>

                @if(getEnvMode() == 'demo')
                @if (isset($role) && $role == 'admin')
                <div class="auto-fill-data-copy">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div>
                            <span class="d-block"><strong>Email</strong> : admin@admin.com</span>
                            <span class="d-block"><strong>Password</strong> : 12345678</span>
                        </div>
                        <div>
                            <button class="btn action-btn btn--primary m-0 copy_cred"><i class="tio-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @endif
                @if (isset($role) && $role == 'vendor')
                <div class="auto-fill-data-copy">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div>
                            <span class="d-block"><strong>Email</strong> : test.restaurant@gmail.com</span>
                            <span class="d-block"><strong>Password</strong> : 12345678</span>
                        </div>
                        <div>
                            <button class="btn action-btn btn--primary m-0 copy_cred2"><i class="tio-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @endif
                @endif
            </div>
        </section>
    </div>
</main>
<!-- ========== END MAIN CONTENT ========== -->
<div class="modal fade" id="forgetPassModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header justify-content-end">
        <span type="button" class="close-modal-icon" data-dismiss="modal">
            <i class="tio-clear"></i>
        </span>
      </div>
      <div class="modal-body">
        <div class="forget-pass-content">
            <img src="{{asset('/public/assets/admin/img/send-mail.svg')}}" alt="">
            <!-- After Succeed -->
            <h4>
                {{ translate('Send_Mail_to_Your_Email') }} ?
            </h4>
            <p>
                {{ translate('A mail will be send to your registered email') }} {{ isset($role) && $role == 'admin'  ? \App\Models\Admin::where('role_id',1)->first()?->masked_email : ''  }} {{ translate('with a  link to change passowrd') }}
            </p>
            <a class="btn btn-lg btn-block btn--primary mt-3" href="{{route('reset-password')}}">
                {{ translate('Send Mail') }}
            </a>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="forgetPassModal1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header justify-content-end">
        <span type="button" class="close-modal-icon" data-dismiss="modal">
            <i class="tio-clear"></i>
        </span>
      </div>
      <div class="modal-body">
        <div class="forget-pass-content">
            <img src="{{asset('/public/assets/admin/img/send-mail.svg')}}" alt="">
            <!-- After Succeed -->
            <!-- <img src="{{asset('/public/assets/admin/img/sent-mail.svg')}}" alt=""> -->
            <h4>
                {{ translate('messages.Send_Mail_to_Your_Email') }} ?
            </h4>
            <form class="" action="{{ route('vendor-reset-password') }}" method="post">
                @csrf

                <input type="email" name="email" id="" class="form-control" placeholder="{{ translate('messages.plesae_enter_your_registerd_email') }}" required>
                <button type="submit" class="btn btn-lg btn-block btn--primary mt-3">{{ translate('messages.Send Mail') }}</button>
            </form>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="successMailModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header justify-content-end">
          <span type="button" class="close-modal-icon" data-dismiss="modal">
              <i class="tio-clear"></i>
          </span>
        </div>
        <div class="modal-body">
          <div class="forget-pass-content">
              <!-- After Succeed -->
              <img src="{{asset('/public/assets/admin/img/sent-mail.svg')}}" alt="">
              <h4>
                {{ translate('A mail has been sent to your registered email') }}!
              </h4>
              <p>
                {{ translate('Click the link in the mail description to change password') }}
              </p>
              <button class="btn btn-lg btn-block btn--primary mt-3" data-dismiss="modal">
                {{ translate('Got_It') }}
              </button>
          </div>
        </div>
      </div>
    </div>
  </div>
<!-- JS Implementing Plugins -->
<script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>

<!-- JS Front -->
<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        "use strict";
        @foreach($errors->all() as $error)
        toastr.error('{{translate($error)}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif
@if ($log_email_succ)
@php(session()->forget('log_email_succ'))
    <script>
        "use strict";
        $('#successMailModal').modal('show');
    </script>
@endif

<script>
    "use strict";
    // $("#forget-password").hide();
        $("#role-select").change(function() {
            var selectValue = $(this).val();
            if (selectValue == "admin") {
            $("#forget-password").show();
            $("#forget-password1").hide();
            } else if(selectValue == "vendor") {
            $("#forget-password").hide();
            $("#forget-password1").show();
            }
            else {
            $("#forget-password").hide();
            $("#forget-password1").hide();
            }
        });

    $(document).on('ready', function () {
        // INITIALIZATION OF SHOW PASSWORD
        // =======================================================
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init()
        });

        // INITIALIZATION OF FORM VALIDATION
        // =======================================================
        $('.js-validate').each(function () {
            $.HSCore.components.HSValidation.init($(this));
        });
    });
    $(document).ready(function() {
        $('.onerror-image').on('error', function() {
            let img = $(this).data('onerror-image')
            $(this).attr('src', img);
        });
    });
</script>

<script>
    $(document).on('click', '.reloadCaptcha', function () {
        $.ajax({
            url: "{{ route('reload-captcha') }}",
            type: "GET",
            dataType: 'json',
            beforeSend: function () {
                $('#loading').show()
                $('.capcha-spin').addClass('active')
            },
            success: function (data) {
                $('#reload-captcha').html(data.view);
            },
            complete: function () {
                $('#loading').hide()
                $('.capcha-spin').removeClass('active')
            }
        });
    });

</script>

@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script src="https://www.google.com/recaptcha/api.js?render={{$recaptcha['site_key']}}"></script>
    <script>
        $(document).ready(function () {
            var _ugRecaptchaReady = false;
            var _ugRecaptchaFailed = false;

            function _ugSwitchToCustomCaptcha(reason) {
                if (_ugRecaptchaFailed) return;
                _ugRecaptchaFailed = true;
                $('#reload-captcha').show();
                $('#set_default_captcha_value').val('1');
                if (reason) {
                    toastr.warning(reason + ' Using image captcha instead.');
                }
            }

            try {
                if (typeof grecaptcha === 'undefined') {
                    _ugSwitchToCustomCaptcha('Google reCAPTCHA could not load.');
                } else {
                    grecaptcha.ready(function () {
                        grecaptcha.execute('{{$recaptcha["site_key"]}}', { action: 'ready' }).then(function () {
                            _ugRecaptchaReady = true;
                        }).catch(function () {
                            _ugSwitchToCustomCaptcha('Google reCAPTCHA failed self-test.');
                        });
                    });
                    setTimeout(function () {
                        if (!_ugRecaptchaReady && !_ugRecaptchaFailed) {
                            _ugSwitchToCustomCaptcha('Google reCAPTCHA timed out.');
                        }
                    }, 5000);
                }
            } catch (e) {
                _ugSwitchToCustomCaptcha('Google reCAPTCHA initialization error.');
            }

            $('#signInBtn').click(function (e) {
                if ($('#set_default_captcha_value').val() == 1) {
                    $('#form-id').submit();
                    return true;
                }
                e.preventDefault();
                if (_ugRecaptchaFailed || typeof grecaptcha === 'undefined') {
                    _ugSwitchToCustomCaptcha('');
                    return;
                }
                grecaptcha.ready(function () {
                    grecaptcha.execute('{{$recaptcha["site_key"]}}', { action: 'submit' }).then(function (token) {
                        $('#g-recaptcha-response').val(token);
                        $('#form-id').submit();
                    }).catch(function () {
                        _ugSwitchToCustomCaptcha('Google reCAPTCHA token request failed.');
                    });
                });
            });
        });
    </script>
@endif
{{-- recaptcha scripts end --}}




@if(getEnvMode()=='demo')
    <script>
        "use strict";
        $('.copy_cred').on('click', function () {
            $('#signinSrEmail').val('admin@admin.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
        $('.copy_cred2').on('click', function () {
            $('#signinSrEmail').val('test.restaurant@gmail.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
    </script>
@endif

<!-- IE Support -->
<script>
    if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) document.write('<script src="{{asset('public//assets/admin')}}/vendor/babel-polyfill/polyfill.min.js"><\/script>');
</script>
</body>
</html>
