@extends('business.layouts.dispatcher')
@section('title', translate('Loads'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Loads') }}</h1>
    <div class="d-flex gap-2">
        <span class="badge badge-soft-success me-2" style="font-size:0.85rem;">{{ $stats['available'] }} {{ translate('Available') }}</span>
        <span class="badge badge-soft-warning me-2" style="font-size:0.85rem;">{{ $stats['assigned'] }} {{ translate('Assigned') }}</span>
        <span class="badge badge-soft-info me-2" style="font-size:0.85rem;">{{ $stats['in_transit'] }} {{ translate('In Transit') }}</span>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <input type="text" name="search" class="form-control" placeholder="{{ translate('Search...') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">{{ translate('All Status') }}</option>
                    @foreach(['available','assigned','in_transit','picked_up','delivered','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="origin_state" class="form-control" placeholder="{{ translate('Origin State') }}" value="{{ request('origin_state') }}">
            </div>
            <div class="col-md-2">
                <input type="text" name="destination_state" class="form-control" placeholder="{{ translate('Dest State') }}" value="{{ request('destination_state') }}">
            </div>
            <div class="col-md-2">
                <select name="load_type" class="form-control">
                    <option value="">{{ translate('All Types') }}</option>
                    @foreach(['ftl','ltl','parcel'] as $t)
                    <option value="{{ $t }}" {{ request('load_type') === $t ? 'selected' : '' }}>{{ strtoupper($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn--primary w-100"><i class="tio-search"></i> {{ translate('Filter') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Load #') }}</th>
                        <th>{{ translate('Origin') }}</th>
                        <th>{{ translate('Destination') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Equipment') }}</th>
                        <th class="text-end">{{ translate('Miles') }}</th>
                        <th class="text-end">{{ translate('Payout') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th>{{ translate('Driver') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loads as $load)
                    <tr>
                        <td>
                            <a href="{{ route('dispatcher.loads.show', $load->id) }}" class="fw-bold">
                                {{ $load->load_number ?? '#'.$load->id }}
                            </a>
                        </td>
                        <td>{{ $load->origin_full }}</td>
                        <td>{{ $load->destination_full }}</td>
                        <td><span class="badge badge-soft-primary">{{ strtoupper($load->load_type ?? '-') }}</span></td>
                        <td>{{ ucfirst($load->equipment_type ?? '-') }}</td>
                        <td class="text-end">{{ $load->distance_miles ? number_format($load->distance_miles, 0) : '-' }}</td>
                        <td class="text-end fw-bold">${{ number_format($load->payout_amount, 2) }}</td>
                        <td class="text-center">
                            @php($badgeClass = match($load->status) { 'available' => 'success', 'assigned' => 'warning', 'in_transit' => 'info', 'delivered' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-soft-{{ $badgeClass }}">{{ $load->status_label }}</span>
                        </td>
                        <td>{{ $load->assignedDriver ? $load->assignedDriver->f_name.' '.$load->assignedDriver->l_name : '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">{{ translate('No loads found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($loads->hasPages())
        <div class="card-footer">{{ $loads->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
