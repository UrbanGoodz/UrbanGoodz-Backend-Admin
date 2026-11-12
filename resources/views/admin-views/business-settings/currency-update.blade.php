@extends('layouts.admin.app')

@section('title', translate('Update Currency'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-dollar"></i></span>
                <span>{{ translate('Update Currency') }}</span>
            </h1>
        </div>

        @if (!$currency)
            {{-- currency_edit() uses find(), which returns null for an id that is
                 gone. Say so rather than dying on a property of null. --}}
            <div class="card">
                <div class="card-body text-center py-5">
                    <h5 class="mb-3">{{ translate('This currency no longer exists.') }}</h5>
                    <a href="{{ route('admin.business-settings.currency-add') }}" class="btn btn--primary">
                        {{ translate('Back to Currency Setup') }}
                    </a>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.business-settings.currency-update', [$currency->id]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="country">{{ translate('Country') }}</label>
                                <input type="text" id="country" name="country" class="form-control"
                                       value="{{ old('country', $currency->country) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="currency_code">{{ translate('Currency Code') }}</label>
                                <input type="text" id="currency_code" name="currency_code" class="form-control"
                                       value="{{ old('currency_code', $currency->currency_code) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="currency_symbol">{{ translate('Currency Symbol') }}</label>
                                <input type="text" id="currency_symbol" name="currency_symbol" class="form-control"
                                       value="{{ old('currency_symbol', $currency->currency_symbol) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="exchange_rate">{{ translate('Exchange Rate') }}</label>
                                <input type="number" step="0.000001" min="0" id="exchange_rate" name="exchange_rate"
                                       class="form-control" value="{{ old('exchange_rate', $currency->exchange_rate) }}" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('admin.business-settings.currency-add') }}"
                               class="btn btn-secondary">{{ translate('Cancel') }}</a>
                            <button type="submit" class="btn btn--primary">{{ translate('Update') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
