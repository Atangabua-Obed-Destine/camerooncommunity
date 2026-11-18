# Pass 1 — Layout shells and navigation

Every value here was read out of the Blade views and `resources/css/app.css`, at phone
width. Font sizes are the **phone** sizes (the 1.25× scale from
[README.md](README.md) is already applied) — a size written `text-sm` in a view appears
below as 17.5px, because that is what a phone renders.

Sources: `resources/views/components/layouts/{app,guest,rails,marketplace}.blade.php`,
`resources/views/partials/{fb-nav-tabs,app-drawer,site-nav}.blade.php`,
`resources/views/yard/partials/{icon-sidebar,ads-sidebar}.blade.php`, and
`resources/css/app.css` lines 165–300 and 485–790.

---

## 1. There are four shells, and only two of them matter on a phone

| Shell | File | Used by | On a phone |
|---|---|---|---|
| `app` | `components/layouts/app.blade.php` | yard, profile, people, onboarding, coming-soon | The signed-in shell. **This is the app.** |
| `rails` | `components/layouts/rails.blade.php` | feed (`/` when signed in), marketplace | Wraps `app`, adds rail + ads columns — **both of which disappear below 768px**, so on a phone it is `app` with a plain scrolling column |
| `marketplace` | `components/layouts/marketplace.blade.php` | GoMarket pages | Wraps `rails`, adds a 320px filter sidebar that is `hidden lg:flex` — **not present on a phone** |
| `guest` | `components/layouts/guest.blade.php` | landing, login, register, legal, about | Auth screens. No header, no nav; each page draws its own chrome. |
| `admin` | `components/layouts/admin.blade.php` | 34 `/admin` routes | **Out of scope** |

So for the app: build **one shell** (the `app` header + nav), plus the auth screens, and
treat `rails`/`marketplace` as the same shell with a differently-padded body. The rail and
both sidebars are desktop furniture.

`route('home')` is served by `HomeController::index`, which picks the view by auth state:
a guest gets `home.blade.php` (the marketing landing page, `guest` shell), a signed-in
member gets `feed.blade.php` (`rails` shell). **The app never sees the landing page** — it
opens at splash → configuration → auth. One consequence worth keeping: the landing page is
the single page that does *not* get the 1.25× type scale, so none of its typography is a
reference for anything.

---

## 2. The signed-in header

The one piece of chrome on every screen, and the easiest thing to get subtly wrong.

### Geometry, by width

| | < 640px (iPhone portrait) | 640–1023px | ≥ 1024px |
|---|---|---|---|
| Location strip | **not rendered** | 28px | 28px |
| Main row | 56px | 64px | 64px |
| Icon tab row | 40px | 40px | **gone** (tabs move inline) |
| **Header total** | **96px** | 132px | 92px |
| Body top offset | 104px (`main pt-[104px]`) | 140px | 92px |
| Hamburger | yes | yes to 767px, then no | no |
| Icon rail | no | from 768px | yes |

For v1, only the first column is built: **96px of header, 104px of body offset.**

The 8px of slack between the header (96px) and the body offset (104px) is deliberate
breathing room on the feed and profile pages. `.yard-container` does *not* take it — it
pins to `top: 96px` exactly, so GoConnect and GoMarket sit flush against the header while
the feed sits 8px below it. Reproduce both: flush inside the chat shell, 8px elsewhere.

### The background is not the bar gradient

```
header:      linear-gradient(to right,   #1b2d4a 0%, #243a5c 30%, #2e4a6e 60%, #3a5a80 100%)
panel bars:  linear-gradient(135deg,     #015083 0%, #01395e 60%, #012a47 100%)
```

Two unrelated branded gradients: the site header is **navy, horizontal**; every panel bar
(`.yard-header`, `.mp-header`, the GoMarket mobile bar) is **blue, diagonal**. Anyone
building from the palette alone would use one for both and the app would read wrong on
every screen. The header's stops are also written as literals — `#1b2d4a` and `#243a5c`
are `cm-green-dark` and `cm-green`, but `#2e4a6e` is two units off `cm-green-light`
(`#2e4a70`) and `#3a5a80` is not a token at all.

