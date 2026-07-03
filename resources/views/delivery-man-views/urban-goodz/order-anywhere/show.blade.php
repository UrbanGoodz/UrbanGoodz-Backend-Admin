@extends('delivery-man-views.urban-goodz.layout')

@section('title', 'Order Anywhere Job')

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">{{ $request->request_number }}</h1>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <p><strong>Pickup:</strong> {{ $request->store_vendor_name ?? '-' }}</p>
                    <p><strong>Address/Website:</strong> {{ $request->store_vendor_address_or_website ?? '-' }}</p>
                    <p><strong>Item details:</strong> {{ $request->item_details ?? '-' }}</p>
                    <p><strong>Request details:</strong> {{ $request->request_details ?? '-' }}</p>
                    <p><strong>Payment status:</strong> {{ $request->payment_status ?? 'unquoted' }}</p>
                    <p><strong>Captured:</strong> {{ $request->captured_amount ? '$'.$request->captured_amount : '-' }}</p>
                    <p><strong>Driver notes:</strong> {{ $request->driver_notes ?? '-' }}</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h5 class="card-title mb-0">My Urban Goodz earnings</h5></div>
                <div class="card-body">
                    @forelse($splits as $split)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ $split->split_type }}</span>
                            <strong>{{ $split->currency }} {{ $split->amount }}</strong>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No driver split entries yet</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Update status</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('delivery-man.urban-goodz.order-anywhere.status', $request->id) }}">
                        @csrf
                        @method('PUT')
                        <select name="driver_task_status" class="form-control mb-3">
                            @foreach(['accepted','picked_up','en_route','delivered','issue_reported'] as $status)
                                <option value="{{ $status }}" {{ $request->driver_task_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                            @endforeach
                        </select>
                        <textarea name="driver_notes" class="form-control mb-3" rows="5" placeholder="Driver notes">{{ $request->driver_notes }}</textarea>
                        <button class="btn btn--primary btn-block" type="submit">Save status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
