@extends('layouts.admin.app')

@section('title', translate('Create Event'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-calendar-note"></i></span>
                <span>{{ translate('Create Event') }}</span>
            </h1>
            @if (Route::has('admin.urban-goodz.events.index'))
                <a href="{{ route('admin.urban-goodz.events.index') }}" class="btn btn-secondary btn-sm">
                    {{ translate('Back to Events') }}
                </a>
            @endif
        </div>

        <div class="card">
            <div class="card-body">
                {{-- Fields mirror the store() validator exactly: title, description,
                     starts_at, ends_at (must be after starts_at) and source. The
                     organiser is taken from the signed-in admin, not the form. --}}
                <form id="event-create-form" action="{{ route('admin.urban-goodz.events.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="title">{{ translate('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control"
                                   value="{{ old('title') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="source">{{ translate('Source') }} <span class="text-danger">*</span></label>
                            <input type="text" id="source" name="source" class="form-control"
                                   value="{{ old('source', 'admin') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">{{ translate('Description') }} <span class="text-danger">*</span></label>
                            <textarea id="description" name="description" rows="4" class="form-control" required>{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="starts_at">{{ translate('Starts At') }} <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="starts_at" name="starts_at" class="form-control"
                                   value="{{ old('starts_at') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ends_at">{{ translate('Ends At') }} <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="ends_at" name="ends_at" class="form-control"
                                   value="{{ old('ends_at') }}" required>
                            <small class="form-text text-muted">{{ translate('Must be after the start time.') }}</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn--primary">{{ translate('Create Event') }}</button>
                    </div>
                </form>

                <div id="event-create-result" class="mt-3"></div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        // store() answers with JSON (201 + the created event), not a redirect,
        // so submit over AJAX and report the outcome in place.
        $('#event-create-form').on('submit', function (e) {
            e.preventDefault();
            let $form = $(this);
            let $out = $('#event-create-result');

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                success: function (data) {
                    $out.html('<div class="alert alert-success mb-0">' +
                        (data.message || '{{ translate('Event created') }}') + '</div>');
                    $form[0].reset();
                },
                error: function (xhr) {
                    let msg = '{{ translate('Could not create the event.') }}';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    }
                    $out.html('<div class="alert alert-danger mb-0">' + msg + '</div>');
                }
            });
        });
    </script>
@endpush
