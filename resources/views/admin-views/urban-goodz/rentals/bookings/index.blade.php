@extends('layouts.admin.app')

@section('title', translate('Rental Bookings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Rental Bookings') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $bookings->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.dashboard') }}" class="btn btn--secondary">{{ translate('Dashboard') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search by customer') }}" value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <select name="status" class="form-control ml-2" style="max-width:150px" onchange="this.form.submit()">
                        <option value="">{{ translate('All Status') }}</option>
                        @foreach(['pending','approved','declined','active','picked_up','returned','completed','cancelled'] as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Asset') }}</th>
                            <th>{{ translate('Start') }}</th>
                            <th>{{ translate('End') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Payment') }}</th>
                            <th>{{ translate('Deposit') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $key => $b)
                            <tr>
                                <td>{{ $bookings->firstItem() + $key }}</td>
                                <td>{{ $b->customer_name ?? '#' . $b->id }}</td>
                                <td>{{ $b->asset->title ?? '-' }}</td>
                                <td>{{ $b->start_at ? $b->start_at->format('M d, Y') : '-' }}</td>
                                <td>{{ $b->end_at ? $b->end_at->format('M d, Y') : '-' }}</td>
                                <td>
                                    @php $sc = ['pending'=>'warning','approved'=>'success','declined'=>'danger','active'=>'info','picked_up'=>'primary','returned'=>'secondary','completed'=>'success','cancelled'=>'dark']; @endphp
                                    <span class="badge badge-soft-{{ $sc[$b->status] ?? 'secondary' }}">{{ ucfirst($b->status) }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ $b->payment_status === 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($b->payment_status) }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ $b->deposit_status === 'collected' ? 'success' : ($b->deposit_status === 'released' ? 'info' : 'secondary') }}">{{ ucfirst($b->deposit_status) }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.rentals.bookings.show', $b->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="tio-visible"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">{{ translate('No bookings yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($bookings->hasPages())
                <div class="card-footer">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
@endsection
