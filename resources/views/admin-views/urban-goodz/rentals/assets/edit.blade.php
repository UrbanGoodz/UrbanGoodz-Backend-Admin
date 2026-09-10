@extends('layouts.admin.app')

@section('title', translate('Edit Rental Asset'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Asset') }}: {{ $asset->title }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.assets.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.rentals.assets.update', $asset->id) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Business Type') }} <span class="text-danger">*</span></label>
                                <select name="business_type_slug" class="form-control" required>
                                    @foreach($businessTypes as $slug => $name)
                                        <option value="{{ $slug }}" {{ $asset->business_type_slug === $slug ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="{{ old('title', $asset->title) }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Asset Type') }} <span class="text-danger">*</span></label>
                                <select name="asset_type" class="form-control" required>
                                    @foreach(['car','motorcycle','scooter','equipment','tool','other'] as $t)
                                        <option value="{{ $t }}" {{ $asset->asset_type === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Make') }}</label>
                                <input type="text" name="make" class="form-control" value="{{ old('make', $asset->make) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Model') }}</label>
                                <input type="text" name="model" class="form-control" value="{{ old('model', $asset->model) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Year') }}</label>
                                <input type="text" name="year" class="form-control" value="{{ old('year', $asset->year) }}" maxlength="4">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Plate Number') }}</label>
                                <input type="text" name="plate_number" class="form-control" value="{{ old('plate_number', $asset->plate_number) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('VIN') }}</label>
                                <input type="text" name="vin" class="form-control" value="{{ old('vin', $asset->vin) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Daily Rate ($)') }}</label>
                                <input type="number" name="daily_rate" class="form-control" value="{{ old('daily_rate', $asset->daily_rate) }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Hourly Rate ($)') }}</label>
                                <input type="number" name="hourly_rate" class="form-control" value="{{ old('hourly_rate', $asset->hourly_rate) }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Deposit ($)') }}</label>
                                <input type="number" name="deposit_amount" class="form-control" value="{{ old('deposit_amount', $asset->deposit_amount) }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Mileage Limit') }}</label>
                                <input type="number" name="mileage_limit" class="form-control" value="{{ old('mileage_limit', $asset->mileage_limit) }}" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Pickup Location') }}</label>
                                <input type="text" name="pickup_location" class="form-control" value="{{ old('pickup_location', $asset->pickup_location) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Return Location') }}</label>
                                <input type="text" name="return_location" class="form-control" value="{{ old('return_location', $asset->return_location) }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Description') }}</label>
                                <textarea name="description" class="form-control" rows="2">{{ old('description', $asset->description) }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Photos') }}</label>
                                @php $existingPhotos = json_decode($asset->photos ?? '[]', true) ?: []; @endphp
                                @if(count($existingPhotos))
                                    <div class="d-flex flex-wrap gap-10px mb-2">
                                        @foreach($existingPhotos as $index => $photo)
                                            <div class="text-center">
                                                <img src="{{ \App\CentralLogics\Helpers::get_full_url('rental_assets', $photo['img'] ?? '', $photo['storage'] ?? 'public', 'upload_image') }}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:4px;">
                                                <div class="form-check mt-1">
                                                    <input type="checkbox" class="form-check-input" name="remove_photos[]" value="{{ $index }}" id="remove_photo_{{ $index }}">
                                                    <label class="form-check-label" for="remove_photo_{{ $index }}">{{ translate('Remove') }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                                <small class="text-muted">{{ translate('Add more photos of the vehicle or equipment being offered.') }}</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Instructions') }}</label>
                                <textarea name="instructions" class="form-control" rows="2">{{ old('instructions', $asset->instructions) }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="toggle-switch my-0">
                                <input type="checkbox" name="is_active" class="toggle-switch-input" value="1" {{ $asset->is_active ? 'checked' : '' }}>
                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                <span class="ml-2">{{ translate('Active') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
