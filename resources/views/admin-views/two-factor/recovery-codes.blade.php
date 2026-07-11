<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>2FA Enabled - Save Recovery Codes | Urban Goodz</title>
    <link rel="shortcut icon" href="{{asset('public/favicon.ico')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="tio-checkmark-circle text-success" style="font-size:4rem;"></i>
                    </div>
                    <h2>Two-Factor Authentication Enabled</h2>
                    <p class="text-muted mb-4">Save these recovery codes in a safe place. Each code can only be used once.</p>

                    <div class="bg-light rounded p-4 mb-4 text-left">
                        <div class="row">
                            @foreach($recoveryCodes as $code)
                                <div class="col-sm-6 mb-2">
                                    <code class="d-block p-2 bg-white border rounded text-center" style="font-size:1.1rem;letter-spacing:0.1rem;">{{ $code }}</code>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Warning:</strong> These codes will not be shown again. Store them somewhere safe.
                    </div>

                    <a href="{{ route('admin.two-factor.index') }}" class="btn btn--primary btn-lg">Done</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
