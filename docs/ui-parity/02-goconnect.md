# Pass 2 — GoConnect

The hard one, and the one to build first after the shell. Three panels, a back stack that
took real work to get right, and a long-press selection mode that fought the browser the
whole way. Everything below is read from the views, `resources/js/yard-chat.js`,
`resources/js/overlay-history.js` and `resources/css/app.css`; font sizes are the **phone**
values.

Sources: `resources/views/yard/index.blade.php` (1,102 lines),
`resources/views/livewire/yard/{room-list,chat-room,room-info}.blade.php` (727 / 1,877 /
804), `resources/js/yard-chat.js` (1,686), `resources/js/overlay-history.js` (149), and
`app.css` lines 775–1250 (list), 1249–1600 (rows, FAB), 1590–3350 (chat), 7028–7319
(selection, jump).

---

## 1. Three panels, one visible at a time

Inside `.yard-container` (the fixed box from [pass 1](01-layout-navigation.md)), below
768px:

```
┌─ .yard-panel--list ────────┐   absolute, inset 0
│  header 56 · filters · rooms│
└─────────────────────────────┘
┌─ .yard-panel--chat ────────┐   absolute, inset 0, z-index 3
│  parked at translateX(100%) │   → translateX(0) when .yard-panel--active
└─────────────────────────────┘
┌─ .yard-panel--info ────────┐   fixed, top 96px, inset-x 0, bottom 0, z-index 40
└─────────────────────────────┘
```

| | |
|---|---|
| Slide | `transform .3s cubic-bezier(.4, 0, .2, 1)`, with `will-change: transform` |
| List while a room is open | `.yard-panel--hidden-left` → `translateX(-30%)` — a parallax, not a full exit |
| Chat paper | `#e8ded4` plus a 60×60 repeating SVG cross pattern in `#d4c8bc` at 30% opacity |
| Info panel | full-screen overlay, **not** a slide; `width: 100% !important` beats the 320px desktop rule |
| Ads panel | `display: none !important` |

`isMobile` is `window.innerWidth < 768`, recomputed on resize — the same 768px line the CSS
uses, and the breakpoint that decides whether opening a room is *navigation* (see next
section).

`.yard-container--fullscreen` is added when `activeRoom && isMobile`. It changes nothing on
a phone today — both the base rule and the override set `top: 96px` — so treat it as a hook,
not a behaviour.

---

## 2. The back stack — read this twice

Three things can be "open" over the chat list, and Back has to peel exactly one layer each
press, in the right order. This is the part most likely to go wrong in a port, and the part
with the most hard-won detail behind it.

**Two cooperating mechanisms.**

`yardApp` (`yard/index.blade.php:589`) owns the room itself:

- opening a room on a phone does `history.pushState({room: id}, '', '?room=' + id)` and
  dispatches `chatroom-entered`;
- **on a desktop it does `replaceState` instead** — side-by-side panels mean switching rooms
  is not navigation and must not pile up history entries;
- `goBack()` clears the room, dispatches `chatroom-exited`, then calls `history.back()`;
- its own `popstate` listener closes the room — but only after checking the flag below.

`overlay-history.js` owns everything else, as a stack. `x-overlay="expr"` on the element
whose Alpine state owns the overlay: opening pushes a history entry, `popstate` pops the
newest and calls its close, and closing by ✕ / backdrop / Escape calls `history.back()`
itself to step over the entry it pushed. Nested overlays unwind newest first.

**The flag that keeps them from double-firing.** When the overlay stack consumes a `popstate`
it sets `window.__cnOverlayConsumedPop = true` for one tick. `yardApp`'s listener returns
early if it sees it. Without that, one back press closed the overlay *and* the room —
dismissing a selected message dropped the user all the way back to the chat list.

**`pendingBacks`** counts the `history.back()` calls the stack makes itself, so the resulting
`popstate` is swallowed rather than read as a user press. It claims the pop too, for the same
reason.

**What registers as an overlay, in unwind order:** the message-selection mode
(`x-overlay="sel.on" x-overlay-close="selClear()"`), the image lightbox, the forward modal,
the room-info panel (`x-overlay="showInfo"` on the container), and the app drawer from pass 1.

Two details that are easy to lose:

