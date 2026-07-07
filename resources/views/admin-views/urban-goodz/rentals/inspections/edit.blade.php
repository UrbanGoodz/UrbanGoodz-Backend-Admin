@extends('layouts.admin.app')

@section('title', translate('Edit Inspection'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Inspection') }} #{{ $inspection->id }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.inspections.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.rentals.inspections.update', $inspection->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Inspection Type') }}</label>
                                <input type="text" class="form-control" value="{{ ucfirst($inspection->inspection_type) }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Booking') }}</label>
                                <input type="text" class="form-control" value="#{{ $inspection->booking_id }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Inspected By') }}</label>
                                <input type="text" name="inspected_by" class="form-control" value="{{ old('inspected_by', $inspection->inspected_by) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="pending" {{ $inspection->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="completed" {{ $inspection->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Notes') }}</label>
                                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $inspection->notes) }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="damage_found" class="toggle-switch-input" value="1" {{ old('damage_found', $inspection->damage_found) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Damage Found') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Damage Amount ($)') }}</label>
                                <input type="number" name="damage_amount" class="form-control" value="{{ old('damage_amount', $inspection->damage_amount) }}" step="0.01" min="0">
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
