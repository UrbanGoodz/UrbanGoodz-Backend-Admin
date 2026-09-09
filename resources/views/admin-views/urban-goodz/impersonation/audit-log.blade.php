@extends('layouts.admin.app')

@section('title', translate('Impersonation Audit Log'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.business-clients.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back to Business Clients') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Impersonation Audit Log') }}</h1>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Total Entries') }}</div>
                    <div class="h3">{{ $logs->total() }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Timestamp') }}</th>
                                <th>{{ translate('Admin') }}</th>
                                <th>{{ translate('Business Client') }}</th>
                                <th>{{ translate('Action') }}</th>
                                <th>{{ translate('Mode') }}</th>
                                <th>{{ translate('Target') }}</th>
                                <th>{{ translate('IP Address') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td><small>{{ $log->created_at?->format('M d, Y h:i A') ?? '-' }}</small></td>
                                <td>
                                    @if($log->admin)
                                        {{ $log->admin->f_name }} {{ $log->admin->l_name }}
                                    @else
                                        <span class="text-muted">{{ translate('Deleted admin') }} #{{ $log->admin_id ?? '-' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->client)
                                        {{ $log->client->company_name ?? ('#'.$log->business_client_id) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->action)
                                        <span class="badge badge-soft-info">{{ $log->action }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $log->mode ?? '-' }}</td>
                                <td>
                                    @if($log->target_type)
                                        <small>{{ class_basename($log->target_type) }}{{ $log->target_id ? ' #'.$log->target_id : '' }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $log->ip_address ?? '-' }}</small></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">{{ translate('No impersonation activity recorded') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
