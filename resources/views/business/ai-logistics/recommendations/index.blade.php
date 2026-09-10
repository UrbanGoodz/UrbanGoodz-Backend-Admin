@extends('business.layouts.app')

@section('title', translate('AI Recommendations'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('AI Recommendations') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('AI Recommendations') }}</h1>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Type') }}</th><th>{{ translate('Recommendation') }}</th><th>{{ translate('Status') }}</th><th class="text-center">{{ translate('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($recommendations as $rec)
                        <tr>
                            <td>{{ ucwords(str_replace('_',' ', $rec->recommendation_type)) }}</td>
                            <td><small>{{ Illuminate\Support\Str::limit($rec->suggested_action, 60) }} — {{ Illuminate\Support\Str::limit($rec->reason, 80) }}</small></td>
                            <td><span class="badge badge-soft-info">{{ ucwords($rec->status) }}</span></td>
                            <td class="text-center">
                                @if($rec->status === 'pending')
                                <div class="d-flex gap-1 justify-content-center">
                                    <form method="POST" action="{{ route('business.ai-logistics.copilot.accept', $rec->id) }}">@csrf<button class="btn btn-sm btn-outline-success">{{ translate('Accept') }}</button></form>
                                    <form method="POST" action="{{ route('business.ai-logistics.copilot.dismiss', $rec->id) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ translate('Dismiss') }}</button></form>
                                    <form method="POST" action="{{ route('business.ai-logistics.copilot.snooze', $rec->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ translate('Snooze') }}</button></form>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ translate('No pending recommendations.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($recommendations instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $recommendations->links() }}</div>
        @endif
    </div>
@endsection
