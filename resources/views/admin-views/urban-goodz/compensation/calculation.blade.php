@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))
@section('title', 'Calculation Detail')
@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush
@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')
    <div class="ug-card">
        <h2>{{ $result->subject_type }} #{{ $result->subject_id }}
            @if($result->is_final)<span class="ug-badge ug-badge-published">final — immutable</span>@endif</h2>
        <div class="ug-grid">
            <div><strong>Rule</strong><br>{{ $result->rule_key }} v{{ $result->rule_version }}</div>
            <div><strong>Driver</strong><br>{{ $result->driver_id ?? '—' }}</div>
            <div><strong>Collected</strong><br>{{ number_format($result->gross_cents / 100, 2) }}</div>
            <div><strong>Driver amount</strong><br>{{ number_format($result->driver_cents / 100, 2) }}</div>
            <div><strong>Finalized</strong><br>{{ optional($result->finalized_at)->toDateTimeString() ?? 'not finalized' }}</div>
        </div>
        @if($result->is_final)
            <p class="ug-muted" style="font-size:.83rem; margin-top:.8rem;">
                This result is sealed. Revising the rule does not alter it — a correction must be
                recorded as a new result.
            </p>
        @endif
    </div>
    <div class="ug-card">
        <h2>Components</h2>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead><tr><th>Component</th><th class="ug-num">Amount</th></tr></thead>
                <tbody>
                @foreach($result->breakdown['lines'] ?? [] as $line)
                    <tr><td>{{ $line['label'] }} <span class="ug-muted">({{ $line['code'] }})</span></td><td class="ug-num">{{ $line['amount'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="ug-card">
        <h2>Splits</h2>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <tbody>
                @foreach(($result->splits ?? []) as $key => $value)
                    <tr><td>{{ $key }}</td><td class="ug-num">{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="ug-card">
        <h2>Derivation</h2>
        <div class="ug-explain">{{ $result->explanation }}</div>
    </div>
</div>
@endsection
