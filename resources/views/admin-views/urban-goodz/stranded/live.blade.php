@extends('layouts.admin.app')
@section('title', 'Stranded — Live Operations')

@push('css_or_js')
<style>
  .ug-live-map { height: 460px; width: 100%; border-radius: 8px; background: #eee; }
  .ug-live-stat { text-align: center; padding: .75rem .5rem; }
  .ug-live-stat .v { font-size: 1.6rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
  .ug-live-stat .k { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; opacity: .65; margin-top: .35rem; }
  .ug-dot { display:inline-block; width:.5rem; height:.5rem; border-radius:50%; margin-right:.4rem; }
  .ug-row-emergency { background: rgba(220,53,69,.06) !important; }
  .ug-stale { opacity: .55; }
  #ug-live-updated { font-size: .75rem; opacity: .6; }
</style>
@endpush

@section('content')
<div class="container-fluid">
  <div class="page-header">
    <div class="row align-items-center">
      <div class="col-sm mb-2 mb-sm-0">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb breadcrumb-no-gutter">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Home') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Stranded Live</li>
          </ol>
        </nav>
        <h1 class="page-header-title">Stranded &mdash; Live Operations</h1>
        <p class="text-muted mb-0">
          Every active rescue and every responder on the road.
          <span id="ug-live-updated"></span>
        </p>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row" id="ug-live-summary">
        @foreach ([
          ['k' => 'active_requests', 'label' => 'Active requests'],
          ['k' => 'emergencies', 'label' => 'Emergencies'],
          ['k' => 'awaiting_responder', 'label' => 'Awaiting responder'],
          ['k' => 'in_progress', 'label' => 'In progress'],
          ['k' => 'samaritans_online', 'label' => 'Samaritans online'],
          ['k' => 'professionals_online', 'label' => 'Professionals online'],
          ['k' => 'responders_busy', 'label' => 'On a job'],
          ['k' => 'stale_fixes', 'label' => 'Signal lost'],
        ] as $s)
          <div class="col-6 col-md-3 col-xl">
            <div class="ug-live-stat">
              <div class="v" data-stat="{{ $s['k'] }}">&mdash;</div>
              <div class="k">{{ $s['label'] }}</div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-7 mb-3">
      <div class="card h-100">
        <div class="card-body">
          @if (!empty($mapKey))
            <div id="ug-live-map" class="ug-live-map"></div>
          @else
            <div class="ug-live-map d-flex align-items-center justify-content-center text-center p-4">
              <div class="text-muted">
                <strong>No map key configured.</strong><br>
                Set <code>map_api_key</code> under Settings &rarr; Third Party to see positions on a map.<br>
                The live list beside this still works.
              </div>
            </div>
          @endif
          <div class="mt-2 small text-muted">
            <span class="ug-dot" style="background:#DC3545"></span>Emergency
            <span class="ug-dot ml-3" style="background:#ED9914"></span>Request
            <span class="ug-dot ml-3" style="background:#2F8F4E"></span>Samaritan
            <span class="ug-dot ml-3" style="background:#2C6FA8"></span>Professional
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5 mb-3">
      <div class="card h-100">
        <div class="card-header"><h5 class="card-title mb-0">Active rescues</h5></div>
        <div class="card-body p-0" style="max-height:460px; overflow:auto">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Request</th><th>Status</th><th class="text-right">Waiting</th><th class="text-right">Offers</th>
              </tr>
            </thead>
            <tbody id="ug-live-rows">
              <tr><td colspan="4" class="text-center text-muted py-4">Loading&hellip;</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('script_2')
<script>
(function () {
  var FEED = "{{ route('admin.urban-goodz.stranded.live-feed') }}";
  var map = null, markers = [];

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
    });
  }

  function clearMarkers() {
    markers.forEach(function (m) { m.setMap(null); });
    markers = [];
  }

  function draw(data) {
    if (!map || !window.google) return;
    clearMarkers();
    var bounds = new google.maps.LatLngBounds(), any = false;

    data.requests.forEach(function (r) {
      if (!r.lat || !r.lng) return;
      var pos = { lat: r.lat, lng: r.lng };
      markers.push(new google.maps.Marker({
        position: pos, map: map,
        title: r.number + ' — ' + r.service,
        icon: { path: google.maps.SymbolPath.CIRCLE, scale: 8,
                fillColor: r.emergency ? '#DC3545' : '#ED9914',
                fillOpacity: .95, strokeColor: '#fff', strokeWeight: 2 }
      }));
      bounds.extend(pos); any = true;
    });

    data.responders.forEach(function (p) {
      if (!p.lat || !p.lng) return;
      var pos = { lat: p.lat, lng: p.lng };
      markers.push(new google.maps.Marker({
        position: pos, map: map,
        title: (p.type === 'samaritan' ? 'Samaritan' : 'Professional')
               + (p.busy ? ' (on a job)' : ' (available)')
               + (p.stale ? ' — signal lost' : ''),
        opacity: p.stale ? 0.4 : 1,
        icon: { path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW, scale: 4.5,
                fillColor: p.type === 'samaritan' ? '#2F8F4E' : '#2C6FA8',
                fillOpacity: p.busy ? .55 : .95, strokeColor: '#fff', strokeWeight: 1.5 }
      }));
      bounds.extend(pos); any = true;
    });

    if (any) { map.fitBounds(bounds); if (map.getZoom() > 13) map.setZoom(13); }
  }

  function render(data) {
    Object.keys(data.summary).forEach(function (k) {
      var el = document.querySelector('[data-stat="' + k + '"]');
      if (el) el.textContent = data.summary[k];
    });

    var body = document.getElementById('ug-live-rows');
    if (!data.requests.length) {
      body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No active rescues right now.</td></tr>';
    } else {
      body.innerHTML = data.requests.map(function (r) {
        return '<tr class="' + (r.emergency ? 'ug-row-emergency' : '') + '">'
          + '<td><strong>' + esc(r.number) + '</strong><br><span class="small text-muted">' + esc(r.service) + '</span></td>'
          + '<td><span class="small">' + esc(r.status.replace(/_/g, ' ')) + '</span>'
          + (r.responder ? '<br><span class="small text-muted">ETA ' + esc(r.responder.eta) + 'm</span>' : '')
          + '</td>'
          + '<td class="text-right">' + (r.waiting_minutes === null ? '—' : esc(r.waiting_minutes) + 'm') + '</td>'
          + '<td class="text-right">' + esc(r.offers) + '</td>'
          + '</tr>';
      }).join('');
    }

    document.getElementById('ug-live-updated').textContent =
      'Updated ' + new Date().toLocaleTimeString();
  }

  function poll() {
    fetch(FEED, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (d) { render(d); draw(d); })
      .catch(function () {
        // A failed poll must not blank the operator's screen -- the previous
        // picture is stale but still more useful than nothing.
        document.getElementById('ug-live-updated').textContent = 'Reconnecting…';
      });
  }

  window.ugStrandedInitMap = function () {
    var el = document.getElementById('ug-live-map');
    if (!el || !window.google) return;
    map = new google.maps.Map(el, { center: { lat: 29.7604, lng: -95.3698 }, zoom: 10 });
    poll();
  };

  poll();
  setInterval(poll, 8000);
})();
</script>
@if (!empty($mapKey))
<script async defer
  src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}&callback=ugStrandedInitMap&v=3.61"></script>
@endif
@endpush
