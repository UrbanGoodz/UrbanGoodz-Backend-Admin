@extends('layouts.vendor.app')

@section('title', translate('Stylist Requests'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Stylist Requests') }}</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ translate('Request list') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('ID') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Manual measurements') }}</th>
                        <th>{{ translate('Photo-assisted') }}</th>
                        <th>{{ translate('Payment') }}</th>
                        <th>{{ translate('Vendor fee') }}</th>
                        <th>{{ translate('Review') }}</th>
                        <th>{{ translate('Stylist Notes') }}</th>
                        <th>{{ translate('Created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->id }}</td>
                            <td>{{ $request->customer_id ?? '-' }}</td>
                            <td>
                                {{ translate('Fit') }}: {{ $request->preferred_fit ?? '-' }}<br>
                                {{ translate('H') }} {{ $request->height ?? '-' }},
                                {{ translate('Chest') }} {{ $request->chest_bust ?? '-' }},
                                {{ translate('Waist') }} {{ $request->waist ?? '-' }},
                                {{ translate('Hips') }} {{ $request->hips ?? '-' }}
                            </td>
                            <td>{{ $request->source }} / {{ $request->measurement_status }}</td>
                            <td>{{ $request->payment_status }}</td>
                            <td>{{ $request->currency }} {{ $request->vendor_review_fee }}</td>
                            <td>{{ $request->review_status }}</td>
                            <td>{{ $request->tailor_notes ?? '-' }}</td>
                            <td>{{ optional($request->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">{{ translate('No measurement requests found') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
@endsection
