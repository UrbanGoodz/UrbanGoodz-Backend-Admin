@extends('layouts.admin.app')

@section('title', translate('AI Risk Rules'))

@push('css_or_js')
<style>
    .risk-level-critical { background: #dc3545; color: #fff; }
    .risk-level-high { background: #fd7e14; color: #fff; }
    .risk-level-medium { background: #ffc107; color: #000; }
    .risk-level-low { background: #28a745; color: #fff; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Copilot') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Risk Rules') }}</h1>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <div class="stat-number fw-bold text--danger">{{ $rules->where('risk_level', 'critical')->count() }}</div>
                    <small class="text-muted">{{ translate('Critical') }}</small>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <div class="stat-number fw-bold text--warning">{{ $rules->where('risk_level', 'high')->count() }}</div>
                    <small class="text-muted">{{ translate('High') }}</small>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <div class="stat-number fw-bold text--secondary">{{ $rules->where('risk_level', 'medium')->count() }}</div>
                    <small class="text-muted">{{ translate('Medium') }}</small>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <div class="stat-number fw-bold text--success">{{ $rules->where('enabled', true)->count() }}/{{ $rules->count() }}</div>
                    <small class="text-muted">{{ translate('Enabled') }}</small>
                </div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Risk Rules') }}</h5>
                <button type="button" class="btn btn--primary" data-toggle="modal" data-target="#createModal">
                    <i class="tio-add"></i> {{ translate('New Rule') }}
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Rule') }}</th>
                                <th>{{ translate('Trigger') }}</th>
                                <th>{{ translate('Risk Level') }}</th>
                                <th>{{ translate('Requires Approval') }}</th>
                                <th>{{ translate('Escalation') }}</th>
                                <th class="text-center">{{ translate('Enabled') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rules as $rule)
                            <tr>
                                <td><strong>{{ $rule->rule_name }}</strong></td>
                                <td>
                                    <small>{{ $rule->trigger_type }}
                                    @if($rule->trigger_operator)
                                        {{ $rule->trigger_operator }} {{ $rule->trigger_value }}
                                    @endif
                                    </small>
                                </td>
                                <td>
                                    <span class="badge risk-level-{{ $rule->risk_level }}">{{ ucfirst($rule->risk_level) }}</span>
                                </td>
                                <td>
                                    @if($rule->requires_approval)
                                        <span class="badge badge-soft-warning">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $rule->escalation_action ? ucwords(str_replace('_', ' ', $rule->escalation_action)) : '-' }}</small></td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.risk-rules.toggle', $rule->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $rule->enabled ? 'btn-success' : 'btn-secondary' }}">
                                            {{ $rule->enabled ? translate('On') : translate('Off') }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-rule-btn"
                                        data-id="{{ $rule->id }}"
                                        data-rule_name="{{ $rule->rule_name }}"
                                        data-trigger_type="{{ $rule->trigger_type }}"
                                        data-trigger_operator="{{ $rule->trigger_operator }}"
                                        data-trigger_value="{{ $rule->trigger_value }}"
                                        data-risk_level="{{ $rule->risk_level }}"
                                        data-requires_approval="{{ $rule->requires_approval ? '1' : '0' }}"
                                        data-escalation_action="{{ $rule->escalation_action }}">
                                        <i class="tio-edit"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.risk-rules.delete', $rule->id) }}" class="d-inline"
                                        onsubmit="return confirm('{{ translate('Are you sure you want to delete this rule?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="tio-delete"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No risk rules defined') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="createModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.risk-rules.save') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ translate('New Risk Rule') }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>{{ translate('Rule Name') }}</label>
                                <input type="text" name="rule_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Trigger Type') }}</label>
                                <input type="text" name="trigger_type" class="form-control" placeholder="e.g. refund_amount, payout_change" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ translate('Operator') }}</label>
                                        <select name="trigger_operator" class="form-control">
                                            <option value="">{{ translate('None (exact match)') }}</option>
                                            <option value=">">&gt;</option>
                                            <option value="<">&lt;</option>
                                            <option value=">=">&gt;=</option>
                                            <option value="<=">&lt;=</option>
                                            <option value="=">=</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ translate('Value') }}</label>
                                        <input type="text" name="trigger_value" class="form-control" placeholder="e.g. 25">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Risk Level') }}</label>
                                <select name="risk_level" class="form-control" required>
                                    <option value="low">{{ translate('Low') }}</option>
                                    <option value="medium" selected>{{ translate('Medium') }}</option>
                                    <option value="high">{{ translate('High') }}</option>
                                    <option value="critical">{{ translate('Critical') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Escalation Action') }}</label>
                                <select name="escalation_action" class="form-control">
                                    <option value="">{{ translate('None') }}</option>
                                    <option value="flag_for_review">{{ translate('Flag for Review') }}</option>
                                    <option value="block_action">{{ translate('Block Action') }}</option>
                                    <option value="notify_admin">{{ translate('Notify Admin') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="hidden" name="requires_approval" value="0">
                                    <input type="checkbox" name="requires_approval" value="1" checked>
                                    <span class="toggle-switch-slider"></span>
                                    <span class="ml-2">{{ translate('Requires Approval') }}</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" checked>
                                    <span class="toggle-switch-slider"></span>
                                    <span class="ml-2">{{ translate('Enabled') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                            <button type="submit" class="btn btn--primary">{{ translate('Create Rule') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" id="editRuleForm">
                    @csrf @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ translate('Edit Risk Rule') }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>{{ translate('Rule Name') }}</label>
                                <input type="text" name="rule_name" id="edit_rule_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Trigger Type') }}</label>
                                <input type="text" name="trigger_type" id="edit_trigger_type" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ translate('Operator') }}</label>
                                        <select name="trigger_operator" id="edit_trigger_operator" class="form-control">
                                            <option value="">{{ translate('None (exact match)') }}</option>
                                            <option value=">">&gt;</option>
                                            <option value="<">&lt;</option>
                                            <option value=">=">&gt;=</option>
                                            <option value="<=">&lt;=</option>
                                            <option value="=">=</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ translate('Value') }}</label>
                                        <input type="text" name="trigger_value" id="edit_trigger_value" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Risk Level') }}</label>
                                <select name="risk_level" id="edit_risk_level" class="form-control" required>
                                    <option value="low">{{ translate('Low') }}</option>
                                    <option value="medium">{{ translate('Medium') }}</option>
                                    <option value="high">{{ translate('High') }}</option>
                                    <option value="critical">{{ translate('Critical') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Escalation Action') }}</label>
                                <select name="escalation_action" id="edit_escalation_action" class="form-control">
                                    <option value="">{{ translate('None') }}</option>
                                    <option value="flag_for_review">{{ translate('Flag for Review') }}</option>
                                    <option value="block_action">{{ translate('Block Action') }}</option>
                                    <option value="notify_admin">{{ translate('Notify Admin') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="hidden" name="requires_approval" value="0">
                                    <input type="checkbox" name="requires_approval" id="edit_requires_approval" value="1">
                                    <span class="toggle-switch-slider"></span>
                                    <span class="ml-2">{{ translate('Requires Approval') }}</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" checked>
                                    <span class="toggle-switch-slider"></span>
                                    <span class="ml-2">{{ translate('Enabled') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                            <button type="submit" class="btn btn--primary">{{ translate('Update Rule') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('script_js')
    <script>
        document.querySelectorAll('.edit-rule-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.dataset.id;
                document.getElementById('editRuleForm').action = '{{ route("admin.urban-goodz.ai-copilot.risk-rules.update", ":id") }}'.replace(':id', id);
                document.getElementById('edit_rule_name').value = this.dataset.rule_name;
                document.getElementById('edit_trigger_type').value = this.dataset.trigger_type;
                document.getElementById('edit_trigger_operator').value = this.dataset.trigger_operator || '';
                document.getElementById('edit_trigger_value').value = this.dataset.trigger_value || '';
                document.getElementById('edit_risk_level').value = this.dataset.risk_level;
                document.getElementById('edit_escalation_action').value = this.dataset.escalation_action || '';
                document.getElementById('edit_requires_approval').checked = this.dataset.requires_approval === '1';
                $('#editModal').modal('show');
            });
        });
    </script>
    @endpush
@endsection
