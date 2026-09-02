@extends('layouts.admin.app')

@section('title', translate('Edit Reconstruction Configuration'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', $config->id) }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Edit') }}: {{ $config->configuration_name }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.update', $config->id) }}">
            @csrf
            @method('PUT')
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Configuration Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Configuration Name') }} *</label>
                            <input type="text" name="configuration_name" class="form-control" value="{{ old('configuration_name', $config->configuration_name) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Start Date') }} *</label>
                            <input type="date" name="reconstruction_start_date" class="form-control" value="{{ old('reconstruction_start_date', $config->reconstruction_start_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('End Date') }} *</label>
                            <input type="date" name="reconstruction_end_date" class="form-control" value="{{ old('reconstruction_end_date', $config->reconstruction_end_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Owner/Founder Name') }}</label>
                            <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $config->owner_name) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Owner Non-Delivery Months') }}</label>
                            <input type="text" name="owner_non_delivery_months" class="form-control" value="{{ old('owner_non_delivery_months', implode(',', $config->owner_non_delivery_months ?? [12,1,2])) }}" placeholder="12,1,2">
                            <small class="text-muted">Comma-separated month numbers</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Historical Operating Assumptions') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Monthly Orders') }} *</label>
                            <input type="number" name="baseline_monthly_orders" class="form-control" value="{{ old('baseline_monthly_orders', $config->baseline_monthly_orders) }}" step="1" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Order Value ($)') }} *</label>
                            <input type="number" name="baseline_average_order_value" class="form-control" value="{{ old('baseline_average_order_value', $config->baseline_average_order_value) }}" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Order Commission (%)') }} *</label>
                            <input type="number" name="baseline_order_commission_pct" class="form-control" value="{{ old('baseline_order_commission_pct', $config->baseline_order_commission_pct) }}" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Delivery Fee ($)') }} *</label>
                            <input type="number" name="baseline_delivery_fee" class="form-control" value="{{ old('baseline_delivery_fee', $config->baseline_delivery_fee) }}" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Platform Share of Delivery Fee (%)') }} *</label>
                            <input type="number" name="baseline_platform_delivery_fee_pct" class="form-control" value="{{ old('baseline_platform_delivery_fee_pct', $config->baseline_platform_delivery_fee_pct) }}" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Active Drivers') }} *</label>
                            <input type="number" name="baseline_active_drivers" class="form-control" value="{{ old('baseline_active_drivers', $config->baseline_active_drivers) }}" step="1" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Avg Monthly Net Income ($)') }} *</label>
                            <input type="number" name="baseline_avg_monthly_net" class="form-control" value="{{ old('baseline_avg_monthly_net', $config->baseline_avg_monthly_net) }}" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Operating Expense Ratio (%)') }} *</label>
                            <input type="number" name="operating_expense_ratio" class="form-control" value="{{ old('operating_expense_ratio', $config->operating_expense_ratio) }}" step="0.01" min="0" max="100" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Variation Settings') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Orders Variation (%)') }} *</label>
                            <input type="number" name="orders_variation_pct" class="form-control" value="{{ old('orders_variation_pct', $config->orders_variation_pct) }}" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('AOV Variation (%)') }} *</label>
                            <input type="number" name="aov_variation_pct" class="form-control" value="{{ old('aov_variation_pct', $config->aov_variation_pct) }}" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Delivery Fee Variation (%)') }} *</label>
                            <input type="number" name="delivery_fee_variation_pct" class="form-control" value="{{ old('delivery_fee_variation_pct', $config->delivery_fee_variation_pct) }}" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Driver Count Variation (%)') }} *</label>
                            <input type="number" name="driver_count_variation_pct" class="form-control" value="{{ old('driver_count_variation_pct', $config->driver_count_variation_pct) }}" step="0.01" min="0" max="100" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.destroy', $config->id) }}" onsubmit="return confirm('{{ translate('Delete this configuration and all its snapshots?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="tio-delete"></i> {{ translate('Delete') }}</button>
                </form>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', $config->id) }}" class="btn btn-outline-secondary">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Update Configuration') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
