@php($current = $rule)
<div class="row g-2">
    <div class="col-md-3"><label>{{ translate('Rule Name') }}</label><input class="form-control" name="name" value="{{ old('name', optional($current)->name) }}" required></div>
    <div class="col-md-2"><label>{{ translate('Family') }}</label><select class="form-control" name="rule_family" required>@foreach($families as $value)<option value="{{ $value }}" @selected(old('rule_family', optional($current)->rule_family) === $value)>{{ str_replace('_', ' ', $value) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label>{{ translate('Calculation') }}</label><select class="form-control" name="calculation_type" required>@foreach($calculationTypes as $value)<option value="{{ $value }}" @selected(old('calculation_type', optional($current)->calculation_type) === $value)>{{ str_replace('_', ' ', $value) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label>{{ translate('Amount (cents)') }}</label><input class="form-control" type="number" min="0" name="amount_cents" value="{{ old('amount_cents', optional($current)->amount_cents ?? 0) }}"></div>
    <div class="col-md-2"><label>{{ translate('Rate (basis points)') }}</label><input class="form-control" type="number" min="0" max="10000" name="rate_basis_points" value="{{ old('rate_basis_points', optional($current)->rate_basis_points ?? 0) }}"></div>
    <div class="col-md-2"><label>{{ translate('Scope') }}</label><select class="form-control" name="scope_type" required>@foreach($scopes as $value)<option value="{{ $value }}" @selected(old('scope_type', optional($current)->scope_type ?? 'platform') === $value)>{{ str_replace('_', ' ', $value) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label>{{ translate('Scope ID / Key') }}</label><input class="form-control" name="scope_key" value="{{ old('scope_key', optional($current)->scope_key) }}" placeholder="Blank for platform"></div>
    <div class="col-md-2"><label>{{ translate('Service Type') }}</label><input class="form-control" name="service_type" value="{{ old('service_type', optional($current)->service_type) }}" placeholder="Blank = all"></div>
    <div class="col-md-1"><label>{{ translate('Priority') }}</label><input class="form-control" type="number" min="0" name="priority" value="{{ old('priority', optional($current)->priority ?? 100) }}" required></div>
    <div class="col-md-2"><label>{{ translate('Effective From') }}</label><input class="form-control" type="datetime-local" name="effective_from" value="{{ old('effective_from', optional(optional($current)->effective_from)->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-2"><label>{{ translate('Effective To') }}</label><input class="form-control" type="datetime-local" name="effective_to" value="{{ old('effective_to', optional(optional($current)->effective_to)->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-3"><label>{{ translate('Change Reason') }}</label><input class="form-control" name="change_reason" value="{{ old('change_reason') }}" required></div>
    <div class="col-md-6">
        <label class="d-block">{{ translate('Visible To') }}</label>
        @foreach(['master_admin', 'admin', 'business', 'provider', 'driver', 'shopper'] as $role)
            <label class="mr-2"><input type="checkbox" name="visibility_roles[]" value="{{ $role }}" @checked(in_array($role, old('visibility_roles', optional($current)->visibility_roles ?? ['master_admin', 'admin'])))> {{ str_replace('_', ' ', $role) }}</label>
        @endforeach
    </div>
    <div class="col-md-1"><label class="d-block">{{ translate('Active') }}</label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($current)->is_active ?? true))></div>
</div>
