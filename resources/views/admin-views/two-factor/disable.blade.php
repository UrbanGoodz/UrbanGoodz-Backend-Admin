@extends('layouts.admin.app')
@section('title', 'Disable Two-Factor Authentication')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm">
                <h1 class="page-header-title text-danger">Disable Two-Factor Authentication</h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> Disabling 2FA will make your account less secure. You will no longer need a verification code to log in.
                    </div>
                    <form action="{{ route('admin.two-factor.disable-submit') }}" method="post">
                        @csrf
                        @method('DELETE')
                        <div class="form-group">
                            <label>Confirm your password to disable 2FA</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable 2FA?')">
                            Disable 2FA
                        </button>
                        <a href="{{ route('admin.two-factor.index') }}" class="btn btn-outline-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
