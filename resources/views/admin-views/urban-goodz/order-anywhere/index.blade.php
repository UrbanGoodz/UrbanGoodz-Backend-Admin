@extends('layouts.admin.app')

@section('title', translate('Order Anywhere Requests'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Order Anywhere Requests') }}</h1>
            <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Urban Goodz') }}</a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body"><h6>{{ translate('Total') }}</h6><h3>{{ $totalRequests }}</h3></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><h6>{{ translate('Pending review') }}</h6><h3>{{ $pendingReview }}</h3></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><h6>{{ translate('Active') }}</h6><h3>{{ $activeRequests }}</h3></div></div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Request #') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Store/Vendor') }}</th>
                        <th>{{ translate('Request Details') }}</th>
                        <th>{{ translate('Quantity') }}</th>
                        <th>{{ translate('Budget') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Created At') }}</th>
                        <th>{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->request_number }}</td>
                            <td>{{ $request->customer_name ?? $request->customer_id ?? '-' }}</td>
                            <td>{{ $request->store_vendor_name ?? '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($request->request_details ?? $request->item_details ?? '-', 80) }}</td>
                            <td>{{ $request->quantity ?? '-' }}</td>
                            <td>{{ $request->budget_estimate ? '$'.$request->budget_estimate : '-' }}</td>
                            <td><span class="badge badge-soft-info">{{ $request->status }}</span></td>
                            <td>{{ optional($request->created_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.urban-goodz.order-anywhere.show', $request->id) }}" class="btn btn-sm btn--primary">{{ translate('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center">{{ translate('No Order Anywhere requests found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $requests->links() }}</div>
        </div>
    </div>
@endsection
