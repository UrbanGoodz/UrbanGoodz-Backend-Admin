@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))
@section('title', 'Split Configuration')
@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush
@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')
    <div class="ug-card">
        <h2>Split Configuration</h2>
        <p class="ug-muted" style="font-size:.85rem; margin-top:-.4rem;">
            Split shares across every published rule. The driver amount is computed by the rule's
            components — it is never a leftover percentage. Urban Goodz takes the residual.
        </p>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead>
                    <tr>
                        <th>Rule</th><th>Work type</th><th>Basis</th>
                        @foreach($splitParties as $label)<th class="ug-num">{{ $label }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td><a href="{{ route('admin.urban-goodz.compensation.show', $rule->id) }}">{{ $rule->name }}</a>
                            <div class="ug-muted" style="font-size:.78rem;">{{ $rule->rule_key }} v{{ $rule->version }}</div></td>
                        <td>{{ $workTypes[$rule->work_type] ?? $rule->work_type }}</td>
                        <td>{{ data_get($rule->splits, 'basis', 'customer_charge') }}</td>
                        @foreach($splitParties as $party => $label)
                            <td class="ug-num">
                                @php($percent = data_get($rule->splits, $party . '.percent'))
                                @php($fixed = data_get($rule->splits, $party . '.fixed_cents'))
                                @if($percent !== null){{ $percent }}%@elseif($fixed !== null){{ number_format($fixed / 100, 2) }}@else<span class="ug-muted">—</span>@endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + count($splitParties) }}" class="ug-muted">No published rules.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
