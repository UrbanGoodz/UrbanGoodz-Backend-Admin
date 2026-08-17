{{-- Urban Goodz Digital Human Executive Host Component for Monique (chief_of_staff
     persona -- display name swapped from "Skylar"; internal JS ids/function names
     below still say "skylar" for wiring continuity, that's cosmetic only). --}}
<div class="ug-digital-human-card" data-persona="chief_of_staff" style="background: linear-gradient(135deg, #1F3A5F 0%, #0F1E33 100%); border-radius: 16px; padding: 0; color: #fff; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(31,58,95,0.3);">
    <div class="ug-ambient-overlay" style="position: absolute; top: -50px; right: -50px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(140,158,255,0.2) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>

    <div class="row align-items-center no-gutters" style="padding: 24px;">
        <!-- Real photographed avatar -- expression-swapped by conversation state, not a monogram/placeholder -->
        <div class="col-md-3 text-center mb-3 mb-md-0">
            <div class="ug-avatar-host-container" style="position: relative; width: 140px; height: 140px; margin: 0 auto; border-radius: 16px; border: 2px solid rgba(140,158,255,0.5); overflow: hidden; background: rgba(255,255,255,0.05);">
                <img id="skylar-avatar-img"
                     src="{{ asset('assets/image/personas/skylar_face_neutral.png') }}"
                     alt="{{ $persona['display_name'] ?? 'Monique' }}"
                     style="width: 100%; height: 100%; object-fit: cover; transition: opacity 180ms ease;">
                <span class="badge badge-success ug-viseme-badge" style="position: absolute; bottom: 6px; right: 6px; font-size: 10px; border: 2px solid #0F1E33;">
                    <i class="tio-voice"></i> Live
                </span>
            </div>
        </div>

        <div class="col-md-9">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="badge badge-soft-light text-uppercase tracking-wider" style="font-size: 11px; letter-spacing: 1px; color: #8C9EFF; background: rgba(140,158,255,0.15);">
                        {{ $persona['role_title'] ?? 'Chief of Staff' }}
                    </span>
                    <h3 class="text-white mb-1 font-weight-bold mt-1">{{ $persona['display_name'] ?? 'Monique' }}</h3>
                </div>
            </div>

            @if($narration['available'] ?? false)
                <p id="skylar-narrative-text" class="mt-2 text-white-90" style="font-size: 15px; line-height: 1.6;">
                    {{ $narration['text'] }}
                </p>
                <small class="text-white-50">{{ translate('Briefed from live records at') }} {{ $narration['generated_at'] ?? '' }}</small>
            @else
                <p id="skylar-narrative-text" class="mt-2 text-white-50" style="font-size: 15px; line-height: 1.6;">
                    {{ translate('The spoken brief is unavailable right now. The figures below are live; nothing has been summarised on your behalf.') }}
                </p>
                <small class="text-white-50">
                    {{ translate('Reason') }}: <code class="text-white-50">{{ $narration['reason'] ?? 'unknown' }}</code>
                </small>
            @endif

            <div class="d-flex align-items-center mt-3 gap-2">
                <button type="button" id="skylar-speak-btn" class="btn btn-sm btn-light font-weight-semibold mr-2" onclick="triggerSkylarSpeech()">
                    <i class="tio-volume-up mr-1"></i> Hear Executive Brief
                </button>
                <small id="skylar-speak-status" class="text-white-50"></small>
            </div>
        </div>
    </div>

    <!-- Real conversation with Monique -->
    <div style="background: rgba(0,0,0,0.25); border-top: 1px solid rgba(255,255,255,0.08); padding: 16px 24px;">
        <div id="skylar-chat-log" style="max-height: 220px; overflow-y: auto; margin-bottom: 10px; display: none;"></div>

        <div class="d-flex align-items-center" style="gap: 8px;">
            <input id="skylar-chat-input" type="text" placeholder="Ask Monique about today's business..."
                   style="flex: 1; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #fff; padding: 10px 14px; font-size: 14px;"
                   onkeydown="if(event.key==='Enter'){askSkylar();}">
            <button type="button" id="skylar-mic-btn" class="btn btn-sm" style="background: rgba(255,255,255,0.1); color: #fff; border-radius: 10px;" onclick="toggleSkylarListening()" title="Talk to Monique">
                <i class="tio-microphone"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light font-weight-semibold" onclick="askSkylar()">
                {{ translate('Ask') }}
            </button>
        </div>
        <small id="skylar-chat-status" class="text-white-50 d-block mt-1"></small>
    </div>
</div>

<script>
(function () {
    const sessionId = 'skylar-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    const chatLog = document.getElementById('skylar-chat-log');
    const input = document.getElementById('skylar-chat-input');
    const statusEl = document.getElementById('skylar-chat-status');
    const avatarImg = document.getElementById('skylar-avatar-img');
    const assetBase = '{{ asset("assets/image/personas/skylar_face_") }}';

    function setExpression(bucket) {
        avatarImg.style.opacity = '0';
        setTimeout(function () {
            avatarImg.src = assetBase + bucket + '.png';
            avatarImg.style.opacity = '1';
        }, 120);
    }

    function appendMessage(role, text) {
        chatLog.style.display = 'block';
        const bubble = document.createElement('div');
        bubble.style.margin = '6px 0';
        bubble.style.fontSize = '13.5px';
        bubble.style.lineHeight = '1.4';
        bubble.style.color = role === 'user' ? '#8C9EFF' : '#fff';
        bubble.innerHTML = '<strong>' + (role === 'user' ? 'You' : 'Monique') + ':</strong> ' + text.replace(/</g, '&lt;');
        chatLog.appendChild(bubble);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    window.askSkylar = function (overrideText) {
        const query = (overrideText || input.value || '').trim();
        if (!query) return;
        input.value = '';
        appendMessage('user', query);
        setExpression('thinking');
        statusEl.innerText = 'Monique is thinking...';

        fetch('{{ route("admin.urban-goodz.ai-chief-of-staff.chat") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ query: query, session_id: sessionId }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                const text = (data.data && data.data.response) || 'Monique could not process that request.';
                appendMessage('assistant', text);
                setExpression(data.data && data.data.flagged_as_urgent ? 'concerned' : 'speaking');
                statusEl.innerText = '';
                setTimeout(function () { setExpression('neutral'); }, 2200);
            })
            .catch(function () {
                appendMessage('assistant', "Couldn't reach the AI service. No action was taken.");
                setExpression('concerned');
                statusEl.innerText = '';
            });
    };

    // Real Web Speech API voice input -- no server dependency, browser-native.
    let recognition = null;
    let listening = false;
    const SpeechRecognitionImpl = window.SpeechRecognition || window.webkitSpeechRecognition;
    const micBtn = document.getElementById('skylar-mic-btn');

    if (SpeechRecognitionImpl) {
        recognition = new SpeechRecognitionImpl();
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        recognition.onresult = function (event) {
            let transcript = '';
            for (let i = 0; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            input.value = transcript;
        };
        recognition.onend = function () {
            listening = false;
            micBtn.style.background = 'rgba(255,255,255,0.1)';
            if (input.value.trim()) window.askSkylar();
        };
        recognition.onerror = function () {
            listening = false;
            micBtn.style.background = 'rgba(255,255,255,0.1)';
            statusEl.innerText = "Couldn't hear that -- try typing instead.";
        };
    }

    window.toggleSkylarListening = function () {
        if (!recognition) {
            statusEl.innerText = 'Voice input is not supported in this browser.';
            return;
        }
        if (listening) {
            recognition.stop();
            return;
        }
        setExpression('listening');
        statusEl.innerText = 'Listening...';
        micBtn.style.background = '#E5484D';
        listening = true;
        recognition.start();
    };
})();

var __skylarBriefAudio = null;

function triggerSkylarSpeech() {
    const btn = document.getElementById('skylar-speak-btn');
    const statusEl = document.getElementById('skylar-speak-status');
    const text = (document.getElementById('skylar-narrative-text').innerText || '').trim();

    console.log('[Monique TTS] brief generated:', text.length > 0, '(' + text.length + ' chars)');

    if (!text) {
        statusEl.innerText = 'No brief text to speak yet.';
        return;
    }

    // Reuse an already-fetched clip instead of re-billing ElevenLabs on every click.
    if (__skylarBriefAudio) {
        console.log('[Monique TTS] audio already loaded, replaying');
        __skylarBriefAudio.currentTime = 0;
        __skylarBriefAudio.play().catch(function (err) {
            console.error('[Monique TTS] audio replay failed:', err);
        });
        return;
    }

    btn.disabled = true;
    statusEl.innerText = 'Generating voice...';
    console.log('[Monique TTS] TTS requested');

    fetch('{{ route("admin.urban-goodz.ai-chief-of-staff.speak") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'audio/mpeg, application/json',
        },
        body: JSON.stringify({ text: text }),
    })
        .then(function (res) {
            if (!res.ok) {
                return res.json().catch(function () { return {}; }).then(function (body) {
                    throw new Error(body.message || ('Voice request failed (' + res.status + ')'));
                });
            }
            console.log('[Monique TTS] TTS completed');
            return res.blob();
        })
        .then(function (blob) {
            const url = URL.createObjectURL(blob);
            const audio = new Audio(url);
            __skylarBriefAudio = audio;
            console.log('[Monique TTS] audio loaded');

            audio.addEventListener('play', function () { console.log('[Monique TTS] audio started'); });
            audio.addEventListener('ended', function () { console.log('[Monique TTS] audio completed'); });
            audio.addEventListener('error', function (e) { console.error('[Monique TTS] audio playback error:', e); });

            btn.disabled = false;
            statusEl.innerText = '';
            return audio.play();
        })
        .catch(function (err) {
            console.error('[Monique TTS] audio failed:', err);
            btn.disabled = false;
            statusEl.innerText = err.message || "Couldn't generate voice right now.";
        });
}
</script>