- `x-overlay-close` exists because some states cannot be closed by assigning `false` to the
  expression that reveals them. Selection needs `selClear()` — the default close would only
  hide the toolbar and leave the messages highlighted with nothing to act on.
- The lightbox and selection registrations sit on **`wire:ignore`** wrappers. Livewire morphs
  this component on every arriving message; without `wire:ignore` the morph destroys and
  recreates the directive mid-selection, the pushed entry is orphaned, and a later close
  calls `history.back()` once too often — navigating out of the room exactly when the user
  was picking a message.

**For Flutter:** these are Navigator routes, not booleans. The ordering contract is what
matters: info closes before chat, selection closes before chat, and the iOS edge-swipe must
drive the same stack as the back gesture does here. The flag-and-counter machinery above is
browser-history plumbing with no Flutter equivalent — but the *bug* it fixes (one gesture
dismissing two layers) is just as reachable with a careless `Navigator.pop`.

Also carried by the URL: `?room={id}` is written on every room change so a refresh reopens
the right room, and stripped whenever no room is open — otherwise the next refresh reopened
a room the user had just left. The app's equivalent is restoring the last room on cold start,
and the same rule applies: clear it when they leave.

---

## 3. Panel 1 — the room list

### Header

`.yard-header`, 56px, 8px side padding, 8px gap. **Below 768px it is white** with a 1px
`slate-200` underline, `slate-900` title, `slate-500` buttons — the brand-blue gradient bar
is tablet and desktop only. Title "GoConnect" at **24px** weight 700.

Buttons are 40×40 circles with 22px icons, in this order: Refresh · Contacts (with a dot
badge) · Archived (with a count badge, `99+` capped) · ⋮ overflow.

The overflow menu is 224px wide, `rounded-xl`, white, 1px `slate-100`, `z-50`, items at
17.5px: Search · New chat · Contacts (with a count pill) · Group · rule · Starred messages.

`.yard-header__btn-badge` sits at `top: 2px; right: 2px`, min 16×16, the green gradient
`#007A5E → #00a37a`, 12px weight 700, ringed 1.5px — in `cm-bar-dark` on the blue bar and
**white on the phone's white bar**.

### Filter pills

`.yard-filters`: 8px gap, `10px 14px 6px` padding, horizontal scroll with the scrollbar
hidden. Four pills — **All · Unread · Favorites · Groups** — at 17px weight 700, `6px 14px`,
fully rounded, `slate-100` on `slate-600`.

Active: `cm-yellow` background, `#1a2238` text, `0 1px 4px rgba(26,34,56,.18)`.

Each pill except *All* carries a count: min 20×20, 14.4px weight 700, the green gradient with
a 1.5px white ring. At zero it goes quiet — `slate-200` on `slate-500`, no shadow, no ring,
weight 600. Inside an active pill the count inverts to `#1a2238` on `cm-yellow`.

A fifth state replaces the whole row: when the filter is `archived`, `.yard-archived-bar`
takes its place — a 32px back button and the word "Archived" at 16px.

### Sections and rows

Rooms are grouped under `.yard-section-label` (14px, weight 600, uppercase, `.05em` tracking,
`slate-400`): **National · Regional · Groups · Messages**.

`.yard-room` is 10px/12px padding, 12px gap, full width, left-aligned, `background .12s`:

| Part | Phone value |
|---|---|
| Avatar | 52px circle, 16px initial, white text |
| Name | **19px** weight 700 `slate-900`, ellipsised |
| Time | **14px** `slate-400` |
| Preview | **16.4px** weight 600 `slate-700`, ellipsised |
| Unread pill | **13px** weight 700, white on **`cm-green` `#243a5c`** (navy), min 20×20 |
| `@` mention badge | **14px** weight 800, white on `cm-green`, 20×20 |
| Type badge | 13px, `slate-200` on `slate-500`, `1px 7px` |
| Pin | 16px `slate-400`, **rotated 45°** |
| Online dot | 10px, `#22c55e`, 2px white border, `bottom: 1px; right: 1px` |

Active row (desktop only in practice, since a phone shows one panel at a time):
`rgba(252,209,22,.14)` background, **5px `cm-yellow` left border with `padding-left` dropped
to 7px so the content does not shift**, inset 1px yellow ring, name and time forced to
`cm-green`.

