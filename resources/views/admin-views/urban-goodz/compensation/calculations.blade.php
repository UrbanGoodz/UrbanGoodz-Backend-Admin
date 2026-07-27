@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))
@section('title', 'Calculation History')
@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush
@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')
    <div class="ug-card">
        <h2>Calculation History</h2>
        <form method="GET" class="ug-grid" style="margin-bottom:1rem;">
            <div class="ug-field"><label for="driver_id">Driver ID</label><input type="number" id="driver_id" name="driver_id" value="{{ $filters['driver_id'] ?? '' }}"></div>
            <div class="ug-field"><label for="rule_key">Rule key</label><input type="text" id="rule_key" name="rule_key" value="{{ $filters['rule_key'] ?? '' }}"></div>
            <div class="ug-field"><label for="subject_type">Subject type</label><input type="text" id="subject_type" name="subject_type" value="{{ $filters['subject_type'] ?? '' }}"></div>
            <div class="ug-field" style="display:flex; align-items:flex-end;"><button type="submit" class="ug-btn">Filter</button></div>
        </form>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead><tr><th>When</th><th>Subject</th><th>Driver</th><th>Rule</th><th class="ug-num">Collected</th><th class="ug-num">Driver</th><th>State</th><th></th></tr></thead>
                <tbody>
                @forelse($results as $result)
                    <tr>
                        <td class="ug-muted">{{ $result->created_at }}</td>
                        <td>{{ $result->subject_type }} #{{ $result->subject_id }}</td>
                        <td>{{ $result->driver_id ?? '—' }}</td>
                        <td>{{ $result->rule_key }} <span class="ug-muted">v{{ $result->rule_version }}</span></td>
                        <td class="ug-num">{{ number_format($result->gross_cents / 100, 2) }}</td>
                        <td class="ug-num">{{ number_format($result->driver_cents / 100, 2) }}</td>
                        <td>
                            @if($result->is_final)<span class="ug-badge ug-badge-published">final</span>@else<span class="ug-badge ug-badge-draft">provisional</span>@endif
                            @if($result->splits['is_deficit'] ?? false)<span class="ug-badge ug-badge-deficit">deficit</span>@endif
                        </td>
                        <td><a href="{{ route('admin.urban-goodz.compensation.calculation', $result->id) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ug-muted">No recorded calculations.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $results->links() }}</div>
    </div>
</div>
@endsection