### Element by element, at phone width

Left to right, all inside 12px side padding (`px-3`):

| Element | Size | Detail |
|---|---|---|
| Hamburger | 40×40 | `rounded-full`, icon 24px stroke 2.2, three lines. Idle `white/90`, hover `white/10` bg, pressed `white/20` bg. Offset `-4px` left, `4px` right. Dispatches `open-app-menu`. |
| Logo | height 56px | `max-width: 52vw`, contain. `drop-shadow(0 2px 6px rgba(0,0,0,.35))`. Hover scale 1.04 / press 0.98 over 200ms ease-out. Falls back to a 🇨🇲 emoji at 30px when no logo is set. |
| *(spacer)* | | `mr-auto` on the logo pushes everything else right |
| Kamer AI | 36×36 | `rounded-full`, 1px `white/20` border, 🤖 at 20px. Hover: border `white/40`, bg `white/10`. |
| Language | auto × 30 | `rounded-full`, 1px `white/20` border, 12px/6px padding, label `FR`/`EN` at **15px** weight 600, `white/80`. |
| Discover | — | `hidden md:inline-block` — **not on a phone**. It lives in the drawer instead. |
| Bell | 36×36 | 20px icon, `white/80`, 8px padding. Badge `.nb-badge` when count > 0, capped at `99+`. |
| Avatar | 32×32 | `ring-2 ring-white/25`. The chevron beside it is `hidden sm:block` — **not on a phone**. |

4px gap between the right-hand actions; 4px extra before the avatar.

**Account menu** (tap the avatar): 192px wide, `rounded-xl`, white, 1px `slate-200`
border, `shadow-lg`, 8px below, right-aligned, `z-50`. Rows are 16px/8px padding at
**17.5px** `slate-700`, hover `slate-50`. Profile → Admin Panel (admins only) → 1px
`slate-100` rule → Logout in `cm-red` with a `red-50` hover.

> The `z-50` is load-bearing. The nav tab links below are positioned and come later in the
> DOM, so without it they paint over this menu and swallow taps meant for Profile and
> Logout. In Flutter the equivalent is making this an overlay entry rather than a sibling.

---

## 3. The six-tab icon row

Below the header on anything under 1024px: 40px tall, 4px side padding, six tabs
`justify-around`, each `flex: 1` with 2px inner padding and a 24px icon.

| # | Tab | Destination | Active when | Icon |
|---|---|---|---|---|
| 1 | Home | `route('home')` | `routeIs('home')` | filled rounded house |
| 2 | GoConnect | `route('yard')` | `routeIs('yard*')` and not marketplace | filled bubble with a lightning notch |
| 3 | GoMarket | `route('marketplace.index')` | `routeIs('marketplace.*')` and **not** `marketplace.seller` | outlined shopping bag, stroke 2.5 → **2.75 when active** |
| 4 | GoParcel | — inert — | never | outlined cube, stroke 2.4 |
| 5 | GoRide | — inert — | never | outlined truck, stroke 2.4 |
| 6 | GoPartner | — inert — | never | outlined briefcase, stroke 2.4 |

States:

- **active** — `cm-yellow` (`#FCD116`), `scale(1.10)`, `drop-shadow(0 2px 4px rgba(0,0,0,.55))`
- **inactive** — white, `drop-shadow(0 1px 2px rgba(0,0,0,.45))`
- **coming soon** — `white/40`, hover `white/70`

**The yellow underline is desktop-only.** `fb-nav-tabs.blade.php` renders the 4px
`cm-yellow` active bar only when `!$isMobile`, so on a phone the *only* selected-state
signal is the colour change plus the 10% scale. Do not add an indicator the web does not
draw.

