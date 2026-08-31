@extends('business.layouts.app')

@section('title', translate('Post Load'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('business.ai-logistics.load-board.index') }}">{{ translate('Load Board') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ translate('Post Load') }}</li>
                </ol>
            </nav>
            <h1 class="page-header-title">{{ translate('Post Load') }}</h1>
        </div>
        <a href="{{ route('business.ai-logistics.load-board.index') }}" class="btn btn-secondary">{{ translate('Back') }}</a>
    </div>

    <form method="POST" action="{{ route('business.ai-logistics.load-board.store') }}">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="input-label">{{ translate('Origin') }} <span class="text-danger">*</span></label><input type="text" name="origin_name" class="form-control" required value="{{ old('origin_name') }}"></div>
                    <div class="col-md-3"><label class="input-label">{{ translate('Origin City') }} <span class="text-danger">*</span></label><input type="text" name="origin_city" class="form-control" required value="{{ old('origin_city') }}"></div>
                    <div class="col-md-3"><label class="input-label">{{ translate('Origin State') }} <span class="text-danger">*</span></label><input type="text" name="origin_state" class="form-control" maxlength="2" required value="{{ old('origin_state') }}"></div>

                    <div class="col-md-6"><label class="input-label">{{ translate('Destination') }} <span class="text-danger">*</span></label><input type="text" name="destination_name" class="form-control" required value="{{ old('destination_name') }}"></div>
                    <div class="col-md-3"><label class="input-label">{{ translate('Destination City') }} <span class="text-danger">*</span></label><input type="text" name="destination_city" class="form-control" required value="{{ old('destination_city') }}"></div>
                    <div class="col-md-3"><label class="input-label">{{ translate('Destination State') }} <span class="text-danger">*</span></label><input type="text" name="destination_state" class="form-control" maxlength="2" required value="{{ old('destination_state') }}"></div>

                    <div class="col-md-6"><label class="input-label">{{ translate('Pickup By') }} <span class="text-danger">*</span></label><input type="datetime-local" name="origin_ready_at" class="form-control" required value="{{ old('origin_ready_at') }}"></div>
                    <div class="col-md-6"><label class="input-label">{{ translate('Delivery By') }} <span class="text-danger">*</span></label><input type="datetime-local" name="destination_due_at" class="form-control" required value="{{ old('destination_due_at') }}"></div>

                    <div class="col-md-4"><label class="input-label">{{ translate('Equipment Type') }} <span class="text-danger">*</span></label>
                        <select name="equipment_type" class="form-control" required>
                            <option value="">{{ translate('Select') }}</option>
                            @foreach(['dry_van','reefer','flatbed','box_truck','cargo_van','sprinter'] as $type)
                                <option value="{{ $type }}" {{ old('equipment_type') === $type ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><label class="input-label">{{ translate('Weight (lbs)') }}</label><input type="number" name="weight_lbs" class="form-control" step="0.01" min="0" value="{{ old('weight_lbs') }}"></div>
                    <div class="col-md-4"><label class="input-label">{{ translate('Distance (miles)') }}</label><input type="number" name="distance_miles" class="form-control" step="0.01" min="0" value="{{ old('distance_miles') }}"></div>

                    <div class="col-md-4"><label class="input-label">{{ translate('Payout Amount ($)') }}</label><input type="number" name="payout_amount" class="form-control" step="0.01" min="0" value="{{ old('payout_amount') }}"></div>
                    <div class="col-md-8"></div>

                    <div class="col-12"><label class="input-label">{{ translate('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                    <div class="col-12"><label class="input-label">{{ translate('Special Requirements') }}</label><textarea name="special_requirements" class="form-control" rows="2">{{ old('special_requirements') }}</textarea></div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn--primary" style="background-color: var(--ug-primary); color: #fff;">{{ translate('Post Load') }}</button>
            </div>
        </div>
    </form>
@endsection
