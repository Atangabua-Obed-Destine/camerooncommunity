<div x-data="callEngine(@js(auth()->id()), @js(app('currentTenant')?->id))"
     @call-started.window="onCallStarted($event.detail)"
     @call-answered.window="onCallAnswered($event.detail)"
     @call-ended.window="onCallEnded()"
     @call-error.window="showError($event.detail.message)"
     @initiate-call.window="$wire.initiateCall($event.detail.roomId, $event.detail.type)"
     class="relative z-[200]">

    {{-- ════════════════════════════════════════════════════════
         INCOMING CALL — Floating notification card
    ════════════════════════════════════════════════════════ --}}
    <template x-if="incomingCall">
        <div class="fixed inset-0 z-[201] flex items-start justify-center pt-8 pointer-events-none" x-transition>
            <div class="yard-call-modal yard-call-modal--incoming pointer-events-auto animate-slide-down"
                 style="width: 380px;">
                <div class="yard-call-modal__body">
                    {{-- Header row --}}
                    <div class="flex items-center gap-4 mb-5">
                        <div class="yard-call-modal__avatar">
                            <span x-text="incomingCall?.callerName?.charAt(0)?.toUpperCase() || '?'"></span>
                            <div class="yard-call-modal__pulse"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="yard-call-modal__name" x-text="incomingCall?.callerName || 'Unknown'"></h3>
                            <p class="yard-call-modal__status">
                                <span class="yard-call-modal__type-icon">
                                    <template x-if="incomingCall?.callType === 'video'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                                    </template>
                                    <template x-if="incomingCall?.callType === 'voice'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                                    </template>
                                </span>
                                <span x-text="incomingCall?.callType === 'video' ? $store.lang.t('Incoming video call...', 'Appel vidéo entrant...') : $store.lang.t('Incoming voice call...', 'Appel vocal entrant...')"></span>
                            </p>
                        </div>
                    </div>
                    {{-- Action buttons --}}
                    <div class="flex items-center justify-center gap-5">
                        <button @click="decline()" class="yard-call-btn yard-call-btn--decline" title="Decline">
                            <svg class="w-6 h-6 rotate-[135deg]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                            <span class="yard-call-btn__label" x-text="$store.lang.t('Decline', 'Refuser')"></span>
                        </button>
                        <button @click="accept()" class="yard-call-btn yard-call-btn--accept" title="Accept">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                            <span class="yard-call-btn__label" x-text="$store.lang.t('Accept', 'Accepter')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ════════════════════════════════════════════════════════
         OUTGOING CALL — Floating draggable card (WhatsApp-style)
    ════════════════════════════════════════════════════════ --}}
    <template x-if="callState === 'outgoing'">
        <div class="fixed z-[201]"
             x-ref="callModal"
             :style="modalPos.x !== null ? `left:${modalPos.x}px;top:${modalPos.y}px` : 'top:50%;left:50%;transform:translate(-50%,-50%)'"
             x-transition>
            <div class="yard-call-modal yard-call-modal--outgoing" style="width: 360px;">
                {{-- Drag handle --}}
                <div class="yard-call-modal__drag"
                     @mousedown.prevent="startDrag($event)"
                     @touchstart.prevent="startDrag($event)">
                    <div class="yard-call-modal__drag-pill"></div>
                </div>

                <div class="yard-call-modal__body yard-call-modal__body--centered">
                    {{-- Avatar with ringing animation --}}
                    <div class="yard-call-modal__avatar yard-call-modal__avatar--lg">
                        <template x-if="calleeAvatar">
                            <img :src="calleeAvatar" alt="" class="w-full h-full rounded-full object-cover">
                        </template>
                        <template x-if="!calleeAvatar">
                            <span x-text="(calleeName || callRoomName || '?').charAt(0).toUpperCase()"></span>
                        </template>
                        <div class="yard-call-modal__ring yard-call-modal__ring--1"></div>
                        <div class="yard-call-modal__ring yard-call-modal__ring--2"></div>
                        <div class="yard-call-modal__ring yard-call-modal__ring--3"></div>
                    </div>

                    <h2 class="yard-call-modal__name yard-call-modal__name--lg" x-text="calleeName || callRoomName"></h2>

                    {{-- Online / Offline --}}
                    <div class="yard-call-modal__presence">
                        <span class="yard-call-modal__dot" :class="calleeOnline ? 'yard-call-modal__dot--online' : 'yard-call-modal__dot--offline'"></span>
                        <span x-text="calleeOnline ? $store.lang.t('Online', 'En ligne') : $store.lang.t('Offline', 'Hors ligne')"></span>
                    </div>

                    <p class="yard-call-modal__calling-text">
                        <span x-text="callType === 'video' ? $store.lang.t('Video calling...', 'Appel vidéo...') : $store.lang.t('Calling...', 'Appel...')"></span>
                    </p>
                    <p class="yard-call-modal__timer" x-text="ringTimer"></p>

                    {{-- Controls --}}
                    <div class="yard-call-modal__controls">
                        <button @click="toggleMute()" class="yard-call-ctrl" :class="isMuted ? 'yard-call-ctrl--active' : ''">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3z"/></svg>
                            <span class="yard-call-ctrl__label" x-text="$store.lang.t('Mute', 'Muet')"></span>
                        </button>
                        <button @click="toggleSpeaker()" class="yard-call-ctrl" :class="isSpeaker ? 'yard-call-ctrl--active' : ''">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25h2.24z"/></svg>
                            <span class="yard-call-ctrl__label" x-text="$store.lang.t('Speaker', 'Haut-parleur')"></span>
                        </button>
                        <template x-if="callType === 'video'">
                            <button @click="toggleVideo()" class="yard-call-ctrl" :class="isVideoOff ? 'yard-call-ctrl--active' : ''">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                                <span class="yard-call-ctrl__label" x-text="$store.lang.t('Camera', 'Caméra')"></span>
                            </button>
                        </template>
                    </div>

                    <div class="mt-5">
                        <button @click="hangUp()" class="yard-call-btn yard-call-btn--end">
                            <svg class="w-7 h-7 rotate-[135deg]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ════════════════════════════════════════════════════════
         ACTIVE CALL — Floating draggable modal (WhatsApp-style)
    ════════════════════════════════════════════════════════ --}}
    <template x-if="callState === 'active'">
        <div class="fixed z-[201]"
             x-ref="callModal"
             :style="modalPos.x !== null ? `left:${modalPos.x}px;top:${modalPos.y}px` : 'top:50%;left:50%;transform:translate(-50%,-50%)'"
             x-transition>
            <div class="yard-call-modal yard-call-modal--active"
                 :style="callType === 'video' ? 'width:480px' : 'width:360px'">
                {{-- Drag handle --}}
                <div class="yard-call-modal__drag"
                     @mousedown.prevent="startDrag($event)"
                     @touchstart.prevent="startDrag($event)">
                    <div class="yard-call-modal__drag-pill"></div>
                </div>

                {{-- Video area --}}
                <template x-if="callType === 'video'">
                    <div class="yard-call-modal__video-area">
                        <div class="yard-call-modal__remote-grid"
                             :class="remoteStreams.length > 1 ? 'yard-call-modal__remote-grid--multi' : ''"
                             id="remote-videos">
                            <template x-for="stream in remoteStreams" :key="stream.peerId">
                                <div class="yard-call-modal__remote-tile">
                                    <video :id="'remote-video-' + stream.peerId" autoplay playsinline
                                           class="w-full h-full object-cover"></video>
                                    <div class="yard-call-modal__remote-label" x-text="stream.name"></div>
                                </div>
                            </template>
                            <template x-if="remoteStreams.length === 0">
                                <div class="yard-call-modal__video-placeholder">
                                    <div class="yard-call-modal__avatar">
                                        <template x-if="calleeAvatar">
                                            <img :src="calleeAvatar" alt="" class="w-full h-full rounded-full object-cover">
                                        </template>
                                        <template x-if="!calleeAvatar">
                                            <span x-text="(calleeName || callRoomName || '?').charAt(0).toUpperCase()"></span>
                                        </template>
                                    </div>
                                    <p class="text-white/50 text-xs mt-3" x-text="$store.lang.t('Connecting...', 'Connexion...')"></p>
                                </div>
                            </template>
                        </div>
                        <div class="yard-call-modal__local-pip" x-show="!isVideoOff" x-transition>
                            <video id="local-video" autoplay playsinline muted class="w-full h-full object-cover rounded-xl"></video>
                        </div>
                    </div>
                </template>

                {{-- Voice call body --}}
                <template x-if="callType === 'voice'">
                    <div class="yard-call-modal__body yard-call-modal__body--centered">
                        <div class="flex flex-wrap items-center justify-center gap-4 mb-4">
                            <template x-for="p in callParticipants" :key="p.user_id">
                                <div class="flex flex-col items-center gap-1.5">
                                    <div class="yard-call-modal__participant"
                                         :class="p.status === 'joined' ? 'yard-call-modal__participant--active' : 'yard-call-modal__participant--inactive'">
                                        <template x-if="p.avatar">
                                            <img :src="p.avatar" alt="" class="w-full h-full rounded-full object-cover">
                                        </template>
                                        <template x-if="!p.avatar">
                                            <span x-text="p.initial"></span>
                                        </template>
                                    </div>
                                    <span class="text-white/60 text-[10px] font-medium" x-text="p.name.split(' ')[0]"></span>
                                    <template x-if="p.is_muted">
                                        <span class="yard-call-modal__muted-badge">muted</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <p class="yard-call-modal__timer yard-call-modal__timer--active" x-text="callDuration"></p>
                    </div>
                </template>

                {{-- Info bar --}}
                <div class="yard-call-modal__info-bar">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="yard-call-modal__info-name" x-text="calleeName || callRoomName"></span>
                        <span class="yard-call-modal__info-dot"></span>
                    </div>
                    <div class="flex items-center gap-1.5 text-white/40 text-[10px]">
                        <span x-text="callParticipants.filter(p => p.status === 'joined').length"></span>
                        <span x-text="$store.lang.t('in call', 'en appel')"></span>
                        <span class="mx-1">&middot;</span>
                        <span x-text="callDuration" class="font-mono"></span>
                    </div>
                </div>

                {{-- Controls bar --}}
                <div class="yard-call-modal__controls-bar">
                    <button @click="toggleMute()" class="yard-call-ctrl" :class="isMuted ? 'yard-call-ctrl--active' : ''">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3z"/></svg>
                    </button>
                    <template x-if="callType === 'video'">
                        <button @click="toggleVideo()" class="yard-call-ctrl" :class="isVideoOff ? 'yard-call-ctrl--active' : ''">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                        </button>
                    </template>
                    <button @click="toggleSpeaker()" class="yard-call-ctrl" :class="isSpeaker ? 'yard-call-ctrl--active' : ''">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25h2.24z"/></svg>
                    </button>
                    <button @click="hangUp()" class="yard-call-ctrl yard-call-ctrl--end">
                        <svg class="w-6 h-6 rotate-[135deg]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Hidden audio for ringtone --}}
    <audio id="call-ringtone" loop preload="auto">
        <source src="data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=" type="audio/wav">
    </audio>
</div>
