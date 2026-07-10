@extends('business.layouts.app')

@section('title', translate('My Profile'))

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">{{ translate('My Profile') }}</h1>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ translate('Profile Information') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('business.profile.update') }}" method="POST">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('First Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required value="{{ old('first_name', $user->first_name) }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Last Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control" required value="{{ old('last_name', $user->last_name) }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Email') }}</label>
                                    <input type="email" class="form-control" value="{{ $user->email }}" disabled>
                                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('Email cannot be changed') }}</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Phone') }}</label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Role') }}</label>
                                    <input type="text" class="form-control" value="{{ ucwords(str_replace('_', ' ', $user->role ?? '-')) }}" disabled>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Last Login') }}</label>
                                    <input type="text" class="form-control" value="{{ $user->last_login_at?->diffForHumans() ?? translate('Never') }}" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn--primary">{{ translate('Update Profile') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ translate('Change Password') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('business.profile.password') }}" method="POST">
                        @csrf

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Current Password') }} <span class="text-danger">*</span></label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('New Password') }} <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" required minlength="8">
                                    <small class="text-muted" style="color: #6c757d !important;">{{ translate('Minimum 8 characters') }}</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">{{ translate('Confirm New Password') }} <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn--primary">{{ translate('Change Password') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
