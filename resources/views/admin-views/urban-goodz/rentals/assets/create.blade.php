@extends('layouts.admin.app')

@section('title', translate('Add Rental Asset'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Add Rental Asset') }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.assets.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.rentals.assets.store') }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Business Type') }} <span class="text-danger">*</span></label>
                                <select name="business_type_slug" class="form-control" required>
                                    <option value="">{{ translate('Select') }}</option>
                                    @foreach($businessTypes as $slug => $name)
                                        <option value="{{ $slug }}" {{ old('business_type_slug') === $slug ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Asset Type') }} <span class="text-danger">*</span></label>
                                <select name="asset_type" class="form-control" required>
                                    <option value="car" {{ old('asset_type') === 'car' ? 'selected' : '' }}>Car</option>
                                    <option value="motorcycle" {{ old('asset_type') === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                                    <option value="scooter" {{ old('asset_type') === 'scooter' ? 'selected' : '' }}>Scooter</option>
                                    <option value="equipment" {{ old('asset_type') === 'equipment' ? 'selected' : '' }}>Equipment</option>
                                    <option value="tool" {{ old('asset_type') === 'tool' ? 'selected' : '' }}>Tool</option>
                                    <option value="other" {{ old('asset_type') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Make') }}</label>
                                <input type="text" name="make" class="form-control" value="{{ old('make') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Model') }}</label>
                                <input type="text" name="model" class="form-control" value="{{ old('model') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Year') }}</label>
                                <input type="text" name="year" class="form-control" value="{{ old('year') }}" maxlength="4">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Plate Number') }}</label>
                                <input type="text" name="plate_number" class="form-control" value="{{ old('plate_number') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('VIN') }}</label>
                                <input type="text" name="vin" class="form-control" value="{{ old('vin') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Unit Number') }}</label>
                                <input type="text" name="unit_number" class="form-control" value="{{ old('unit_number') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Daily Rate ($)') }}</label>
                                <input type="number" name="daily_rate" class="form-control" value="{{ old('daily_rate') }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Hourly Rate ($)') }}</label>
                                <input type="number" name="hourly_rate" class="form-control" value="{{ old('hourly_rate') }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Deposit Amount ($)') }}</label>
                                <input type="number" name="deposit_amount" class="form-control" value="{{ old('deposit_amount') }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Mileage Limit') }}</label>
                                <input type="number" name="mileage_limit" class="form-control" value="{{ old('mileage_limit') }}" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Pickup Location') }}</label>
                                <input type="text" name="pickup_location" class="form-control" value="{{ old('pickup_location') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Return Location') }}</label>
                                <input type="text" name="return_location" class="form-control" value="{{ old('return_location') }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Instructions') }}</label>
                                <textarea name="instructions" class="form-control" rows="2">{{ old('instructions') }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="is_active" class="toggle-switch-input" value="1" checked>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Active') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
