@extends('business.layouts.app')

@section('title', translate('Edit Team Member'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="page-header-title">{{ translate('Edit Team Member') }}</h1>
        <a href="{{ route('business.users.index') }}" class="btn btn-secondary">
            <i class="tio-back"></i> {{ translate('Back') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.users.update', $user->id) }}" method="POST">
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
                            <label class="form-label">{{ translate('Email') }} <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}">
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
                            <label class="form-label">{{ translate('Role') }} <span class="text-danger">*</span></label>
                            <select name="role" class="form-control" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Status') }} <span class="text-danger">*</span></label>
                            <select name="is_active" class="form-control" required>
                                <option value="1" {{ old('is_active', $user->is_active) ? 'selected' : '' }}>{{ translate('Active') }}</option>
                                <option value="0" {{ !old('is_active', $user->is_active) ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('business.users.index') }}" class="btn btn-secondary me-2">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary">{{ translate('Update User') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
