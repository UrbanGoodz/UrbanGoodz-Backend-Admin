@extends('layouts.admin.app')

@section('title', translate('Sourced Business Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title">{{ $business->name }}</h1>
            <div>
                <a href="{{ route('urban-goodz.sourced-businesses.index', ['batch' => $business->created_by_source]) }}" class="btn btn--secondary">{{ translate('Back to queue') }}</a>
            </div>
        </div>

        <div class="alert alert--info mb-3">
            {{ translate('Review-only record. partnered_status stays false; visibility stays private; no store, vendor, product, or activation is performed here.') }}
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">{{ translate('Details') }}</div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">{{ translate('Business') }}</dt><dd class="col-sm-8">{{ $business->name }}</dd>
                            <dt class="col-sm-4">{{ translate('Module') }}</dt><dd class="col-sm-8">{{ $business->module_name }} (id {{ $business->module_id }})</dd>
                            <dt class="col-sm-4">{{ translate('City/State') }}</dt><dd class="col-sm-8">{{ $business->city }}, {{ $business->state }}</dd>
                            <dt class="col-sm-4">{{ translate('Zone') }}</dt><dd class="col-sm-8">{{ $business->zone_name }} (id {{ $business->zone_id }})</dd>
                            <dt class="col-sm-4">{{ translate('Source URL') }}</dt><dd class="col-sm-8">{{ $business->source_urls[0] ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ translate('Confidence') }}</dt><dd class="col-sm-8">{{ $business->data_confidence_score }}</dd>
                            <dt class="col-sm-4">{{ translate('Onboarding') }}</dt><dd class="col-sm-8">{{ $business->onboarding_status }}</dd>
                            <dt class="col-sm-4">{{ translate('Source status') }}</dt><dd class="col-sm-8">{{ $business->source_status }}</dd>
                            <dt class="col-sm-4">{{ translate('Fulfillment') }}</dt><dd class="col-sm-8">{{ implode(', ', (array) $business->fulfillment_modes) }}</dd>
                            <dt class="col-sm-4">{{ translate('Age-restricted') }}</dt><dd class="col-sm-8">{{ (array) $business->fulfillment_modes === ['review_only'] ? translate('Yes (review_only)') : translate('No') }}</dd>
                            <dt class="col-sm-4">{{ translate('Category IDs') }}</dt><dd class="col-sm-8">{{ empty($business->category_ids) ? translate('Pending') : implode(', ', (array) $business->category_ids) }}</dd>
                            <dt class="col-sm-4">{{ translate('Batch marker') }}</dt><dd class="col-sm-8">{{ $business->created_by_source }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ translate('Review') }}</div>
                    <div class="card-body">
                        <form method="post" action="{{ route('urban-goodz.sourced-businesses.update', $business->id) }}">
                            @csrf
                            @method('put')
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Review status') }}</label>
                                <select name="admin_review_status" class="form-select">
                                    @foreach(['pending','approved','rejected','merge_required'] as $s)
                                        <option value="{{ $s }}" @selected($business->admin_review_status == $s)>{{ translate(ucfirst(str_replace('_', ' ', $s))) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Category IDs (module-correct only)') }}</label>
                                <select name="category_ids[]" class="form-select" multiple size="8">
                                    @foreach($categories as $c)
                                        <option value="{{ $c->id }}" @selected(in_array($c->id, (array) $business->category_ids))>{{ $c->name }} ({{ $c->id }})</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ translate('Only category ids that exist under this module are accepted. The Demo category id (1) is never allowed.') }}</small>
                            </div>
                            <button type="submit" class="btn btn--primary">{{ translate('Save review') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
