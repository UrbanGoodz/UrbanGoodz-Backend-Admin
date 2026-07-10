@extends('layouts.admin.app')

@section('title', $client->company_name . ' - ' . translate('Documents'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title">{{ $client->company_name }} {{ translate('Documents') }}</h1>
            <a href="{{ route('admin.urban-goodz.business-clients.show', $client->id) }}" class="btn btn--secondary">
                <i class="tio-back"></i> {{ translate('Back to Client') }}
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5>{{ translate('Upload Document') }}</h5></div>
            <div class="card-body">
                <form action="{{ route('admin.urban-goodz.business-clients.documents.upload', $client->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Document Type') }} <span class="text-danger">*</span></label>
                            <select name="document_type" class="form-control" required>
                                @foreach($types as $t)
                                    <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Document Name') }}</label>
                            <input type="text" name="document_name" class="form-control" placeholder="{{ translate('Leave blank to use filename') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('File') }} <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="1"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn--primary">
                                <i class="tio-upload"></i> {{ translate('Upload') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('File') }}</th>
                            <th>{{ translate('Size') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Uploaded') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $key => $doc)
                            <tr>
                                <td>{{ $documents->firstItem() + $key }}</td>
                                <td class="font-weight-bold">{{ $doc->document_name }}</td>
                                <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', $doc->document_type) }}</span></td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.business-clients.documents.download', [$client->id, $doc->id]) }}" class="text-primary">
                                        <i class="tio-download"></i> {{ $doc->file_type ? explode('/', $doc->file_type)[1] : 'file' }}
                                    </a>
                                </td>
                                <td>{{ $doc->file_size ? round($doc->file_size / 1024, 1) . ' KB' : translate('N/A') }}</td>
                                <td>
                                    @php $docStatusColor = ['active'=>'success', 'archived'=>'secondary', 'pending'=>'warning', 'approved'=>'success', 'rejected'=>'danger']; @endphp
                                    <span class="badge badge-soft-{{ $docStatusColor[$doc->status] ?? 'info' }}">{{ $doc->status }}</span>
                                </td>
                                <td>{{ $doc->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-clients.documents.download', [$client->id, $doc->id]) }}">
                                                <i class="tio-download"></i> {{ translate('Download') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.business-clients.documents.destroy', [$client->id, $doc->id]) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this document?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($documents) === 0)
                            <tr><td colspan="8" class="text-center text-muted">{{ translate('No documents uploaded yet') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $documents->links() }}</div>
        </div>
    </div>
@endsection
