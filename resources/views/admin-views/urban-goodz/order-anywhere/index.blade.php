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

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Automatic dispatch') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.urban-goodz.order-anywhere.dispatch-settings') }}" method="POST">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="auto_dispatch" name="auto_dispatch" value="1" @checked($dispatchSettings['auto_dispatch'])>
                                <label class="custom-control-label" for="auto_dispatch">{{ translate('Dispatch automatically after payment (nearest driver first, then the next closest)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label>{{ translate('Driver radius (miles)') }}</label>
                            <input type="number" step="0.5" min="1" max="500" name="radius_miles" class="form-control" value="{{ $dispatchSettings['radius_miles'] }}">
                        </div>
                        <div class="col-md-3">
                            <label>{{ translate('Minutes each driver has to accept') }}</label>
                            <input type="number" min="1" max="60" name="offer_minutes" class="form-control" value="{{ $dispatchSettings['offer_minutes'] }}">
                        </div>
                        <div class="col-md-3">
                            <label>{{ translate('Alert admins after (minutes)') }}</label>
                            <input type="number" min="0" max="1440" name="escalate_after_minutes" class="form-control" value="{{ $dispatchSettings['escalate_after_minutes'] }}">
                        </div>
                        <div class="col-md-3">
                            <label>{{ translate('Cancel and release payment after (minutes, 0 = never)') }}</label>
                            <input type="number" min="0" max="10080" name="cancel_after_minutes" class="form-control" value="{{ $dispatchSettings['cancel_after_minutes'] }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn--primary mt-3">{{ translate('Save dispatch settings') }}</button>
                </form>
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
                                        <span class="badge badge-soft-{{ in_array($r->payment_status, ['captured', 'authorized', 'partially_captured']) ? 'success' : (in_array($r->payment_status, ['unpaid', 'awaiting_quote', 'quoted', 'awaiting_payment', 'payment_session_created', 'capture_pending']) ? 'warning' : 'danger') }}">
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
