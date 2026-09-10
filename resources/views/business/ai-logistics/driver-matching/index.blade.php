@extends('business.layouts.app')

@section('title', translate('Driver Matching'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Driver Matching') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Driver Matching') }}</h1>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ translate('Your Fleet') }}</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light"><tr><th>{{ translate('Driver') }}</th><th>{{ translate('Phone') }}</th><th>{{ translate('Vehicle') }}</th><th>{{ translate('Availability') }}</th></tr></thead>
                            <tbody>
                                @forelse($drivers as $d)
                                <tr>
                                    <td>{{ $d['name'] }}</td>
                                    <td>{{ $d['phone'] }}</td>
                                    <td><small>{{ ucwords(str_replace('_',' ', $d['vehicle_type'])) }}</small></td>
                                    <td>
                                        @if($d['is_available'])
                                            <span class="badge badge-soft-success">{{ translate('Available') }}</span>
                                        @else
                                            <span class="badge badge-soft-warning">{{ $d['active_dispatches'] }} {{ translate('active') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">{{ translate('No drivers assigned to your fleet yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Open Loads') }}</h5>
                    <a href="{{ route('business.ai-logistics.dispatch.create') }}" class="btn btn-sm" style="background-color: var(--ug-primary); color: #fff;">{{ translate('Dispatch') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <tbody>
                                @forelse($openLoads as $load)
                                <tr>
                                    <td>{{ $load->load_number }}</td>
                                    <td><small>{{ $load->origin_city }} &rarr; {{ $load->destination_city }}</small></td>
                                    <td class="text-end">${{ number_format($load->payout_amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td class="text-center text-muted py-4">{{ translate('No open loads.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
