@extends('business.layouts.dispatcher')
@section('title', translate('Edit Team Member'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Edit Team Member') }}</h1>
    <a href="{{ route('business.dispatcher.users') }}" class="btn btn-outline--primary"><i class="tio-arrow-left"></i> {{ translate('Back') }}</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('business.dispatcher.users.update', $editUser->id) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ translate('First Name') }} *</label>
                    <input type="text" name="first_name" class="form-control" required value="{{ old('first_name', $editUser->first_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Last Name') }} *</label>
                    <input type="text" name="last_name" class="form-control" required value="{{ old('last_name', $editUser->last_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Email') }} *</label>
                    <input type="email" name="email" class="form-control" required value="{{ old('email', $editUser->email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $editUser->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Role') }} *</label>
                    <select name="role" class="form-control" required>
                        @foreach(['dispatch_manager' => 'Dispatch Manager', 'dispatcher' => 'Dispatcher', 'dispatch_readonly' => 'Read-Only Dispatcher', 'dispatch_finance' => 'Dispatch Finance'] as $val => $label)
                        <option value="{{ $val }}" {{ old('role', $editUser->role) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ translate('New Password') }} <small class="text-muted">({{ translate('leave blank to keep current') }})</small></label>
                        <input type="password" name="password" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $editUser->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">{{ translate('Active') }}</label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Update User') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
