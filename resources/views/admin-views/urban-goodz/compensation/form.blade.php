@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))

@section('title', $mode === 'create' ? 'Create Compensation Rule' : 'Edit Draft Rule')

@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush

@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')

    <form method="POST"
          action="{{ $mode === 'create'
              ? route('admin.urban-goodz.compensation.store')
              : route('admin.urban-goodz.compensation.update', $rule->id) }}">
        @csrf
        @if($mode !== 'create') @method('PUT') @endif

        <div class="ug-card">
            <h2>{{ $mode === 'create' ? 'Create Rule' : 'Edit Draft — v' . $rule->version }}</h2>
            <div class="ug-grid">
                <div class="ug-field">
                    <label for="name">Rule name</label>
                    <input type="text" id="name" name="name" required value="{{ old('name', $rule->name) }}">
                </div>
                <div class="ug-field">
                    <label for="rule_key">Rule key</label>
                    <input type="text" id="rule_key" name="rule_key" required
                           value="{{ old('rule_key', $rule->rule_key) }}" placeholder="delivery.marketplace.standard">
                </div>
                <div class="ug-field">
                    <label for="work_type">Work type</label>
                    <select id="work_type" name="work_type" required>
                        @foreach($workTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('work_type', $rule->work_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ug-field">
                    <label for="service_scope">Service type</label>
                    <select id="service_scope" name="service_scope">
                        <option value="">Any service</option>
                        @foreach($serviceScopes as $group => $options)
                            <optgroup label="{{ $workTypes[$group] ?? $group }}">
                                @foreach($options as $value => $label)
                                    <option value="{{ $value }}" @selected(old('service_scope', $rule->service_scope) === $value)>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="ug-field">
                    <label for="market_scope">Markets (comma separated)</label>
                    <input type="text" id="market_scope_display" name="market_scope_display"
                           value="{{ implode(',', old('market_scope', $rule->market_scope ?? [])) }}"
                           placeholder="stl,kc — blank for any">
                </div>
                <div class="ug-field">
                    <label for="zone_id">Zone ID</label>
                    <input type="number" id="zone_id" name="zone_id" min="1" value="{{ old('zone_id', $rule->zone_id) }}">
                </div>
                <div class="ug-field">
                    <label for="priority">Priority (higher wins)</label>
                    <input type="number" id="priority" name="priority" required
                           value="{{ old('priority', $rule->priority ?? 0) }}">
                </div>
                <div class="ug-field">
                    <label for="rounding_mode">Rounding behaviour</label>
                    <select id="rounding_mode" name="rounding_mode" required>
                        @foreach($roundingModes as $mode_)
                            <option value="{{ $mode_ }}" @selected(old('rounding_mode', $rule->rounding_mode) === $mode_)>{{ $mode_ }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ug-field">
                    <label for="effective_from">Effective start</label>
                    <input type="datetime-local" id="effective_from" name="effective_from"
                           value="{{ old('effective_from', optional($rule->effective_from)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="ug-field">
                    <label for="effective_to">Effective end</label>
                    <input type="datetime-local" id="effective_to" name="effective_to"
                           value="{{ old('effective_to', optional($rule->effective_to)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="ug-field">
                    <label for="minimum_payout_cents">Minimum payout (cents)</label>
                    <input type="number" id="minimum_payout_cents" name="minimum_payout_cents" min="0"
                           value="{{ old('minimum_payout_cents', $rule->minimum_payout_cents) }}">
                </div>
                <div class="ug-field">
                    <label for="maximum_payout_cents">Maximum payout (cents)</label>
                    <input type="number" id="maximum_payout_cents" name="maximum_payout_cents" min="0"
                           value="{{ old('maximum_payout_cents', $rule->maximum_payout_cents) }}">
                </div>
            </div>

            <div class="ug-field" style="margin-top:.85rem;">
                <label for="notes">Description</label>
                <textarea id="notes" name="notes" rows="2">{{ old('notes', $rule->notes) }}</textarea>
            </div>

            <div class="ug-field" style="margin-top:.85rem;">
                <label>Vehicle / equipment scope</label>
                <div style="display:flex; flex-wrap:wrap; gap:.6rem;">
                    @foreach($vehicles as $value => $label)
                        <label style="font-weight:400; text-transform:none; letter-spacing:0; font-size:.85rem;">
                            <input type="checkbox" name="vehicle_scope[]" value="{{ $value }}"
                                   @checked(in_array($value, old('vehicle_scope', $rule->vehicle_scope ?? []), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="ug-muted" style="font-size:.78rem; margin:.3rem 0 0;">No selection means the rule applies to any vehicle.</p>
            </div>
        </div>

        <div class="ug-card">
            <h2>Pay components</h2>
            <p class="ug-muted" style="font-size:.82rem; margin-top:-.4rem;">
                Leave a field blank to omit it. Terminal components (cancellation, failed delivery,
                failed handoff) replace the whole earning calculation when their state is set.
                Pass-through components are added after minimum/maximum clamping.
            </p>

            @foreach($catalog as $groupName => $components)
                <details class="ug-component-group" @if($groupName === ($rule->work_type ?? 'delivery')) open @endif>
                    <summary>{{ ucfirst(str_replace('_', ' ', $groupName)) }} components</summary>
                    @foreach($components as $componentKey => $spec)
                        <div class="ug-component">
                            <div class="ug-component-name">
                                {{ $spec['label'] }}
                                @if($spec['terminal'])<span class="ug-badge ug-badge-draft">terminal</span>@endif
                                @if($spec['pass_through'])<span class="ug-badge">pass-through</span>@endif
                            </div>
                            <div class="ug-grid">
                                @foreach($spec['fields'] as $field => $fieldLabel)
                                    <div class="ug-field">
                                        <label>{{ $fieldLabel }}</label>
                                        <input type="text" min="0"
                                               name="components[{{ $componentKey }}][{{ $field }}]"
                                               value="{{ old("components.$componentKey.$field", data_get($rule->components, "$componentKey.$field")) }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </details>
            @endforeach
        </div>

        <div class="ug-card">
            <h2>Split configuration</h2>
            <div class="ug-field" style="max-width:280px;">
                <label for="splits_basis">Split basis</label>
                <select id="splits_basis" name="splits[basis]">
                    @foreach(['customer_charge' => 'Customer charge', 'linehaul' => 'Linehaul', 'delivery_charge' => 'Delivery charge'] as $value => $label)
                        <option value="{{ $value }}" @selected(data_get($rule->splits, 'basis') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ug-grid" style="margin-top:.85rem;">
                @foreach($splitParties as $party => $label)
                    <div class="ug-field">
                        <label>{{ $label }} — percent</label>
                        <input type="number" step="0.01" min="0" max="100"
                               name="splits[{{ $party }}][percent]"
                               value="{{ old("splits.$party.percent", data_get($rule->splits, "$party.percent")) }}">
                        <label style="margin-top:.35rem;">{{ $label }} — fixed (cents)</label>
                        <input type="number" min="0"
                               name="splits[{{ $party }}][fixed_cents]"
                               value="{{ old("splits.$party.fixed_cents", data_get($rule->splits, "$party.fixed_cents")) }}">
                    </div>
                @endforeach
            </div>
            <p class="ug-muted" style="font-size:.8rem;">
                The driver amount is whatever the components compute — it is never a leftover
                percentage. Urban Goodz takes the residual. If a rule pays out more than it
                collects, the result is reported as a deficit rather than clamped.
            </p>
        </div>

        <div style="display:flex; gap:.6rem; margin-bottom:2rem;">
            <button type="submit" class="ug-btn ug-btn-primary">
                {{ $mode === 'create' ? 'Create draft' : 'Save draft' }}
            </button>
            <a href="{{ route('admin.urban-goodz.compensation.index') }}" class="ug-btn">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    // Markets are entered as a comma separated list; expand into array inputs on submit.
    var form = document.querySelector('form');
    if (!form) return;
    form.addEventListener('submit', function () {
        var display = form.querySelector('[name="market_scope_display"]');
        if (!display) return;
        display.removeAttribute('name');
        String(display.value || '').split(',')
            .map(function (s) { return s.trim(); })
            .filter(function (s) { return s.length > 0; })
            .forEach(function (market) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'market_scope[]';
                hidden.value = market;
                form.appendChild(hidden);
            });
    });
})();
</script>
@endsection
