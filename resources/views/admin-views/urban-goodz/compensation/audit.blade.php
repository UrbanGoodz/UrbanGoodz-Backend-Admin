@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))
@section('title', 'Adjustment Audit')
@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush
@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')
    <div class="ug-card">
        <h2>Adjustment Audit</h2>
        <p class="ug-muted" style="font-size:.85rem; margin-top:-.4rem;">
            Append-only record of every rule change. Entries are never edited or removed.
        </p>
        <form method="GET" class="ug-grid" style="margin-bottom:1rem;">
            <div class="ug-field"><label for="rule_key">Rule key</label><input type="text" id="rule_key" name="rule_key" value="{{ $filters['rule_key'] ?? '' }}"></div>
            <div class="ug-field"><label for="event">Event</label>
                <select id="event" name="event">
                    <option value="">All</option>
                    @foreach(['created', 'updated', 'published', 'archived', 'enabled', 'disabled'] as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ug-field" style="display:flex; align-items:flex-end;"><button type="submit" class="ug-btn">Filter</button></div>
        </form>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead><tr><th>When</th><th>Rule</th><th class="ug-num">Version</th><th>Event</th><th>Actor</th><th>Description</th></tr></thead>
                <tbody>
                @forelse($audits as $audit)
                    <tr>
                        <td class="ug-muted">{{ $audit->created_at }}</td>
                        <td>{{ $audit->rule_key }}</td>
                        <td class="ug-num">v{{ $audit->version }}</td>
                        <td><span class="ug-badge">{{ $audit->event }}</span></td>
                        <td>{{ $audit->actor_id ?? '—' }}</td>
                        <td>{{ $audit->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ug-muted">No audit entries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $audits->links() }}</div>
    </div>
</div>
@endsection
