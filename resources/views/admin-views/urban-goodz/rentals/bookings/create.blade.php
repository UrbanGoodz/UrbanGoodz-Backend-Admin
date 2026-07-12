@extends('layouts.admin.app')

@section('title', translate('Create Rental Booking'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Create Rental Booking') }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.bookings.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.urban-goodz.rentals.bookings.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Rental Asset') }} *</label>
                                <select name="rental_asset_id" class="form-control" required>
                                    <option value="">{{ translate('Select Asset') }}</option>
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}" {{ old('rental_asset_id') == $asset->id ? 'selected' : '' }}>
                                            {{ $asset->title }} ({{ $asset->asset_type }}) - ${{ number_format($asset->daily_rate ?? 0, 2) }}/day
                                        </option>
                                    @endforeach
                                </select>
                                @error('rental_asset_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Customer Name') }} *</label>
                                <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}" required>
                                @error('customer_name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Customer Phone') }}</label>
                                <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Customer ID') }}</label>
                                <input type="number" name="customer_id" class="form-control" value="{{ old('customer_id') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Start Date') }} *</label>
                                <input type="datetime-local" name="start_at" class="form-control" value="{{ old('start_at') }}" required>
                                @error('start_at') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('End Date') }} *</label>
                                <input type="datetime-local" name="end_at" class="form-control" value="{{ old('end_at') }}" required>
                                @error('end_at') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Total Amount') }} *</label>
                                <input type="number" step="0.01" name="total_amount" class="form-control" value="{{ old('total_amount') }}" required>
                                @error('total_amount') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Deposit Amount') }}</label>
                                <input type="number" step="0.01" name="deposit_amount" class="form-control" value="{{ old('deposit_amount') }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="text-dark">{{ translate('Customer Notes') }}</label>
                                <textarea name="customer_notes" class="form-control" rows="3">{{ old('customer_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn--primary mt-3">{{ translate('Create Booking') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
