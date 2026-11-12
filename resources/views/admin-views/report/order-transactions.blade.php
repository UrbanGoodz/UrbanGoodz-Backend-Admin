@extends('layouts.admin.app')

@section('title', translate('Order Transactions'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-money"></i></span>
                <span>{{ translate('Order Transactions') }}</span>
                <span class="badge badge-soft-dark ms-2">{{ $order_transactions->total() }}</span>
            </h1>
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Order') }}</th>
                            <th>{{ translate('Order Amount') }}</th>
                            <th>{{ translate('Store Amount') }}</th>
                            <th>{{ translate('Admin Commission') }}</th>
                            <th>{{ translate('Delivery Charge') }}</th>
                            <th>{{ translate('Received By') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order_transactions as $key => $transaction)
                            <tr>
                                <td>{{ $order_transactions->firstItem() + $key }}</td>
                                <td>
                                    @if ($transaction->order_id)
                                        <a class="text-body" href="{{ route('admin.order.details', ['id' => $transaction->order_id]) }}">
                                            #{{ $transaction->order_id }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($transaction->order_amount ?? 0) }}</td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($transaction->store_amount ?? 0) }}</td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($transaction->admin_commission ?? 0) }}</td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($transaction->delivery_charge ?? 0) }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', (string) $transaction->received_by) }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $transaction->status ? 'success' : 'warning' }}">
                                        {{ $transaction->status ? translate('settled') : translate('pending') }}
                                    </span>
                                </td>
                                <td>{{ $transaction->created_at ? $transaction->created_at->format('d M Y') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">{{ translate('no_data_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="page-area px-4 pb-3">
                {!! $order_transactions->links() !!}
            </div>
        </div>
    </div>
@endsection
