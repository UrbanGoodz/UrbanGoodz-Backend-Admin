@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))

@section('title', $heading ?? 'Driver Pricing & Compensation')

@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush

@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')

    <div class="ug-card">
        <h2>{{ $heading ?? 'Rules Overview' }}</h2>

        <form method="GET" class="ug-grid" style="margin-bottom:1rem;">
            <div class="ug-field">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Rule name or key">
            </div>
            <div class="ug-field">
                <label for="work_type">Work type</label>
                <select id="work_type" name="work_type">
                    <option value="">All</option>
                    @foreach($workTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['work_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if(($stateFilter ?? null) === null)
                <div class="ug-field">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="">All</option>
                        @foreach(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="ug-field" style="display:flex; align-items:flex-end;">
                <button type="submit" class="ug-btn">Filter</button>
            </div>
        </form>

        <div class="ug-table-wrap">
            <table class="ug-table">
                <thead>
                    <tr>
                        <th>Rule</th><th>Work type</th><th>Scope</th>
                        <th class="ug-num">Priority</th><th class="ug-num">Version</th>
                        <th>State</th><th>Effective</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td>
                            <a href="{{ route('admin.urban-goodz.compensation.show', $rule->id) }}">{{ $rule->name }}</a>
                            <div class="ug-muted" style="font-size:.78rem;">{{ $rule->rule_key }}</div>
                        </td>
                        <td>{{ $workTypes[$rule->work_type] ?? $rule->work_type }}</td>
                        <td class="ug-muted" style="font-size:.8rem;">
                            {{ $rule->service_scope ?? 'any service' }}<br>
                            {{ $rule->vehicle_scope ? implode(', ', $rule->vehicle_scope) : 'any vehicle' }}<br>
                            {{ $rule->market_scope ? implode(', ', $rule->market_scope) : 'any market' }}
                            @if($rule->zone_id) <br>zone {{ $rule->zone_id }} @endif
                        </td>
                        <td class="ug-num">{{ $rule->priority }}</td>
                        <td class="ug-num">v{{ $rule->version }}</td>
                        <td>
                            <span class="ug-badge ug-badge-{{ $rule->state }}">{{ ucfirst($rule->state) }}</span>
                            @unless($rule->is_active)
                                <span class="ug-badge ug-badge-archived">disabled</span>
                            @endunless
                        </td>
                        <td class="ug-muted" style="font-size:.8rem;">
                            {{ optional($rule->effective_from)->toDateString() ?? 'immediate' }}
                            &rarr;
                            {{ optional($rule->effective_to)->toDateString() ?? 'open' }}
                        </td>
                        <td>
                            <a href="{{ route('admin.urban-goodz.compensation.versions', $rule->rule_key) }}"
                               class="ug-muted" style="font-size:.8rem;">Versions</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ug-muted">No compensation rules match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;">{{ $rules->links() }}</div>
    </div>
</div>
@endsection
