# Pass 12 — The overlays

The twenty things the screen map lists as "not routes": anything an event opens over a screen
instead of navigating to it. Passes 1–4 already describe ten of them. This pass links to those
and adds only what was missing (how each closes, its transition, its endpoints). The other ten
are new here.

Sources: `resources/js/overlay-history.js`; `partials/{discover-modal,user-preview,pwa-install,
toasts}.blade.php`; `livewire/ai/kamer-chat.blade.php` + `KamerChat.php`; `livewire/yard/
{communities,connections,starred-messages,share-to-chat,call-manager,room-list,chat-room}`;
`livewire/location-tracker.blade.php`; `yard/index.blade.php`; the matching rules in `app.css`;
and Alpine's transition helper as Livewire bundles it (`livewire.esm.js:2370`).

**How this was checked.** From the source first. Then every overlay a phone member can reach
was **opened in a signed-in session** at iPhone SE size, as one throwaway member
(`parity_tmp_overlays`), with a copy of `shoot.mjs` that logs in first. The member, its three
sessions and its one location-history row were deleted afterwards. Screenshots are
`shots/overlay-*-se.png`. Four of them (communities, share, toasts, location prompt), plus the
bell and PWA-banner measurements, are reused from the stalled first attempt at this pass.

Font sizes are phone values (1.25×), **except** where marked *unscaled*. The 1.25× block reaches
Tailwind's variables, `text-[Npx]` utilities and `app.css` component rules. It does not reach a
view's own `<style>` block or an inline `style=""`, so Discover's badges and the photo viewer
render at face value. An element with no size class inherits the body's unscaled 16px.

---

## 1. Three rules that apply to all of them

**Back closes the top overlay, if it registered.** `overlay-history.js` keeps a stack. Each
overlay pushes a history entry when it opens, so the back gesture pops the newest overlay
instead of leaving the page. Closing it by ✕, backdrop or Escape steps back over that entry.
There are three ways to join:

| How | Used by |
|---|---|
| `x-overlay="expr"` on the element that owns the state | drawer, Discover, user preview + photo viewer, room info, lightbox, forward, reaction row, calls notice, PWA "already installed" |
| `x-overlay` + `x-overlay-close="method()"` for Livewire state | selection (`selClear()`), reply, search |
| `window.cnOverlay.open(id, close)` / `.close(id)` from `init()`/`destroy()` | chat dock, Communities, Connections, location switcher |

**Not on the stack:** Kamer AI, Starred, Share to chat, the Archived filter, the call cards,
the listing modal (it has its own `popstate`, [pass 3 finding 2](03-gomarket.md)), GoMarket's
filters drawer and the notification bell. For these, Back leaves the page. In the app the rule
is simpler: **every overlay is a route**, and the edge swipe pops it.

**Anything inside the chat shell is confined to its panel.** `.yard-panel` sets
`will-change: transform` (`app.css:530`). That makes each panel the containing block for its
`position: fixed` children *and* a stacking context. So an overlay rendered inside the room list
or the chat room is clipped to the panel (from y 96 on a phone) and paints **under** the z-50
header whatever its own z-index. Its backdrop never dims the header. That is why the preview,
drawer, dock and listing modal are teleported to `<body>`. These are not: the **location
switcher** (room list), and the **lightbox, forward sheet, selection UI and calls notice** (chat
room). Measured: the switcher's root is 571px tall from y 96, and a tap on the header still lands
on a header tab.

**Alpine's default transition.** Bare `x-transition`, `.opacity` or `.scale.N` means **150ms
in, 75ms out, `cubic-bezier(.4,0,.2,1)`**, scale .95 by default. Alpine applies it as an inline
`transform: scale()`, which **replaces** any centring `translate(-50%,-50%)` for the duration
(divergence 10). And `x-transition` on an element shown by Blade `@if` rather than `x-show`
does nothing, so Communities, Connections, Starred and Share have **no enter animation**.

The web has **no safe-area handling** ([pass 3 finding 1](03-gomarket.md)). Every full-height
overlay needs `SafeArea`: top when it covers the header, bottom when it has an input or footer.

---

## 2. The twenty, on one ladder

Top paints over bottom. **Panel** = confined (rule 2), so under the header despite the number.

