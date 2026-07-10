@extends('layouts.admin.app')

@section('title', $client->company_name . ' - ' . translate('Create Location'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Create Location') }} — {{ $client->company_name }}</h1>
            <a href="{{ route('admin.urban-goodz.business-clients.locations.index', $client->id) }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.business-clients.locations.store', $client->id) }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Location Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Type') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Address') }} <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control" required rows="2">{{ old('address') }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('City') }} <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" required value="{{ old('city') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('State') }}</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Postal Code') }}</label>
                            <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Country') }}</label>
                            <input type="text" name="country" class="form-control" value="{{ old('country', 'US') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Latitude') }}</label>
                            <input type="text" name="latitude" class="form-control" step="any" value="{{ old('latitude') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Longitude') }}</label>
                            <input type="text" name="longitude" class="form-control" step="any" value="{{ old('longitude') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Contact Name') }}</label>
                            <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Contact Phone') }}</label>
                            <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Contact Email') }}</label>
                            <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Operating Hours') }}</label>
                            <textarea name="operating_hours" class="form-control" rows="2" placeholder="e.g. Mon-Fri 9am-5pm">{{ old('operating_hours') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Pickup Instructions') }}</label>
                            <textarea name="pickup_instructions" class="form-control" rows="2">{{ old('pickup_instructions') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Delivery Instructions') }}</label>
                            <textarea name="delivery_instructions" class="form-control" rows="2">{{ old('delivery_instructions') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" checked value="1">
                                <label class="form-check-label" for="isActive">{{ translate('Active') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('Create Location') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
