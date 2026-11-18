# Pass 4 — Shared components

The pieces that appear on more than one screen. Most of them exist in several slightly
different versions, which is the main thing this pass is for: in the app they should be one
widget each, and this is the record of which variant is which. Font sizes are the **phone**
values.

---

## 0. A correction to the method

`extract.php` only ever read `resources/css/app.css`. That was wrong: **eight views ship
their own `<style>` blocks**, 509 lines between them, and everything in them was invisible to
the token layer — including the notification badge, the connection toast, the connections
badge, the Discover modal and the story strip.

The extractor now reads those eight files as a second source, and `tokens.json` gained:

| Key | What |
|---|---|
| `keyframes` | all **38** keyframe sets in the product and where each is declared — 26 in `app.css`, 12 in views |
| `inline_style_colors` | the **20** colours declared in a view's `<style>` that are in no `@theme` token, each with the views it appears in |
| `inline_style_views` | the eight files, listed rather than globbed so a ninth is a deliberate addition |

`resources/views/welcome.blade.php` is deliberately excluded: it is the stock Laravel starter
page, no route renders it, and its 247 CSS blocks and 4 keyframe sets are not this product's.
It is also dead code — worth deleting, but not by me.

The README's earlier claim that re-running the extractors makes the spec follow is now true.
Before this pass it was not.

---

## 1. The avatar — the one genuinely shared component

`resources/views/components/user-avatar.blade.php`. Its own comment says why it exists: the
header, the drawer, the admin bar and the profile pages each drew their own circle, and most
of them only ever drew the initial, so a member who had uploaded a photo still saw a letter
everywhere outside the chat.

```blade
<x-user-avatar :user="$u" size="h-8 w-8" text="text-sm" letters="1" ring="ring-2 ring-white/25" />
```

| Prop | Default | Note |
|---|---|---|
| `user` | the signed-in member | |
| `size` | `h-8 w-8` | a Tailwind pair, not a scale — these sit in headers, drawers and tables that were each already sized differently |
| `text` | `text-sm` | initial size |
| `letters` | `1` | **two reads better in a table, one in a header button** |
| `ring` | — | |

Behaviour:

- the stored column holds a **path**, not a URL, except for Google accounts which store a
  full URL — so `str_starts_with($path, 'http')` decides between the value and
  `asset('storage/'.$path)`. The API's `UserResource` does the same thing, which is why
  `avatar_url` is always absolute.
- the initial is `mb_substr(username ?? name ?? '?', 0, letters)`, uppercased.
- no photo means a **gradient** from `AvatarPalette`, not a flat colour.

### The palette

`app/Support/AvatarPalette.php`: **18 two-stop gradients**, each `to bottom right`, picked
by `crc32($seed) % 18`. The seed is `'user:' . $id`, so **one person keeps one colour across
the whole app** — chat, header, drawer, forward list, member list. Rooms use `'room:' . $id`.

| # | Pair | From | To |
|---|---|---|---|
| 0 | rose-400 → rose-600 | `#ff637e` | `#ec003f` |
| 1 | pink-400 → pink-600 | `#fb64b6` | `#e60076` |
| 2 | fuchsia-400 → fuchsia-600 | `#ed6aff` | `#c800de` |
| 3 | purple-400 → purple-600 | `#c27aff` | `#9810fa` |
| 4 | violet-400 → violet-600 | `#a684ff` | `#7f22fe` |
| 5 | indigo-400 → indigo-600 | `#7c86ff` | `#4f39f6` |
| 6 | blue-400 → blue-600 | `#51a2ff` | `#155dfc` |
| 7 | sky-400 → sky-600 | `#00bcff` | `#0084d1` |
| 8 | cyan-500 → cyan-700 | `#00b8db` | `#007595` |
| 9 | teal-400 → teal-600 | `#00d5be` | `#009689` |
| 10 | emerald-400 → emerald-600 | `#00d492` | `#009966` |
| 11 | green-500 → green-700 | `#00c950` | `#008236` |
| 12 | lime-500 → lime-700 | `#7ccf00` | `#497d00` |
| 13 | amber-400 → amber-600 | `#ffb900` | `#e17100` |
| 14 | orange-400 → orange-600 | `#ff8904` | `#f54900` |
| 15 | red-400 → red-600 | `#ff6467` | `#e7000b` |
| 16 | slate-500 → slate-700 | `#64748b` | `#334155` |
| 17 | stone-500 → stone-700 | `#79716b` | `#44403b` |

