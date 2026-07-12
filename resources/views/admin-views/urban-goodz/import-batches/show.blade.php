@extends('layouts.admin.app')

@section('title', translate('Import Batch Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Import Batch') }} #{{ $batch->id }}</h1>
                    <p class="text-muted mb-0" style="color: #6c757d !important;">
                        {{ $batch->city ?? '-' }}, {{ $batch->state ?? '-' }} &middot; {{ $batch->category ?? '-' }} &middot; {{ $batch->module ?? '-' }}
                    </p>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.import-batches.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--info">{{ $batch->total_found }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Total Found') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--success">{{ $batch->total_imported }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Imported') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--warning">{{ $batch->total_needs_review }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Needs Review') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            @php
                                $statusMap = ['pending' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
                            @endphp
                            <span class="badge badge-soft-{{ $statusMap[$batch->status] ?? 'secondary' }}">
                                {{ ucfirst($batch->status) }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>{{ translate('City') }}:</strong> {{ $batch->city ?? '-' }}</p>
                        <p><strong>{{ translate('State') }}:</strong> {{ $batch->state ?? '-' }}</p>
                        <p><strong>{{ translate('Category') }}:</strong> {{ $batch->category ?? '-' }}</p>
                        <p><strong>{{ translate('Module') }}:</strong> {{ $batch->module ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Source Query') }}:</strong></p>
                        <pre class="mb-2" style="font-size: 0.85rem;">{{ $batch->source_query ?? '-' }}</pre>
                        <p><strong>{{ translate('Source Platforms') }}:</strong></p>
                        @if(is_array($batch->source_platforms) && count($batch->source_platforms))
                            <div>
                                @foreach($batch->source_platforms as $platform)
                                    <span class="badge badge-soft-info">{{ $platform }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>{{ translate('Admin ID') }}:</strong> {{ $batch->admin_id ?? '-' }}</p>
                        <p><strong>{{ translate('Completed At') }}:</strong> {{ $batch->completed_at?->format('M d, Y h:i A') ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Created') }}:</strong> {{ $batch->created_at?->format('M d, Y h:i A') ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.urban-goodz.import-batches.status', [$batch->id, 'processing']) }}" class="btn btn-outline--info btn-sm">
                    {{ translate('Mark Processing') }}
                </a>
                <a href="{{ route('admin.urban-goodz.import-batches.status', [$batch->id, 'completed']) }}" class="btn btn-outline--success btn-sm">
                    {{ translate('Mark Completed') }}
                </a>
                <a href="{{ route('admin.urban-goodz.import-batches.status', [$batch->id, 'failed']) }}" class="btn btn-outline--danger btn-sm">
                    {{ translate('Mark Failed') }}
                </a>
            </div>
        </div>
    </div>
@endsection
