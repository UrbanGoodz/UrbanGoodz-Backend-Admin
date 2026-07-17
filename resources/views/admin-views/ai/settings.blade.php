@extends('layouts.admin.app')

@section('title', translate('AI Settings'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="page-header-title">{{ translate('AI Engine Settings') }}</h1>
        <a href="{{ route('admin.dashboard') }}" class="btn btn--primary">
            <i class="tio-arrow-left"></i> {{ translate('Back to Dashboard') }}
        </a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ translate('OpenAI Configuration') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <td><strong>{{ translate('API Key') }}</strong></td>
                            <td>{{ $settings['openai_api_key'] }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ translate('Organization') }}</strong></td>
                            <td>{{ $settings['openai_organization'] }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ translate('Timeout') }}</strong></td>
                            <td>{{ $settings['openai_timeout'] }}s</td>
                        </tr>
                        <tr>
                            <td><strong>{{ translate('Active Engine') }}</strong></td>
                            <td><span class="badge badge--primary">{{ strtoupper($settings['active_engine']) }}</span></td>
                        </tr>
                    </table>
                    <div class="alert alert--info mt-3">
                        <i class="tio-info-circle"></i>
                        {{ translate('API keys are configured via .env file. Update OPENAI_API_KEY and run php artisan config:clear to apply changes.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ translate('Available Engines') }}</h5>
                </div>
                <div class="card-body">
                    @foreach($settings['engines'] as $slug => $engine)
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                        <div>
                            <strong>{{ $engine['name'] }}</strong>
                            @if($slug === $settings['active_engine'])
                                <span class="badge badge--success ml-2">{{ translate('Active') }}</span>
                            @endif
                        </div>
                        <div>
                            @if($engine['available'])
                                <span class="badge badge--success">{{ translate('Available') }}</span>
                            @else
                                <span class="badge badge--warning">{{ translate('Not Configured') }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ translate('AI Features') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($settings['features'] as $slug => $feature)
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0">{{ translate(ucwords(str_replace('_', ' ', $slug))) }}</h6>
                                    @if($feature['enabled'])
                                        <span class="badge badge--success">{{ translate('Enabled') }}</span>
                                    @else
                                        <span class="badge badge--secondary">{{ translate('Disabled') }}</span>
                                    @endif
                                </div>
                                <p class="text-muted small mb-0">{{ $feature['description'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ translate('AI Copilot Quick Links') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary w-100">
                                <i class="tio-bot"></i> {{ translate('AI Copilot Dashboard') }}
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="{{ route('admin.urban-goodz.ai-copilot.settings') }}" class="btn btn-outline--primary w-100">
                                <i class="tio-settings-outlined"></i> {{ translate('Copilot Settings') }}
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="{{ route('admin.urban-goodz.ai-copilot.load-board-analytics') }}" class="btn btn-outline--primary w-100">
                                <i class="tio-truck"></i> {{ translate('Load Board Analytics') }}
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="{{ route('admin.urban-goodz.ai-copilot.generate') }}" class="btn btn-outline--success w-100" onclick="event.preventDefault(); document.getElementById('generate-form').submit();">
                                <i class="tio-play"></i> {{ translate('Generate Recommendations Now') }}
                            </a>
                            <form id="generate-form" action="{{ route('admin.urban-goodz.ai-copilot.generate') }}" method="GET" class="d-none"></form>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="{{ route('admin.urban-goodz.ai-concierge.intents') }}" class="btn btn-outline--primary w-100">
                                <i class="tio-chat"></i> {{ translate('AI Concierge Intents') }}
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="{{ route('admin.urban-goodz.ai-concierge.conversations') }}" class="btn btn-outline--primary w-100">
                                <i class="tio-comment"></i> {{ translate('Concierge Conversations') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
