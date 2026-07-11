@extends('layouts.admin.app')
@section('title', 'Setup Two-Factor Authentication')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm">
                <h1 class="page-header-title">Setup Two-Factor Authentication</h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Step 1: Scan QR Code</h4>
                    <p>Open your authenticator app (Google Authenticator, Authy, etc.) and scan this QR code.</p>

                    <div class="text-center my-4">
                        <div id="qrcode" class="d-inline-block p-3 bg-white border rounded"></div>
                    </div>

                    <div class="mt-3 mb-4">
                        <p class="text-muted mb-1"><strong>Can't scan? Enter this key manually:</strong></p>
                        <code class="d-block p-2 bg-light rounded text-break">{{ $secret }}</code>
                    </div>

                    <hr>

                    <h4 class="card-title">Step 2: Enter Verification Code</h4>
                    <p>Enter the 6-digit code from your authenticator app to confirm setup.</p>

                    <form action="{{ route('admin.two-factor.confirm') }}" method="post" class="mt-3">
                        @csrf
                        <div class="form-group">
                            <input type="text" class="form-control form-control-lg" name="otp_code"
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                                   autocomplete="one-time-code" required autofocus style="max-width:200px;text-align:center;font-size:1.5rem;letter-spacing:0.5rem;">
                        </div>
                        <button type="submit" class="btn btn--primary">Confirm & Enable</button>
                        <a href="{{ route('admin.two-factor.index') }}" class="btn btn-outline-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById("qrcode"), {
        text: "{{ $qrCodeUri }}",
        width: 200,
        height: 200
    });
</script>
@endpush
