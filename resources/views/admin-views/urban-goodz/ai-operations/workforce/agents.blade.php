@extends('layouts.admin.app')

@section('title', translate('AI Agents'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('AI Agents') }}</h1>
                <p class="text-muted">{{ translate('Agents configured to execute or recommend workforce actions.') }}</p>
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
                                <th>{{ translate('Name') }}</th>
                                <th>{{ translate('Role') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Autonomy') }}</th>
                                <th>{{ translate('Tasks') }}</th>
                                <th>{{ translate('Actions') }}</th>
                                <th>{{ translate('Last Run') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agents as $agent)
                                <tr>
                                    <td>{{ $agent->name }}</td>
                                    <td>{{ $agent->role }}</td>
                                    <td>{{ ucfirst($agent->status) }}</td>
                                    <td>{{ App\Models\AiAgent::AUTONOMY_LABELS[$agent->autonomy_level] ?? $agent->autonomy_level }}</td>
                                    <td>{{ $agent->tasks_count }}</td>
                                    <td>{{ $agent->actions_count }}</td>
                                    <td>{{ $agent->last_run_at?->format('Y-m-d H:i') ?? translate('Never') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">{{ translate('No AI agents defined yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $agents->links() }}
        </div>
    </div>
@endsection