Six of the eighteen use 500→700 rather than 400→600, for contrast against white text.

**These hexes are derived, not transcribed, and that turned out to matter.** Tailwind v4
stores its palette in **OKLCH** and only resolves it at build time, so the compiled bundle
is the only place the real values exist. The v3 hexes everyone remembers are wrong by a
visible margin on exactly the saturated hues this palette uses — `rose-400` is `#ff637e`,
not `#fb7185`; `green-500` is `#00c950`, not `#22c55e`. `extract.php` now reads
`app/Support/AvatarPalette.php` for the order, resolves each class against
`public/build/assets/app-*.css`, converts OKLCH to sRGB and writes `CnAvatarPalette` into
[generated/cn_tokens.dart](generated/cn_tokens.dart). Use that; do not retype these.

**Port it exactly** — same list, same order, same `crc32`. A different hash gives everyone
a different colour than the web shows them, which is the sort of thing people notice
immediately about their own face.

---

## 2. Message ticks

`components/message-ticks.blade.php`. Covered in [pass 2 §4](02-goconnect.md), repeated here
because it is a component rather than a screen: 14×12 (clock 12×12), own messages only, 3px
left margin, `color 240ms ease-out`.

| State | Colour | Mark | Motion |
|---|---|---|---|
| `sending` | `rgba(30,51,82,.45)` | clock | 1.2s opacity pulse |
| `sent` | `rgba(30,51,82,.55)` | one check | — |
| `delivered` | `rgba(30,51,82,.55)` | two checks | — |
| `read` | `#34B7F1` | two checks | 320ms scale pop 0.85 → 1.12 → 1, but it runs on the wrapper **and** the icon inside it, so it renders compounded, 0.72 → 1.25 → 1 (see 11-animation.md). The app should pop once. |

Live upgrades come through an Alpine store (`$store.msgStatus[id]`) bound on the wrapper, so a
receipt changes the tick without re-rendering the bubble. The app's equivalent is a per-message
listenable, not a list rebuild.

---

## 3. Badges — seven treatments for one idea

Every badge is a rounded pill with a number in it, and no two agree:

| Badge | Ground | Size | Ring | Text |
|---|---|---|---|---|
| `.yard-room__unread` | `cm-green` `#243a5c` | min 20×20 | — | **13px** / 700 |
| `.yard-room__mention` | `cm-green` `#243a5c` | fixed 20×20 | — | **14px** / 800 |
| `.yard-filters__count` | gradient `#007A5E → #00a37a` | min 20×20 | 1.5px white | **14.4px** / 700 |
| `.yard-menu-count` | gradient `#007A5E → #00a37a` | min 20 × 18 | 1.5px white | 13px / 700 |
| `.yard-header__btn-badge` | gradient `#007A5E → #00a37a` | min 16×16 | 1.5px `cm-bar-dark`, **white on a phone** | **12px** / 700 |
| `.yard-jump__badge` | `cm-green` `#243a5c` | min 20×20 | 2px white | **11px** / 800 — the one that missed the phone scale ([pass 2 finding 1](02-goconnect.md)) |
| `.nb-badge` *(bell)* | gradient `#ef4444 → #dc2626` | min 18×18 | **2px `#2c4a3e`** | 10px / 800 |
| *Soon* pill | `amber-100` on `amber-700` | auto × auto | — | **13px** / 700 uppercase |

Three different grounds, four ring treatments, sizes from 10 to 14.4px. Two also animate:
`.nb-badge__pulse` is a `rgba(239,68,68,.45)` ring scaling 0.6 → 1.6 while fading over 1.6s,
and `.nb-pulse` shakes the bell itself ±12° over 500ms when a notification lands.

`#2c4a3e` deserves a line of its own: it is the bell badge's border, a **dark green that is in
no token and matches nothing** — the header it sits on is navy `#243a5c`–`#3a5a80`. It reads
as a ring of the wrong colour, and it is one of the 20 inline-style colours the extractor now
records.

