@extends('layouts.admin.app')

@section('title', translate('Order Anywhere - ') . $request->request_number)

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        {{ translate('Order Anywhere') }}: {{ $request->request_number }}
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.order-anywhere.index') }}" class="btn btn--secondary">{{ translate('Back to list') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Request Details') }}</h5>
                        <span class="badge badge-soft-{{ in_array($request->status, ['completed','approved','vendor_accepted']) ? 'success' : (in_array($request->status, ['rejected','cancelled']) ? 'danger' : 'info') }} fs-14">
                            {{ str_replace('_', ' ', $request->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Customer Name') }}</label>
                                <p>{{ $request->customer_name }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Contact') }}</label>
                                <p>{{ $request->customer_phone }} @if($request->customer_email) / {{ $request->customer_email }} @endif</p>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Store / Vendor') }}</label>
                                <p>{{ $request->store_vendor_name ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Store Address / Website') }}</label>
                                <p class="text-wrap">{{ $request->store_vendor_address_or_website ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-12">
                                <label class="font-weight-bold">{{ translate('Request Details') }}</label>
                                <p class="text-wrap">{{ $request->request_details ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-12">
                                <label class="font-weight-bold">{{ translate('Item Details') }}</label>
                                <p class="text-wrap">{{ $request->item_details ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Quantity') }}</label>
                                <p>{{ $request->quantity ?? 1 }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Budget Estimate') }}</label>
                                <p>${{ number_format($request->budget_estimate ?? 0, 2) }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Quote Amount') }}</label>
                                <p>${{ number_format($request->quote_amount ?? 0, 2) }}</p>
                            </div>
                        </div>

                        @if($request->source_urls)
                            <div class="mt-3">
                                <label class="font-weight-bold">{{ translate('Source URLs') }}</label>
                                <ul class="list-unstyled">
                                    @foreach((array)$request->source_urls as $url)
                                        <li><a href="{{ $url }}" target="_blank" class="text-break">{{ $url }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>{{ translate('Financial Summary') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Item') }}</th>
                                        <th>{{ translate('Subtotal') }}</th>
                                        <th>{{ translate('Service Fee') }}</th>
                                        <th>{{ translate('Delivery Fee') }}</th>
                                        <th>{{ translate('Tax') }}</th>
                                        <th>{{ translate('Tip') }}</th>
                                        <th>{{ translate('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ Str::limit($request->item_details, 40) }}</td>
                                        <td>${{ number_format($request->item_subtotal ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->service_fee ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->delivery_fee ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->tax ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->tip ?? 0, 2) }}</td>
                                        <td class="font-weight-bold">
                                            ${{ number_format(($request->item_subtotal ?? 0) + ($request->service_fee ?? 0) + ($request->delivery_fee ?? 0) + ($request->tax ?? 0) + ($request->tip ?? 0), 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @if($request->final_amount)
                            <div class="mt-2 text-right">
                                <strong>{{ translate('Final Amount') }}: ${{ number_format($request->final_amount, 2) }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Status & Assignment') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.order-anywhere.status', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Update Status') }}</label>
                                <select name="status" class="form-control">
                                    @foreach($statuses as $s)
                                        <option value="{{ $s }}" {{ $request->status === $s ? 'selected' : '' }}>{{ str_replace('_', ' ', $s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Update') }}</button>
                        </form>

                        <hr>

                        <form action="{{ route('admin.urban-goodz.order-anywhere.assign', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Vendor ID') }}</label>
                                <input type="number" name="vendor_id" class="form-control" value="{{ $request->vendor_id }}" placeholder="{{ translate('Vendor ID') }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Delivery Man ID') }}</label>
                                <input type="number" name="assigned_delivery_man_id" class="form-control" value="{{ $request->assigned_delivery_man_id }}" placeholder="{{ translate('Delivery Man ID') }}">
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Assign') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>{{ translate('Admin Notes') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.order-anywhere.notes', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <textarea name="admin_notes" class="form-control" rows="3">{{ $request->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Save Notes') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>{{ translate('Quote / Capture / Refund') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.order-anywhere.quote', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Quote Amount') }}</label>
                                <input type="number" step="0.01" name="quote_amount" class="form-control" value="{{ $request->quote_amount }}" required>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Create Quote') }}</button>
                        </form>
                        <hr>
                        <form action="{{ route('admin.urban-goodz.order-anywhere.capture', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Capture Amount') }}</label>
                                <input type="number" step="0.01" name="captured_amount" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-success btn-block">{{ translate('Capture Payment') }}</button>
                        </form>
                        <hr>
                        <form action="{{ route('admin.urban-goodz.order-anywhere.refund', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Refund Amount') }}</label>
                                <input type="number" step="0.01" name="refund_amount" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-danger btn-block">{{ translate('Refund') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(count($ledgers) > 0)
            <div class="card mt-3">
                <div class="card-header">
                    <h5>{{ translate('Payment Ledgers') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ translate('Ledger #') }}</th>
                                    <th>{{ translate('Event') }}</th>
                                    <th>{{ translate('Direction') }}</th>
                                    <th>{{ translate('Amount') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ledgers as $l)
                                    <tr>
                                        <td>{{ $l->ledger_number }}</td>
                                        <td>{{ $l->event_type }}</td>
                                        <td>{{ $l->direction }}</td>
                                        <td>${{ number_format($l->amount, 2) }}</td>
                                        <td>{{ $l->payment_status }}</td>
                                        <td>{{ $l->created_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
