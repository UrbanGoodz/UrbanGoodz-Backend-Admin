@extends('layouts.admin.app')

@section('title', translate('Age-Restricted Orders'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Age-Restricted Orders') }}</h1>
            <a href="{{ route('admin.urban-goodz.age-compliance.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Compliance') }}
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ translate('Orders') }}</h5>
                <form method="GET">
                    <select name="verification_status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Verification Statuses') }}</option>
                        <option value="pending" {{ request('verification_status') === 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                        <option value="verified" {{ request('verification_status') === 'verified' ? 'selected' : '' }}>{{ translate('Verified') }}</option>
                        <option value="failed" {{ request('verification_status') === 'failed' ? 'selected' : '' }}>{{ translate('Failed') }}</option>
                        <option value="refused" {{ request('verification_status') === 'refused' ? 'selected' : '' }}>{{ translate('Refused') }}</option>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Amount') }}</th>
                                <th>{{ translate('Delivery Man') }}</th>
                                <th>{{ translate('Age Restricted') }}</th>
                                <th>{{ translate('Verification Status') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td>{{ $order->id }}</td>
                                <td>{{ $order->customer?->f_name ?? '' }} {{ $order->customer?->l_name ?? '' }}</td>
                                <td>\${{ number_format($order->order_amount ?? 0, 2) }}</td>
                                <td>{{ $order->delivery_man?->f_name ?? '' }} {{ $order->delivery_man?->l_name ?? '-' }}</td>
                                <td>{!! $order->age_restricted_order ? '<span class="badge badge-soft-warning">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</td>
                                <td>
                                    @if($order->age_verification_status)
                                    <span class="badge badge-soft-{{ $order->age_verification_status === 'verified' ? 'success' : 'warning' }}">
                                        {{ ucfirst($order->age_verification_status) }}
                                    </span>
                                    @else
                                    <span class="badge badge-soft-secondary">{{ translate('N/A') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $order->created_at?->format('M d, Y') ?? '-' }}</small></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No age-restricted orders found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
@endsection
