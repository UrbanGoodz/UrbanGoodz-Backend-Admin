@extends('business.layouts.dispatcher')
@section('title', translate('Add Team Member'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Add Team Member') }}</h1>
    <a href="{{ route('business.dispatcher.users') }}" class="btn btn-outline--primary"><i class="tio-arrow-left"></i> {{ translate('Back') }}</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('business.dispatcher.users.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ translate('First Name') }} *</label>
                    <input type="text" name="first_name" class="form-control" required value="{{ old('first_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Last Name') }} *</label>
                    <input type="text" name="last_name" class="form-control" required value="{{ old('last_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Email') }} *</label>
                    <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Role') }} *</label>
                    <select name="role" class="form-control" required>
                        @foreach(['dispatch_manager' => 'Dispatch Manager', 'dispatcher' => 'Dispatcher', 'dispatch_readonly' => 'Read-Only Dispatcher', 'dispatch_finance' => 'Dispatch Finance'] as $val => $label)
                        <option value="{{ $val }}" {{ old('role') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Password') }} *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ translate('Confirm Password') }} *</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Create User') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
