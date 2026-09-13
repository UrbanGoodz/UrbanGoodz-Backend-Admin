@extends('layouts.admin.app')

@section('title', translate('Sourcing CSV Import'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-file-add"></i></span>
                <span>{{ translate('CSV Import') }}</span>
            </h1>
            <p class="page-header-text mb-0">
                {{ translate('Upload sourcing leads in bulk. Rows land in the validation queue rather than going live directly.') }}
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.sourcing.csv-import.process') }}"
                              method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="csv_file">{{ translate('CSV File') }}</label>
                                <input type="file" id="csv_file" name="csv_file" class="form-control"
                                       accept=".csv,text/csv" required>
                                <small class="form-text text-muted">
                                    {{ translate('A header row is expected. Maximum 2MB.') }}
                                </small>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1"
                                       id="skip_duplicates" name="skip_duplicates" checked>
                                <label class="form-check-label" for="skip_duplicates">
                                    {{ translate('Skip rows that duplicate an existing lead') }}
                                </label>
                            </div>

                            <button type="submit" class="btn btn--primary">{{ translate('Upload and Validate') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('After upload') }}</h5></div>
                    <div class="card-body">
                        <p class="fs-12 text-muted">
                            {{ translate('Imported rows are reviewed before they are published.') }}
                        </p>
                        <div class="d-grid gap-2">
                            @foreach ([
                                'admin.urban-goodz.sourcing.validation-queue' => translate('Validation Queue'),
                                'admin.urban-goodz.sourcing.duplicate-queue'  => translate('Duplicate Queue'),
                                'admin.urban-goodz.sourcing.approval-queue'   => translate('Approval Queue'),
                                'admin.urban-goodz.sourcing.audit-history'    => translate('Audit History'),
                            ] as $routeName => $label)
                                {{-- Guarded so an unregistered queue route cannot take this page down. --}}
                                @continue(!Route::has($routeName))
                                <a href="{{ route($routeName) }}" class="btn btn-white text-start">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