Tapping a coming-soon tab navigates nowhere. It shows a floating label for **1600ms**:
anchored under the icon, `cm-yellow` background, `slate-900` text at **14px** weight 700,
6px radius, 8px/4px padding, `shadow-lg`, 1px `black/10` ring, 150ms opacity fade. Text is
`"GoParcel (Coming Soon)"` / `"GoParcel (Bientôt)"`. The last tab anchors to its right edge
so the label never clips the viewport — the others centre on the icon.

Two naming notes, both deliberate-looking and both worth confirming before the app repeats
them: tab 1's tooltip and `aria-label` say **"The Yard"** while it goes to Home and the
drawer calls the same destination **"Home"**; and tab 2 is called **GoConnect** while
`/yard` is "The Yard" everywhere in the code.

---

## 4. The drawer

`partials/app-drawer.blade.php`. `md:hidden`, so it exists **only** below 768px — which
means on a phone it is the only route to Discover, Kamer AI, Contact, the module teasers
and the account actions. It is the mobile stand-in for the whole icon rail.

- Opened by `open-app-menu` (the hamburger); closed by the backdrop, the ✕, `Escape`, or
  **the system back gesture** — it registers with `x-overlay` /
  `resources/js/overlay-history.js`, so back peels the drawer off instead of leaving the
  page. In Flutter: a route on the Navigator, not a bare widget.
- Teleported to `<body>`, because the header is a stacking context and a drawer rendered
  inside it would paint underneath.
- Root `z-[70]`. Backdrop `slate-900/50`, 200ms opacity.
- Panel: **82vw, capped at 320px**, full height, white, `shadow-2xl`, slides from the left
  — in 200ms ease-out, out in 150ms ease-in.

**Header row** — 16px/12px padding, 1px `slate-100` underline:

- The **KAMER** wordmark at 22.5px weight 800, tight tracking, in three colours:
  `KA` `#009639`, `M` `cm-red`, `ER` `cm-yellow`. Note `#009639` is a literal green and is
  **not** `cm-green` (`#243a5c`, a navy). The rail draws the same wordmark *with* two
  twinkling stars over the M; the drawer's has none.
- Close button 36×36, `slate-400`, hover `slate-100` bg + `slate-600`, 20px icon.

**Destinations** — scrollable, 8px/12px padding. Every row: 12px gap, 12px/10px padding,
12px radius, **19px** weight 600 `slate-700`, 22px icon, hover `slate-100`. Active row:
`cm-green/10` background, `cm-green-dark` text.

1. Home
2. GoConnect
3. Discover — closes the drawer, then fires `open-discover` **180ms later**
4. Kamer AI — closes the drawer, then fires `open-kamer-ai` **180ms later**
5. GoMarket
6. — `COMING SOON` label: 16px top margin, 12px side padding, **14px** weight 700
   uppercase, wide tracking, `slate-400`
7. Solidarity, GoParcel, GoRide, GoPartner — inert `slate-400` rows, each with a right-aligned
   `Soon` pill: `amber-100` background, `amber-700` text at **13px** weight 700 uppercase,
   8px/2px padding, fully rounded

The 180ms delay on items 3 and 4 is the drawer's own exit duration plus a frame — the
overlay opens as the drawer finishes leaving rather than fighting it. Keep it.

**Account block** — 1px `slate-100` top rule, same row metrics: Contact us · Language
(a 22px outlined circle showing the *other* language at 13px weight 800) · the member's
own row (22px avatar + username, truncated) · Admin Panel (admins only) · Logout in
`cm-red` with a `red-50` hover.

---

## 5. The icon rail — desktop only, and the landscape question

`.yard-icon-sidebar` is `display: none` and becomes `display: flex` only at **768px and
up**. It is not part of the iPhone build. Recorded here because it is the drawer's twin —
same destinations, same order — and because of one decision it forces.

