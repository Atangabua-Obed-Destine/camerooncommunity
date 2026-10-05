/**
 * The Yard chat room's client-side behaviour.
 *
 * This lived in a <script> tag inside the Livewire component, which meant 66 KB
 * of JavaScript travelled with the component's HTML — on the first paint and
 * again on every round trip, since Livewire re-renders the whole component to
 * send a message, add a reaction or load older messages. Here it is part of the
 * bundle: fetched once, cached, and parsed once.
 *
 * The handful of server-side values it needs (who the viewer is, the receipts
 * channel, delivery statuses, a draft in progress) arrive as `cfg` from the
 * x-data attribute instead of being baked into the script.
 */
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    if (! Alpine) return;

    Alpine.data('forwardModal', () => {
            return {
                show: false,
                msgId: null,
                msgIds: [],
                search: '',
                // Takes one message or a selection; the rest of the modal does
                // not care which, so the list is the single source of truth.
                openForward(msgId, msgIds) {
                    this.msgIds = (msgIds && msgIds.length) ? [...msgIds] : (msgId ? [msgId] : []);
                    this.msgId = this.msgIds[0] ?? null;
                    this.search = '';
                    this.show = true;
                },
                doForward(roomId) {
                    const ids = this.msgIds.length ? this.msgIds : [this.msgId];

                    this.$wire.forwardMessages(ids, roomId).then((result) => {
                        this.show = false;
                        this.msgId = null;
                        this.msgIds = [];
                        window.dispatchEvent(new CustomEvent('clear-selection'));
                        // Only navigate to the target room when the forward
                        // actually succeeded. PHP returns false when the
                        // target is a DM with a blocked / unconnected user.
                        if (result) {
                            Livewire.dispatch('room-selected', { roomId: roomId });
                            Livewire.dispatch('refreshRoomList');
                        }
                    });
                }
            };
    });

        // lastSeen() now lives in resources/js/app.js as an Alpine.data
        // component. It used to be defined here, inside the component, and a
        // Livewire morph could initialise the status span before this script
        // had run — "label is not defined" in the console, and no status.

    Alpine.data('chatUi', (cfg = {}) => {
            return {
                // Messages painted straight from a broadcast, waiting for the
                // server refresh to replace them with the real thing.
                incoming: [],
                _syncTimer: null,
                _lastBroadcast: null,

                // Whether the thread is parked at the newest message, and what
                // has arrived since it stopped being.
                atBottom: true,
                newSinceScroll: 0,

                // The "calls are not available yet" notice.
                callsNotice: false,

                typingUsers: [],
                lightboxOpen: false,
                lightboxSrc: '',
                _typingTimers: {},
                optimistic: [],
                _optId: 0,
                _echoChannel: null,
                _echoChannelName: null,
                _receiptsChannelName: null,
                _statusPoll: null,
                _prevScrollHeight: null,
                _prevScrollTop: null,
                _autoLoading: false,

                // True from the moment a room is asked for until its messages are
                // scrolled into place; the overlay above watches it.
                positioning: false,

                // ── Selection mode (WhatsApp's multi-select) ──
                // ids keeps the order people picked things in; texts and own are
                // kept alongside so Copy and Delete do not have to go digging
                // through the DOM or ask the server what it already sent us.
                sel: { on: false, ids: [], texts: {}, own: {} },

                // What actually drops the keyboard on a phone is the browser's own
                // long-press text selection: it starts a selection in the thread, the
                // composer blurs, and the keyboard goes down with it. WhatsApp has no
                // native selection in a chat at all — Copy lives in the selection bar
                // instead — so it is switched off below 768px, and only there. This is
                // an Alpine binding rather than a CSS class (a new class would force a
                // Tailwind rebuild) and rather than a plain inline style (Livewire's
                // morph strips attributes the server did not send).

                init() {
                    // Hydrate the global msgStatus store with server-computed statuses
                    // so ticks render correctly on first paint and after Livewire updates.
                    this.hydrateStatuses();

                    // Subscribe ONCE to this user's private receipts channel for tick updates.
                    if (cfg.receiptsChannel) {
                    this.subscribeReceipts(cfg.receiptsChannel);
                    }

                    // Poll DM partner status every 30 seconds
                    this._statusPoll = setInterval(() => {
                        if (this.$wire) this.$wire.pollDmStatus();
                    }, 30000);
                },
                destroy() {
                    if (this._statusPoll) clearInterval(this._statusPoll);
                    clearTimeout(this._syncTimer);

                    // Release our own handlers only. The call engine listens on
                    // the room channel too, and leaving it outright used to take
                    // its listeners down with ours.
                    if (this._receiptsChannelName) {
                        window.cnRealtime?.release(this._receiptsChannelName, this);
                        this._receiptsChannelName = null;
                    }
                    if (this._echoChannelName) {
                        window.cnRealtime?.release(this._echoChannelName, this);
                        this._echoChannelName = null;
                    }
                },

                hydrateStatuses() {
                    if (cfg.messageStatuses && Object.keys(cfg.messageStatuses).length) {
                    const initial = cfg.messageStatuses;
                    if (window.Alpine && window.Alpine.store) {
                        const store = window.Alpine.store('msgStatus');
                        for (const id in initial) {
                            // Don't downgrade existing (e.g. 'read' should not go back to 'sent').
                            const cur = store[id];
                            const next = initial[id];
                            if (!cur || this._statusRank(next) > this._statusRank(cur)) {
                                store[id] = next;
                            }
                        }
                    }
                    }
                },

                _statusRank(s) {
                    return { 'sending': 0, 'sent': 1, 'delivered': 2, 'read': 3 }[s] ?? 0;
                },

                subscribeReceipts(channelName) {
                    if (! channelName) return;

                    // Asking the registry rather than trusting a local flag: the
                    // flag said "subscribed" while the channel had been torn down
                    // underneath us, and ticks then stopped advancing for good.
                    if (window.cnRealtime?.holds(channelName, this)) return;

                    if (this._receiptsChannelName && this._receiptsChannelName !== channelName) {
                        window.cnRealtime?.release(this._receiptsChannelName, this);
                    }

                    const self = this;

                    const ok = window.cnRealtime?.join(channelName, this, {
                        '.MessageDelivered': (e) => {
                            // Sender side: a recipient confirmed delivery.
                            // Only upgrade to 'delivered' if ALL recipients have delivered.
                            if (!e || !e.message_id) return;
                            const store = window.Alpine.store('msgStatus');
                            const cur = store[e.message_id];
                            if (e.all_delivered && self._statusRank('delivered') > self._statusRank(cur)) {
                                store[e.message_id] = 'delivered';
                            } else if (!cur) {
                                store[e.message_id] = 'sent';
                            }
                        },
                        '.MessageRead': (e) => {
                            // Sender side: recipient(s) opened the room and read.
                            if (!e || !e.message_ids) return;
                            const store = window.Alpine.store('msgStatus');
                            const allRead = e.all_read || {};
                            for (const id of e.message_ids) {
                                if (allRead[id]) {
                                    store[id] = 'read';
                                } else if (self._statusRank('delivered') > self._statusRank(store[id])) {
                                    store[id] = 'delivered';
                                }
                            }
                        },
                    });

                    if (ok) this._receiptsChannelName = channelName;
                },

                // ── Context Menu state ──
                // The shared hover toolbar: which bubble it is over, and where.
                hov: { open: false, pick: false, own: false, x: 0, y: 0, d: null },
                _hovTimer: null,
                _hoverBarHome: null,

                ctx: {
                    open: false,
                    moreEmojis: false,
                    msgId: null,
                    isOwn: false,
                    msgType: '',
                    content: '',
                    isPinned: false,
                    posX: 0,
                    posY: 0,
                    moreMenu: false,
                    quickEmojis: ['👍','❤️','😂','😮','😢','🙏'],
                    extraEmojis: ['👎','🔥','🎉','💯','🤩','😍','🥳','🤔','😎','💀','👏','✨','🤝','😈','🥶','🥵','😤','🤡','💎','🌟'],
                },

                /**
                 * Show the shared hover toolbar over a bubble.
                 *
                 * Positioned in viewport coordinates because the thread
                 * scrolls underneath it; a scroll hides it rather than letting
                 * it drift away from the message it belongs to.
                 */
                hovShow(el, detail) {
                    if (this.sel.on || this.ctx.open) return;
                    if (! window.matchMedia('(hover: hover)').matches) return;

                    clearTimeout(this._hovTimer);

                    // Moved into the bubble rather than positioned over it.
                    // Viewport coordinates drifted the moment anything scrolled
                    // or reflowed, which is how the bar ended up sitting on top
                    // of the message instead of beside it. Inside the bubble,
                    // ordinary absolute positioning puts it where the old
                    // per-bubble buttons were, and it cannot come adrift.
                    const bar = this.$refs.hoverBar;

                    if (bar && bar.parentElement !== el) {
                        // Remember where it lives, so it can be put back. A
                        // Livewire morph that replaces the hovered bubble would
                        // otherwise take the only copy of the bar with it, and
                        // hovering would do nothing for the rest of the session.
                        this._hoverBarHome ??= bar.parentElement;
                        el.appendChild(bar);
                    }

                    this.hov.d = detail;
                    this.hov.own = !! detail.isOwn;
                    this.hov.open = true;
                    this.hov.pick = false;
                },

                /**
                 * Leave with a grace period, so crossing the gap between the
                 * bubble and the toolbar does not close it mid-reach.
                 */
                hovLeave() {
                    clearTimeout(this._hovTimer);
                    this._hovTimer = setTimeout(() => {
                        this.hov.open = false;
                        this.hov.pick = false;
                        this.parkHoverBar();
                    }, 180);
                },

                hovKeep() {
                    clearTimeout(this._hovTimer);
                },

                hovHide() {
                    clearTimeout(this._hovTimer);
                    this.hov.open = false;
                    this.hov.pick = false;
                    this.parkHoverBar();
                },

                /** Return the bar to the component root, out of the morph's way. */
                parkHoverBar() {
                    const bar = this.$refs.hoverBar;

                    if (bar && this._hoverBarHome && bar.parentElement !== this._hoverBarHome) {
                        this._hoverBarHome.appendChild(bar);
                    }
                },

                hovReact(em) {
                    if (this.hov.d) this.$wire.toggleReaction(this.hov.d.msgId, em);
                    this.hovHide();
                },

                /** Hand off to the full menu, anchored where the toolbar is. */
                hovMore() {
                    const d = this.hov.d;
                    const bar = this.$refs.hoverBar;
                    const r = bar?.parentElement?.getBoundingClientRect();

                    this.hovHide();

                    if (d) {
                        this.ctxOpen({
                            ...d,
                            x: r ? r.left : 0,
                            y: r ? r.bottom : 0,
                        });
                    }
                },

                ctxOpen(detail) {
                    // A right-click (and some long presses) leaves a half-made
                    // selection behind the menu, which then sits there
                    // highlighted until the user clicks somewhere to get rid
                    // of it.
                    this.dropNativeSelection();
                    this.ctx.msgId = detail.msgId;
                    this.ctx.isOwn = detail.isOwn;
                    this.ctx.msgType = detail.msgType;
                    this.ctx.content = detail.content || '';
                    this.ctx.isPinned = detail.isPinned;
                    this.ctx.moreEmojis = false;
                    this.ctx.moreMenu = false;

                    // will-change:transform on .yard-panel makes position:fixed
                    // relative to that ancestor — offset coords accordingly
                    const container = this.$root.closest('.yard-panel') || this.$root;
                    const cr = container.getBoundingClientRect();

                    // Only the reaction bar is positioned now; the actions moved to
                    // the selection bar at the top, WhatsApp-style.
                    const menuW = 300, menuH = 60;
                    let x = detail.x - cr.left;
                    let y = detail.y - cr.top;
                    const cw = cr.width, ch = cr.height;
                    if (x + menuW > cw) x = cw - menuW - 8;
                    if (y + menuH > ch) y = ch - menuH - 8;
                    if (x < 8) x = 8;
                    if (y < 8) y = 8;
                    this.ctx.posX = x;
                    this.ctx.posY = y;
                    this.ctx.open = true;
                },

                ctxClose() {
                    this.ctx.open = false;
                    this.ctx.moreEmojis = false;
                    this.ctx.moreMenu = false;
                },

                // Long-press to select, the way WhatsApp does on a phone.
                // 450ms matches the platform feel; any movement cancels it so a
                // scroll never turns into a selection.
                lpStart(e, detail) {
                    this.lpCancel();
                    this.dropNativeSelection();
                    const t = e.touches ? e.touches[0] : e;
                    this._lpX = t.clientX; this._lpY = t.clientY;
                    this._lpFired = false;
                    // The reaction bar is anchored to the bubble, not to the finger:
                    // opening it under the touch point put its buttons exactly where
                    // the finger was about to lift. The rect is read now, because the
                    // event is gone by the time the timer runs.
                    const rect = e.currentTarget && e.currentTarget.getBoundingClientRect
                        ? e.currentTarget.getBoundingClientRect()
                        : null;
                    // Was the composer focused? Then the keyboard is up, and it has
                    // to stay up while the message is selected, like WhatsApp.
                    this._lpFocused = !!this.$refs.msgInput && document.activeElement === this.$refs.msgInput;
                    this._lpTimer = setTimeout(() => {
                        this._lpTimer = null;
                        this._lpFired = true;
                        if (navigator.vibrate) navigator.vibrate(12);
                        // A long press both selects the message and offers the
                        // reactions, exactly as WhatsApp does. Tapping further
                        // messages then extends the selection.
                        this.selStart(detail);
                        this.ctxOpen({
                            ...detail,
                            x: rect ? rect.left : this._lpX,
                            y: rect ? rect.top - 56 : this._lpY,
                        });
                        this.restoreKeyboard();
                    }, 450);
                },

                lpMove(e) {
                    if (!this._lpTimer) return;
                    const t = e.touches ? e.touches[0] : e;
                    // A thumb moves while it presses. 10px cancelled perfectly
                    // deliberate long presses, which is why the reaction bar
                    // only showed up sometimes; a scroll is a much larger,
                    // mostly vertical movement than this.
                    if (Math.abs(t.clientX - this._lpX) > 16 || Math.abs(t.clientY - this._lpY) > 24) {
                        this.lpCancel();
                    }
                },

                // Lifting the finger after a long press fires a click wherever the
                // finger is — which is now on top of the freshly opened reaction bar,
                // so the press itself was tapping '+' (or an emoji). Cancelling the
                // touchend default stops that synthetic click being generated at all.
                lpEnd(e) {
                    if (this._lpFired) {
                        this._lpFired = false;
                        if (e.cancelable) e.preventDefault();
                        this.swallowNextClick();
                    }
                    this.lpCancel();
                },

                // Belt and braces for browsers that emit the click anyway: eat the
                // very next one, then stop listening.
                swallowNextClick() {
                    const eat = (ev) => {
                        ev.preventDefault();
                        ev.stopPropagation();
                        cleanup();
                    };
                    const cleanup = () => {
                        document.removeEventListener('click', eat, true);
                        clearTimeout(this._lpClickTimer);
                    };
                    document.addEventListener('click', eat, true);
                    this._lpClickTimer = setTimeout(cleanup, 700);
                },

                lpCancel() {
                    if (this._lpTimer) { clearTimeout(this._lpTimer); this._lpTimer = null; }
                },

                // ── Selection mode ───────────────────────────────────────────
                selHas(id) {
                    return this.sel.ids.includes(id);
                },

                /**
                 * Throw away any native text selection.
                 *
                 * Picking messages and selecting text are two different modes,
                 * and leaving the browser's highlight on screen while the app's
                 * own selection runs is what makes the thread feel like a web
                 * page being wrestled with rather than an app.
                 */
                dropNativeSelection() {
                    try {
                        const s = window.getSelection();
                        if (s && ! s.isCollapsed) s.removeAllRanges();
                    } catch (_) { /* not worth failing a long press over */ }
                },

                /** Enter selection mode on one message (long press, or Select in the menu). */
                selStart(detail) {
                    this.dropNativeSelection();
                    this.sel.on = true;
                    this.sel.ids = [];
                    this.sel.texts = {};
                    this.sel.own = {};
                    this.selAdd(detail);
                },

                selAdd(detail) {
                    if (! this.selHas(detail.msgId)) {
                        this.sel.ids.push(detail.msgId);
                    }
                    this.sel.texts[detail.msgId] = detail.content || '';
                    this.sel.own[detail.msgId] = !! detail.isOwn;
                },

                /** Tapping a message while selecting adds or removes it. */
                selToggle(detail) {
                    if (this.selHas(detail.msgId)) {
                        this.sel.ids = this.sel.ids.filter(i => i !== detail.msgId);
                        delete this.sel.texts[detail.msgId];
                        delete this.sel.own[detail.msgId];

                        // Last one unpicked: leave selection mode, like WhatsApp.
                        if (this.sel.ids.length === 0) {
                            this.selClear();
                            return;
                        }
                    } else {
                        this.selAdd(detail);
                    }

                    // Reactions belong to a single message; once this is a batch
                    // the emoji row has nothing to act on.
                    if (this.sel.ids.length !== 1) {
                        this.ctx.open = false;
                        this.ctx.moreEmojis = false;
                    }
                },

                selClear() {
                    this.sel = { on: false, ids: [], texts: {}, own: {} };
                    this.ctxClose();
                },

                /** How many of the selected messages this user may delete. */
                selOwnCount() {
                    return this.sel.ids.filter(id => this.sel.own[id]).length;
                },

                selCopy() {
                    // Chronological, not pick order: a pasted conversation should
                    // read the way it happened.
                    const text = [...this.sel.ids]
                        .sort((a, b) => a - b)
                        .map(id => this.sel.texts[id])
                        .filter(t => t)
                        .join('\n');

                    if (! text) {
                        this.selClear();
                        return;
                    }

                    navigator.clipboard?.writeText(text).then(() => {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: { type: 'success', message: this.$store.lang.t('Copied', 'Copié') },
                        }));
                    }).catch(() => {});

                    this.selClear();
                },

                selStar() {
                    this.$wire.starMessages([...this.sel.ids]);
                    this.selClear();
                },

                selForward() {
                    window.dispatchEvent(new CustomEvent('open-forward', {
                        detail: { msgIds: [...this.sel.ids] },
                    }));
                    // Keep the selection until the forward modal has used it.
                    this.sel.on = false;
                    this.ctxClose();
                },

                selDelete() {
                    const mine = this.sel.ids.filter(id => this.sel.own[id]);

                    if (mine.length === 0) {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                type: 'warning',
                                message: this.$store.lang.t(
                                    'You can only delete your own messages.',
                                    'Vous ne pouvez supprimer que vos propres messages.'
                                ),
                            },
                        }));
                        return;
                    }

                    const question = this.$store.lang.t(
                        'Delete ' + mine.length + ' message(s)?',
                        'Supprimer ' + mine.length + ' message(s) ?'
                    );
                    if (! window.confirm(question)) return;

                    this.$wire.deleteMessages(mine);
                    this.selClear();
                },

                // Keeping the on-screen keyboard up while a message is selected.
                // A touch outside the composer blurs it and the keyboard drops with
                // it; preventing the touch pointerdown default stops that focus
                // change. click still fires, and scrolling is untouched (that is
                // governed by touch-action, not by this). Mouse pointers are left
                // alone, so selecting message text on desktop still works.
                keepKeyboard(e) {
                    if (e.pointerType !== 'touch') return;
                    const ta = this.$refs.msgInput;
                    if (ta && document.activeElement === ta) e.preventDefault();
                },

                // Fallback for browsers that blur regardless: put focus back without
                // scrolling the thread, and only if the keyboard was already up.
                restoreKeyboard() {
                    if (!this._lpFocused) return;
                    const ta = this.$refs.msgInput;
                    if (!ta || document.activeElement === ta) return;
                    try { ta.focus({ preventScroll: true }); } catch (_) { ta.focus(); }
                },

                ctxReact(emoji) {
                    this.$wire.toggleReaction(this.ctx.msgId, emoji);
                    this.ctxClose();
                },

                ctxCopy() {
                    if (this.ctx.content && navigator.clipboard) {
                        navigator.clipboard.writeText(this.ctx.content);
                    }
                    this.ctxClose();
                },

                ctxAction(type) {
                    const id = this.ctx.msgId;
                    this.ctxClose();
                    switch(type) {
                        case 'reply':
                            this.$wire.setReply(id);
                            break;
                        case 'forward':
                            window.dispatchEvent(new CustomEvent('open-forward', { detail: { msgId: id } }));
                            break;
                        case 'star':
                            this.$wire.toggleStar(id);
                            break;
                        case 'pin':
                            this.$wire.togglePin(id);
                            break;
                        case 'edit':
                            this.$wire.startEdit(id);
                            break;
                        case 'translate-en':
                            this.$wire.translateMessage(id, 'en');
                            break;
                        case 'translate-fr':
                            this.$wire.translateMessage(id, 'fr');
                            break;
                        case 'report':
                            if (confirm('Report this message?')) {
                                this.$wire.reportMessage(id);
                            }
                            break;
                        case 'delete':
                            if (confirm('Delete this message?')) {
                                this.$wire.deleteMessage(id);
                            }
                            break;
                    }
                },

                /**
                 * A message arrived. Show it now; tell the server later.
                 *
                 * Asking Livewire to re-render costs the whole thread — around
                 * 190 KB — and it used to happen twice per message, once to
                 * receive it and again to mark it read. On a phone that is the
                 * difference between a message landing instantly and landing
                 * two seconds later, and in a lively room the round trips pile
                 * up behind each other.
                 *
                 * So the bubble is painted straight from the broadcast, the way
                 * our own messages already are while they send, and the server
                 * refresh is coalesced: one for a burst of messages instead of
                 * two per message. The refresh replaces these bubbles with the
                 * real ones, which carry reactions, receipts and the rest.
                 */
                onMessageBroadcast(e) {
                    this._lastBroadcast = e;

                    const text = typeof e.content === 'string' ? e.content : '';
                    const plain = e.message_type === 'text' && text !== '';

                    if (e.user_id !== cfg.userId && ! this.atBottom) {
                        this.newSinceScroll++;
                    }

                    if (plain && e.user_id !== cfg.userId) {
                        this.incoming.push({
                            key: e.id,
                            name: e.user_name || '',
                            initial: (e.user_name || '?').charAt(0).toUpperCase(),
                            avatar: e.user_avatar ? `${cfg.storageUrl}/${e.user_avatar}` : null,
                            text,
                        });
                        this.scrollIfFollowing();
                    }

                    // Anything we cannot paint ourselves — media, a poll, a
                    // system notice — needs the server now rather than in a
                    // moment, because nothing is on screen for it.
                    this.syncMessages(! plain);
                },

                /**
                 * Pull the authoritative thread from the server.
                 *
                 * Coalesced: a burst of messages produces one refresh. `now`
                 * skips the wait when there is nothing on screen to cover it.
                 */
                syncMessages(now = false) {
                    clearTimeout(this._syncTimer);

                    const run = () => {
                        this._syncTimer = null;
                        this.$wire.call('onMessageReceived', this._lastBroadcast || {})
                            .then(() => {
                                // Held briefly: dropping them in the same frame
                                // as the morph makes the thread flicker.
                                setTimeout(() => { this.incoming = []; }, 120);
                                this.scrollIfFollowing();
                            })
                            .catch(() => {
                                // The painted bubbles stay rather than vanishing
                                // on a failed refresh; the next one clears them.
                            });
                    };

                    if (now) { run(); return; }

                    this._syncTimer = setTimeout(run, 700);
                },

                subscribeEcho(channelName) {
                    if (! channelName) return;

                    // The registry is the source of truth, not a local flag. The
                    // flag said we were still subscribed while the call engine's
                    // Echo.leave() had destroyed the shared channel, so this
                    // returned early and the thread never received another
                    // message — with the socket still connected.
                    if (window.cnRealtime?.holds(channelName, this)) return;

                    if (this._echoChannelName && this._echoChannelName !== channelName) {
                        window.cnRealtime?.release(this._echoChannelName, this);
                    }

                    if (! window.Echo) {
                        console.warn('Laravel Echo not available');
                        return;
                    }

                    const component = this.$wire;
                    const self = this;

                    const ok = window.cnRealtime?.join(channelName, this, {
                        '.MessageSent': (e) => {
                            self.onMessageBroadcast(e);
                        },
                        '.MessageDeleted': (e) => {
                            component.onMessageDeleted(e);
                        },
                        '.UserTyping': (e) => {
                            self.onTypingReceived(e);
                        },
                        '.JoinRequestReceived': (e) => {
                            // Refresh room-info panel so admin sees the pending request
                            window.dispatchEvent(new CustomEvent('join-request-received', { detail: e }));
                        },
                        '.room.updated': (e) => {
                            // Membership / avatar / name change in this room.
                            // Refresh both the chat header (member count, avatar)
                            // and the room-info side panel (member roster).
                            try {
                                if (window.Livewire) {
                                    window.Livewire.dispatch('room-updated');
                                }
                                window.dispatchEvent(new CustomEvent('room-updated', { detail: e }));

                                // If the current user was the one removed, kick them back to the list.
                                const myId = cfg.userId;
                                if (e && e.kind === 'member_removed' && e.payload && e.payload.user_id === myId) {
                                    window.dispatchEvent(new CustomEvent('room-selected', { detail: { roomId: null } }));
                                }
                            } catch (err) {
                                console.warn('room.updated handler failed', err);
                            }
                        },
                    });

                    if (ok) this._echoChannelName = channelName;
                },

                // Raise the curtain while a room opens, and never leave it up: a
                // failed or dropped request must not hide the chat for good.
                beginPositioning() {
                    this.positioning = true;
                    clearTimeout(this._posTimer);
                    this._posTimer = setTimeout(() => { this.positioning = false; }, 4000);
                },

                endPositioning() {
                    clearTimeout(this._posTimer);
                    this.positioning = false;
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.chatMessages;
                        if (el) el.scrollTop = el.scrollHeight;
                        this.atBottom = true;
                        this.newSinceScroll = 0;
                        this.endPositioning();
                    });
                },

                /**
                 * Is the newest message on screen?
                 *
                 * A margin, not an exact match: sub-pixel heights and the
                 * composer's own growth mean scrollTop rarely lands exactly at
                 * the end, and a jump button that shows while the reader is
                 * plainly at the bottom is worse than none.
                 */
                trackBottom(el) {
                    const distance = el.scrollHeight - el.scrollTop - el.clientHeight;
                    const wasAtBottom = this.atBottom;

                    this.atBottom = distance < 120;

                    // Reaching the bottom is itself an acknowledgement.
                    if (this.atBottom && ! wasAtBottom) {
                        this.newSinceScroll = 0;
                    }
                },

                /** The button: back to the newest message, counter cleared. */
                jumpToLatest() {
                    const el = this.$refs.chatMessages;

                    if (el) {
                        el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
                    }

                    this.atBottom = true;
                    this.newSinceScroll = 0;
                },

                /**
                 * Follow the conversation only when already following it.
                 *
                 * Scrolling on every arrival pulled the thread out from under
                 * anyone reading back through it.
                 */
                scrollIfFollowing() {
                    if (this.atBottom) this.scrollToBottom();
                },

                /**
                 * WhatsApp-style: when a room opens, jump to the first unread
                 * message (if any) or to the bottom. Server passes the target
                 * id via the 'chat-position-target' event after loadRoom().
                 * Messages may not be in the DOM yet — retry briefly.
                 */
                scrollToTarget(messageId) {
                    const self = this;
                    this.$nextTick(() => {
                        if (!messageId) { self.scrollToBottom(); return; }
                        let attempts = 0;
                        const maxAttempts = 12;
                        const tick = () => {
                            const container = self.$refs.chatMessages;
                            const el = document.getElementById('msg-' + messageId);
                            if (el && container) {
                                // Align the first unread message near the top of the viewport
                                container.scrollTop = Math.max(0, el.offsetTop - 12);
                                self.endPositioning();
                                return;
                            }
                            if (++attempts < maxAttempts) {
                                requestAnimationFrame(tick);
                            } else {
                                self.scrollToBottom();
                            }
                        };
                        tick();
                    });
                },

                /**
                 * Try to scroll to a specific message id (used by Starred messages).
                 * The message may not be in the DOM yet if the room just opened or
                 * if it's older than the loaded page — we retry briefly.
                 */
                /**
                 * Bring a message into view and flash it.
                 *
                 * Unlike scrollToMessageId below, this one will fetch older pages
                 * when the message is not loaded yet, which is the common case for
                 * a reply to something said a while ago.
                 */
                jumpToMessage(id, depth = 0) {
                    if (!id) return;

                    const el = document.getElementById('msg-' + id);
                    if (el) {
                        this.flashMessage(el);
                        return;
                    }

                    // Not rendered: pull in another page and look again. Three
                    // rounds is 150 messages, past which "scroll up" is fairer
                    // than loading the whole history.
                    if (depth < 3 && this.$wire.hasMore) {
                        this.$wire.loadMore().then(() => {
                            setTimeout(() => this.jumpToMessage(id, depth + 1), 250);
                        });
                        return;
                    }

                    window.dispatchEvent(new CustomEvent('toast', { detail: {
                        type: 'info',
                        message: this.$store.lang.t(
                            'That message is too far back to jump to.',
                            'Ce message est trop ancien pour y accéder directement.'
                        ),
                    }}));
                },

                /** Scroll a message into the middle and ring it briefly. */
                flashMessage(el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    const bubble = el.querySelector('.yard-msg__bubble') || el;
                    const prevTransition = bubble.style.transition;
                    const prevShadow = bubble.style.boxShadow;

                    bubble.style.transition = 'box-shadow 200ms ease-out';
                    bubble.style.boxShadow = '0 0 0 3px rgba(252, 209, 22, 0.7)';

                    setTimeout(() => {
                        bubble.style.boxShadow = prevShadow;
                        setTimeout(() => { bubble.style.transition = prevTransition; }, 250);
                    }, 1200);
                },

                scrollToMessageId(id) {
                    if (!id) return;
                    let attempts = 0;
                    const maxAttempts = 12; // ~1.8s total
                    const tick = () => {
                        const el = document.getElementById('msg-' + id);
                        if (el) {
                            this.flashMessage(el);
                            return;
                        }
                        if (++attempts < maxAttempts) {
                            setTimeout(tick, 150);
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', { detail: {
                                type: 'info',
                                message: 'Scroll up in the chat to find this message.',
                            }}));
                        }
                    };
                    // Small initial delay so the room/messages have time to render.
                    setTimeout(tick, 200);
                },

                typingLabel() {
                    if (this.typingUsers.length === 1) return this.typingUsers[0] + ' is typing...';
                    if (this.typingUsers.length === 2) return this.typingUsers.join(' and ') + ' are typing...';
                    return this.typingUsers.length + ' people are typing...';
                },






                openLightbox(src) {
                    this.lightboxSrc = src;
                    this.lightboxOpen = true;
                },

                onTypingReceived(data) {
                    const name = data.user_name;
                    if (!this.typingUsers.includes(name)) {
                        this.typingUsers.push(name);
                    }
                    clearTimeout(this._typingTimers[name]);
                    this._typingTimers[name] = setTimeout(() => {
                        this.typingUsers = this.typingUsers.filter(u => u !== name);
                    }, 3000);
                }
            };
    });

    Alpine.data('inputBar', (cfg = {}) => {
            return {
                emojiOpen: false,
                recording: false,
                recPaused: false,
                recSeconds: 0,
                recTimerLabel: '0:00',
                mediaRecorder: null,
                audioChunks: [],
                _typingTimeout: null,
                _recTimer: null,
                _animFrame: null,
                _analyser: null,
                _stream: null,
                voiceUploading: false,
                voiceProgress: 0,
                msgText: cfg.newMessage || '',

                // ── @mention autocomplete ──
                mentionOpen: false,
                mentionResults: [],
                mentionIndex: 0,
                mentionQuery: '',
                _mentionAnchor: -1,
                _mentionDebounce: null,

                /** Detect an @ token at the caret and ask the server for matches. */
                checkMention() {
                    const ta = this.$refs.msgInput;
                    if (!ta) { this.mentionOpen = false; return; }
                    const caret = ta.selectionStart || 0;
                    const upToCaret = (this.msgText || '').slice(0, caret);
                    // Match the trailing "@username" token (or "@" alone), allowing it at the
                    // start of input or after whitespace.
                    const m = upToCaret.match(/(?:^|\s)@([a-zA-Z0-9_]{0,32})$/);
                    if (!m) {
                        this.mentionOpen = false;
                        this._mentionAnchor = -1;
                        return;
                    }
                    this._mentionAnchor = caret - m[1].length - 1; // position of '@'
                    this.mentionQuery = m[1];
                    if (this._mentionDebounce) clearTimeout(this._mentionDebounce);
                    this._mentionDebounce = setTimeout(async () => {
                        try {
                            const res = await this.$wire.mentionSuggest(this.mentionQuery);
                            this.mentionResults = Array.isArray(res) ? res : [];
                            this.mentionIndex = 0;
                            this.mentionOpen = this.mentionResults.length > 0;
                        } catch (e) {
                            this.mentionOpen = false;
                        }
                    }, 120);
                },
                /** Replace the current "@token" with "@username " and close picker. */
                pickMention(idx) {
                    if (!this.mentionResults[idx]) return;
                    const user = this.mentionResults[idx];
                    const username = user.username || user.name || '';
                    if (!username) { this.mentionOpen = false; return; }
                    const ta = this.$refs.msgInput;
                    if (!ta || this._mentionAnchor < 0) { this.mentionOpen = false; return; }
                    const before = (this.msgText || '').slice(0, this._mentionAnchor);
                    const after = (this.msgText || '').slice(ta.selectionStart || 0);
                    const inserted = '@' + username + ' ';
                    this.msgText = before + inserted + after;
                    this.mentionOpen = false;
                    this._mentionAnchor = -1;
                    this.mentionQuery = '';
                    this.$nextTick(() => {
                        ta.value = this.msgText;
                        const newPos = before.length + inserted.length;
                        ta.selectionStart = ta.selectionEnd = newPos;
                        ta.focus();
                    });
                },
                mentionKey(e) {
                    if (!this.mentionOpen) return false;
                    if (e.key === 'ArrowDown') { e.preventDefault(); this.mentionIndex = Math.min(this.mentionIndex + 1, this.mentionResults.length - 1); return true; }
                    if (e.key === 'ArrowUp') { e.preventDefault(); this.mentionIndex = Math.max(this.mentionIndex - 1, 0); return true; }
                    if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this.pickMention(this.mentionIndex); return true; }
                    if (e.key === 'Escape') { e.preventDefault(); this.mentionOpen = false; return true; }
                    return false;
                },

                // ── Poll Builder state ──
                pollOpen: false,
                pollDraft: {
                    question: '',
                    options: ['', ''],
                    allowMultiple: false,
                },
                ensureOptionRow() {
                    // Auto-grow: if last input has text and we're under 12, add a fresh blank.
                    const opts = this.pollDraft.options;
                    if (opts.length < 12 && opts[opts.length - 1].trim() !== '') {
                        opts.push('');
                    }
                },
                removeOption(i) {
                    if (this.pollDraft.options.length > 2) {
                        this.pollDraft.options.splice(i, 1);
                    }
                },
                canSendPoll() {
                    const q = (this.pollDraft.question || '').trim();
                    const opts = this.pollDraft.options.map(o => (o || '').trim()).filter(o => o !== '');
                    const unique = [...new Set(opts.map(o => o.toLowerCase()))];
                    return q !== '' && unique.length >= 2;
                },
                sendPoll() {
                    if (!this.canSendPoll()) return;
                    const q = this.pollDraft.question.trim();
                    const opts = this.pollDraft.options.map(o => o.trim()).filter(o => o !== '');
                    this.$wire.createPoll(q, opts, this.pollDraft.allowMultiple);
                },
                closePoll() {
                    this.pollOpen = false;
                    this.pollDraft = { question: '', options: ['', ''], allowMultiple: false };
                },

                // ── Poll Voters ("View votes") state ──
                votersOpen: false,
                votersLoading: false,
                votersData: null,
                async openVoters(pollId) {
                    this.votersOpen = true;
                    this.votersLoading = true;
                    this.votersData = null;
                    try {
                        const data = await this.$wire.pollVoters(pollId);
                        this.votersData = data && data.options ? data : null;
                    } catch (e) {
                        console.error('pollVoters failed', e);
                        this.votersData = null;
                    } finally {
                        this.votersLoading = false;
                    }
                },
                closeVoters() {
                    this.votersOpen = false;
                    this.votersData = null;
                },

                // ── Media Preview state (WhatsApp-style) ──
                preview: {
                    active: false,
                    type: '',       // 'image' | 'document'
                    url: '',        // object URL for image preview
                    fileName: '',
                    fileSize: '',
                    fileExt: '',
                    fileIcon: '📄',
                    caption: '',
                },

                // In-browser camera capture state (WhatsApp-style)
                camera: {
                    active: false,
                    stream: null,
                    facing: 'environment',
                    capturedUrl: '',
                    error: '',
                },
                _pendingCameraFile: null,

                async openCamera() {
                    this.camera.active = true;
                    this.camera.error = '';
                    this.camera.capturedUrl = '';
                    this._stopCameraStream();
                    if (!navigator.mediaDevices?.getUserMedia) {
                        this.camera.error = 'Camera not supported in this browser';
                        return;
                    }
                    try {
                        this.camera.stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: this.camera.facing },
                            audio: false,
                        });
                        await this.$nextTick();
                        const v = this.$refs.cameraVideo;
                        if (v) {
                            v.srcObject = this.camera.stream;
                            try { await v.play(); } catch (_) {}
                        }
                    } catch (e) {
                        this.camera.error = (e && e.message) ? e.message : 'Cannot access camera';
                    }
                },

                async flipCamera() {
                    this.camera.facing = this.camera.facing === 'environment' ? 'user' : 'environment';
                    await this.openCamera();
                },

                captureCameraPhoto() {
                    const v = this.$refs.cameraVideo;
                    if (!v || !v.videoWidth) return;
                    const canvas = document.createElement('canvas');
                    canvas.width = v.videoWidth;
                    canvas.height = v.videoHeight;
                    const ctx = canvas.getContext('2d');
                    if (this.camera.facing === 'user') {
                        // Mirror the front-camera capture so the photo matches the preview
                        ctx.translate(canvas.width, 0);
                        ctx.scale(-1, 1);
                    }
                    ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((blob) => {
                        if (!blob) return;
                        if (this.camera.capturedUrl) URL.revokeObjectURL(this.camera.capturedUrl);
                        this.camera.capturedUrl = URL.createObjectURL(blob);
                        this._pendingCameraFile = new File([blob], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    }, 'image/jpeg', 0.92);
                },

                retakeCameraPhoto() {
                    if (this.camera.capturedUrl) URL.revokeObjectURL(this.camera.capturedUrl);
                    this.camera.capturedUrl = '';
                    this._pendingCameraFile = null;
                },

                useCameraPhoto() {
                    const file = this._pendingCameraFile;
                    if (!file) return;
                    // Hand the captured object URL to the existing media-preview overlay
                    if (this.preview.url) URL.revokeObjectURL(this.preview.url);
                    this.preview.type = 'image';
                    this.preview.fileName = file.name;
                    this.preview.fileExt = 'jpg';
                    this.preview.fileSize = (file.size < 1048576)
                        ? (file.size / 1024).toFixed(1) + ' KB'
                        : (file.size / 1048576).toFixed(1) + ' MB';
                    this.preview.fileIcon = '📷';
                    this.preview.caption = '';
                    this.preview.url = this.camera.capturedUrl;
                    this.camera.capturedUrl = ''; // ownership transferred to preview
                    this.preview.active = true;
                    this._closeCameraInternal(/*keepFile*/ true);
                },

                closeCamera() {
                    this._closeCameraInternal(false);
                },

                _closeCameraInternal(keepFile) {
                    this._stopCameraStream();
                    if (this.camera.capturedUrl) URL.revokeObjectURL(this.camera.capturedUrl);
                    this.camera.capturedUrl = '';
                    this.camera.active = false;
                    if (!keepFile) this._pendingCameraFile = null;
                },

                _stopCameraStream() {
                    if (this.camera.stream) {
                        this.camera.stream.getTracks().forEach(t => t.stop());
                        this.camera.stream = null;
                    }
                },

                onFileSelected(event, type) {
                    const file = event.target.files[0];
                    if (!file) return;

                    this.preview.type = type;
                    this.preview.fileName = file.name;
                    this.preview.fileExt = file.name.split('.').pop() || '';
                    this.preview.caption = '';

                    // Human-readable file size
                    if (file.size < 1024) {
                        this.preview.fileSize = file.size + ' B';
                    } else if (file.size < 1048576) {
                        this.preview.fileSize = (file.size / 1024).toFixed(1) + ' KB';
                    } else {
                        this.preview.fileSize = (file.size / 1048576).toFixed(1) + ' MB';
                    }

                    // File type icon for documents
                    const ext = this.preview.fileExt.toLowerCase();
                    const iconMap = {
                        'pdf': '📕', 'doc': '📘', 'docx': '📘',
                        'xlsx': '📗', 'xls': '📗', 'csv': '📗',
                        'pptx': '📙', 'ppt': '📙',
                        'txt': '📝', 'zip': '🗜️', 'rar': '🗜️'
                    };
                    this.preview.fileIcon = iconMap[ext] || '📄';

                    if (type === 'image') {
                        // Revoke previous object URL if any
                        if (this.preview.url) URL.revokeObjectURL(this.preview.url);
                        this.preview.url = URL.createObjectURL(file);
                    } else {
                        this.preview.url = '';
                    }

                    this.preview.active = true;
                },

                sendPreviewMedia() {
                    if (!this.preview.active) return;
                    const type = this.preview.type;
                    const caption = this.preview.caption;

                    // Push optimistic message into the chat list immediately so the
                    // user sees their image/document right away. The placeholder will
                    // be cleared when 'media-sent' fires after the server responds.
                    window.dispatchEvent(new CustomEvent('optimistic-media', { detail: {
                        kind: type,
                        url: this.preview.url,
                        fileName: this.preview.fileName,
                        fileSize: this.preview.fileSize,
                        fileIcon: this.preview.fileIcon,
                        caption: caption,
                    }}));

                    // Hand ownership of the object URL to the optimistic bubble so that
                    // closePreview() (fired by media-sent) doesn't revoke it underneath us.
                    this.preview.url = '';

                    const finalize = () => {
                        this.$wire.set('mediaCaption', caption).then(() => {
                            this.$wire.sendMedia(type);
                        });
                    };

                    // Camera-captured photos aren't bound to a file <input>, so upload
                    // the captured Blob directly through Livewire before sending.
                    if (this._pendingCameraFile) {
                        const file = this._pendingCameraFile;
                        this._pendingCameraFile = null;
                        this.$wire.upload('mediaUpload', file,
                            () => finalize(),
                            () => window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Upload failed.' } })),
                        );
                    } else {
                        finalize();
                    }
                },

                closePreview() {
                    if (this.preview.url) URL.revokeObjectURL(this.preview.url);
                    this.preview.active = false;
                    this.preview.type = '';
                    this.preview.url = '';
                    this.preview.fileName = '';
                    this.preview.fileSize = '';
                    this.preview.fileExt = '';
                    this.preview.fileIcon = '📄';
                    this.preview.caption = '';
                    // Reset file inputs so re-selecting the same file triggers change
                    if (this.$refs.photoInput) this.$refs.photoInput.value = '';
                    if (this.$refs.docInput) this.$refs.docInput.value = '';
                    if (this.$refs.cameraInput) this.$refs.cameraInput.value = '';
                },

                insertEmoji(emoji) {
                    const ta = this.$refs.msgInput;
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const val = ta.value;
                    ta.value = val.substring(0, start) + emoji + val.substring(end);
                    ta.selectionStart = ta.selectionEnd = start + emoji.length;
                    this.msgText = ta.value;
                    ta.dispatchEvent(new Event('input'));
                    this.emojiOpen = false;
                    ta.focus();
                },

                onTyping() {
                    // Don't broadcast typing for empty / whitespace-only input.
                    // Reset the debounce so the next real keystroke fires immediately.
                    if (!this.msgText || !this.msgText.trim()) {
                        if (this._typingTimeout) {
                            clearTimeout(this._typingTimeout);
                            this._typingTimeout = null;
                        }
                        return;
                    }
                    if (this._typingTimeout) return;
                    this.$wire.sendTyping();
                    this._typingTimeout = setTimeout(() => { this._typingTimeout = null; }, 2500);
                },

                // ── Timer helpers ──
                _startTimer() {
                    this.recSeconds = 0;
                    this.recTimerLabel = '0:00';
                    this._recTimer = setInterval(() => {
                        this.recSeconds++;
                        const m = Math.floor(this.recSeconds / 60);
                        const s = this.recSeconds % 60;
                        this.recTimerLabel = m + ':' + String(s).padStart(2, '0');
                    }, 1000);
                },
                _stopTimer() {
                    clearInterval(this._recTimer);
                    this._recTimer = null;
                },

                // ── Waveform visualizer ──
                _startWaveform(stream) {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const src = ctx.createMediaStreamSource(stream);
                        this._analyser = ctx.createAnalyser();
                        this._analyser.fftSize = 64;
                        src.connect(this._analyser);
                        this._audioCtx = ctx;
                        this._drawWave();
                    } catch(e) { /* silent — waveform is cosmetic */ }
                },
                _drawWave() {
                    if (!this._analyser || !this.recording) return;
                    const canvas = this.$refs.waveCanvas;
                    if (!canvas) { this._animFrame = requestAnimationFrame(() => this._drawWave()); return; }
                    const c = canvas.getContext('2d');
                    const bufLen = this._analyser.frequencyBinCount;
                    const data = new Uint8Array(bufLen);
                    this._analyser.getByteFrequencyData(data);

                    c.clearRect(0, 0, canvas.width, canvas.height);
                    const barW = Math.max(2, (canvas.width / bufLen) - 1);
                    const gap = 1;
                    let x = 0;
                    for (let i = 0; i < bufLen; i++) {
                        const h = (data[i] / 255) * canvas.height * 0.9;
                        const barH = Math.max(2, h);
                        const y = (canvas.height - barH) / 2;
                        c.fillStyle = this.recPaused ? '#94a3b8' : '#CE1126';
                        c.fillRect(x, y, barW, barH);
                        x += barW + gap;
                    }
                    this._animFrame = requestAnimationFrame(() => this._drawWave());
                },
                _stopWaveform() {
                    cancelAnimationFrame(this._animFrame);
                    this._animFrame = null;
                    if (this._audioCtx) { try { this._audioCtx.close(); } catch(e){} this._audioCtx = null; }
                    this._analyser = null;
                },

                // ── Recording actions ──
                async startRecording() {
                    if (this.recording) return;
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this._stream = stream;
                        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus'
                            : MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm'
                            : MediaRecorder.isTypeSupported('audio/ogg') ? 'audio/ogg'
                            : MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4'
                            : '';
                        const options = mimeType ? { mimeType } : {};
                        this.mediaRecorder = new MediaRecorder(stream, options);
                        this.audioChunks = [];
                        this.recPaused = false;

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data && e.data.size > 0) this.audioChunks.push(e.data);
                        };
                        this.mediaRecorder.onstop = () => {
                            stream.getTracks().forEach(t => t.stop());
                            this._stopWaveform();
                            this._stopTimer();
                            if (this._shouldSend && this.audioChunks.length) {
                                const actual = mimeType || this.mediaRecorder.mimeType || 'audio/webm';
                                const ext = actual.includes('mp4') ? 'm4a' : actual.includes('ogg') ? 'ogg' : 'webm';
                                const blob = new Blob(this.audioChunks, { type: actual });
                                const file = new File([blob], 'voice-' + Date.now() + '.' + ext, { type: blob.type });
                                this.voiceUploading = true;
                                this.voiceProgress = 0;
                                this.$wire.upload('mediaUpload', file,
                                    // success
                                    () => {
                                        this.voiceProgress = 100;
                                        this.$wire.sendMedia('audio');
                                        // Hide the bar shortly after sendMedia resolves
                                        setTimeout(() => { this.voiceUploading = false; this.voiceProgress = 0; }, 350);
                                    },
                                    // error
                                    () => {
                                        console.error('Voice upload failed');
                                        this.voiceUploading = false;
                                        this.voiceProgress = 0;
                                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Voice upload failed.' } }));
                                    },
                                    // progress
                                    (event) => {
                                        if (event && typeof event.detail?.progress === 'number') {
                                            this.voiceProgress = Math.min(99, event.detail.progress);
                                        }
                                    }
                                );
                            }
                            this._shouldSend = false;
                        };

                        this._shouldSend = false;
                        this.mediaRecorder.start(250);
                        this.recording = true;
                        this._startTimer();
                        this._startWaveform(stream);
                    } catch (e) {
                        console.warn('Microphone access denied:', e.message);
                        alert(this.$store?.lang?.t?.('Microphone access denied. Please allow microphone in your browser settings.', 'Accès au microphone refusé. Veuillez autoriser le microphone dans les paramètres de votre navigateur.') || 'Microphone access denied.');
                    }
                },

                togglePauseRecording() {
                    if (!this.mediaRecorder) return;
                    if (this.recPaused) {
                        this.mediaRecorder.resume();
                        this.recPaused = false;
                        this._startTimer();
                    } else {
                        this.mediaRecorder.pause();
                        this.recPaused = true;
                        this._stopTimer();
                    }
                },

                discardRecording() {
                    if (!this.mediaRecorder) return;
                    this._shouldSend = false;
                    this.audioChunks = [];
                    if (this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    this.recording = false;
                    this.recPaused = false;
                    this._stopTimer();
                    this._stopWaveform();
                    if (this._stream) { this._stream.getTracks().forEach(t => t.stop()); this._stream = null; }
                },

                sendRecording() {
                    if (!this.mediaRecorder) return;
                    this._shouldSend = true;
                    if (this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    this.recording = false;
                    this.recPaused = false;
                }
            };
    });

    Alpine.data('audioPlayer', () => {
            return {
                playing: false,
                progress: 0,
                timeLabel: '0:00',
                speed: 1,
                speedLabel: '1×',
                _speeds: [1, 1.5, 2],
                _raf: null,
                _audioEl: null,
                _knownDuration: 0,

                _getDuration(el) {
                    if (el.duration && isFinite(el.duration)) {
                        this._knownDuration = el.duration;
                        return el.duration;
                    }
                    return this._knownDuration || 0;
                },

                probeDuration(el) {
                    if (el.duration && isFinite(el.duration)) {
                        this._knownDuration = el.duration;
                        this.timeLabel = this._formatTime(el.duration);
                        return;
                    }
                    // WebM duration fix: seek to huge value to force browser to resolve real duration
                    const onSeeked = () => {
                        el.removeEventListener('seeked', onSeeked);
                        if (el.duration && isFinite(el.duration)) {
                            this._knownDuration = el.duration;
                            this.timeLabel = this._formatTime(el.duration);
                        }
                        el.currentTime = 0;
                    };
                    el.addEventListener('seeked', onSeeked);
                    el.currentTime = 1e101;
                },

                _formatTime(sec) {
                    const m = Math.floor(sec / 60);
                    const s = Math.floor(sec % 60);
                    return m + ':' + String(s).padStart(2, '0');
                },

                _tick() {
                    const el = this._audioEl;
                    if (!el || !this.playing) return;

                    // Always update time label from currentTime
                    this.timeLabel = this._formatTime(el.currentTime);

                    const dur = this._getDuration(el);
                    if (dur > 0) {
                        this.progress = Math.min((el.currentTime / dur) * 100, 100);
                    }

                    this._raf = requestAnimationFrame(() => this._tick());
                },
                _startTick(el) {
                    this._audioEl = el;
                    if (this._raf) cancelAnimationFrame(this._raf);
                    this._raf = requestAnimationFrame(() => this._tick());
                },
                _stopTick() {
                    if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
                },

                toggle(el) {
                    if (el.paused) {
                        el.playbackRate = this.speed;
                        const p = el.play();
                        if (p && p.catch) p.catch(err => { console.error('Audio play failed:', err, el.src); this.playing = false; this._stopTick(); });
                        this.playing = true;
                        this._startTick(el);
                    } else { el.pause(); this.playing = false; this._stopTick(); }
                },
                cycleSpeed(el) {
                    const idx = (this._speeds.indexOf(this.speed) + 1) % this._speeds.length;
                    this.speed = this._speeds[idx];
                    this.speedLabel = this.speed === 1 ? '1×' : this.speed === 1.5 ? '1.5×' : '2×';
                    if (!el.paused) el.playbackRate = this.speed;
                },
                seek(event, el) {
                    const rect = event.currentTarget.getBoundingClientRect();
                    const pct = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                    const dur = this._getDuration(el);
                    if (dur > 0) {
                        el.currentTime = pct * dur;
                        this.progress = pct * 100;
                    }
                },
                onTime(e) {
                    const el = e.target;
                    this.timeLabel = this._formatTime(el.currentTime);
                    const dur = this._getDuration(el);
                    if (dur > 0) {
                        this.progress = Math.min((el.currentTime / dur) * 100, 100);
                    }
                },
                onEnded(el) {
                    this._stopTick();
                    this.playing = false;
                    // Now duration is known for sure
                    if (el.duration && isFinite(el.duration)) {
                        this._knownDuration = el.duration;
                    }
                    this.progress = 0;
                    this.timeLabel = this._knownDuration ? this._formatTime(this._knownDuration) : '0:00';
                },
                durationLabel(el) {
                    if (!el) return '0:00';
                    const dur = el.duration && isFinite(el.duration) ? el.duration : this._knownDuration;
                    return dur ? this._formatTime(dur) : '0:00';
                }
            };
    });
});
