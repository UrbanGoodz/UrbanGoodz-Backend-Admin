@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))
@section('title', 'Deficit Alerts')
@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush
@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')
    <div class="ug-card">
        <h2>Deficit Alerts</h2>
        <p class="ug-muted" style="font-size:.85rem; margin-top:-.4rem;">
            A deficit means the rule paid out more than the job collected. These are reported, never
            silently clamped, so a loss-making rule is visible.
        </p>
        @if($deficits->isEmpty())
            <p class="ug-muted">No deficits in the most recent 500 calculations.</p>
        @else
            <div class="ug-alert ug-alert-error">
                <strong>{{ $deficits->count() }}</strong> deficit calculation(s).
                Total shortfall: <strong>{{ number_format($totalDeficitCents / 100, 2) }}</strong>.
            </div>
            <div class="ug-table-wrap">
                <table class="ug-table">
                    <thead><tr><th>When</th><th>Subject</th><th>Rule</th><th class="ug-num">Collected</th><th class="ug-num">Driver</th><th class="ug-num">Platform</th><th></th></tr></thead>
                    <tbody>
                    @foreach($deficits as $deficit)
                        <tr>
                            <td class="ug-muted">{{ $deficit->created_at }}</td>
                            <td>{{ $deficit->subject_type }} #{{ $deficit->subject_id }}</td>
                            <td>{{ $deficit->rule_key }} v{{ $deficit->rule_version }}</td>
                            <td class="ug-num">{{ number_format($deficit->gross_cents / 100, 2) }}</td>
                            <td class="ug-num">{{ number_format($deficit->driver_cents / 100, 2) }}</td>
                            <td class="ug-num ug-badge-deficit">{{ number_format(($deficit->splits['platform_cents'] ?? 0) / 100, 2) }}</td>
                            <td><a href="{{ route('admin.urban-goodz.compensation.calculation', $deficit->id) }}">Detail</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
