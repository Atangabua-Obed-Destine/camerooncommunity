{{--
    Global, real-time connection notifier.

    Subscribes the authenticated user to their personal Reverb channel
      tenant.{tid}.user.{uid}
    and reacts to two whisper-broadcast events:
      - .connection.requested  → blue toast with "View / Accept"
      - .connection.accepted   → green celebratory toast with confetti burst

    Bonus flair:
      - WebAudio chime (no asset needed)
      - Native Notification API (asks permission once)
      - Document title pulse "(1) New connection · …"
      - Refreshes the Connections Livewire modal if it's mounted
--}}
@auth
@php
    $tid = app('currentTenant')?->id;
    $uid = auth()->id();
@endphp
@if($tid && $uid)
<div
    x-data="connectionNotifier(@js($uid), @js($tid))"
    x-init="init()"
    class="kc-notifier"
    aria-live="polite"
>
    {{-- Toast stack — newest on top, auto-dismisses after 8s --}}
    <template x-for="t in toasts" :key="t.id">
        <div
            class="kc-toast"
            :class="t.kind === 'accepted' ? 'kc-toast--accepted' : 'kc-toast--requested'"
            x-transition:enter="kc-toast--enter"
            x-transition:enter-start="kc-toast--enter-start"
            x-transition:enter-end="kc-toast--enter-end"
            x-transition:leave="kc-toast--leave"
            x-transition:leave-start="kc-toast--leave-start"
            x-transition:leave-end="kc-toast--leave-end"
        >
            <div class="kc-toast__avatar">
                <template x-if="t.from.avatar">
                    <img :src="'{{ asset('storage') }}/' + t.from.avatar" alt="">
                </template>
                <template x-if="!t.from.avatar">
                    <span x-text="(t.from.username || t.from.name || '?').charAt(0).toUpperCase()"></span>
                </template>
                <span class="kc-toast__badge" x-text="t.kind === 'accepted' ? '✓' : '+'"></span>
            </div>
            <div class="kc-toast__body">
                <div class="kc-toast__title" x-text="t.title"></div>
                <div class="kc-toast__text" x-text="t.text"></div>
                <div class="kc-toast__actions">
                    <button type="button" @click="goTo(t)" class="kc-toast__btn kc-toast__btn--primary" x-text="t.cta"></button>
                    <button type="button" @click="dismiss(t.id)" class="kc-toast__btn kc-toast__btn--ghost">Dismiss</button>
                </div>
            </div>
            <button type="button" @click="dismiss(t.id)" class="kc-toast__close" aria-label="Close">×</button>
        </div>
    </template>

    {{-- Confetti canvas (lazy-shown on accepted) --}}
    <canvas x-ref="confetti" class="kc-confetti" x-show="confettiActive" x-cloak></canvas>
</div>