Room avatars by type — emoji on a tinted circle: National 🇨🇲 `bg-cm-green`, Regional 🗺️
`bg-amber-500`, City 📍 `bg-blue-500`, Private group 👥 `bg-violet-500`, DM 💬 `bg-cm-green`.
A DM with a photo shows the photo; without one it shows an initial on a gradient from
`AvatarPalette`, seeded `user:{id}` so one person keeps one colour everywhere.

### The FAB

`.yard-fab-wrap` is `md:hidden` — **phones only**, `absolute; bottom: 20px; right: 20px`,
`z-index: 5`. The button is **56px with a 16px radius** (a squircle, not a circle),
`cm-green` on white, shadow `0 4px 12px rgba(0,107,63,.35)`; hover scales 1.06, press 0.96.
Its menu opens upward: `bottom: 64px`, 180px min width, 12px radius, items 16.4px with 32px
coloured emoji circles.

### Empty states

Four of them, and they are not interchangeable — `room-list.blade.php:258–300` branches on
the active filter, so *Unread*, *Groups* and the unfiltered list each explain themselves
differently, each with a "show all" button back to `all`. The decorative
`.yard-empty-illus--bubbles` has three floating bubbles at 26px. Build all four; a single
"no chats" screen is a visible regression.

---

## 4. Panel 2 — the chat room

### Header

`.yard-chat__header`, 56px, 8px padding, 10px gap, **white** with a 1px `slate-100`
underline, `z-index: 5`.

Back 40×40 `slate-500` · avatar 38px (`cm-green` ground, 17.6px glyph, online dot at
`bottom: 0; right: 0`) · name **19px** weight 700 and meta **14px** `slate-400`, the pair
tappable to open room info · then 40×40 action buttons.

Status line: online is `#22c55e` weight 600; last-seen is `slate-400`. Both 14px.

### Thread

`.yard-chat__messages`: `flex: 1`, scrolls, **12px/8px padding on a phone** (16px/24px from
768px), 4px gap, column.

| | |
|---|---|
| Row | `.yard-msg`, `align-items: flex-end`, 6px gap, **max-width 85%** |
| Own | `align-self: flex-end`, `flex-direction: row-reverse` |
| Avatar | 32px, `slate-300`, 12px initial — others only |
| Sender name | **14.4px** weight 700 `cm-green`, 4px left padding |
| Bubble | `7px 10px`, radius 12 with the near corner cut to **4px**, **18px** text, line-height 1.45, min-width 60px, `0 1px 1px rgba(0,0,0,.06)` |
| Own bubble | `#e1ffc7` on `slate-900` |
| Other bubble | `#fff` on `slate-800` |
| Deleted | `rgba(255,255,255,.6)`, italic, `slate-400`, **16.4px** |
| Time | **13px**, right-aligned, 2px top margin |
| Date divider | **14.4px** weight 600, white on `rgba(0,0,0,.2)`, `3px 12px`, radius 12 |
| System line | **15.6px** `slate-500`, `rgba(255,255,255,.6)`, radius 12, centred |

A system line naming a person is a button that opens their card — inherits the pill's type,
with a dotted `slate-400` underline at 3px offset as the only affordance.

Reply quote: **14.4px**, `rgba(0,107,63,.08)` ground, **3px `cm-green` left border**, radius
4, ellipsised to one line. It is a button — tapping it jumps to the quoted message, hover
deepens the ground to `rgba(0,107,63,.16)`.

### Ticks

`components/message-ticks.blade.php`, own messages only, 14×12 (the clock is 12×12), 3px left
margin, `color 240ms ease-out`:

| State | Colour | Mark |
|---|---|---|
| sending | `rgba(30,51,82,.45)` + 1.2s opacity pulse | clock |
| sent | `rgba(30,51,82,.55)` | one check |
| delivered | `rgba(30,51,82,.55)` | two checks |
| read | **`#34B7F1`** + a 320ms scale pop (0.85 → 1.12 → 1) | two checks |

The pop on turning blue is the detail people notice without being able to name. Keep it.

### Input bar

`.yard-chat__input-bar`: white, 1px `slate-100` top border, `6px 8px`. The form is
`align-items: flex-end`, 6px gap — so the send button stays bottom-aligned as the textarea
grows.

