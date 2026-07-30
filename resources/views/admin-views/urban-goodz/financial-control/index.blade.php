@extends('layouts.admin.app')

@section('title', translate('Financial Control Center'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm">
                <h1 class="page-header-title">{{ translate('Financial Control Center') }}</h1>
                <p class="text-muted mb-0">{{ translate('Integer-cent rules, immutable settlement snapshots, refunds, and reconciliation.') }}</p>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-outline-primary" href="{{ route('admin.urban-goodz.financial-control.ledger') }}">{{ translate('Ledger JSON') }}</a>
                <a class="btn btn-outline-secondary" href="{{ route('admin.urban-goodz.financial-control.reconciliation') }}">{{ translate('Reconciliation JSON') }}</a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-2 mb-3">
        @foreach([
            'Active Rules' => $stats['active_rules'],
            'Settled' => '$'.number_format($stats['settled_cents'] / 100, 2),
            'Provider Proceeds' => '$'.number_format($stats['provider_proceeds_cents'] / 100, 2),
            'Driver Net' => '$'.number_format($stats['driver_net_cents'] / 100, 2),
            'Platform Net' => '$'.number_format($stats['platform_net_cents'] / 100, 2),
            'Out of Balance' => $stats['out_of_balance'],
        ] as $label => $value)
            <div class="col-md-2 col-6">
                <div class="card h-100"><div class="card-body py-3">
                    <small class="text-muted">{{ translate($label) }}</small>
                    <div class="h4 mb-0">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="alert alert-soft-info">
        <strong>{{ translate('Settlement invariant:') }}</strong>
        {{ translate('business commission is deducted from provider proceeds and never added to Shopper merchandise. Driver compensation and Driver Admin fees are calculated separately.') }}
    </div>

    <div class="card mb-3">
        <div class="card-header"><h4 class="mb-0">{{ translate('Publish Financial Rule') }}</h4></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.urban-goodz.financial-control.rules.store') }}">
                @csrf
                @include('admin-views.urban-goodz.financial-control.rule-fields', ['rule' => null])
                <div class="mt-3"><button class="btn btn--primary" type="submit">{{ translate('Publish Rule') }}</button></div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h4 class="mb-0">{{ translate('Effective Rule History') }}</h4></div>
        <div class="table-responsive">
            <table class="table table-hover table-align-middle mb-0">
                <thead><tr>
                    <th>{{ translate('Rule') }}</th><th>{{ translate('Family / Method') }}</th>
                    <th>{{ translate('Rate') }}</th><th>{{ translate('Scope') }}</th>
                    <th>{{ translate('Priority') }}</th><th>{{ translate('Effective') }}</th><th>{{ translate('Controls') }}</th>
                </tr></thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td><strong>{{ $rule->name }}</strong><br><small>{{ $rule->rule_key }} v{{ $rule->version }}</small></td>
                        <td>{{ str_replace('_', ' ', $rule->rule_family) }}<br><span class="badge badge-soft-info">{{ str_replace('_', ' ', $rule->calculation_type) }}</span></td>
                        <td>
                            @if($rule->calculation_type === 'percentage')
                                {{ number_format($rule->rate_basis_points / 100, 2) }}%
                            @else
                                ${{ number_format($rule->amount_cents / 100, 2) }}
                            @endif
                        </td>
                        <td>{{ $rule->scope_type }}{{ $rule->scope_key ? ': '.$rule->scope_key : '' }}<br><small>{{ $rule->service_type ?: 'all services' }}</small></td>
                        <td>{{ $rule->priority }}</td>
                        <td>
                            <span class="badge badge-soft-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->is_active ? 'active' : 'historical' }}</span><br>
                            <small>{{ optional($rule->effective_from)->format('Y-m-d H:i') ?: 'now' }} — {{ optional($rule->effective_to)->format('Y-m-d H:i') ?: 'open' }}</small>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-info" href="{{ route('admin.urban-goodz.financial-control.rules.history', $rule) }}">{{ translate('History') }}</a>
                            @if($rule->is_active)
                                <details class="mt-2">
                                    <summary class="btn btn-sm btn-outline-primary">{{ translate('Publish Revision') }}</summary>
                                    <form class="border rounded p-3 mt-2" method="POST" action="{{ route('admin.urban-goodz.financial-control.rules.update', $rule) }}" style="min-width:700px">
                                        @csrf @method('PUT')
                                        @include('admin-views.urban-goodz.financial-control.rule-fields', ['rule' => $rule])
                                        <button class="btn btn--primary btn-sm mt-2" type="submit">{{ translate('Publish New Version') }}</button>
                                    </form>
                                </details>
                                <form class="mt-2" method="POST" action="{{ route('admin.urban-goodz.financial-control.rules.deactivate', $rule) }}" onsubmit="return confirm('Deactivate this rule?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" type="submit">{{ translate('Deactivate') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4">{{ translate('No financial rules have been published.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rules->withQueryString()->links() }}</div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ translate('Live Settlement Simulator') }}</h4>
            <div>
                @foreach($exampleScenarios as $name => $scenario)
                    <button type="button" class="btn btn-sm btn-outline-secondary js-fin-example" data-example='@json($scenario)'>{{ $name }}</button>
                @endforeach
            </div>
        </div>
        <div class="card-body">
            <form id="financial-simulator" method="POST" action="{{ route('admin.urban-goodz.financial-control.simulate') }}">
                @csrf
                @include('admin-views.urban-goodz.financial-control.context-fields')
                <button class="btn btn--primary mt-3" type="submit">{{ translate('Run Against Live Rules') }}</button>
            </form>

            @if($simulation)
                <hr>
                <h5>{{ translate('Simulation Result') }}</h5>
                <div class="row g-2">
                    @foreach([
                        'Shopper Total' => 'shopper_total_cents',
                        'Business Commission' => 'business_commission_cents',
                        'Provider Proceeds' => 'provider_proceeds_cents',
                        'Driver Gross' => 'driver_compensation_cents',
                        'Driver Admin Fee' => 'driver_admin_fee_cents',
                        'Driver Net' => 'driver_net_cents',
                        'Platform Net' => 'platform_net_cents',
                    ] as $label => $key)
                        <div class="col-md-3"><div class="border rounded p-2"><small>{{ $label }}</small><div class="h5 mb-0">${{ number_format($simulation[$key] / 100, 2) }}</div></div></div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.urban-goodz.financial-control.settlements.store') }}" class="mt-3">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-3"><label>{{ translate('Source Type') }}</label><input class="form-control" name="source_type" value="admin_live_example" required></div>
                        <div class="col-md-3"><label>{{ translate('Source ID') }}</label><input class="form-control" name="source_id" value="example-{{ now()->format('YmdHis') }}" required></div>
                        <div class="col-md-4"><label>{{ translate('Idempotency Key') }}</label><input class="form-control" name="idempotency_key" value="admin-example:{{ now()->format('YmdHis') }}" required></div>
                    </div>
                    @foreach($simulation['inputs'] as $key => $value)
                        @if($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <button class="btn btn-success mt-2" type="submit">{{ translate('Record This Settlement Snapshot') }}</button>
                </form>
                <details class="mt-2"><summary>{{ translate('Applied immutable rule decisions') }}</summary><pre class="bg-light p-2">@json($simulation['rules'], JSON_PRETTY_PRINT)</pre></details>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="mb-0">{{ translate('Settlement Snapshots & Reconciliation') }}</h4></div>
        <div class="table-responsive">
            <table class="table table-hover table-align-middle mb-0">
                <thead><tr>
                    <th>{{ translate('Snapshot') }}</th><th>{{ translate('Source') }}</th><th>{{ translate('Shopper') }}</th>
                    <th>{{ translate('Provider') }}</th><th>{{ translate('Driver') }}</th><th>{{ translate('Platform') }}</th>
                    <th>{{ translate('Refunded') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Controls') }}</th>
                </tr></thead>
                <tbody>
                @forelse($settlements as $snapshot)
                    <tr>
                        <td><a href="{{ route('admin.urban-goodz.financial-control.settlements.show', $snapshot) }}">{{ $snapshot->snapshot_number }}</a><br><small>{{ optional($snapshot->settled_at)->format('Y-m-d H:i') }}</small></td>
                        <td>{{ $snapshot->source_type }} #{{ $snapshot->source_id }}<br><small>{{ $snapshot->service_type }}</small></td>
                        <td>${{ number_format($snapshot->shopper_total_cents / 100, 2) }}</td>
                        <td>${{ number_format($snapshot->provider_proceeds_cents / 100, 2) }}<br><small>commission ${{ number_format($snapshot->business_commission_cents / 100, 2) }}</small></td>
                        <td>${{ number_format($snapshot->driver_net_cents / 100, 2) }}<br><small>fee ${{ number_format($snapshot->driver_admin_fee_cents / 100, 2) }}</small></td>
                        <td>${{ number_format($snapshot->platform_net_cents / 100, 2) }}</td>
                        <td>${{ number_format($snapshot->refunded_cents / 100, 2) }}</td>
                        <td><span class="badge badge-soft-{{ $snapshot->reconciliation_status === 'balanced' ? 'success' : 'danger' }}">{{ $snapshot->status }} / {{ $snapshot->reconciliation_status }}</span></td>
                        <td>
                            <form class="mb-1" method="POST" action="{{ route('admin.urban-goodz.financial-control.settlements.reconcile', $snapshot) }}">@csrf<button class="btn btn-sm btn-outline-info">{{ translate('Reconcile') }}</button></form>
                            @if($snapshot->refunded_cents < $snapshot->shopper_total_cents)
                            <details>
                                <summary class="btn btn-sm btn-outline-warning">{{ translate('Refund / Reverse') }}</summary>
                                <form class="border rounded p-2 mt-1" method="POST" action="{{ route('admin.urban-goodz.financial-control.settlements.refund', $snapshot) }}" style="min-width:280px">
                                    @csrf
                                    <input class="form-control form-control-sm mb-1" type="number" name="amount_cents" min="1" max="{{ $snapshot->shopper_total_cents - $snapshot->refunded_cents }}" placeholder="Refund cents" required>
                                    <input class="form-control form-control-sm mb-1" name="reason" placeholder="Reason" required>
                                    <input class="form-control form-control-sm mb-1" name="idempotency_key" value="refund:{{ $snapshot->id }}:{{ now()->format('YmdHis') }}" required>
                                    <button class="btn btn-sm btn-warning">{{ translate('Post Partial Refund') }}</button>
                                </form>
                                <form class="border rounded p-2 mt-1" method="POST" action="{{ route('admin.urban-goodz.financial-control.settlements.reverse', $snapshot) }}" onsubmit="return confirm('Fully reverse this settlement?')">
                                    @csrf
                                    <input class="form-control form-control-sm mb-1" name="reason" placeholder="Reversal reason" required>
                                    <input type="hidden" name="idempotency_key" value="reversal:{{ $snapshot->id }}:{{ now()->format('YmdHis') }}">
                                    <button class="btn btn-sm btn-danger">{{ translate('Full Reversal') }}</button>
                                </form>
                            </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center py-4">{{ translate('No settlement snapshots recorded.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $settlements->withQueryString()->links() }}</div>
    </div>
</div>
@endsection

@push('script_2')
<script>
document.querySelectorAll('.js-fin-example').forEach(function (button) {
    button.addEventListener('click', function () {
        var example = JSON.parse(this.dataset.example);
        Object.keys(example).forEach(function (key) {
            var input = document.querySelector('#financial-simulator [name="' + key + '"]');
            if (input) input.value = example[key];
        });
    });
});
</script>
@endpush
