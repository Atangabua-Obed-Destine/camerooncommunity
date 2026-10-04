/**
 * Shared Echo channels, reference counted.
 *
 * Echo caches channels by name, so `Echo.channel('tenant.1.room.9')` hands the
 * same object to everyone who asks. `Echo.leave()` then destroys that object
 * and every listener on it — including listeners belonging to other parts of
 * the app.
 *
 * In the Yard the chat thread and the call engine both subscribe to the room
 * channel. Whichever left first silently tore down the other's listeners, and
 * because each kept a private "I am subscribed to X" flag, neither noticed or
 * re-subscribed. The result was a chat that worked when the page loaded and
 * stopped receiving messages after a room switch, with the socket still
 * connected and nothing in the console — and ticks that stopped advancing for
 * the same reason on the receipts channel.
 *
 * This keeps one owner set per channel. Releasing removes only that owner's
 * handlers, and the channel itself is left only when nobody is holding it.
 */

const held = new Map();

function echo() {
    return window.Echo ?? null;
}

/**
 * Listen to events on a channel for as long as `owner` holds it.
 *
 * @param {string} name     channel name, e.g. 'tenant.1.room.9'
 * @param {object} owner    any stable object; the same owner re-joining replaces its handlers
 * @param {object} handlers { '.EventName': fn }
 * @returns {boolean}       false when Echo is not available
 */
export function join(name, owner, handlers) {
    const e = echo();
    if (! e || ! name) return false;

    // Re-joining with the same owner: drop the old handlers first, or they
    // stack up and every message is handled twice, then three times.
    //
    // Only when this owner actually holds it. Releasing an owner that holds
    // nothing would find the channel at zero owners and leave it — tearing
    // down a channel on the very call that was meant to join it.
    if (held.get(name)?.owners.has(owner)) {
        release(name, owner);
    }

    let entry = held.get(name);

    if (! entry) {
        entry = { channel: e.channel(name), owners: new Map() };
        held.set(name, entry);
    }

    const bound = [];

    for (const [event, fn] of Object.entries(handlers)) {
        entry.channel.listen(event, fn);
        bound.push([event, fn]);
    }

    entry.owners.set(owner, bound);

    return true;
}

/**
 * Stop listening for one owner. The channel survives while others hold it.
 */
export function release(name, owner) {
    const entry = held.get(name);
    if (! entry) return;

    const bound = entry.owners.get(owner);

    if (bound) {
        for (const [event, fn] of bound) {
            try { entry.channel.stopListening(event, fn); } catch (_) { /* already gone */ }
        }
        entry.owners.delete(owner);
    }

    if (entry.owners.size === 0) {
        held.delete(name);
        try { echo()?.leave(name); } catch (_) { /* already gone */ }
    }
}

/** Whether this owner currently holds this channel. */
export function holds(name, owner) {
    return held.get(name)?.owners.has(owner) ?? false;
}

/** For diagnosis from the console: which channels are held, and by how many. */
export function subscriptions() {
    return Object.fromEntries(
        [...held.entries()].map(([name, entry]) => [name, entry.owners.size])
    );
}

window.cnRealtime = { join, release, holds, subscriptions };