- **Pill** `.yard-chat__input-pill`: `slate-100`, **radius 24**, `2px 4px`, `flex: 1`. Also
  the anchor for the `@mention` dropdown.
- **Pill buttons** (attach, emoji): 36px circles, `slate-500`, 23px glyph, hover `cm-green`.
- **Textarea**: min-height 36, **max-height 120**, `7px 4px`, transparent, **18px**,
  line-height 1.4, no resize.
- **Mic**: 38px, `cm-green` ground, **`cm-yellow` glyph**, 2.5px `cm-green` border; hover
  `cm-green-light` + `scale(1.08)`, press `scale(0.95)`.
- **Send**: 40px circle, `cm-green` on white; hover `cm-green-light`, press `scale(0.93)`,
  disabled at 0.5 opacity.

### The three bars that appear above it

| Bar | Look |
|---|---|
| Reply (`.yard-chat__reply-bar`) | white, 1px `slate-100` top, `6px 12px`, 8px gap, with a **3×28px `cm-green` accent** at radius 2 |
| Search (`.yard-chat__search-bar`) | white, 1px `slate-100` bottom, `6px 12px`, input at 17px |
| Pinned (`.yard-chat__pinned-bar`) | **`#fffbeb`** with a `#fef3c7` underline; the toggle row is `8px 12px`, items separated by 1px `#fef3c7` |

### Typing

`.yard-chat__typing`: white, `4px 16px 6px`, 8px gap. Three 6px `cm-green` dots bouncing
`-4px` on a 1.2s loop, delays 0 / 0.2 / 0.4s, idle opacity 0.3. Label **14px** italic
`cm-green`.

---

## 5. Jump to the newest message

`.yard-jump`: `position: absolute` inside the chat panel, so it holds still while the thread
scrolls under it. On a phone **38×38 at `right: 16px; bottom: 76px`** (42×42 and 84px from
768px), white, `slate-600`, `0 2px 10px rgba(0,0,0,.18)`, `z-index: 20`. Enters over 150ms
from `opacity 0` + 4px down, leaves over 100ms.

Badge: `top: -6px; right: -4px`, min 20×20, `cm-green` ground, white **11px** weight 800,
ringed 2px white, capped at `99+`.

The logic, from `yard-chat.js`:

- `atBottom` is `scrollHeight - scrollTop - clientHeight < 120` — **a 120px margin, not an
  exact match**, because sub-pixel heights and the composer's own growth mean it rarely lands
  exactly at the end, and a jump button showing while the reader is plainly at the bottom is
  worse than no button.
- `newSinceScroll` counts arrivals from other people while `!atBottom`.
- Reaching the bottom clears the counter — arriving there is the acknowledgement.
- `scrollIfFollowing()` only follows the conversation when the reader was already following
  it; arrivals do **not** yank the thread out from under someone reading back.
- `jumpToLatest()` scrolls smoothly and clears both.
- On opening a room the thread positions on **the first unread message**, or the bottom if
  there is none, retrying briefly because the messages may not be in the DOM yet.
- After "load older", the message that was at the top gets `.yard-msg-anchor-pulse` — a 1.4s
  green ring that fades out — so the reader can find their place again.

---

## 6. Long-press selection

WhatsApp's model, and the piece with the most browser-specific scar tissue. Most of that
scar tissue is irrelevant in Flutter; the **gesture numbers and the resulting UI** are not.

### The gesture

| | |
|---|---|
| Hold | **450ms** |
| Cancel on movement | **>16px horizontal or >24px vertical** |
| Haptic | `navigator.vibrate(12)` on fire |
| On fire | enters selection mode on that message **and** opens the reaction row |
| Reaction row anchor | the **bubble's** rect, read at touch-start, 56px above its top — *not* the finger |

The movement thresholds were raised deliberately: a thumb moves while it presses, and 10px
cancelled perfectly deliberate long presses, which is why the reaction bar only appeared
sometimes. A scroll is a much larger and mostly vertical movement than this.

Anchoring the reaction row to the bubble rather than the touch point matters because
otherwise its buttons open exactly where the finger is about to lift.

### While selecting

