@extends('layouts.vendor.app')

@section('title', translate('Order Anywhere Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $request->request_number }}</h1>
            <a href="{{ route('vendor.urban-goodz.order-anywhere.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
        </div>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <p><strong>{{ translate('Store/Vendor requested') }}:</strong> {{ $request->store_vendor_name ?? '-' }}</p>
                        <p><strong>{{ translate('Address/Website') }}:</strong> {{ $request->store_vendor_address_or_website ?? '-' }}</p>
                        <p><strong>{{ translate('Item details') }}:</strong> {{ $request->item_details ?? '-' }}</p>
                        <p><strong>{{ translate('Request details') }}:</strong> {{ $request->request_details ?? '-' }}</p>
                        <p><strong>{{ translate('Quantity') }}:</strong> {{ $request->quantity ?? '-' }}</p>
                        <p><strong>{{ translate('Budget') }}:</strong> {{ $request->budget_estimate ? '$'.$request->budget_estimate : '-' }}</p>
                        <p><strong>{{ translate('Payment status') }}:</strong> {{ $request->payment_status ?? 'unquoted' }}</p>
                        <p><strong>{{ translate('Quote') }}:</strong> {{ $request->quote_amount ? '$'.$request->quote_amount : '-' }}</p>
                        <p><strong>{{ translate('Captured') }}:</strong> {{ $request->captured_amount ? '$'.$request->captured_amount : '-' }}</p>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('My Urban Goodz earnings') }}</h5></div>
                    <div class="card-body">
                        @forelse($splits as $split)
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span>{{ $split->split_type }}</span>
                                <strong>{{ $split->currency }} {{ $split->amount }}</strong>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ translate('No vendor split entries yet') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Vendor response') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('vendor.urban-goodz.order-anywhere.update', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <select name="vendor_status" class="form-control mb-3">
                                @foreach(['accepted','declined','quote_submitted','in_progress','ready_for_pickup','completed'] as $status)
                                    <option value="{{ $status }}" {{ $request->vendor_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                @endforeach
                            </select>
                            <input name="vendor_quote_amount" class="form-control mb-3" value="{{ $request->vendor_quote_amount }}" placeholder="{{ translate('Quote amount') }}">
                            <textarea name="vendor_notes" class="form-control mb-3" rows="5" placeholder="{{ translate('Vendor notes') }}">{{ $request->vendor_notes }}</textarea>
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Save response') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
