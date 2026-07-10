@extends('business.layouts.app')

@section('title', translate('Add Package'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ translate('Add Package to') }}: {{ $route->route_name }}</h1>
        <a href="{{ route('business.routes.packages', $route->id) }}" class="btn btn-secondary">
            {{ translate('Back to Packages') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.routes.packages.store', $route->id) }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="dropoff_name">{{ translate('Recipient Name') }}</label>
                            <input type="text" class="form-control" name="dropoff_name" id="dropoff_name" value="{{ old('dropoff_name') }}" placeholder="{{ translate('Business or person name') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="dropoff_phone">{{ translate('Contact Phone') }}</label>
                            <input type="text" class="form-control" name="dropoff_phone" id="dropoff_phone" value="{{ old('dropoff_phone') }}" placeholder="{{ translate('Phone number') }}">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="input-label" for="dropoff_address">{{ translate('Drop-off Address') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="dropoff_address" id="dropoff_address" required value="{{ old('dropoff_address') }}" placeholder="123 Main St">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label" for="dropoff_city">{{ translate('City') }}</label>
                            <input type="text" class="form-control" name="dropoff_city" id="dropoff_city" value="{{ old('dropoff_city') }}" placeholder="{{ translate('City') }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label" for="dropoff_state">{{ translate('State') }}</label>
                            <input type="text" class="form-control" name="dropoff_state" id="dropoff_state" value="{{ old('dropoff_state') }}" placeholder="{{ translate('State') }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label" for="dropoff_zip">{{ translate('ZIP Code') }}</label>
                            <input type="text" class="form-control" name="dropoff_zip" id="dropoff_zip" value="{{ old('dropoff_zip') }}" placeholder="{{ translate('ZIP') }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label" for="package_type">{{ translate('Package Type') }}</label>
                            <select class="form-control" name="package_type" id="package_type">
                                <option value="parcel">{{ translate('Parcel') }}</option>
                                <option value="document">{{ translate('Document') }}</option>
                                <option value="specimen">{{ translate('Specimen') }}</option>
                                <option value="supply">{{ translate('Supply') }}</option>
                                <option value="pallet">{{ translate('Pallet') }}</option>
                                <option value="envelope">{{ translate('Envelope') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label" for="weight">{{ translate('Weight') }} ({{ translate('lbs') }})</label>
                            <input type="number" class="form-control" name="weight" id="weight" value="{{ old('weight') }}" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label" for="priority">{{ translate('Priority') }}</label>
                            <select class="form-control" name="priority" id="priority">
                                <option value="normal">{{ translate('Normal') }}</option>
                                <option value="high">{{ translate('High') }}</option>
                                <option value="urgent">{{ translate('Urgent') }}</option>
                                <option value="medical">{{ translate('Medical') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label" for="delivery_window_start">{{ translate('Delivery Window') }}</label>
                            <div class="d-flex gap-1">
                                <input type="time" class="form-control" name="delivery_window_start" id="delivery_window_start" value="{{ old('delivery_window_start') }}">
                                <span class="align-self-center text-muted small">to</span>
                                <input type="time" class="form-control" name="delivery_window_end" id="delivery_window_end" value="{{ old('delivery_window_end') }}">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="input-label" for="delivery_notes">{{ translate('Delivery Notes') }}</label>
                            <textarea class="form-control" name="delivery_notes" id="delivery_notes" rows="2" placeholder="{{ translate('Leave at door, call on arrival, gate code, etc.') }}">{{ old('delivery_notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3 gap-2">
                    <a href="{{ route('business.routes.packages', $route->id) }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary">{{ translate('Add Package') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