- Tapping another message toggles it in or out. Unpicking the last one leaves selection mode.
- Once more than one is picked, the reaction row closes — reactions belong to a single
  message.
- Escape clears; so does the back gesture, through the overlay stack.
- **There is no cancel button.** Selection is left by unpicking, Escape, or back.

### The picked look

```
.yard-msg--picked-row    background rgba(1,80,131,.10)
                         box-shadow 0 0 0 100vmax rgba(1,80,131,.10)
                         clip-path  inset(0 -100vmax)
```

The tint runs the **full width of the row**, not just the bubble, so a column of picked
messages reads as one block. The `100vmax` spread with a clip-path is how that is achieved
without a wrapper element; in Flutter it is simply a full-width row background.

`.yard-msg__bubble--picked` also outlines the bubble `2px rgba(1,80,131,.45)` at 2px offset,
as a fallback for browsers without `:has()`. A single selected message additionally lifts
above the dimmed backdrop with `z-index: 1001` and `0 6px 24px rgba(2,25,45,.35)`.

### The selection toolbar

`.yard-sel-bar` covers the chat header: `absolute; top/left/right: 0`, **56px**,
`z-index: 1003`, white, 1px `slate-200` underline, `0 2px 10px rgba(2,25,45,.08)`, 2px gap,
6px side padding. Enters on a 150ms opacity fade.

Left to right: the **count** (16.8px weight 700 `slate-900`, 10px left margin), a spacer,
then 40px circular buttons in `slate-700`:

| Button | Shown when |
|---|---|
| Reply | exactly one picked |
| Copy | always — joined **in chronological order, not pick order**, so a pasted conversation reads the way it happened |
| Star | always |
| Forward | always |
| Delete | only when the selection contains something this user may delete (`selOwnCount() > 0`); `#e11d48` |
| Pin | exactly one picked |

Its ⋮ dropdown is 230px, `4px 0`, radius 12, 1px `slate-200`, `z-index: 1004`.

### Reaction row and emoji grid

`.yard-sel-react` is `position: fixed`, `z-index: 1002`, white, fully rounded, `6px 8px`,
2px gap, `0 8px 28px rgba(2,25,45,.28)`. The "more" grid `.yard-sel-grid` is
`max-width: min(300px, 100vw - 32px)`, radius 16, 10px padding, 4px gap.

Both are deliberately **outside** the phone breakpoint. They used to be inside it, back when
picking a message was a phone-only idea — which left a long press on a touchscreen laptop
selecting a message and offering nothing to do with it. Selection is not about screen width,
so neither is its UI.

### What is hidden on a phone

Below 768px: `.yard-ctx-menu` (the desktop right-click dropdown), `.yard-msg__chevron`,
`.yard-msg__hover-emoji`, `.yard-msg__quick-emoji` — all `display: none !important`. Hover
affordances are a mouse idea; a tap still fires the compatibility `mouseenter`, so they
appeared over the bubble next to the long-press UI. **Long press is the only way in on a
phone.** The app should not offer a second one.

### Text selection is off

Under `(hover: none) and (pointer: coarse), (max-width: 767px)` the thread, the backdrop and
all three selection surfaces get `user-select: none` and `-webkit-touch-callout: none`, which
stops the iOS copy/look-up callout and Android's magnifier. Inputs stay selectable.

Both conditions are present because neither covers the other: the pointer query catches a
tablet or a large phone in landscape that a 767px breakpoint misses, and the breakpoint
catches a narrow window on a machine that reports a mouse. Irrelevant to Flutter, except as
the reason the thread is not selectable text — which is also why the keyboard used to drop
mid-gesture.

### The keyboard stays up

`keepKeyboard($event)` is on `@pointerdown` of the reaction row, the emoji grid, the
selection bar and the backdrop, and `lpStart` records whether the composer was focused so
`restoreKeyboard()` can put it back. In Flutter: do not unfocus the composer when entering
selection mode.

---

## 7. Panel 3 — room info

A full-screen overlay on a phone, from `top: 96px`, `z-index: 40`. Registered with
`x-overlay="showInfo"` so Back closes it before it closes the chat.

Hero: cover, 96px avatar overlapping it, name, then a subtitle that reads
`{room type} · {n} members` for a group. Three actions in a row: Profile (DMs) or Add
(groups), Voice, Add.