| # | Overlay | z | Where | Back | Esc | Scrim tap | Described in |
|---|---|---|---|---|---|---|---|
| 1 | Toasts | 9999 | `top-4 right-4` | — | — | — | [04 §4](04-shared-components.md), §3 |
| 2 | Connection notifier | 9999 | `top:76px`, 8px sides | — | — | — | [04 §4](04-shared-components.md) |
| 3 | Call manager | 200/201 | screen | no | no | — | §4.7 |
| 4 | User preview → photo | 120 → 130 | teleported | yes | yes | yes | [04 §7](04-shared-components.md), §3 |
| 5 | Discover | 100/101 | screen | yes | yes | yes | §4.1 |
| 6 | Communities | 900/901 | full screen | yes¹ | no | — | §4.3 |
| 7 | Connections | 900/901 | full screen | yes¹ | no | — | §4.4 |
| 8 | Starred messages | 80 | screen | **no** | yes | yes | §4.5 |
| 9 | Share to chat | 80 | screen | **no** | yes | yes | §4.8 |
| 10 | PWA install banner | 70 | bottom | — | — | — | §4.10 |
| 11 | App drawer | 70 | teleported | yes | yes | yes | [01 §4](01-layout-navigation.md) |
| 12 | GoMarket chat dock | 70 | bottom, full width | yes | no | — | [03 §9](03-gomarket.md) |
| 13 | Listing modal | 58/60 | teleported | own | — | — | [03 §6](03-gomarket.md) |
| 14 | Kamer AI | 50, after the header | full screen | **no** | **no** | unreachable | §4.2 |
| 15 | Forward sheet | 1000/1001 | panel | yes | yes | yes | [04 §5](04-shared-components.md), §3 |
| 16 | Message selection | 1000–1004 | panel | yes | yes | — | [02 §6](02-goconnect.md) |
| 17 | Location switcher + prompt | 70 / 9999 | panel / screen | yes¹ / no | yes / no | yes | §4.9 |
| 18 | Image lightbox | 60 | panel | yes | no | any tap | [04 §5](04-shared-components.md), §3 |
| 19 | Room info | 40 | `top: 96px` | yes | — | — | [02 §7](02-goconnect.md) |
| 20 | Archived chats | — | a list filter, not an overlay | **no** | — | — | §4.6 |

¹ Registered by hand, and closing it by ✕ or backdrop **leaves its entry on the stack**, so the
next Back press is swallowed. Measured on Communities; divergence 1.

---

## 3. The ten already described: what this pass adds

**App drawer** — complete in [pass 1 §4](01-layout-navigation.md). Discover's own Kamer card
hands off after **200ms**, not the drawer's 180 (`discover-modal.blade.php:130,170`). Use 180.

**User preview card** — [pass 4 §7](04-shared-components.md) gives only its role. Measured
(`shots/overlay-user-preview-se.png`):

- Opened by `open-user-preview {id | username}` (37 matching lines in the views). The photo
  viewer opens from the avatar, or from `open-user-photo {url, name, bg}`.
