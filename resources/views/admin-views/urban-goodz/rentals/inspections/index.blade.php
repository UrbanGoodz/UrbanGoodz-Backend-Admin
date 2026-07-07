@extends('layouts.admin.app')

@section('title', translate('Rental Inspections'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Rental Inspections') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $inspections->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.inspections.create') }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add Inspection') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.rentals.dashboard') }}" class="btn btn--secondary">{{ translate('Dashboard') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper">
                    <select name="inspection_type" class="form-control ml-2" style="max-width:180px" onchange="location.href=this.value ? '?inspection_type=' + this.value : '?'">
                        <option value="">{{ translate('All Types') }}</option>
                        <option value="pre_pickup" {{ request('inspection_type') === 'pre_pickup' ? 'selected' : '' }}>Pre-Pickup</option>
                        <option value="post_return" {{ request('inspection_type') === 'post_return' ? 'selected' : '' }}>Post-Return</option>
                        <option value="damage" {{ request('inspection_type') === 'damage' ? 'selected' : '' }}>Damage</option>
                        <option value="routine" {{ request('inspection_type') === 'routine' ? 'selected' : '' }}>Routine</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Booking') }}</th>
                            <th>{{ translate('Asset') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Damage') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inspections as $key => $ins)
                            <tr>
                                <td>{{ $inspections->firstItem() + $key }}</td>
                                <td>#{{ $ins->booking_id }}</td>
                                <td>{{ $ins->booking->asset->title ?? '-' }}</td>
                                <td><span class="badge badge-soft-info">{{ ucfirst($ins->inspection_type) }}</span></td>
                                <td>
                                    @if($ins->damage_found)
                                        <span class="badge badge-soft-danger">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-success">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td>{{ $ins->damage_amount ? '$' . number_format($ins->damage_amount, 2) : '-' }}</td>
                                <td><span class="badge badge-soft-{{ $ins->status === 'completed' ? 'success' : 'warning' }}">{{ $ins->status }}</span></td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.rentals.inspections.edit', $ins->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="tio-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">{{ translate('No inspections yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($inspections->hasPages())
                <div class="card-footer">{{ $inspections->links() }}</div>
            @endif
        </div>
    </div>
@endsection
