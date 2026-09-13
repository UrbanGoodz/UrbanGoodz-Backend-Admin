@extends('layouts.admin.app')

@section('title', translate('Impersonation Audit Log'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-security-on"></i></span>
                <span>{{ translate('Impersonation Audit Log') }}</span>
                <span class="badge badge-soft-dark ms-2">{{ $logs->total() }}</span>
            </h1>
            <p class="page-header-text mb-0">
                {{ translate('Every admin entry into a business client account, and what was done while inside it.') }}
            </p>
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('When') }}</th>
                            <th>{{ translate('Admin') }}</th>
                            <th>{{ translate('Business Client') }}</th>
                            <th>{{ translate('Action') }}</th>
                            <th>{{ translate('Mode') }}</th>
                            <th>{{ translate('Target') }}</th>
                            <th>{{ translate('IP') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $key => $log)
                            <tr>
                                <td>{{ $logs->firstItem() + $key }}</td>
                                <td>{{ $log->created_at ? $log->created_at->format('d M Y H:i') : '-' }}</td>
                                <td>#{{ $log->admin_id ?? '-' }}</td>
                                <td>
                                    @if ($log->client)
                                        {{ $log->client->company_name ?? ('#' . $log->business_client_id) }}
                                    @else
                                        #{{ $log->business_client_id ?? '-' }}
                                    @endif
                                </td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', (string) $log->action) }}</td>
                                <td>
                                    {{-- read-only vs write matters most when reviewing this log --}}
                                    <span class="badge badge-soft-{{ $log->mode === 'write' ? 'danger' : 'secondary' }}">
                                        {{ $log->mode ?: translate('n/a') }}
                                    </span>
                                </td>
                                <td>
                                    @if ($log->target_type)
                                        {{ class_basename($log->target_type) }}{{ $log->target_id ? ' #' . $log->target_id : '' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $log->ip_address ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">{{ translate('no_data_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="page-area px-4 pb-3">
                {!! $logs->links() !!}
            </div>
        </div>
    </div>
@endsection
