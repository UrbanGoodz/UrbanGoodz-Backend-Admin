@extends('layouts.admin.app')

@section('title', translate('Fashion Fit Request Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Fashion Fit Request') }} #{{ $request['id'] }}</h1>
            <a href="{{ route('admin.urban-goodz.fashion-fit.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Measurement profile') }}</h5></div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">{{ translate('Customer') }}</dt><dd class="col-sm-8">{{ $request['user_id'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Preferred fit') }}</dt><dd class="col-sm-8">{{ $request['preferred_fit'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Item wanted') }}</dt><dd class="col-sm-8">{{ $request['item_wanted'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Budget') }}</dt><dd class="col-sm-8">{{ $request['budget'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Height') }}</dt><dd class="col-sm-8">{{ $request['height'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Chest/Bust') }}</dt><dd class="col-sm-8">{{ $request['chest_bust'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Waist') }}</dt><dd class="col-sm-8">{{ $request['waist'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Hips') }}</dt><dd class="col-sm-8">{{ $request['hips'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Inseam') }}</dt><dd class="col-sm-8">{{ $request['inseam'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Sleeve') }}</dt><dd class="col-sm-8">{{ $request['sleeve'] ?? '-' }}</dd>
                            <dt class="col-sm-4">{{ translate('Shoulder width') }}</dt><dd class="col-sm-8">{{ $request['shoulder_width'] ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Admin review') }}</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.urban-goodz.fashion-fit.update', $request['id']) }}">
                            @csrf
                            @method('PUT')
                            <label class="input-label">{{ translate('Status') }}</label>
                            <input name="status" class="form-control mb-3" value="{{ $request['status'] ?? 'pending' }}">
                            <label class="input-label">{{ translate('Quote amount') }}</label>
                            <input name="quote_amount" class="form-control mb-3" value="{{ $request['quote_amount'] }}">
                            <label class="input-label">{{ translate('Mockup reference') }}</label>
                            <input name="mockup_reference" class="form-control mb-3" value="{{ $request['mockup_reference'] }}">
                            <label class="input-label">{{ translate('Stylist notes') }}</label>
                            <textarea name="stylist_notes" class="form-control mb-3" rows="4">{{ $request['stylist_notes'] }}</textarea>
                            <label class="input-label">{{ translate('Corrected measurements') }}</label>
                            <textarea name="corrected_measurements" class="form-control mb-3" rows="4">{{ $request['corrected_measurements'] }}</textarea>
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('Save review') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
