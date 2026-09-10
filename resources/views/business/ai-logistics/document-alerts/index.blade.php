@extends('business.layouts.app')

@section('title', translate('Document Alerts'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Document Alerts') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Document Alerts') }}</h1>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-danger-soft"><h5 class="mb-0 text-danger">{{ translate('Expired') }} ({{ $expiredDocs->count() }})</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead class="bg-light"><tr><th>{{ translate('Document') }}</th><th>{{ translate('Expired') }}</th></tr></thead>
                <tbody>
                    @forelse($expiredDocs as $doc)
                    <tr><td>{{ $doc->document_type ?? $doc->name ?? ('#'.$doc->id) }}</td><td class="text-danger">{{ $doc->expires_at->format('M d, Y') }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-muted py-4">{{ translate('No expired documents.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-warning-soft"><h5 class="mb-0 text-warning">{{ translate('Expiring Soon') }} ({{ $warningDocs->count() }})</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead class="bg-light"><tr><th>{{ translate('Document') }}</th><th>{{ translate('Expires') }}</th></tr></thead>
                <tbody>
                    @forelse($warningDocs as $doc)
                    <tr><td>{{ $doc->document_type ?? $doc->name ?? ('#'.$doc->id) }}</td><td>{{ $doc->expires_at->format('M d, Y') }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-muted py-4">{{ translate('Nothing expiring in the next 60 days.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection
