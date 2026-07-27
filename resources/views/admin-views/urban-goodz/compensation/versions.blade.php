@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))
@section('title', 'Rule Versions')
@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush
@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')
    <div class="ug-card">
        <h2>Versions of <span class="ug-muted">{{ $ruleKey }}</span></h2>
        <p class="ug-muted" style="font-size:.85rem; margin-top:-.4rem;">
            Published rules are never edited in place. Each revision creates a new version, so the
            exact rule behind any historical payout stays readable.
        </p>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead><tr><th class="ug-num">Version</th><th>Name</th><th>State</th><th class="ug-num">Priority</th><th>Effective</th><th>Published</th><th></th></tr></thead>
                <tbody>
                @foreach($versions as $version)
                    <tr>
                        <td class="ug-num">v{{ $version->version }}</td>
                        <td>{{ $version->name }}</td>
                        <td><span class="ug-badge ug-badge-{{ $version->state }}">{{ ucfirst($version->state) }}</span></td>
                        <td class="ug-num">{{ $version->priority }}</td>
                        <td class="ug-muted" style="font-size:.8rem;">{{ optional($version->effective_from)->toDateString() ?? 'immediate' }} &rarr; {{ optional($version->effective_to)->toDateString() ?? 'open' }}</td>
                        <td class="ug-muted" style="font-size:.8rem;">{{ optional($version->published_at)->toDateTimeString() ?? '—' }}</td>
                        <td><a href="{{ route('admin.urban-goodz.compensation.show', $version->id) }}">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="ug-card">
        <h2>Change history</h2>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead><tr><th>When</th><th class="ug-num">Version</th><th>Event</th><th>Actor</th><th>Description</th></tr></thead>
                <tbody>
                @forelse($audits as $audit)
                    <tr>
                        <td class="ug-muted">{{ $audit->created_at }}</td>
                        <td class="ug-num">v{{ $audit->version }}</td>
                        <td><span class="ug-badge">{{ $audit->event }}</span></td>
                        <td>{{ $audit->actor_id ?? '—' }}</td>
                        <td>{{ $audit->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ug-muted">No history recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
