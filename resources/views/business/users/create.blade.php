@extends('business.layouts.app')

@section('title', translate('Add Team Member'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="page-header-title">{{ translate('Add Team Member') }}</h1>
        <a href="{{ route('business.users.index') }}" class="btn btn-secondary">
            <i class="tio-back"></i> {{ translate('Back') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.users.store') }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('First Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required value="{{ old('first_name') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Last Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required value="{{ old('last_name') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Email') }} <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Phone') }}</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Role') }} <span class="text-danger">*</span></label>
                            <select name="role" class="form-control" required>
                                <option value="">{{ translate('Select role') }}</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Password') }} <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <small class="text-muted" style="color: #6c757d !important;">{{ translate('Minimum 8 characters') }}</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Confirm Password') }} <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('business.users.index') }}" class="btn btn-secondary me-2">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary">{{ translate('Create User') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
