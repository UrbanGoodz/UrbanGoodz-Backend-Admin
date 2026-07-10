@extends('business.layouts.app')

@section('title', translate('Documents'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ translate('Documents') }}</h1>
        <a href="{{ route('business.documents.create') }}" class="btn btn--primary">{{ translate('Upload Document') }}</a>
    </div>

    @if($documents->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No documents uploaded yet') }}</h5>
            <p class="text-muted mb-0" style="color: #6c757d !important; max-width: 450px; margin: 0 auto;">
                {{ translate('Upload contracts, insurance certificates, permits, and other business documents. Your account manager can help upload or you may upload documents from the admin panel.') }}
            </p>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Document Name') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Expires') }}</th>
                            <th>{{ translate('Uploaded') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                        <tr>
                            <td>{{ $document->document_name ?? '-' }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $document->document_type ?? '-')) }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $document->status === 'active' || $document->status === 'approved' ? 'success' : ($document->status === 'rejected' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($document->status) }}
                                </span>
                            </td>
                            <td>{{ $document->expires_at?->format('M d, Y') ?? '-' }}</td>
                            <td>{{ $document->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('business.documents.download', $document->id) }}" class="btn btn-sm btn-outline-primary" title="{{ translate('Download') }}">
                                        <i class="tio-download"></i>
                                    </a>
                                    <form action="{{ route('business.documents.delete', $document->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Are you sure you want to delete this document?') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Delete') }}">
                                            <i class="tio-delete"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                {{ translate('No documents found.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($documents->hasPages())
        <div class="card-footer">
            {{ $documents->links() }}
        </div>
        @endif
    </div>
    @endif
@endsection
