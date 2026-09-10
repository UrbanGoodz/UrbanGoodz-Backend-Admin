@extends('business.layouts.app')

@section('title', translate('Load Board'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ translate('Load Board') }}</li>
                </ol>
            </nav>
            <h1 class="page-header-title">{{ translate('Load Board') }}</h1>
        </div>
        <a href="{{ route('business.ai-logistics.load-board.create') }}" class="btn btn--primary" style="background-color: var(--ug-primary); color: #fff;">
            <i class="tio-add"></i> {{ translate('Post Load') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex flex-wrap gap-2">
                <input type="text" name="search" class="form-control" style="max-width: 260px;" placeholder="{{ translate('Search reference, origin, destination') }}" value="{{ request('search') }}">
                <select name="status" class="form-control" style="max-width: 180px;">
                    <option value="">{{ translate('All Statuses') }}</option>
                    @foreach(['available','assigned','in_transit','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-secondary">{{ translate('Filter') }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ translate('Reference') }}</th>
                            <th>{{ translate('Route') }}</th>
                            <th>{{ translate('Vehicle') }}</th>
                            <th>{{ translate('Payout') }}</th>
                            <th>{{ translate('Driver') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loads as $load)
                        <tr>
                            <td>{{ $load->load_number }}</td>
                            <td>{{ $load->origin_city }}, {{ $load->origin_state }} &rarr; {{ $load->destination_city }}, {{ $load->destination_state }}</td>
                            <td><small>{{ ucwords(str_replace('_',' ', $load->equipment_type ?? '—')) }}</small></td>
                            <td><strong class="text-success">${{ number_format($load->payout_amount ?? 0, 2) }}</strong></td>
                            <td>{{ $load->deliveryMan ? $load->deliveryMan->f_name.' '.$load->deliveryMan->l_name : '—' }}</td>
                            <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_',' ', $load->status)) }}</span></td>
                            <td class="text-center"><a href="{{ route('business.ai-logistics.load-board.show', $load->id) }}" class="btn btn-outline-info btn-xs p-1"><i class="tio-visible"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No loads posted yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($loads instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $loads->withQueryString()->links() }}</div>
        @endif
    </div>
@endsection
