# Pass 3 — GoMarket

Fifteen routes, thirteen Livewire components, and — unlike GoConnect — **almost no CSS of its
own**. Font sizes below are the **phone** values.

Sources: `resources/views/livewire/marketplace/*.blade.php` (13 views, ~4,200 lines),
`resources/views/components/marketplace/{listing-card,seller-popover}.blade.php`,
`resources/views/livewire/marketplace/partials/{sidebar,sidebar-nav,sidebar-filters,map-view}.blade.php`,
and `app.css` 6732–6798 — which is the *entire* dedicated stylesheet for this module.

---

## 1. The structural fact that changes how to read this module

**GoConnect is hand-written CSS. GoMarket is Tailwind utilities.**

The whole module has 12 lines of bespoke CSS: `.mp-topbar` and `.mp-header`, and both exist
only because a brand bar is a gradient — a `bg-white` utility would set the colour and leave
the gradient image painted on top of it. Everything else is utility classes in the Blade.

Three consequences:

1. **`extract.php` has nothing to say about GoMarket.** The 191-entry component table is all
   `.yard-*` and `.wa-*`. There is no `.mp-card` font size to look up, because the card's
   sizes are `text-[15px]` in the markup.
2. **The 1.25× trap bites hardest here**, because every size arrives through a utility class.
   The mapping is the whole specification:

   | Written | Phone | Written | Phone |
   |---|---|---|---|
   | `text-[10px]` | **13px** | `text-xs` | **15px** |
   | `text-[11px]` | **14px** | `text-sm` | **17.5px** |
   | `text-[12px]` | **15px** | `text-base` | **20px** |
   | `text-[13px]` | **16px** | `text-lg` | **22.5px** |
   | `text-[15px]` | **19px** | `text-xl` | **25px** |

3. **The module reads as one surface with GoConnect by deliberate imitation, not by shared
   code.** `.mp-topbar` and `.mp-header` are commented as mirroring `.yard-header`, white
   phone treatment included. Two copies of the same intent, so they can drift — and in the
   app they should be one widget.

---

## 2. Screens

| Route | Component | Note |
|---|---|---|
| `marketplace.index` | FeedBrowse | the grid |
| `marketplace.category` `/c/{slug}` | FeedBrowse | same screen, category applied |
| `marketplace.show` `/listing/{slug}` | **FeedBrowse** | the feed *with the item modal open* — see below |
| `marketplace.sell` | ListingComposer | |
| `marketplace.edit` `/listing/{id}/edit` | ListingComposer | same screen |
| `marketplace.checkout` | Checkout | MoMo, manual |
| `marketplace.review` | LeaveReview | |
| `marketplace.seller` `/seller/{username}` | SellerProfile | **excluded from the GoMarket tab highlight** — it is the app-wide profile page, not a shop section |
| `marketplace.mine` | MyListings | |
| `marketplace.favorites` | Favorites | |
| `marketplace.offers` | MyOffers | |
| `marketplace.saved` | SavedSearches | |
| `marketplace.orders` | Orders | |
| `marketplace.insights` | ListingInsights | per listing |
| `marketplace.inbox` | Inbox | |

Plus one app-wide overlay: **ChatDock**, mounted in the `app` layout (pass 1, overlay 5).

**A listing is not its own page.** `marketplace.show` renders FeedBrowse with
`$modalSlug` set, so the URL for a listing produces the grid with a modal over it. On a phone
the modal is `inset-0` and covers everything, so it *looks* like a page. For the app: a listing
is a pushed route, and a cold start on a listing deep link should still leave a working path
back to the feed.

---

## 3. The mobile top bar

`.mp-topbar` is `lg:hidden sticky top-0 z-30`, `12px 10px` padding, `shadow-sm`:

- **Below 768px:** white, `background-image: none`, 1px `slate-200` underline, `slate-900`
  title, `slate-100` buttons on `slate-700`.
- **768–1023px:** the blue `--cm-bar-gradient` with a `cm-bar-deep` underline, white title,
  `white/15` buttons.

Title: the category icon and name when one is applied, otherwise "GoMarket", at
`text-lg font-extrabold` → **22.5px**, truncated.

Right side, 6px gap:

| | |
|---|---|
| **Filters** | `.mp-topbar__btn`, fully rounded, `12px 6px`, **15px** weight 600, with a 14px three-line icon. Opens the drawer. |
| **Sell** | `cm-yellow` on `cm-green-dark`, fully rounded, `12px 6px`, **15px** weight 700, `shadow-sm`, with a 14px plus icon at stroke 3. Goes to `marketplace.sell`. |

