@extends('business.layouts.app')

@section('title', translate('Audit Log'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Audit Log') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Audit Log') }}</h1>
    </div>

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <select name="action_type" class="form-control" style="max-width: 220px;">
            <option value="">{{ translate('All Actions') }}</option>
            @foreach($actionTypes as $type)
                <option value="{{ $type }}" {{ request('action_type') === $type ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ', $type)) }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" class="form-control" style="max-width: 180px;" value="{{ request('date_from') }}">
        <input type="date" name="date_to" class="form-control" style="max-width: 180px;" value="{{ request('date_to') }}">
        <button type="submit" class="btn btn-outline-secondary">{{ translate('Filter') }}</button>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Action') }}</th><th>{{ translate('Reason') }}</th><th>{{ translate('When') }}</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_',' ', $log->action_taken)) }}</span></td>
                            <td><small>{{ $log->reason }}</small></td>
                            <td class="text-muted small">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y g:i A') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">{{ translate('No actions logged yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $logs->withQueryString()->links() }}</div>
        @endif
    </div>
@endsection
