@extends('layouts.admin.app')

@section('title', translate('AI Concierge - Intents'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('AI Concierge - Intents') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $intents->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary mr-2">{{ translate('Back') }}</a>
                    <button class="btn btn--primary" data-toggle="modal" data-target="#createIntentModal">{{ translate('Add Intent') }}</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Slug') }}</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Description') }}</th>
                            <th>{{ translate('Capability') }}</th>
                            <th>{{ translate('Active') }}</th>
                            <th>{{ translate('Sort') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($intents as $key => $intent)
                            <tr>
                                <td>{{ $intents->firstItem() + $key }}</td>
                                <td><code>{{ $intent->slug }}</code></td>
                                <td>{{ $intent->name }}</td>
                                <td class="text-wrap" style="max-width:250px">{{ Str::limit($intent->description, 60) }}</td>
                                <td>{{ $intent->capability_slug ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $intent->is_active ? 'success' : 'secondary' }}">
                                        {{ $intent->is_active ? translate('Yes') : translate('No') }}
                                    </span>
                                </td>
                                <td>{{ $intent->sort_order }}</td>
                                <td>
                                    <button class="btn btn-sm btn--primary" data-toggle="modal" data-target="#editIntentModal{{ $intent->id }}">
                                        {{ translate('Edit') }}
                                    </button>
                                    <form action="{{ route('admin.urban-goodz.ai-concierge.intents.destroy', $intent->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Delete this intent?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">{{ translate('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editIntentModal{{ $intent->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form action="{{ route('admin.urban-goodz.ai-concierge.intents.update', $intent->id) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h5>{{ translate('Edit Intent') }}: {{ $intent->slug }}</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>{{ translate('Slug') }} *</label>
                                                            <input type="text" name="slug" class="form-control" value="{{ $intent->slug }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>{{ translate('Name') }} *</label>
                                                            <input type="text" name="name" class="form-control" value="{{ $intent->name }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>{{ translate('Capability Slug') }}</label>
                                                            <input type="text" name="capability_slug" class="form-control" value="{{ $intent->capability_slug }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>{{ translate('Admin Section Key') }}</label>
                                                            <input type="text" name="admin_section_key" class="form-control" value="{{ $intent->admin_section_key }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-group">
                                                            <label>{{ translate('Description') }}</label>
                                                            <textarea name="description" class="form-control" rows="2">{{ $intent->description }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-group">
                                                            <label>{{ translate('Keywords (comma-separated)') }}</label>
                                                            <input type="text" name="keywords" class="form-control" value="{{ is_array($intent->keywords) ? implode(',', $intent->keywords) : $intent->keywords }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-group">
                                                            <label>{{ translate('Response Template') }}</label>
                                                            <textarea name="response_template" class="form-control" rows="3">{{ $intent->response_template }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>{{ translate('Sort Order') }}</label>
                                                            <input type="number" name="sort_order" class="form-control" value="{{ $intent->sort_order }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <div class="d-flex align-items-center mt-4">
                                                                <input type="checkbox" name="is_active" value="1" id="is_active_{{ $intent->id }}" {{ $intent->is_active ? 'checked' : '' }} class="mr-2">
                                                                <label for="is_active_{{ $intent->id }}" class="mb-0">{{ translate('Active') }}</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn--primary">{{ translate('Update') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if(count($intents) === 0)
                            <tr>
                                <td colspan="8" class="text-center">{{ translate('No intents defined') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $intents->links() }}
            </div>
        </div>
    </div>

    <div class="modal fade" id="createIntentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('admin.urban-goodz.ai-concierge.intents.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5>{{ translate('Create Intent') }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ translate('Slug') }} *</label>
                                    <input type="text" name="slug" class="form-control" placeholder="e.g. request-order-status" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ translate('Name') }} *</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. Order Status Inquiry" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ translate('Capability Slug') }}</label>
                                    <input type="text" name="capability_slug" class="form-control" placeholder="e.g. order-management">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ translate('Admin Section Key') }}</label>
                                    <input type="text" name="admin_section_key" class="form-control" placeholder="e.g. order-anywhere">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label>{{ translate('Description') }}</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Describe when this intent matches"></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label>{{ translate('Keywords (comma-separated)') }}</label>
                                    <input type="text" name="keywords" class="form-control" placeholder="status, tracking, where is my order">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label>{{ translate('Response Template') }}</label>
                                    <textarea name="response_template" class="form-control" rows="3" placeholder="Your order is currently {status}. Expected delivery: {eta}"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ translate('Sort Order') }}</label>
                                    <input type="number" name="sort_order" class="form-control" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="d-flex align-items-center mt-4">
                                        <input type="checkbox" name="is_active" value="1" id="create_is_active" checked class="mr-2">
                                        <label for="create_is_active" class="mb-0">{{ translate('Active') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary">{{ translate('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
