<div class="row g-2">
    <div class="col-md-2"><label>{{ translate('Service Type') }}</label><input class="form-control" name="service_type" value="{{ old('service_type', 'marketplace_delivery') }}" required></div>
    <div class="col-md-2"><label>{{ translate('Merchandise (cents)') }}</label><input class="form-control" type="number" min="0" name="merchandise_subtotal_cents" value="{{ old('merchandise_subtotal_cents', 12500) }}" required></div>
    <div class="col-md-2"><label>{{ translate('Delivery Charge (cents)') }}</label><input class="form-control" type="number" min="0" name="delivery_charge_cents" value="{{ old('delivery_charge_cents', 1299) }}" required></div>
    <div class="col-md-2"><label>{{ translate('Miles × 1000') }}</label><input class="form-control" type="number" min="0" name="miles_milli" value="{{ old('miles_milli', 8400) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Packages') }}</label><input class="form-control" type="number" min="0" name="package_count" value="{{ old('package_count', 2) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Stops') }}</label><input class="form-control" type="number" min="0" name="stop_count" value="{{ old('stop_count', 2) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Routes') }}</label><input class="form-control" type="number" min="0" name="route_count" value="{{ old('route_count', 1) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Minutes') }}</label><input class="form-control" type="number" min="0" name="hours_minutes" value="{{ old('hours_minutes', 45) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Returns') }}</label><input class="form-control" type="number" min="0" name="return_count" value="{{ old('return_count', 0) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Exceptions') }}</label><input class="form-control" type="number" min="0" name="exception_count" value="{{ old('exception_count', 0) }}" required></div>
    <div class="col-md-1"><label>{{ translate('Business ID') }}</label><input class="form-control" type="number" min="1" name="business_id" value="{{ old('business_id') }}"></div>
    <div class="col-md-1"><label>{{ translate('Provider ID') }}</label><input class="form-control" type="number" min="1" name="provider_id" value="{{ old('provider_id') }}"></div>
    <div class="col-md-1"><label>{{ translate('Driver ID') }}</label><input class="form-control" type="number" min="1" name="driver_id" value="{{ old('driver_id') }}"></div>
    <div class="col-md-1"><label>{{ translate('Zone ID') }}</label><input class="form-control" type="number" min="1" name="zone_id" value="{{ old('zone_id') }}"></div>
    <div class="col-md-1"><label>{{ translate('Customer ID') }}</label><input class="form-control" type="number" min="1" name="customer_id" value="{{ old('customer_id') }}"></div>
    <div class="col-md-1"><label>{{ translate('Currency') }}</label><input class="form-control" name="currency" value="{{ old('currency', 'USD') }}"></div>
</div>
