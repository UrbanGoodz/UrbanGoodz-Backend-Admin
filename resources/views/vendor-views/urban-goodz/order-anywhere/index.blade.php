@extends('layouts.vendor.app')

@section('title', translate('Order Anywhere'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Assigned Order Anywhere Requests') }}</h1>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Request #') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Details') }}</th>
                        <th>{{ translate('Budget') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Vendor status') }}</th>
                        <th>{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->request_number }}</td>
                            <td>{{ $request->customer_name ?? $request->customer_id ?? '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($request->request_details ?? $request->item_details ?? '-', 80) }}</td>
                            <td>{{ $request->budget_estimate ? '$'.$request->budget_estimate : '-' }}</td>
                            <td>{{ $request->status }}</td>
                            <td>{{ $request->vendor_status ?? '-' }}</td>
                            <td><a href="{{ route('vendor.urban-goodz.order-anywhere.show', $request->id) }}" class="btn btn-sm btn--primary">{{ translate('View') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">{{ translate('No assigned requests found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $requests->links() }}</div>
        </div>
    </div>
@endsection
