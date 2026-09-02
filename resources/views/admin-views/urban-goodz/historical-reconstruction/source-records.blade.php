@extends('layouts.admin.app')

@section('title', translate('Source Records'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', $config->id) }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Source Records') }}: {{ $config->configuration_name }}</h1>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Import Source Record') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.source-records.import', $config->id) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Source Type') }} *</label>
                            <select name="source_type" class="form-select" required>
                                @foreach(\App\Models\UrbanGoodzHistoricalSourceRecord::SOURCE_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Confidence') }} *</label>
                            <select name="confidence_label" class="form-select" required>
                                <option value="verified">{{ translate('Verified') }}</option>
                                <option value="high">{{ translate('High') }}</option>
                                <option value="medium">{{ translate('Medium') }}</option>
                                <option value="estimated" selected>{{ translate('Estimated') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Snapshot Month') }}</label>
                            <input type="month" name="snapshot_month" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Source Date') }}</label>
                            <input type="date" name="source_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Description') }}</label>
                            <input type="text" name="source_description" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Notes') }}</label>
                            <input type="text" name="notes" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn--primary w-100"><i class="tio-add"></i> {{ translate('Import Record') }}</button>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Orders') }}</label>
                            <input type="number" name="orders" class="form-control" step="1" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Avg Order Value') }}</label>
                            <input type="number" name="average_order_value" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Delivery Fee') }}</label>
                            <input type="number" name="delivery_fee" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Active Drivers') }}</label>
                            <input type="number" name="active_drivers" class="form-control" step="1" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Owner Deliveries') }}</label>
                            <input type="number" name="owner_deliveries" class="form-control" step="1" min="0">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Imported Source Records') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Description') }}</th>
                                <th>{{ translate('Snapshot') }}</th>
                                <th>{{ translate('Confidence') }}</th>
                                <th>{{ translate('Overrides') }}</th>
                                <th>{{ translate('Imported') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                            <tr>
                                <td>{{ $record->id }}</td>
                                <td><span class="badge badge-soft-info">{{ $record->source_type_label }}</span></td>
                                <td>{{ $record->source_description ?? '-' }}</td>
                                <td>{{ $record->snapshot ? $record->snapshot->month_label : '-' }}</td>
                                <td><span class="badge badge-soft-{{ $record->confidence_label === 'verified' ? 'success' : 'secondary' }}">{{ strtoupper($record->confidence_label) }}</span></td>
                                <td>
                                    @if($record->overrides_reconstruction)
                                        <span class="badge badge-soft-warning">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="text-muted">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $record->created_at->format('M d, Y') }}</small></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No source records imported yet') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $records->links() }}
            </div>
        </div>
    </div>
@endsection
