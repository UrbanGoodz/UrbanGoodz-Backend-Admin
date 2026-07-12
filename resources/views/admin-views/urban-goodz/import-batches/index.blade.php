@extends('layouts.admin.app')

@section('title', translate('Import Batches'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Import Batches') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $batches->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper justify-content-end">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search by city, state, category, or module') }}"
                                   value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('City') }}</th>
                            <th>{{ translate('State') }}</th>
                            <th>{{ translate('Category') }}</th>
                            <th>{{ translate('Module') }}</th>
                            <th>{{ translate('Found') }}</th>
                            <th>{{ translate('Imported') }}</th>
                            <th>{{ translate('Needs Review') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Completed') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $key => $batch)
                            <tr>
                                <td>{{ $batches->firstItem() + $key }}</td>
                                <td>{{ $batch->city ?? '-' }}</td>
                                <td>{{ $batch->state ?? '-' }}</td>
                                <td>{{ $batch->category ?? '-' }}</td>
                                <td>{{ $batch->module ?? '-' }}</td>
                                <td><span class="badge badge-soft-info">{{ $batch->total_found }}</span></td>
                                <td><span class="badge badge-soft-success">{{ $batch->total_imported }}</span></td>
                                <td><span class="badge badge-soft-warning">{{ $batch->total_needs_review }}</span></td>
                                <td>
                                    @php
                                        $statusMap = ['pending' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
                                        $badge = $statusMap[$batch->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge badge-soft-{{ $badge }}">{{ ucfirst($batch->status) }}</span>
                                </td>
                                <td>{{ $batch->completed_at?->format('M d, Y h:i A') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.import-batches.show', $batch->id) }}" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($batches->hasPages())
                <div class="card-footer">
                    {{ $batches->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
