@extends('layouts.admin.app')

@section('title', translate('Age Compliance'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.6rem; font-weight: 700; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Age Compliance Dashboard') }}</h1>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--primary">{{ $stats['needs_review'] }}</div>
                        <small class="text-muted">{{ translate('Needs Review') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--warning">{{ $stats['pending'] }}</div>
                        <small class="text-muted">{{ translate('Pending') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--success">{{ $stats['verified'] }}</div>
                        <small class="text-muted">{{ translate('Verified') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center py-3">
                        <div class="stat-number text--danger">{{ $stats['refused'] + $stats['failed'] }}</div>
                        <small class="text-muted">{{ translate('Failed / Refused') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('admin.urban-goodz.age-compliance.index') }}" class="btn btn-sm btn-outline--primary">{{ translate('All Verifications') }}</a>
            <a href="{{ route('admin.urban-goodz.age-compliance.index', ['needs_review' => 1]) }}" class="btn btn-sm btn-outline-warning">{{ translate('Needs Review') }}</a>
            <a href="{{ route('admin.urban-goodz.age-compliance.packages') }}" class="btn btn-sm btn-outline-info">{{ translate('Age-Restricted Packages') }} ({{ $stats['age_restricted_packages'] }})</a>
            <a href="{{ route('admin.urban-goodz.age-compliance.orders') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Age-Restricted Orders') }} ({{ $stats['age_restricted_orders'] }})</a>
            <a href="{{ route('admin.urban-goodz.age-compliance.items') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Age-Restricted Items') }}</a>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ translate('Verification Records') }}</h5>
                <form method="GET" class="d-flex gap-2">
                    <select name="verification_status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Statuses') }}</option>
                        @foreach(['pending', 'verified', 'failed', 'refused'] as $s)
                        <option value="{{ $s }}" {{ request('verification_status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    @if(count(request()->query()) > 0)
                    <a href="{{ route('admin.urban-goodz.age-compliance.index') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Package') }}</th>
                                <th>{{ translate('Order') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Refusal Reason') }}</th>
                                <th>{{ translate('Admin Review') }}</th>
                                <th>{{ translate('Attempted') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($verifications as $v)
                            <tr>
                                <td>{{ $v->id }}</td>
                                <td><code>{{ $v->package?->tracking_id ?? '-' }}</code></td>
                                <td>{{ $v->order_id ? '#' . $v->order_id : '-' }}</td>
                                <td>{{ $v->driver?->f_name ?? '-' }} {{ $v->driver?->l_name ?? '' }}</td>
                                <td>
                                    @php
                                        $vMap = ['pending' => 'warning', 'verified' => 'success', 'failed' => 'danger', 'refused' => 'danger'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $vMap[$v->verification_status] ?? 'secondary' }}">
                                        {{ ucfirst($v->verification_status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($v->refusal_reason)
                                    <span class="badge badge-soft-danger">{{ str_replace('_', ' ', $v->refusal_reason) }}</span>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($v->admin_review_required)
                                        @php
                                            $aMap = ['pending' => 'warning', 'reviewed' => 'info', 'resolved' => 'success', 'escalated' => 'danger'];
                                        @endphp
                                        <span class="badge badge-soft-{{ $aMap[$v->admin_review_status] ?? 'warning' }}">
                                            {{ ucfirst($v->admin_review_status ?? 'pending') }}
                                        </span>
                                    @else
                                    <span class="badge badge-soft-secondary">{{ translate('N/A') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $v->verification_attempted_at?->format('M d, h:i A') ?? '-' }}</small></td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.age-compliance.show', $v->id) }}" class="btn btn-sm btn--primary">{{ translate('View') }}</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">{{ translate('No verification records found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $verifications->links() }}
            </div>
        </div>
    </div>
@endsection
