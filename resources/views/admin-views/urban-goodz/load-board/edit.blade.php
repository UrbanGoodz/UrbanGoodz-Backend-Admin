@extends('layouts.admin.app')

@section('title', translate('Edit Load') . ' #' . $load->id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.load-board.show', $load->id) }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Load') }}
            </a>
            <h1 class="page-header-title">{{ translate('Edit Load') }} #{{ $load->id }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.urban-goodz.load-board.update', $load->id) }}">
            @csrf
            @method('PUT')
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('General') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Load Number') }}</label>
                            <input type="text" name="load_number" class="form-control" value="{{ old('load_number', $load->load_number) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Load Type') }}</label>
                            <select name="load_type" class="form-control">
                                <option value="">{{ translate('Select') }}</option>
                                @foreach(['FTL', 'LTL', 'Partial', 'Last Mile', 'White Glove'] as $type)
                                <option value="{{ $type }}" {{ old('load_type', $load->load_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Equipment Type') }}</label>
                            <input type="text" name="equipment_type" class="form-control" value="{{ old('equipment_type', $load->equipment_type) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Origin') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">{{ translate('Name') }}</label><input type="text" name="origin_name" class="form-control" value="{{ old('origin_name', $load->origin_name) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('City') }}</label><input type="text" name="origin_city" class="form-control" value="{{ old('origin_city', $load->origin_city) }}"></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('State') }}</label><input type="text" name="origin_state" class="form-control" value="{{ old('origin_state', $load->origin_state) }}" maxlength="2"></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('Zip') }}</label><input type="text" name="origin_zip" class="form-control" value="{{ old('origin_zip', $load->origin_zip) }}" maxlength="10"></div>
                        <div class="col-md-1"><label class="form-label">{{ translate('Ready At') }}</label><input type="datetime-local" name="origin_ready_at" class="form-control" value="{{ old('origin_ready_at', $load->origin_ready_at?->format('Y-m-d\TH:i')) }}"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Destination') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">{{ translate('Name') }}</label><input type="text" name="destination_name" class="form-control" value="{{ old('destination_name', $load->destination_name) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('City') }}</label><input type="text" name="destination_city" class="form-control" value="{{ old('destination_city', $load->destination_city) }}"></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('State') }}</label><input type="text" name="destination_state" class="form-control" value="{{ old('destination_state', $load->destination_state) }}" maxlength="2"></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('Zip') }}</label><input type="text" name="destination_zip" class="form-control" value="{{ old('destination_zip', $load->destination_zip) }}" maxlength="10"></div>
                        <div class="col-md-1"><label class="form-label">{{ translate('Due At') }}</label><input type="datetime-local" name="destination_due_at" class="form-control" value="{{ old('destination_due_at', $load->destination_due_at?->format('Y-m-d\TH:i')) }}"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Pricing & Specs') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">{{ translate('Payout Amount') }} *</label><input type="number" name="payout_amount" class="form-control" value="{{ old('payout_amount', $load->payout_amount) }}" step="0.01" required></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Payout Type') }}</label><select name="payout_type" class="form-control"><option value="flat" {{ old('payout_type', $load->payout_type) === 'flat' ? 'selected' : '' }}>Flat</option><option value="per_mile" {{ old('payout_type', $load->payout_type) === 'per_mile' ? 'selected' : '' }}>Per Mile</option><option value="per_hour" {{ old('payout_type', $load->payout_type) === 'per_hour' ? 'selected' : '' }}>Per Hour</option></select></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Rate Per Mile') }}</label><input type="number" name="rate_per_mile" class="form-control" value="{{ old('rate_per_mile', $load->rate_per_mile) }}" step="0.01"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Distance (miles)') }}</label><input type="number" name="distance_miles" class="form-control" value="{{ old('distance_miles', $load->distance_miles) }}" step="0.1"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Weight (lbs)') }}</label><input type="number" name="weight_lbs" class="form-control" value="{{ old('weight_lbs', $load->weight_lbs) }}" step="0.1"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Length (ft)') }}</label><input type="number" name="length_ft" class="form-control" value="{{ old('length_ft', $load->length_ft) }}" step="0.1"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Pieces') }}</label><input type="number" name="pieces" class="form-control" value="{{ old('pieces', $load->pieces) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Est. Duration (min)') }}</label><input type="number" name="estimated_duration_minutes" class="form-control" value="{{ old('estimated_duration_minutes', $load->estimated_duration_minutes) }}"></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('Commodity Description') }}</label><input type="text" name="commodity_description" class="form-control" value="{{ old('commodity_description', $load->commodity_description) }}"></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('Special Requirements') }}</label><input type="text" name="special_requirements" class="form-control" value="{{ old('special_requirements', $load->special_requirements) }}"></div>
                        <div class="col-12"><label class="form-label">{{ translate('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $load->notes) }}</textarea></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Flags') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3 form-check"><input type="checkbox" name="is_hazmat" value="1" class="form-check-input" id="is_hazmat" {{ old('is_hazmat', $load->is_hazmat) ? 'checked' : '' }}><label class="form-check-label" for="is_hazmat">{{ translate('Hazmat') }}</label></div>
                        <div class="col-md-3 form-check"><input type="checkbox" name="is_temperature_controlled" value="1" class="form-check-input" id="is_tc" {{ old('is_temperature_controlled', $load->is_temperature_controlled) ? 'checked' : '' }}><label class="form-check-label" for="is_tc">{{ translate('Temperature Controlled') }}</label></div>
                        <div class="col-md-3 form-check"><input type="checkbox" name="requires_liftgate" value="1" class="form-check-input" id="liftgate" {{ old('requires_liftgate', $load->requires_liftgate) ? 'checked' : '' }}><label class="form-check-label" for="liftgate">{{ translate('Liftgate Required') }}</label></div>
                        <div class="col-md-3 form-check"><input type="checkbox" name="requires_pallet_jack" value="1" class="form-check-input" id="pallet" {{ old('requires_pallet_jack', $load->requires_pallet_jack) ? 'checked' : '' }}><label class="form-check-label" for="pallet">{{ translate('Pallet Jack Required') }}</label></div>
                        <div class="col-md-3 form-check"><input type="checkbox" name="is_team_load" value="1" class="form-check-input" id="team" {{ old('is_team_load', $load->is_team_load) ? 'checked' : '' }}><label class="form-check-label" for="team">{{ translate('Team Load') }}</label></div>
                        <div class="col-md-3 form-check"><input type="checkbox" name="is_expedited" value="1" class="form-check-input" id="exp" {{ old('is_expedited', $load->is_expedited) ? 'checked' : '' }}><label class="form-check-label" for="exp">{{ translate('Expedited') }}</label></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Temp Min (F)') }}</label><input type="number" name="temperature_min_f" class="form-control" value="{{ old('temperature_min_f', $load->temperature_min_f) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Temp Max (F)') }}</label><input type="number" name="temperature_max_f" class="form-control" value="{{ old('temperature_max_f', $load->temperature_max_f) }}"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Contact Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">{{ translate('Shipper Name') }}</label><input type="text" name="shipper_name" class="form-control" value="{{ old('shipper_name', $load->shipper_name) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Shipper Phone') }}</label><input type="text" name="shipper_phone" class="form-control" value="{{ old('shipper_phone', $load->shipper_phone) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Consignee Name') }}</label><input type="text" name="consignee_name" class="form-control" value="{{ old('consignee_name', $load->consignee_name) }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('Consignee Phone') }}</label><input type="text" name="consignee_phone" class="form-control" value="{{ old('consignee_phone', $load->consignee_phone) }}"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle"></i> {{ translate('Update Load') }}</button>
                <a href="{{ route('admin.urban-goodz.load-board.show', $load->id) }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
