@extends('layouts.admin.app')

@section('title', translate('Admin Marketplace Data Center'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Admin Marketplace Data Center') }}</h1>
            <p class="text-muted mb-0">
                {{ translate('Review-gated business, catalog, product, and image sourcing. Staging and approval never create live stores or items.') }}
            </p>
        </div>

        <div class="alert alert-warning">
            {{ translate('Shopper visibility is a separate action after validation, production classification, source verification, product approval, and image-rights approval.') }}
        </div>

        <div class="row g-3 mb-3">
            @foreach([
                'queued' => 'Queued',
                'review_required' => 'Review required',
                'failed' => 'Failures / retries',
                'approved' => 'Approved batches',
                'api_visible' => 'API visible',
                'shopper_visible' => 'Shopper visible',
            ] as $key => $label)
                <div class="col-sm-6 col-xl-2">
                    <div class="card h-100 p-3">
                        <div class="text-muted">{{ translate($label) }}</div>
                        <div class="h3 mb-0">{{ $stats[$key] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h4 mb-0">{{ translate('Import and sourcing queues') }}</h2>
                <form method="get" class="d-flex gap-2">
                    <select name="queue_type" class="form-select">
                        <option value="">{{ translate('All queues') }}</option>
                        <option value="import" @selected(request('queue_type') === 'import')>{{ translate('Import') }}</option>
                        <option value="sourcing" @selected(request('queue_type') === 'sourcing')>{{ translate('Sourcing') }}</option>
                    </select>
                    <input name="status" value="{{ request('status') }}" class="form-control" placeholder="{{ translate('Status') }}">
                    <button class="btn btn--primary">{{ translate('Filter') }}</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Batch') }}</th>
                            <th>{{ translate('Queue') }}</th>
                            <th>{{ translate('Priority') }}</th>
                            <th>{{ translate('Location') }}</th>
                            <th>{{ translate('Category') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Records') }}</th>
                            <th>{{ translate('Failures') }}</th>
                            <th>{{ translate('Attempts') }}</th>
                            <th>{{ translate('Preview') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            <tr>
                                <td>#{{ $batch->id }}</td>
                                <td>{{ ucfirst($batch->queue_type) }}</td>
                                <td>{{ $batch->priority }}</td>
                                <td>{{ $batch->city }}, {{ $batch->state }}</td>
                                <td>{{ $batch->category }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $batch->status)) }}</td>
                                <td>{{ $batch->total_imported }}</td>
                                <td>{{ $batch->total_failed }}</td>
                                <td>{{ $batch->attempt_count }}/{{ $batch->max_attempts }}</td>
                                <td>
                                    <a class="btn btn--secondary btn-sm"
                                       href="{{ route('admin.urban-goodz.data-center.batches.preview', $batch) }}">
                                        {{ translate('JSON preview') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">{{ translate('No data-center batches found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $batches->links() }}</div>
        </div>
    </div>
@endsection
