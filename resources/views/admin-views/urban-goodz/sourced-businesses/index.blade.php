@extends('layouts.admin.app')

@section('title', translate('Sourced Business Review'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title">{{ translate('Sourced Business Review') }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back to Control Center') }}</a>
            </div>
        </div>

        <div class="alert alert--info mb-3">
            {{ translate('Staged review-only records. partnered_status stays false; visibility stays private; no store, vendor, product, or activation is performed here.') }}
        </div>

        {{-- Counts --}}
        <div class="row g-3 mb-3">
            <div class="col"><div class="card p-3"><div class="text-muted">{{ translate('Total staged') }}</div><div class="h3">{{ $stats['total'] }}</div></div></div>
            <div class="col"><div class="card p-3"><div class="text-muted">{{ translate('Pending review') }}</div><div class="h3">{{ $stats['pending'] }}</div></div></div>
            <div class="col"><div class="card p-3"><div class="text-muted">{{ translate('Approved') }}</div><div class="h3">{{ $stats['approved'] }}</div></div></div>
            <div class="col"><div class="card p-3"><div class="text-muted">{{ translate('Rejected') }}</div><div class="h3">{{ $stats['rejected'] }}</div></div></div>
            <div class="col"><div class="card p-3"><div class="text-muted">{{ translate('Category pending') }}</div><div class="h3">{{ $stats['category_pending'] }}</div></div></div>
            <div class="col"><div class="card p-3"><div class="text-muted">{{ translate('Age-restricted review_only') }}</div><div class="h3">{{ $stats['age_review_only'] }}</div></div></div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
                    <div><label class="form-label">{{ translate('Batch marker') }}</label>
                        <input type="text" name="batch" value="{{ $marker }}" class="form-control" readonly></div>
                    <div><label class="form-label">{{ translate('Review status') }}</label>
                        <select name="review_status" class="form-select">
                            <option value="">{{ translate('All') }}</option>
                            @foreach(['pending','approved','rejected','merge_required'] as $s)
                                <option value="{{ $s }}" @selected(request('review_status')===$s)>{{ translate(ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="form-label">{{ translate('Module') }}</label>
                        <select name="module_id" class="form-select">
                            <option value="">{{ translate('All') }}</option>
                            @foreach($modules as $m)<option value="{{ $m->id }}" @selected(request('module_id')==$m->id)>{{ $m->module_name }}</option>@endforeach
                        </select></div>
                    <div><label class="form-label">{{ translate('City') }}</label><input type="text" name="city" value="{{ request('city') }}" class="form-control"></div>
                    <div><label class="form-label">{{ translate('State') }}</label><input type="text" name="state" value="{{ request('state') }}" class="form-control"></div>
                    <div><label class="form-label">{{ translate('Age-restricted only') }}</label><input type="checkbox" name="age_restricted" value="1" @checked(request('age_restricted')=='1')></div>
                    <div><label class="form-label">{{ translate('Category pending only') }}</label><input type="checkbox" name="category_pending" value="1" @checked(request('category_pending')=='1')></div>
                    <div><label class="form-label">{{ translate('Invalid source URL only') }}</label><input type="checkbox" name="source_url_invalid" value="1" @checked(request('source_url_invalid')=='1')></div>
                    <div><button type="submit" class="btn btn--primary">{{ translate('Filter') }}</button></div>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ translate('Business') }}</th>
                            <th>{{ translate('Module') }}</th>
                            <th>{{ translate('City/State') }}</th>
                            <th>{{ translate('Age-restricted') }}</th>
                            <th>{{ translate('Category IDs') }}</th>
                            <th>{{ translate('Review status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td>{{ $r->name }}</td>
                                <td>{{ $r->module_name }}</td>
                                <td>{{ $r->city }}, {{ $r->state }}</td>
                                <td>{{ $r->fulfillment_modes === ['review_only'] ? translate('Yes (review_only)') : translate('No') }}</td>
                                <td>{{ empty($r->category_ids) ? translate('Pending') : implode(', ', $r->category_ids) }}</td>
                                <td>{{ translate(ucfirst(str_replace('_',' ',$r->admin_review_status))) }}</td>
                                <td><a href="{{ route('admin.urban-goodz.sourced-businesses.show', $r->id) }}" class="btn btn--secondary btn-sm">{{ translate('Review') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">{{ translate('No staged rows match the filter.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $rows->links() }}
            </div>
        </div>
    </div>
@endsection
