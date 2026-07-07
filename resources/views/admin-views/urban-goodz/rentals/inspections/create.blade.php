@extends('layouts.admin.app')

@section('title', translate('Add Inspection'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Add Rental Inspection') }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.inspections.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.rentals.inspections.store') }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Booking') }} <span class="text-danger">*</span></label>
                                <select name="rental_booking_id" class="form-control" required>
                                    <option value="">{{ translate('Select Booking') }}</option>
                                    @foreach($activeBookings as $ab)
                                        <option value="{{ $ab->id }}" {{ old('rental_booking_id', $booking->id ?? '') == $ab->id ? 'selected' : '' }}>
                                            #{{ $ab->id }} - {{ $ab->customer_name ?? 'N/A' }} ({{ $ab->asset->title ?? '' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Inspection Type') }} <span class="text-danger">*</span></label>
                                <select name="inspection_type" class="form-control" required>
                                    <option value="pre_pickup" {{ old('inspection_type') === 'pre_pickup' ? 'selected' : '' }}>Pre-Pickup</option>
                                    <option value="post_return" {{ old('inspection_type') === 'post_return' ? 'selected' : '' }}>Post-Return</option>
                                    <option value="damage" {{ old('inspection_type') === 'damage' ? 'selected' : '' }}>Damage Report</option>
                                    <option value="routine" {{ old('inspection_type') === 'routine' ? 'selected' : '' }}>Routine</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Inspected By') }}</label>
                                <input type="text" name="inspected_by" class="form-control" value="{{ old('inspected_by', auth('admin')->user()->name ?? '') }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Notes') }}</label>
                                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="damage_found" class="toggle-switch-input" value="1" {{ old('damage_found') ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Damage Found') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Damage Amount ($)') }}</label>
                                <input type="number" name="damage_amount" class="form-control" value="{{ old('damage_amount') }}" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Save Inspection') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
