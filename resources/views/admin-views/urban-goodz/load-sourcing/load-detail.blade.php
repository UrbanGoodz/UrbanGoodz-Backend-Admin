@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Load Detail'))

@section('content')
    <div class="content container-fluid">

        {{-- Breadcrumb & Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}">{{ translate('Sourced Loads') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">#{{ $load->id }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Load') }} #{{ $load->id }}</h1>
            </div>
            <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-back-ui"></i> {{ translate('Back to Sourced Loads') }}
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="row">
            {{-- Load facts --}}
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Load Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">{{ translate('Origin') }}</h6>
                                <p class="mb-3">
                                    {{ $load->origin_city }}{{ $load->origin_state ? ', ' . $load->origin_state : '' }} {{ $load->origin_zip }}<br>
                                    <small class="text-muted">{{ $load->origin_address }}</small>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">{{ translate('Destination') }}</h6>
                                <p class="mb-3">
                                    {{ $load->destination_city }}{{ $load->destination_state ? ', ' . $load->destination_state : '' }} {{ $load->destination_zip }}<br>
                                    <small class="text-muted">{{ $load->destination_address }}</small>
                                </p>
                            </div>
                        </div>

                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><th class="w-25">{{ translate('Status') }}</th><td><span class="badge badge-soft-info">{{ ucfirst(str_replace('_', ' ', $load->status)) }}</span></td></tr>
                                <tr><th>{{ translate('Source') }}</th><td>{{ $load->source->name ?? translate('Unknown') }}</td></tr>
                                <tr><th>{{ translate('External ID') }}</th><td>{{ $load->external_id ?: '—' }}</td></tr>
                                <tr><th>{{ translate('Broker') }}</th><td>{{ $load->broker_name ?: '—' }}</td></tr>
                                <tr><th>{{ translate('Equipment') }}</th><td>{{ $load->equipment_type ?: '—' }}{{ $load->trailer_type ? ' / ' . $load->trailer_type : '' }}</td></tr>
                                <tr><th>{{ translate('Commodity') }}</th><td>{{ $load->commodity ?: '—' }}</td></tr>
                                <tr><th>{{ translate('Weight') }}</th><td>{{ $load->weight ? number_format((float) $load->weight) . ' lb' : '—' }}</td></tr>
                                <tr><th>{{ translate('Loaded Miles') }}</th><td>{{ $load->distance_loaded ? number_format((float) $load->distance_loaded) : '—' }}</td></tr>
                                <tr><th>{{ translate('Deadhead Miles') }}</th><td>{{ $load->distance_deadhead ? number_format((float) $load->distance_deadhead) : '—' }}</td></tr>
                                <tr><th>{{ translate('Gross Rate') }}</th><td>{{ $load->gross_rate ? '$' . number_format((float) $load->gross_rate, 2) : '—' }}</td></tr>
                                <tr><th>{{ translate('Rate / Loaded Mile') }}</th><td>{{ $load->rate_per_loaded_mile ? '$' . number_format((float) $load->rate_per_loaded_mile, 2) : '—' }}</td></tr>
                                <tr><th>{{ translate('Estimated Driver Net') }}</th><td>{{ $load->estimated_driver_net ? '$' . number_format((float) $load->estimated_driver_net, 2) : '—' }}</td></tr>
                                <tr><th>{{ translate('Pickup Window') }}</th><td>{{ $load->pickup_start ?: '—' }} → {{ $load->pickup_end ?: '—' }}</td></tr>
                                <tr><th>{{ translate('Delivery Window') }}</th><td>{{ $load->delivery_start ?: '—' }} → {{ $load->delivery_end ?: '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="col-lg-4 mb-3">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Assign Dispatcher') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.assign-dispatcher', $load->id) }}">
                            @csrf
                            <div class="form-group mb-2">
                                <select name="delivery_man_id" class="form-control" required>
                                    <option value="">{{ translate('Select a dispatcher') }}</option>
                                    @foreach($dispatchers as $dispatcher)
                                        <option value="{{ $dispatcher->id }}">{{ $dispatcher->f_name }} {{ $dispatcher->l_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn--primary btn-sm w-100">
                                <i class="tio-user"></i> {{ translate('Assign') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Actions') }}</h5></div>
                    <div class="card-body d-flex flex-column gap-2">
                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.recommend-driver', $load->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm w-100">
                                <i class="tio-star"></i> {{ translate('Generate Driver Recommendations') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.publish-load', $load->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-info btn-sm w-100">
                                <i class="tio-send"></i> {{ translate('Publish to Load Board') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.archive-load', $load->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-dark btn-sm w-100">
                                <i class="tio-archive"></i> {{ translate('Archive Load') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recommendations for this load --}}
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ translate('Driver Recommendations') }}</h5></div>
            <div class="table-responsive">
                <table class="table table-align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Driver') }}</th>
                            <th>{{ translate('Score') }}</th>
                            <th>{{ translate('Confidence') }}</th>
                            <th>{{ translate('Estimated Net') }}</th>
                            <th>{{ translate('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recommendations as $rec)
                            <tr>
                                <td>{{ $rec->driver ? $rec->driver->f_name . ' ' . $rec->driver->l_name : translate('Unknown') }}</td>
                                <td>{{ $rec->score }}</td>
                                <td>{{ $rec->confidence_label }}</td>
                                <td>{{ $rec->estimated_driver_net ? '$' . number_format((float) $rec->estimated_driver_net, 2) : '—' }}</td>
                                <td><span class="badge badge-soft-secondary">{{ ucfirst(str_replace('_', ' ', $rec->status)) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    {{ translate('No driver recommendations have been generated for this load yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
