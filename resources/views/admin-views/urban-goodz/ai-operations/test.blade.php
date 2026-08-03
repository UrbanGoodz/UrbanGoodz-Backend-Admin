@extends('layouts.admin.app')

@section('title', translate('Test AI Endpoint'))

@push('css_or_js')
<style>
    .ai-response-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 16px;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-family: inherit;
        line-height: 1.6;
    }
    .ai-meta { font-size: 0.85rem; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to AI Operations') }}
            </a>
            <h1 class="page-header-title">{{ translate('Test AI Endpoint') }}</h1>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Send Test Query') }}</h5>
                <small class="text-muted">{{ translate('This will send a query through the UrbanGoodzAIConciergeService. No API keys are exposed.') }}</small>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.ai-operations.test.run') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="query" class="form-label fw-bold">{{ translate('Query') }}</label>
                        <textarea
                            name="query"
                            id="query"
                            class="form-control"
                            rows="4"
                            placeholder="{{ translate('Enter a test query, e.g.: Where is my order? / I need a refund / How do I become a driver?') }}"
                            required
                            maxlength="2000"
                        >{{ $lastQuery ?? old('query') }}</textarea>
                        <small class="text-muted">{{ translate('Max 2000 characters') }}</small>
                        @error('query')
                        <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn--primary" onclick="this.disabled=true; this.innerHTML='<i class=\'tio-refresh tio-spin\'></i> {{ translate('Processing...') }}'; this.form.submit();">
                        <i class="tio-send"></i> {{ translate('Send Test Query') }}
                    </button>
                </form>
            </div>
        </div>

        @if($result)
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ translate('Response') }}</h5>
                @if($result['success'])
                <span class="badge badge-soft-success">{{ translate('Success') }}</span>
                @else
                <span class="badge badge-soft-danger">{{ translate('Error') }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($result['success'])
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Detected Intent') }}</small>
                        <strong>{{ $result['intent'] }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Confidence') }}</small>
                        @if($result['confidence'] !== null)
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1" style="max-width: 80px; height: 6px; background: #dee2e6; border-radius: 3px;">
                                <div style="width: {{ $result['confidence'] }}%; height: 100%; background: {{ $result['confidence'] >= 70 ? '#28a745' : ($result['confidence'] >= 40 ? '#ffc107' : '#dc3545') }}; border-radius: 3px;"></div>
                            </div>
                            <strong>{{ number_format($result['confidence'], 1) }}%</strong>
                        </div>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Status') }}</small>
                        <span class="badge badge-soft-{{ $result['status'] === 'resolved' ? 'success' : 'warning' }}">{{ ucfirst($result['status'] ?? 'unknown') }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">{{ translate('Response Time') }}</small>
                        <strong>{{ $result['elapsed_ms'] }}ms</strong>
                    </div>
                </div>
                @endif

                <div class="mb-0">
                    <small class="text-muted d-block mb-1">{{ translate('AI Response') }}</small>
                    <div class="ai-response-box">{{ $result['response'] }}</div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Suggested Test Queries') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @php
                        $suggestions = [
                            'Where is my order?',
                            'I need a refund for my last order',
                            'How do I become a delivery driver?',
                            'What is Order Anywhere?',
                            'Track my package',
                            'How do I schedule a medical courier?',
                            'What are the available loads near Houston?',
                            'I want to book a service',
                            'How do I list my business on the marketplace?',
                            'Tell me about the Fashion Fit feature',
                        ];
                    @endphp
                    @foreach($suggestions as $suggestion)
                    <button type="button" class="btn btn-outline-secondary btn-sm suggestion-btn" onclick="document.getElementById('query').value='{{ $suggestion }}'">
                        {{ $suggestion }}
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
