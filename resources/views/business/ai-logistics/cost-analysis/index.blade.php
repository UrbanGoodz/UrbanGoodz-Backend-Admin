@extends('business.layouts.app')

@section('title', translate('Cost Analysis'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Cost Analysis') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Cost Analysis') }}</h1>
    </div>

    <form method="GET" class="d-flex gap-2 mb-3">
        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
        <button type="submit" class="btn btn-outline-secondary">{{ translate('Apply') }}</button>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100" style="border-left: 4px solid #28a745;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Revenue') }}</h6><h3>${{ number_format($totalRevenue, 2) }}</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100" style="border-left: 4px solid #dc3545;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Cost') }}</h6><h3>${{ number_format($totalCost, 2) }}</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100" style="border-left: 4px solid #007bff;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Margin') }}</h6><h3>{{ $margin }}%</h3></div></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ translate('Cost by Month') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Month') }}</th><th>{{ translate('Invoiced') }}</th></tr></thead>
                    <tbody>
                        @forelse($costByMonth as $month => $amount)
                        <tr><td>{{ $month }}</td><td>${{ number_format($amount, 2) }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">{{ translate('No invoices in this date range.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
