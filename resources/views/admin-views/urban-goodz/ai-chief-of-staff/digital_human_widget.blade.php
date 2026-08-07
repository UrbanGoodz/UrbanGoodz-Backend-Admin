{{-- Urban Goodz Digital Human Executive Host Component for Skylar --}}
<div class="ug-digital-human-card" data-persona="chief_of_staff" style="background: linear-gradient(135deg, #1F3A5F 0%, #0F1E33 100%); border-radius: 16px; padding: 24px; color: #fff; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(31,58,95,0.3);">
    <!-- Dynamic Ambient Light Background Overlay -->
    <div class="ug-ambient-overlay" style="position: absolute; top: -50px; right: -50px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(140,158,255,0.2) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>

    <div class="row align-items-center">
        <!-- Avatar Rive / Layer Container -->
        <div class="col-md-3 text-center mb-3 mb-md-0">
            <div class="ug-avatar-host-container" style="position: relative; width: 120px; height: 120px; margin: 0 auto; border-radius: 50%; border: 3px solid #8C9EFF; padding: 4px; background: rgba(255,255,255,0.05);">
                @if(!empty($persona['avatar']))
                    <img id="skylar-avatar-img" class="img-fluid rounded-circle" src="{{ asset($persona['avatar']) }}" alt="{{ $persona['display_name'] ?? 'Skylar' }}" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                    <div class="d-flex align-items-center justify-content-center h-100 font-weight-bold text-white display-4" style="border-radius: 50%; background: #1F3A5F;">
                        {{ $persona['initials'] ?? 'S' }}
                    </div>
                @endif
                <!-- Viseme Lip Sync Indicator Badge -->
                <span class="badge badge-success ug-viseme-badge" style="position: absolute; bottom: 0; right: 0; font-size: 10px; border: 2px solid #0F1E33;">
                    <i class="tio-voice"></i> Live Host
                </span>
            </div>
        </div>

        <!-- Executive Narrative & Dynamic State -->
        <div class="col-md-9">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="badge badge-soft-light text-uppercase tracking-wider" style="font-size: 11px; letter-spacing: 1px; color: #8C9EFF; background: rgba(140,158,255,0.15);">
                        {{ $persona['role_title'] ?? 'Chief of Staff' }}
                    </span>
                    <h3 class="text-white mb-1 font-weight-bold mt-1">{{ $persona['display_name'] ?? 'Skylar' }}</h3>
                </div>
                <div class="ug-state-pill" style="background: rgba(255,255,255,0.1); border-radius: 20px; padding: 4px 12px; font-size: 12px; color: #E8EDF4;">
                    <i class="tio-brightness mr-1" style="color: #D4AF37;"></i> State: <span id="skylar-mood-label">Poised Executive</span>
                </div>
            </div>

            <p id="skylar-narrative-text" class="mt-2 text-white-90" style="font-size: 15px; line-height: 1.6;">
                {{ $narration['text'] ?? "Good morning, D'Andre. I've reviewed overnight activity across all Houston routes and dispatch boards." }}
            </p>

            <div class="d-flex align-items-center mt-3 gap-2">
                <button type="button" class="btn btn-sm btn-light font-weight-semibold mr-2" onclick="triggerSkylarSpeech()">
                    <i class="tio-volume-up mr-1"></i> Hear Executive Brief
                </button>
                <span class="text-white-50 small">Environment: <strong>Executive Operations Center</strong></span>
            </div>
        </div>
    </div>
</div>

<script>
function triggerSkylarSpeech() {
    fetch('{{ url("api/v1/urban-goodz/cross-app/ai/digital-human/state") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            persona: 'chief_of_staff',
            text: document.getElementById('skylar-narrative-text').innerText,
            domain: 'finance',
            event_type: 'executive_brief'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const dh = data.data.digital_human;
            document.getElementById('skylar-mood-label').innerText = dh.mood;
            console.log('Digital Human State Payload loaded:', data.data);
            alert('Skylar Executive Brief voice stream initialized with ' + data.data.playback.viseme_timeline.length + ' viseme frames.');
        }
    })
    .catch(err => console.error('Digital Human API Error:', err));
}
</script>
