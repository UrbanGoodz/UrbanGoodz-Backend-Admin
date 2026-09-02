@extends('layouts.admin.app')

@section('title', translate('Audit Trail'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', $config->id) }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Audit Trail') }}: {{ $config->configuration_name }}</h1>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Timestamp') }}</th>
                                <th>{{ translate('Action') }}</th>
                                <th>{{ translate('Entity') }}</th>
                                <th>{{ translate('Description') }}</th>
                                <th>{{ translate('Admin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trail as $entry)
                            <tr>
                                <td>{{ $entry->id }}</td>
                                <td><small>{{ $entry->created_at->format('M d, Y h:i A') }}</small></td>
                                <td><span class="badge badge-soft-info">{{ $entry->action }}</span></td>
                                <td><small>{{ class_basename($entry->entity_type) }} #{{ $entry->entity_id }}</small></td>
                                <td><span style="max-width:300px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $entry->description ?? '-' }}</span></td>
                                <td>{{ $entry->admin_id ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ translate('No audit trail entries') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $trail->links() }}
            </div>
        </div>
    </div>
@endsection
