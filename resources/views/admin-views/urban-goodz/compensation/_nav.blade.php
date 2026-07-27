<div class="ug-comp-header">
    <div class="ug-comp-title">
        <h1>Driver Pricing &amp; Compensation</h1>
        <p>Rule versioning, splits, simulation and payout history. The backend is authoritative — mobile clients never calculate payout.</p>
    </div>
    @if($permissions['compensation_create_draft'] ?? false)
        <a href="{{ route('admin.urban-goodz.compensation.create') }}" class="ug-btn ug-btn-primary">New rule</a>
    @endif
</div>

<nav class="ug-comp-tabs" aria-label="Compensation sections">
    @foreach($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="ug-tab {{ request()->routeIs($tab['route']) ? 'is-active' : '' }}">{{ $tab['label'] }}</a>
    @endforeach
</nav>

@if(session('success'))
    <div class="ug-alert ug-alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="ug-alert ug-alert-warning">{{ session('warning') }}</div>
@endif
@if($errors->any())
    <div class="ug-alert ug-alert-error">
        <strong>Please correct the following:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
