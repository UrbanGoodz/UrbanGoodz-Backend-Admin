@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))

@section('title', 'Rule Detail')

@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush

@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')

    <div class="ug-card">
        <h2>
            {{ $rule->name }}
            <span class="ug-badge ug-badge-{{ $rule->state }}">{{ ucfirst($rule->state) }}</span>
            <span class="ug-badge">v{{ $rule->version }}</span>
            @unless($rule->is_active)<span class="ug-badge ug-badge-archived">disabled</span>@endunless
        </h2>
        <p class="ug-muted">{{ $rule->rule_key }}</p>
        @if($rule->notes)<p>{{ $rule->notes }}</p>@endif

        <div class="ug-grid">
            <div><strong>Work type</strong><br>{{ $workTypes[$rule->work_type] ?? $rule->work_type }}</div>
            <div><strong>Service</strong><br>{{ $rule->service_scope ?? 'any' }}</div>
            <div><strong>Vehicles</strong><br>{{ $rule->vehicle_scope ? implode(', ', $rule->vehicle_scope) : 'any' }}</div>
            <div><strong>Markets</strong><br>{{ $rule->market_scope ? implode(', ', $rule->market_scope) : 'any' }}</div>
            <div><strong>Zone</strong><br>{{ $rule->zone_id ?? 'any' }}</div>
            <div><strong>Priority</strong><br>{{ $rule->priority }}</div>
            <div><strong>Rounding</strong><br>{{ $rule->rounding_mode }}</div>
            <div><strong>Minimum</strong><br>{{ $rule->minimum_payout_cents !== null ? number_format($rule->minimum_payout_cents / 100, 2) : 'none' }}</div>
            <div><strong>Maximum</strong><br>{{ $rule->maximum_payout_cents !== null ? number_format($rule->maximum_payout_cents / 100, 2) : 'none' }}</div>
        </div>
    </div>

    <div class="ug-card">
        <h2>Impact summary</h2>
        <ul style="font-size:.88rem; line-height:1.7;">
            <li>Components configured: <strong>{{ $impact['component_count'] }}</strong></li>
            <li>Effective: {{ $impact['effective_from'] ?? 'immediate' }} &rarr; {{ $impact['effective_to'] ?? 'open' }}</li>
            <li>Affected scopes:
                work type <strong>{{ $impact['scopes']['work_type'] }}</strong>,
                service <strong>{{ $impact['scopes']['service_scope'] }}</strong>,
                vehicles <strong>{{ implode(', ', (array) $impact['scopes']['vehicle_scope']) }}</strong>,
                markets <strong>{{ implode(', ', (array) $impact['scopes']['market_scope']) }}</strong>,
                zone <strong>{{ $impact['scopes']['zone_id'] }}</strong>
            </li>
            <li>
                @if($impact['would_archive'])
                    Publishing will archive currently published
                    <strong>v{{ $impact['would_archive']['version'] }}</strong>.
                @else
                    No currently published version of this key — publishing introduces it.
                @endif
            </li>
        </ul>
    </div>

    <div class="ug-card">
        <h2>Conflict detection</h2>
        @if(empty($conflicts))
            <p class="ug-muted">No other published rule overlaps this rule's scope.</p>
        @else
            <div class="ug-table-wrap">
                <table class="ug-table">
                    <thead><tr><th>Rule</th><th class="ug-num">Priority</th><th class="ug-num">Specificity</th><th>Resolution</th></tr></thead>
                    <tbody>
                    @foreach($conflicts as $conflict)
                        <tr>
                            <td><a href="{{ route('admin.urban-goodz.compensation.show', $conflict['id']) }}">{{ $conflict['name'] }}</a>
                                <div class="ug-muted" style="font-size:.78rem;">{{ $conflict['rule_key'] }} v{{ $conflict['version'] }}</div></td>
                            <td class="ug-num">{{ $conflict['priority'] }}</td>
                            <td class="ug-num">{{ $conflict['specificity'] }}</td>
                            <td>
                                <span class="ug-badge {{ $conflict['outcome'] === 'ambiguous tie' ? 'ug-badge-deficit' : '' }}">
                                    {{ $conflict['outcome'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="ug-card">
        <h2>Actions</h2>
        <div style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:center;">
            @if($rule->state === 'draft' && ($permissions['compensation_edit_draft'] ?? false))
                <a href="{{ route('admin.urban-goodz.compensation.edit', $rule->id) }}" class="ug-btn">Edit draft</a>
            @endif

            @if($rule->state === 'published' && ($permissions['compensation_create_draft'] ?? false))
                <form method="POST" action="{{ route('admin.urban-goodz.compensation.new-version', $rule->id) }}">
                    @csrf
                    <button type="submit" class="ug-btn">Create new draft version</button>
                </form>
            @endif

            @if($rule->state !== 'archived' && ($permissions['compensation_archive'] ?? false))
                <form method="POST" action="{{ route('admin.urban-goodz.compensation.archive', $rule->id) }}">
                    @csrf
                    <button type="submit" class="ug-btn ug-btn-danger">Archive</button>
                </form>
            @endif

            <a href="{{ route('admin.urban-goodz.compensation.versions', $rule->rule_key) }}" class="ug-btn">All versions</a>
        </div>

        @if($rule->state === 'draft' && ($permissions['compensation_publish'] ?? false))
            <hr style="margin:1rem 0; border:none; border-top:1px solid var(--ug-line);">
            <form method="POST" action="{{ route('admin.urban-goodz.compensation.publish', $rule->id) }}">
                @csrf
                <h2 style="margin-bottom:.5rem;">Publish this version</h2>
                <p class="ug-muted" style="font-size:.85rem;">
                    Publishing archives the currently published version of this key. Published rules
                    cannot be edited in place — a change creates a new draft version. Compensation
                    results already finalized are never altered by publishing.
                </p>
                <div class="ug-field" style="max-width:280px;">
                    <label for="publish_effective_from">Effective date</label>
                    <input type="datetime-local" id="publish_effective_from" name="effective_from"
                           value="{{ optional($rule->effective_from)->format('Y-m-d\TH:i') }}">
                </div>
                <label style="display:block; margin:.7rem 0; font-weight:400; text-transform:none; letter-spacing:0;">
                    <input type="checkbox" name="confirm" value="1" required>
                    I confirm this rule should govern live driver payouts.
                </label>
                <button type="submit" class="ug-btn ug-btn-primary">Publish v{{ $rule->version }}</button>
            </form>
        @endif
    </div>

    <div class="ug-card">
        <h2>Recent audit entries</h2>
        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead><tr><th>When</th><th>Event</th><th>Actor</th><th>Description</th></tr></thead>
                <tbody>
                @forelse($audits as $audit)
                    <tr>
                        <td class="ug-muted">{{ $audit->created_at }}</td>
                        <td><span class="ug-badge">{{ $audit->event }}</span></td>
                        <td>{{ $audit->actor_id ?? '—' }}</td>
                        <td>{{ $audit->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ug-muted">No audit entries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
