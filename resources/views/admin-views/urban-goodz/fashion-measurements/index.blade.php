@extends('layouts.admin.app')

@section('title', translate('Fashion Fit - Measurements'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Fashion Fit - Measurement Requests') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $totalRequests }}</span>
                    </h1>
                    <div class="d-flex gap-2 mt-1">
                        <span class="badge badge-soft-info">{{ translate('Pending Review') }}: {{ $pendingReview }}</span>
                        <span class="badge badge-soft-warning">{{ translate('Ready for Tailor') }}: {{ $readyForTailorReview }}</span>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Item Wanted') }}</th>
                            <th>{{ translate('Measurement Status') }}</th>
                            <th>{{ translate('Review Status') }}</th>
                            <th>{{ translate('Payment') }}</th>
                            <th>{{ translate('Tailor') }}</th>
                            <th>{{ translate('Date') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $key => $r)
                            <tr>
                                <td>{{ $requests->firstItem() + $key }}</td>
                                <td>{{ $r->customer_id }}</td>
                                <td>{{ $r->item_wanted ?? translate('Custom Garment') }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $r->measurement_status === 'approved' ? 'success' : 'info' }}">
                                        {{ str_replace('_', ' ', $r->measurement_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ $r->review_status === 'accepted' ? 'success' : ($r->review_status === 'pending' ? 'warning' : 'info') }}">
                                        {{ str_replace('_', ' ', $r->review_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ $r->payment_status === 'paid' ? 'success' : 'secondary' }}">
                                        {{ $r->payment_status }}
                                    </span>
                                </td>
                                <td>{{ $r->tailor_id ?? translate('Unassigned') }}</td>
                                <td>{{ $r->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.fashion-fit.show', $r->id) }}" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($requests) === 0)
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No measurement requests found') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
@endsection
