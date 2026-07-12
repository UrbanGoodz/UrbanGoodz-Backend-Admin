@extends('layouts.admin.app')

@section('title', translate('AI Concierge - Conversation #') . $conversation->id)

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        {{ translate('AI Concierge - Conversation #') . $conversation->id }}
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.ai-concierge.conversations') }}" class="btn btn--secondary">{{ translate('Back to list') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Conversation') }}</h5>
                        <span class="badge badge-soft-{{ $conversation->status === 'resolved' ? 'success' : ($conversation->status === 'escalated' ? 'danger' : 'info') }}">
                            {{ $conversation->status }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="font-weight-bold">{{ translate('User') }}</label>
                            <p>{{ $conversation->customer_id ?? translate('Guest') }}</p>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold">{{ translate('Detected Intent') }}</label>
                            <p>{{ $conversation->detectedIntent->name ?? translate('Unknown') }} <code>{{ $conversation->detectedIntent?->slug ?? '' }}</code></p>
                        </div>

                        @if($conversation->query_text || $conversation->response_text)
                            <hr>
                            <h6>{{ translate('Conversation') }}</h6>
                            <div class="border rounded p-3 bg-light" style="max-height:400px;overflow-y:auto">
                                @if($conversation->query_text)
                                    <div class="mb-2 p-2 rounded bg-primary text-white mr-4">
                                        <small class="text-muted">{{ translate('User') }}</small>
                                        <p class="mb-0">{{ $conversation->query_text }}</p>
                                    </div>
                                @endif
                                @if($conversation->response_text)
                                    <div class="mb-2 p-2 rounded bg-white ml-4">
                                        <small class="text-muted">{{ translate('AI') }}</small>
                                        <p class="mb-0">{{ $conversation->response_text }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Admin Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.ai-concierge.conversations.update', $conversation->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="pending" {{ $conversation->status === 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                                    <option value="resolved" {{ $conversation->status === 'resolved' ? 'selected' : '' }}>{{ translate('Resolved') }}</option>
                                    <option value="escalated" {{ $conversation->status === 'escalated' ? 'selected' : '' }}>{{ translate('Escalated') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="4">{{ $conversation->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Update') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
