@extends('layouts.admin.app')

@section('title', translate('Edit Spotlight Business'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Spotlight Business') }}: {{ $business->business_name }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.spotlight-businesses.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.spotlight-businesses.update', $business->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Business Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $business->business_name) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Vendor ID') }}</label>
                                <input type="number" name="vendor_id" class="form-control" value="{{ old('vendor_id', $business->vendor_id) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Category') }}</label>
                                <input type="text" name="category" class="form-control" value="{{ old('category', $business->category) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Image URL') }}</label>
                                <input type="url" name="image_url" class="form-control" value="{{ old('image_url', $business->image_url) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Featured Until') }}</label>
                                <input type="datetime-local" name="featured_until" class="form-control" value="{{ old('featured_until', $business->featured_until?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $business->description) }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="is_featured" class="toggle-switch-input" value="1" {{ old('is_featured', $business->is_featured) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Featured') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="is_active" class="toggle-switch-input" value="1" {{ old('is_active', $business->is_active) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Active') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
