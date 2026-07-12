@extends('business.layouts.dispatcher')
@section('title', translate('Commissions'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Commissions') }}</h1>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card stat-commission">
            <div class="card-body text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;color:#e94560;">${{ number_format($stats['total_pending'], 2) }}</div>
                <small class="text-muted">{{ translate('Pending') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card" style="border-left-color:#17a2b8;">
            <div class="card-body text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;color:#17a2b8;">${{ number_format($stats['total_approved'], 2) }}</div>
                <small class="text-muted">{{ translate('Approved') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card" style="border-left-color:#28a745;">
            <div class="card-body text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;color:#28a745;">${{ number_format($stats['total_paid'], 2) }}</div>
                <small class="text-muted">{{ translate('Paid') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card dispatch-stat-card" style="border-left-color:#6c757d;">
            <div class="card-body text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;">${{ number_format($stats['total_earned'], 2) }}</div>
                <small class="text-muted">{{ translate('Total Earned') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">{{ translate('All Status') }}</option>
                @foreach(['pending','approved','paid','disputed'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Load') }}</th>
                        <th class="text-end">{{ translate('Load Payout') }}</th>
                        <th class="text-end">{{ translate('Rate') }}</th>
                        <th class="text-end">{{ translate('Commission') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th>{{ translate('Dispatcher') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $c)
                    <tr>
                        <td>
                            @if($c->load)
                            <a href="{{ route('dispatcher.loads.show', $c->load_id) }}">{{ $c->load->load_number ?? '#'.$c->load_id }}</a>
                            @else
                            <span class="text-muted">#{{ $c->load_id }}</span>
                            @endif
                        </td>
                        <td class="text-end">${{ number_format($c->load_payout, 2) }}</td>
                        <td class="text-end">{{ $c->commission_rate }}%</td>
                        <td class="text-end fw-bold">${{ number_format($c->commission_amount, 2) }}</td>
                        <td class="text-center">
                            @php($sClass = match($c->status) { 'paid' => 'success', 'approved' => 'info', 'disputed' => 'danger', default => 'warning' })
                            <span class="badge badge-soft-{{ $sClass }}">{{ ucfirst($c->status) }}</span>
                        </td>
                        <td>{{ $c->dispatcher->name ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ translate('No commissions found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($commissions->hasPages())
        <div class="card-footer">{{ $commissions->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
