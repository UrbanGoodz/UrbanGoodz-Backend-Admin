@extends('business.layouts.app')

@section('title', translate('Invoices'))

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">{{ translate('Invoices') }}</h1>
    </div>

    @if($invoices->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No invoices yet') }}</h5>
            <p class="text-muted mb-0" style="color: #6c757d !important; max-width: 450px; margin: 0 auto;">
                {{ translate('Invoices will appear here once they are generated for your routes and deliveries.') }}
            </p>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Invoice') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Route') }}</th>
                            <th>{{ translate('Total') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Date') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $inv)
                        <tr>
                            <td><code>{{ $inv->invoice_number }}</code></td>
                            <td>{{ ucfirst($inv->invoice_type) }}</td>
                            <td>{{ $inv->route?->route_name ?? '-' }}</td>
                            <td>${{ number_format($inv->total, 2) }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $inv->status === 'paid' ? 'success' : ($inv->status === 'overdue' ? 'danger' : ($inv->status === 'sent' ? 'info' : 'secondary')) }}">
                                    {{ ucfirst($inv->status) }}
                                </span>
                            </td>
                            <td>{{ $inv->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('business.invoices.show', $inv->id) }}" class="btn btn-sm btn--primary">
                                    {{ translate('View') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">{{ translate('No invoices found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($invoices->hasPages())
        <div class="card-footer">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>
    @endif
@endsection
