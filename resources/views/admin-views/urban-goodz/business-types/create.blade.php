@extends('layouts.admin.app')

@section('title', translate('Add Business Type'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Add Business Type') }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.business-types.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.business-types.store') }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Slug') }} <span class="text-danger">*</span></label>
                                <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" required placeholder="e.g. restaurant">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Icon') }}</label>
                                <input type="text" name="icon" class="form-control" value="{{ old('icon') }}" placeholder="tio-icon-name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Sort Order') }}</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0" max="999">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="is_active" class="toggle-switch-input" value="1" checked>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Active') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
