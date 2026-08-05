@extends('layouts.admin.app')

@section('title', 'AI Chief of Staff')

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header pb-2 mb-4">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title d-flex align-items-center">
                    <i class="tio-robot nav-icon mr-2" style="color: #ED9914; font-size: 2rem;"></i>
                    AI Chief of Staff Operations
                </h1>
                <p class="mb-0 text-muted">Autonomous executive intelligence, live operational telemetry, and ecosystem monitoring</p>
            </div>
            <div class="col-sm-auto">
                <span class="badge badge-soft-success p-2" style="font-size: 0.9rem;">
                    <i class="tio-checkmark-circle mr-1"></i> System Operational (99.8% Uptime)
                </span>
            </div>
        </div>
    </div>

    <!-- Telemetry Cards -->
    <div class="row gx-2 gx-3 mb-4">
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center shadow-sm border-0" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white;">
                <span class="text-uppercase font-size-sm font-weight-bold" style="color: #ED9914;">Active Fleet Orders</span>
                <span class="display-4 font-weight-bold my-2">{{ $telemetry['active_orders'] }}</span>
                <span class="font-size-sm text-success"><i class="tio-trending-up"></i> Live Dispatching</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center shadow-sm border-0" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white;">
                <span class="text-uppercase font-size-sm font-weight-bold" style="color: #ED9914;">Dispatch Latency</span>
                <span class="display-4 font-weight-bold my-2">{{ $telemetry['dispatch_latency_sec'] }}s</span>
                <span class="font-size-sm text-success"><i class="tio-bolt"></i> Ultra Fast</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center shadow-sm border-0" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white;">
                <span class="text-uppercase font-size-sm font-weight-bold" style="color: #ED9914;">Fashion Fit Sizing</span>
                <span class="display-4 font-weight-bold my-2">{{ $telemetry['fashion_fit_analyses_today'] }}</span>
                <span class="font-size-sm text-success"><i class="tio-checkmark-circle-outlined"></i> 100% Instant Resolution</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center shadow-sm border-0" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white;">
                <span class="text-uppercase font-size-sm font-weight-bold" style="color: #ED9914;">AI Copilot Queries</span>
                <span class="display-4 font-weight-bold my-2">{{ $telemetry['ai_queries_processed'] }}</span>
                <span class="font-size-sm text-info"><i class="tio-cpu"></i> Autonomous Response</span>
            </div>
        </div>
    </div>

    <!-- AI Chief of Staff Interactive Chat Window -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title text-white mb-0 d-flex align-items-center">
                        <i class="tio-chat-outlined mr-2" style="color: #ED9914;"></i> Executive AI Assistant Interface
                    </h5>
                    <span class="badge badge-pill badge-primary">Online</span>
                </div>
                <div class="card-body" id="chatContainer" style="height: 380px; overflow-y: auto; background-color: #f8fafc;">
                    <div class="d-flex mb-3">
                        <div class="avatar avatar-sm avatar-circle mr-2">
                            <span class="avatar-initials bg-warning text-dark font-weight-bold">AI</span>
                        </div>
                        <div class="bg-white p-3 rounded shadow-sm border" style="max-width: 80%;">
                            <strong style="color: #ED9914;">AI Chief of Staff</strong>
                            <p class="mb-0 mt-1">Hello Owner. I am monitoring all operations across Shopper, Vendor, Driver apps, and Fashion Fit AI photo processing. How may I assist you with executive insights today?</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top">
                    <form id="aiChatForm" class="d-flex">
                        @csrf
                        <input type="text" id="aiPrompt" class="form-control mr-2" placeholder="Ask AI Chief of Staff (e.g. status of orders, mobile release updates, fashion fit analysis)..." required>
                        <button type="submit" class="btn btn-primary" style="background-color: #ED9914; border-color: #ED9914;">
                            <i class="tio-send mr-1"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title text-white mb-0">System Directives</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>In-App Auto Update System</span>
                            <span class="badge badge-success">ACTIVE</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Fashion Fit AI Instant Engine</span>
                            <span class="badge badge-success">SYNC ACTIVE</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Driver Load Board Auto Dispatch</span>
                            <span class="badge badge-success">RUNNING</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Order Anywhere Engine</span>
                            <span class="badge badge-success">PRODUCTION</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('aiChatForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const promptInput = document.getElementById('aiPrompt');
    const prompt = promptInput.value.trim();
    if (!prompt) return;

    const chatContainer = document.getElementById('chatContainer');
    
    // Append User Message
    const userMessageHtml = `
        <div class="d-flex justify-content-end mb-3">
            <div class="bg-primary text-white p-3 rounded shadow-sm" style="max-width: 80%; background-color: #ED9914 !important;">
                <strong>You</strong>
                <p class="mb-0 mt-1">${prompt}</p>
            </div>
        </div>
    `;
    chatContainer.insertAdjacentHTML('beforeend', userMessageHtml);
    promptInput.value = '';
    chatContainer.scrollTop = chatContainer.scrollHeight;

    // Send Request to Backend AI Chief of Staff Controller
    fetch("{{ route('admin.urban-goodz.ai-chief-of-staff.query') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify({ prompt: prompt })
    })
    .then(res => res.json())
    .then(data => {
        const replyHtml = `
            <div class="d-flex mb-3">
                <div class="avatar avatar-sm avatar-circle mr-2">
                    <span class="avatar-initials bg-warning text-dark font-weight-bold">AI</span>
                </div>
                <div class="bg-white p-3 rounded shadow-sm border" style="max-width: 80%;">
                    <strong style="color: #ED9914;">AI Chief of Staff</strong>
                    <p class="mb-0 mt-1">${data.reply}</p>
                </div>
            </div>
        `;
        chatContainer.insertAdjacentHTML('beforeend', replyHtml);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    })
    .catch(err => {
        console.error(err);
    });
});
</script>
@endsection
