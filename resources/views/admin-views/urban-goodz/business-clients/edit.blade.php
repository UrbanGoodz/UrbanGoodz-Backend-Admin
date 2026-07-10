@extends('layouts.admin.app')

@section('title', translate('Edit') . ' - ' . $client->company_name)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Edit') }}: {{ $client->company_name }}</h1>
            <a href="{{ route('admin.urban-goodz.business-clients.show', $client->id) }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back to Client') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.business-clients.update', $client->id) }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-header"><h5>{{ translate('Company Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Company Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required value="{{ old('company_name', $client->company_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Legal Name') }}</label>
                            <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name', $client->legal_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Contact Name') }}</label>
                            <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $client->contact_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Business Type') }}</label>
                            <input type="text" name="business_type" class="form-control" value="{{ old('business_type', $client->business_type) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Email') }} <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email', $client->email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Contact Email') }}</label>
                            <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $client->contact_email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Billing Email') }}</label>
                            <input type="email" name="billing_email" class="form-control" value="{{ old('billing_email', $client->billing_email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Phone') }}</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $client->phone) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Contact Phone') }}</label>
                            <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $client->contact_phone) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Billing Phone') }}</label>
                            <input type="text" name="billing_phone" class="form-control" value="{{ old('billing_phone', $client->billing_phone) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Website') }}</label>
                            <input type="url" name="website" class="form-control" value="{{ old('website', $client->website) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Tax ID') }}</label>
                            <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $client->tax_id) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <select name="status" class="form-control">
                                @foreach($statuses as $st)
                                    <option value="{{ $st }}" {{ old('status', $client->status) === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h5>{{ translate('Address') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">{{ translate('Address') }}</label>
                            <textarea name="address" class="form-control" rows="2">{{ old('address', $client->address) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('City') }}</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $client->city) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('State') }}</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state', $client->state) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Postal Code') }}</label>
                            <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $client->postal_code) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Country') }}</label>
                            <input type="text" name="country" class="form-control" value="{{ old('country', $client->country) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h5>{{ translate('Billing & Payment') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Billing Terms') }}</label>
                            <select name="billing_terms" class="form-control">
                                @foreach($billingTerms as $bt)
                                    <option value="{{ $bt }}" {{ old('billing_terms', $client->billing_terms) === $bt ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $bt)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Credit Limit') }} ($)</label>
                            <input type="number" name="credit_limit" class="form-control" step="0.01" min="0" value="{{ old('credit_limit', $client->credit_limit) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Payment Method Status') }}</label>
                            <select name="payment_method_status" class="form-control">
                                @foreach($paymentMethodStatuses as $pms)
                                    <option value="{{ $pms }}" {{ old('payment_method_status', $client->payment_method_status) === $pms ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $pms)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h5>{{ translate('Notes') }}</h5></div>
                <div class="card-body">
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $client->notes) }}</textarea>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('Update Business Client') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
