/**
 * Back closes the thing on top, not the page.
 *
 * Every popup in the app — the profile card, the photo viewer, Discover, the
 * menu drawer, forward, room info — was invisible to browser history. Opening
 * one and pressing Back (or swiping back on a phone, where it is the main way
 * to dismiss anything) left the page entirely with the popup still "open"
 * underneath. The Yard's chat panel had its own one-off handling for this; the
 * rest had none.
 *
 * This keeps a stack of open overlays. Each one pushes a history entry when it
 * opens, so Back pops the newest overlay instead of navigating, and closing by
 * other means (✕, backdrop, Escape) steps over that entry so the history stays
 * honest.
 *
 * Usage — one attribute on the element whose Alpine state owns the overlay:
 *
 *     <div x-data="{ open: false }" x-overlay="open"> … </div>
 *
 * Nested overlays work: they unwind newest first.
 */

const stack = [];

// Backs we triggered ourselves. Their popstate must not close anything else.
let pendingBacks = 0;

// True while a close is running because the user pressed Back, so the close
// does not try to step back again.
let unwinding = false;

function pushOverlay(entry) {
    stack.push(entry);

    try {
        history.pushState({ cnOverlay: stack.length }, '');
    } catch (_) {
        // Private modes and odd embeds can refuse; the overlay still works,
        // it just will not answer the back button.
    }
}

function popOverlay(id) {
    const index = stack.findIndex((entry) => entry.id === id);
    if (index === -1) return;

    stack.splice(index, 1);

    // Closed from its own UI: consume the entry we pushed so a later Back does
    // not land on a no-op state.
    if (! unwinding) {
        pendingBacks++;
        try {
            history.back();
        } catch (_) {
            pendingBacks--;
        }
    }
}

/** Tell other popstate listeners this one is ours, for the current tick. */
function claimPop() {
    window.__cnOverlayConsumedPop = true;
    setTimeout(() => { window.__cnOverlayConsumedPop = false; }, 0);
}

window.addEventListener('popstate', () => {
    if (pendingBacks > 0) {
        pendingBacks--;

        // Claimed here too. This pop is the one WE asked for, stepping over
        // the entry an overlay pushed when it opened — nobody pressed
        // anything. Without the claim the Yard's own popstate listener read
        // it as a back press and closed the room, so dismissing a selected
        // message dropped the user back to the chat list.
        claimPop();

        return;
    }

    const top = stack.pop();
    if (! top) return;

    // Tell anything else listening (the Yard closes its chat panel on back)
    // that this press has been spent.
    claimPop();

    unwinding = true;
    try {
        top.close();
    } finally {
        unwinding = false;
    }
});

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    if (! Alpine) return;

    Alpine.directive('overlay', (el, { expression }, { effect, evaluateLater, evaluate, cleanup }) => {
        const isOpen = evaluateLater(expression);

        // Some states cannot be closed by assigning false to the expression
        // that reveals them — a Livewire property needs its own method, and a
        // reply is cancelled rather than unset. Those name the action here:
        //
        //     x-overlay="$wire.replyToId !== null" x-overlay-close="$wire.cancelReply()"
        const closeExpression = el.getAttribute('x-overlay-close') || `${expression} = false`;

        const id = {};
        let wasOpen = false;

        effect(() => {
            isOpen((value) => {
                const open = !! value;
                if (open === wasOpen) return;
                wasOpen = open;

                if (open) {
                    pushOverlay({ id, close: () => evaluate(closeExpression) });
                } else {
                    popOverlay(id);
                }
            });
        });

        // A Livewire morph can remove an open overlay from the DOM; drop its
        // entry rather than leaving a dead one on the stack.
        cleanup(() => {
            const index = stack.findIndex((entry) => entry.id === id);
            if (index !== -1) stack.splice(index, 1);
        });
    });
});

// For overlays that are not a simple Alpine boolean (anything driven from
// plain JS) — open with a close callback, and tell us when it closes.
window.cnOverlay = {
    open(id, close) {
        popOverlay(id);
        pushOverlay({ id, close });
    },
    close(id) {
        popOverlay(id);
    },
    get depth() {
        return stack.length;
    },
};
