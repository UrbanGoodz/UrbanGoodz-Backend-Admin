@extends('layouts.admin.app')

@section('title', translate('Add Driver Pricing Override'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-add text-primary mr-1"></i></span>
                <span>{{ translate('Add Driver Pricing Policy / Zone Override') }}</span>
            </h1>
        </div>

        <form action="{{ route('admin.urban-goodz.driver-pricing.store') }}" method="POST">
            @csrf

            <div class="row g-3">
                <!-- Basic Policy Settings -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title">{{ translate('Basic Settings') }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Policy Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Houston Marketplace Delivery Policy" required value="{{ old('name') }}">
                            </div>

                            <div class="form-group">
                                <label class="input-label">{{ translate('Service Type / Channel') }}</label>
                                <select name="policy_type" class="form-control text-capitalize" required>
                                    <option value="">-- {{ translate('Select Service Type') }} --</option>
                                    <option value="marketplace_delivery" {{ old('policy_type') === 'marketplace_delivery' ? 'selected' : '' }}>Marketplace Delivery</option>
                                    <option value="courier_parcel" {{ old('policy_type') === 'courier_parcel' ? 'selected' : '' }}>Courier & Parcel</option>
                                    <option value="business_routes" {{ old('policy_type') === 'business_routes' ? 'selected' : '' }}>Business Routes</option>
                                    <option value="dedicated_routes" {{ old('policy_type') === 'dedicated_routes' ? 'selected' : '' }}>Dedicated Routes</option>
                                    <option value="logistics_loads" {{ old('policy_type') === 'logistics_loads' ? 'selected' : '' }}>Logistics Loads</option>
                                    <option value="medical_courier" {{ old('policy_type') === 'medical_courier' ? 'selected' : '' }}>Medical Courier</option>
                                    <option value="order_anywhere" {{ old('policy_type') === 'order_anywhere' ? 'selected' : '' }}>Order Anywhere</option>
                                    <option value="returns_exceptions" {{ old('policy_type') === 'returns_exceptions' ? 'selected' : '' }}>Returns & Exceptions</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="input-label">{{ translate('Zone (Optional for Override)') }}</label>
                                <select name="zone_id" class="form-control">
                                    <option value="">-- {{ translate('Global Default (All Zones)') }} --</option>
                                    @foreach($zones as $zone)
                                        <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">{{ translate('Leave blank to set as a global default policy.') }}</small>
                            </div>

                            <div class="form-group">
                                <label class="input-label">{{ translate('Payout Calculation Model') }}</label>
                                <select name="payout_model" id="payoutModelSelect" class="form-control text-capitalize" required>
                                    <option value="fixed_payout" {{ old('payout_model') === 'fixed_payout' ? 'selected' : '' }}>Fixed Payout</option>
                                    <option value="base_mileage" {{ old('payout_model') === 'base_mileage' ? 'selected' : '' }}>Base + Mileage</option>
                                    <option value="base_mileage_time" {{ old('payout_model') === 'base_mileage_time' ? 'selected' : '' }}>Base + Mileage + Time</option>
                                    <option value="per_stop" {{ old('payout_model') === 'per_stop' ? 'selected' : '' }}>Per Stop</option>
                                    <option value="per_package" {{ old('payout_model') === 'per_package' ? 'selected' : '' }}>Per Package</option>
                                    <option value="percentage_of_revenue" {{ old('payout_model') === 'percentage_of_revenue' ? 'selected' : '' }}>Percentage of Revenue</option>
                                    <option value="dynamic_ai" {{ old('payout_model') === 'dynamic_ai' ? 'selected' : '' }}>Dynamic AI Pricing</option>
                                    <option value="manual_quote" {{ old('payout_model') === 'manual_quote' ? 'selected' : '' }}>Manual Quote</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parameters Box -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title">{{ translate('Pricing Parameters') }}</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6 form-group payout-param-field" data-models="fixed_payout,manual_quote">
                                    <label class="input-label">{{ translate('Fixed Payout Amount') }} ($)</label>
                                    <input type="number" step="0.01" name="fixed_amount" class="form-control" value="{{ old('fixed_amount', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group payout-param-field" data-models="base_mileage,base_mileage_time,dynamic_ai">
                                    <label class="input-label">{{ translate('Base Fare') }} ($)</label>
                                    <input type="number" step="0.01" name="base_fare" class="form-control" value="{{ old('base_fare', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group payout-param-field" data-models="base_mileage,base_mileage_time">
                                    <label class="input-label">{{ translate('Rate Per Mile') }} ($)</label>
                                    <input type="number" step="0.01" name="rate_per_mile" class="form-control" value="{{ old('rate_per_mile', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group payout-param-field" data-models="base_mileage_time">
                                    <label class="input-label">{{ translate('Rate Per Minute') }} ($)</label>
                                    <input type="number" step="0.01" name="rate_per_minute" class="form-control" value="{{ old('rate_per_minute', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group payout-param-field" data-models="per_stop">
                                    <label class="input-label">{{ translate('Rate Per Stop') }} ($)</label>
                                    <input type="number" step="0.01" name="rate_per_stop" class="form-control" value="{{ old('rate_per_stop', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group payout-param-field" data-models="per_package">
                                    <label class="input-label">{{ translate('Rate Per Package') }} ($)</label>
                                    <input type="number" step="0.01" name="rate_per_package" class="form-control" value="{{ old('rate_per_package', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group payout-param-field" data-models="percentage_of_revenue">
                                    <label class="input-label">{{ translate('Revenue Percentage') }} (%)</label>
                                    <input type="number" step="0.01" name="revenue_percentage" class="form-control" min="0" max="100" value="{{ old('revenue_percentage', '0.00') }}">
                                </div>
                            </div>

                            <hr>

                            <!-- Operational Pay Rates -->
                            <h6 class="text-secondary mb-3">{{ translate('Operational Compensation & Multipliers') }}</h6>
                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Urgency Premium') }} ($)</label>
                                    <input type="number" step="0.01" name="urgency_premium" class="form-control" value="{{ old('urgency_premium', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Deadhead Pay Rate') }} ($/mi)</label>
                                    <input type="number" step="0.01" name="deadhead_pay_rate" class="form-control" value="{{ old('deadhead_pay_rate', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Waiting Pay Rate') }} ($/min)</label>
                                    <input type="number" step="0.01" name="waiting_pay_rate" class="form-control" value="{{ old('waiting_pay_rate', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Return Pay Rate') }} ($)</label>
                                    <input type="number" step="0.01" name="return_pay_rate" class="form-control" value="{{ old('return_pay_rate', '0.00') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Exception Pay Rate') }} ($)</label>
                                    <input type="number" step="0.01" name="exception_pay_rate" class="form-control" value="{{ old('exception_pay_rate', '0.00') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Limits & Multipliers -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title">{{ translate('Safety Limits & Margins') }}</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Minimum Payout') }} ($)</label>
                                    <input type="number" step="0.01" name="minimum_payout" class="form-control" placeholder="No limit" value="{{ old('minimum_payout') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Maximum Payout') }} ($)</label>
                                    <input type="number" step="0.01" name="maximum_payout" class="form-control" placeholder="No limit" value="{{ old('maximum_payout') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Minimum Platform Margin') }} (%)</label>
                                    <input type="number" step="0.01" name="minimum_margin" class="form-control" placeholder="e.g. 15.00" value="{{ old('minimum_margin') }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Effective From') }}</label>
                                    <input type="datetime-local" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label class="input-label">{{ translate('Effective To') }}</label>
                                    <input type="datetime-local" name="effective_to" class="form-control" value="{{ old('effective_to') }}">
                                </div>
                            </div>

                            <!-- Vehicle Multipliers -->
                            <hr>
                            <h6 class="text-secondary mb-3">{{ translate('Vehicle Multipliers') }}</h6>
                            <div class="row">
                                @foreach($vehicles as $vehicle)
                                    <div class="col-sm-6 form-group">
                                        <label class="input-label text-capitalize">{{ $vehicle->type }} (Multiplier)</label>
                                        <input type="number" step="0.05" name="vehicle_multipliers[{{ $vehicle->id }}]" class="form-control" placeholder="1.00" value="{{ old("vehicle_multipliers.{$vehicle->id}", '1.00') }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toggles & Approvals -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title">{{ translate('Control Toggles & Approvals') }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="dynamicPricingToggle" name="dynamic_pricing_enabled" class="custom-control-input" value="1" {{ old('dynamic_pricing_enabled') ? 'checked' : '' }}>
                                    <label class="custom-checkbox-switch-label" for="dynamicPricingToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('AI Dynamic Pricing Enabled') }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="recommendationOnlyToggle" name="recommendation_only" class="custom-control-input" value="1" {{ old('recommendation_only') ? 'checked' : '' }}>
                                    <label class="custom-checkbox-switch-label" for="recommendationOnlyToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('Recommendation Only') }}</span>
                                </div>
                                <small class="text-muted d-block mt-1">{{ translate('AI results are suggestions only, dispatcher confirms payout.') }}</small>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="autoApplyToggle" name="auto_apply_within_limits" class="custom-control-input" value="1" {{ old('auto_apply_within_limits') ? 'checked' : '' }}>
                                    <label class="custom-checkbox-switch-label" for="autoApplyToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('Auto-Apply Within Safe Limits') }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="dispatcherApproveToggle" name="dispatcher_approval_required" class="custom-control-input" value="1" {{ old('dispatcher_approval_required') ? 'checked' : '' }}>
                                    <label class="custom-checkbox-switch-label" for="dispatcherApproveToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('Dispatcher Approval Required') }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="adminApproveToggle" name="admin_approval_required" class="custom-control-input" value="1" {{ old('admin_approval_required') ? 'checked' : '' }}>
                                    <label class="custom-checkbox-switch-label" for="adminApproveToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('Admin Approval Required') }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="livePricingToggle" name="live_pricing_enabled" class="custom-control-input" value="1" {{ old('live_pricing_enabled') ? 'checked' : '' }}>
                                    <label class="custom-checkbox-switch-label" for="livePricingToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('Live Pricing Active') }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox-switch">
                                    <input type="checkbox" id="sandboxPricingToggle" name="sandbox_pricing_enabled" class="custom-control-input" value="1" checked {{ old('sandbox_pricing_enabled') === '0' ? '' : 'checked' }}>
                                    <label class="custom-checkbox-switch-label" for="sandboxPricingToggle">
                                        <span class="custom-checkbox-switch-inner"></span>
                                    </label>
                                    <span class="ml-2 font-weight-bold">{{ translate('Sandbox Testing Mode Enabled') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="d-flex justify-content-end gap-3 mt-4 mb-5">
                <button type="reset" class="btn btn--secondary">{{ translate('Reset') }}</button>
                <button type="submit" class="btn btn--primary">{{ translate('Save Policy') }}</button>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
<script>
    $(document).ready(function() {
        function toggleParamFields() {
            var selectedModel = $('#payoutModelSelect').val();
            $('.payout-param-field').each(function() {
                var models = $(this).data('models').split(',');
                if (models.includes(selectedModel)) {
                    $(this).show().find('input').prop('disabled', false);
                } else {
                    $(this).hide().find('input').prop('disabled', true);
                }
            });
        }

        $('#payoutModelSelect').on('change', toggleParamFields);
        toggleParamFields(); // Init on load
    });
</script>
@endpush
