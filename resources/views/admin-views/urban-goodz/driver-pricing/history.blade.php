@extends('layouts.admin.app')

@section('title', translate('Driver Pricing Policy History'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h1 class="page-header-title">
                    <span class="page-header-icon"><i class="tio-history text-primary mr-1"></i></span>
                    <span>{{ translate('Audit History & Rollback') }}: {{ $policy->name }}</span>
                </h1>
                <a href="{{ route('admin.urban-goodz.driver-pricing.index') }}" class="btn btn-outline-secondary">
                    {{ translate('Back to Pricing Dashboard') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title">{{ translate('Version History Logs') }}</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Event') }}</th>
                                <th>{{ translate('Modified By') }}</th>
                                <th>{{ translate('Details') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $key => $log)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $log->event === 'created' ? 'success' : ($log->event === 'deleted' ? 'danger' : ($log->event === 'rollback' ? 'warning' : 'primary')) }} text-capitalize">
                                            {{ $log->event }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->causer)
                                            {{ $log->causer->f_name . ' ' . $log->causer->l_name }} ({{ translate('Admin') }})
                                        @else
                                            <span class="text-muted">{{ translate('System') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="d-block text-muted">{{ $log->description }}</small>
                                        
                                        @if(!empty($log->old_values) && !empty($log->new_values))
                                            <div class="mt-2">
                                                <button class="btn btn-xs btn-outline-info" type="button" data-toggle="collapse" data-target="#diff-{{ $log->id }}">
                                                    {{ translate('View Changes') }}
                                                </button>
                                                <div class="collapse mt-1" id="diff-{{ $log->id }}">
                                                    <div class="bg-light p-2 rounded" style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-borderless font-size-sm mb-0">
                                                            <thead>
                                                                <tr class="border-bottom"><th class="py-1">{{ translate('Field') }}</th><th class="py-1">{{ translate('From') }}</th><th class="py-1">{{ translate('To') }}</th></tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($log->old_values as $field => $oldValue)
                                                                    @if(array_key_exists($field, $log->new_values) && $oldValue != $log->new_values[$field])
                                                                        <tr>
                                                                            <td><code>{{ $field }}</code></td>
                                                                            <td class="text-danger">{{ is_array($oldValue) ? json_encode($oldValue) : ($oldValue ?? 'null') }}</td>
                                                                            <td class="text-success">{{ is_array($log->new_values[$field]) ? json_encode($log->new_values[$field]) : ($log->new_values[$field] ?? 'null') }}</td>
                                                                        </tr>
                                                                    @endif
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $log->created_at->format('M d, Y g:i A') }}</td>
                                    <td>
                                        <!-- Do not allow rollback for creation log if it represents the current active state, but allow rollback to old values for updates -->
                                        @if($log->event === 'updated' || $log->event === 'rollback')
                                            <form action="{{ route('admin.urban-goodz.driver-pricing.rollback', $policy->id) }}" method="POST" onsubmit="return confirm('{{ translate('Revert policy back to this version state?') }}')">
                                                @csrf
                                                <input type="hidden" name="log_id" value="{{ $log->id }}">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    <i class="tio-history"></i> {{ translate('Rollback') }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">{{ translate('No modification logs recorded for this policy yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
