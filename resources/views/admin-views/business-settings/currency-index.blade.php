@extends('layouts.admin.app')

@section('title', translate('Currency Setup'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-dollar"></i></span>
                <span>{{ translate('Currency Setup') }}</span>
            </h1>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Add New Currency') }}</h5></div>
                    <div class="card-body">
                        {{-- The POST on this URL has no route name in the route table,
                             so the URL is used directly rather than route(). --}}
                        <form action="{{ url('admin/business-settings/currency-add') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="country">{{ translate('Country') }}</label>
                                <input type="text" id="country" name="country" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="currency_code">{{ translate('Currency Code') }}</label>
                                <input type="text" id="currency_code" name="currency_code" class="form-control"
                                       placeholder="{{ translate('e.g. USD') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="currency_symbol">{{ translate('Currency Symbol') }}</label>
                                <input type="text" id="currency_symbol" name="currency_symbol" class="form-control"
                                       placeholder="{{ translate('e.g. $') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="exchange_rate">{{ translate('Exchange Rate') }}</label>
                                <input type="number" step="0.000001" min="0" id="exchange_rate" name="exchange_rate"
                                       class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            {{ translate('Currency List') }}
                            <span class="badge badge-soft-dark ms-1">{{ count($currencies ?? []) }}</span>
                        </h5>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th>{{ translate('Country') }}</th>
                                    <th>{{ translate('Code') }}</th>
                                    <th>{{ translate('Symbol') }}</th>
                                    <th>{{ translate('Exchange Rate') }}</th>
                                    <th class="text-center">{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($currencies ?? []) as $key => $currency)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $currency->country }}</td>
                                        <td>{{ $currency->currency_code }}</td>
                                        <td>{{ $currency->currency_symbol }}</td>
                                        <td>{{ $currency->exchange_rate }}</td>
                                        <td class="text-center">
                                            <a class="btn btn-sm btn-white"
                                               href="{{ route('admin.business-settings.currency-update', [$currency->id]) }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <form class="d-inline" method="POST"
                                                  action="{{ route('admin.business-settings.currency-delete', [$currency->id]) }}"
                                                  onsubmit="return confirm('{{ translate('Delete this currency?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-white text-danger">
                                                    <i class="tio-delete"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            {{ translate('no_data_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