- Overlay `rgba(0,0,0,.40)`, z **120** inline (the stylesheet's 50 is overridden). Closes on
  scrim, Escape, Back, or the *Close* link (15px `slate-400`).
- Card: white, radius **16**, padding **28 × 24**, `0 12px 40px rgba(0,0,0,.2)`, min-width 260,
  max-width `min(92vw, 360)`, max-height 88vh. It hugs its content: **270 × 284** with no bio.
- Avatar **72** on `cm-green`. Name **22.5px** bold, `@handle` and location **15px** `slate-400`,
  bio **17.5px** `slate-600`.
- One CTA pill per state: `8px 18px`, radius 20, **17px** weight 600, `cm-green`. *Send
  Message* / *Connect* / *Accept connection* / *View my profile*. *Request sent* is a grey chip
  (`#e2e8f0` on `#64748b`) with a quiet *Cancel request* under it. *Disconnect* is the same quiet
  15px link. Blocked shows a 15px `rose-600` line and no CTA. Then the nickname editor.
- Loading: *Loading...* at 17.5px `slate-400`, `py-6`. A failed lookup **closes the card** and
  toasts "Could not open this profile". Transition: the overlay fades 150/75. The card's
  `scale.95` has no `x-show` of its own, so it never fires.
- Photo viewer: `rgba(0,0,0,.92)`, z 130. Its top row (`14px 16px`) has the name at **16px
  unscaled** and a 38px `white/12` close. The image is `contain`, radius 8, in `0 12px 28px`.
  With no photo, a `min(62vw, 260)` initial circle on the member's gradient. Scrim tap closes.

**Chat dock** — complete in [pass 3 §9](03-gomarket.md). It registers with `cnOverlay` using
`this.$el` as the id; the three that misbehave (divergence 1) pass `this`.

**Connection notifier** — complete in [pass 4 §4](04-shared-components.md). Its `top: 76px`
overlaps the header's tab row by 20px on a phone; it is meant to hang off the header, so keep it.

**Room info**, **message selection** — complete in passes [2 §7](02-goconnect.md) and
[2 §6](02-goconnect.md).

**Image lightbox** — opened by tapping a thread photo (`yard-chat.js:1003`). The panel goes
`rgba(0,0,0,.85)`; the image is max 90vw × 90vh, radius 8, `shadow-2xl`. **Any tap** closes it,
the image included, and so does Back. No Escape, no zoom. Fades 150/75.

**Forward sheet** — **340 × max 480**, radius 14, over the 15% scrim ([pass 4 §5](04-shared-components.md)).
Opened by `open-forward {msgId | msgIds}` from the selection bar. Closes on ✕, scrim, Escape,
Back. Header `14px 16px`, title **19px** weight 600. The search pill (radius 20, 16.4px) filters
rows (17px) **client-side**. `scale.90` 150/75, which breaks its centring (divergence 10). No
loading or empty state: the rooms render with the chat.

**Listing modal** — complete in [pass 3 §6](03-gomarket.md): `mp-open-listing {slug, url}` from
an unmodified card tap, `mp-close-listing` to close.

**Toasts** — complete in [pass 4 §4](04-shared-components.md). Measured **288 × 62 at (71, 16)**
on an SE, over the header's bell and avatar (`shots/overlay-toasts-se.png`). `/yard` uses
`yardToasts`; every other page uses the identical `cnToasts`.

---

## 4. The ten new ones

### 4.1 Discover

`partials/discover-modal.blade.php`, mounted once: a marketing carousel and module index
(`shots/overlay-discover-se.png`).

- **Opens** on the window event `open-discover`: from the drawer (180ms hand-off) and the feed's
  Discover card (`feed.blade.php:44`). The rail and header buttons are desktop only.
- **Closes** on ✕, scrim, Escape, Back. The GoConnect and GoMarket cards navigate. The Kamer
  card and the footer CTA close it, then fire `open-kamer-ai` 200ms later.
- **Scrim** `slate-900/70` + 12px blur, z 100, fading over 300ms. **Panel** z 101, centred in
  12px padding: max-height **92vh**, radius **24**, `shadow-2xl`, scrolls. Measured **351 × 614 at
  y 27**, with 1,053px of content.
- **In** 300ms ease-out from opacity 0, `translateY(32px)`, scale .95. **Out** 200ms ease-in to
  opacity 0, scale .95.
- **Hero**, 256px: a 135° `#009639 → #CE1126 → #FCD116` gradient, 200% wide on a 12s loop; three
  blurred orbs (200/180/150px, blur 40, opacity .55, `screen`); 14 sparkles. Five slides
  cross-fade over 700ms (±24px, scale .95) and autoplay every **5s** under a 2px progress bar.
  Each slide has a 60px emoji (bouncing), a **30px** weight-800 title and a **17.5px**
  `white/95` line. Controls: a 36px `white/95` ✕ at top/right 12, 36px `white/30` arrows 8px in,
  and 6px dots (the active one 32px wide).
- **Live now**: a 15px extrabold uppercase label with a pinging dot. Then one column of cards
  (12px gap): padding 14, radius 14, 2px `#E2E8F0` border, a 36px icon tile (radius 10, 12% accent
  tint), title **17.5px** bold, line **14px**. They rise in over 0.6s, staggered 0/100/200ms.
- **Coming soon**: a snap scroller of 200px cards: padding 16, radius 16, dashed accent border,
  a 28px emoji, 17.5px title, 14px line, and a **9px unscaled** weight-900 accent pill.
- **Footer**: a 15px line, then a full-width CTA (radius 12, 17.5px bold) on `#009639 → #00b347`.
- **States**: none; the content is hard-coded in the view.

### 4.2 Kamer AI

`livewire/ai/kamer-chat.blade.php`, mounted once (`shots/overlay-kamer-ai-se.png`).

- **Opens** on the Livewire event `open-kamer-ai`, from the header's 36px 🤖 (`app.blade.php:151`),
  the drawer, and Discover. Outside the `rails` shell there is also a floating 44px bubble at
  `bottom-2 right-4`, z 40.
- **Closes** on ✕ only, a server round trip. Its `black/30` scrim sits under a full-width panel,
  so it can never be tapped. **Escape and Back do nothing.** Measured: Back from the open panel
  went `/marketplace → /yard` with the panel still open.
- **Panel**: full screen, z 50, after the header in the DOM, so it **covers the header**. It slides
  from `translate-x-full`, 300ms ease-out in and 200ms ease-in out.
- **Header** `px-4 py-3`, `cm-green → cm-green/90`: a 36px `white/20` 🤖 circle, the title at
  **17.5px** bold, the subtitle at **13px** `white/70`, then trash (16px glyph, clears history)
  and ✕ (20px), each `p-2`, radius 8.
- **Thread** `p-4`, 16px apart. Kamer: a 28px `cm-green/10` avatar, a `slate-50` bubble (radius
  16, top-left 2), `px-3.5 py-2.5`, **17.5px** `slate-700`, max 90%. Member: `cm-green` with white
  text, top-right 2, max 80%. While there are two messages or fewer, suggestion pills show:
  `px-3 py-1.5`, **15px**, `cm-green/5` on `cm-green`, border `cm-green/20`.
- **Composer** `p-3` above a 1px `slate-200` rule: an auto-growing textarea (to 120px), radius 12,
  `slate-50`, 17.5px. Enter sends; Shift+Enter adds a line. Send is a 40px `cm-green` square,
  radius 12. Under it, a 13px credit line.
- **States**: *loading* is three bouncing 8px dots, which never render (divergence 7). *Error*
  is an assistant bubble: "Sorry, I'm not available right now…". With no AI configured, the bubble
  hides but the header button still opens the panel. History is per **web session** (last 50),
  and the panel polls every 5s while open.

### 4.3 Communities

`livewire/yard/communities.blade.php`, on `/yard` only (`shots/overlay-communities-se.png`).

- **Opens** on `open-communities`: from ⋮ → *Group* (`yard/index.blade.php:105`), the FAB menu
  (`:282`), and the room list's empty-state *Discover Communities* (`room-list.blade.php:308`).
- **Closes** on ✕ and Back. **No Escape.** `x-trap.noscroll` traps focus and locks `<html>`.
- **On a phone (≤640)** `.comm-modal` is **100vw × 100vh, radius 0** (`app.css:2717`), measured
  375 × 667 from y 0, over the header; its scrim (`rgba(0,0,0,.45)`) is never seen. Pass 4's
  "94vw / 88vh / 16" is the wider value. Nothing animates.
- **Header** `18px 22px 14px`, 1px `slate-100`: a 24px icon, the title at **24px** bold `slate-800`,
  a 34px ✕ (radius 8). **Search** `12px 22px 8px`: `slate-50`, 1px `slate-200`, radius 10, an
  18px icon, 17.5px text, a `cm-yellow` border on focus, debounced 300ms.
- **Tabs** (`0 22px`, over a 2px `slate-100` rule): `12px 18px`, **17.5px** weight 600
  `slate-500`; the active tab is `slate-800` with a 3px `cm-yellow` underline. Badges are 20px,
  radius 10, **14px** bold, white on `cm-green`. Two tabs here: *My communities*, *Discover*.
- **Toolbar** `12px 22px`: the count at 16.4px `slate-500`, and *+ Create*, a `cm-yellow` pill
  **110 × 43**, radius 20, 16.4px bold, which opens an inline name / description / private form.
- **Grid**: one column, `8px 22px 22px`, 16px gap. Cards: 1px `slate-200`, radius 14, padding 18.
  **Empty**: a 64px `slate-200` icon over a 17.5px `slate-400` line. **Loading**: none; the
  modal appears when the server answers.

### 4.4 Connections

`livewire/yard/connections.blade.php`: the same shell as §4.3, with these differences:

- **Opens** on `open-connections {tab?}`: from the header's contacts button
  (`yard/index.blade.php:36`), ⋮ → *Contacts* (`tab: 'requests'`), and the deep link
  `?open=connections&tab=…` (`:1072`, stripped after use). Focusing search selects the Search tab.
- **Five tabs**: My connections · Requests · Suggested · Search · Blocked. That is **736px in a
  375px modal**, with no horizontal scroll, so **Search and Blocked cannot be reached** (tabs at
  x 22 / 190 / 332 / 487 / 582; `shots/overlay-connections-se.png`).
- **Row** (`partials/connection-row`): `px-3 py-3`, 1px `slate-100`, a 40px avatar, the name at
  17.5px semibold, a 15px meta line. Buttons are `px-3 py-1.5`, radius 8, **15px** semibold:
  *Message* (`cm-green`), *Disconnect* (`slate-100`), *Block* (`rose-50`). Those three need 276px,
  so a connected row overflows (divergence 3). Blocked rows are `red-50/50` with a 🚫 badge.

### 4.5 Starred messages

`livewire/yard/starred-messages.blade.php`, on `/yard` only (`shots/overlay-starred-se.png`).

- **Opens** on `open-starred`, from ⋮ → *Starred* (`yard/index.blade.php:112`). **Closes** on
  ✕, scrim, Escape — **not Back**. It sets `body.style.overflow = 'hidden'` and never clears it.
- Scrim `black/50` + 4px blur, z 80. The panel is `max-w-md` with 16px margins (**343px** on an
  SE), radius 16, `shadow-2xl`, max-height 85vh; 342px tall when empty. No transition.
- **Header** `px-5 py-3`: a 32px `amber-50` circle with an `amber-500` star, the title at **16px**
  weight 600 (no size class, so unscaled), and a 32px round ✕.
- **Rows** white on a `slate-50` body, `px-4 py-3`: a 36px avatar, then a 15px meta line (sender
  `slate-700` · room · 13px `group` chip), the message at **17.5px**, two lines, which jumps to it
  on tap (`room-selected` + `scroll-to-message`), then a 14px line of ages. The 28px unstar
  button is hover-only: **invisible on a phone**, though still tappable.
- **Empty**: a 56px `amber-50` circle, a 17.5px title and a 15px hint. Tapping a message from a
  room you have left raises an error toast.

### 4.6 Archived chats

**Not an overlay.** The header's archive button (`yard/index.blade.php:53`, badge capped at
`99+`) dispatches `open-archived`, and `RoomList::openArchived()` sets the list filter. The
filter pills give way to `.yard-archived-bar`: `10px 14px 8px`, a 32px back button, *Archived*
at **20px** weight 600. The rows are ordinary; empty reads *No archived chats*. The back arrow
returns to *All*, but **the back gesture leaves GoConnect**. In the app, push the room-list screen
again with the filter. Rooms archived because the member *moved* are a separate locked group
at the list's foot (`.yard-room-archived`, when `show_archived_away` is on).

