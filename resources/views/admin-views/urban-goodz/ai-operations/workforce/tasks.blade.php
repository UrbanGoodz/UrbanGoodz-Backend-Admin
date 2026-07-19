@extends('layouts.admin.app')

@section('title', translate('AI Workforce Tasks'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('AI Workforce Tasks') }}</h1>
                <p class="text-muted">{{ translate('Inspect and monitor AI-generated tasks.') }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-back"></i> {{ translate('Workforce Overview') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline-secondary">
                    <i class="tio-arrow-back"></i> {{ translate('AI Operations') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Task') }}</th>
                                <th>{{ translate('Agent') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Priority') }}</th>
                                <th>{{ translate('Confidence') }}</th>
                                <th>{{ translate('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tasks as $task)
                                <tr>
                                    <td>{{ Str::limit($task->objective ?? $task->task_type, 80) }}</td>
                                    <td>{{ $task->agent?->name ?? translate('Unknown') }}</td>
                                    <td>{{ $task->task_type }}</td>
                                    <td>{{ ucfirst($task->status) }}</td>
                                    <td>{{ ucfirst($task->priority) }}</td>
                                    <td>{{ $task->confidence?->format(2) ?? '-' }}</td>
                                    <td>{{ $task->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">{{ translate('No AI tasks found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $tasks->links() }}
        </div>
    </div>
@endsection
