@extends('layouts.admin.app')

@section('title', translate('messages.settings'))

@section('3rd_party')
    active
@endsection
@section('openAI')
    active
@endsection

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-robot"></i>
                </span>
                <span>{{ translate('OpenAI_Configuration') }}
                </span>
            </h1>
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-5 mt-4 __gap-12px">
                <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                    <!-- Nav -->
                    <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
                        <li class="nav-item">
                            <a class="nav-link   {{ Request::is('admin/business-settings/open-ai') ? 'active' : '' }}"
                                href="{{ route('admin.business-settings.openAI') }}"
                                aria-disabled="true">{{ translate('AI Configuration') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('admin/business-settings/open-ai-settings') ? 'active' : '' }}"
                                href="{{ route('admin.business-settings.openAISettings') }}"
                                aria-disabled="true">{{ translate('AI Settings') }}</a>
                        </li>
                    </ul>
                    <!-- End Nav -->
                </div>
            </div>
        </div>
        <!-- End Page Header -->

        <div class="card min-h-60vh">
            <div class="card-header card-header-shadow pb-0">
                <div class="d-flex flex-wrap justify-content-between w-100 row-gap-1">
                    <ul class="nav nav-tabs nav--tabs border-0 gap-2">
                        <li class="nav-item mr-2 mr-md-4">
                            <a href="#mail-config" data-toggle="tab" class="nav-link pb-2 px-0 pb-sm-3 active">
                                <img src="{{ asset('/public/assets/admin/img/mail-config.png') }}" alt="">
                                <span>{{ translate('OpenAI_Configuration') }}</span>
                            </a>
                        </li>

                    </ul>
                    <div class="py-1">
                        <div class="text--primary-2 d-flex flex-wrap align-items-center" type="button" data-toggle="modal"
                            data-target="#works-modal">
                            <strong class="mr-2">{{ translate('How_it_Works') }}</strong>
                            <div class="blinkings">
                                <i class="tio-info-outined"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active " id="mail-config">
                        @php($config = \App\Models\BusinessSetting::where(['key' => 'openai_config'])->first())
                        @php($data = $config ? json_decode($config['value'], true) : null)
                        <form
                            action="{{ getEnvMode() != 'demo' ? route('admin.business-settings.openAIConfigStatus') : 'javascript:' }}"
                            method="get" id="mail-config-disable_form">


                            <div class="form-group text-center d-flex flex-wrap align-items-center">
                                <label
                                    class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control mb-2">
                                    <span class="pr-1 d-flex align-items-center switch--label text--primary">
                                        <span class="line--limit-1">
                                            {{ isset($data['status']) && $data['status'] == 1 ? translate('Turn_OFF') : translate('Turn_ON')  }}
                                        </span>
                                    </span>
                                    <input id="mail-config-disable" type="checkbox" data-id="mail-config-disable"
                                        data-type="status"
                                        data-image-on="{{ asset('/public/assets/admin/img/modal/mail-success.png') }}"
                                        data-image-off="{{ asset('/public/assets/admin/img/modal/mail-warning.png') }}"
                                        data-title-on="{{ translate('Important!') }}"
                                        data-title-off="{{ translate('Warning!') }}"
                                        data-text-on="<p>{{ translate('You_can_user_the_power_of_OpenAI_to_generate_content.') }}</p>"
                                        data-text-off="<p>{{ translate('All_the_AI_services_will_be_turned_off.') }}</p>"
                                        class="status toggle-switch-input dynamic-checkbox" name="status" value="1"
                                        {{ isset($data['status']) && $data['status'] == 1 ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                                {{-- <small>{{translate('*By_Turning_OFF_mail_configuration,_all_your_mailing_services_will_be_off.')}}</small> --}}
                            </div>
                        </form>
                        <form
                            action="{{ getEnvMode() != 'demo' ? route('admin.business-settings.openAIConfigUpdate') : 'javascript:' }}"
                            method="post">
                            @csrf
                            <div
                                class="disable-on-turn-of {{ isset($data) && isset($data['status']) && $data['status'] == 1 ? '' : 'inactive' }}">
                                <input type="hidden" name="status"
                                    value="{{ isset($data) && isset($data['status']) ? $data['status'] : 0 }}">
                                <div class="row g-3">
                                    <div class="col-sm-12">
                                        <div class="form-group mb-0">
                                            <label class="form-label">{{ translate('OpenAI_API_Key') }}</label><br>
                                            @if(!empty($data['OPENAI_API_KEY_HAS_VALUE']))
                                                <div class="mb-2 p-2 bg-light rounded">
                                                    <code class="text-muted">{{ $data['OPENAI_API_KEY_MASKED'] ?? '' }}</code>
                                                    <small class="text-muted ml-2">{{ translate('Leave blank to keep existing key') }}</small>
                                                </div>
                                            @endif
                                            <input type="password"
                                                placeholder="{{ translate('messages.Ex:') }} sk-proj-K0LhsdcbHJ......."
                                                class="form-control" name="OPENAI_API_KEY"
                                                value="{{ getEnvMode() != 'demo' ? '' : '' }}"
                                                autocomplete="off">
                                            <small class="text-muted">{{ translate('Enter a new key to replace the existing one, or leave blank to keep current key.') }}</small>
                                        </div>
                                    </div>

                                    <div class="col-sm-12">
                                        <div class="form-group mb-0">
                                            <label class="form-label">{{ translate('OpenAI_Organization') }}</label><br>
                                            <input type="text"
                                                placeholder="{{ translate('messages.Ex:') }} org-xxxxxxxxxxx"
                                                class="form-control" name="OPENAI_ORGANIZATION"
                                                value="{{ getEnvMode() != 'demo' ? ($data['OPENAI_ORGANIZATION'] ?? '') : '' }}"
                                                required>
                                        </div>
                                    </div>

                                    {{--
                                        The fields above are legacy 6amtech and OpenAI-shaped.
                                        The Urban Goodz AI stack also runs on OpenRouter and
                                        Gemini, and an API key cannot say which provider it
                                        belongs to. Selecting it here is what stops an
                                        OpenRouter key being posted to api.openai.com.
                                    --}}
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="form-label">{{ translate('AI Provider') }}</label><br>
                                            <select class="form-control" name="AI_PROVIDER">
                                                @php($selectedProvider = $data['AI_PROVIDER'] ?? 'openai')
                                                <option value="openai" {{ $selectedProvider === 'openai' ? 'selected' : '' }}>OpenAI</option>
                                                <option value="openrouter" {{ $selectedProvider === 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                                                <option value="gemini" {{ $selectedProvider === 'gemini' ? 'selected' : '' }}>Gemini</option>
                                            </select>
                                            <small class="text-muted">{{ translate('Must match the key above. An OpenRouter key with OpenAI selected will fail every call.') }}</small>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="form-label">{{ translate('AI Model') }}</label><br>
                                            <input type="text"
                                                placeholder="{{ translate('messages.Ex:') }} openai/gpt-4o-mini"
                                                class="form-control" name="AI_MODEL"
                                                value="{{ getEnvMode() != 'demo' ? ($data['AI_MODEL'] ?? '') : '' }}">
                                            <small class="text-muted">{{ translate('Leave blank to use the provider default.') }}</small>
                                        </div>
                                    </div>

                                    <div class="col-sm-12">
                                        <div class="btn--container justify-content-end">
                                            <button type="button" id="test-connection-btn"
                                                class="btn btn--secondary">{{ translate('Test Connection') }}</button>
                                            <button type="reset"
                                                class="btn btn--reset">{{ translate('messages.reset') }}</button>
                                            <button type="{{ getEnvMode() != 'demo' ? 'submit' : 'button' }}"
                                                class="btn btn--primary call-demo">{{ translate('messages.save') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>





    <!-- How it Works Modal -->
    <div class="modal fade" id="works-modal">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="single-item-slider owl-carousel">


                        <div class="item">
                            <div class="mw-353px mb-20 mx-auto">
                                <div class="text-center">
                                    <img src="{{ asset('/public/assets/admin/img/mail-config/slide-4.png') }}"
                                        alt="" class="mb-20">
                                    <h5 class="modal-title">{{ translate('Enable OpenAI Configuration') }}</h5>
                                </div>
                                <ul class="px-3">
                                    <li>
                                        {{ translate('Go to the OpenAI API platform and.') }}
                                        <a href="https://platform.openai.com/docs/overview" target="_blank"
                                            rel="noopener noreferrer">{{ translate('Sign up / Log in') }}</a>
                                    </li>
                                    <li>
                                        {{ translate('Create a new API key and copy the API key.') }}
                                    </li>
                                </ul>
                                <div class="btn-wrap">
                                    <button type="submit" class="btn btn--primary w-100"
                                        data-dismiss="modal">{{ translate('Got It') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="slide-counter"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script_2')
<script>
$(document).ready(function () {
    $('#test-connection-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text('{{ translate('Testing...') }}');

        $.ajax({
            url: '{{ route('admin.business-settings.openAIConfigTest') }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                } else {
                    toastr.error(res.message);
                }
            },
            error: function () {
                toastr.error('{{ translate('Connection test failed') }}');
            },
            complete: function () {
                btn.prop('disabled', false).text('{{ translate('Test Connection') }}');
            }
        });
    });
});
</script>
@endpush
