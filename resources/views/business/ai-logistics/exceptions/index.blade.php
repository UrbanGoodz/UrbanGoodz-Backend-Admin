@extends('business.layouts.app')

@section('title', translate('Exceptions'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Exceptions') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Exceptions') }}</h1>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ translate('Package Exceptions') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>{{ translate('Package') }}</th><th>{{ translate('Driver') }}</th><th>{{ translate('Notes') }}</th><th class="text-center">{{ translate('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($exceptions as $pkg)
                        <tr>
                            <td>{{ $pkg->tracking_id ?? ('#'.$pkg->id) }}</td>
                            <td>{{ $pkg->routeBatch?->deliveryMan?->f_name }} {{ $pkg->routeBatch?->deliveryMan?->l_name }}</td>
                            <td><small class="text-muted">{{ $pkg->exception_notes ?? '—' }}</small></td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('business.ai-logistics.exceptions.resolve', $pkg->id) }}" class="d-inline">
                                    @csrf
                                    <select name="resolution" class="form-control form-control-sm d-inline w-auto" required>
                                        <option value="redeliver">{{ translate('Redeliver') }}</option>
                                        <option value="return">{{ translate('Return') }}</option>
                                        <option value="refund">{{ translate('Refund') }}</option>
                                        <option value="cancel">{{ translate('Cancel') }}</option>
                                        <option value="reassign">{{ translate('Reassign') }}</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ translate('Resolve') }}</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ translate('No open exceptions.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($exceptions instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer d-flex justify-content-end">{{ $exceptions->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ translate('Driver-Reported Exceptions') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <tbody>
                        @forelse($driverExceptions as $ex)
                        <tr><td>{{ $ex->description ?? $ex->id }}</td></tr>
                        @empty
                        <tr><td class="text-center text-muted py-4">{{ translate('Driver-reported exceptions are not yet available.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