**For the app:** one badge widget, variants `count` / `mention` / `alert`, with the navy
ground as the default, the green gradient for counts that summarise a list, and red for
notifications. Match the web's per-site choices from the table rather than unifying them, or
the app will look subtly unlike the site on every screen at once.

---

## 4. Toasts — three systems

| | `yardToasts` | `kc-notifier` (connections) | `wa-join-toast` |
|---|---|---|---|
| Position | `top-4 right-4` | `top: 96px; right: 16px` | own, see `app.css:2661` |
| Phone | unchanged | **`top: 76px`, `left/right: 8px`, full width** | `@media (max-width: 767px)` block |
| z-index | `9999` | `9999` (confetti `9998`) | — |
| Width | **288px** (`w-72`) | max **380px** | — |
| Shape | `rounded-xl`, `shadow-lg`, 1px border tinted by kind | radius **14**, `0 12px 32px rgba(15,23,42,.18)`, **4px left border** | — |
| Dismiss | **5000ms** | **8000ms** | — |
| Max | **3**, oldest shifted out | unbounded | — |
| Enter | 200ms, +16px x | `kcSlideIn .35s cubic-bezier(.18,.89,.32,1.28)`, +40px x and `scale(.95)` | — |
| Leave | 150ms opacity | **none**: the leave classes are defined nowhere, so it vanishes at once (see 11-animation.md) | — |

`yardToasts` has four kinds, each with a border colour and a 36px icon circle:

| Kind | Border | Circle | Icon |
|---|---|---|---|
| info *(default)* | `slate-200` | `cm-green/10` on `cm-green` | chat bubble |
| success | `emerald-200` | `emerald-100` on `emerald-600` | check |
| warning | `amber-200` | `amber-100` on `amber-600` | alert circle |
| error | `rose-200` | `rose-100` on `rose-600` | alert circle |

Title **17.5px** weight 600 `slate-900`; body **15px** `slate-500`, truncated to one line. The
whole toast is a button: tapping it opens the room it came from, or dismisses if there is none.
It listens on **two** events — `yard-toast` (from `pushToast`) and a plain `toast` (dispatched
from Livewire anywhere) — and the component is mounted outside `yardApp`'s scope deliberately,
so it survives the DOM moves a Livewire morph causes.

`kc-notifier` is the richer one, and it is the app's model for a social notification: a 44px
avatar on an indigo gradient (`#e0e7ff → #ddd6fe`, initial in `#4338ca`) with an 18px status
badge, title 14px/700, text 13px, and two buttons at 12px/600 radius 8. Blue `#3b82f6` for a
request, green `#16a34a` for an acceptance — including the left border, the badge, the primary
button and a 120° tinted wash over the whole card. It also fires a WebAudio chime, asks once
for the native Notification permission, pulses the document title, and on an acceptance throws
**confetti** from a full-screen canvas.

**For the app:** one toast host with two layouts — a plain line and a person-shaped one. Keep
the 5s/8s split (an actionable toast should outlast an informational one), keep the chime and
the confetti, and drop the document-title pulse and the Notification API, which are what a
real push replaces.

---

## 5. Modals — six shells

| Shell | Size | Radius | Backdrop | z |
|---|---|---|---|---|
| `.comm-modal` (communities, connections) | `94vw` / max 1120 × `88vh` on desktop; **full screen, radius 0, at ≤640px** (pass 12) | 16 | `rgba(0,0,0,.45)` | 901 over 900 |
| `.yard-modal` (new group, new DM) | `90%` / max 420 × `80vh`, 24px padding | 16 | `rgba(0,0,0,.4)` | 50 |
| `.yard-forward-modal` | **340 × max 480** | 14 | `.yard-ctx-backdrop` | 1001 over 1000 |
| `.yard-ctx-backdrop` | full | — | **`rgba(0,0,0,.15)`** | 1000 |
| `.yard-lightbox` | full, `cursor: zoom-out` | — | `rgba(0,0,0,.85)` | 60 |
| Listing modal (GoMarket) | `inset-0`; `inset-4`/`inset-8` from 1024 | 16 (desktop only) | `black/70` + blur | 60 over 58 |

Six backdrops at four different opacities — `.15`, `.4`, `.45`, `.7`, `.85` — and z-indexes
spread over 50 … 1004. The stack is meaningful in places (selection at 1003 must beat the
reaction row at 1002 which must beat the backdrop at 1000) and accidental in others.

