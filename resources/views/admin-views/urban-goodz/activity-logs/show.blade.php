@extends('layouts.admin.app')

@section('title', translate('Activity Log Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.activity-logs.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back to Activity Logs') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Activity Log') }} #{{ $log->id }}</h1>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">{{ translate('Log Details') }}</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ translate('ID') }}</dt>
                            <dd class="col-sm-8">{{ $log->id }}</dd>

                            <dt class="col-sm-4">{{ translate('Timestamp') }}</dt>
                            <dd class="col-sm-8">{{ $log->created_at->format('M d, Y h:i:s A') }}</dd>

                            <dt class="col-sm-4">{{ translate('Event') }}</dt>
                            <dd class="col-sm-8">
                                @if($log->event)
                                <span class="badge badge-soft-info">{{ $log->event }}</span>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4">{{ translate('Description') }}</dt>
                            <dd class="col-sm-8">{{ $log->description ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">{{ translate('Old Values') }}</div>
                    <div class="card-body">
                        @if($log->old_values && count($log->old_values) > 0)
                        <pre class="bg-light p-3 rounded mb-0" style="font-size: 0.8rem; max-height: 300px; overflow-y: auto;">@json($log->old_values, JSON_PRETTY_PRINT)</pre>
                        @else
                        <p class="text-muted mb-0">{{ translate('No old values recorded') }}</p>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">{{ translate('New Values') }}</div>
                    <div class="card-body">
                        @if($log->new_values && count($log->new_values) > 0)
                        <pre class="bg-light p-3 rounded mb-0" style="font-size: 0.8rem; max-height: 300px; overflow-y: auto;">@json($log->new_values, JSON_PRETTY_PRINT)</pre>
                        @else
                        <p class="text-muted mb-0">{{ translate('No new values recorded') }}</p>
                        @endif
                    </div>
                </div>

                @if($log->metadata && count($log->metadata) > 0)
                <div class="card mb-3">
                    <div class="card-header">{{ translate('Metadata') }}</div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded mb-0" style="font-size: 0.8rem; max-height: 300px; overflow-y: auto;">@json($log->metadata, JSON_PRETTY_PRINT)</pre>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">{{ translate('Causer') }}</div>
                    <div class="card-body">
                        @if($log->causer_type)
                        <dl class="row mb-0">
                            <dt class="col-sm-5">{{ translate('Type') }}</dt>
                            <dd class="col-sm-7">{{ class_basename($log->causer_type) }}</dd>

                            <dt class="col-sm-5">{{ translate('ID') }}</dt>
                            <dd class="col-sm-7">{{ $log->causer_id }}</dd>

                            @if($log->causer && method_exists($log->causer, 'getAttribute'))
                            @if($log->causer->getAttribute('name') ?? null)
                            <dt class="col-sm-5">{{ translate('Name') }}</dt>
                            <dd class="col-sm-7">{{ $log->causer->getAttribute('name') }}</dd>
                            @endif
                            @if($log->causer->getAttribute('email') ?? null)
                            <dt class="col-sm-5">{{ translate('Email') }}</dt>
                            <dd class="col-sm-7">{{ $log->causer->getAttribute('email') }}</dd>
                            @endif
                            @endif
                        </dl>
                        @else
                        <p class="text-muted mb-0">{{ translate('System / No causer') }}</p>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">{{ translate('Loggable') }}</div>
                    <div class="card-body">
                        @if($log->loggable_type)
                        <dl class="row mb-0">
                            <dt class="col-sm-5">{{ translate('Type') }}</dt>
                            <dd class="col-sm-7">{{ class_basename($log->loggable_type) }}</dd>

                            <dt class="col-sm-5">{{ translate('ID') }}</dt>
                            <dd class="col-sm-7">{{ $log->loggable_id }}</dd>
                        </dl>
                        @else
                        <p class="text-muted mb-0">{{ translate('No loggable model') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
