@extends('layouts.admin.app')

@section('title', translate('Booking') . ' #' . $booking->id)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Booking') }} #{{ $booking->id }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.bookings.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Booking Details') }}</h5>
                        <span class="badge badge-soft-{{ $booking->status === 'approved' ? 'success' : ($booking->status === 'pending' ? 'warning' : 'secondary') }} badge-lg">{{ ucfirst($booking->status) }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">{{ translate('Asset') }}</dt>
                            <dd class="col-sm-8">{{ $booking->asset->title ?? '-' }} ({{ $booking->asset->asset_type ?? '-' }})</dd>

                            <dt class="col-sm-4">{{ translate('Customer') }}</dt>
                            <dd class="col-sm-8">{{ $booking->customer_name ?? '-' }} {{ $booking->customer_phone ? '/ ' . $booking->customer_phone : '' }}</dd>

                            <dt class="col-sm-4">{{ translate('Period') }}</dt>
                            <dd class="col-sm-8">{{ $booking->start_at ? $booking->start_at->format('M d, Y g:i A') : '-' }} &rarr; {{ $booking->end_at ? $booking->end_at->format('M d, Y g:i A') : '-' }}</dd>

                            <dt class="col-sm-4">{{ translate('Total Amount') }}</dt>
                            <dd class="col-sm-8">{{ $booking->total_amount ? '$' . number_format($booking->total_amount, 2) : '-' }}</dd>

                            <dt class="col-sm-4">{{ translate('Deposit Amount') }}</dt>
                            <dd class="col-sm-8">{{ $booking->deposit_amount ? '$' . number_format($booking->deposit_amount, 2) : '-' }}</dd>

                            <dt class="col-sm-4">{{ translate('Customer Notes') }}</dt>
                            <dd class="col-sm-8">{{ $booking->customer_notes ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>

                @if($booking->inspections->count() > 0)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title">{{ translate('Inspections') }}</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-borderless table-align-middle mb-0">
                                <thead class="thead-light">
                                    <tr><th>{{ translate('Type') }}</th><th>{{ translate('Damage') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Status') }}</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($booking->inspections as $ins)
                                        <tr>
                                            <td>{{ ucfirst($ins->inspection_type) }}</td>
                                            <td>{{ $ins->damage_found ? translate('Yes') : translate('No') }}</td>
                                            <td>{{ $ins->damage_amount ? '$' . number_format($ins->damage_amount, 2) : '-' }}</td>
                                            <td><span class="badge badge-soft-{{ $ins->status === 'completed' ? 'success' : 'warning' }}">{{ $ins->status }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Status Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="d-block text-dark font-weight-bold mb-2">{{ translate('Booking Status') }}</label>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach(['pending','approved','declined','active','picked_up','returned','completed','cancelled'] as $st)
                                    <a href="{{ route('admin.urban-goodz.rentals.bookings.status', [$booking->id, $st]) }}"
                                       class="btn btn-sm {{ $booking->status === $st ? 'btn-primary' : 'btn-outline-secondary' }} m-1">
                                        {{ ucfirst($st) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="d-block text-dark font-weight-bold mb-2">{{ translate('Verification') }}</label>
                            <div class="d-flex gap-1">
                                @foreach(['pending','verified','failed'] as $st)
                                    <a href="{{ route('admin.urban-goodz.rentals.bookings.verification', [$booking->id, $st]) }}"
                                       class="btn btn-sm {{ $booking->verification_status === $st ? 'btn-primary' : 'btn-outline-secondary' }} m-1">
                                        {{ ucfirst($st) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="d-block text-dark font-weight-bold mb-2">{{ translate('Payment') }}</label>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach(['pending','paid','refunded','failed'] as $st)
                                    <a href="{{ route('admin.urban-goodz.rentals.bookings.payment', [$booking->id, $st]) }}"
                                       class="btn btn-sm {{ $booking->payment_status === $st ? 'btn-primary' : 'btn-outline-secondary' }} m-1">
                                        {{ ucfirst($st) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="d-block text-dark font-weight-bold mb-2">{{ translate('Deposit') }}</label>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach(['pending','collected','released','partially_released','forfeited'] as $st)
                                    <a href="{{ route('admin.urban-goodz.rentals.bookings.deposit', [$booking->id, $st]) }}"
                                       class="btn btn-sm {{ $booking->deposit_status === $st ? 'btn-primary' : 'btn-outline-secondary' }} m-1">
                                        {{ ucfirst($st) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <hr>

                        <form action="{{ route('admin.urban-goodz.rentals.bookings.notes', $booking->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label class="text-dark font-weight-bold">{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="3">{{ $booking->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Save Notes') }}</button>
                        </form>

                        <hr>

                        <a href="{{ route('admin.urban-goodz.rentals.inspections.create', ['booking_id' => $booking->id]) }}" class="btn btn-warning btn-block">
                            <i class="tio-search"></i> {{ translate('Add Inspection') }}
                        </a>

                        <form action="{{ route('admin.urban-goodz.rentals.bookings.destroy', $booking->id) }}" method="POST" class="mt-2"
                              onsubmit="return confirm('{{ translate('Delete this booking?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-block">
                                <i class="tio-delete"></i> {{ translate('Delete Booking') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
