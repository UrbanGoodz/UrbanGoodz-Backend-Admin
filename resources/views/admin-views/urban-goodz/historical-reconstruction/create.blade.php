@extends('layouts.admin.app')

@section('title', translate('Create Reconstruction Configuration'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('New Reconstruction Configuration') }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.store') }}">
            @csrf
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Configuration Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Configuration Name') }} *</label>
                            <input type="text" name="configuration_name" class="form-control" value="{{ old('configuration_name', 'Urban Goodz 24-Month Reconstruction') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Start Date') }} *</label>
                            <input type="date" name="reconstruction_start_date" class="form-control" value="{{ old('reconstruction_start_date', '2023-10-01') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('End Date') }} *</label>
                            <input type="date" name="reconstruction_end_date" class="form-control" value="{{ old('reconstruction_end_date', '2025-10-31') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Owner/Founder Name') }}</label>
                            <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', 'D\'Andre Good') }}">
                            <small class="text-muted">Owner was also an active delivery driver</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Owner Non-Delivery Months') }}</label>
                            <input type="text" name="owner_non_delivery_months" class="form-control" value="{{ old('owner_non_delivery_months', '12,1,2') }}" placeholder="12,1,2">
                            <small class="text-muted">Comma-separated month numbers (Dec, Jan, Feb)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Historical Operating Assumptions (Owner-Provided Baselines)') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Monthly Orders') }} *</label>
                            <input type="number" name="baseline_monthly_orders" class="form-control" value="{{ old('baseline_monthly_orders', 750) }}" step="1" min="0" required>
                            <small class="text-muted">~750 orders/month</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Order Value ($)') }} *</label>
                            <input type="number" name="baseline_average_order_value" class="form-control" value="{{ old('baseline_average_order_value', 29.00) }}" step="0.01" min="0" required>
                            <small class="text-muted">~$29 average</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Order Commission (%)') }} *</label>
                            <input type="number" name="baseline_order_commission_pct" class="form-control" value="{{ old('baseline_order_commission_pct', 23) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">~23% commission</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Delivery Fee ($)') }} *</label>
                            <input type="number" name="baseline_delivery_fee" class="form-control" value="{{ old('baseline_delivery_fee', 15.00) }}" step="0.01" min="0" required>
                            <small class="text-muted">~$15 delivery fee</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Platform Share of Delivery Fee (%)') }} *</label>
                            <input type="number" name="baseline_platform_delivery_fee_pct" class="form-control" value="{{ old('baseline_platform_delivery_fee_pct', 3) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">~3% platform share</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Active Drivers') }} *</label>
                            <input type="number" name="baseline_active_drivers" class="form-control" value="{{ old('baseline_active_drivers', 13) }}" step="1" min="1" required>
                            <small class="text-muted">~13 drivers</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Monthly Net Income ($)') }} *</label>
                            <input type="number" name="baseline_avg_monthly_net" class="form-control" value="{{ old('baseline_avg_monthly_net', 5700.00) }}" step="0.01" required>
                            <small class="text-muted">~$5,700/month net</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Operating Expense Ratio (%)') }} *</label>
                            <input type="number" name="operating_expense_ratio" class="form-control" value="{{ old('operating_expense_ratio', 25) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">% of platform revenue</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Month-to-Month Variation Settings') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">{{ translate('Controls how much each month\'s values may deviate from the baseline. The system generates varied monthly data and calculates revenue/expenses mathematically.') }}</p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Orders Variation (%)') }} *</label>
                            <input type="number" name="orders_variation_pct" class="form-control" value="{{ old('orders_variation_pct', 10) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">+/- 10%</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('AOV Variation (%)') }} *</label>
                            <input type="number" name="aov_variation_pct" class="form-control" value="{{ old('aov_variation_pct', 8) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">+/- 8%</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Delivery Fee Variation (%)') }} *</label>
                            <input type="number" name="delivery_fee_variation_pct" class="form-control" value="{{ old('delivery_fee_variation_pct', 7) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">+/- 7%</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Driver Count Variation (%)') }} *</label>
                            <input type="number" name="driver_count_variation_pct" class="form-control" value="{{ old('driver_count_variation_pct', 15) }}" step="0.01" min="0" max="100" required>
                            <small class="text-muted">+/- 15%</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.index') }}" class="btn btn-outline-secondary">{{ translate('Cancel') }}</a>
                <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Create Configuration') }}</button>
            </div>
        </form>
    </div>
@endsection
