@extends('layouts.admin.app')

@section('title', $client->company_name . ' - ' . translate('Edit User'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Edit User') }} — {{ $user->first_name }} {{ $user->last_name }}</h1>
            <a href="{{ route('admin.urban-goodz.business-clients.users.index', $client->id) }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.business-clients.users.update', [$client->id, $user->id]) }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('First Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required value="{{ old('first_name', $user->first_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Last Name') }}</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Email') }} <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Phone') }}</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('New Password') }} <small>({{ translate('Leave blank to keep current password') }})</small></label>
                            <input type="password" name="new_password" class="form-control" minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Confirm New Password') }}</label>
                            <input type="password" name="new_password_confirmation" class="form-control" minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Role') }} <span class="text-danger">*</span></label>
                            <select name="role" class="form-control" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <select name="status" class="form-control">
                                @foreach(['active', 'inactive', 'suspended'] as $st)
                                    <option value="{{ $st }}" {{ old('status', $user->status ?? ($user->is_active ? 'active' : 'inactive')) === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" {{ $user->is_active ? 'checked' : '' }}>
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
                                    <input type="checkbox" name="permissions[]" class="form-check-input" value="{{ $perm }}" id="perm_{{ $loop->index }}"
                                        {{ in_array($perm, old('permissions', $user->permissions ?? [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="perm_{{ $loop->index }}">{{ ucfirst(str_replace('_', ' ', $perm)) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('Update User') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
