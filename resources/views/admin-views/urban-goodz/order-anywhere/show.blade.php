@extends('layouts.admin.app')

@section('title', translate('Order Anywhere Request Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $request->request_number }}</h1>
            <a href="{{ route('admin.urban-goodz.order-anywhere.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Request details') }}</h5></div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">{{ translate('Customer') }}</dt><dd class="col-sm-8">{{ $request->customer_name ?? $request->customer_id ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Phone') }}</dt><dd class="col-sm-8">{{ $request->customer_phone ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Email') }}</dt><dd class="col-sm-8">{{ $request->customer_email ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Store/Vendor') }}</dt><dd class="col-sm-8">{{ $request->store_vendor_name ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Address/Website') }}</dt><dd class="col-sm-8">{{ $request->store_vendor_address_or_website ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Item details') }}</dt><dd class="col-sm-8">{{ $request->item_details ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Request details') }}</dt><dd class="col-sm-8">{{ $request->request_details ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Quantity') }}</dt><dd class="col-sm-8">{{ $request->quantity ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Budget') }}</dt><dd class="col-sm-8">{{ $request->budget_estimate ? '$'.$request->budget_estimate : '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Quote') }}</dt><dd class="col-sm-8">{{ $request->quote_amount ? '$'.$request->quote_amount : '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Authorized') }}</dt><dd class="col-sm-8">{{ $request->authorized_amount ? '$'.$request->authorized_amount : '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Captured') }}</dt><dd class="col-sm-8">{{ $request->captured_amount ? '$'.$request->captured_amount : '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Refunded') }}</dt><dd class="col-sm-8">{{ $request->refunded_amount ? '$'.$request->refunded_amount : '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Payment status') }}</dt><dd class="col-sm-8"><span class="badge badge-soft-info">{{ $request->payment_status ?? 'unquoted' }}</span></dd>
                            <dt class="col-sm-4">{{ translate('Receipt') }}</dt><dd class="col-sm-8">{{ $request->receipt_path ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Vendor') }}</dt><dd class="col-sm-8">{{ $request->vendor_id ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Vendor status') }}</dt><dd class="col-sm-8">{{ $request->vendor_status ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Vendor quote') }}</dt><dd class="col-sm-8">{{ $request->vendor_quote_amount ? '$'.$request->vendor_quote_amount : '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Vendor notes') }}</dt><dd class="col-sm-8">{{ $request->vendor_notes ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Assigned driver') }}</dt><dd class="col-sm-8">{{ $request->assigned_delivery_man_id ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Status') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.order-anywhere.status', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <select name="status" class="form-control mb-3">
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ $request->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Update status') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Admin notes') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.order-anywhere.notes', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <textarea name="admin_notes" class="form-control mb-3" rows="6">{{ $request->admin_notes }}</textarea>
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Save notes') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Assignment') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.order-anywhere.assign', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <label class="input-label">{{ translate('Vendor ID') }}</label>
                            <input name="vendor_id" class="form-control mb-3" value="{{ $request->vendor_id }}">
                            <label class="input-label">{{ translate('Delivery Man ID') }}</label>
                            <input name="assigned_delivery_man_id" class="form-control mb-3" value="{{ $request->assigned_delivery_man_id }}">
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Save assignment') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Quote') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.order-anywhere.quote', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <input name="quote_amount" class="form-control mb-3" value="{{ $request->quote_amount }}" placeholder="{{ translate('Quote amount') }}">
                            <input name="final_amount" class="form-control mb-3" value="{{ $request->final_amount }}" placeholder="{{ translate('Final amount') }}">
                            <input name="quote_reference" class="form-control mb-3" placeholder="{{ translate('Quote reference') }}">
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Save quote') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Capture') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.order-anywhere.capture', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <input name="captured_amount" class="form-control mb-3" value="{{ $request->captured_amount ?? $request->authorized_amount ?? $request->final_amount }}" placeholder="{{ translate('Captured amount') }}">
                            <input name="payment_method" class="form-control mb-3" value="{{ $request->payment_method ?? 'manual' }}" placeholder="{{ translate('Payment method') }}">
                            <input name="capture_reference" class="form-control mb-3" placeholder="{{ translate('Capture reference') }}">
                            <input name="platform_fee" class="form-control mb-3" placeholder="{{ translate('Platform fee') }}">
                            <input name="vendor_amount" class="form-control mb-3" placeholder="{{ translate('Vendor amount') }}">
                            <input name="driver_amount" class="form-control mb-3" placeholder="{{ translate('Driver amount') }}">
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Capture and split') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Refund') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.order-anywhere.refund', $request->id) }}">
                            @csrf
                            @method('PUT')
                            <input name="refund_amount" class="form-control mb-3" placeholder="{{ translate('Refund amount') }}">
                            <input name="refund_reference" class="form-control mb-3" placeholder="{{ translate('Refund reference') }}">
                            <textarea name="reason" class="form-control mb-3" rows="3" placeholder="{{ translate('Reason') }}"></textarea>
                            <button class="btn btn--danger btn-block" type="submit">{{ translate('Create refund ledger') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5 class="card-title mb-0">{{ translate('Payment ledger') }}</h5></div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Ledger #') }}</th>
                        <th>{{ translate('Event') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Reference') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($ledgers as $ledger)
                        <tr>
                            <td>{{ $ledger->ledger_number }}</td>
                            <td>{{ $ledger->event_type }} / {{ $ledger->direction }}</td>
                            <td>{{ $ledger->currency }} {{ $ledger->amount }}</td>
                            <td>{{ $ledger->payment_status }}</td>
                            <td>{{ $ledger->reference ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">{{ translate('No ledger entries found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5 class="card-title mb-0">{{ translate('Payment splits') }}</h5></div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Recipient') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($splits as $split)
                        <tr>
                            <td>{{ $split->recipient_type }} {{ $split->recipient_id ? '#'.$split->recipient_id : '' }}</td>
                            <td>{{ $split->split_type }}</td>
                            <td>{{ $split->currency }} {{ $split->amount }}</td>
                            <td>{{ $split->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">{{ translate('No split entries found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
