{{--
    WhatsApp-style profile preview card, shared by the whole app.

    Mounted once in components/layouts/app.blade.php, so every authenticated page
    has it. Anything that shows a person opens it with:

        $dispatch('open-user-preview', { id: 12 })
        $dispatch('open-user-preview', { username: 'ngwa' })   // @mentions

    Data comes from one call to yard.user-preview, which also decides the CTA
    (connect / accept / message / blocked) so this card never has to guess.

    The overlay is TELEPORTED TO <body> on purpose. .yard-panel sets
    `will-change: transform` (app.css:530), which makes it the containing block
    for position:fixed descendants AND a stacking context. An overlay rendered
    inside the chat panel is therefore confined to that panel and cannot dim the
    page or paint above the z-50 header. Teleporting escapes both.
--}}
<div x-data="userPreview()"
     @open-user-preview.window="open($event.detail)"
     @open-user-photo.window="viewPhoto($event.detail?.url, $event.detail?.name, $event.detail?.bg)">
    <template x-teleport="body">
        <div x-show="isOpen" x-cloak x-transition.opacity
             class="yard-user-profile-overlay"
             style="z-index:120;"
             @click.self="close()"
             @keydown.escape.window="if (isOpen) close()">

            <div class="yard-user-profile" x-transition.scale.95 @click.stop
                 style="max-width:min(92vw, 360px); max-height:88vh; overflow-y:auto;">

                {{-- Loading --}}
                <template x-if="loading">
                    <div class="py-6 text-sm text-slate-400"
                         x-text="$store.lang.t('Loading...', 'Chargement...')"></div>
                </template>

                <template x-if="!loading && user">
                    <div>
                        {{-- Avatar. Tapping a real photo opens it full size, like WhatsApp. --}}
                        <div class="yard-user-profile__avatar" style="cursor:zoom-in"
                             @click="viewPhoto(user.avatar, user.name, user.avatar_bg)">
                            <template x-if="user.avatar">
                                <img :src="user.avatar" alt="" class="w-full h-full rounded-full object-cover">
                            </template>
                            <template x-if="!user.avatar">
                                <span class="text-3xl font-bold text-white"
                                      x-text="(user.name || '?').charAt(0).toUpperCase()"></span>
                            </template>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 mt-3" x-text="user.name"></h3>

                        {{-- Real handle, shown when it differs from the displayed name --}}
                        <template x-if="user.username && user.username !== user.name">
                            <p class="text-xs text-slate-400 mt-0.5">@<span x-text="user.username"></span></p>
                        </template>

                        {{-- Bio --}}
                        <template x-if="user.bio">
                            <p class="text-sm text-slate-600 mt-2" x-text="user.bio"></p>
                        </template>

                        {{-- Location --}}
                        <template x-if="user.location">
                            <p class="text-xs text-slate-400 mt-1.5">
                                <span aria-hidden="true">&#128205;</span> <span x-text="user.location"></span>
                            </p>
                        </template>

                        {{-- ── Your own profile ── --}}
                        <template x-if="user.is_self">
                            <a :href="user.profile_url" class="yard-user-profile__dm-btn">
                                <span x-text="$store.lang.t('View my profile', 'Voir mon profil')"></span>
                            </a>
                        </template>

                        {{-- ── Someone else ── --}}
                        <template x-if="!user.is_self">
                            <div>
                                {{-- Connected → message --}}
                                <button x-show="user.state === 'connected'" @click="message()"
                                        class="yard-user-profile__dm-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span x-text="$store.lang.t('Send Message', 'Envoyer un message')"></span>
                                </button>

                                {{-- No connection yet → ask --}}
                                <button x-show="user.state === 'none'" :disabled="busy" @click="connect()"
                                        class="yard-user-profile__dm-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v6m3-3h-6M5.25 21v-1.5a6 6 0 0 1 6-6h2.25a6 6 0 0 1 4.215 1.737M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/></svg>
                                    <span x-text="busy
                                        ? $store.lang.t('Sending...', 'Envoi...')
                                        : $store.lang.t('Connect', 'Se connecter')"></span>
                                </button>

                                {{-- Waiting on them. Inline style because the --muted
                                     modifier has no CSS rule, so it used to render as a
                                     live-looking green button. --}}
                                <div x-show="user.state === 'outgoing'"
                                     class="yard-user-profile__dm-btn pointer-events-none"
                                     style="background:#e2e8f0; color:#64748b; box-shadow:none;">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span x-text="$store.lang.t('Request sent', 'Demande envoyée')"></span>
                                </div>

                                {{-- They asked you --}}
                                <button x-show="user.state === 'incoming'" :disabled="busy" @click="accept()"
                                        class="yard-user-profile__dm-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    <span x-text="busy
                                        ? $store.lang.t('Accepting...', 'Acceptation...')
                                        : $store.lang.t('Accept connection', 'Accepter la connexion')"></span>
                                </button>

                                {{-- Blocked either way → no CTA --}}
                                <div x-show="user.state === 'blocked-by-me' || user.state === 'blocked-by-them'"
                                     class="mt-2 text-xs text-rose-600 font-medium">
                                    <span x-show="user.state === 'blocked-by-me'"
                                          x-text="$store.lang.t('You blocked this user', 'Vous avez bloqué cet utilisateur')"></span>
                                    <span x-show="user.state === 'blocked-by-them'"
                                          x-text="$store.lang.t('You can\'t message this user', 'Vous ne pouvez pas envoyer de message')"></span>
                                </div>

                                {{-- Saved name, WhatsApp's "Save as..." --}}
                                <div class="w-full mt-3 px-1"
                                     x-show="user.state !== 'blocked-by-me' && user.state !== 'blocked-by-them'">
                                    <template x-if="!nicknameEditing">
                                        <button type="button"
                                                @click="nicknameEditing = true; nicknameDraft = user.nickname || ''; $nextTick(() => $refs.nickInput?.focus())"
                                                class="yard-nickname-trigger">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897l11.932-11.93z"/></svg>
                                            <span x-text="user.nickname
                                                ? $store.lang.t('Edit saved name', 'Modifier le nom enregistré')
                                                : $store.lang.t('Save as a contact', 'Enregistrer comme contact')"></span>
                                        </button>
                                    </template>
                                    <template x-if="nicknameEditing">
                                        <div class="yard-nickname-edit">
                                            <input x-ref="nickInput" type="text" maxlength="60"
                                                   x-model="nicknameDraft"
                                                   @keydown.enter.prevent="saveNickname()"
                                                   @keydown.escape.stop="nicknameEditing = false"
                                                   :placeholder="$store.lang.t('Enter a name you\'ll recognize', 'Entrez un nom familier')"
                                                   class="yard-nickname-input">
                                            <div class="flex items-center gap-2 mt-2">
                                                <button type="button" @click="saveNickname()" :disabled="nicknameSaving"
                                                        class="yard-nickname-save"
                                                        x-text="nicknameSaving
                                                            ? $store.lang.t('Saving...', 'Enregistrement...')
                                                            : $store.lang.t('Save', 'Enregistrer')"></button>
                                                <template x-if="user.nickname">
                                                    <button type="button" @click="nicknameDraft = ''; saveNickname()" :disabled="nicknameSaving"
                                                            class="yard-nickname-clear"
                                                            x-text="$store.lang.t('Remove', 'Supprimer')"></button>
                                                </template>
                                                <button type="button" @click="nicknameEditing = false" class="yard-nickname-cancel"
                                                        x-text="$store.lang.t('Cancel', 'Annuler')"></button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- The full page is always one tap away --}}
                                <template x-if="user.username">
                                    <a :href="user.profile_url"
                                       class="mt-2 inline-flex items-center justify-center gap-1.5 text-sm font-medium text-cm-green hover:underline">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        <span x-text="$store.lang.t('View full profile', 'Voir le profil complet')"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <button @click="close()" class="mt-2 block mx-auto text-xs text-slate-400 hover:text-slate-600">
                            <span x-text="$store.lang.t('Close', 'Fermer')"></span>
                        </button>
                    </div>
                </template>
            </div>
        </div>

    </template>

    {{-- ── Full-size profile photo ──
         Its OWN teleport on purpose: Alpine's x-teleport moves only the
         template's first element child, so a second sibling in the template
         above would silently never reach the DOM.

         Sits above the card (z 130 vs 120) so it can be opened from the card and
         closed back onto it. Also openable on its own from anywhere:
         $dispatch('open-user-photo', { url, name }) --}}
    <template x-teleport="body">
        <div x-show="photo.open" x-cloak x-transition.opacity
             style="position:fixed; inset:0; z-index:130; background:rgba(0,0,0,.92);"
             @keydown.escape.window="if (photo.open) closePhoto()">

            {{-- Layout lives here, not on the x-show element above: x-show toggles
                 that element's inline `display`, which would wipe out display:flex
                 the moment it is shown, collapsing this column. --}}
            <div style="height:100%; display:flex; flex-direction:column;" @click.self="closePhoto()">

            <div style="display:flex; align-items:center; gap:12px; padding:14px 16px; color:#fff; flex-shrink:0;">
                <span style="font-size:16px; font-weight:600; flex:1; min-width:0;"
                      class="truncate" x-text="photo.name"></span>
                <button type="button" @click="closePhoto()"
                        style="width:38px; height:38px; border-radius:9999px; background:rgba(255,255,255,.12); color:#fff; font-size:24px; line-height:1; display:grid; place-items:center;"
                        :aria-label="$store.lang.t('Close', 'Fermer')">&times;</button>
            </div>

            <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center; padding:0 12px 28px;"
                 @click.self="closePhoto()">
                <template x-if="photo.url">
                    <img :src="photo.url" :alt="photo.name"
                         style="max-width:100%; max-height:100%; object-fit:contain; border-radius:8px;">
                </template>
                {{-- No photo: the initial, in the same colour as the avatar that was
                     tapped, so it reads as the same object getting bigger. --}}
                <template x-if="!photo.url">
                    <div :class="photo.bg"
                         style="width:min(62vw, 260px); height:min(62vw, 260px); border-radius:9999px; color:#fff; display:grid; place-items:center; font-size:min(26vw, 110px); font-weight:700; line-height:1;"
                         x-text="photo.initial"></div>
                </template>
            </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