**For the app:** one dialog and one bottom sheet, with the scrim at a single opacity. Record
the per-shell opacity only where it carries meaning: `.yard-ctx-backdrop`'s **15%** is
deliberately almost transparent, because the message being acted on must stay readable
underneath.

---

## 6. Empty states — two systems plus the utility ones

**The plain one**, `.yard-empty`: centred column, `40px 24px`, a 64px emoji at
`grayscale(0.3)`, title **1.3rem / 20.8px** weight 700 `slate-800`, description **18px**
`slate-500` capped at 320px.

**The rich one**, `.yard-empty-*`, used by the room list: a **96px circular illustration** on a
green radial halo (`rgba(34,197,94,.18)` at 30%/30% fading to 4%) holding a 56px icon — or, in
the `--bubbles` variant, three floating emoji at **26px**, each on a 3.6s `translateY(-6px)
rotate(-4deg)` loop with delays 0 / 1.2 / 2.4s. Then title **16px** weight 600, hint **13px**
line-height 1.45 capped at 280px, and a stacked action column (max 280px, 8px gap) of pills:
`--primary` is `cm-green` with a **green** shadow `rgba(34,197,94,.30)` lifting 1px on hover;
`--ghost` is `rgba(15,23,42,.04)` on `slate-700`. An optional location hint sits below in
`rgba(252,209,22,.18)` on `#92400e` at **12px**.

GoMarket builds its two from utilities instead ([pass 3 §4](03-gomarket.md)) — white
`rounded-2xl` card, `py-20`, 60px emoji, 25px title.

So: three empty-state idioms. **For the app:** the rich one is the good one; use it everywhere
and keep the four room-list variants from [pass 2 §3](02-goconnect.md) distinct, because they
say different things.

---

## 7. The globally-mounted components

From [pass 1 §7](01-layout-navigation.md), these are components rather than screens and belong
above the Navigator:

| | Opened by | Notes |
|---|---|---|
| `partials/user-preview.blade.php` (517 lines) | `open-user-preview` with `{id}` or `{username}` | the WhatsApp-style profile card; every avatar and every name in the app opens it, including inside the chat dock and system messages |
| `components/connection-notifier.blade.php` (430) | realtime, `tenant.{t}.user.{u}` | §4 above |
| `partials/discover-modal.blade.php` (435) | `open-discover` | **7 of the product's 38 keyframe sets live here** |
| `livewire/marketplace/chat-dock` | `open-gomarket-chat` | [pass 3 §9](03-gomarket.md) |
| `livewire/a-i/kamer-chat` | `open-kamer-ai` | bubble hidden in `yardMode` |
| `partials/app-drawer.blade.php` | `open-app-menu` | [pass 1 §4](01-layout-navigation.md) |
| `livewire/location-tracker` | silent | toasts on a country change |
| `partials/impersonation-banner.blade.php` | admin state | |
| `partials/pwa-install.blade.php` | — | no app equivalent |

One pattern worth copying: **a name is always a tap target.** Sender names, system lines,
member rows, the dock's header, the toast's avatar — all of them open the same preview card.
Cheap to miss, and its absence makes an app feel inert.

---

## 8. Divergences found in this pass

1. **`app.css` was not the whole stylesheet, and my own extractor had the same blind spot.**
   509 lines across eight views, 12 of the 38 keyframe sets, and 20 colours in no token.
   **Fixed** — the extractor reads them now and `tokens.json` records all three. Stated as a
   divergence because the README claimed the generated spec was complete, and until this pass
   it was not.

2. **About 45 places pair the navy `cm-green` with a green literal.** 23
   `var(--color-cm-green, <some green>)` fallbacks, 17 `rgba(34,197,94,…)` halos and shadows,
   5 `rgba(0,107,63,…)` grounds. So the empty-state CTA is navy with a green shadow, the empty
   illustration is navy on a green halo, the FAB is navy with a green glow
   ([pass 2 finding 3](02-goconnect.md)). **This is what the web renders, so parity means
   reproducing it** — but it is a systematic artifact of `cm-green` having been changed from a
   green to a navy without the shadows following, and it is worth one deliberate decision
   rather than 45 accidental ones. The `#1e7a3e` and `#16a34a` fallbacks are dead code either
   way: the variable is always defined, so the fallback never fires.