Below it, a sort row (`lg:hidden`, 12px bottom margin, 4px side padding): the listing count at
**17.5px** `slate-600` on the left, and on the right a label at **15px** with a fully-rounded
`select` ringed 1px `slate-300`, `10px 4px`, **15px** weight 500.

---

## 4. The grid and the card

Grid: **2 columns on a phone** (3 at 640, 4 at 768, 5 at 1280, 6 at 1536),
`gap-x-3 gap-y-4` — 12px across, 16px down.

`components/marketplace/listing-card.blade.php` is Facebook's card: a rounded image sitting
directly on the page background, text beneath it. **No card surface, no border, no lift, no
zoom** — the comment says so explicitly, and it is the kind of thing a developer "improves"
by adding a white card and a hover lift.

| Part | Value |
|---|---|
| Image | `aspect-square`, radius 8, `ring-1 ring-black/5`, gradient `slate-200 → slate-300` placeholder, `object-cover` |
| No-image state | the category emoji at 40px (70% opacity) over its name at **13px** uppercase, wide tracking, weight 600, `slate-400` |
| Hover veil | `bg-black/0 → bg-black/5` over 150ms — signals clickability without moving anything |
| Body | `padding-top: 6px`, 2px side |
| **Price** | **19px** weight 600 `slate-900`, `leading-tight`, truncated — *first*, as Facebook does |
| **Title** | **16px** `slate-600`, `leading-snug`, truncated, 2px above |
| **Location** | **15px** `slate-500`, truncated; a distance suffix `· 4 km` in `slate-400`, or `<1 km` under a kilometre |

Badges, top-left, stacked 4px apart, each `8px 2px` at radius 6 with a shadow, **13px** weight
700 uppercase:

- **Featured** — `cm-yellow` on `slate-900`, `★ Featured` / `★ Vedette`
- **Just listed** — `white/95` on `slate-900`, only when **under 2 days old** *and not
  featured*

Sold: a full-cover `black/50` with a 1px backdrop blur and a centred `cm-red` pill, white
**15px** weight 700 uppercase, `12px 6px`.

**Loading**: 12 skeleton cards, `animate-pulse`, an `aspect-square` `slate-200` block over
three bars at 14/12/10px height and 1/2, 3/4, 1/3 width.

**Infinite scroll**: `x-intersect="$wire.loadMore()"`, with a spinner and "Loading more…" at
**17.5px**.

**Two empty states**, and they differ: with filters active it is 🔍 "No results found" and a
*Clear all filters* button; with none it is 🛍️ "No listings yet" and a *Create your first
listing* button. Both are `py-20` centred on a white `rounded-2xl` `ring-1 ring-slate-200`
card, emoji at 60px, title at **25px** weight 700, body at **17.5px** `slate-600` capped at
`max-w-sm`.

---

## 5. The filters drawer

`lg:hidden`. Backdrop `black/50` at `z-40`; panel at `z-50`, **`w-[88%] max-w-sm`** (so
**384px**), full height, white, `shadow-2xl`, sliding from the left — **300ms in, 200ms out**.

- Head: "Filters & categories" weight 700 with a 36px ✕ at 25px.
- Body scrolls: `sidebar-nav` then `sidebar-filters`.
- Foot, above a 1px `slate-100` rule: a full-width `cm-green` **Show results** button,
  `py-3`, radius 12, weight 700.

Filter sections, from `sidebar-filters.blade.php` — section labels at **16px** weight 600
`slate-700`, the panel title at **19px** weight 700 with a *Reset* link at **15px** `cm-red`:

1. **Location** — region search, *Current Loc*, *Map picker* (a modal with Cancel / Confirm),
   a **Radius** slider at **15px**, and a *This region* toggle
2. **Condition**
3. **Delivery**
4. **Price (XAF)** — min and max
5. **Categories**
6. **Recently viewed**

> **This drawer does not answer the back button.** It is a plain Alpine boolean with no
> `x-overlay`, unlike the app drawer in pass 1 and everything in GoConnect. It is also a third
> drawer geometry: 384px and 300/200ms here, against 320px and 200/150ms for the app drawer.
> See finding 3.

---

## 6. The listing detail

Rendered two ways from one view, switched by `$asModal`:

| | Modal (from the grid) | Full page |
|---|---|---|
| Backdrop | `black/70` + blur at `z-[58]` | — |
| Frame | `z-[60]`, `inset-0` on a phone; `inset-4` / `inset-8` with a 16px radius and `shadow-2xl` from 1024px | `inset-0` on `black/90` |
| Close | dispatches `mp-close-listing` to the feed host | `history.back()`, or navigate to the feed if the referrer is off-site |

