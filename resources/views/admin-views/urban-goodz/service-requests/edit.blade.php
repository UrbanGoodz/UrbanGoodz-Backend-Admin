@extends('layouts.admin.app')

@section('title', translate('Edit Service Request'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Service Request') }} #{{ $serviceRequest->id }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.service-requests.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.service-requests.update', $serviceRequest->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Customer Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name', $serviceRequest->customer_name) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Customer Email') }}</label>
                                <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $serviceRequest->customer_email) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Customer Phone') }}</label>
                                <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $serviceRequest->customer_phone) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Service Type') }} <span class="text-danger">*</span></label>
                                <input type="text" name="service_type" class="form-control" value="{{ old('service_type', $serviceRequest->service_type) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Location') }}</label>
                                <input type="text" name="location" class="form-control" value="{{ old('location', $serviceRequest->location) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Status') }} <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    @foreach(['pending', 'assigned', 'in_progress', 'completed', 'cancelled'] as $s)
                                        <option value="{{ $s }}" {{ old('status', $serviceRequest->status) === $s ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Assigned Vendor ID') }}</label>
                                <input type="number" name="assigned_vendor_id" class="form-control" value="{{ old('assigned_vendor_id', $serviceRequest->assigned_vendor_id) }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $serviceRequest->description) }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="3">{{ old('admin_notes', $serviceRequest->admin_notes) }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Preferred Dates') }} <small class="text-muted">(one per line)</small></label>
                                <textarea name="preferred_dates" class="form-control" rows="3">{{ old('preferred_dates', is_array($serviceRequest->preferred_dates) ? implode("\n", $serviceRequest->preferred_dates) : '') }}</textarea>
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
