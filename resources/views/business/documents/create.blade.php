@extends('business.layouts.app')

@section('title', translate('Upload Document'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ translate('Upload Document') }}</h1>
        <a href="{{ route('business.documents.index') }}" class="btn btn-secondary">{{ translate('Back to Documents') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.documents.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="document_name">{{ translate('Document Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="document_name" id="document_name" required value="{{ old('document_name') }}" placeholder="{{ translate('e.g. Certificate of Insurance') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="document_type">{{ translate('Document Type') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="document_type" id="document_type" required>
                                <option value="">{{ translate('Select type') }}</option>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" {{ old('document_type') === $type ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="input-label" for="file">{{ translate('File') }} <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="file" id="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt,.csv">
                                <label class="custom-file-label" for="file">{{ translate('Choose file') }}</label>
                            </div>
                            <small class="text-muted" style="color: #6c757d !important;">
                                {{ translate('Accepted: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, TXT, CSV. Max 25MB.') }}
                            </small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="expires_at">{{ translate('Expiration Date') }}</label>
                            <input type="date" class="form-control" name="expires_at" id="expires_at" value="{{ old('expires_at') }}">
                            <small class="text-muted" style="color: #6c757d !important;">{{ translate('Leave blank if no expiration') }}</small>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="input-label" for="notes">{{ translate('Notes') }}</label>
                            <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="{{ translate('Optional notes about this document') }}">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3 gap-2">
                    <a href="{{ route('business.documents.index') }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary">{{ translate('Upload Document') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
