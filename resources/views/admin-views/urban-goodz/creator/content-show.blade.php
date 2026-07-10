@extends('layouts.admin.app')

@section('title', translate('Content Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ translate('Content Detail') }}</h1>
            <a href="{{ route('admin.urban-goodz.creator.content') }}" class="btn btn-secondary"><i class="tio-back"></i> {{ translate('Back') }}</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>{{ $item->title }}</h5></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th>{{ translate('Type') }}</th><td>{{ $item->content_type }}</td></tr>
                            <tr><th>{{ translate('Creator') }}</th><td>{{ $item->profile->display_name ?? 'N/A' }} (@{{ $item->profile->handle ?? 'N/A' }})</td></tr>
                            <tr><th>{{ translate('Campaign') }}</th><td>{{ $item->campaign->title ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Status') }}</th><td><span class="badge badge-{{ $item->status == 'published' ? 'success' : ($item->status == 'rejected' ? 'danger' : 'secondary') }}">{{ $item->status }}</span></td></tr>
                            <tr><th>{{ translate('Shoppable') }}</th><td>{!! $item->is_shoppable ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' !!}</td></tr>
                            <tr><th>{{ translate('Featured') }}</th><td>{!! $item->is_featured ? '<span class="badge badge-primary">Yes</span>' : '<span class="badge badge-secondary">No</span>' !!}</td></tr>
                            <tr><th>{{ translate('CTA Label') }}</th><td>{{ $item->cta_label ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('CTA URL') }}</th><td>{{ $item->cta_url ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Linked Vendor') }}</th><td>{{ $item->linked_vendor_name ?? ($item->linked_vendor_type ? $item->linked_vendor_type . ' #' . $item->linked_vendor_id : 'N/A') }}</td></tr>
                            @if($item->description)<tr><th>{{ translate('Description') }}</th><td>{{ $item->description }}</td></tr>@endif
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3"><h5>{{ number_format($item->clicks_count) }}</h5><small>{{ translate('Clicks') }}</small></div>
                            <div class="col-3"><h5>{{ number_format($item->likes_count) }}</h5><small>{{ translate('Likes') }}</small></div>
                            <div class="col-3"><h5>{{ number_format($item->shares_count) }}</h5><small>{{ translate('Shares') }}</small></div>
                            <div class="col-3"><h5>{{ number_format($item->saves_count) }}</h5><small>{{ translate('Saves') }}</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Update Status') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.creator.content.status', $item->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>{{ translate('Status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="draft" {{ $item->status == 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                                    <option value="submitted" {{ $item->status == 'submitted' ? 'selected' : '' }}>{{ translate('Submitted') }}</option>
                                    <option value="approved" {{ $item->status == 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                                    <option value="published" {{ $item->status == 'published' ? 'selected' : '' }}>{{ translate('Published') }}</option>
                                    <option value="rejected" {{ $item->status == 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_featured" class="custom-control-input" id="cs_featured" value="1" {{ $item->is_featured ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="cs_featured">{{ translate('Featured') }}</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_shoppable" class="custom-control-input" id="cs_shoppable" value="1" {{ $item->is_shoppable ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="cs_shoppable">{{ translate('Shoppable') }}</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="2">{{ $item->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">{{ translate('Update') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