### 4.7 Call manager (deferred with calls)

`livewire/yard/call-manager.blade.php`, z 200/201, listening for `call-started`, `-answered`,
`-ended`, `-error` and `initiate-call`. Incoming calls get a fixed **380px** card, 32px from the
top. Outgoing and active calls get a draggable **360px** card (480 for video). None is on the
back stack. Calls are **off** by default (`calls_enabled` = false), so every call button opens the
**calls notice** (`chat-room.blade.php:947`): z 120, panel-confined, `black/50`, a 320px card with
radius 16 and `p-6`. Inside: a 56px `slate-100` icon circle, the title at 20px bold, a 17.5px
line, and a full-width `cm-green` *Close* (radius 12). It closes on scrim, Escape and Back.
**Build the notice now; CallKit comes with calls.**

### 4.8 Share to chat

`livewire/yard/share-to-chat.blade.php`, on `/yard` only (`shots/overlay-share-to-chat-se.png`).

- **Opens** on `open-share-to-chat {type, id, note?}`, where the type is one of
  `ShareableResolver::TYPES` (solidarity, marketplace, event, job, housing, food, parcel). The
  **only trigger in the views is a Solidarity card in a chat** (`_solidarity-card.blade.php:47`),
  which nothing can create yet.
- **Closes** on ✕, scrim, Escape — **not Back**. `x-trap.inert.noscroll` traps focus and makes the
  rest of the page inert.