Then collapsible sections, each a `.wa-info-section__row` toggle at 17.5px weight 500
`slate-700`:

1. **About / Description**
2. **Media, links and docs** — a horizontal strip of thumbs, expanding to a grid
3. **Starred messages**
4. **Pinned messages**
5. **Join requests** — groups, with a count; approve and reject per row
6. **Members** — with an *Add member* row first, `Admin` badges, online dots, and a remove
   button per row
7. **Danger** — leave or delete

`.wa-add-member` is a second screen inside the panel, with its own back arrow, selected-member
chips and a search list.

---

## 8. The twelve message types

`App\Enums\MessageType` — every one of these has to render, and three of them carry their own
sub-UI:

`text` · `image` · `video` · `audio` (waveform player, speed control, played/unplayed bars) ·
`file` (icon card) · `system` · `solidarity_card` · `gif` · `sticker` · `call_log` · `poll`
(own vote UI and voter list) · `share_card`

Room types, `App\Enums\RoomType`: `national` · `regional` · `city` · `private_group` ·
`direct_message`.

`solidarity_card` is worth a note: Solidarity has no member-facing entry point on the web
(see [pass 1, finding 1](01-layout-navigation.md)), so no one can create one — but old rows
may exist and the renderer still has to handle the type.

---

## 9. What the web leaves to us

On top of the pass-1 list:

| Native | Why |
|---|---|
| Keyboard inset | The composer is in a `position: fixed` panel relying on the visual viewport. A Flutter `Scaffold` resizes; the composer must sit above the keyboard and the thread must keep its scroll offset. |
| Haptics | `navigator.vibrate(12)` is the web's best effort. Use a light impact on long-press fire. |
| Scroll anchoring | The web restores scroll position by hand after prepending older messages, then pulses the anchor. Flutter's `center`-keyed slivers do this properly — keep the pulse. |
| Edge swipe | Must pop the same stack, in the same order, as section 2. |
| Audio playback | The waveform player is hand-built over `<audio>`. A native player is fine as long as the bars, speed pill and played/unplayed colours match. |
| Image viewer | `.yard-lightbox` is a plain overlay. Pinch-zoom and a dismiss drag are expected natively. |

---

## 10. Divergences found in this pass

1. **The jump-to-latest badge never got the phone type scale.** `.yard-jump__badge` is
   declared at the top level (`app.css:7078`), *outside* any media query, so it renders
   **11px** on a phone while every sibling badge was scaled — `.yard-room__unread` is 13px,
   `.yard-msg__time` 13px, `.yard-sel-bar__count` 16.8px. The jump button and the selection
   bar were built at the same time and only one of them is in the override block. Visible as
   a counter that looks a size too small. **Not changed** — it is a one-line CSS addition,
   but it changes the live UI and that is your call. The app should use the scaled size
   (13px) or match the web exactly; say which.

2. **Two errors in the reference page, caught by doing this pass.** The published specimens
   had the room-row unread pill drawn as the green gradient `#007A5E → #00a37a` and the thread
   paper as `#efeae2`. The real values are **`cm-green` `#243a5c`** (the green gradient belongs
   to the *filter* counts and the header badge, not the room row) and **`#e8ded4`**.
   **Fixed and republished.** This is exactly what the page is for.

3. **Several shadows and fallbacks still assume `cm-green` is green.** `.yard-fab` is
   `cm-green` (navy `#243a5c`) with a shadow of `rgba(0,107,63,.35)` — a green glow under a
   navy button. Same in `.yard-msg__reply-preview` (`rgba(0,107,63,.08)` ground, navy border),
   `.yard-chat__send-btn`'s hover, and `.yard-jump__badge`'s dead fallback
   `var(--color-cm-green, #1e7a3e)`. The web renders navy-with-a-green-glow today, so
   **parity means reproducing it** — recorded because it reads as a bug to anyone building
   from the tokens, and because it is the same renaming that left `cm-green` a navy.

4. **`.yard-container--fullscreen` does nothing.** Both the base rule and the `max-width:
   1023px` override set `top: 96px`, and the 640–1023px override sets both to 132px, so the
   class never changes a value. It is applied on every phone room open. Harmless; noted so
   nobody ports a behaviour that does not exist.

