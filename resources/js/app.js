import './bootstrap';
import './call-engine';

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
