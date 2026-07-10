@extends('layouts.admin.app')

@section('title', $client->company_name . ' - ' . translate('Create User'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Create User') }} — {{ $client->company_name }}</h1>
            <a href="{{ route('admin.urban-goodz.business-clients.users.index', $client->id) }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.business-clients.users.store', $client->id) }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('First Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required value="{{ old('first_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Last Name') }}</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Email') }} <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Phone') }}</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Temporary Password') }} <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Confirm Password') }} <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Role') }} <span class="text-danger">*</span></label>
                            <select name="role" class="form-control" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <select name="status" class="form-control">
                                <option value="active">{{ translate('Active') }}</option>
                                <option value="inactive">{{ translate('Inactive') }}</option>
                                <option value="suspended">{{ translate('Suspended') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" checked value="1">
                                <label class="form-check-label" for="isActive">{{ translate('Active') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h5>{{ translate('Permissions') }}</h5></div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach($permissions as $perm)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="permissions[]" class="form-check-input" value="{{ $perm }}" id="perm_{{ $loop->index }}">
                                    <label class="form-check-label" for="perm_{{ $loop->index }}">{{ ucfirst(str_replace('_', ' ', $perm)) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('Create User') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
