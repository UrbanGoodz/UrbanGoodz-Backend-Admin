@extends('layouts.admin.app')

@section('title', translate('AI Workforce - Business Needs'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('Business Needs') }}</h1>
                <p class="text-muted">{{ translate('Automatically detected operational shortages, inventory gaps, and staffing warnings.') }}</p>
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
                                <th>{{ translate('Need Type') }}</th>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Priority') }}</th>
                                <th>{{ translate('Severity') }}</th>
                                <th>{{ translate('Assigned Role') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Created At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($needs as $need)
                                <tr>
                                    <td>#{{ $need->id }}</td>
                                    <td><span class="badge badge-soft-dark">{{ str_replace('_', ' ', $need->type) }}</span></td>
                                    <td>
                                        <strong>{{ $need->title }}</strong>
                                        <div class="text-muted small">{{ $need->description }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $need->priority === 'high' ? 'danger' : ($need->priority === 'medium' ? 'warning' : 'info') }}">
                                            {{ $need->priority }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $need->severity === 'critical' || $need->severity === 'high' ? 'danger' : 'warning' }}">
                                            {{ $need->severity }}
                                        </span>
                                    </td>
                                    <td>{{ $need->assigned_human_role ?: translate('None') }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $need->status === 'resolved' ? 'success' : 'warning' }}">
                                            {{ $need->status }}
                                        </span>
                                    </td>
                                    <td>{{ $need->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center p-4">
                                        <p class="text-muted mb-0">{{ translate('No business needs detected.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($needs->hasPages())
                <div class="card-footer">
                    {!! $needs->links() !!}
                </div>
            @endif
        </div>
    </div>
@endsection
