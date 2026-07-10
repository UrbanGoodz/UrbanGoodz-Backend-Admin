@extends('layouts.admin.app')

@section('title', translate('Create Dedicated Route'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Create Dedicated Route') }}</h1>
            <a href="{{ route('admin.urban-goodz.dedicated-routes.index') }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.dedicated-routes.store') }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Business Client') }} <span class="text-danger">*</span></label>
                            <select name="business_client_id" class="form-control" required>
                                <option value="">{{ translate('Select Client') }}</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Route Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="route_name" class="form-control" required placeholder="e.g. Downtown Medical Run">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Route Type') }} <span class="text-danger">*</span></label>
                            <select name="route_type" class="form-control" required>
                                <option value="logistics">{{ translate('Logistics') }}</option>
                                <option value="medical_courier">{{ translate('Medical Courier') }}</option>
                                <option value="load_board">{{ translate('Load Board') }}</option>
                                <option value="bulk_delivery">{{ translate('Bulk Delivery') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Scheduled Date') }}</label>
                            <input type="date" name="scheduled_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Recurring Rule') }}</label>
                            <select name="recurring_rule" class="form-control">
                                <option value="">{{ translate('One Time') }}</option>
                                <option value="daily">{{ translate('Daily') }}</option>
                                <option value="weekly">{{ translate('Weekly') }}</option>
                                <option value="monthly">{{ translate('Monthly') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Pickup Location') }}</label>
                            <input type="text" name="pickup_location" class="form-control" placeholder="Pickup address or location name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Vehicle Type Required') }}</label>
                            <input type="text" name="vehicle_type_required" class="form-control" placeholder="e.g. Van, Refrigerated Truck">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Max Packages Per Batch') }}</label>
                            <input type="number" name="max_packages_per_batch" class="form-control" value="50" min="1" max="200">
                        </div>
                        <div class="col-12">
                            <h5 class="mb-2 mt-2">{{ translate('Financial Settings') }}</h5>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Driver Pay Per Package') }} ($)</label>
                            <input type="number" name="driver_pay_per_package" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Business Charge Per Package') }} ($)</label>
                            <input type="number" name="business_charge_per_package" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Pickup Bonus') }} ($)</label>
                            <input type="number" name="pickup_bonus" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Route Completion Bonus') }} ($)</label>
                            <input type="number" name="route_completion_bonus" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Priority Package Bonus') }} ($)</label>
                            <input type="number" name="priority_package_bonus" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Failed Delivery Partial Pay') }} ($)</label>
                            <input type="number" name="failed_delivery_partial_pay" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Return to Sender Pay') }} ($)</label>
                            <input type="number" name="return_to_sender_pay" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-3">
                            <div class="form-check">
                                <input type="checkbox" name="instant_payout_allowed" class="form-check-input" id="instantPayout" checked value="1">
                                <label class="form-check-label" for="instantPayout">{{ translate('Instant Payout') }}</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="weekly_payout_allowed" class="form-check-input" id="weeklyPayout" checked value="1">
                                <label class="form-check-label" for="weeklyPayout">{{ translate('Weekly Payout') }}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Admin Notes') }}</label>
                            <textarea name="admin_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('Create Route') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
