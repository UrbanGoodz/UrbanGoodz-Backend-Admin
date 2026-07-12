@extends('layouts.admin.app')

@section('title', translate('Edit Appointment'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Appointment') }} #{{ $appointment->id }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.appointments.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.appointments.update', $appointment->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Service Request') }}</label>
                                <select name="service_request_id" class="form-control">
                                    <option value="">{{ translate('Select Service Request') }}</option>
                                    @foreach($serviceRequests as $sr)
                                        <option value="{{ $sr->id }}" {{ old('service_request_id', $appointment->service_request_id) == $sr->id ? 'selected' : '' }}>
                                            #{{ $sr->id }} - {{ $sr->customer_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Service Provider') }}</label>
                                <select name="service_provider_id" class="form-control">
                                    <option value="">{{ translate('Select Service Provider') }}</option>
                                    @foreach($serviceProviders as $sp)
                                        <option value="{{ $sp->id }}" {{ old('service_provider_id', $appointment->service_provider_id) == $sp->id ? 'selected' : '' }}>
                                            {{ $sp->business_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Scheduled At') }} <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at', $appointment->scheduled_at?->format('Y-m-d\TH:i')) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Completed At') }}</label>
                                <input type="datetime-local" name="completed_at" class="form-control" value="{{ old('completed_at', $appointment->completed_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Status') }} <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    @foreach(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'] as $s)
                                        <option value="{{ $s }}" {{ old('status', $appointment->status) === $s ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Notes') }}</label>
                                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $appointment->notes) }}</textarea>
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
