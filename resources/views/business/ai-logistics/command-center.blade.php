@extends('business.layouts.app')

@section('title', translate('AI Command Center'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ translate('AI Command Center') }}</li>
                </ol>
            </nav>
            <h1 class="page-header-title">{{ translate('AI Command Center') }}</h1>
            <p class="text-muted mb-0">{{ $client->business_name ?? $client->company_name ?? '' }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #007bff;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Active Loads') }}</h6><h3>{{ $active_loads }}</h3><small class="text-muted">{{ translate('of') }} {{ $total_loads }} {{ translate('total') }}</small></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #17a2b8;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Pool Packages') }}</h6><h3>{{ $pool_packages }}</h3></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #28a745;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Active Routes') }}</h6><h3>{{ $active_routes }}</h3></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #ffc107;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Available Drivers') }}</h6><h3>{{ $available_drivers }}</h3></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #6f42c1;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Pending Dispatches') }}</h6><h3>{{ $pending_dispatches }}</h3></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #dc3545;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Pending Exceptions') }}</h6><h3>{{ $pending_exceptions }}</h3></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #fd7e14;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Unpaid Invoices') }}</h6><h3>{{ $unpaid_invoices }}</h3></div></div></div>
        <div class="col-md-3 col-6"><div class="card h-100" style="border-left: 4px solid #20c997;"><div class="card-body py-3"><h6 class="text-muted mb-1">{{ translate('Docs Expiring Soon') }}</h6><h3>{{ $expiring_docs }}</h3></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Recent AI Recommendations') }}</h5>
                    <a href="{{ route('business.ai-logistics.copilot-recommendations') }}" class="btn btn-sm btn-outline-secondary">{{ translate('View All') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive"><table class="table table-hover mb-0"><tbody>
                        @forelse($recent_recommendations as $rec)
                            <tr><td>{{ ucwords(str_replace('_', ' ', $rec->recommendation_type)) }}</td><td class="text-muted small">{{ $rec->created_at->diffForHumans() }}</td></tr>
                        @empty
                            <tr><td class="text-center text-muted py-3">{{ translate('No pending recommendations') }}</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Recent Dispatches') }}</h5>
                    <a href="{{ route('business.ai-logistics.dispatches.index') }}" class="btn btn-sm btn-outline-secondary">{{ translate('View All') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive"><table class="table table-hover mb-0"><tbody>
                        @forelse($recent_dispatches as $d)
                            <tr>
                                <td>{{ $d->driver?->f_name }} {{ $d->driver?->l_name }}</td>
                                <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $d->status)) }}</span></td>
                                <td class="text-muted small">{{ $d->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3">{{ translate('No recent dispatches') }}</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            </div>
        </div>
    </div>
@endsection