| | |
|---|---|
| Width | 56px collapsed, 190px expanded, 300ms `cubic-bezier(.4,0,.2,1)` |
| Expanded state | remembered per browser in `localStorage.yardSidebarExpanded`, **starts collapsed** |
| Items | 40px tall, 12px radius, 10px gap, 13px label, `slate-500` |
| Active item | `#0c4a6e` text, `rgba(186,220,245,.35)` background, 3px `#f5c518` left border |
| Labels | hidden until expanded; a hover tooltip stands in while collapsed |
| Edge handle | 12×48 at `right: -12px`, `cm-yellow`, radius `0 6 6 0`; widens to 16px on hover |
| Avatar | 34px circle, `cm-yellow` on `cm-green`, 2px `slate-200` border |

**The decision:** the web's breakpoint is 768px, and an iPhone 15 Pro Max in landscape is
932pt wide. On the web that width gets the rail *and* loses the 1.25× type scale. The
agreed scope is to mirror the mobile rendering, so **v1 renders the phone layout at every
width** — no rail, scaled type, in both orientations. Flagging it because it is a real
divergence from "what the web does at this width", not an oversight.

---

## 6. How the body is positioned

Two different arrangements, and the app needs both.

**Scrolling pages** (feed, profile, people, coming-soon): `main` with `padding-top: 104px`
and normal document scroll.

**Chat-shell pages** (GoConnect, GoMarket): `.yard-container` is `position: fixed` from
`top: 96px` to `bottom: 0`, `display: flex`, `overflow: hidden`, background `#eee`. The
page itself never scrolls; the panels inside it do. In Flutter this is the difference
between a scrolling `CustomScrollView` body and a fixed-height `Column` whose children own
their own scrolling — get it wrong and the keyboard pushes the whole chat off-screen.

Inside `.yard-container`, under 768px:

| Panel | Phone behaviour |
|---|---|
| Rail | not rendered |
| `.yard-panel--list` | `position: absolute; inset: 0` — fills the container |
| `.yard-panel--chat` | `position: absolute; inset: 0; z-index: 3`, parked at `translateX(100%)`, slides to `0` with `.yard-panel--active`; transition `transform .3s cubic-bezier(.4,0,.2,1)`. Background `#e8ded4` plus a 60×60 repeating SVG cross pattern at `#d4c8bc` 30% opacity. |
| `.yard-panel--info` | `position: fixed`, full screen from `top: 96px`, `z-index: 40` |
| `.yard-panel--ads` | `display: none !important` |

Sponsored ads do not vanish on a phone — they move. The 260px ads column is hidden below
1280px, and `partials/home-stories.blade.php` renders the same ads as a horizontal story
strip on the feed instead.

The panel sliding, the `--hidden-left` (`translateX(-30%)`) parallax and the back stack are
pass 2.

---

## 7. Nine things are mounted once, above every screen

The `app` layout mounts these outside `main`, so they persist across navigation:

| | What | Opened by |
|---|---|---|
| 1 | Impersonation banner | present whenever an admin is impersonating |
| 2 | `location-tracker` | silent; watches for a country change and toasts |
| 3 | `kamer-chat` | `open-kamer-ai`; its floating bubble is hidden when `yardMode` |
| 4 | Discover modal | `open-discover` |
| 5 | `marketplace.chat-dock` | `open-gomarket-chat` with `{sellerId, listingId}` |
| 6 | User preview | `open-user-preview` with `{id}` or `{username}` |
| 7 | App drawer | `open-app-menu` |
| 8 | Connection notifier | realtime; toast + chime + confetti |
| 9 | PWA install banner | — no app equivalent |

This is an architectural instruction, not decoration: in Flutter these belong **above the
Navigator** — a persistent overlay stack owned by the shell — not inside each screen.
Mounting the product chat dock per screen, for instance, would drop an in-progress
conversation on every navigation, which is exactly what mounting it once prevents here.

`yardMode` is the flag that suppresses the Kamer AI bubble, and it is `true` for the whole
`rails` shell — so the bubble is absent on the feed and GoMarket as well as GoConnect.
On those screens Kamer AI is reached from the drawer or the header button only.

---

## 8. The guest shell and the auth screens

```
body: bg-slate-900, min-height 100dvh, overflow-x hidden
      + cn-app on every guest page EXCEPT route('home')
```

