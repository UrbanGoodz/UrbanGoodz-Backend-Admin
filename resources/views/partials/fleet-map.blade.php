{{-- Live fleet map. $dataUrl: JSON endpoint. $mapKey: Google Maps key (business settings). Refreshes every 15 seconds. --}}
<div class="card mb-3">
    <div class="card-body p-0">
        <div class="row no-gutters">
            <div class="col-lg-9">
                <div id="fleet-map" style="height: 70vh; min-height: 420px; background:#eef1f4;"></div>
            </div>
            <div class="col-lg-3 border-left" style="max-height:70vh; overflow:auto;">
                <div class="p-3 border-bottom">
                    <strong id="fleet-summary">Loading…</strong>
                    <div class="small text-muted" id="fleet-updated"></div>
                </div>
                <div id="fleet-list"></div>
            </div>
        </div>
    </div>
</div>
<div class="small text-muted mb-4">
    <span style="color:#1e8e3e">●</span> On a route &nbsp;
    <span style="color:#1a73e8">●</span> Available (live location) &nbsp;
    <span style="color:#f9ab00">●</span> No recent signal (saved location) &nbsp;
    <span style="color:#9aa0a6">●</span> Offline
</div>

@push('script_2')
<script>
    (function () {
        var DATA_URL = @json($dataUrl);
        var COLORS = {on_route: '#1e8e3e', available: '#1a73e8', no_signal: '#f9ab00', offline: '#9aa0a6'};
        var LABELS = {on_route: 'On a route', available: 'Available', no_signal: 'No recent signal', offline: 'Offline'};
        var map, markers = {}, firstFit = true;

        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        window.initFleetMap = function () {
            map = new google.maps.Map(document.getElementById('fleet-map'), {
                center: {lat: 29.76, lng: -95.37}, zoom: 10, streetViewControl: false, mapTypeControl: false
            });
            refresh();
            setInterval(refresh, 15000);
        };

        function pin(color) {
            return {path: google.maps.SymbolPath.CIRCLE, scale: 9, fillColor: color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2};
        }

        function refresh() {
            fetch(DATA_URL, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { document.getElementById('fleet-updated').textContent = 'Could not refresh. Retrying…'; });
        }

        function render(data) {
            var drivers = data.drivers || [], bounds = new google.maps.LatLngBounds(), seen = {}, html = '';
            var counts = {on_route: 0, available: 0, no_signal: 0, offline: 0};

            drivers.forEach(function (d) {
                counts[d.state] = (counts[d.state] || 0) + 1;
                var color = COLORS[d.state] || '#9aa0a6';
                if (d.lat != null && d.lng != null) {
                    var pos = {lat: d.lat, lng: d.lng};
                    seen[d.id] = true;
                    bounds.extend(pos);
                    if (!markers[d.id]) {
                        markers[d.id] = new google.maps.Marker({map: map, position: pos, icon: pin(color), title: d.name});
                        markers[d.id].addListener('click', function () { focus(d.id); });
                    } else {
                        markers[d.id].setPosition(pos);
                        markers[d.id].setIcon(pin(color));
                    }
                }
                var route = d.route ? '<div class="small">' + esc(d.route.name || 'Route') + ' · ' + d.route.completed_packages + '/' + d.route.total_packages + ' delivered</div>' : '';
                var age = d.position_age_minutes != null ? (d.position_source === 'live' ? d.position_age_minutes + ' min ago' : 'saved location') : 'no location';
                html += '<div class="p-3 border-bottom" style="cursor:pointer" data-id="' + d.id + '">'
                    + '<div><span style="color:' + color + '">●</span> <strong>' + esc(d.name) + '</strong></div>'
                    + '<div class="small text-muted">' + LABELS[d.state] + ' · ' + age + '</div>' + route
                    + (d.phone ? '<div class="small"><a href="tel:' + esc(d.phone) + '">' + esc(d.phone) + '</a></div>' : '') + '</div>';
            });

            Object.keys(markers).forEach(function (id) { if (!seen[id]) { markers[id].setMap(null); delete markers[id]; } });
            document.getElementById('fleet-list').innerHTML = html || '<div class="p-3 text-muted">No drivers to show.</div>';
            document.getElementById('fleet-summary').textContent = drivers.length + ' drivers · ' + counts.on_route + ' on a route · ' + counts.available + ' available';
            document.getElementById('fleet-updated').textContent = 'Updated ' + new Date(data.generated_at).toLocaleTimeString();
            document.querySelectorAll('#fleet-list [data-id]').forEach(function (el) { el.onclick = function () { focus(el.getAttribute('data-id')); }; });
            if (firstFit && !bounds.isEmpty()) { map.fitBounds(bounds); if (map.getZoom() > 14) { map.setZoom(14); } firstFit = false; }
        }

        function focus(id) {
            var m = markers[id];
            if (m) { map.panTo(m.getPosition()); map.setZoom(Math.max(map.getZoom(), 14)); }
        }
    })();
</script>
@if($mapKey)
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}&callback=initFleetMap"></script>
@else
<script>document.getElementById('fleet-map').innerHTML = '<div class="p-5 text-center text-muted">The map key is not set. Add it under Business Settings, then Third Party, then Map API.</div>'; document.getElementById('fleet-summary').textContent = '';</script>
@endif
@endpush
