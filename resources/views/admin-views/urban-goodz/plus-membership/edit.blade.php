@extends('layouts.admin.app')

@section('title', translate('Edit Membership'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Edit Plus Membership') }}: {{ $membership->member_name }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.plus-membership.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.urban-goodz.plus-membership.update', $membership->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Member Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="member_name" class="form-control" value="{{ old('member_name', $membership->member_name) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Member Email') }} <span class="text-danger">*</span></label>
                                <input type="email" name="member_email" class="form-control" value="{{ old('member_email', $membership->member_email) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Tier') }} <span class="text-danger">*</span></label>
                                <select name="tier" class="form-control" required>
                                    @foreach(['basic', 'premium', 'elite'] as $tier)
                                        <option value="{{ $tier }}" {{ old('tier', $membership->tier) === $tier ? 'selected' : '' }}>
                                            {{ ucfirst($tier) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Status') }} <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    @foreach(['active', 'expired', 'cancelled'] as $s)
                                        <option value="{{ $s }}" {{ old('status', $membership->status) === $s ? 'selected' : '' }}>
                                            {{ ucfirst($s) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Monthly Fee') }} <span class="text-danger">*</span></label>
                                <input type="number" name="monthly_fee" class="form-control" value="{{ old('monthly_fee', $membership->monthly_fee) }}" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Subscribed At') }}</label>
                                <input type="datetime-local" name="subscribed_at" class="form-control" value="{{ old('subscribed_at', $membership->subscribed_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Expires At') }}</label>
                                <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at', $membership->expires_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Benefits') }} <small class="text-muted">(one per line)</small></label>
                                <textarea name="benefits" class="form-control" rows="3">{{ old('benefits', is_array($membership->benefits) ? implode("\n", $membership->benefits) : '') }}</textarea>
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