So **login, register, forgot/reset password, verify-email and the legal pages all get the
1.25× type scale**; only the landing page escapes it.

The pattern to copy from these screens is how they handle height. Login is
`fixed inset-0 flex flex-col overflow-hidden` on a phone and only becomes `lg:static` on a
desktop — i.e. **the page does not scroll; the panel inside it does**. Same on the landing
page (`md:static`). For the app: full-bleed, non-scrolling scaffolds with scrolling
children, which is also what makes the keyboard inset behave.

The login screen's own mobile bar is 56px-ish (`py-3` around a 40px logo) with a
`to left` gradient `white → blue-400 → cm-green` and a `cameroon-ribbon` component — it is
not the app header, and it is the only place that gradient appears.

`partials/site-nav.blade.php` is the landing/legal header (sticky, transparent until
40px of scroll, then `white/95` with blur). Not needed by the app.

---

## 9. What the web has no answer for

These are the only places the app should behave natively rather than copy:

| Native | Why the web has nothing to copy |
|---|---|
| Scroll physics | Browser momentum ≠ iOS. Use `BouncingScrollPhysics` throughout. |
| Edge swipe-back | The web's back stack is `overlay-history.js` + browser back. The gesture must drive the same stack. |
| Keyboard insets | The chat shell is `position: fixed` and relies on the visual viewport. A Flutter `Scaffold` resizes instead; the composer must stay pinned above the keyboard. |
| Safe areas | No notch or home-indicator handling exists in the CSS. The 96px header sits **below** the status bar inset; the tab row needs no bottom inset because there is no bottom bar. |
| Haptics | Long-press selection in the chat has no web feedback. A light impact on selection start. |
| Share sheet | The web has `ShareableResolver` and copy-link. Use the system sheet. |
| Status bar | Light content over the navy header on every signed-in screen; dark content over the white auth screens. |

---

## 10. Divergences found in this pass

Ordered by what would actually hurt. Nothing here has been changed except where it says so.

1. **`modules.live` claimed Solidarity was live; it has no member entry point at all.**
   `app/Livewire/Solidarity/{CreateCampaign,ContributeModal}.php` exist and render their
   own views, but **nothing mounts either component** — no route, no `@livewire`, no
   `<livewire:` tag anywhere in `resources/` or `routes/`. The only Solidarity routes are
   three admin moderation routes. Every navigation surface (header tabs, drawer, rail)
   already marks it *Coming Soon*. `config/cameroon.php` listed it under `modules.live`,
   which my `/api/v1/configuration` endpoint would have advertised to the app as a live
   module. **Fixed** — removed from `modules.live` with a comment, covered by a test.
   This also narrows the v1 parity scope: there is no Solidarity UI to mirror.

2. **Three coming-soon modules had two different names each.** `config/cameroon.php` and
   the `/module/{slug}` teaser pages said **EasyGoParcel**, **RoadFam**, **WorkConnect**;
   the header tabs, drawer and rail said **GoParcel**, **GoRide**, **GoPartner** for the
   same three slugs. `/api/v1/configuration` was handing the app one set of names while
   the navigation showed another. **Settled 2026-11-02 — the navigation names are the
   approved ones.** `config/cameroon.php` now carries them, so the API, the teaser pages
   and the app agree; `ConfigurationTest` locks all three, because the two lists live in
   different files and drifted once already. The landing copy in `lang/{en,fr}/app.php`
   (`problem_3_solve`) was the last place still saying EasyGoParcel to a member, and
   follows the same decision.

3. **`/module/easygoparcel` showed the wrong icon.** `modules/coming-soon.blade.php` had
   `@case('easygoparcell')` — a doubled L — so the slug never matched and the page fell
   through to the default 🚀 instead of 📦. **Fixed**, along with a dead `marche` case
   left over from when the marketplace was a placeholder.

