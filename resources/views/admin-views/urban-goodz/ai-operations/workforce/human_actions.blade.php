@extends('layouts.admin.app')

@section('title', translate('AI Workforce - Human Action Items'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('Human Action Items') }}</h1>
                <p class="text-muted">{{ translate('Actionable tasks requiring manual human verification or execution.') }}</p>
            </div>
            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-back"></i> {{ translate('Back to Workforce') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-styled table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('ID') }}</th>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Assigned Role') }}</th>
                                <th>{{ translate('Area') }}</th>
                                <th>{{ translate('Priority') }}</th>
                                <th>{{ translate('Due Date') }}</th>
                                <th>{{ translate('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>#{{ $item->id }}</td>
                                    <td>
                                        <strong>{{ $item->title }}</strong>
                                        <div class="text-muted small">{{ $item->description }}</div>
                                        @if($item->recommended_next_step)
                                            <div class="text-info mt-1 small">
                                                <strong>{{ translate('Recommendation:') }}</strong> {{ $item->recommended_next_step }}
                                            </div>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-soft-primary">{{ $item->assigned_role }}</span></td>
                                    <td><code>{{ $item->business_area }}</code></td>
                                    <td>
                                        <span class="badge badge-soft-{{ $item->priority === 'urgent' || $item->priority === 'high' ? 'danger' : 'warning' }}">
                                            {{ $item->priority }}
                                        </span>
                                    </td>
                                    <td>{{ $item->due_date ? $item->due_date->format('Y-m-d H:i') : translate('No deadline') }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $item->status === 'completed' ? 'success' : ($item->status === 'escalated' ? 'danger' : 'warning') }}">
                                            {{ $item->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center p-4">
                                        <p class="text-muted mb-0">{{ translate('No human action items pending.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($items->hasPages())
                <div class="card-footer">
                    {!! $items->links() !!}
                </div>
            @endif
        </div>
    </div>
@endsection
