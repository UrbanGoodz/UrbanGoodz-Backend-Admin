@extends('layouts.admin.app')

@section('title', translate('Order Anywhere Requests'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Order Anywhere Requests') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $totalRequests }}</span>
                    </h1>
                    <div class="d-flex gap-2 mt-1">
                        <span class="badge badge-soft-info">{{ translate('Pending Review') }}: {{ $pendingReview }}</span>
                        <span class="badge badge-soft-warning">{{ translate('Active') }}: {{ $activeRequests }}</span>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper justify-content-end">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search by request number or name') }}"
                                   value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Request #') }}</th>
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Item') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Payment') }}</th>
                            <th>{{ translate('Vendor') }}</th>
                            <th>{{ translate('Date') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $key => $r)
                            <tr>
                                <td>{{ $requests->firstItem() + $key }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.order-anywhere.show', $r->id) }}" class="font-weight-bold">
                                        {{ $r->request_number }}
                                    </a>
                                </td>
                                <td>
                                    <div>{{ $r->customer_name }}</div>
                                    <div class="small text-muted">{{ $r->customer_phone }}</div>
                                </td>
                                <td>
                                    <div class="text-wrap" style="max-width:200px">{{ Str::limit($r->item_details ?? $r->request_details, 60) }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ in_array($r->status, ['completed','approved','vendor_accepted']) ? 'success' : (in_array($r->status, ['rejected','cancelled']) ? 'danger' : 'info') }}">
                                        {{ str_replace('_', ' ', $r->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($r->payment_status)
                                        <span class="badge badge-soft-{{ $r->payment_status === 'paid' ? 'success' : 'warning' }}">
                                            {{ $r->payment_status }}
                                        </span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('N/A') }}</span>
                                    @endif
                                </td>
                                <td>{{ $r->store_vendor_name ?? translate('Unassigned') }}</td>
                                <td>{{ $r->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.order-anywhere.show', $r->id) }}" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($requests) === 0)
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No requests found') }}</td>
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