Teleported to `<body>` in modal mode, to escape `.yard-container`'s `position: fixed`
stacking context — the same trick the chat dock uses. It sits above the app top bar (`z-50`)
and below the dock (`z-70`).

On a phone the layout is a column: header, gallery, then the detail panel.

**Mobile header** (`lg:hidden`): white, 1px `slate-200` underline, `8px` padding, `z-20`. A
40px back arrow (24px glyph, stroke 2.5), the title at **19px** weight 700 truncated, then a
Save (bookmark, filled when saved, `cm-red`) and a Share button — **Share uses
`navigator.share` and falls back to copying the link with a toast.** The app uses the system
sheet here.

**Gallery**: `aspect-square`-ish stage with 40px circular `white/90` arrows at the vertical
centre (disabled at 30% opacity), a counter pill bottom-right (`black/70`, white **14px**
weight 700, `10px 4px`), and — when there is more than one image — a thumbnail strip
bottom-centre: `max-w-[88%]`, horizontally scrolling, `black/35` with a backdrop blur at
radius 16, `12px 8px`, 8px gap, 48px thumbs at radius 8 with a 2px ring (white and
`scale-105` when active, transparent at 70% opacity otherwise).

Status badge, `top-3 left-16` so it clears the back button: **Sold** in `cm-red` at **15px**,
or **Featured** in `cm-yellow` at **14px**, both uppercase with wide tracking, `12px 6px`,
fully rounded, `shadow-lg`.

**Detail panel**: full width on a phone (`lg:w-[400px]` / `xl:w-[440px]` beside the gallery
on a desktop), scrolls, white, `16px` padding with `20px` between blocks. In order: price and
title, the action group, offer status, description, attributes, location with a 128px map
thumbnail that opens a modal, the seller block, safety advice (**15px** `amber-900` on
`amber-50`, radius 12, `14px`, 1px `amber-200`), and a related-listings rail of 140px cards
(160px from 640px) that snap-centre.

**Actions**, in the panel:

- **Buy now** — only when the listing is fixed or negotiable, priced, not sold, not the
  viewer's, **and the seller has a MoMo number**. Full width, `cm-red`, weight 800, `py-3`,
  radius 8, **17.5px**, with the price in the label.
- **Message** — `cm-green`, weight 700, `py-2.5`, radius 8, **17.5px**; dispatches
  `open-gomarket-chat` with `{sellerId, listingId}`. Its label becomes *Message again* once
  the viewer has messaged.
- **Save** and **Share** — 44px wide squares beside it at radius 8; Save flips to
  `cm-red/10` on `cm-red` with a `cm-red/40` ring when saved.

**Mobile sticky action bar** (`lg:hidden sticky bottom-0`, `z-50`): white, 1px `slate-200`
top border, `shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]`, 12px padding, 8px gap — **Buy now**
(`cm-red`, weight 800) and **Message** (`cm-green`, weight 700), both `py-3` radius 12 at
**17.5px**. See finding 1: its `pb-safe` class does not exist.

---

## 7. The composer

`lg:h-full lg:flex` — so **below 1024px it is not a two-column layout at all**: the form is
`w-full` and the live preview stacks *beneath* it. On a phone you fill the form and scroll
past it to find a "Preview" heading and the card as it will appear. Mirror that order; do not
invent a tab or a sheet for it.

The form column is `lg:w-[416px]` / `xl:w-[440px]`, white, with a sticky header
(`bg-white/95` + backdrop blur, 1px `slate-100` underline).

**Step 0** is its own screen when `$listingType === null`: a `GoMarket` back link, "Create new
listing" at **37.5px** weight 800, and a 3-across choice grid from 640px.

Autosaves every 20s (`wire:poll.20s="autosaveDraft"`), so a draft survives. The app needs the
same — a half-written listing lost to a phone call is a worse failure here than on a desktop.

Image limit comes from `marketplace_max_images` (default 10 in the settings, 4 in
`config/cameroon.php`'s shipped defaults — the API's `/configuration` sends whichever is
live, which is why the app must read it rather than hard-code it). Category attributes are
dynamic, from `CategoryAttributeSchema::forCategory()`.

---

## 8. Checkout

A narrow column — `max-w-3xl`, `12px` side padding, `24px` top — on `slate-100`.