- Scrim `rgba(15,23,42,.55)`, z 80, 16px padding. The panel is radius 18, max 440 × 88vh,
  `0 24px 60px -12px rgba(0,0,0,.35)`: **343 × 563** on an SE.
- Contents: a header (`14px 18px`, title **20px** bold), a preview card of the item (title 19px), a
  note field, a chat search, then the rooms. Four of those classes have no CSS, so each room
  renders as a centred stack, not the avatar / name / *Send* row the stylesheet describes.
  Empty: *No chats found.* (18px). Success toasts "Shared to {room}" and closes.

### 4.9 Location switcher and location prompt

**Switcher.** The room list's *Active location* strip (`room-list.blade.php:80`, and `:344`) calls
`openLocationSwitcher`. Because it renders inside the room list, it is panel-confined
(`shots/overlay-location-switcher-se.png`):

- **Closes** on ✕, *Close*, scrim, Escape, Back.
- A bottom sheet, radius **20 20 0 0**, `0 25px 50px -12px rgba(0,0,0,.4)`, over a `black/50` +
  4px-blur scrim that **leaves the header uncovered**. Measured: **582px** tall in a 571px
  container, so its top **10.5px** (the 4px tricolour bar and both corners) is under the
  header. The body scrolls at max 60vh.
- **In**: 300ms ease-out from `translateY(32px)`, opacity 0. No out transition (`@if`).
- The 4px `cm-green → cm-red → cm-yellow` bar. A head at `18px 20px 12px`: title **20px** bold,
  sub-line **15px** `slate-500`. *Currently active* (12.5px label, 17.5px value). The detected
  card in one of three states (differs / matches / unknown). *Use my current location*, whose
  failure shows a 15px `rose-600` reason. An amber warning. The footer (`14px 20px`, `slate-50/50`)
  has *Close* and *Switch & join rooms*, both radius 10, **16.3px** weight 600.

