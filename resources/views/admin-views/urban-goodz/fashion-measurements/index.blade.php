@extends('layouts.admin.app')

@section('title', translate('Urban Goodz Fashion Measurements'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Urban Goodz') }} - {{ translate('Fashion Measurements') }}</h1>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ translate('Total requests') }}</h6>
                        <h3>{{ $totalRequests }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ translate('Pending review') }}</h6>
                        <h3>{{ $pendingReview }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ translate('Ready for tailor review') }}</h6>
                        <h3>{{ $readyForTailorReview }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ translate('Free tester mode') }}</h6>
                        <h3>{{ !empty($settings['measurement_free_tester_mode']) ? translate('On') : translate('Off') }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ translate('Measurement requests') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('ID') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Vendor / Tailor') }}</th>
                        <th>{{ translate('Platform fee') }}</th>
                        <th>{{ translate('Vendor fee') }}</th>
                        <th>{{ translate('Total') }}</th>
                        <th>{{ translate('Payment') }}</th>
                        <th>{{ translate('Privacy') }}</th>
                        <th>{{ translate('Face blur') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->id }}</td>
                            <td>{{ $request->customer_id ?? '-' }}</td>
                            <td>{{ $request->vendor_id ?? '-' }} / {{ $request->tailor_id ?? '-' }}</td>
                            <td>{{ $request->currency }} {{ $request->platform_measurement_fee }}</td>
                            <td>{{ $request->currency }} {{ $request->vendor_review_fee }}</td>
                            <td>{{ $request->currency }} {{ $request->total_measurement_fee }}</td>
                            <td>{{ $request->payment_status }}</td>
                            <td>{{ $request->privacy_review_status }}</td>
                            <td>{{ $request->face_blur_status }}</td>
                            <td>{{ $request->measurement_status }} / {{ $request->review_status }}</td>
                            <td>{{ optional($request->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center">{{ translate('No measurement requests found') }}</td>
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
