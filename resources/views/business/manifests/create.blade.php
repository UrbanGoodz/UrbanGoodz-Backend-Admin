@extends('business.layouts.app')

@section('title', translate('Create Manifest'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-header-title mb-0">{{ translate('Create Manifest') }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Create a manifest to group packages before route assignment') }}
            </p>
        </div>
        <a href="{{ route('business.manifests.index') }}" class="btn btn-outline--primary">
            <i class="tio-arrow-backward"></i> {{ translate('Back to Manifests') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.manifests.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="manifest_name">{{ translate('Manifest Name') }}</label>
                            <input type="text" id="manifest_name" name="manifest_name"
                                   class="form-control @error('manifest_name') is-invalid @enderror"
                                   placeholder="{{ translate('e.g. Friday Bulk Delivery') }}"
                                   value="{{ old('manifest_name') }}" maxlength="255">
                            @error('manifest_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="service_date">{{ translate('Service Date') }}</label>
                            <input type="date" id="service_date" name="service_date"
                                   class="form-control @error('service_date') is-invalid @enderror"
                                   value="{{ old('service_date', now()->format('Y-m-d')) }}">
                            @error('service_date') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="service_type">{{ translate('Service Type') }}</label>
                            <select id="service_type" name="service_type"
                                    class="form-control @error('service_type') is-invalid @enderror">
                                <option value="">{{ translate('Select service type (optional)') }}</option>
                                @foreach(\App\Models\UrbanGoodzManifest::SERVICE_TYPES as $type)
                                <option value="{{ $type }}" {{ old('service_type') === $type ? 'selected' : '' }}>
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                </option>
                                @endforeach
                            </select>
                            @error('service_type') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="pickup_location_id">{{ translate('Pickup Location') }}</label>
                            <select id="pickup_location_id" name="pickup_location_id"
                                    class="form-control @error('pickup_location_id') is-invalid @enderror">
                                <option value="">{{ translate('Select a location') }}</option>
                                @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('pickup_location_id') == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }} — {{ $location->address }}
                                </option>
                                @endforeach
                            </select>
                            <small class="text-muted" style="color: #6c757d !important;">
                                {{ translate('or') }}
                                <a href="{{ route('business.locations.create') }}">{{ translate('add a new location') }}</a>
                            </small>
                            @error('pickup_location_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="pickup_location_text">{{ translate('Or Enter Pickup Address') }}</label>
                            <input type="text" id="pickup_location_text" name="pickup_location_text"
                                   class="form-control"
                                   placeholder="{{ translate('123 Main St, City, State') }}"
                                   value="{{ old('pickup_location_text') }}">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label" for="notes">{{ translate('Notes') }}</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"
                                      placeholder="{{ translate('Optional notes about this manifest') }}">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('business.manifests.index') }}" class="btn btn-outline-secondary">
                        {{ translate('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Create Manifest') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
