@extends('layouts.admin.app')
@section('title', 'Recovery Codes')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm">
                <h1 class="page-header-title">Recovery Codes</h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <p>Your recovery codes allow you to access your account if you lose your authenticator device.</p>

                    <div class="alert alert-warning">
                        <strong>Important:</strong> Each recovery code can only be used once. Store them securely.
                    </div>

                    <form action="{{ route('admin.two-factor.recovery-codes-regenerate') }}" method="post" class="mt-3">
                        @csrf
                        <div class="form-group">
                            <label>Enter your password to regenerate recovery codes</label>
                            <input type="password" class="form-control" name="password" required style="max-width:400px;">
                        </div>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('This will invalidate your current recovery codes. Continue?')">
                            Regenerate Recovery Codes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
