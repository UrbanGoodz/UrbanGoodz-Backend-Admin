@extends('layouts.admin.app')

@section('title', translate('Edit Capability') . ' - ' . $capability->name)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Capability') }}: {{ $capability->name }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.capabilities.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.capabilities.update', $capability->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $capability->name) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Slug') }} <span class="text-danger">*</span></label>
                                <input type="text" name="slug" class="form-control" value="{{ old('slug', $capability->slug) }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Group') }}</label>
                                <input type="text" name="group" class="form-control" value="{{ old('group', $capability->group) }}" list="groupList">
                                <datalist id="groupList">
                                    @foreach($groups as $g)
                                        <option value="{{ $g }}">
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Admin Section Key') }}</label>
                                <input type="text" name="admin_section_key" class="form-control" value="{{ old('admin_section_key', $capability->admin_section_key) }}" list="sectionKeyList">
                                <datalist id="sectionKeyList">
                                    @foreach($sectionKeys as $sk)
                                        <option value="{{ $sk }}">
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Sort Order') }}</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $capability->sort_order) }}" min="0" max="999">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $capability->description) }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="is_core" class="toggle-switch-input" value="1" {{ $capability->is_core ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Core Capability') }}</span>
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
