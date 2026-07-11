@extends('layouts.admin.app')
@section('title', 'Two-Factor Authentication')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm">
                <h1 class="page-header-title">Two-Factor Authentication</h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    @if($admin->two_factor_enabled)
                        <div class="d-flex align-items-center mb-4">
                            <span class="btn btn-success btn-sm mr-2"><i class="tio-checkmark"></i> Enabled</span>
                            <span class="text-muted">Since {{ $admin->two_factor_confirmed_at?->diffForHumans() }}</span>
                        </div>
                        <p>Two-factor authentication is <strong>active</strong> on your account. You will be prompted for a verification code each time you log in.</p>

                        <div class="mt-4">
                            <a href="{{ route('admin.two-factor.recovery-codes') }}" class="btn btn-outline-primary mr-2">
                                <i class="tio-key"></i> View Recovery Codes
                            </a>
                            <a href="{{ route('admin.two-factor.disable') }}" class="btn btn-outline-danger">
                                <i class="tio-clear"></i> Disable 2FA
                            </a>
                        </div>
                    @else
                        <div class="d-flex align-items-center mb-4">
                            <span class="btn btn-warning btn-sm mr-2"><i class="tio-alert"></i> Not Enabled</span>
                        </div>
                        <p>Two-factor authentication adds an extra layer of security to your account. Once enabled, you will be prompted for a verification code from your authenticator app each time you log in.</p>

                        <div class="mt-4">
                            <a href="{{ route('admin.two-factor.show-setup') }}" class="btn btn--primary">
                                <i class="tio-shield-check"></i> Enable 2FA
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
