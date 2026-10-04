import './bootstrap';
import './call-engine';
// Reference-counted Echo channels. The chat and the call engine share the
// room channel, and Echo.leave() destroys a channel for everyone holding it.
import './realtime-channels';
// Registers the x-overlay directive: the back button closes popups.
import './overlay-history';
// The Yard chat room's Alpine components. Bundled rather than inlined in the
// Blade view: Livewire re-sends a component's HTML on every round trip, and
// this is 66 KB of it.
import './yard-chat';

// Livewire v4 bundles Alpine + @alpinejs/persist internally.
// We use alpine:init to register stores BEFORE Alpine.start().
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // Global language store — persisted to localStorage
    Alpine.store('lang', {
        current: Alpine.$persist('en').as('cc_lang'),

        get isEn() { return this.current === 'en'; },
        get isFr() { return this.current === 'fr'; },

        toggle() {
            this.current = this.current === 'en' ? 'fr' : 'en';
            document.documentElement.lang = this.current;
            if (window.Livewire) {
                window.Livewire.dispatch('language-changed', { lang: this.current });
            }
            fetch('/api/language', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ lang: this.current }),
            }).catch(() => {});
        },

        t(en, fr) {
            return this.current === 'fr' ? (fr || en) : en;
        }
    });

    /**
     * Check photos before they are uploaded, and say what is wrong.
     *
     * The server rejects anything over 8 MB or not an image, but by then the
     * file has been sent over a phone connection and the failure comes back as
     * a validation error with no obvious cause. Checking here turns that into
     * an immediate, specific message naming the file.
     *
     * Lives in Alpine's registry rather than in the Blade view so both the
     * empty-state picker and the grid's "+" use the same copy of it.
     */
    Alpine.data('photoPicker', (messages = {}) => ({
        maxBytes: 8 * 1024 * 1024,

        check(event) {
            const files = Array.from(event.target.files || []);

            for (const file of files) {
                if (file.size > this.maxBytes) {
                    const mb = (file.size / 1024 / 1024).toFixed(1);
                    window.alert(`${messages.tooBig ?? 'This photo is too large'}\n\n${file.name} — ${mb} MB`);
                    event.target.value = '';
                    return;
                }

                if (! file.type.startsWith('image/')) {
                    window.alert(`${messages.notImage ?? 'That file is not an image'}\n\n${file.name}`);
                    event.target.value = '';
                    return;
                }
            }
        },

        failed() {
            window.alert(messages.failed ?? 'The upload did not go through. Please try again.');
        },
    }));

    // "last seen 5 min ago" under a DM partner's name.
    //
    // Registered here rather than in the chat view: a component-local function
    // can be outrun by a Livewire morph that re-inserts the element without
    // re-running the script, and the span then fails with "label is not
    // defined". Alpine's registry is available before any component mounts.
    Alpine.data('lastSeen', (isoDate) => ({
        label: '',
        _interval: null,

        init() {
            this.refresh(isoDate);
            // Keeps "2 min ago" honest while the chat stays open.
            this._interval = setInterval(() => this.refresh(isoDate), 30000);
        },

        destroy() {
            if (this._interval) clearInterval(this._interval);
        },

        refresh(iso) {
            const date = new Date(iso);
            if (isNaN(date.getTime())) { this.label = ''; return; }

            const now = new Date();
            const mins = Math.floor((now - date) / 60000);
            const hours = Math.floor((now - date) / 3600000);
            const days = Math.floor((now - date) / 86400000);
            const isEn = (this.$store?.lang?.current ?? 'en') === 'en';
            const time = () => date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            if (mins < 1) {
                this.label = isEn ? 'last seen just now' : 'vu il y a un instant';
            } else if (mins < 60) {
                this.label = isEn ? `last seen ${mins} min ago` : `vu il y a ${mins} min`;
            } else if (hours < 24 && date.getDate() === now.getDate()) {
                this.label = isEn ? `last seen today at ${time()}` : `vu aujourd'hui à ${time()}`;
            } else if (days < 2) {
                this.label = isEn ? `last seen yesterday at ${time()}` : `vu hier à ${time()}`;
            } else {
                const d = date.toLocaleDateString([], { day: 'numeric', month: 'short' });
                this.label = isEn ? `last seen ${d} at ${time()}` : `vu le ${d} à ${time()}`;
            }
        },
    }));

    // Live message-status store for WhatsApp-style ticks.
    // Keyed by message id → 'sending' | 'sent' | 'delivered' | 'read'.
    Alpine.store('msgStatus', {});
});

// IntersectionObserver for scroll animations
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Count-up animation for number elements
                entry.target.querySelectorAll('[data-count-to]').forEach(el => {
                    const target = parseInt(el.dataset.countTo);
                    const duration = parseInt(el.dataset.countDuration || 2000);
                    animateCount(el, target, duration);
                });
            }
        });
    }, { threshold: 0.15 });

    document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));
});

function animateCount(el, target, duration) {
    const start = 0;
    const startTime = performance.now();
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString();
        if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
}