<style>
    .kc-notifier {
        position: fixed;
        top: 96px;
        right: 16px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
        max-width: 380px;
    }
    .kc-toast {
        pointer-events: auto;
        position: relative;
        display: flex;
        gap: 12px;
        padding: 14px 14px 14px 14px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .18), 0 2px 6px rgba(15,23,42,.06);
        border-left: 4px solid #3b82f6;
        animation: kcSlideIn .35s cubic-bezier(.18,.89,.32,1.28) both;
        overflow: hidden;
    }
    .kc-toast--accepted { border-left-color: #16a34a; }
    .kc-toast::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(120deg, rgba(59,130,246,.08), transparent 60%);
        pointer-events: none;
    }
    .kc-toast--accepted::before { background: linear-gradient(120deg, rgba(22,163,74,.10), transparent 60%); }
    .kc-toast__avatar {
        position: relative;
        flex-shrink: 0;
        width: 44px; height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e0e7ff, #ddd6fe);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        color: #4338ca;
        overflow: hidden;
    }
    .kc-toast__avatar img { width: 100%; height: 100%; object-fit: cover; }
    .kc-toast__badge {
        position: absolute;
        bottom: -2px; right: -2px;
        width: 18px; height: 18px;
        border-radius: 50%;
        background: #3b82f6;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid #fff;
    }
    .kc-toast--accepted .kc-toast__badge { background: #16a34a; }
    .kc-toast__body { flex: 1; min-width: 0; }
    .kc-toast__title { font-size: 14px; font-weight: 700; color: #0f172a; }
    .kc-toast__text { font-size: 13px; color: #475569; margin-top: 2px; }
    .kc-toast__actions { display: flex; gap: 8px; margin-top: 8px; }
    .kc-toast__btn {
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: transform .1s, background .15s;
    }
    .kc-toast__btn:active { transform: scale(.97); }
    .kc-toast__btn--primary {
        background: #3b82f6;
        color: #fff;
    }
    .kc-toast--accepted .kc-toast__btn--primary { background: #16a34a; }
    .kc-toast__btn--primary:hover { filter: brightness(1.05); }
    .kc-toast__btn--ghost {
        background: transparent;
        color: #64748b;
    }
    .kc-toast__btn--ghost:hover { background: #f1f5f9; }
    .kc-toast__close {
        position: absolute;
        top: 6px; right: 8px;
        background: transparent;
        border: 0;
        font-size: 18px;
        line-height: 1;
        color: #94a3b8;
        cursor: pointer;
    }
    .kc-toast__close:hover { color: #475569; }
    .kc-confetti {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 9998;
    }
    @keyframes kcSlideIn {
        from { opacity: 0; transform: translateX(40px) scale(.95); }
        to   { opacity: 1; transform: translateX(0) scale(1); }
    }
    @media (max-width: 640px) {
        .kc-notifier { top: 76px; right: 8px; left: 8px; max-width: none; }
    }
</style>

<script>
    function connectionNotifier(userId, tenantId) {
        return {
            toasts: [],
            confettiActive: false,
            _seq: 0,
            _origTitle: document.title,
            _audioCtx: null,

            init() {
                if (!window.Echo || !tenantId || !userId) return;

                // Ask for native notification permission once, lazily.
                if ('Notification' in window && Notification.permission === 'default') {
                    // Defer so it doesn't fire on page load — wait until first user click.
                    const askOnce = () => {
                        try { Notification.requestPermission(); } catch (e) {}
                        document.removeEventListener('click', askOnce);
                    };
                    document.addEventListener('click', askOnce, { once: true });
                }

                const channel = `tenant.${tenantId}.user.${userId}`;
                this._channel = window.Echo.channel(channel);

                this._channel.listen('.connection.requested', (data) => {
                    this.push({
                        kind: 'requested',
                        from: data.from || {},
                        title: 'New connection request',
                        text: (data.from?.username || data.from?.name || 'Someone') + ' wants to connect with you.',
                        cta: 'View request',
                        action_url: '{{ route('yard') }}?open=connections&tab=requests',
                    });
                    this.chime('request');
                    this.nativeNotify(
                        'New connection request',
                        (data.from?.username || data.from?.name || 'Someone') + ' wants to connect.'
                    );
                    // Tell the Connections modal to refresh if it's mounted/open.
                    try { window.Livewire?.dispatch('connection-incoming', { userId: data.from?.id }); } catch(_) {}
                });

                this._channel.listen('.connection.accepted', (data) => {
                    this.push({
                        kind: 'accepted',
                        from: data.from || {},
                        title: '🎉 Connection accepted',
                        text: (data.from?.username || data.from?.name || 'Someone') + ' accepted your request. Say hello!',
                        cta: 'Open chat',
                        action_url: '{{ route('yard') }}',
                        partnerId: data.from?.id,
                    });
                    this.chime('accepted');
                    this.fireConfetti();
                    this.nativeNotify(
                        '🎉 Connection accepted',
                        (data.from?.username || data.from?.name || 'Someone') + ' accepted your request.'
                    );
                    try { window.Livewire?.dispatch('connection-updated', { userId: data.from?.id, state: 'connected' }); } catch(_) {}
                });
            },

            push(payload) {
                const id = ++this._seq;
                this.toasts.unshift({ id, ...payload });
                this.pulseTitle(payload.title);
                // Auto-dismiss after 8s
                setTimeout(() => this.dismiss(id), 8000);
            },

            dismiss(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
                if (this.toasts.length === 0) document.title = this._origTitle;
            },

            goTo(t) {
                if (t.kind === 'accepted' && t.partnerId) {
                    // Try in-app DM open without a full reload first.
                    try {
                        window.dispatchEvent(new CustomEvent('open-dm', { detail: { userId: t.partnerId } }));
                        this.dismiss(t.id);
                        return;
                    } catch (_) {}
                }
                window.location.href = t.action_url;
            },

            pulseTitle(title) {
                let on = false;
                if (this._titleTimer) clearInterval(this._titleTimer);
                this._titleTimer = setInterval(() => {
                    document.title = on ? this._origTitle : `🔔 ${title}`;
                    on = !on;
                }, 1200);
                setTimeout(() => {
                    clearInterval(this._titleTimer);
                    document.title = this._origTitle;
                }, 12000);
            },

            chime(kind) {
                try {
                    if (!this._audioCtx) {
                        const Ctx = window.AudioContext || window.webkitAudioContext;
                        if (!Ctx) return;
                        this._audioCtx = new Ctx();
                    }
                    const ctx = this._audioCtx;
                    if (ctx.state === 'suspended') ctx.resume();

                    // Simple two-note pleasant chime. Different intervals per kind.
                    const notes = kind === 'accepted' ? [523.25, 659.25, 783.99] : [523.25, 659.25];
                    const now = ctx.currentTime;
                    notes.forEach((freq, i) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        gain.gain.setValueAtTime(0, now + i * 0.12);
                        gain.gain.linearRampToValueAtTime(0.18, now + i * 0.12 + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.12 + 0.35);
                        osc.connect(gain).connect(ctx.destination);
                        osc.start(now + i * 0.12);
                        osc.stop(now + i * 0.12 + 0.4);
                    });
                } catch (_) {}
            },

            nativeNotify(title, body) {
                try {
                    if (document.visibilityState === 'visible') return;
                    if (!('Notification' in window) || Notification.permission !== 'granted') return;
                    new Notification(title, { body, silent: true });
                } catch (_) {}
            },

            fireConfetti() {
                this.confettiActive = true;
                this.$nextTick(() => {
                    const canvas = this.$refs.confetti;
                    if (!canvas) { this.confettiActive = false; return; }
                    const ctx = canvas.getContext('2d');
                    const W = canvas.width = window.innerWidth;
                    const H = canvas.height = window.innerHeight;
                    const colors = ['#16a34a', '#facc15', '#ef4444', '#3b82f6', '#a855f7'];
                    const pieces = Array.from({ length: 90 }, () => ({
                        x: W / 2 + (Math.random() - 0.5) * 80,
                        y: H * 0.2 + (Math.random() - 0.5) * 40,
                        vx: (Math.random() - 0.5) * 8,
                        vy: -Math.random() * 8 - 4,
                        g: 0.25 + Math.random() * 0.15,
                        size: 4 + Math.random() * 5,
                        rot: Math.random() * Math.PI,
                        vr: (Math.random() - 0.5) * 0.3,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        life: 0,
                    }));
                    let raf;
                    const start = performance.now();
                    const tick = (t) => {
                        ctx.clearRect(0, 0, W, H);
                        pieces.forEach(p => {
                            p.vy += p.g;
                            p.x += p.vx;
                            p.y += p.vy;
                            p.rot += p.vr;
                            ctx.save();
                            ctx.translate(p.x, p.y);
                            ctx.rotate(p.rot);
                            ctx.fillStyle = p.color;
                            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                            ctx.restore();
                        });
                        if (t - start < 2200) {
                            raf = requestAnimationFrame(tick);
                        } else {
                            cancelAnimationFrame(raf);
                            ctx.clearRect(0, 0, W, H);
                            this.confettiActive = false;
                        }
                    };
                    raf = requestAnimationFrame(tick);
                });
            },
        };
    }
</script>
@endif
@endauth
