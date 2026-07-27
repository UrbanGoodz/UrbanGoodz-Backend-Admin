@extends(config('urban_goodz_compensation.layout', 'layouts.admin.app'))

@section('title', 'Compensation Simulator')

@push('css_or_js')
    @include('admin-views.urban-goodz.compensation._styles')
@endpush

@section('content')
<div class="content container-fluid ug-comp">
    @include('admin-views.urban-goodz.compensation._nav')

    <div class="ug-card">
        <h2>Compensation Simulator</h2>
        <p class="ug-muted" style="font-size:.85rem; margin-top:-.4rem;">
            Dry run only. Nothing on this page writes a payout, an earning, or a ledger entry.
        </p>

        <form method="POST" action="{{ route('admin.urban-goodz.compensation.simulate') }}">
            @csrf
            <div class="ug-grid">
                <div class="ug-field">
                    <label for="work_type">Work type</label>
                    <select id="work_type" name="work_type" required>
                        @foreach($workTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($input['work_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ug-field">
                    <label for="service_scope">Service type</label>
                    <input type="text" id="service_scope" name="service_scope" value="{{ $input['service_scope'] ?? '' }}">
                </div>
                <div class="ug-field">
                    <label for="market">Market</label>
                    <input type="text" id="market" name="market" value="{{ $input['market'] ?? '' }}">
                </div>
                <div class="ug-field">
                    <label for="zone_id">Zone</label>
                    <input type="number" id="zone_id" name="zone_id" min="1" value="{{ $input['zone_id'] ?? '' }}">
                </div>
                <div class="ug-field">
                    <label for="vehicle_type">Vehicle</label>
                    <select id="vehicle_type" name="vehicle_type">
                        <option value="">Any</option>
                        @foreach($vehicles as $value => $label)
                            <option value="{{ $value }}" @selected(($input['vehicle_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach([
                    'miles' => 'Miles', 'loaded_miles' => 'Loaded miles', 'deadhead_miles' => 'Deadhead miles',
                    'stops' => 'Stops', 'packages' => 'Packages', 'delivered_packages' => 'Delivered packages',
                    'minutes' => 'Minutes', 'wait_minutes' => 'Wait minutes', 'detention_minutes' => 'Detention minutes',
                    'layover_nights' => 'Layover nights', 'extra_stops' => 'Additional stops',
                    'customer_charge_cents' => 'Customer charge (cents)', 'linehaul_cents' => 'Load rate / linehaul (cents)',
                    'delivery_charge_cents' => 'Delivery charge (cents)', 'tolls_cents' => 'Tolls (cents)',
                    'reimbursements_cents' => 'Reimbursements (cents)', 'tips_cents' => 'Tips (cents)',
                    'batched_orders' => 'Batched orders',
                ] as $field => $label)
                    <div class="ug-field">
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input type="number" step="any" min="0" id="{{ $field }}" name="{{ $field }}"
                               value="{{ $input[$field] ?? '' }}">
                    </div>
                @endforeach
            </div>

            <div class="ug-field" style="margin-top:.9rem;">
                <label>Terminal state &amp; special flags</label>
                <div style="display:flex; flex-wrap:wrap; gap:.75rem;">
                    @foreach([
                        'is_cancelled' => 'Cancelled', 'is_failed_delivery' => 'Failed delivery',
                        'is_failed_handoff' => 'Failed handoff', 'is_redelivery' => 'Redelivery',
                        'is_return_trip' => 'Return trip', 'is_return_specimen' => 'Return specimen',
                        'route_completed' => 'Route completed', 'is_peak' => 'Peak / surge',
                        'is_after_hours' => 'After hours', 'is_weekend' => 'Weekend', 'is_overnight' => 'Overnight',
                        'is_stat' => 'STAT', 'requires_chain_of_custody' => 'Chain of custody',
                        'requires_temperature_control' => 'Temperature control',
                        'is_heavy_item' => 'Heavy item', 'driver_assist' => 'Driver assist',
                    ] as $flag => $label)
                        <label style="font-weight:400; text-transform:none; letter-spacing:0; font-size:.85rem;">
                            <input type="checkbox" name="{{ $flag }}" value="1" @checked($input[$flag] ?? false)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="ug-btn ug-btn-primary" style="margin-top:1rem;">Run simulation</button>
        </form>
    </div>

    @if($simulation)
        @if(!$simulation['matched'])
            <div class="ug-card">
                <h2>No rule matched</h2>
                <p class="ug-muted">{{ $simulation['reason'] ?? 'No published, active, in-effect rule matches this context.' }}</p>
            </div>
        @else
            @php($calc = $simulation['calculation'])
            @php($splits = $calc['splits'])

            <div class="ug-card">
                <h2>Selected rule</h2>
                <p>
                    <strong>{{ $simulation['selected_rule']['name'] }}</strong>
                    <span class="ug-badge">v{{ $simulation['selected_rule']['version'] }}</span>
                    <span class="ug-muted">{{ $simulation['selected_rule']['rule_key'] }}</span>
                </p>
                <h2 style="margin-top:1rem;">Why this rule won</h2>
                <div class="ug-table-wrap">
                    <table class="ug-table">
                        <thead><tr><th>Rule</th><th class="ug-num">Priority</th><th class="ug-num">Specificity</th><th class="ug-num">Version</th><th>Outcome</th></tr></thead>
                        <tbody>
                        @foreach($simulation['candidates'] as $candidate)
                            <tr>
                                <td>{{ $candidate['name'] }} <span class="ug-muted">({{ $candidate['rule_key'] }})</span></td>
                                <td class="ug-num">{{ $candidate['priority'] }}</td>
                                <td class="ug-num">{{ $candidate['specificity'] }}</td>
                                <td class="ug-num">v{{ $candidate['version'] }}</td>
                                <td>{!! $candidate['selected'] ? '<span class="ug-badge ug-badge-published">selected</span>' : '<span class="ug-badge ug-badge-archived">displaced</span>' !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ug-card">
                <h2>Calculation components</h2>
                <div class="ug-table-wrap">
                    <table class="ug-table">
                        <thead><tr><th>Component</th><th>Inputs</th><th class="ug-num">Amount</th></tr></thead>
                        <tbody>
                        @foreach($calc['breakdown']['lines'] as $line)
                            <tr>
                                <td>{{ $line['label'] }}<div class="ug-muted" style="font-size:.75rem;">{{ $line['code'] }}</div></td>
                                <td class="ug-muted" style="font-size:.78rem;">
                                    @foreach($line['inputs'] as $k => $v)
                                        {{ $k }}={{ is_bool($v) ? ($v ? 'true' : 'false') : $v }}@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td class="ug-num">{{ $line['amount'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="2">Subtotal</th><th class="ug-num">{{ $calc['breakdown']['subtotal'] }}</th></tr>
                        </tfoot>
                    </table>
                </div>

                @if(!empty($calc['breakdown']['adjustments']))
                    <h2 style="margin-top:1rem;">Adjustments applied</h2>
                    <ul style="font-size:.85rem;">
                        @foreach($calc['breakdown']['adjustments'] as $adjustment)
                            <li><strong>{{ $adjustment['label'] }}</strong>
                                <span class="ug-muted">{{ json_encode($adjustment['detail']) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="ug-card">
                <h2>Driver outcome</h2>
                <div class="ug-grid">
                    <div><strong>Earned (after min/max)</strong><br>{{ number_format($calc['earned_cents'] / 100, 2) }}</div>
                    <div><strong>Pass-through</strong><br>{{ number_format($calc['pass_through_cents'] / 100, 2) }}</div>
                    <div><strong>Final driver amount</strong><br><span style="font-size:1.15rem; font-weight:650;">{{ $calc['driver_amount'] }}</span></div>
                    <div><strong>Rounding</strong><br>{{ $calc['rounding_mode'] }}</div>
                </div>
            </div>

            <div class="ug-card">
                <h2>Splits &amp; ledger preview</h2>
                @if($splits['is_deficit'])
                    <div class="ug-alert ug-alert-error">
                        <strong>Deficit.</strong> This rule pays out more than the job collects.
                        Platform remainder is {{ number_format($splits['platform_cents'] / 100, 2) }}.
                    </div>
                @endif
                <div class="ug-table-wrap">
                    <table class="ug-table">
                        <thead><tr><th>Party</th><th class="ug-num">Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Total collected (basis: {{ $splits['basis'] }})</td><td class="ug-num">{{ number_format($splits['basis_cents'] / 100, 2) }}</td></tr>
                            <tr><td>Driver</td><td class="ug-num">{{ number_format($splits['driver_cents'] / 100, 2) }}</td></tr>
                            @foreach($splitParties as $party => $label)
                                <tr><td>{{ $label }}</td><td class="ug-num">{{ number_format(($splits[$party . '_cents'] ?? 0) / 100, 2) }}</td></tr>
                            @endforeach
                            <tr><td>Urban Goodz platform remainder</td>
                                <td class="ug-num {{ $splits['is_deficit'] ? 'ug-badge-deficit' : '' }}">{{ number_format($splits['platform_cents'] / 100, 2) }}</td></tr>
                            <tr><td>Driver pass-through (tolls, reimbursements, tips)</td><td class="ug-num">{{ number_format($splits['driver_pass_through_cents'] / 100, 2) }}</td></tr>
                            <tr><td><strong>Total distributed</strong></td><td class="ug-num"><strong>{{ number_format($splits['reconciled_cents'] / 100, 2) }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
                <p style="font-size:.85rem; margin-top:.6rem;">
                    Reconciles to basis:
                    <strong class="{{ $splits['reconciles'] ? '' : 'ug-badge-deficit' }}">{{ $splits['reconciles'] ? 'yes' : 'NO' }}</strong>
                </p>
            </div>

            <div class="ug-card">
                <h2>Full derivation</h2>
                <div class="ug-explain">{{ $calc['explanation'] }}</div>
            </div>
        @endif
    @endif
</div>
@endsection
