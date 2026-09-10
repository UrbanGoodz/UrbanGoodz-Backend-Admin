@extends('business.layouts.app')

@section('title', translate('Returns'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Returns') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Returns') }}</h1>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Package') }}</th><th>{{ translate('Driver') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse($returns as $pkg)
                        <tr>
                            <td>{{ $pkg->tracking_id ?? ('#'.$pkg->id) }}</td>
                            <td>{{ $pkg->routeBatch?->deliveryMan?->f_name }} {{ $pkg->routeBatch?->deliveryMan?->l_name }}</td>
                            <td><span class="badge badge-soft-warning">{{ ucwords(str_replace('_',' ', $pkg->status)) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">{{ translate('No returns in progress.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($returns instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $returns->links() }}</div>
        @endif
    </div>
@endsection
