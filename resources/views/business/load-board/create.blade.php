@extends('business.layouts.app')
@section('title', translate('Create Load Request'))
@section('content')
<div class="page-header mb-3">
    <h1 class="page-header-title">{{ translate('Create Load Request') }}</h1>
    <p class="text-muted" style="color: #6c757d !important;">{{ translate('Request a new courier delivery or load shipment') }}</p>
</div>

<form action="{{ route('business.load-board.store') }}" method="POST" class="card">
    @csrf
    <div class="card-body">
        <div class="row g-3">
            <!-- Section 1: Basic Information -->
            <div class="col-12">
                <h5 class="border-bottom pb-2">{{ translate('General Info') }}</h5>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Load Number / Reference') }}</label>
                <input type="text" name="load_number" class="form-control" placeholder="e.g. LN-98402" value="{{ old('load_number') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Load Type') }}</label>
                <select name="load_type" class="form-control">
                    <option value="Same-Day">{{ translate('Same-Day') }}</option>
                    <option value="Last-Mile">{{ translate('Last-Mile') }}</option>
                    <option value="Middle-Mile">{{ translate('Middle-Mile') }}</option>
                    <option value="Dedicated Route">{{ translate('Dedicated Route') }}</option>
                    <option value="Scheduled Route">{{ translate('Scheduled Route') }}</option>
                    <option value="Multi-stop courier">{{ translate('Multi-stop courier') }}</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Required Vehicle / Equipment') }}</label>
                <select name="equipment_type" class="form-control">
                    <option value="Cargo Van">{{ translate('Cargo Van') }}</option>
                    <option value="Pickup Truck">{{ translate('Pickup Truck') }}</option>
                    <option value="Box Truck">{{ translate('Box Truck') }}</option>
                    <option value="Hotshot">{{ translate('Hotshot') }}</option>
                    <option value="18-wheeler">{{ translate('18-wheeler') }}</option>
                </select>
            </div>

            <!-- Section 2: Origin -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2">{{ translate('Pickup Details') }}</h5>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Pickup Location Name / Facility') }}</label>
                <input type="text" name="origin_name" class="form-control" required placeholder="e.g. North Warehouse" value="{{ old('origin_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Pickup City') }}</label>
                <input type="text" name="origin_city" class="form-control" required placeholder="Houston" value="{{ old('origin_city') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Pickup State') }}</label>
                <input type="text" name="origin_state" class="form-control" required placeholder="TX" maxlength="2" value="{{ old('origin_state') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Pickup Zip Code') }}</label>
                <input type="text" name="origin_zip" class="form-control" required placeholder="77002" value="{{ old('origin_zip') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Pickup Scheduled Window') }}</label>
                <input type="datetime-local" name="origin_ready_at" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Shipper Contact Name') }}</label>
                <input type="text" name="shipper_name" class="form-control" placeholder="John Smith" value="{{ old('shipper_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Shipper Contact Phone') }}</label>
                <input type="text" name="shipper_phone" class="form-control" placeholder="555-0199" value="{{ old('shipper_phone') }}">
            </div>

            <!-- Section 3: Destination -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2">{{ translate('Delivery Details') }}</h5>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Delivery Destination Name') }}</label>
                <input type="text" name="destination_name" class="form-control" required placeholder="e.g. Retail Store #12" value="{{ old('destination_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Delivery City') }}</label>
                <input type="text" name="destination_city" class="form-control" required placeholder="Dallas" value="{{ old('destination_city') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Delivery State') }}</label>
                <input type="text" name="destination_state" class="form-control" required placeholder="TX" maxlength="2" value="{{ old('destination_state') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Delivery Zip Code') }}</label>
                <input type="text" name="destination_zip" class="form-control" required placeholder="75201" value="{{ old('destination_zip') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ translate('Delivery Due Window') }}</label>
                <input type="datetime-local" name="destination_due_at" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Consignee Contact Name') }}</label>
                <input type="text" name="consignee_name" class="form-control" placeholder="Jane Doe" value="{{ old('consignee_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ translate('Consignee Contact Phone') }}</label>
                <input type="text" name="consignee_phone" class="form-control" placeholder="555-0210" value="{{ old('consignee_phone') }}">
            </div>

            <!-- Section 4: Specifications & Pricing -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2">{{ translate('Cargo Specifications & Financials') }}</h5>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ translate('Payout Amount ($)') }}</label>
                <input type="number" name="payout_amount" step="0.01" class="form-control" required placeholder="250.00" value="{{ old('payout_amount') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ translate('Pricing Type') }}</label>
                <select name="payout_type" class="form-control">
                    <option value="flat">{{ translate('Flat Rate') }}</option>
                    <option value="per_mile">{{ translate('Per Mile') }}</option>
                    <option value="per_hour">{{ translate('Per Hour') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ translate('Rate Per Mile') }}</label>
                <input type="number" name="rate_per_mile" step="0.01" class="form-control" placeholder="e.g. 2.50" value="{{ old('rate_per_mile') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ translate('Estimated Distance (Miles)') }}</label>
                <input type="number" name="distance_miles" step="0.1" class="form-control" placeholder="e.g. 240" value="{{ old('distance_miles') }}">
            </div>

            <div class="col-md-3 col-sm-6">
                <label class="form-label">{{ translate('Weight (lbs)') }}</label>
                <input type="number" name="weight_lbs" step="0.1" class="form-control" placeholder="e.g. 1500" value="{{ old('weight_lbs') }}">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label">{{ translate('Length (ft)') }}</label>
                <input type="number" name="length_ft" step="0.1" class="form-control" placeholder="e.g. 12" value="{{ old('length_ft') }}">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label">{{ translate('Pieces Count') }}</label>
                <input type="number" name="pieces" class="form-control" placeholder="e.g. 4" value="{{ old('pieces') }}">
            </div>
            <div class="col-md-3 col-sm-6 d-flex align-items-end">
                <div class="w-100 mb-2">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="is_hazmat" value="1" class="form-check-input" id="is_hazmat">
                        <label class="form-check-label" for="is_hazmat">{{ translate('Hazmat') }}</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="requires_liftgate" value="1" class="form-check-input" id="requires_liftgate">
                        <label class="form-check-label" for="requires_liftgate">{{ translate('Liftgate') }}</label>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-3">
                <label class="form-label">{{ translate('Commodity / Cargo Description') }}</label>
                <input type="text" name="commodity_description" class="form-control" placeholder="e.g. Palletized electronics, auto parts..." value="{{ old('commodity_description') }}">
            </div>

            <div class="col-12 mt-3">
                <label class="form-label">{{ translate('Special Requirements or Handling Notes') }}</label>
                <textarea name="special_requirements" rows="2" class="form-control" placeholder="e.g. Liftgate required on delivery. Fragile items.">{{ old('special_requirements') }}</textarea>
            </div>

            <div class="col-12 mt-3">
                <label class="form-label">{{ translate('Internal Notes') }}</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Private internal notes visible only to your team..."></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('business.load-board.index') }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
        <button type="submit" class="btn btn--primary" style="background-color: var(--ug-primary); color: #fff;">{{ translate('Submit Load Request') }}</button>
    </div>
</form>
@endsection