4. **`.yard-panel--info` is positioned for the wrong header between 640 and 767px.**
   `.yard-container` has an override for that band (`top: 132px`, because the location
   strip adds 28px) but the fullscreen info panel keeps `top: 96px`, so it slides 36px
   under the header. Invisible on an iPhone in portrait; real on a small tablet. Listed so
   the app does not reproduce a 96px constant that is only correct below 640px.

5. **A stale comment promises a bottom nav that does not exist.** `app.css` line ~500 says
   "Leave room for the mobile bottom nav (h-14 = 3.5rem)" and then sets `bottom: 0`. There
   is no bottom navigation bar anywhere in the codebase — navigation on a phone is the
   header tab row plus the drawer. The README's pass-1 list inherited that phantom and has
   been corrected. **The app must not add a bottom bar.**

6. **The active rail item shifts its own contents 3px.** `.yard-icon-sidebar__item--active`
   adds a `border-left: 3px` with no compensating padding, so the icon jumps right when a
   row becomes active. Desktop-only, cosmetic, noted for whenever the rail is built.

---

## 11. Dart for this pass

Shell geometry, hand-written from the table above rather than generated — these are layout
constants, not stylesheet tokens, so they do not come out of `extract.php`. Colours and
font sizes referenced here already exist in
[generated/cn_tokens.dart](generated/cn_tokens.dart).

```dart
/// The signed-in shell, at phone width. Mirrors
/// resources/views/components/layouts/app.blade.php — see docs/ui-parity/01-layout-navigation.md.
class CnShell {
  CnShell._();

  // Header. 56 + 40 = 96; the body clears it by 104 everywhere except the
  // chat shell, which sits flush at 96.
  static const headerRow = 56.0;
  static const headerTabRow = 40.0;
  static const headerHeight = headerRow + headerTabRow;
  static const bodyOffset = 104.0;
  static const headerPadX = 12.0;
  static const tabRowPadX = 4.0;

  /// Navy, horizontal. NOT the panel-bar gradient — see CnColors.barGradient.
  static const headerGradient = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: [Color(0xFF1B2D4A), Color(0xFF243A5C), Color(0xFF2E4A6E), Color(0xFF3A5A80)],
    stops: [0.0, 0.30, 0.60, 1.0],
  );

  // Header controls.
  static const hamburger = 40.0;
  static const logoHeight = 56.0;
  static const logoMaxWidthFraction = 0.52;
  static const actionButton = 36.0;
  static const avatar = 32.0;
  static const accountMenuWidth = 192.0;

  // Tabs.
  static const tabIcon = 24.0;
  static const tabActiveScale = 1.10;
  static const comingSoonLabelDuration = Duration(milliseconds: 1600);

  // Drawer. 82% of the width, never past 320.
  static const drawerWidthFraction = 0.82;
  static const drawerMaxWidth = 320.0;
  static const drawerEnter = Duration(milliseconds: 200);
  static const drawerLeave = Duration(milliseconds: 150);
  /// The drawer's own exit, plus a frame: an overlay opened from a drawer row
  /// waits this long so the two animations do not overlap.
  static const drawerHandoff = Duration(milliseconds: 180);
  static const drawerRow = 19.0;        // text-[15px] -> 19px on a phone
  static const drawerRowRadius = 12.0;
  static const drawerIcon = 22.0;

  // Chat shell.
  static const panelSlide = Duration(milliseconds: 300);
  static const panelCurve = Cubic(0.4, 0.0, 0.2, 1.0);
  static const panelParkedOffset = 1.0;    // translateX(100%)
  static const panelParallaxOffset = -0.3; // translateX(-30%)
  static const chatBackground = Color(0xFFE8DED4);
  static const containerBackground = Color(0xFFEEEEEE);

  /// The KAMER wordmark's green. A literal in the views, not a token, and not
  /// the navy that cm-green names.
  static const wordmarkGreen = Color(0xFF009639);
}
```

---

Next: **pass 2 — GoConnect.** The room list, the chat room, the sliding panel stack and the
interaction states (long-press selection, selection toolbar, jump-to-latest with its unread
counter, the overlay back stack).
