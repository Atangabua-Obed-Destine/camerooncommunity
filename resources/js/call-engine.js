/**
 * Cameroon Community — WebRTC Call Engine (Alpine.js component)
 * Handles peer connections, media streams, and signaling via Livewire + Echo.
 */
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('callEngine', (currentUserId, tenantId) => ({
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
            // Fetch TURN credentials from Metered
            this.fetchTurnServers();

            // Subscribe to user-specific call channel so we receive
            // incoming calls regardless of which room is currently open
            if (window.Echo) {
                const userCallChannel = `tenant.${tenantId}.user.${currentUserId}.calls`;
                this._userCallChannel = window.Echo.channel(userCallChannel);
                this._userCallChannel.listen('.CallStarted', (data) => {
                    if (data.initiated_by !== currentUserId) {
                        this.handleIncomingCall(data);
                    }
                });
                this._userCallChannel.listen('.CallUpdated', (data) => {
                    this.handleCallUpdate(data);
                });
            }

            // Also subscribe to room-specific channel when a room is selected
            // (needed for CallSignal and CallUpdated during active calls)
            window.addEventListener('room-selected', (e) => {
                const roomId = e.detail?.roomId;
                if (roomId) this.subscribeToRoom(roomId);
            });
        },

        subscribeToRoom(roomId) {
            if (this._echoChannel) {
                // Already subscribed — skip if same room
                if (this._echoChannel._roomId === roomId) return;
            }

            const channelName = `tenant.${tenantId}.room.${roomId}`;

            if (window.Echo) {
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
                const resp = await fetch('/camerooncommunity/public/api/turn-credentials', {
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
            if (this.callState !== 'idle') return; // already in a call

            this.incomingCall = {
                callUuid: data.call_uuid,
                callId: data.call_id,
                callType: data.call_type,
                roomId: data.room_id,
                callerName: data.caller_name,
                roomName: data.caller_name, // for DMs
            };
            this.callUuid = data.call_uuid;
            this.callType = data.call_type;
            this.callRoomId = data.room_id;

            // Subscribe to the room channel immediately so we receive
            // CallUpdated (ended/declined) even before accepting the call
            if (data.room_id) {
                this.subscribeToRoom(data.room_id);
            }

            // Play ringtone via Web Audio (no file needed)
            this.playRingtone();
        },

        // ── Accept call ──
        accept() {
            if (!this.incomingCall) return;

            this.stopRingtone();
            this.callerName = this.incomingCall.callerName;

            // Subscribe to the room channel for signaling (CallSignal, CallUpdated)
            if (this.incomingCall.roomId) {
                this.subscribeToRoom(this.incomingCall.roomId);
            }

            // Tell server we answered
            this.$wire.answerCall(this.incomingCall.callUuid);

            this.incomingCall = null;
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
                // Someone answered — transition to active
                if (this.callState === 'outgoing') {
                    this.callState = 'active';
                    clearTimeout(this._ringTimeout);
                    this.stopRingTimer();
                    this.startCallTimer();

                    // Refresh participants from Livewire
                    this.$wire.call('refreshParticipants').then(() => {
                        this.callParticipants = this.$wire.get('participants') || [];
                    });

                    // Create peer connection to the joined user
                    this.createPeerConnection(data.user_id, data.user_name, true);
                } else if (this.callState === 'active') {
                    // Additional person joining group call
                    this.createPeerConnection(data.user_id, data.user_name, true);
                    this.$wire.call('refreshParticipants').then(() => {
                        this.callParticipants = this.$wire.get('participants') || [];
                    });
                }
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

        // ── WebRTC Signaling ──
        handleSignal(data) {
            const peerId = data.from_user_id;

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

            const pc = new RTCPeerConnection({ iceServers: this.iceServers });
            this.peers[peerId] = pc;
            this._pendingCandidates[peerId] = [];

            // Add local tracks
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    pc.addTrack(track, this.localStream);
                });
            }

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
                    this.$wire.sendSignal(
                        this.callUuid,
                        peerId,
                        'offer',
                        { sdp: pc.localDescription.toJSON() }
                    );
                }).catch(err => console.error('Offer error:', err));
            }
        },

        async handleOffer(peerId, data) {
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
            // Check if we're in a secure context (HTTPS or localhost)
            if (!window.isSecureContext) {
                console.warn('[CallEngine] Not a secure context — microphone/camera unavailable. Call will proceed without local media.');
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
            try {
                this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                this._playRingLoop();
            } catch (e) { /* Audio not available */ }
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