Summary card: white `rounded-2xl`, `ring-1 ring-slate-200`, with a `cm-green → cm-green/80`
gradient head carrying "Checkout" at **25px** weight 800 and a line of help at **15px**.
Below it a 64px cover thumb at radius 12, the title in weight 700, "Sold by {seller}" at
**15px** `slate-500`, and the price at **22.5px** weight 800 `cm-green`.

Then a step card: *Choose how you want to pay*, the available drivers as a 1-up (2-up from
640px) grid of `rounded-2xl` buttons with a 2px ring — `cm-green` with a `cm-green/5` ground
when selected — each showing the driver's emoji at 30px and label at **17.5px** weight 700.
A *Back to listing* link and a fully-rounded `cm-green` **Continue**.

When the seller has no MoMo set up, the whole step is replaced by an `amber-50` panel at
**17.5px** telling the buyer to message the seller instead. That is the only failure path and
the app must carry it — this is a **manual** MoMo flow, not a gateway.

---

## 9. The chat dock

The one realtime surface in GoMarket, and app-wide: mounted once in the `app` layout, opened
from anywhere with `$dispatch('open-gomarket-chat', { sellerId, listingId })`.

| | |
|---|---|
| Position | `fixed z-[70]`; **full width flush to the bottom on a phone**, a 348px card at `right-4 bottom-4` from 640px |
| Shape | `rounded-t-xl` on a phone (`rounded-xl` from 640px), `shadow-2xl`, `ring-1 ring-slate-200` |
| Height | `max-height: 70vh`, column, clipped |
| Header | `cm-green → cm-green-light` gradient, white, `12px 8px`, 8px gap: a 32px `white/20` avatar, the partner's name at **17.5px** weight 700 (a link that opens the user preview), "Marketplace" at **14px** `white/80`, then 28px Minimize and Close buttons |
| Body | the pinned product header, the thread, the composer — hidden while minimised |

**It registers with the overlay stack by hand.** Its open state lives on the server, not in an
Alpine boolean, so `x-overlay` cannot read it; instead an empty `x-data` element calls
`window.cnOverlay.open(el, () => $wire.close())` on init and `.close(el)` on destroy. Back
closes the dock; the ✕ steps over the entry it pushed. Before that, back left the page with
the conversation still open behind it.

Realtime is `window.Echo.channel(...)` — a **public** channel, like every other broadcast in
the app. That is item A5 in the mobile plan and it matters more here than in GoConnect,
because a product negotiation is a private conversation on a channel anyone can guess.

---

## 10. What the web leaves to us

| Native | Why |
|---|---|
| **Safe areas** | The web has none that work (finding 1). The detail's bottom action bar and the dock both sit flush against the bottom edge and need a real inset. |
| Share | `navigator.share` with a clipboard fallback. Use the system sheet; drop the fallback. |
| Image gallery | Arrows and a thumbnail strip. Keep both, and add pinch-zoom and a drag-to-dismiss in the lightbox. |
| Map | Leaflet in a modal, plus a 128px static thumbnail. A native map view, same framing. |
| Camera | The composer takes file input. The app should offer camera and library. |
| Draft survival | `wire:poll.20s` autosave. The app should save on background, not on a timer. |
| Infinite scroll | `x-intersect`. A standard scroll-end trigger, with the same 12-card skeleton. |

---

## 11. Divergences found in this pass

