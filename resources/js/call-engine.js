// Bumped whenever this file changes, so a deployed browser can be identified.
const ENGINE_BUILD = '2026-10-04.poll-trace';

/**
 * Cameroon Network — WebRTC Call Engine (Alpine.js component)
 * Handles peer connections, media streams, and signaling via Livewire + Echo.
 */
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /**
     * `endpoints` is passed in from Blade, because this file is bundled and
     * cannot call route(). The URLs used to be written out by hand with the
     * development subdirectory baked in ('/camerooncommunity/public/...'), which
     * 404s in production — and a 404 on the TURN endpoint leaves the connection
     * STUN-only, so any pair of peers behind carrier NAT never connects and the
     * call just never starts.
     */
    Alpine.data('callEngine', (currentUserId, tenantId, endpoints = {}) => ({
        endpoints: {
            turn:     endpoints.turn     || '/api/turn-credentials',
            nickname: endpoints.nickname || '/yard/contacts/nickname',
        },

        // State
        callState: 'idle', // idle | outgoing | incoming | active
        callUuid: null,
        callId: null,
        callType: null,
        callRoomId: null,
        callRoomName: null,
        callerName: null,
        isInitiator: false,

        // Media
        localStream: null,
        isMuted: false,
        isVideoOff: false,
        isSpeaker: false,

        // Incoming call data
        incomingCall: null,

        // Callee info (for outgoing calls)
        calleeName: null,
        calleeAvatar: null,
        calleeOnline: false,

        // Drag state for floating modal
        isDragging: false,
        dragOffset: { x: 0, y: 0 },
        modalPos: { x: null, y: null },

        // Peer connections: { peerId: RTCPeerConnection }
        peers: {},

        // Who we have already opened a connection to, so a 'joined' that
        // arrives twice (two channels, or broadcast plus poll) is harmless.
        _joinedPeers: [],
        _joinPoll: null,

        // Keys of signals already applied, so a duplicate delivery is ignored.
        _seenSignals: [],
        remoteStreams: [],

        // Participants (from server)
        callParticipants: [],

        // Timers
        callStartTime: null,
        callDuration: '00:00',
        ringTimer: '00:00',
        _durationInterval: null,
        _ringInterval: null,
        _ringTimeout: null,
        _echoChannel: null,

        // ICE servers — populated dynamically from Metered TURN service
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
        ],
        _iceReady: false,

        init() {
            // Fetch TURN credentials FIRST so any immediate call has a full ICE
            // server list. Without this, an immediately-initiated call falls
            // back to STUN-only and silently fails to connect across NATs.
            // Wrapped so init() stays sync-friendly for Alpine.
            (async () => {
                try { await this.fetchTurnServers(); } catch (e) { /* falls back to STUN */ }
            })();

            // Subscribe to user-specific call channel so we receive
            // incoming calls regardless of which room is currently open
            // A build marker. Assets are rebuilt on the server, so the first
            // question when calls misbehave in production is always whether the
            // browser is running the code that was just deployed.
            console.log('[CallEngine] init', {
                build: ENGINE_BUILD,
                userId: currentUserId,
                tenantId,
                echo: !!window.Echo,
            });

            if (!window.Echo || !tenantId || !currentUserId) {
                console.error('[CallEngine] Cannot receive calls: missing ' +
                    (!window.Echo ? 'Echo' : (!tenantId ? 'tenant id' : 'user id')) +
                    '. Incoming calls will never ring on this page.');
            }

            if (window.Echo) {
                const userCallChannel = `tenant.${tenantId}.user.${currentUserId}.calls`;
                console.log('[CallEngine] listening for calls on', userCallChannel);
                this._userCallChannelName = userCallChannel;
                this._userCallChannel = window.Echo.channel(userCallChannel);
                this._userCallChannel.listen('.CallStarted', (data) => {
                    console.log('[CallEngine] CallStarted on user channel', data);
                    if (data.initiated_by !== currentUserId) {
                        this.handleIncomingCall(data);
                    }
                });
                this._userCallChannel.listen('.CallUpdated', (data) => {
                    this.handleCallUpdate(data);
                });
                // Signalling also comes down this channel, so a call still
                // connects when the room channel is not subscribed.
                this._userCallChannel.listen('.CallSignal', (data) => {
                    if (data.to_user_id === currentUserId || data.to_user_id === 0) {
                        this.handleSignal(data);
                    }
                });
            }

            // Also subscribe to room-specific channel when a room is selected
            // (needed for CallSignal and CallUpdated during active calls)
            window.addEventListener('room-selected', (e) => {
                const roomId = e.detail?.roomId;
                if (roomId) {
                    this.subscribeToRoom(roomId);
                } else {
                    this.unsubscribeRoom();
                }
            });

            // Deliberately NOT unsubscribing on 'pagehide'. That event fires when
            // the page is merely hidden — switching apps, locking the phone, the
            // tab going to the background — which is precisely when a device most
            // needs to still be reachable. Leaving the channel there meant a phone
            // sitting on the chat screen stopped being ringable, silently, with no
            // way back until a reload. Reverb drops the subscription by itself when
            // the socket actually closes.
        },

        unsubscribeRoom() {
            if (this._echoChannel && this._echoChannelName && window.Echo) {
                try { window.Echo.leave(this._echoChannelName); } catch (_) {}
            }
            this._echoChannel = null;
            this._echoChannelName = null;
        },

        subscribeToRoom(roomId) {
            const channelName = `tenant.${tenantId}.room.${roomId}`;
            // Same room — no-op.
            if (this._echoChannelName === channelName) return;
            // Different room — leave the previous one before subscribing.
            this.unsubscribeRoom();

            if (window.Echo) {
                console.log('[CallEngine] subscribing to room channel', channelName);
                this._echoChannelName = channelName;
                this._echoChannel = window.Echo.channel(channelName);
                this._echoChannel._roomId = roomId;

                this._echoChannel.listen('.CallStarted', (data) => {
                    if (data.initiated_by !== currentUserId) {
                        this.handleIncomingCall(data);
                    }
                });

                this._echoChannel.listen('.CallSignal', (data) => {
                    if (data.to_user_id === currentUserId || data.to_user_id === 0) {
                        this.handleSignal(data);
                    }
                });

                this._echoChannel.listen('.CallUpdated', (data) => {
                    this.handleCallUpdate(data);
                });
            }
        },

        async fetchTurnServers() {
            try {
                const resp = await fetch(this.endpoints.turn, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!resp.ok) throw new Error('TURN API error');
                const servers = await resp.json();

                // Metered returns [{urls, username, credential}, ...] — merge with STUN fallback
                this.iceServers = [
                    { urls: 'stun:stun.l.google.com:19302' },
                    ...servers,
                ];
            } catch (e) {
                console.warn('Could not fetch TURN servers, using STUN only:', e.message);
            }
            this._iceReady = true;
        },

        // ── Initiate a call ──
        onCallStarted(detail) {
            // Called when Livewire dispatches call-started (we are the initiator)
            const d = Array.isArray(detail) ? detail[0] : detail;
            this.callUuid = d.callUuid;
            this.callId = d.callId;
            this.callType = d.callType;
            this.callRoomId = d.roomId;
            this.calleeName = d.calleeName || d.callRoomName || '';
            this.calleeAvatar = d.calleeAvatar || null;
            this.calleeOnline = d.calleeOnline || false;
            this.isInitiator = true;
            this.callState = 'outgoing';

            this.startRingTimer();
            this.startJoinPoll();
            this.acquireMedia(d.callType).then(() => {
                // Wait for answer via broadcast
            });

            // Auto-cancel after 45s
            this._ringTimeout = setTimeout(() => {
                if (this.callState === 'outgoing') {
                    this.hangUp();
                }
            }, 45000);
        },

        // ── Incoming call from broadcast ──
        handleIncomingCall(data) {
            if (this.callState !== 'idle') {
                // A previous call that ended badly can leave this stuck, and then
                // nothing ever rings again until the page is reloaded.
                console.warn('[CallEngine] Ignoring incoming call: state is', this.callState);
                return;
            }

            // The same call arrives on both the user channel and the room
            // channel, so without this the card is built twice and the
            // ringtone restarts on top of itself.
            if (this.incomingCall && this.incomingCall.callUuid === data.call_uuid) {
                return;
            }

            console.log('[CallEngine] ringing — showing the incoming card', data.call_uuid);

            this.incomingCall = {
                callUuid: data.call_uuid,
                callId: data.call_id,
                callType: data.call_type,
                roomId: data.room_id,
                callerName: data.caller_name,
                callerAvatar: data.caller_avatar || null,
                roomName: data.caller_name, // for DMs
            };
            this.callUuid = data.call_uuid;
            this.callType = data.call_type;
            this.callRoomId = data.room_id;
            // Use the caller avatar as the "other side" avatar so the active
            // call UI keeps the same profile picture once we accept.
            this.calleeAvatar = data.caller_avatar || null;
            this.calleeName = data.caller_name || '';

            // Resolve the saved contact nickname (if the recipient has one)
            // so the incoming-call card shows the personalized name instead
            // of the raw username broadcast by the initiator.
            if (data.initiated_by) {
                fetch(`${this.endpoints.nickname}/${data.initiated_by}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(r => r.ok ? r.json() : null)
                    .then(j => {
                        if (j && j.nickname && this.incomingCall && this.incomingCall.callUuid === data.call_uuid) {
                            this.incomingCall.callerName = j.nickname;
                            this.incomingCall.roomName  = j.nickname;
                            this.calleeName = j.nickname;
                            this.callerName = j.nickname;
                        }
                    })
                    .catch(() => { /* fall back to broadcast name */ });
            }

            // Subscribe to the room channel immediately so we receive
            // CallUpdated (ended/declined) even before accepting the call
            if (data.room_id) {
                this.subscribeToRoom(data.room_id);
            }

            // Play ringtone via Web Audio (no file needed)
            this.playRingtone();
        },

        // ── Accept call ──
        async accept() {
            // Logged because the server records a call starting and then, when
            // this never runs, records nothing at all — and from the caller's
            // side a card that was never tapped looks exactly like an answer
            // that was lost on the way.
            console.log('[CallEngine] accept pressed', this.incomingCall?.callUuid);

            if (!this.incomingCall) {
                console.warn('[CallEngine] accept ignored: no incoming call on this engine');
                return;
            }

            this.stopRingtone();
            this.callerName = this.incomingCall.callerName;

            // Subscribe to the room channel for signaling (CallSignal, CallUpdated)
            if (this.incomingCall.roomId) {
                this.subscribeToRoom(this.incomingCall.roomId);
            }

            const uuid = this.incomingCall.callUuid;
            this.incomingCall = null;

            // The microphone should be open before the server is told we
            // answered. Answering triggers the caller's offer, and the answer we
            // send back carries only the tracks this side had at that moment — so
            // acquiring media afterwards produced a one-way call: the caller was
            // heard, but nothing went back the other way.
            //
            // It must not be allowed to block forever, though. getUserMedia does
            // not settle while a permission prompt sits unanswered, and on a
            // domain the browser has never been granted the microphone on, that
            // prompt appears exactly here — leaving the caller watching
            // "Calling…" while this side had already pressed accept. After the
            // grace period we answer anyway; _acquireMedia attaches the tracks
            // and renegotiates if the stream turns up later.
            await this.withTimeout(this.acquireMedia(this.callType), 8000);

            console.log('[CallEngine] media ready, telling the server we answered', uuid);

            try {
                await this.$wire.answerCall(uuid);
                console.log('[CallEngine] server accepted the answer', uuid);
            } catch (e) {
                // A failed round trip here used to be invisible on both sides:
                // this side's incoming card was already gone, and the caller
                // kept ringing until the 45s timeout.
                console.error('[CallEngine] answerCall failed', e);
                this.showError('Could not join the call. Check your connection and try again.');
                this.cleanup();
            }
        },

        onCallAnswered(detail) {
            const d = Array.isArray(detail) ? detail[0] : detail;
            this.callUuid = d.callUuid;
            this.callId = d.callId;
            this.callType = d.callType;
            this.callRoomId = d.roomId;
            this.callRoomName = this.callerName || 'Call';
            this.isInitiator = false;
            this.callState = 'active';

            this.startCallTimer();
            this.acquireMedia(d.callType).then(() => {
                // Server will broadcast CallUpdated with 'joined' — initiator will create offer
            });
        },

        // ── Decline call ──
        decline() {
            if (!this.incomingCall) return;
            this.stopRingtone();
            this.$wire.declineCall(this.incomingCall.callUuid);
            this.incomingCall = null;
            this.callState = 'idle';
        },

        // ── Handle CallUpdated : someone joined/declined/ended ──
        handleCallUpdate(data) {
            if (data.call_uuid !== this.callUuid) return;

            // Ignore our own updates (in case toOthers() didn't exclude us)
            if (data.user_id === currentUserId) return;

            if (data.action === 'joined') {
                this.onPeerJoined(data.user_id, data.user_name);
            }

            if (data.action === 'declined') {
                this.$wire.call('refreshParticipants').then(() => {
                    this.callParticipants = this.$wire.get('participants') || [];

                    // If we're still in 'outgoing' (caller waiting) and no one
                    // else is ringing/joined, the callee declined → end the call.
                    if (this.callState === 'outgoing') {
                        const others = (this.callParticipants || []).filter(p =>
                            p.user_id !== currentUserId &&
                            (p.status === 'ringing' || p.status === 'joined')
                        );
                        if (others.length === 0) {
                            this.cleanup();
                        }
                    }
                });
            }

            if (data.action === 'ended') {
                this.cleanup();
            }
        },

        /**
         * Someone is now in the call with us: open the peer connection and, if
         * we were still ringing, start the call.
         *
         * Reached two ways — the CallUpdated 'joined' broadcast and the poll
         * below, which reconciles with the server when that broadcast is lost.
         * Both can arrive for the same person, and the broadcast now travels on
         * two channels, so this has to be idempotent.
         */
        onPeerJoined(userId, userName) {
            if (!userId || userId === currentUserId) return;
            if (this._joinedPeers.includes(userId)) return;
            if (this.callState !== 'outgoing' && this.callState !== 'active') return;

            console.log('[CallEngine] peer joined:', userId, '— leaving', this.callState);

            this._joinedPeers.push(userId);

            // Glare avoidance: when both peers receive each other's `joined`
            // ~simultaneously they would both create offers, racing on the same
            // connection. The peer with the LOWER user id is deterministically
            // nominated as the offerer, except that the initiator of the call is
            // always the offerer toward newcomers.
            const shouldOffer = this.callState === 'outgoing' ? true : (currentUserId < userId);

            if (this.callState === 'outgoing') {
                this.callState = 'active';
                clearTimeout(this._ringTimeout);
                this.stopRingTimer();
                this.stopJoinPoll();
                this.startCallTimer();
            }

            this.createPeerConnection(userId, userName, shouldOffer);

            this.$wire.call('refreshParticipants').then(() => {
                this.callParticipants = this.$wire.get('participants') || [];
            }).catch(() => {});
        },

        /**
         * While we are ringing, keep asking the server whether the other side
         * picked up.
         *
         * The 'joined' broadcast is the normal path and this is the safety net.
         * A websocket that dropped and reconnected mid-ring, a proxy that
         * swallowed the frame, or a room channel that was never subscribed all
         * end the same way: the callee is in the call and the caller still sees
         * "Calling…" until it times out. The participant row is the truth, so
         * poll it cheaply until the two agree.
         */
        startJoinPoll() {
            this.stopJoinPoll();

            this._joinPoll = setInterval(() => {
                if (this.callState !== 'outgoing') {
                    this.stopJoinPoll();
                    return;
                }

                this.$wire.call('refreshParticipants').then(() => {
                    const participants = this.$wire.get('participants') || [];
                    this.callParticipants = participants;

                    // Printed every tick while ringing. If the other side shows
                    // as joined here and the call still does not start, the
                    // fault is below this line rather than in the broadcast.
                    console.log('[CallEngine] ringing — server says:',
                        participants.map(p => `${p.user_id}:${p.status}`).join(' '));

                    const joined = participants.find(p =>
                        p.user_id !== currentUserId && p.status === 'joined'
                    );

                    if (joined) {
                        console.warn('[CallEngine] joined event never arrived — reconciled from the server');
                        this.onPeerJoined(joined.user_id, joined.name);
                    }
                }).catch((e) => {
                    // Swallowing this made a poll that never worked look
                    // identical to one that found nothing.
                    console.error('[CallEngine] the ringing poll failed:', e);
                });
            }, 3000);
        },

        stopJoinPoll() {
            clearInterval(this._joinPoll);
            this._joinPoll = null;
        },

        /**
         * Resolve when the promise settles or when the grace period expires,
         * whichever comes first. Never rejects: the caller carries on either way.
         */
        withTimeout(promise, ms) {
            return Promise.race([
                Promise.resolve(promise).catch(() => {}),
                new Promise((resolve) => setTimeout(resolve, ms)),
            ]);
        },

        // ── WebRTC Signaling ──
        handleSignal(data) {
            const peerId = data.from_user_id;

            console.log('[CallEngine] signal in:', data.signal_type, 'from', peerId);

            // The same signal now arrives on two channels when both are
            // subscribed. Applying an offer or an answer twice throws the peer
            // connection out of state, so each one is handled once.
            const key = [data.call_uuid, peerId, data.signal_type,
                JSON.stringify(data.signal_data)].join('|');

            if (this._seenSignals.includes(key)) return;
            this._seenSignals.push(key);
            if (this._seenSignals.length > 200) this._seenSignals.shift();

            if (data.signal_type === 'offer') {
                this.handleOffer(peerId, data.signal_data);
            } else if (data.signal_type === 'answer') {
                this.handleAnswer(peerId, data.signal_data);
            } else if (data.signal_type === 'ice-candidate') {
                this.handleIceCandidate(peerId, data.signal_data);
            }
        },

        // Buffered ICE candidates per peer (before remote description is set)
        _pendingCandidates: {},

        createPeerConnection(peerId, peerName, createOffer = false) {
            if (this.peers[peerId]) return;

            console.log('[CallEngine] opening peer connection to', peerId,
                createOffer ? '(we offer)' : '(we answer)');

            const pc = new RTCPeerConnection({ iceServers: this.iceServers });
            this.peers[peerId] = pc;
            this._pendingCandidates[peerId] = [];

            this.attachLocalTracks(pc);

            // Handle ICE candidates
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    this.$wire.sendSignal(
                        this.callUuid,
                        peerId,
                        'ice-candidate',
                        { candidate: event.candidate.toJSON() }
                    );
                }
            };

            // Handle remote stream
            pc.ontrack = (event) => {
                const stream = event.streams[0];
                if (!stream) return;

                const existing = this.remoteStreams.find(s => s.peerId === peerId);
                if (!existing) {
                    this.remoteStreams.push({
                        peerId,
                        name: peerName || 'Peer',
                        stream,
                    });
                }

                // Attach to video element (video calls) or create hidden audio element (voice calls)
                this.$nextTick(() => {
                    const videoEl = document.getElementById('remote-video-' + peerId);
                    if (videoEl) {
                        videoEl.srcObject = stream;
                    } else {
                        // Voice call — create a hidden <audio> element for this peer
                        let audioEl = document.getElementById('remote-audio-' + peerId);
                        if (!audioEl) {
                            audioEl = document.createElement('audio');
                            audioEl.id = 'remote-audio-' + peerId;
                            audioEl.autoplay = true;
                            audioEl.playsInline = true;
                            document.body.appendChild(audioEl);
                        }
                        audioEl.srcObject = stream;
                    }
                });
            };

            // Track disconnection timers per peer
            pc._disconnectTimer = null;

            pc.onconnectionstatechange = () => {
                console.log(`[CallEngine] Peer ${peerId} connection state: ${pc.connectionState}`);

                // Clear any pending disconnect timer on state change
                if (pc._disconnectTimer) {
                    clearTimeout(pc._disconnectTimer);
                    pc._disconnectTimer = null;
                }

                if (pc.connectionState === 'disconnected') {
                    // 'disconnected' is often temporary — give 10s to recover
                    pc._disconnectTimer = setTimeout(() => {
                        if (pc.connectionState === 'disconnected') {
                            console.warn(`[CallEngine] Peer ${peerId} still disconnected after 10s, removing`);
                            this.removePeer(peerId);
                        }
                    }, 10000);
                } else if (pc.connectionState === 'failed') {
                    // Nearly always a relay problem: no TURN server reachable and
                    // both peers behind NAT. Silence made this look like the app
                    // doing nothing at all.
                    console.error('[CallEngine] Connection failed for peer ' + peerId +
                        '; ICE servers in use:', this.iceServers.length);
                    this.showError('Could not connect the call. Check your network and try again.');
                    this.removePeer(peerId);
                }
            };

            if (createOffer) {
                pc.createOffer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: this.callType === 'video',
                }).then(offer => {
                    return pc.setLocalDescription(offer);
                }).then(() => {
                    console.log('[CallEngine] signal out: offer to', peerId);
                    this.$wire.sendSignal(
                        this.callUuid,
                        peerId,
                        'offer',
                        { sdp: pc.localDescription.toJSON() }
                    );
                }).catch(err => console.error('[CallEngine] could not build an offer:', err));
            }
        },

        async handleOffer(peerId, data) {
            // Last line of defence: an offer can still arrive before our own
            // media is ready (permission prompt, slow device, a renegotiation).
            // Answering without tracks is what makes a call one-way.
            if (!this.localStream) {
                await this.acquireMedia(this.callType);
            }

            if (!this.peers[peerId]) {
                this.createPeerConnection(peerId, null, false);
            }
            const pc = this.peers[peerId];

            await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));

            // Flush any ICE candidates that arrived before the remote description
            await this.flushPendingCandidates(peerId);

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            this.$wire.sendSignal(
                this.callUuid,
                peerId,
                'answer',
                { sdp: pc.localDescription.toJSON() }
            );
        },

        /** Put our microphone/camera on a peer connection. Safe to call twice. */
        attachLocalTracks(pc) {
            if (!this.localStream || pc._hasLocalTracks) return false;

            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
            pc._hasLocalTracks = true;

            return true;
        },

        /**
         * Media that arrived after a peer connection was already built (a late
         * permission grant, say) still has to reach the other side, and that
         * means a fresh offer. Only the side whose media was late renegotiates,
         * so the two peers cannot collide here.
         */
        async renegotiate(peerId) {
            const pc = this.peers[peerId];
            if (!pc || pc.signalingState !== 'stable') return;

            try {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                this.$wire.sendSignal(this.callUuid, peerId, 'offer', { sdp: pc.localDescription.toJSON() });
            } catch (err) {
                console.error('[CallEngine] Renegotiation failed:', err);
            }
        },

        async handleAnswer(peerId, data) {
            const pc = this.peers[peerId];
            if (pc) {
                await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
                // Flush any ICE candidates that arrived before the remote description
                await this.flushPendingCandidates(peerId);
            }
        },

        async handleIceCandidate(peerId, data) {
            const pc = this.peers[peerId];
            if (!pc || !data.candidate) return;

            // Buffer if remote description isn't set yet
            if (!pc.remoteDescription || !pc.remoteDescription.type) {
                if (!this._pendingCandidates[peerId]) {
                    this._pendingCandidates[peerId] = [];
                    // Safety net: if no offer/answer arrives within 15s the
                    // buffered candidates are stale — drop them so the next
                    // negotiation cycle (e.g. ICE restart) starts clean and
                    // we don't leak unbounded memory.
                    setTimeout(() => {
                        const stillPc = this.peers[peerId];
                        if (stillPc && (!stillPc.remoteDescription || !stillPc.remoteDescription.type)) {
                            console.warn('[CallEngine] Dropping ICE buffer for peer ' + peerId + ', no remote description after 15s');
                            delete this._pendingCandidates[peerId];
                        }
                    }, 15000);
                }
                this._pendingCandidates[peerId].push(data.candidate);
                return;
            }

            try {
                await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
            } catch (e) {
                // Ignore race-condition ICE errors
            }
        },

        async flushPendingCandidates(peerId) {
            const candidates = this._pendingCandidates[peerId] || [];
            this._pendingCandidates[peerId] = [];
            const pc = this.peers[peerId];
            if (!pc) return;
            for (const candidate of candidates) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(candidate));
                } catch (e) {
                    // Ignore
                }
            }
        },

        removePeer(peerId) {
            if (this.peers[peerId]) {
                if (this.peers[peerId]._disconnectTimer) {
                    clearTimeout(this.peers[peerId]._disconnectTimer);
                }
                this.peers[peerId].close();
                delete this.peers[peerId];
            }
            delete this._pendingCandidates[peerId];
            this.remoteStreams = this.remoteStreams.filter(s => s.peerId !== peerId);

            // Remove dynamically created audio element (voice calls)
            const audioEl = document.getElementById('remote-audio-' + peerId);
            if (audioEl) audioEl.remove();

            // If no peers remain during an active call, end it
            if (this.callState === 'active' && Object.keys(this.peers).length === 0) {
                console.warn('[CallEngine] No peers remaining, ending call');
                this.hangUp();
            }
        },

        // ── Media ──
        async acquireMedia(type) {
            // Callers now ask for media from several places; opening a second
            // microphone stream would leave the first one live and the call
            // half-connected.
            if (this.localStream) return;
            if (this._mediaPromise) return this._mediaPromise;

            this._mediaPromise = this._acquireMedia(type).finally(() => {
                this._mediaPromise = null;
            });

            return this._mediaPromise;
        },

        async _acquireMedia(type) {
            // Check if we're in a secure context (HTTPS or localhost)
            if (!window.isSecureContext) {
                console.warn('[CallEngine] Not a secure context, microphone/camera unavailable. Call will proceed without local media.');
                this.showError('Microphone/camera requires HTTPS. Audio may not work on this connection.');
                return;
            }

            try {
                const constraints = {
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                    },
                    video: type === 'video' ? { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } } : false,
                };
                this.localStream = await navigator.mediaDevices.getUserMedia(constraints);

                // Show local video
                if (type === 'video') {
                    this.$nextTick(() => {
                        const localVideo = document.getElementById('local-video');
                        if (localVideo) localVideo.srcObject = this.localStream;
                    });
                }

                // Any peer built while we had no media is currently sending
                // silence. Give it the tracks and offer again.
                for (const peerId of Object.keys(this.peers)) {
                    if (this.attachLocalTracks(this.peers[peerId])) {
                        await this.renegotiate(peerId);
                    }
                }
            } catch (err) {
                console.error('[CallEngine] Media access denied:', err);
                this.showError(
                    err.name === 'NotAllowedError'
                        ? 'Please allow camera/microphone access to make calls.'
                        : 'Could not access your camera or microphone. Call will continue without local audio.'
                );
                // Do NOT hang up — let the call continue; the remote side can still be heard
            }
        },

        toggleMute() {
            this.isMuted = !this.isMuted;
            if (this.localStream) {
                this.localStream.getAudioTracks().forEach(t => { t.enabled = !this.isMuted; });
            }
            this.$wire.toggleMute();
        },

        toggleVideo() {
            this.isVideoOff = !this.isVideoOff;
            if (this.localStream) {
                this.localStream.getVideoTracks().forEach(t => { t.enabled = !this.isVideoOff; });
            }
            this.$wire.toggleVideo();
        },

        toggleSpeaker() {
            this.isSpeaker = !this.isSpeaker;
            // Speaker toggle is primarily for mobile — toggle audio output
            document.querySelectorAll('video, audio').forEach(el => {
                if (el.setSinkId && this.isSpeaker) {
                    el.setSinkId('default').catch(() => {});
                }
            });
        },

        // ── Drag methods for floating modal ──
        startDrag(e) {
            this.isDragging = true;
            const modal = this.$refs.callModal;
            if (!modal) return;
            const rect = modal.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            this.dragOffset = { x: clientX - rect.left, y: clientY - rect.top };
            const onMove = (ev) => {
                if (!this.isDragging) return;
                const cx = ev.touches ? ev.touches[0].clientX : ev.clientX;
                const cy = ev.touches ? ev.touches[0].clientY : ev.clientY;
                this.modalPos = {
                    x: Math.max(0, Math.min(window.innerWidth - rect.width, cx - this.dragOffset.x)),
                    y: Math.max(0, Math.min(window.innerHeight - rect.height, cy - this.dragOffset.y)),
                };
            };
            const onUp = () => {
                this.isDragging = false;
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onUp);
        },

        // ── Hang up ──
        hangUp() {
            this.$wire.endCall(this.callUuid);
        },

        onCallEnded() {
            this.cleanup();
        },

        cleanup() {
            // Close all peer connections
            Object.keys(this.peers).forEach(id => {
                this.peers[id].close();
            });
            this.peers = {};

            // Remove dynamically created audio elements (voice calls)
            this.remoteStreams.forEach(s => {
                const audioEl = document.getElementById('remote-audio-' + s.peerId);
                if (audioEl) audioEl.remove();
            });
            this.remoteStreams = [];

            // Stop local media
            if (this.localStream) {
                this.localStream.getTracks().forEach(t => t.stop());
                this.localStream = null;
            }

            this.stopRingtone();
            this.stopRingTimer();
            this.stopJoinPoll();
            this._joinedPeers = [];
            this._seenSignals = [];
            clearInterval(this._durationInterval);
            clearTimeout(this._ringTimeout);

            this.callState = 'idle';
            this.callUuid = null;
            this.callId = null;
            this.incomingCall = null;
            this.calleeName = null;
            this.calleeAvatar = null;
            this.calleeOnline = false;
            this.modalPos = { x: null, y: null };
            this.isMuted = false;
            this.isVideoOff = false;
            this.callDuration = '00:00';
            this.ringTimer = '00:00';
            this.callParticipants = [];
        },

        // ── Timers ──
        startCallTimer() {
            this.callStartTime = Date.now();
            this._durationInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - this.callStartTime) / 1000);
                const m = String(Math.floor(elapsed / 60)).padStart(2, '0');
                const s = String(elapsed % 60).padStart(2, '0');
                this.callDuration = `${m}:${s}`;
            }, 1000);
        },

        startRingTimer() {
            const start = Date.now();
            this._ringInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - start) / 1000);
                const m = String(Math.floor(elapsed / 60)).padStart(2, '0');
                const s = String(elapsed % 60).padStart(2, '0');
                this.ringTimer = `${m}:${s}`;
            }, 1000);
        },

        stopRingTimer() {
            clearInterval(this._ringInterval);
            this.ringTimer = '00:00';
        },

        // ── Ringtone (Web Audio API — no file needed) ──
        _audioCtx: null,
        _oscillator: null,

        playRingtone() {
            // A phone also has to buzz: an AudioContext created without a prior
            // user gesture starts suspended, and on a page the user has not yet
            // touched it stays that way — a silent incoming call.
            this._startVibrating();

            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;

                this._audioCtx = new Ctx();

                if (this._audioCtx.state === 'suspended') {
                    this._audioCtx.resume()
                        .then(() => this._playRingLoop())
                        .catch(() => {
                            console.warn('[CallEngine] Ringtone blocked: audio is suspended until the page is interacted with. Vibrating instead.');
                        });
                    return;
                }

                this._playRingLoop();
            } catch (e) {
                console.warn('[CallEngine] Ringtone unavailable:', e.message);
            }
        },

        _startVibrating() {
            if (!navigator.vibrate) return;
            const buzz = () => { try { navigator.vibrate([600, 400]); } catch (_) {} };
            buzz();
            clearInterval(this._vibrateTimer);
            this._vibrateTimer = setInterval(buzz, 1200);
        },

        _stopVibrating() {
            clearInterval(this._vibrateTimer);
            this._vibrateTimer = null;
            try { navigator.vibrate && navigator.vibrate(0); } catch (_) {}
        },

        _playRingLoop() {
            if (!this._audioCtx || this.callState === 'active' || !this.incomingCall) return;

            const ctx = this._audioCtx;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.frequency.setValueAtTime(440, ctx.currentTime);
            osc.frequency.setValueAtTime(480, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);

            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);

            // Repeat every 1s
            this._ringTimeout2 = setTimeout(() => this._playRingLoop(), 1000);
        },

        stopRingtone() {
            this._stopVibrating();
            clearTimeout(this._ringTimeout2);
            if (this._audioCtx) {
                this._audioCtx.close().catch(() => {});
                this._audioCtx = null;
            }
        },

        showError(message) {
            // Use the browser notification or a toast
            if (window.Livewire) {
                // Dispatch a simple toast-like notification
                window.dispatchEvent(new CustomEvent('call-toast', { detail: { message } }));
            }
            console.warn('Call error:', message);
        },
    }));
});
