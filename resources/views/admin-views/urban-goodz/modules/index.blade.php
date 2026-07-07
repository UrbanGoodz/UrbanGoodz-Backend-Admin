@extends('layouts.admin.app')

@section('title', translate($title))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate($title) }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $items->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.modules.create', $section) }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add New') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search') }}" value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @if(!empty($statuses))
                        <select name="status" class="form-control ml-2" style="max-width:180px" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            @foreach($statuses as $st)
                                <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            @foreach($columns as $col)
                                <th>{{ translate(str_replace('_', ' ', ucfirst($col))) }}</th>
                            @endforeach
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $key => $item)
                            <tr>
                                <td>{{ $items->firstItem() + $key }}</td>
                                @foreach($columns as $col)
                                    <td>
                                        @if(in_array($col, ['is_active', 'is_published', 'is_featured', 'is_verified', 'is_approved', 'was_fulfilled']))
                                            <span class="badge badge-soft-{{ $item->$col ? 'success' : 'secondary' }}">
                                                {{ $item->$col ? translate('Yes') : translate('No') }}
                                            </span>
                                        @elseif(in_array($col, ['price', 'reward_amount', 'monthly_fee', 'offer_amount', 'ticket_price']))
                                            {{ $item->$col !== null ? '$' . number_format($item->$col, 2) : '-' }}
                                        @elseif($col === 'status' && $item->$col)
                                            @php
                                                $colors = ['pending'=>'warning', 'active'=>'success', 'inactive'=>'secondary', 'published'=>'success', 'draft'=>'secondary', 'completed'=>'info', 'cancelled'=>'danger', 'approved'=>'success', 'rejected'=>'danger', 'assigned'=>'info', 'in_transit'=>'warning', 'delivered'=>'success', 'available'=>'success', 'sold'=>'info', 'reserved'=>'warning', 'expired'=>'dark'];
                                                $color = $colors[$item->$col] ?? 'secondary';
                                            @endphp
                                            <span class="badge badge-soft-{{ $color }}">{{ ucfirst($item->$col) }}</span>
                                        @elseif(in_array($col, ['created_at', 'updated_at', 'scheduled_at', 'subscribed_at', 'expires_at', 'starts_at', 'ends_at', 'logged_at', 'completed_at']) && $item->$col)
                                            {{ $item->$col instanceof \Carbon\Carbon ? $item->$col->format('M d, Y g:i A') : $item->$col }}
                                        @elseif($col === 'body' || $col === 'description')
                                            {{ \Illuminate\Support\Str::limit($item->$col, 60) }}
                                        @else
                                            {{ $item->$col ?? '-' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.modules.edit', [$section, $item->id]) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.modules.destroy', [$section, $item->id]) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this record?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) + 2 }}" class="text-center">
                                    <span class="text-muted">{{ translate('No records found.') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($items->hasPages())
                <div class="card-footer">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