1. **`pb-safe` is not a real class, and `env(safe-area-inset-*)` is inert anyway.** The
   listing detail's sticky bottom action bar — the Buy / Message pair — carries `pb-safe`
   ([listing-detail.blade.php:873](../../resources/views/livewire/marketplace/listing-detail.blade.php#L873)).
   There is no such utility in Tailwind v4, nothing defines it in `app.css`, and it does not
   appear in the compiled `public/build/assets/app-*.css`. **It compiles to nothing**, so on
   an iPhone those buttons sit under the home indicator. Separately, **no layout sets
   `viewport-fit=cover`** — all four set only `width=device-width, initial-scale=1` — so even
   the one correct usage in the codebase (`pb-[calc(0.75rem+env(safe-area-inset-bottom))]` on
   the landing page, [home.blade.php:1021](../../resources/views/home.blade.php#L1021))
   resolves to zero. So the web has **no working safe-area handling anywhere**, including the
   installed PWA, where it is most visible. Not changed: it is a two-line fix
   (`viewport-fit=cover` in the layouts, and swap `pb-safe` for the calc form) but it moves
   live UI. Say the word. In the app this is `SafeArea`, and it is not optional.

2. **The listing modal keeps its own history, separate from the overlay stack.** FeedBrowse
   installs its own `popstate` listener and pushes `{mpItem: slug}` itself, with no
   `x-overlay` and — crucially — **no check of `window.__cnOverlayConsumedPop`**, the flag
   pass 2 showed exists precisely to stop two layers closing on one press. The effect today
   is a wasted round trip rather than a visible break: any back press while the marketplace is
   mounted runs the handler, so it calls `closeListing()` on an already-closed modal, or
   re-runs `openListing(slug)` and re-fetches a listing that is already open — for instance
   when the app drawer is dismissed over an open listing. **The app must have one back stack**
   covering the listing route, the filters sheet, the dock and the drawer.

3. **The filters drawer ignores the back button, and is a third drawer geometry.** No
   `x-overlay`, so back leaves the page with the drawer still open — the exact bug
   `overlay-history.js` was written to end, in the one drawer that never got it. And its
   dimensions disagree with the app drawer: **384px / 300ms in / 200ms out** against
   **320px / 200ms / 150ms**. The app should pick one sheet.

4. **The mobile sort list is shorter than the desktop one.** Desktop offers six orderings
   (Newest, Relevance when searching, Price ↑, Price ↓, Popular, Recommended); the phone row
   offers four — **Relevance and Recommended are desktop-only**. The ranking work behind
   *Recommended* is therefore unreachable on a phone. Mirroring mobile means four; if that is
   an oversight rather than a decision, it is worth fixing on the web first.

5. **Two near-identical bar implementations.** `.mp-topbar` and `.mp-header` each re-create
   `.yard-header` — same 56px, same gradient, same white phone override — by hand, with their
   own comments saying they mirror it. Three copies of one bar is three places to forget.
   In the app it is one widget; recorded because the next person to restyle the chat header
   on the web will miss two.

6. **The dock's realtime is a public channel.** `window.Echo.channel(...)`, consistent with
   every other broadcast in the app, and already item A5 in the plan — noted here because a
   buyer–seller negotiation is the most obviously private thing on a guessable channel.

---

## 12. Dart for this pass

```dart
/// GoMarket. Mostly Tailwind utilities rather than CSS, so these are read from
/// the Blade class strings — see docs/ui-parity/03-gomarket.md.
class CnMarket {
  CnMarket._();

  // ── Top bar ──
  static const topbarPadH = 12.0;
  static const topbarPadV = 10.0;
  static const topbarTitle = 22.5;
  static const topbarPill = 15.0;

  // ── Grid ──
  static const gridColumns = 2;          // 3 at 640, 4 at 768
  static const gridGapX = 12.0;
  static const gridGapY = 16.0;
  static const skeletonCards = 12;

  // ── Card. Facebook's: image on the page, text beneath. No surface,
  // no border, no lift, no zoom — the view says so explicitly.
  static const cardRadius = 8.0;
  static const cardPrice = 19.0;         // text-[15px]
  static const cardTitle = 16.0;         // text-[13px]
  static const cardLocation = 15.0;      // text-[12px]
  static const cardBadge = 13.0;         // text-[10px]
  static const cardBadgeRadius = 6.0;
  static const justListedWindow = Duration(days: 2);
  static const hoverVeil = Color(0x0D000000);   // black/5

  // ── Filters sheet. 384/300/200 on the web; the app drawer is 320/200/150.
  // Pick one — see divergence 3.
  static const filtersWidthFraction = 0.88;
  static const filtersMaxWidth = 384.0;

  // ── Detail ──
  static const detailHeaderTitle = 19.0;
  static const galleryArrow = 40.0;
  static const galleryThumb = 48.0;
  static const galleryThumbStripRadius = 16.0;
  static const detailPanelWidth = 400.0;        // desktop only
  static const buyButton = Color(0xFFCE1126);   // cm-red
  static const messageButton = Color(0xFF243A5C); // cm-green (navy)
  static const actionRadius = 8.0;
  static const stickyBarRadius = 12.0;

  // ── Chat dock ──
  static const dockWidth = 348.0;        // full width below 640
  static const dockMaxHeightFraction = 0.70;
  static const dockRadius = 12.0;
  static const dockAvatar = 32.0;
  static const dockName = 17.5;
  static const dockSub = 14.0;

  // ── Composer ──
  static const autosave = Duration(seconds: 20);
  /// The form and the preview stack on a phone; they are only side by side
  /// from 1024px. Keep the order: form, then preview.
  static const previewBelowForm = true;
}
```

---

Next: **pass 4 — shared components** (avatar and its palette fallback, message ticks, badges,
modals, toasts, empty states), then the **screen map** that turns all of this into the parity
checklist.