if (typeof window.userPreview !== 'function') {
    window.userPreview = function () {
        return {
            isOpen: false,
            loading: false,
            photo: { open: false, url: null, name: '', initial: '?', bg: '' },
            busy: false,
            user: null,
            nicknameEditing: false,
            nicknameDraft: '',
            nicknameSaving: false,

            open(detail) {
                const key = detail?.id ?? detail?.username;
                if (!key) return;

                this.isOpen = true;
                this.loading = true;
                this.user = null;
                this.nicknameEditing = false;
                this.busy = false;
                this.load(key);
            },

            async load(key) {
                try {
                    const res = await fetch('{{ url('/yard/user-preview') }}/' + encodeURIComponent(key), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error('lookup failed');
                    this.user = await res.json();
                } catch (e) {
                    this.close();
                    this.toast('error', this.$store.lang.t('Could not open this profile', "Impossible d'ouvrir ce profil"));
                } finally {
                    this.loading = false;
                }
            },

            close() {
                this.isOpen = false;
                this.nicknameEditing = false;
                this.closePhoto();
            },

            // Full-size profile photo. Openable from the card's avatar or directly
            // from any page via the open-user-photo event. Opens even without a
            // photo, showing the initial, so the tap is never a dead end.
            viewPhoto(url, name, bg) {
                this.photo = {
                    open: true,
                    url: url || null,
                    name: name || '',
                    initial: (name || '?').charAt(0).toUpperCase(),
                    bg: bg || 'bg-gradient-to-br from-slate-500 to-slate-700',
                };
            },

            closePhoto() {
                this.photo = { open: false, url: null, name: '', initial: '?', bg: '' };
            },

            toast(type, message) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
            },

            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            },

            // Open the existing conversation when there is one, otherwise create it.
            async message() {
                if (!this.user) return;
                if (this.user.dm_room_id) { this.goToRoom(this.user.dm_room_id, this.user.dm_room_slug); return; }

                this.busy = true;
                try {
                    const res = await fetch('{{ route('yard.dm.create') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ user_id: this.user.id }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || 'dm failed');
                    this.goToRoom(data.room_id, data.slug);
                } catch (e) {
                    this.toast('error', this.$store.lang.t('Could not open the chat', "Impossible d'ouvrir la discussion"));
                } finally {
                    this.busy = false;
                }
            },

            // Inside the Yard the chat swaps in place. Anywhere else we must navigate,
            // and it has to be /yard/room/{slug}: the Yard page only reads
            // ?open=connections from the URL, so ?room= would land on the room list
            // without opening anything.
            goToRoom(roomId, slug) {
                this.close();
                if (window.location.pathname.includes('/yard')) {
                    window.dispatchEvent(new CustomEvent('room-selected', { detail: { roomId } }));
                } else if (slug) {
                    window.location.assign('{{ url('/yard/room') }}/' + slug);
                } else {
                    window.location.assign('{{ route('yard') }}');
                }
            },

            async connect() {
                if (!this.user || this.busy) return;
                this.busy = true;
                try {
                    const res = await fetch('{{ route('yard.connections.request') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ user_id: this.user.id }),
                    });
                    if (!res.ok) throw new Error('request failed');
                    this.user.state = 'outgoing';
                    this.toast('success', this.$store.lang.t('Connection request sent', 'Demande de connexion envoyée'));
                } catch (e) {
                    this.toast('error', this.$store.lang.t('Network error', 'Erreur réseau'));
                } finally {
                    this.busy = false;
                }
            },

            async accept() {
                if (!this.user || this.busy) return;
                this.busy = true;
                try {
                    const res = await fetch('{{ route('yard.connections.accept') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ user_id: this.user.id }),
                    });
                    if (!res.ok) throw new Error('accept failed');
                    this.user.state = 'connected';
                    this.toast('success', this.$store.lang.t('Connected', 'Connecté'));
                    if (window.Livewire) window.Livewire.dispatch('refreshChat');
                } catch (e) {
                    this.toast('error', this.$store.lang.t('Network error', 'Erreur réseau'));
                } finally {
                    this.busy = false;
                }
            },

            async saveNickname() {
                if (!this.user) return;
                this.nicknameSaving = true;
                try {
                    const res = await fetch('{{ route('yard.contacts.nickname') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ user_id: this.user.id, nickname: this.nicknameDraft || '' }),
                    });
                    if (!res.ok) throw new Error('save failed');
                    const data = await res.json();
                    this.user.nickname = data.nickname || '';
                    this.user.name = data.nickname || this.user.username;
                    this.nicknameEditing = false;
                    this.toast('success', data.nickname
                        ? this.$store.lang.t('Saved as ' + data.nickname, 'Enregistré sous ' + data.nickname)
                        : this.$store.lang.t('Custom name removed', 'Nom personnalisé supprimé'));
                    // Message sender chips in the chat show the saved name.
                    if (window.Livewire) window.Livewire.dispatch('refreshChat');
                } catch (e) {
                    this.toast('error', this.$store.lang.t('Could not save name', "Impossible d'enregistrer"));
                } finally {
                    this.nicknameSaving = false;
                }
            },
        };
    };
}

// @mentions in chat are rendered as <a class="yard-msg__mention" data-username="...">
// with no href. Delegated so it keeps working as messages stream in.
document.addEventListener('click', function (e) {
    const mention = e.target.closest('.yard-msg__mention');
    if (!mention) return;
    const username = mention.dataset.username;
    if (!username) return;
    e.preventDefault();
    window.dispatchEvent(new CustomEvent('open-user-preview', { detail: { username } }));
});
</script>
@endpush
@endonce