**Prompt.** `location-tracker` raises it when a fix differs from the active location: a 360px
card at `bottom-4 right-4`, z 9999, sliding in from `translate-x-[120%]` (500ms in, 300ms out).
It has a 4px tricolour top, a 40px pin tile, a 17.5px title, 15px text, *Switch location* and
*Stay here*. *Switch* POSTs `location.switch` and **reloads the page**
(`shots/overlay-location-prompt-se.png`). **In the app it is a sheet, not a toast:** it asks
for a decision.

### 4.10 PWA install banner

Web only, **no app equivalent**. Recorded because it collides with other overlays. It is a bottom
banner at z 70, `padding 12`, max 460, radius 16, measured **351 × 98 at y 557** on an SE. Its
"already installed" dialog is z 90, with a 340px card at radius 18.

---

## 5. Endpoints these overlays need

All under `/api/v1`; none exists yet. Names follow passes 6–9 where those already chose one.

| Overlay | Endpoints |
|---|---|
| Discover | `GET configuration` (exists): live and coming modules, instead of hard-coded cards |
| Kamer AI | `GET`/`POST`/`DELETE kamer/messages`. History moves from the web session to the member. |
| User preview | `GET users/{id\|username}/preview` (replaces `yard.user-preview`); `POST connections/{user}` and `POST dm/{user}` from [pass 8](08-profile.md); `PUT contacts/{user}/nickname` |
| Communities | `GET communities?tab=mine\|discover&q=`, `POST communities`, `POST communities/{room}/join` or `/request`, `DELETE …/request`, `DELETE …/membership` |
| Connections | `GET people?tab=…` from [pass 9](09-people-static.md), plus `tab=sent\|blocked`; the actions from pass 8, with `block` |
| Starred | `GET starred?cursor=`, `DELETE messages/{id}/star` |
| Archived | `GET rooms?filter=archived`, `POST`/`DELETE rooms/{room}/archive` |
| Share / Forward | `GET shareables/{type}/{id}`, `GET rooms?q=`, `POST rooms/{room}/share`, `POST messages/forward` |
| Location | `GET me/location` (active + detected), `POST location/resolve` ([pass 6](06-auth-onboarding.md)), `POST me/location/switch` → rooms joined and archived, so no reload |
| Notifier, toasts | a private per-member channel and APNs |

---

## 6. Native only where the web has none

| Overlay | Native | Why |
|---|---|---|
| All | each overlay is a route; the edge swipe pops it | the web's stack is partial (§1) |
| Full-height ones | `SafeArea` | no safe-area CSS |
| Kamer, Share, Connections | keyboard insets keep the input visible | the web leaves it to the browser |
| Location switcher | swipe-down dismiss, **no grabber**: keep the tricolour bar | no gesture on the web |
| Lightbox, photo viewer | pinch-zoom, swipe-down dismiss; a tap still closes | the web has neither |
| Discover | stop all motion under Reduce Motion | no reduced-motion rule |
| Starred | unstar always visible | the web's is hover-only |
| Call manager | CallKit | when calls ship |

---

## 7. Divergences found in this pass

Ordered by what would hurt.