3. **Tailwind v4's palette is OKLCH, so the remembered hexes are wrong.** I wrote the 18
   avatar gradients out by hand first and every saturated pair was off — `rose-400` is
   `#ff637e`, not the `#fb7185` of v3. The values only exist resolved in the compiled
   bundle. **Fixed**: the extractor derives them and emits `CnAvatarPalette`. Worth
   recording because it applies to every Tailwind colour in the app that is not one of
   the 28 `@theme` tokens — all the `emerald-*`, `amber-*` and `rose-*` utilities in the
   views included.

4. **`#2c4a3e` rings the notification badge and matches nothing.** A dark green border on a
   badge sitting on a navy header. In no token, in no palette, used once.

5. **Three toast systems, two timeouts, three enter animations, two widths.** They are
   genuinely different jobs — a passing notice, an actionable social event, a group join
   request — but not three different *looks*. The app should have one host.

6. **Six modal shells with four scrim opacities.** Only one of the four is load-bearing
   (`.yard-ctx-backdrop`'s 15%, so the selected message stays readable).

7. **`welcome.blade.php` is dead.** The stock Laravel page, no route, 247 CSS blocks and 4
   keyframe sets. Excluded from the extractor; worth deleting.

8. **The badge inventory disagrees with itself** (§3). Seven treatments, and at least two are
   plainly unintended: the jump badge's unscaled 11px and the bell badge's green ring.

---

## 9. Dart for this pass

```dart
/// Shared components. See docs/ui-parity/04-shared-components.md.
class CnShared {
  CnShared._();

  // ── Avatar ──
  // The 18 gradients are GENERATED, in CnAvatarPalette — see
  // generated/cn_tokens.dart. Typing them by hand gives Tailwind v3's
  // hexes, which are visibly wrong on the saturated hues.
  //
  //   final pair = CnAvatarPalette.gradients[crc32(seed) % 18];
  //
  // Seed with 'user:{id}' for people and 'room:{id}' for rooms.
  static const avatarGradientCount = 18;
  static const avatarGradientDirection = Alignment.bottomRight;

  // ── Badges ──
  static const badgeCountGround = Color(0xFF243A5C);          // cm-green (navy)
  static const badgeListGradient = [Color(0xFF007A5E), Color(0xFF00A37A)];
  static const badgeAlertGradient = [Color(0xFFEF4444), Color(0xFFDC2626)];
  static const badgeMinSize = 20.0;
  static const badgePulse = Duration(milliseconds: 1600);
  static const bellShake = Duration(milliseconds: 500);

  // ── Toasts ──
  static const toastWidth = 288.0;
  static const toastRadius = 12.0;
  static const toastDismiss = Duration(seconds: 5);
  static const toastMaxStacked = 3;
  /// An actionable social toast outlasts an informational one.
  static const socialToastDismiss = Duration(seconds: 8);
  static const socialToastMaxWidth = 380.0;
  static const socialToastRadius = 14.0;
  static const socialToastAccent = 4.0;                       // left border
  static const socialRequest = Color(0xFF3B82F6);
  static const socialAccepted = Color(0xFF16A34A);
  static const socialAvatar = 44.0;
  static const socialEnter = Duration(milliseconds: 350);
  static const socialEnterCurve = Cubic(0.18, 0.89, 0.32, 1.28);

  // ── Scrims. One opacity, except the message-action backdrop, which is
  // almost transparent on purpose so the selected message stays readable.
  static const scrim = Color(0x73000000);                     // 45%
  static const messageActionScrim = Color(0x26000000);        // 15%
  static const lightboxScrim = Color(0xD9000000);             // 85%

  // ── Empty state (the rich one — use it everywhere) ──
  static const emptyIllustration = 96.0;
  static const emptyIcon = 56.0;
  static const emptyTitle = 16.0;
  static const emptyHint = 13.0;
  static const emptyHintMaxWidth = 280.0;
  static const emptyBubbleFloat = Duration(milliseconds: 3600);
}
```

---

Next: the **screen map** — every route to its view to its app screen, which turns these four
passes into the parity checklist item C1 of the plan asks for. Then icons and animation, which
now have a real inventory behind them: **38 keyframe sets**, listed in `tokens.json`.
