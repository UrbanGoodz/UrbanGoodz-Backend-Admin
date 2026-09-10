@extends('business.layouts.app')

@section('title', translate('Demand Forecast'))

@section('content')
    <div class="page-header mb-3">
        <nav aria-label="breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-1">
            <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}">{{ translate('Business') }}</a></li>
            <li class="breadcrumb-item active">{{ translate('Demand Forecast') }}</li>
        </ol></nav>
        <h1 class="page-header-title">{{ translate('Demand Forecast') }}</h1>
        <p class="text-muted mb-0">{{ translate('Historical volume — not a predictive model') }}</p>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ translate('Monthly Loads (last 12 months)') }}</h5></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                    @forelse($monthlyLoads as $month => $count)
                    <tr><td>{{ $month }}</td><td class="text-end">{{ $count }}</td></tr>
                    @empty
                    <tr><td class="text-center text-muted py-3">{{ translate('No load history yet.') }}</td></tr>
                    @endforelse
                </tbody></table></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ translate('Monthly Packages (last 12 months)') }}</h5></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                    @forelse($monthlyPackages as $month => $count)
                    <tr><td>{{ $month }}</td><td class="text-end">{{ $count }}</td></tr>
                    @empty
                    <tr><td class="text-center text-muted py-3">{{ translate('No package history yet.') }}</td></tr>
                    @endforelse
                </tbody></table></div></div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ translate('Weekly Trend (last 8 weeks)') }}</h5></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                    @forelse($weeklyTrend as $week => $count)
                    <tr><td>{{ translate('Week') }} {{ $week }}</td><td class="text-end">{{ $count }}</td></tr>
                    @empty
                    <tr><td class="text-center text-muted py-3">{{ translate('No recent load history.') }}</td></tr>
                    @endforelse
                </tbody></table></div></div>
            </div>
        </div>
    </div>
@endsection