> **Fixed on the web (2026-11-11), and checked in a signed-in browser at SE size:**
> 1 (Communities, Connections and the location switcher pass their element to
> `cnOverlay`, so closing by ✕ or scrim leaves no dead entry: depth back to 0),
> 2 (Back closes Kamer, Starred, Share, the Archived filter and GoMarket's filters
> drawer; Escape closes Kamer), 3 (Connections' tabs scroll sideways on a phone, and a row's
> buttons wrap under the name), 4 (the location switcher, join preview, lightbox, forward
> sheet and calls notice are teleported to `<body>`; the switcher's scrim now covers the
> header), 5 (Share's four unstyled classes have rules; it still has no trigger), 6 (Starred
> releases the scroll lock, and its unstar button shows on touch screens), 7 (Kamer shows your
> message and the dots at once and replies in a second request; and **all of Kamer's
> language checks compared an enum with `'fr'`, so French members got English**, fixed
> too), 9 (Discover: taller hero, GoMarket slide instead of "Solidarity in Action", the
> timer stops on close), 10 (`translate` instead of `transform`), 11 (call cards capped at
> the screen width), 12 (the install banner hides while Kamer is open), 13 (the location
> prompt fits an SE, its countdown runs, a failed switch says so), 14 (the bell's panel
> sits under the header on phones). 8 (the name) is moot: the platform is Cameroon
> Network. 15 is corrected in pass 4. Also found and fixed: `fallbackGreeting()` was handed
> the language enum and threw, so a room's join preview crashed whenever the AI greeting
> was unavailable.

1. **Closing Communities by ✕ or scrim leaves a dead entry on the back stack, and the next Back is
   swallowed.** *Rendered (measured).* The depth stays 1 after the modal is gone; the next Back
   just runs the stale `$wire.close()`. With Kamer open on top, it stayed on `/yard`. Connections
   and the location switcher use the same `init`/`destroy` pair with `this` as the id
   (`communities.blade.php:8-11`, `connections.blade.php:7-10`, `room-list.blade.php:585-588`).
   The dock passes `this.$el`, which is the likely fix.
2. **Kamer AI, Starred and Share ignore Back, and Kamer also ignores Escape.** *Rendered* for
   Kamer: it is full screen on a phone, and Back left the page with it open. *Code* for the
   others (`kamer-chat.blade.php:15`, `starred-messages.blade.php:3`, `share-to-chat.blade.php:3`).
   The Archived filter (§4.6) and GoMarket's filters drawer are the same.
3. **Connections is unusable on an iPhone.** *Rendered (measured).* The five tabs are 736px wide
   with no scroll, so **Search and Blocked are unreachable**, and with them the only list for
   unblocking (`app.css:2205`). A connected row needs at least 364px against 331 (SE) or 349
   (15). The grid item grows to fit, so on the SE it measured **514px**, with *Block* off-screen.
   *App:* a scrolling tab bar, and Disconnect and Block in the row's overflow.
4. **Overlays inside the chat shell sit under the header.** *Rendered* for the location
   switcher: the header stays undimmed and tappable, and the sheet's top 10.5px is hidden.
   *Code* for the lightbox, forward sheet and calls notice (`app.css:530`; the comment at the top
   of `user-preview.blade.php` explains the mechanism). *App:* present them above the shell.
5. **Share to chat is unreachable, and broken if reached.** *Rendered.* Its one trigger cannot
   exist yet. `__note-input`, `__search-input`, `__room-btn` and `__room-cta` have no CSS
   (`share-to-chat.blade.php:42,50,60,72` vs `app.css:6627-6726`). *App:* skip it until it has a
   trigger; GoMarket's Share is the system sheet.
6. **Starred leaves page scroll locked.** *Rendered (measured).* `body.style.overflow` is still
   `hidden` after closing (`starred-messages.blade.php:5`). That is harmless on the fixed `/yard`
   shell, but it is a leak. The unstar button is hover-only (`:73`).
7. **Kamer's thinking dots never show.** *Code.* `send()` sets `isLoading` on and off within one
   request, and the member's own message appears only with the reply (`KamerChat.php:62-91`).
   *App:* show the member's bubble at once and the dots while waiting, as the markup intends.
8. **Kamer calls the product "Cameroon Network".** *Code.* In the greeting and in nine lines of the AI prompts
   (`KamerChat.php:40-41`, `AIService.php:12` and on). It also suggests *How does Solidarity work?*
   (not live), and picks the suggestions' language by `language_pref`, not the toggle
   (`kamer-chat.blade.php:98-100`).
9. **Discover's hero text overlaps its dots, and its copy is stale.** *Rendered (measured).* The
   line ends at y 272 and the dots start at 265. The slides sell *Solidarity in Action* and list
   GoMarket under *Much More Coming* (`discover-modal.blade.php:202-216`). The autoplay interval
   is never cleared after closing (`:241-250`).
