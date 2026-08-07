@extends('layouts.admin.app')
@section('title', 'Payout Settings')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Home') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Payout Settings</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">Same-day payout pricing</h1>
                <p class="text-muted mb-0">
                    Weekly payouts are always free. Same-day money costs Urban Goodz something to front,
                    so it carries a fee. Changes apply to new requests only &mdash; a payout keeps the rate it was quoted.
                </p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.urban-goodz.payouts.settings.update') }}" method="post">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="enabled"
                           name="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                    <label class="custom-control-label" for="enabled">Offer same-day payouts</label>
                </div>
                <small class="text-muted d-block mt-1">
                    Switched off, everyone is paid on the weekly schedule and no fee is charged to anybody.
                </small>

                <div class="form-group mt-4 mb-0" style="max-width:280px">
                    <label for="minimum_amount">Minimum balance for same-day ($)</label>
                    <input type="number" step="0.01" min="0" class="form-control"
                           id="minimum_amount" name="minimum_amount"
                           value="{{ old('minimum_amount', number_format($minimumAmount, 2, '.', '')) }}" required>
                    <small class="text-muted">Below this, the balance goes out with the free weekly payout.</small>
                </div>
            </div>
        </div>

        <div class="row">
            @foreach ([
                ['who' => 'driver', 'title' => 'Drivers', 'percent' => $driverPercent, 'min' => $driverMin, 'cap' => $driverCap],
                ['who' => 'vendor', 'title' => 'Vendors', 'percent' => $vendorPercent, 'min' => $vendorMin, 'cap' => $vendorCap],
            ] as $g)
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title mb-0">{{ $g['title'] }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="{{ $g['who'] }}_percent">Fee (%)</label>
                                <input type="number" step="0.01" min="0" max="50" class="form-control"
                                       id="{{ $g['who'] }}_percent" name="{{ $g['who'] }}_percent"
                                       value="{{ old($g['who'].'_percent', number_format($g['percent'], 2, '.', '')) }}" required>
                            </div>
                            <div class="form-group">
                                <label for="{{ $g['who'] }}_min">Minimum fee ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       id="{{ $g['who'] }}_min" name="{{ $g['who'] }}_min"
                                       value="{{ old($g['who'].'_min', number_format($g['min'], 2, '.', '')) }}" required>
                            </div>
                            <div class="form-group mb-0">
                                <label for="{{ $g['who'] }}_cap">Maximum fee ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       id="{{ $g['who'] }}_cap" name="{{ $g['who'] }}_cap"
                                       value="{{ old($g['who'].'_cap', number_format($g['cap'], 2, '.', '')) }}" required>
                                <small class="text-muted">0 means no cap.</small>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary mb-4">Save settings</button>
    </form>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">What these rates actually charge</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                A percentage with a minimum and a cap does not obviously translate into what somebody
                loses on a cash-out. These are the currently saved rates applied to real amounts.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Cash out</th>
                            <th class="text-right">Driver fee</th>
                            <th class="text-right">Driver receives</th>
                            <th class="text-right">Vendor fee</th>
                            <th class="text-right">Vendor receives</th>
                            <th class="text-right">Weekly</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($samples as $s)
                            <tr>
                                <td>${{ number_format($s['amount'], 2) }}</td>
                                @if ($s['driver']['available'])
                                    <td class="text-right">${{ number_format($s['driver']['fee'], 2) }}</td>
                                    <td class="text-right">${{ number_format($s['driver']['net'], 2) }}</td>
                                @else
                                    <td class="text-right text-muted" colspan="2">{{ $s['driver']['message'] }}</td>
                                @endif

                                @if ($s['vendor']['available'])
                                    <td class="text-right">${{ number_format($s['vendor']['fee'], 2) }}</td>
                                    <td class="text-right">${{ number_format($s['vendor']['net'], 2) }}</td>
                                @else
                                    <td class="text-right text-muted" colspan="2">&mdash;</td>
                                @endif

                                <td class="text-right text-success">Free</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
