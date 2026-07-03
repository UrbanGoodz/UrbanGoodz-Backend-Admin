@extends('delivery-man-views.urban-goodz.layout')

@section('title', 'Order Anywhere Jobs')

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">Assigned Order Anywhere Jobs</h1>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>Request #</th>
                    <th>Pickup</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>Driver status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($requests as $request)
                    <tr>
                        <td>{{ $request->request_number }}</td>
                        <td>{{ $request->store_vendor_name ?? '-' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($request->request_details ?? $request->item_details ?? '-', 80) }}</td>
                        <td>{{ $request->status }}</td>
                        <td>{{ $request->driver_task_status ?? '-' }}</td>
                        <td><a href="{{ route('delivery-man.urban-goodz.order-anywhere.show', $request->id) }}" class="btn btn-sm btn--primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No assigned jobs found</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $requests->links() }}</div>
    </div>
@endsection
