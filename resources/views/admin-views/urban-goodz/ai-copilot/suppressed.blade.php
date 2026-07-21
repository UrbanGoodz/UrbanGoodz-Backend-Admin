@extends('layouts.admin.app')

@section('title', translate('Suppressed Recommendations'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('Suppressed & Snoozed Recommendations') }}</h1>
                <small class="text-muted">{{ translate('View and restore recommendations that were dismissed, snoozed, marked resolved, or permanently suppressed.') }}</small>
            </div>
            <div>
                <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline-secondary">
                    <i class="tio-back"></i> {{ translate('Back to Copilot') }}
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Suppressed Logs') }}</h5>
                <form method="GET" class="d-flex gap-2">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Suppressed Statuses') }}</option>
                        <option value="snoozed" {{ request('status') === 'snoozed' ? 'selected' : '' }}>{{ translate('Snoozed') }}</option>
                        <option value="dont_show_again" {{ request('status') === 'dont_show_again' ? 'selected' : '' }}>{{ translate('Don\'t Show Again') }}</option>
                        <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>{{ translate('Dismissed Once') }}</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>{{ translate('Resolved') }}</option>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Suggestion') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Suppression Expiration') }}</th>
                                <th>{{ translate('Reviewed By') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recommendations as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>
                                    <span class="badge badge-soft-secondary">
                                        {{ ucwords(str_replace('_', ' ', $r->recommendation_type)) }}
                                    </span>
                                </td>
                                <td style="max-width: 320px;">
                                    <strong>{{ $r->suggested_action }}</strong>
                                    <br><small class="text-muted">{{ $r->reason }}</small>
                                </td>
                                <td>
                                    @php
                                        $sBadge = ['snoozed' => 'info', 'dont_show_again' => 'danger', 'dismissed' => 'secondary', 'resolved' => 'success'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $sBadge[$r->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $r->status)) }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($r->metadata['suppressed_until']))
                                        <span class="text-primary font-weight-bold">{{ \Carbon\Carbon::parse($r->metadata['suppressed_until'])->format('M d, Y H:i') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $r->reviewer ? ($r->reviewer->f_name . ' ' . $r->reviewer->l_name) : 'System' }}</small>
                                    <br><small class="text-muted">{{ $r->reviewed_at ? $r->reviewed_at->format('M d, H:i') : '' }}</small>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.restore', $r->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-info" title="Restore to Active Recommendations">
                                            <i class="tio-redo"></i> {{ translate('Restore') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ translate('No suppressed recommendations found.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $recommendations->links() }}
            </div>
        </div>
    </div>
@endsection
