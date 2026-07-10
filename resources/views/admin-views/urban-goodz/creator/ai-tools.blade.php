@extends('layouts.admin.app')

@section('title', translate('AI Creator Tools'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('AI Creator Tools') }}</h1></div>

        <div class="alert alert-info">
            <i class="tio-info"></i>
            {{ translate('AI tools help creators generate captions, hashtags, scripts, and more. Configure your OpenAI API key in Admin > Business Settings > OpenAI Config for full AI-powered responses. Without a key, local fallback templates are used.') }}
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Generate Caption') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Describe your content') }}</label>
                            <textarea id="ai_caption_input" class="form-control" rows="3" placeholder="{{ translate('e.g. A photoshoot at a local Urban Goodz vendor featuring their new product line') }}"></textarea>
                        </div>
                        <button onclick="generateAI('caption')" class="btn btn-primary btn-block">{{ translate('Generate Caption') }}</button>
                        <div id="ai_caption_output" class="mt-3 p-3 bg-light rounded d-none"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Generate Hashtags') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Topic or keyword') }}</label>
                            <input type="text" id="ai_hashtags_input" class="form-control" placeholder="{{ translate('e.g. Urban Goodz local boutique') }}">
                        </div>
                        <button onclick="generateAI('hashtags')" class="btn btn-primary btn-block">{{ translate('Generate Hashtags') }}</button>
                        <div id="ai_hashtags_output" class="mt-3 p-3 bg-light rounded d-none"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Create Script') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Describe the video') }}</label>
                            <textarea id="ai_script_input" class="form-control" rows="3" placeholder="{{ translate('e.g. A 30-second reel showcasing a rental car from Urban Goodz Rentals') }}"></textarea>
                        </div>
                        <button onclick="generateAI('script')" class="btn btn-primary btn-block">{{ translate('Generate Script') }}</button>
                        <div id="ai_script_output" class="mt-3 p-3 bg-light rounded d-none"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Suggest Concept') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Business or product type') }}</label>
                            <input type="text" id="ai_concept_input" class="form-control" placeholder="{{ translate('e.g. Local restaurant on Urban Goodz Order Anywhere') }}">
                        </div>
                        <button onclick="generateAI('concept')" class="btn btn-primary btn-block">{{ translate('Suggest Concept') }}</button>
                        <div id="ai_concept_output" class="mt-3 p-3 bg-light rounded d-none"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Draft Campaign Response') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Campaign or brand details') }}</label>
                            <textarea id="ai_draft_response_input" class="form-control" rows="3" placeholder="{{ translate('e.g. Vendor reached out about a food review campaign for their new menu items') }}"></textarea>
                        </div>
                        <button onclick="generateAI('draft_response')" class="btn btn-primary btn-block">{{ translate('Draft Response') }}</button>
                        <div id="ai_draft_response_output" class="mt-3 p-3 bg-light rounded d-none"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Content Trends') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Market or niche') }}</label>
                            <input type="text" id="ai_trends_input" class="form-control" placeholder="{{ translate('e.g. Urban Goodz local creators, fashion, food') }}">
                        </div>
                        <button onclick="generateAI('trends')" class="btn btn-primary btn-block">{{ translate('Get Trends') }}</button>
                        <div id="ai_trends_output" class="mt-3 p-3 bg-light rounded d-none"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
<script>
async function generateAI(action) {
    const input = document.getElementById('ai_' + action + '_input')?.value;
    const output = document.getElementById('ai_' + action + '_output');

    if (!input) { alert('{{ translate('Please enter input') }}'); return; }

    output.classList.remove('d-none');
    output.innerHTML = '<div class="spinner-border spinner-border-sm"></div> {{ translate('Generating...') }}';

    try {
        const res = await fetch('{{ route('admin.urban-goodz.creator.ai-tools.generate') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ action, input })
        });
        const data = await res.json();
        if (data.success) {
            output.innerHTML = '<pre class="mb-0 text-wrap">' + escapeHtml(data.output) + '</pre>';
            if (data.source === 'local') {
                output.innerHTML += '<small class="text-muted mt-1 d-block"><i class="tio-info"></i> {{ translate('Using local fallback. Configure OpenAI for AI-powered results.') }}</small>';
            }
        } else {
            output.innerHTML = '<div class="text-danger">' + (data.error || '{{ translate('Generation failed') }}') + '</div>';
        }
    } catch(e) {
        output.innerHTML = '<div class="text-danger">{{ translate('Request failed') }}</div>';
    }
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
@endpush
