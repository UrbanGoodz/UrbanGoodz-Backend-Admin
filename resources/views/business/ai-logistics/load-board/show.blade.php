@extends('business.layouts.app')

@section('title', translate('Load Details'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h1 class="page-header-title mb-0">
                <span class="page-header-icon"><i class="tio-truck"></i></span>
                <span>{{ $load->load_number ?? ('#' . $load->id) }}</span>
            </h1>
            <a href="{{ route('business.ai-logistics.load-board.index') }}" class="btn btn-secondary btn-sm">
                {{ translate('Back to Load Board') }}
            </a>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Load') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">{{ translate('Status') }}</div>
                                <div class="text-capitalize">{{ str_replace('_', ' ', (string) $load->status) }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">{{ translate('Rate') }}</div>
                                <div>{{ $load->rate ? \App\CentralLogics\Helpers::format_currency($load->rate) : '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">{{ translate('Pickup') }}</div>
                                <div>{{ $load->pickup_city ?? $load->pickup_location ?? '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">{{ translate('Dropoff') }}</div>
                                <div>{{ $load->dropoff_city ?? $load->dropoff_location ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            {{ translate('Dispatches') }}
                            <span class="badge badge-soft-dark ms-1">{{ count($dispatches ?? []) }}</span>
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('Driver') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('When') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($dispatches ?? []) as $dispatch)
                                    <tr>
                                        <td>
                                            @if ($dispatch->driver)
                                                {{ $dispatch->driver->f_name }} {{ $dispatch->driver->l_name }}
                                            @else
                                                #{{ $dispatch->driver_id ?? '-' }}
                                            @endif
                                        </td>
                                        <td class="text-capitalize">{{ str_replace('_', ' ', (string) $dispatch->status) }}</td>
                                        <td>{{ $dispatch->created_at ? $dispatch->created_at->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            {{ translate('No driver has been dispatched to this load yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            {{ translate('Available Drivers') }}
                            <span class="badge badge-soft-dark ms-1">{{ count($availableDrivers ?? []) }}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        {{-- dispatchDriver() redirects back to this page on success, so
                             the form posts to the existing dispatch route rather than
                             inventing a new one. --}}
                        @if (Route::has('business.ai-logistics.dispatch.store') && count($availableDrivers ?? []))
                            <form method="POST" action="{{ route('business.ai-logistics.dispatch.store') }}">
                                @csrf
                                <input type="hidden" name="load_id" value="{{ $load->id }}">
                                <div class="mb-3">
                                    <label class="form-label" for="driver_id">{{ translate('Driver') }}</label>
                                    <select id="driver_id" name="driver_id" class="form-control" required>
                                        @foreach ($availableDrivers as $driver)
                                            <option value="{{ $driver->id }}">
                                                {{ $driver->f_name }} {{ $driver->l_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn--primary w-100">{{ translate('Dispatch Driver') }}</button>
                            </form>
                        @else
                            <p class="text-muted mb-0">
                                {{ translate('No approved, active driver is available for this load right now.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
