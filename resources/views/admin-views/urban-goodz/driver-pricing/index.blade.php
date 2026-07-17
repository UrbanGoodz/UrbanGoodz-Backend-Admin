@extends('layouts.admin.app')

@section('title', translate('Driver Pricing & Payouts'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h1 class="page-header-title">
                    <i class="tio-money nav-icon text-primary mr-1"></i>
                    <span>{{ translate('Driver Pricing & Payouts') }}</span>
                </h1>
                <div>
                    <a href="{{ route('admin.urban-goodz.driver-pricing.create') }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add Zone Override') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="js-nav-scroller hs-nav-scroller-horizontal mb-4">
            <ul class="nav nav-tabs page-header-tabs" id="driverPricingTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ !request('tab') || request('tab') === 'policies' ? 'active' : '' }}" href="?tab=policies" role="tab">
                        {{ translate('Pricing Policies') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('tab') === 'payouts' ? 'active' : '' }}" href="?tab=payouts" role="tab">
                        {{ translate('Payout Requests') }} 
                        @if($payoutStats['pending_count'] > 0)
                            <span class="badge badge-soft-danger badge-pill ml-1">{{ $payoutStats['pending_count'] }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('tab') === 'earnings' ? 'active' : '' }}" href="?tab=earnings" role="tab">
                        {{ translate('Driver Earnings') }}
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- TAB 1: PRICING POLICIES -->
            @if(!request('tab') || request('tab') === 'policies')
                <div class="tab-pane fade show active" role="tabpanel">
                    <div class="card">
                        <div class="card-header border-0">
                            <h5 class="card-title">{{ translate('Driver Pricing Policies & Zone Overrides') }}</h5>
                        </div>

                        <div class="table-responsive datatable-custom">
                            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ translate('Policy Name') }}</th>
                                        <th>{{ translate('Service Type') }}</th>
                                        <th>{{ translate('Payout Model') }}</th>
                                        <th>{{ translate('Zone') }}</th>
                                        <th>{{ translate('Details') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($policies as $key => $policy)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>
                                                <strong class="text-hover-primary">{{ $policy->name }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-soft-info text-capitalize">
                                                    {{ str_replace('_', ' ', $policy->policy_type) }}
                                                </span>
                                            </td>
                                            <td>
                                                <code class="text-capitalize">{{ str_replace('_', ' ', $policy->payout_model) }}</code>
                                            </td>
                                            <td>
                                                @if($policy->zone)
                                                    <span class="badge badge-soft-success">{{ $policy->zone->name }}</span>
                                                @else
                                                    <span class="badge badge-soft-secondary">{{ translate('Global Default') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted d-block">
                                                    @if($policy->payout_model === 'fixed_payout')
                                                        {{ translate('Fixed Amount') }}: ${{ number_format($policy->fixed_amount, 2) }}
                                                    @elseif($policy->payout_model === 'base_mileage')
                                                        {{ translate('Base') }}: ${{ number_format($policy->base_fare, 2) }} / {{ translate('Mile') }}: ${{ number_format($policy->rate_per_mile, 2) }}
                                                    @elseif($policy->payout_model === 'base_mileage_time')
                                                        {{ translate('Base') }}: ${{ number_format($policy->base_fare, 2) }} / {{ translate('Mile') }}: ${{ number_format($policy->rate_per_mile, 2) }} / {{ translate('Min') }}: ${{ number_format($policy->rate_per_minute, 2) }}
                                                    @elseif($policy->payout_model === 'per_stop')
                                                        {{ translate('Per Stop') }}: ${{ number_format($policy->rate_per_stop, 2) }}
                                                    @elseif($policy->payout_model === 'per_package')
                                                        {{ translate('Per Package') }}: ${{ number_format($policy->rate_per_package, 2) }}
                                                    @elseif($policy->payout_model === 'percentage_of_revenue')
                                                        {{ translate('Revenue') }}: {{ $policy->revenue_percentage }}%
                                                    @elseif($policy->payout_model === 'dynamic_ai')
                                                        {{ translate('AI Dynamic Capping') }}
                                                    @else
                                                        {{ translate('Manual Quote') }}
                                                    @endif
                                                </small>
                                            </td>
                                            <td>
                                                @if($policy->is_active)
                                                    <span class="badge badge-success">{{ translate('Active') }}</span>
                                                @else
                                                    <span class="badge badge-danger">{{ translate('Inactive') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                        <i class="tio-settings"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{ route('admin.urban-goodz.driver-pricing.edit', $policy->id) }}">
                                                            <i class="tio-edit"></i> {{ translate('Edit') }}
                                                        </a>
                                                        <a class="dropdown-item" href="{{ route('admin.urban-goodz.driver-pricing.history', $policy->id) }}">
                                                            <i class="tio-history"></i> {{ translate('Audit Log & Rollback') }}
                                                        </a>
                                                        @if($policy->zone_id !== null)
                                                            <form action="{{ route('admin.urban-goodz.driver-pricing.destroy', $policy->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this zone override policy?') }}')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4">{{ translate('No pricing policies created') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- TAB 2: PAYOUT REQUESTS -->
            @if(request('tab') === 'payouts')
                <div class="tab-pane fade show active" role="tabpanel">
                    <!-- Stats Grid -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="card bg-soft-warning">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($payoutStats['total_pending'], 2) }}</h3>
                                    <small>{{ translate('Pending Payouts') }} ({{ $payoutStats['pending_count'] }})</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-soft-success">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($payoutStats['total_paid'], 2) }}</h3>
                                    <small>{{ translate('Total Paid') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-soft-info">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($payoutStats['total_fees'], 2) }}</h3>
                                    <small>{{ translate('Instant Fees Collected') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless table-thead-bordered table-nowrap card-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ translate('#') }}</th>
                                        <th>{{ translate('Driver') }}</th>
                                        <th>{{ translate('Type') }}</th>
                                        <th>{{ translate('Requested') }}</th>
                                        <th>{{ translate('Fee') }}</th>
                                        <th>{{ translate('Net') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Requested At') }}</th>
                                        <th>{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payouts as $key => $payout)
                                        <tr>
                                            <td>{{ $payouts->firstItem() + $key }}</td>
                                            <td>
                                                <a href="{{ route('admin.customer.view', $payout->driver?->id) }}">
                                                    {{ $payout->driver?->f_name . ' ' . $payout->driver?->l_name }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-soft-{{ $payout->payout_type === 'instant' ? 'warning' : 'primary' }}">
                                                    {{ ucfirst($payout->payout_type) }}
                                                </span>
                                            </td>
                                            <td>${{ number_format($payout->requested_amount, 2) }}</td>
                                            <td>${{ number_format($payout->instant_fee, 2) }}</td>
                                            <td><strong>${{ number_format($payout->net_amount, 2) }}</strong></td>
                                            <td>
                                                @php $sMap = ['pending' => 'warning', 'approved' => 'info', 'processing' => 'secondary', 'paid' => 'success', 'rejected' => 'danger', 'held' => 'dark']; @endphp
                                                <span class="badge badge-soft-{{ $sMap[$payout->status] ?? 'secondary' }}">{{ ucfirst($payout->status) }}</span>
                                            </td>
                                            <td>{{ $payout->created_at->format('M d, Y g:i A') }}</td>
                                            <td>
                                                <a href="{{ route('admin.urban-goodz.driver-payouts.show', $payout->id) }}" class="btn btn-sm btn-outline-info">
                                                    <i class="tio-visible"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="text-center py-4">{{ translate('No payout requests yet') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($payouts->hasPages())
                            <div class="card-footer">
                                {{ $payouts->appends(['tab' => 'payouts'])->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- TAB 3: DRIVER EARNINGS -->
            @if(request('tab') === 'earnings')
                <div class="tab-pane fade show active" role="tabpanel">
                    <!-- Stats Grid -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="card bg-soft-warning">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($earningStats['pending'], 2) }}</h3>
                                    <small>{{ translate('Pending Earnings') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-soft-info">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($earningStats['approved'], 2) }}</h3>
                                    <small>{{ translate('Approved Earnings') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-soft-success">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($earningStats['paid'], 2) }}</h3>
                                    <small>{{ translate('Paid Earnings') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-soft-primary">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">${{ number_format($earningStats['total'], 2) }}</h3>
                                    <small>{{ translate('Total All Time') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless table-thead-bordered table-nowrap card-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ translate('#') }}</th>
                                        <th>{{ translate('Driver') }}</th>
                                        <th>{{ translate('Type') }}</th>
                                        <th>{{ translate('Amount') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Package') }}</th>
                                        <th>{{ translate('Route') }}</th>
                                        <th>{{ translate('Description') }}</th>
                                        <th>{{ translate('Date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($earnings as $key => $earning)
                                        <tr>
                                            <td>{{ $earnings->firstItem() + $key }}</td>
                                            <td>
                                                <a href="{{ route('admin.customer.view', $earning->driver?->id) }}">
                                                    {{ $earning->driver?->f_name . ' ' . $earning->driver?->l_name }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $earning->earning_type)) }}</span>
                                            </td>
                                            <td><strong>${{ number_format($earning->amount, 2) }}</strong></td>
                                            <td>
                                                @php $sMap = ['pending' => 'warning', 'approved' => 'info', 'paid' => 'success', 'held' => 'dark', 'disputed' => 'danger']; @endphp
                                                <span class="badge badge-soft-{{ $sMap[$earning->status] ?? 'secondary' }}">{{ ucfirst($earning->status) }}</span>
                                            </td>
                                            <td>{{ $earning->package?->tracking_id ?? '—' }}</td>
                                            <td>{{ $earning->route?->route_name ?? '—' }}</td>
                                            <td>{{ Str::limit($earning->description, 40) ?? '—' }}</td>
                                            <td>{{ $earning->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="text-center py-4">{{ translate('No earnings recorded') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($earnings->hasPages())
                            <div class="card-footer">
                                {{ $earnings->appends(['tab' => 'earnings'])->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
