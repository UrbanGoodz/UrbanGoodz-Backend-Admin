@extends('business.layouts.app')

@section('title', translate('Edit Location'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="page-header-title">{{ translate('Edit Location') }}</h1>
        <a href="{{ route('business.locations.index') }}" class="btn btn-secondary">
            <i class="tio-back"></i> {{ translate('Back') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.locations.update', $location->id) }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Location Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $location->name) }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Type') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" {{ old('type', $location->type) === $type ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Address') }} <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control" required rows="2">{{ old('address', $location->address) }}</textarea>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('City') }} <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" required value="{{ old('city', $location->city) }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('State') }}</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state', $location->state) }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Postal Code') }}</label>
                            <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $location->postal_code) }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Contact Name') }}</label>
                            <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $location->contact_name) }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Contact Phone') }}</label>
                            <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $location->contact_phone) }}">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label">{{ translate('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $location->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('business.locations.index') }}" class="btn btn-secondary me-2">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary">{{ translate('Update Location') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
