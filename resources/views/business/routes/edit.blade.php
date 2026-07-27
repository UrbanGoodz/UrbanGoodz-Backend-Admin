@extends('business.layouts.app')

@section('title', translate('Edit Route'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ translate('Edit Courier Route') }}</h1>
        <a href="{{ route('business.routes.show', $route->id) }}" class="btn btn-secondary">{{ translate('Back to Route') }}</a>
    </div>

    @if(in_array($route->status, ['completed', 'in_progress', 'canceled']))
    <div class="alert alert-warning">
        {{ translate('This route cannot be edited because it is') }} <strong>{{ $route->status }}</strong>.
    </div>
    @else
    <form action="{{ route('business.routes.update', $route->id) }}" method="POST">
        @csrf

        <div class="card">
            <div class="card-header"><h5>{{ translate('Route Information') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="route_name">{{ translate('Route Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="route_name" id="route_name" required value="{{ old('route_name', $route->route_name) }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="route_type">{{ translate('Route Type') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="route_type" id="route_type" required>
                                <option value="">{{ translate('Select type') }}</option>
                                <option value="logistics" {{ (old('route_type', $route->route_type) === 'logistics') ? 'selected' : '' }}>{{ translate('Logistics') }}</option>
                                <option value="medical_courier" {{ (old('route_type', $route->route_type) === 'medical_courier') ? 'selected' : '' }}>{{ translate('Medical Courier') }}</option>
                                <option value="load_board" {{ (old('route_type', $route->route_type) === 'load_board') ? 'selected' : '' }}>{{ translate('Load Board') }}</option>
                                <option value="bulk_delivery" {{ (old('route_type', $route->route_type) === 'bulk_delivery') ? 'selected' : '' }}>{{ translate('Bulk Delivery') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="pickup_location">{{ translate('Pickup Location') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="pickup_location" id="pickup_location" required>
                                <option value="">{{ translate('Select a location') }}</option>
                                @foreach($locations as $location)
                                    @php($locValue = $location->name . ' - ' . $location->address)
                                    <option value="{{ $locValue }}" data-lat="{{ $location->latitude }}" data-lng="{{ $location->longitude }}" {{ old('pickup_location', $route->pickup_location) === $locValue ? 'selected' : '' }}>{{ $location->name }} - {{ $location->city }}, {{ $location->state }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="pickup_lat" id="pickup_lat" value="{{ old('pickup_lat', $route->pickup_lat) }}">
                            <input type="hidden" name="pickup_lng" id="pickup_lng" value="{{ old('pickup_lng', $route->pickup_lng) }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="scheduled_date">{{ translate('Scheduled Date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="scheduled_date" id="scheduled_date" required value="{{ old('scheduled_date', $route->scheduled_date?->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label class="input-label" for="end_location">{{ translate('End Location (Optional)') }}</label>
                            <select class="form-control" name="end_location" id="end_location">
                                <option value="">{{ translate('No fixed endpoint') }}</option>
                                @foreach($locations as $location)
                                    @php($endValue = $location->name.' - '.$location->address)
                                    <option value="{{ $endValue }}" data-lat="{{ $location->latitude }}" data-lng="{{ $location->longitude }}" @selected(old('end_location', $route->end_location) === $endValue)>{{ $location->name }} — {{ $location->city }}, {{ $location->state }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="end_lat" id="end_lat" value="{{ old('end_lat', $route->end_lat) }}">
                            <input type="hidden" name="end_lng" id="end_lng" value="{{ old('end_lng', $route->end_lng) }}">
                            <label class="mt-2"><input type="checkbox" name="return_to_origin" value="1" @checked(old('return_to_origin', $route->return_to_origin))> {{ translate('Return to pickup location') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3 gap-2">
            <a href="{{ route('business.routes.show', $route->id) }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
            <button type="submit" class="btn btn--primary">{{ translate('Update Route') }}</button>
        </div>
    </form>
    @endif
@endsection

@push('script')
<script>
    function syncRouteLocation(selectId, latId, lngId) {
        const selected = document.getElementById(selectId).selectedOptions[0];
        document.getElementById(latId).value = selected.dataset.lat || '';
        document.getElementById(lngId).value = selected.dataset.lng || '';
    }
    document.getElementById('pickup_location')?.addEventListener('change', () => syncRouteLocation('pickup_location', 'pickup_lat', 'pickup_lng'));
    document.getElementById('end_location')?.addEventListener('change', () => syncRouteLocation('end_location', 'end_lat', 'end_lng'));
</script>
@endpush
