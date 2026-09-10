@extends('business.layouts.app')

@section('title', translate('Invoice Insights'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Invoice Insights') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Invoice Insights') }}</h1>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100" style="border-left: 4px solid #dc3545;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Unpaid') }}</h6><h3>${{ number_format($unpaidTotal, 2) }}</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100" style="border-left: 4px solid #28a745;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Paid') }}</h6><h3>${{ number_format($paidTotal, 2) }}</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100" style="border-left: 4px solid #ffc107;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Overdue') }}</h6><h3>{{ $overdueCount }}</h3></div></div></div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Invoice #') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Total') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse($invoices as $inv)
                        <tr>
                            <td><a href="{{ route('business.invoices.show', $inv->id) }}">{{ $inv->invoice_number }}</a></td>
                            <td>{{ ucwords($inv->invoice_type) }}</td>
                            <td>${{ number_format($inv->total, 2) }}</td>
                            <td><span class="badge badge-soft-{{ $inv->status === 'paid' ? 'success' : 'warning' }}">{{ ucwords($inv->status) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ translate('No invoices yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $invoices->links() }}</div>
        @endif
    </div>
@endsection
