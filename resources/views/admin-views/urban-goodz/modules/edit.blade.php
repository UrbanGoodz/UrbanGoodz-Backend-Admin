@extends('layouts.admin.app')

@section('title', translate('Edit') . ' - ' . translate($title))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit') }} {{ translate($title) }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.modules.index', $section) }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.modules.update', [$section, $item->id]) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    @foreach($item->getFillable() as $field)
                        @continue(in_array($field, ['id']))
                        <div class="form-group">
                            <label class="input-label">
                                {{ translate(str_replace('_', ' ', ucfirst($field))) }}
                            </label>

                            @if(in_array($field, ['description', 'body', 'bio', 'terms', 'admin_notes']))
                                <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $item->$field) }}</textarea>
                            @elseif(in_array($field, ['is_active', 'is_published', 'is_featured', 'is_verified', 'is_approved', 'was_fulfilled', 'requires_refrigeration', 'is_biological_hazard']))
                                <label class="toggle-switch my-0">
                                    <input type="checkbox" name="{{ $field }}" class="toggle-switch-input" value="1" {{ old($field, $item->$field) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                </label>
                            @elseif(in_array($field, ['price', 'reward_amount', 'monthly_fee', 'offer_amount', 'ticket_price', 'follower_count', 'capacity', 'sort_order', 'result_count', 'weight_kg']))
                                <input type="number" name="{{ $field }}" class="form-control" value="{{ old($field, $item->$field) }}" step="0.01" min="0">
                            @elseif(in_array($field, ['starts_at', 'ends_at', 'subscribed_at', 'expires_at', 'scheduled_at', 'pickup_by', 'deliver_by', 'completed_at', 'featured_until', 'published_at']))
                                <input type="datetime-local" name="{{ $field }}" class="form-control" value="{{ old($field, $item->$field ? (is_string($item->$field) ? $item->$field : $item->$field->format('Y-m-d\TH:i')) : '') }}">
                            @elseif(in_array($field, ['email', 'member_email', 'customer_email', 'applicant_email']))
                                <input type="email" name="{{ $field }}" class="form-control" value="{{ old($field, $item->$field) }}">
                            @elseif($field === 'currency')
                                <input type="text" name="{{ $field }}" class="form-control" value="{{ old($field, $item->$field ?? 'USD') }}" maxlength="3">
                            @elseif(in_array($field, ['media_urls', 'service_areas', 'preferred_dates', 'benefits']))
                                <textarea name="{{ $field }}" class="form-control" rows="2" placeholder="JSON">{{ old($field, is_array($item->$field) ? json_encode($item->$field) : $item->$field) }}</textarea>
                            @else
                                <input type="text" name="{{ $field }}" class="form-control" value="{{ old($field, $item->$field) }}">
                            @endif

                            @error($field)
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    @endforeach
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary">{{ translate('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
