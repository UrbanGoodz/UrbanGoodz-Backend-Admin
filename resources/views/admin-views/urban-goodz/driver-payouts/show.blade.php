@extends('layouts.admin.app')

@section('title', translate('Payout') . ' #' . $payout->id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Payout') }} #{{ $payout->id }}</h1>
            <a href="{{ route('admin.urban-goodz.driver-payouts.index') }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back') }}
            </a>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Payout Details') }}</h5></div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><td width="150"><strong>{{ translate('Driver') }}:</strong></td><td>{{ $payout->driver?->f_name . ' ' . $payout->driver?->l_name }}</td></tr>
                            <tr><td><strong>{{ translate('Type') }}:</strong></td><td><span class="badge badge-soft-{{ $payout->payout_type === 'instant' ? 'warning' : 'primary' }}">{{ ucfirst($payout->payout_type) }}</span></td></tr>
                            <tr><td><strong>{{ translate('Requested Amount') }}:</strong></td><td>${{ number_format($payout->requested_amount, 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Instant Fee') }}:</strong></td><td>${{ number_format($payout->instant_fee, 2) }}</td></tr>
                            <tr><td><strong>{{ translate('Net Amount') }}:</strong></td><td><strong>${{ number_format($payout->net_amount, 2) }}</strong></td></tr>
                            <tr><td><strong>{{ translate('Status') }}:</strong></td><td>
                                @php $sMap = ['pending' => 'warning', 'approved' => 'info', 'processing' => 'secondary', 'paid' => 'success', 'rejected' => 'danger', 'held' => 'dark']; @endphp
                                <span class="badge badge-soft-{{ $sMap[$payout->status] ?? 'secondary' }}">{{ ucfirst($payout->status) }}</span>
                            </td></tr>
                            <tr><td><strong>{{ translate('Requested At') }}:</strong></td><td>{{ $payout->created_at->format('M d, Y g:i A') }}</td></tr>
                            @if($payout->approved_at)<tr><td><strong>{{ translate('Approved At') }}:</strong></td><td>{{ $payout->approved_at->format('M d, Y g:i A') }}</td></tr>@endif
                            @if($payout->paid_at)<tr><td><strong>{{ translate('Paid At') }}:</strong></td><td>{{ $payout->paid_at->format('M d, Y g:i A') }}</td></tr>@endif
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Admin Notes') }}</h5></div>
                    <div class="card-body">
                        <p class="mb-0">{{ $payout->admin_notes ?? translate('No admin notes') }}</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5>{{ translate('Driver Notes') }}</h5></div>
                    <div class="card-body">
                        <p class="mb-0">{{ $payout->driver_notes ?? translate('No driver notes') }}</p>
                    </div>
                </div>

                @if($payout->status === 'pending')
                    <div class="card">
                        <div class="card-header"><h5>{{ translate('Actions') }}</h5></div>
                        <div class="card-body d-flex gap-2">
                            <form action="{{ route('admin.urban-goodz.driver-payouts.approve', $payout->id) }}" method="POST">
                                @csrf
                                <div class="mb-2">
                                    <textarea name="admin_notes" class="form-control" rows="2" placeholder="{{ translate('Approval notes') }}"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-block">{{ translate('Approve Payout') }}</button>
                            </form>
                            <form action="{{ route('admin.urban-goodz.driver-payouts.reject', $payout->id) }}" method="POST">
                                @csrf
                                <div class="mb-2">
                                    <textarea name="admin_notes" class="form-control" rows="2" placeholder="{{ translate('Rejection reason') }}"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('{{ translate('Reject this payout?') }}')">{{ translate('Reject') }}</button>
                            </form>
                        </div>
                    </div>
                @elseif($payout->status === 'approved')
                    <div class="card">
                        <div class="card-header"><h5>{{ translate('Actions') }}</h5></div>
                        <div class="card-body">
                            <form action="{{ route('admin.urban-goodz.driver-payouts.pay', $payout->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-block">{{ translate('Mark as Paid') }}</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
