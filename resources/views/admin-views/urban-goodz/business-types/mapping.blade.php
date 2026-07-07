@extends('layouts.admin.app')

@section('title', translate('Capability Mapping') . ' - ' . $type->name)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        {{ translate('Capability Mapping') }}: {{ $type->name }}
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.business-types.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.business-types.mapping-update', $type->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ translate('Assign Capabilities to') }}: {{ $type->name }}</h5>
                </div>
                <div class="card-body">
                    @php
                        $grouped = $capabilities->groupBy('group');
                    @endphp

                    @foreach($grouped as $group => $items)
                        <div class="mb-4">
                            <h6 class="text-capitalize text-primary">{{ $group ?: 'Ungrouped' }}</h6>
                            <div class="row">
                                @foreach($items as $cap)
                                    @php
                                        $isAssigned = in_array($cap->id, $assignedIds);
                                        $pivot = $type->capabilities->firstWhere('id', $cap->id);
                                        $isRequired = $pivot && $pivot->pivot->is_required;
                                    @endphp
                                    <div class="col-md-4 col-lg-3 mb-2">
                                        <div class="border rounded p-3 {{ $isAssigned ? 'border-primary' : '' }}">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="capabilities[]" value="{{ $cap->id }}"
                                                           class="custom-control-input capability-checkbox"
                                                           id="cap_{{ $cap->id }}"
                                                           {{ $isAssigned ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="cap_{{ $cap->id }}">
                                                        <strong>{{ $cap->name }}</strong>
                                                    </label>
                                                </div>
                                                @if($cap->is_core)
                                                    <span class="badge badge-soft-info badge-sm">core</span>
                                                @endif
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <small class="text-muted"><code>{{ $cap->slug }}</code></small>
                                                <label class="toggle-switch my-0 ml-auto">
                                                    <input type="checkbox" name="is_required[{{ $cap->id }}]" class="toggle-switch-input" value="1"
                                                           data-cap-id="{{ $cap->id }}" {{ $isRequired ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                                    <small class="ml-1">{{ translate('Required') }}</small>
                                                </label>
                                            </div>
                                            @if($cap->description)
                                                <small class="text-muted d-block mt-1">{{ $cap->description }}</small>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Update Mapping') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
