@extends('layouts.admin.app')

@section('title', translate('File Library'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('File Library') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $files->total() }}</span>
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
                            <select name="file_category" class="form-control h--40px mr-2">
                                <option value="">{{ translate('All Categories') }}</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c }}" {{ request('file_category') === $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                            <select name="owner_type" class="form-control h--40px mr-2">
                                <option value="">{{ translate('All Owner Types') }}</option>
                                @foreach($ownerTypes as $o)
                                    <option value="{{ $o }}" {{ request('owner_type') === $o ? 'selected' : '' }}>{{ $o }}</option>
                                @endforeach
                            </select>
                            <input type="search" name="search" class="form-control h--40px" placeholder="{{ translate('Search files') }}" value="{{ request('search') }}">
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
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Category') }}</th>
                            <th>{{ translate('Owner Type') }}</th>
                            <th>{{ translate('Size') }}</th>
                            <th>{{ translate('MIME') }}</th>
                            <th>{{ translate('Visibility') }}</th>
                            <th>{{ translate('Uploaded') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($files as $key => $f)
                            <tr>
                                <td>{{ $files->firstItem() + $key }}</td>
                                <td>
                                    <div class="text-wrap" style="max-width:200px">{{ $f->original_name }}</div>
                                </td>
                                <td><span class="badge badge-soft-info">{{ $f->file_category }}</span></td>
                                <td>{{ $f->owner_type }}</td>
                                <td>{{ $f->file_size ? number_format($f->file_size / 1024, 1) . ' KB' : translate('N/A') }}</td>
                                <td><small>{{ $f->mime_type }}</small></td>
                                <td>
                                    <span class="badge badge-soft-{{ $f->visibility === 'public' ? 'success' : 'secondary' }}">
                                        {{ $f->visibility }}
                                    </span>
                                </td>
                                <td>{{ $f->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ asset('storage/' . $f->stored_path) }}" target="_blank" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($files) === 0)
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No files found') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $files->links() }}
            </div>
        </div>
    </div>
@endsection
