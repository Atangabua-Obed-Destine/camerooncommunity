{{-- "Install app" banner.

     Inline styles rather than new Tailwind classes on purpose: Tailwind v4 JIT-scans
     Blade, so a class that appears nowhere else would need `npm run build`, and
     public/build is committed (rebuilding it causes git pull conflicts on the VPS).

     iOS Safari never fires beforeinstallprompt, so it gets an instruction sheet
     instead of a working button. --}}
<div x-data="pwaInstall()" x-show="show" x-cloak x-transition.opacity
     style="position:fixed; left:0; right:0; bottom:0; z-index:70; display:flex; justify-content:center; padding:12px; pointer-events:none;">

    <div style="pointer-events:auto; width:100%; max-width:460px; background:#ffffff; border-radius:16px;
                box-shadow:0 10px 30px rgba(2,25,45,0.28); overflow:hidden;">

        {{-- Prompt row --}}
        <div style="display:flex; align-items:center; gap:12px; padding:12px 14px;">
            <img src="{{ asset('icons/icon-192.png') }}" alt=""
                 style="width:44px; height:44px; border-radius:11px; flex-shrink:0;">

            <div style="flex:1; min-width:0;">
                <div style="font-weight:700; font-size:0.92rem; color:#0f172a; line-height:1.25;">
                    <span x-text="$store.lang.t('Install CM Network', 'Installer CM Network')"></span>
                </div>
                <div style="font-size:0.76rem; color:#64748b; margin-top:1px;">
                    <span x-text="$store.lang.t('Add it to your home screen', 'Ajoutez-la à votre écran d\'accueil')"></span>
                </div>
            </div>

            <button type="button" @click="canPrompt ? install() : (howTo = !howTo)"
                    style="flex-shrink:0; background:#015083; color:#ffffff; border:0; cursor:pointer;
                           font-family:inherit; font-size:0.82rem; font-weight:700;
                           padding:9px 18px; border-radius:9999px;">
                <span x-text="$store.lang.t('Install', 'Installer')"></span>
            </button>

            <button type="button" @click="dismiss()"
                    :aria-label="$store.lang.t('Dismiss', 'Fermer')"
                    style="flex-shrink:0; width:30px; height:30px; display:grid; place-items:center;
                           background:transparent; border:0; cursor:pointer; color:#94a3b8; font-size:1.1rem;">
                &times;
            </button>
        </div>

        {{-- Manual steps, for when the browser gives no install prompt (always on iOS; on other
             browsers when the prompt isn't available, e.g. unsupported or not yet eligible). --}}
        <div x-show="howTo" x-cloak x-transition
             style="border-top:1px solid #e2e8f0; background:#f8fafc; padding:12px 14px; font-size:0.8rem; color:#334155; line-height:1.5;">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#015083" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <path d="M12 16V3"/><path d="M8 7l4-4 4 4"/>
                    <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>
                </svg>
                <span x-show="ios" x-text="$store.lang.t(
                    'Tap Share, then choose Add to Home Screen.',
                    'Touchez Partager, puis Sur l\'écran d\'accueil.')"></span>
                <span x-show="!ios" x-text="$store.lang.t(
                    'Open your browser menu (⋮), then choose Install app or Add to Home screen.',
                    'Ouvrez le menu du navigateur (⋮), puis Installer l\'application ou Ajouter à l\'écran d\'accueil.')"></span>
            </div>
        </div>
    </div>
</div>

@once
<script>
    function pwaInstall() {
        return {
            show: false,
            ios: false,
            howTo: false,
            canPrompt: false,
            // 14-day snooze, only set when the user closes the banner. New storage key: the old
            // one was also set on install, which kept the banner hidden after an uninstall.
            snoozedAt: window.Alpine.$persist(0).as('cc_pwa_snoozed_at'),

            get installed() {
                return window.matchMedia('(display-mode: standalone)').matches
                    || window.navigator.standalone === true;
            },

            get snoozed() {
                return Date.now() - this.snoozedAt < 14 * 24 * 60 * 60 * 1000;
            },

            init() {
                if (this.installed) return;

                const ua = navigator.userAgent;
                this.ios = /iphone|ipad|ipod/i.test(ua) && !/crios|fxios|edgios/i.test(ua);

                // "Download Our App" buttons open the install flow directly, ignoring the snooze.
                window.addEventListener('pwa-open-install', () => this.open());

                if (this.ios) {
                    // No beforeinstallprompt on iOS, ever — show the manual path,
                    // but not the instant someone lands on the page.
                    if (!this.snoozed) setTimeout(() => { this.show = true; }, 8000);
                    return;
                }

                // The prompt event itself is captured in partials/pwa-head.
                this.canPrompt = !!window.__pwaDeferredPrompt;
                if (this.canPrompt && !this.snoozed) this.show = true;

                window.addEventListener('pwa-installable', () => {
                    this.canPrompt = true;
                    if (!this.snoozed) this.show = true;
                });

                window.addEventListener('appinstalled', () => {
                    this.canPrompt = false;
                    this.show = false;
                });
            },

            async install() {
                const prompt = window.__pwaDeferredPrompt;
                if (!prompt) return;
                prompt.prompt();
                await prompt.userChoice;
                window.__pwaDeferredPrompt = null;
                this.canPrompt = false;
                this.show = false;
            },

            // Called from "Download Our App": the browser's own install prompt when it has one,
            // otherwise the banner with the manual steps.
            open() {
                if (window.__pwaDeferredPrompt) {
                    this.install();
                    return;
                }

                this.howTo = true;
                this.show = true;
            },

            dismiss() {
                this.show = false;
                this.howTo = false;
                this.snoozedAt = Date.now();
            },
        };
    }
</script>
@endonce
