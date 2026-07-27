@extends('layouts.admin.app')

@section('title', translate('Vendors & Businesses'))

@section('content')
    @php
        $cards = [
            ['label' => 'Vendor Accounts', 'value' => $summary['vendor_accounts'], 'tab' => 'accounts', 'class' => 'text-info'],
            ['label' => 'Active Vendors', 'value' => $summary['active_vendors'], 'tab' => 'active-stores', 'class' => 'text-success'],
            ['label' => 'Active Stores', 'value' => $summary['active_stores'], 'tab' => 'active-stores', 'class' => 'text-success'],
            ['label' => 'Pending Vendors', 'value' => $summary['pending_vendors'], 'tab' => 'pending-onboarding', 'class' => 'text-warning'],
            ['label' => 'Orphaned Vendors', 'value' => $summary['orphaned_vendors'], 'tab' => 'missing-store', 'class' => 'text-danger'],
            ['label' => 'Business Clients', 'value' => $summary['business_clients'], 'tab' => 'business-clients', 'class' => 'text-primary'],
            ['label' => 'Service Providers', 'value' => $summary['service_providers'], 'tab' => 'service-providers', 'class' => 'text-primary'],
            ['label' => 'Rental Providers', 'value' => $summary['rental_providers'], 'tab' => 'rental-providers', 'class' => 'text-primary'],
            ['label' => 'Creators', 'value' => $summary['creators'], 'tab' => 'creators', 'class' => 'text-primary'],
            ['label' => 'Suspended', 'value' => $summary['suspended'], 'tab' => 'accounts', 'status' => 'suspended', 'class' => 'text-danger'],
            ['label' => 'Imported/Demo', 'value' => $summary['imported_demo'], 'tab' => 'imported-demo', 'class' => 'text-warning'],
            ['label' => 'Data Issues', 'value' => $summary['data_issues'], 'tab' => 'data-issues', 'class' => 'text-danger'],
        ];
    @endphp

    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h1 class="page-header-title">{{ translate('Vendors & Businesses') }}</h1>
                <p class="text-muted mb-0">
                    {{ translate('Vendor accounts, customer-facing stores, business clients, providers, rental operators, and creators are reported separately.') }}
                </p>
            </div>
            <div class="d-flex flex-wrap" style="gap: .5rem;">
                <a href="{{ route('admin.store.list') }}" class="btn btn-outline-primary">
                    {{ translate('Marketplace Stores') }}
                </a>
                <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--primary">
                    {{ translate('Command Center') }}
                </a>
            </div>
        </div>

        <div class="alert alert-warning">
            <strong>{{ translate('Read-only reconciliation view.') }}</strong>
            {{ translate('Active Vendor requires an active vendor account, active store, active module and zone, valid module-zone assignment, and at least one active approved offering.') }}
            {{ $summary['eligible_without_offerings'] }} {{ translate('structurally eligible stores currently lack an active approved offering.') }}
            {{ $summary['unverified_lifecycle'] }} {{ translate('stores have positive lifecycle flags without matching lifecycle timestamps and require owner review.') }}
        </div>

        <div class="row g-3 mb-4">
            @foreach($cards as $card)
                <div class="col-sm-6 col-lg-3">
                    <a class="order--card h-100"
                       href="{{ route('admin.urban-goodz.vendors.index', array_filter(['tab' => $card['tab'], 'status' => $card['status'] ?? null])) }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-subtitle m-0">{{ translate($card['label']) }}</h6>
                            <span class="card-title {{ $card['class'] }}">{{ $card['value'] }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto">
                    @foreach($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                               href="{{ route('admin.urban-goodz.vendors.index', ['tab' => $key]) }}">
                                {{ translate($label) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.urban-goodz.vendors.index') }}">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                                   class="form-control" placeholder="{{ translate('Search account, business, email, or phone') }}">
                        </div>
                        @if(!in_array($tab, ['business-clients', 'service-providers', 'creators']))
                            <div class="col-md-2">
                                <select name="module_id" class="form-control">
                                    <option value="">{{ translate('All modules') }}</option>
                                    @foreach($modules as $module)
                                        <option value="{{ $module->id }}" @selected(($filters['module_id'] ?? null) == $module->id)>
                                            {{ $module->module_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="zone_id" class="form-control">
                                    <option value="">{{ translate('All zones') }}</option>
                                    @foreach($zones as $zone)
                                        <option value="{{ $zone->id }}" @selected(($filters['zone_id'] ?? null) == $zone->id)>
                                            {{ $zone->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">{{ translate('All statuses') }}</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ translate('Active') }}</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ translate('Inactive') }}</option>
                                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>{{ translate('Suspended') }}</option>
                                </select>
                            </div>
                        @endif
                        <div class="col-md-2">
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Filter') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Account owner') }}</th>
                            <th>{{ translate('Business / Store') }}</th>
                            <th>{{ translate('Role / Type') }}</th>
                            <th>{{ translate('Contact') }}</th>
                            <th>{{ translate('City / Zone') }}</th>
                            <th>{{ translate('Module') }}</th>
                            <th>{{ translate('Approval') }}</th>
                            <th>{{ translate('Active') }}</th>
                            <th>{{ translate('Stores') }}</th>
                            <th>{{ translate('Offerings') }}</th>
                            <th>{{ translate('Orders') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th>{{ translate('Data issue') }}</th>
                            <th class="text-right">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            @php
                                $detailUrl = null;
                                if ($record->record_type === 'vendor_account' && $record->primary_store_id) {
                                    $detailUrl = route('admin.store.view', [
                                        'store' => $record->primary_store_id,
                                        'module_id' => $record->module_id,
                                    ]);
                                } elseif ($record->record_type === 'business_client') {
                                    $detailUrl = route('admin.urban-goodz.business-clients.show', $record->record_id);
                                } elseif ($record->record_type === 'service_provider') {
                                    $detailUrl = route('admin.urban-goodz.service-providers.show', $record->record_id);
                                } elseif ($record->record_type === 'creator') {
                                    $detailUrl = route('admin.urban-goodz.creator.applications.show', $record->record_id);
                                }
                            @endphp
                            <tr>
                                <td>{{ $record->account_owner ?: '—' }}</td>
                                <td>
                                    <strong>{{ $record->business_name ?: '—' }}</strong>
                                    <div class="text-muted small">{{ $record->classification ?: '' }}</div>
                                </td>
                                <td>{{ $record->role_type }}</td>
                                <td>
                                    <div>{{ $record->email ?: '—' }}</div>
                                    <div class="text-muted small">{{ $record->phone ?: '' }}</div>
                                </td>
                                <td>{{ $record->city ?: ($record->zone_name ?: '—') }}</td>
                                <td>{{ $record->module_name ?: '—' }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ in_array($record->approval_status, ['approved', 'active']) ? 'success' : 'warning' }}">
                                        {{ $record->approval_status ?: '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ $record->active_status === 'active' ? 'success' : 'secondary' }}">
                                        {{ $record->active_status ?: '—' }}
                                    </span>
                                </td>
                                <td>{{ $record->store_count }}</td>
                                <td>{{ $record->offering_count }}</td>
                                <td>{{ $record->orders_count }}</td>
                                <td>{{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('Y-m-d') : '—' }}</td>
                                <td>
                                    @if($record->data_issue)
                                        <span class="badge badge-soft-danger">{{ $record->data_issue }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($detailUrl)
                                        <a href="{{ $detailUrl }}" class="btn btn-sm btn-outline-primary">{{ translate('View') }}</a>
                                    @else
                                        <span class="text-muted">{{ translate('No linked record') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center py-5">
                                    <h5>{{ translate('No records match this directory view.') }}</h5>
                                    <p class="text-muted mb-0">{{ translate('No data was changed.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($records->hasPages())
                <div class="card-footer">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