10. **The forward sheet loses its centring while it animates.** *Code.* `x-transition.scale.90`
    writes `transform: scale(.9)` over `translate(-50%,-50%)`, so for 150ms in and 75ms out its
    corner sits at the centre (`chat-room.blade.php:1786`, `app.css:2868-2872`).
11. **The call cards are wider than a phone.** *Code.* Fixed at 380 / 360 / 480px
    (`call-manager.blade.php:21,73,145`). Deferred with calls.
12. **The PWA banner covers Kamer's composer.** *Rendered (measured, first attempt).* z 70 over
    z 50, at y 557–655 over a textarea at y 580; a tap on the input hits the banner
    (`pwa-install.blade.php:55`). Web only.
13. **The location prompt overflows an SE and never auto-dismisses.** *Rendered + Code.* The 360px
    card runs past the right edge (`location-tracker.blade.php:14,30`). Its countdown queries
    `[x-data*="showToast"]`, which matches nothing, so the 20s bar never moves (`:490`). A failed
    switch is only logged (`:589`).
14. **The notification bell's panel starts 36px off-screen.** *Rendered (measured, first
    attempt).* It is not one of the twenty: a 351px panel anchored to the bell's right edge
    puts its left edge at x −36 on an SE (`notification-bell.blade.php:200-202`).
15. **A correction to pass 4 §5, and the scrim count.** *Code.* `.comm-modal` is full screen at
    ≤640px, so it never appears on a phone at 94vw / 88vh / radius 16. Scrims now number nine:
    .15, .30, .40, .45, .50, .55, .70, .85, .92. The recommendation stands: one sheet and one
    dialog with a single scrim, plus the deliberate 15% for selection.

---

## 8. Dart for this pass

```dart
/// Overlays. Phone values; see docs/ui-parity/12-overlays.md.
abstract final class CnOverlay {
  // Alpine's default x-transition, used wherever a view writes a bare one.
  static const defaultIn = Duration(milliseconds: 150);
  static const defaultOut = Duration(milliseconds: 75);
  static const defaultCurve = Cubic(0.4, 0.0, 0.2, 1.0);
  static const defaultScale = 0.95;
  static const handoff = Duration(milliseconds: 180); // drawer/Discover -> next overlay

  static const scrim = Color(0x80000000);             // black/50
  static const scrimSelection = Color(0x26000000);    // .15: the message stays readable
  static const scrimDiscover = Color(0xB30F172A);     // slate-900/70, plus 12px blur
  static const scrimLightbox = Color(0xD9000000);     // .85
  static const scrimPhoto = Color(0xEB000000);        // .92

  // Discover
  static const discoverInset = 12.0;
  static const discoverMaxHeight = 0.92;              // of the screen
  static const discoverRadius = 24.0;
  static const discoverIn = Duration(milliseconds: 300);
  static const discoverOut = Duration(milliseconds: 200);
  static const discoverHero = 256.0;
  static const discoverAutoplay = Duration(seconds: 5);
  static const discoverSoonCard = 200.0;
  static const discoverSoonBadge = 9.0;               // unscaled

  // Kamer AI: full screen, from the right
  static const kamerIn = Duration(milliseconds: 300);
  static const kamerOut = Duration(milliseconds: 200);
  static const kamerBubble = 17.5;
  static const kamerComposerMax = 120.0;
  static const kamerSend = 40.0;

  // User preview card
  static const previewMinWidth = 260.0;
  static const previewMaxWidth = 360.0;               // and at most 92% of the width
  static const previewPad = EdgeInsets.symmetric(horizontal: 24, vertical: 28);
  static const previewAvatar = 72.0;
  static const previewName = 22.5;
  static const previewCta = 17.0;

  // Communities / Connections: full screen
  static const commTitle = 24.0;
  static const commSidePad = 22.0;
  static const commTab = 17.5;
  static const commTabUnderline = 3.0;                // cm-yellow
  static const commTabsScroll = true;                 // 736px of tabs

  // Centred cards
  static const starredMaxWidth = 448.0;
  static const shareMaxWidth = 440.0;
  static const forwardSize = Size(340, 480);
  static const callsNoticeWidth = 320.0;

  // Location switcher: bottom sheet
  static const sheetRadius = 20.0;                    // top corners
  static const sheetBar = 4.0;                        // cm-green -> cm-red -> cm-yellow
  static const sheetIn = Duration(milliseconds: 300);
  static const sheetBodyMax = 0.60;
}
```

---

Next: the README's two remaining items, **icons** and **animation**. Divergences 1–4 and 13 are
the ones worth fixing on the web before the app copies them.
