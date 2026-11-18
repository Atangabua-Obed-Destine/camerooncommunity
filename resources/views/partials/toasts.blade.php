{{-- Toasts for every page except GoConnect itself (/yard), which mounts its own
     (yardToasts in yard/index) and would otherwise show each one twice. GoMarket
     and the profile run in yardMode too but have none of their own, so their
     toasts were dropped until this existed.

     Raise one with window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } })),
     or from Livewire with $this->dispatch('toast', type: ..., message: ...).
     Same look as GoConnect's. --}}
<script>
    function cnToasts() {
        return {
            toasts: [],
            _id: 0,
            init() {
                if (document.querySelector('[x-data="yardToasts()"]')) return;
                window.addEventListener('toast', (e) => {
                    const raw = (e && e.detail) || {};
                    const data = Array.isArray(raw) ? raw[0] : raw;
                    if (!data || !data.message) return;
                    const id = ++this._id;
                    this.toasts.push({ id, title: data.message, body: '', kind: data.type || 'info' });
                    if (this.toasts.length > 3) this.toasts.shift();
                    setTimeout(() => this.dismiss(id), 5000);
                });
            },
            dismiss(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
            },
        };
    }
</script>
<div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"
     x-data="cnToasts()" role="status" aria-live="polite" x-cloak>
    <template x-for="t in toasts" :key="t.id">
        <button type="button" @click="dismiss(t.id)"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto bg-white shadow-lg rounded-xl border px-4 py-3 w-72 text-left hover:bg-slate-50 transition flex items-start gap-3"
                :class="{
                    'border-slate-200': !t.kind || t.kind === 'info',
                    'border-emerald-200': t.kind === 'success',
                    'border-amber-200': t.kind === 'warning',
                    'border-rose-200': t.kind === 'error',
                }">
            <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center"
                 :class="{
                     'bg-cm-green/10 text-cm-green': !t.kind || t.kind === 'info',
                     'bg-emerald-100 text-emerald-600': t.kind === 'success',
                     'bg-amber-100 text-amber-600': t.kind === 'warning',
                     'bg-rose-100 text-rose-600': t.kind === 'error',
                 }">
                <svg x-show="!t.kind || t.kind === 'info'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                <svg x-show="t.kind === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <svg x-show="t.kind === 'warning' || t.kind === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-900" x-text="t.title"></p>
                <p x-show="t.body" class="text-xs text-slate-500 truncate" x-text="t.body"></p>
            </div>
        </button>
    </template>
</div>