5. **`.yard-panel--info` is positioned for the wrong header at 640–767px** — carried over
   from pass 1, now confirmed to matter here: the info panel is the one surface that hard-codes
   `top: 96px` while the container has a 132px override for that band, so it slides 36px under
   the header. Never bites an iPhone in portrait.

6. **The room-list active row is effectively desktop-only.** `.yard-room--active` is fully
   styled — yellow left border, tint, recoloured name — but on a phone the list is covered by
   the chat panel the moment a room is active, so the state is almost never seen. Build it
   anyway for the parallax peek during the slide; just do not spend time on it.

---

## 11. Dart for this pass

```dart
/// GoConnect. Mirrors the Yard views and resources/js/yard-chat.js —
/// see docs/ui-parity/02-goconnect.md.
class CnChat {
  CnChat._();

  // ── Panels ──
  static const panelSlide = Duration(milliseconds: 300);
  static const panelCurve = Cubic(0.4, 0.0, 0.2, 1.0);
  static const listParallax = -0.30;        // translateX(-30%)
  static const paper = Color(0xFFE8DED4);
  static const paperPattern = Color(0xFFD4C8BC); // at 30% opacity, 60x60 tile

  // ── Room list ──
  static const listHeader = 56.0;
  static const roomRowPadV = 10.0;
  static const roomRowPadH = 12.0;
  static const roomAvatar = 52.0;
  static const roomName = 19.0;
  static const roomPreview = 16.4;
  static const roomTime = 14.0;
  static const roomUnread = 13.0;
  /// Navy, despite the name. The green gradient is the FILTER count, not this.
  static const unreadGround = Color(0xFF243A5C);
  static const filterCountGradient = [Color(0xFF007A5E), Color(0xFF00A37A)];
  static const activeRowTint = Color(0x24FCD116);   // rgba(252,209,22,.14)
  static const activeRowBorder = 5.0;
  static const onlineDot = Color(0xFF22C55E);
  static const fabSize = 56.0;
  static const fabRadius = 16.0;               // a squircle, not a circle

  // ── Thread ──
  static const threadPadV = 12.0;
  static const threadPadH = 8.0;
  static const threadGap = 4.0;
  static const bubbleMaxWidthFraction = 0.85;
  static const bubbleText = 18.0;
  static const bubbleLineHeight = 1.45;
  static const bubbleRadius = 12.0;
  static const bubbleNearCorner = 4.0;
  static const bubbleOwn = Color(0xFFE1FFC7);
  static const bubbleOther = Color(0xFFFFFFFF);
  static const msgTime = 13.0;
  static const senderName = 14.4;
  static const msgAvatar = 32.0;
  static const tickRead = Color(0xFF34B7F1);
  static const tickGrey = Color(0x8C1E3352);    // rgba(30,51,82,.55)
  static const tickPop = Duration(milliseconds: 320);

  // ── Composer ──
  static const pillRadius = 24.0;
  static const textareaMin = 36.0;
  static const textareaMax = 120.0;
  static const textareaText = 18.0;
  static const sendButton = 40.0;
  static const micButton = 38.0;

  // ── Jump to latest ──
  static const jumpSize = 38.0;                 // 42 from 768px
  static const jumpRight = 16.0;
  static const jumpBottom = 76.0;               // 84 from 768px
  /// The web renders this at 11px because it missed the phone type scale.
  /// 13px matches every sibling badge — see divergence 1.
  static const jumpBadgeText = 13.0;
  static const atBottomSlack = 120.0;

  // ── Selection ──
  static const longPress = Duration(milliseconds: 450);
  static const longPressCancelX = 16.0;
  static const longPressCancelY = 24.0;
  static const selBar = 56.0;
  static const selCount = 16.8;
  static const pickedTint = Color(0x1A015083);  // rgba(1,80,131,.10)
  static const pickedOutline = Color(0x73015083);
  static const selDanger = Color(0xFFE11D48);
  /// The reaction row anchors to the bubble's top, not the finger.
  static const reactionRowOffset = 56.0;
}
```

---

Next: **pass 3 — GoMarket.** Feed, sidebar filters, listing card and detail, composer,
checkout, seller profile, and the floating product chat dock.
