/*
 * Makes a persona portrait feel alive: she breathes and sways while idle, her expression follows the
 * conversation, and while she speaks her mouth frames and posture follow the voice.
 *
 * Works with whatever audio the page has: for an <audio> element it reads the real loudness (WebAudio
 * analyser) so the mouth moves with the actual voice; for the browser's speech synthesis (which exposes
 * no audio) it follows the engine's word-boundary events with a natural cadence between them.
 *
 * Frames are the persona's own photographed expressions: <assetBase><name>.png
 * (neutral, speaking, thinking, listening, concerned, happy, laughing, confident, celebrating).
 */
(function (global) {
    'use strict';

    var FRAMES = ['neutral', 'speaking', 'thinking', 'listening', 'concerned', 'happy', 'laughing', 'confident', 'celebrating'];

    function injectStyles() {
        if (document.getElementById('ug-persona-animator-css')) return;
        var css = document.createElement('style');
        css.id = 'ug-persona-animator-css';
        css.textContent =
            '@keyframes ugBreathe{0%,100%{transform:translateY(0) scale(1) rotate(0deg)}' +
            '25%{transform:translateY(-1.5px) scale(1.012) rotate(.35deg)}' +
            '50%{transform:translateY(-2.5px) scale(1.02) rotate(0deg)}' +
            '75%{transform:translateY(-1px) scale(1.01) rotate(-.35deg)}}' +
            '@keyframes ugGlow{0%,100%{box-shadow:0 0 0 0 rgba(140,158,255,.0)}50%{box-shadow:0 0 22px 4px rgba(140,158,255,.45)}}' +
            '.ug-anim-idle{animation:ugBreathe 5.5s ease-in-out infinite;transform-origin:50% 90%;will-change:transform}' +
            '.ug-anim-thinking{animation:ugBreathe 3.2s ease-in-out infinite;transform-origin:50% 90%}' +
            '.ug-anim-host-thinking{animation:ugGlow 1.6s ease-in-out infinite}' +
            '.ug-anim-host-speaking{box-shadow:0 0 18px 3px rgba(140,158,255,.5)!important}';
        document.head.appendChild(css);
    }

    function PersonaAnimator(opts) {
        injectStyles();
        this.img = opts.img;
        this.host = opts.host || (opts.img && opts.img.parentElement);
        this.assetBase = opts.assetBase;
        this.current = 'neutral';
        this.speaking = false;
        this._timer = null;
        this._mouthOpen = false;
        this._swapToken = 0;
        this._audioCtx = null;
        this._analyser = null;
        this._raf = null;
        this.events = []; // test/diagnostic trail of frame changes
        this.preload();
        this.img.classList.add('ug-anim-idle');
    }

    PersonaAnimator.prototype.url = function (name) { return this.assetBase + name + '.png'; };

    PersonaAnimator.prototype.preload = function () {
        var self = this;
        FRAMES.forEach(function (n) { var i = new Image(); i.src = self.url(n); });
    };

    PersonaAnimator.prototype._show = function (name, fast) {
        if (name === this.current) return;
        this.current = name;
        this.events.push(name);
        var img = this.img, token = ++this._swapToken, url = this.url(name);
        if (fast) { img.src = url; return; }   // mouth frames: swap instantly so lips keep up with speech
        img.style.opacity = '0.0';
        var self = this;
        setTimeout(function () {
            if (token !== self._swapToken) return;
            img.src = url;
            img.style.opacity = '1';
        }, 110);
    };

    /** Conversation-driven expression (thinking, listening, concerned, happy ...). */
    PersonaAnimator.prototype.setExpression = function (name) {
        if (this.speaking && name !== 'concerned') return;     // while speaking, mouth frames own the face
        this.img.classList.toggle('ug-anim-thinking', name === 'thinking');
        this.img.classList.toggle('ug-anim-idle', name !== 'thinking');
        if (this.host) this.host.classList.toggle('ug-anim-host-thinking', name === 'thinking' || name === 'listening');
        this._show(name, false);
    };

    /** Start talking. Pass an <audio> element for real loudness-driven lips, or nothing for cadence-driven. */
    PersonaAnimator.prototype.startSpeaking = function (audioEl) {
        var self = this;
        this.stopSpeaking(true);
        this.speaking = true;
        this.img.classList.remove('ug-anim-thinking');
        this.img.classList.add('ug-anim-idle');
        if (this.host) { this.host.classList.remove('ug-anim-host-thinking'); this.host.classList.add('ug-anim-host-speaking'); }

        if (audioEl && this._tapAudio(audioEl)) {
            this._loopAnalyser();
            return;
        }
        // Cadence: syllable-length open/close, slightly irregular so it never looks mechanical.
        (function tick() {
            if (!self.speaking) return;
            self._mouthOpen = !self._mouthOpen;
            self._show(self._mouthOpen ? 'speaking' : 'neutral', true);
            self.img.style.transform = self._mouthOpen ? 'scale(1.025)' : '';
            self._timer = setTimeout(tick, 110 + Math.random() * 130);
        })();
    };

    /** The speech engine reported a word boundary: open the mouth on it (keeps lips tied to real words). */
    PersonaAnimator.prototype.pulse = function () {
        if (!this.speaking) return;
        this._mouthOpen = true;
        this._show('speaking', true);
    };

    PersonaAnimator.prototype._tapAudio = function (el) {
        try {
            var Ctx = global.AudioContext || global.webkitAudioContext;
            if (!Ctx) return false;
            this._audioCtx = this._audioCtx || new Ctx();
            if (!el.__ugSource) {
                el.__ugSource = this._audioCtx.createMediaElementSource(el);
            }
            this._analyser = this._audioCtx.createAnalyser();
            this._analyser.fftSize = 256;
            el.__ugSource.connect(this._analyser);
            this._analyser.connect(this._audioCtx.destination);   // keep it audible
            if (this._audioCtx.state === 'suspended') this._audioCtx.resume();
            return true;
        } catch (e) {
            return false;
        }
    };

    PersonaAnimator.prototype._loopAnalyser = function () {
        var self = this, data = new Uint8Array(this._analyser.frequencyBinCount);
        (function frame() {
            if (!self.speaking || !self._analyser) return;
            self._analyser.getByteFrequencyData(data);
            var sum = 0; for (var i = 2; i < 40; i++) sum += data[i];
            var level = sum / (38 * 255);                       // 0..1 voice-band loudness
            var open = level > 0.07;
            self._show(open ? 'speaking' : 'neutral', true);
            self.img.style.transform = open ? 'scale(' + (1 + Math.min(level, 0.5) * 0.06) + ')' : '';
            self._raf = global.requestAnimationFrame(frame);
        })();
    };

    PersonaAnimator.prototype.stopSpeaking = function (quiet) {
        this.speaking = false;
        clearTimeout(this._timer);
        if (this._raf) global.cancelAnimationFrame(this._raf);
        this._raf = null;
        if (this._analyser) { try { this._analyser.disconnect(); } catch (e) {} this._analyser = null; }
        this.img.style.transform = '';
        if (this.host) this.host.classList.remove('ug-anim-host-speaking');
        if (!quiet) { this.current = ''; this._show('neutral', false); }
    };

    global.UgPersonaAnimator = PersonaAnimator;
})(window);
