# Pass 7 — GoMarket's own-account screens

The eight screens a member uses to manage their side of GoMarket: **Favorites, My listings,
Offers, Orders, Inbox, Insights, Leave a review, Saved searches**. Pass 3 covered the feed, the
listing, the composer, checkout and the dock; this pass finishes the module.

Sources: `resources/views/livewire/marketplace/{favorites,my-listings,my-offers,orders,inbox,listing-insights,leave-review,saved-searches}.blade.php`
(1,288 lines), their eight components in `app/Livewire/Marketplace/` (1,267 lines),
`partials/sidebar-nav.blade.php`, `components/layouts/marketplace.blade.php`, and
`app/Enums/{ListingStatus,OfferStatus,OrderStatus}.php`.

Like the rest of GoMarket this is Tailwind utilities, not CSS (pass 3 §1), so every size is
read from a class string and is given here at its **phone** value (×1.25; `text-4xl` and up
are not scaled).

**How this was checked.** All eight screens need a signed-in member, and `shoot.mjs` does
not log in, so these are read from the views. Where a layout could break, the markup was
rebuilt from the views' own class strings against the compiled stylesheet and measured at
iPhone SE and iPhone 15 width (§12, finding 1). Everything else here is from the code.

---

## 1. How a phone reaches these screens

**Only through the feed's Filters drawer.** On a phone:

- `components/layouts/marketplace.blade.php` puts the navigation in a sidebar that is
  `hidden lg:flex`, so it does not exist below 1024px.
- `.mp-topbar` (the GoMarket bar with **Filters** and **Sell**) is rendered by FeedBrowse
  only. None of these eight screens has it.
- The same `sidebar-nav` partial is included at the top of the **Filters & categories**
  drawer, above the filters. That is the phone's only link to all eight.

So the route to "My offers" on a phone is **GoMarket → Filters → My Offers**, and once there
the only way to another of these screens is back to the feed and through the drawer again.
Three of the eight also hide their own way back on a phone (§12, finding 9).

**For the app:** the web's answer is "inside the filters drawer", and it is a poor one. This is
the one place this pass recommends departing from the web. It would mean a **"Your GoMarket"**
entry (a menu or a sheet) on the GoMarket top bar, listing the same seven items in the same order
with the same badges. It is listed as decision 1 in §15 rather than assumed.

### The drawer list (`sidebar-nav`), in order

| Item | EN / FR | Badge |
|---|---|---|
| Browse all | Tout parcourir | — |
| Inbox | Boîte de réception | unread marketplace messages, cached per user (`mp:inbox-unread:{id}`), red pill, `99+` cap |
| **Saved** | Favoris | — |
| My Offers | Mes Offres | — |
| Orders | Commandes | orders **awaiting payment** where you are the seller, cached 120s |
| My Listings | Mes Annonces | — |
| Saved searches | Recherches enregistrées | new matches across all saved searches, `99+` cap |

Row: `px-3 py-2`, radius 12, **19px** weight 500 (`text-[15px]`); a 36px disc,
`slate-200` on `slate-700`, turning `cm-green` on white when the route is current, with a
16px icon. Active row: `cm-green/10` fill, `cm-green` text. Badge: `min-w-20 h-20`, `cm-red`,
**14px** bold.

---

## 2. The shared page idiom

All eight sit in `x-layouts.rails` → `marketplace` layout, so `body.cn-app` and the 1.25×
scale apply. Seven of them share one skeleton; Inbox is the exception (§7).

| Part | Value |
|---|---|
| Page | `bg-slate-100`, `min-h-[calc(100vh-96px)]` |
| Side gutter | **24px**: the layout's `main` is `p-3` (12) and every page adds its own `px-3` (12). Inbox adds `px-2`, so 20. |
| Top gutter | layout 12 + page `py-4` (16) or `py-6` (24) |
| Max width | Favorites and Saved searches: 1400. My listings and Inbox: `6xl` (1152). Insights and Orders: `5xl` (1024). Offers: `4xl` (896). Leave a review: `2xl` (672). It doesn't matter on a phone, but it explains why they look unrelated on desktop. |
| Card | `bg-white`, radius **16**, `ring-1 ring-slate-200`, `shadow-sm` |

### Three header styles

| Style | Used by | Spec |
|---|---|---|
| **Header card** | Favorites, My listings, Offers | A white card, `p-4`, 16 below. A 44px disc tinted with the screen's colour (`cm-red/10` heart, `cm-green/10` box, `cm-yellow/20` tag) with a 20px icon. Title `text-xl` extrabold, **25px**, `tracking-tight`, truncated. Subtitle `text-xs` **15px** `slate-500`. Right: one pill action (see the button table below). |
| **Bare** | Orders, Insights, Saved searches | No card. The title sits on `slate-100`: `text-2xl` extrabold, **30px** (Insights: `text-xl`, 25px, prefixed 📊). Subtitle `text-sm` **17.5px** `slate-600` (Insights `text-xs`, 15px). |
| **Gradient banner** | Leave a review | The card's top band, `cm-green → cm-green/80`, white text: title 25px extrabold, subtitle 17.5px at 90% opacity. |

Three idioms for one module. The app should pick the header card for all seven, because it is
the most common and the only one that carries an action. That is a visual change, so it's
decision 2 in §15, not a default.

### Buttons on these screens

Nearly every action is a pill: `rounded-full`, `text-xs` bold (**15px**, weight 700),
`px-3 py-1.5`. **Measured: 32px tall.** That is 12pt short of Apple's 44pt minimum touch
target, and these are the screens with destructive actions (Delete, Reject, Open dispute). The
app should keep the look and pad the hit area to 44 (`MaterialTapTargetSize.padded` or a
44×44 `SizedBox` around the visual).

| Role | Fill | Text |
|---|---|---|
| Primary (Browse, Create, Confirm sale, Send) | `cm-green` (navy), `shadow-md` on CTAs | white |
| Neutral (Edit, Stats, Cancel) | `slate-100`, hover `slate-200` | `slate-700` |
| Bump | `cm-yellow`, `ring-1 ring-cm-yellow` | `slate-900` |
| Pause | `amber-50`, `ring-amber-200` | `amber-700` |
| Mark sold | `blue-50`, `ring-blue-200` | `blue-700` |
| Reactivate | `emerald-50`, `ring-emerald-200` | `emerald-700` |
| Accept offer | `emerald-600`, `shadow-sm` | white |
| Counter | `blue-600`, `shadow-sm` | white |
| Destructive, quiet | `cm-red/10`, hover `cm-red` + white | `cm-red` |
| Text link (Withdraw, Open dispute, Rename, Delete search) | none | `slate-600` or `cm-red`, underline on hover |

Emoji lead the labels on My listings (✏️ 🚀 ⏸ 💰 ▶ 📊 🗑) and Offers (✓ ↩ ✕). They are part
of the approved design and stay. For an app they render as Apple Color Emoji, which differ
from the Windows set the web was designed on. Expect that, and don't treat it as a mismatch.

### Empty states

All the same shape: a white card, centred, `py-16` (64px), a disc or emoji, then a title and a body:

| Screen | Mark | Title (EN) | CTA |
|---|---|---|---|
| Favorites | 80px `cm-red/10` disc, 40px outline heart | No favorites yet | Browse GoMarket → |
| My listings | 80px `slate-100` disc, 📭 at 36px | No listings here | Create one → |
| Offers (per tab) | 80px `cm-yellow/20` disc, 🏷️ | No offers sent yet / No offers received yet | Go to marketplace → |
| Orders | 🧾 at **60px**, no disc | No orders yet | — |
| Saved searches | 64px `cm-green/10` disc, document icon | No saved searches yet | Browse Marketplace |
| Inbox list | 💬 at 36px, no card | No conversations yet | — |

Title `text-lg` bold **22.5px**; body `text-sm` **17.5px** `slate-500`, `max-w-sm`; CTA a
navy pill, `px-5 py-2.5`, **17.5px** bold, `shadow-md`.

---

## 3. Favorites (`marketplace.favorites`)

- Header card (red heart). The **GoMarket ←** back pill is `hidden sm:inline-flex`, so it doesn't appear on a phone.
- Grid: **the feed's card and grid exactly** (`x-marketplace.listing-card`; 2 columns,
  `gap-x-3 gap-y-4`). Pass 3 §4 is the spec; don't fork it.
- Each card gets a **remove button** laid over the image, top-right, 10px in: 32px white/95
  disc, `backdrop-blur`, `shadow-lg`, `ring-slate-200`, a 16px `cm-red` X; hover fills it
  red and scales 1.1. Tapping it asks a browser `confirm()`: "Remove from favorites?".
- 24 per page, newest favourite first, with Laravel's numbered pagination.
- A favourite whose listing was removed is skipped in the grid but still counted in the
  page, so a page can show fewer than 24. The app should filter server-side.

## 4. My listings (`marketplace.mine`)

### Header and filter row

Header card (box icon) with a **+ New** navy pill (`Nouveau`; "New listing" from 640px).

Filter pills, horizontally scrollable, hidden scrollbar: **All · Active · Paused · Sold ·
Expired · Drafts**. They are 40px tall (measured). Active: navy fill, white, `shadow-md`.
Inactive: white with a `ring-slate-200` border. Measured content width is **493px**, so on any phone the last one or
two pills start off-screen. That's intended; keep the scroll.

### The card: different from the feed card

This is a **managed** card with a surface, unlike pass 3's no-surface feed card:

| Part | Value |
|---|---|
| Card | white, radius 16, ring, `shadow-sm`, hover `shadow-md` |
| Image | `aspect-[4/3]` (not square), `object-cover`; no image → 📦 at 48px on `slate-100` |
| Select box | top-left 8px, in a white/90 blurred chip, 16px checkbox |
| Status chip | bottom-left 10px, `text-[10px]` **13px** bold uppercase, wide tracking, `px-2.5 py-1`, pill (§11 colours) |
| Featured chip | top-right, `cm-yellow` on `slate-900`, "⭐ Featured" |
| Title | **20px** bold (`font-bold`, no size class), one line |
| Price | `text-lg` extrabold **22.5px**, `cm-green` (navy) |
| Counters | **15px** `slate-500`, 14px icons: 👁 views · ❤ saves (red icon) · 💬 inquiries (links to Inbox → Selling, filtered to this listing) |
| Actions | Edit · Bump · Pause · Sold · Stats, with a 32px round delete pushed right. **Measured: two rows on every phone.** |

Which actions show:

- **active**: Edit, Bump (disabled for 24h after a bump, with a tooltip "Available in…"),
  Pause, Sold, Stats, Delete.
- **paused**: Edit, Reactivate, Stats, Delete.
- **anything else**: Edit, Stats, Delete.

The 24h bump cooldown is enforced server-side too: a second bump toasts "You can bump again
in :min min".

### Mark as sold (modal)

Centred, `max-w-lg`, radius 16, `black/60` backdrop with `backdrop-blur-sm`; tap outside to
cancel. Header "💰 Mark as sold" at 22.5px extrabold with a 36px ✕.

1. The listing in a `slate-50` strip (56px thumb, title, price).
2. **Buyer**: optional. Pre-filled with the most recent **accepted** offer's buyer, and the
   price and currency pre-filled from that offer. Otherwise a search box (300ms debounce)
   that lists **past offer-makers on this listing**, or, once you type, any member by name or
   @username (8 results).
3. **Final price** (2/3 width) and **Currency** (XAF, EUR, USD, GBP).
4. A note: "Attributing a buyer lets them rate this transaction."
5. Footer on `slate-50`: Cancel (white, ringed) and **Confirm sale** (navy).

On confirm: if `quantity > 1`, quantity drops by one and **nothing else changes**. Otherwise
the listing becomes sold with price, currency, buyer and date. Either way an attributed buyer
gets a notification inviting a review (§12, finding 4).

### Bulk selection bar

When any box is ticked: a bar fixed to the bottom, white, top border, `p-4`, with
**Select all / Deselect all**, an "N selected" chip, and **⏸ Pause · ▶ Reactivate · 🗑
Delete** at 17.5px bold. It does not fit on a phone (§12, finding 1).

## 5. Offers (`marketplace.offers`)

Header card (yellow tag). Then one white card holding:

**Tabs**: two equal halves, `py-3.5`, **17.5px** bold, each with a 16px icon and a count
chip; the active one is navy text on `cm-green/5` with a 2px navy underline. Received also
shows an amber **"N new"** chip for pending/countered offers. The tab is in the URL (`?tab=`).

**Rows**, divided by `slate-100` lines, `p-4`:

| Part | Value |
|---|---|
| Thumb | 64px, radius 12, ring; the listing's thumbnail, else its category emoji, else 📦 |
| Title | `text-sm` bold **17.5px**, one line; "(Listing removed)" when gone |
| Status | top-right chip, **13px** bold uppercase: Pending amber · Accepted emerald · Rejected rose · Countered blue · Withdrawn and Expired slate |
| Amount | `text-xl` extrabold **25px** navy, with the asking price struck through at **14px** `slate-400` |
| Meta | **15px** `slate-500`: "To"/"From" + the other party's name (tap → user preview) · time ago |
| Message | if any: italic **15px** in a `slate-50` box, two lines max, in quotes |

Actions, only while the offer is open (pending or countered):

- **Sent**: "Withdraw offer" as an underlined text link, with a confirm.
- **Received**: ✓ Accept (emerald, confirm) · ↩ Counter (blue) · ✕ Reject (slate). They measured
  at two rows on a phone. Counter swaps the row for an inline number field plus Send and ✕, and
  Enter submits.

10 per page. **Accepting** rejects every other open offer on the listing. The counter-offer
flow is broken (§12, finding 2), so mirror the screen, not the flow.

## 6. Orders (`marketplace.orders`)

Bare header, **"Orders"** at 30px. When you have sales needing action and you're on the
Buying tab, a red **"N need attention"** pill sits top-right and switches tabs.

| Part | Value |
|---|---|
| Tabs | a white segmented control, radius 16, `p-1`: **I bought (n)** / **I sold (n)**, **17.5px** bold, 53px tall (measured); active is a navy radius-12 fill |
| Status filter | wrapping pills, **15px** semibold, `px-3 py-1`: All · Awaiting · Paid · Completed · Disputed · Cancelled. Active `slate-900`. **English in both languages** (§12). |
| Card | white, radius 16, `p-4`; 64px radius-12 thumb |
| Title row | **20px** bold, truncated, with the status chip (§11) |
| Meta | **15px**, dot-separated: reference in monospace · amount bold navy · "seller:" or "buyer:" + name · time ago |
| Extras | "Tx: …" provider reference in mono at 14px; the buyer's note in italics |

Actions by state and side:

| State | Buyer sees | Seller sees |
|---|---|---|
| initiated, awaiting payment | Continue payment (navy) | **Mark as paid** (navy, awaiting only, confirm) |
| paid | Mark complete (emerald, confirm) | Mark complete |
| initiated, awaiting, paid | Open dispute (red text link, confirm) | Open dispute |
| released | ★ Leave a review (`cm-yellow` text) | — |

15 per page, newest first; tab and filter in the URL.

## 7. Inbox (`marketplace.inbox`)

The one screen here built like a chat app, and the one the app should build on GoConnect's
widgets rather than the card idiom.

**Frame**: one white radius-16 card, `height: calc(100vh - 150px)`, `min-height: 480px`. On a
phone it shows **either** the list **or** the thread (`threadOpen`); from 1024px both sit side by side,
340px | rest.

**List pane**:

- Header: a 32px back chevron to the feed, **"Inbox"** at 25px extrabold.
- A segmented control (`slate-100`, `p-0.5`, pill): **Buying / Selling**, **17.5px**
  semibold, the active one white with navy text and a shadow. Each carries a red unread
  count. The tab is in the URL.
- Rows (`px-3 py-3`, bottom border): a 48px radius-8 square with the listing's cover (else the
  partner's avatar, round; else 🛍️). Partner name **18px** bold (`text-[14px]`) with the
  time at 13px `slate-400` (short form: "3 hours"). The listing title at 14px navy semibold.
  Last message at 15px `slate-500`, semibold `slate-700` when unread, with an 8px red dot.
- Active row: `cm-green/5`.

**Thread pane**:

- Header: back chevron (phone only), 36px avatar (initial on `cm-green/15` fallback), name at
  17.5px bold (tap → user preview), and "Buyer" / "Seller" at 14px `slate-400`.
- **Pinned listing strip** under it: `slate-50`, 40px thumb, price at **16px** bold, title at 15px,
  "See details" in navy, linking to the listing.
- Messages on `slate-50`, `px-4 py-4`, 8px apart. **Bubbles**: `max-w-[70%]`, `px-3.5 py-2`,
  **18px**, `leading-snug`, `whitespace-pre-line`; mine navy with white text and a 6px bottom-right
  corner; theirs white, ringed, with a 6px bottom-left corner. **Share cards**: 60% wide, a
  112px image, then price at 16px bold navy and title at 15px.
- Composer: a pill input on `slate-100` (white with a navy ring when focused), 4,000 characters
  max, and a 40px round navy send button.

Realtime: it listens on the **open** room's channel only, and any `MessageSent` re-renders the
whole component. New messages in other conversations don't update the list until you
navigate.

## 8. Insights (`marketplace.insights/{listing}`)

Owner only (403 otherwise).

- A **← My listings** text link at 15px, then "📊 {title}" at 25px and a **View listing →**
  white ringed pill.
- **Range**: a white pill-shaped segmented control: **7d · 30d · 90d** (15px bold; active navy).
  In the URL as `?days=`.
- **KPI grid**, 2 columns on a phone, 12px gap. Each is a white radius-16 card, `p-4`, with a
  label at 14px bold uppercase `slate-500`, a value at **30px** extrabold, and a sub-line at 14px.
  - Views: `slate-900`, sub-line "n unique"
  - Favorites: `cm-red`, sub-line ❤
  - Offers: `cm-yellow`, sub-line "n accepted"
  - Engagement (FR: Conversion): navy, the `(favourites + offers) ÷ views` percentage, sub-line "n orders · n paid"
- **Daily views**: a pure-CSS bar chart, 128px tall, one bar per day, `cm-green/70`, rounded
  tops, minimum height 2%. Values appear **only on hover** (13px tooltip, "12 · Nov 3"). Phone
  problems in §12, finding 7.
- **Viewer regions** and **Traffic sources**: the top 6 each, as rows with a 15px label, a
  bold count, and a 6px bar (`cm-green` for regions, `cm-yellow` for sources). They stack on a phone.
  Empty: "No data yet."
- **Tips**: a `cm-yellow/10` card with "💡 Tips to sell faster" and 0–4 rule-based bullets
  (under 20 views → bump; ≥20 views and under 2% engagement → photos or price; open offers
  with none accepted → respond; no category attributes → add them). When no rule fires, the
  card shows its heading over an empty list.

## 9. Leave a review (`marketplace.review/{slug}`)

Only the **attributed buyer** of a **sold** listing gets in (403 otherwise). It doubles as
"edit my review".

- The gradient banner (§2), then the listing strip: a 56px thumb, title, and "Sold by {seller}".
- **Rating**: five ★ at 36px (`text-4xl`, unscaled), `cm-yellow` when lit and `slate-300` when not,
  6px apart, scale 1.1 on hover, with "n / 5" beside them. Required.
- **Comment**: optional, a 5-row textarea, radius 12, 1,000 characters max, with "Be specific and
  respectful." and a counter at 14px (§12, finding 8).
- Cancel (text) and **Post review** / **Update review** (navy pill, `px-6 py-2.5`).
- On save the seller is notified (new reviews only), a toast appears, and it returns to the listing.

## 10. Saved searches (`marketplace.saved`)

- Desktop has its own nav sidebar (where Favorites is called "⭐ Favorites"); on a phone that sidebar is hidden.
- Bare header, "Saved searches" at 30px, with "We'll notify you when new matching listings
  appear." Right side: **Mark all as seen** (navy text) when there are new matches.
- Each search is a white radius-16 card, `p-4`:
  - **Name** at 20px bold with a red **"+N new"** pill and a quiet **Rename** link (inline
    field + Save / Cancel; Enter saves, Esc cancels).
  - **Summary** (from `MarketplaceQueryBuilder::summarize`, localised) at 16px, two lines max.
  - "Saved 3 days ago" at 14px `slate-400`.
  - A **View matches →** navy pill on the right.
  - A divided footer row: **🔔 In-app** and **✉️ Email** checkboxes at 15px, and **Delete** (red text, confirm).
- Alerts come from `marketplace:run-saved-searches`, **hourly**, as an in-app notification
  and/or a digest email.

## 11. Status colour maps

Three enums, three chip styles. Use the same maps in the app.

**Listing** (My listings chip, `{bg}-100 / {fg}-800`):

| Status | EN / FR | Chip |
|---|---|---|
| active | Active / Actif | emerald |
| sold | Sold / Vendu | blue |
| paused | Paused / En pause | `slate-200` on `slate-700` |
| expired | Expired / Expiré | orange |
| draft | Draft / Brouillon | amber |
| pending_review | Pending Review / En revue | gray *(the view has no case for it)* |
| removed | Removed / Retiré | gray |

**Offer** (`{c}-50 / {c}-800 / ring {c}-200`): pending amber · accepted emerald · rejected rose ·
countered blue · withdrawn slate · expired slate.

**Order** (`OrderStatus::chip()`, `{c}-100 / {c}-800 / ring {c}-200`): initiated and
awaiting_payment amber · paid blue · released emerald · cancelled slate · disputed red. Labels
come from `label()` / `labelFr()`; "released" reads **Released / Libéré** on the chip but
**Completed** in the filter row.

---

## 12. Divergences found in this pass

Ordered by how much a member would notice. Each is marked **rendered** (a rebuild measured
at iPhone size) or **code** (read from the source).

> **Fixed on the web (2026-11-06)**, the batch from decisions 3 and 4, with tests in
> `tests/Feature/MarketplaceOfferFlowTest.php` and `MarketplaceOwnScreensTest.php`:
> - **1.** On a phone the bulk bar is the count over three equal toolbar buttons (icon above
>   label), clear of the home indicator. In French on an SE they span x=16 to 359, and the page
>   gets bottom padding so the bar doesn't cover the last row.
> - **2. The offer flow, decision 3.** One implementation, `App\Services\OfferService`, used by
>   the listing page and Offers. A counter is now `counter_amount` on the buyer's own offer
>   (status countered), not a second row. The buyer gets **Accept** and **Decline**, and the seller
>   sees "Waiting for the buyer". Every step notifies the other side, in *their* language, with a
>   link to the right Offers tab. A migration folds existing stray counter rows into the offer
>   they answered.
> - **3.** Bulk Pause touches only active listings, bulk Reactivate only paused ones, and so do
>   the single buttons. If nothing selected qualifies, it says so.
> - **4.** A partial sale no longer sends a review link that ends in a 403. Completing an order
>   for one of several now takes one off the quantity instead of marking the listing sold, and a
>   buyer with a completed order can review.
> - **5.** The Inbox marks a conversation read only when it is on screen: opened on a phone,
>   both panes on a desktop (the browser says which), or reached by a `?c=` link, which now
>   opens the thread on a phone too.
> - **6.** The thread shows the newest 200, oldest first.
> - **8.** The review counter counts as you type.
> - **10.** `WithPagination` on My listings, Favorites and Orders, and a new tab or filter
>   starts at page 1.
> - **12.** `lang/fr.json` covers every `__()` string in the app (192). A test fails when one is
>   added without French. Notifications are translated for the **recipient**, not the sender.
> - **13.** All leaves out deleted listings, and there is a **Pending review** pill (amber).
> - **16 (wording only).** The completion notice said "Both sides confirmed", which was never
>   checked. It now says "The order was marked complete."
> - **17.** The Inbox height uses `100dvh` where supported.
>
> **Second round (2026-11-12):** **7** (the Offers figure is `yellow-700`, readable outdoors;
> the fourth KPI is "Engagement" in both languages; "Favorites" is "Saves"; tooltip dates
> follow the language. The 1.2px bars remain a desktop chart), **9** (the way back shows on
> phones on Favorites and Offers, and Orders and Saved searches have one), **11** (a separate
> `last_seen_at`, so the badge waits for the member and "Mark as seen" no longer postpones
> alerts), **14** (Saved searches calls it "Saved listings"), **15** (one definition,
> `MarketplaceOrder::needingSellerAttention()`, for the badge and the page).
>
> Not changed: 16's rule itself (either side can still complete an order), and the smaller
> performance notes.

1. **On a phone, bulk Delete is off-screen.** *Rendered.* The bulk bar's content is **499px
   wide** on a 375px and a 393px screen. **🗑 Delete starts at x=412**, beyond both, and
   ▶ Reactivate is cut in half. The bar is `fixed`, so it can't be scrolled to. The labels
   also wrap, so the bar grows to 99px and the buttons to 66px. On top of that, the bar has no bottom padding on the page, so it covers
   the last row of cards, and it sits under the home indicator (pass 3, finding 1).
   On a phone, bulk delete is unreachable. *App:* a bottom action bar with three icon
   buttons, or the iOS edit-mode toolbar.

2. **Counter-offers don't reach the buyer.** *Code.* Two implementations, both broken, and
   they disagree with each other:
   - From **Offers → Received**, Counter marks the original "countered" and creates a
     **new pending offer with the seller as `seller_id`**. That new offer shows up in the
     **seller's own Received tab**, with Accept, Counter and Reject, so the seller can accept
     their own counter. The original, still "countered", is still open, so it keeps its three
     buttons too.
   - From the **listing page**, the new offer is created with status "countered" and a
     `counter_amount`; Offers creates "pending" and sets no `counter_amount`.
   - On the buyer's side, both screens give the buyer only **Withdraw**. **Nothing anywhere
     lets a buyer accept a counter.**

   And **no offer action sends a notification**: making, accepting, rejecting and
   countering are all silent, unlike orders, which notify at every step. *App:* mirror the
   screen, but this needs fixing on the web first (decision 3).

3. **Bulk Pause and Reactivate ignore status.** *Code.* `bulkPause` and `bulkReactivate`
   update every selected listing regardless of state. Under **All**, ticking a sold, expired or
   draft listing and tapping Reactivate **puts it back on the market**. A draft goes live
   without passing the composer's validation or review (`pending_review` is bypassed). The
   single-listing buttons don't have this problem: they only offer Reactivate on paused listings.

4. **Partial sales invite a review the buyer can't leave.** *Code.* Marking a listing with
   `quantity > 1` as sold only decrements quantity. It does not set `buyer_id` or `status`,
   but it **still notifies the attributed buyer** "Leave a review". Leave a review requires
   `status = sold` and `buyer_id = you`, so that link is a **403**. Separately, completing
   an *order* marks the whole listing sold whatever its quantity, so two sale paths disagree
   about quantity.

5. **Opening the Inbox on a phone marks a conversation read that you never saw.** *Code.*
   `mount()` marks the **first** conversation read and clears the unread badge. On a phone
   the screen opens on the **list** (`threadOpen` is false), so that conversation is marked
   read while its unread dot disappears in front of you. Every incoming `MessageSent` does
   it again (`refresh()` marks the resolved conversation read). A `?c=` deep link to a
   thread also opens on the list.

6. **The inbox thread stops at its 200th message.** *Code.* `orderBy('id')->limit(200)`
   returns the **oldest** 200. Once a negotiation passes 200 messages, new messages,
   including ones you send, never appear. *App:* cursor pagination from the newest (plan
   A3), which also replaces this.

7. **Insights' chart can't be read on a phone.** *Rendered.* At 90 days the bars are
   **1.2px** wide on an SE (1.4 on a 15), with 2px gaps wider than the bars. The values are
   only in a hover tooltip, which a touch screen never shows. Also: the dates in that tooltip
   are always English (`format('M j')`), the fourth KPI is "Engagement" in English but
   "Conversion" in French, and the Offers figure is `cm-yellow` (#FCD116) on white, about
   **1.5:1** contrast, unreadable outdoors. *App:* tap-to-inspect bars, weekly buckets at
   90 days, and a darker yellow for text (`yellow-700`, #A65F00, 4.9:1; decision 4).

8. **The review character counter never moves.** *Code.* It prints `mb_strlen($comment)`
   from the server, and the textarea is `wire:model` (deferred), so it reads "0 / 1000" until
   submit. *App:* count locally.

9. **Three screens hide their way back on a phone.** *Code.* Favorites and Offers put the
   "← GoMarket" pill behind `hidden sm:inline-flex`, and Saved searches puts its nav in an
   `lg`-only sidebar. Orders has none at all. With the GoMarket bar absent (§1), the
   only way out is the header's tab row or the browser. *App:* a back button on every
   pushed screen anyway; noted because it is part of decision 1.

10. **Pagination breaks after any action on three screens.** *Code.* My listings, Favorites
    and Orders call `paginate()` **without** Livewire's `WithPagination` trait. Livewire only
    takes over paginator URLs for components that use the trait, so after any action on the page
    (pause, remove a favourite, switch tab or filter) the re-rendered links are built from
    the AJAX request's URL, and the page number resets to 1. Offers uses the trait and
    is fine. Not reproduced here (it needs over 20 listings on one account). *App:* not
    affected, because it uses infinite scroll.

11. **Saved-search "new" badges clear themselves.** *Code.* "+N new" counts listings
    published since `last_notified_at`. The hourly job sets `last_notified_at` to now
    every time it alerts. So for any search with alerts on (the default), the badge is
    back to 0 within the hour **whether or not the member looked**, and **Mark all as seen**
    has almost nothing to do. The drawer badge inherits this. **Email alerts can't arrive
    at all** while production runs `MAIL_MAILER=log` (plan C3).

12. **French members get English server messages throughout.** *Code.* There is **no
    `lang/fr.json`**, so every `__('...')` string literal in these components (every toast,
    such as "Listing bumped to top", "Offer accepted.", "Marked as paid", and every
    notification title and body) is English in French. The Orders filter row (All,
    Awaiting, Paid, Completed, Disputed, Cancelled) and "seller:" / "buyer:" are English too.
    It's the same root cause as pass 6's auth messages. The app has its own `fr.json` and
    should not copy this, but the **notifications** are composed on the server and will
    arrive in English until the web is fixed.

13. **My listings → All shows deleted listings.** *Code.* The query is `withTrashed()`, so
    listings the member deleted come back under All with a grey "Removed" chip and live Edit
    and Stats buttons. Both lead to a **404**, because the edit and insights routes don't load
    trashed listings. There's no Removed filter pill, and no **Pending review** pill either:
    those listings appear only under All, with a grey chip because the view's colour `match`
    has no case for them (the enum says amber).

14. **One screen, three names.** *Code.* The favourites screen is **"Saved"** in the drawer,
    **"Saved listings"** on its own header, and **"Favorites"** on Saved searches' sidebar
    and in Insights. "Saved" also sits one row above **"Saved searches"**. The app should
    use one name; mirroring means "Saved" in the menu and "Saved listings" as the title.

15. **Two different counts for "needs attention".** *Code.* The drawer's Orders badge counts
    orders *awaiting payment*. The Orders page's red pill counts awaiting payment **plus
    paid**. A seller sees "1" in the drawer and "3 need attention" on the page.

16. **"Completed" says both sides confirmed. Only one did.** *Code.* Either party can mark
    a paid order complete, and the other party is told "Both sides confirmed, leave a review
    when you can." A seller can mark paid and then complete in two taps, with the buyer
    confirming nothing. That's a trust question more than a UI one, but the app will show the
    same wording.

17. **The Inbox uses `100vh`.** *Code.* `height: calc(100vh - 150px)`. In iOS Safari `100vh`
    is the viewport with the toolbars hidden, so with them showing the composer sits below the
    fold until you scroll the page. *App:* not affected; the composer sits above the keyboard inset.

Smaller ones, recorded and not counted: an image message in a marketplace room (possible, because it is
a Yard room) renders as its caption in a text bubble; the Inbox computes an unread count
per conversation in a loop (N+1); Saved searches runs a full match count per search on
every render, including each checkbox toggle; the Insights tips card can render with no
tips; "Seller" / "User" fallbacks are English.

---

## 13. Endpoints these screens need

None of this exists in `/api/v1` yet. Shapes follow the plan's A3 rules (API Resources,
absolute URLs, cursor pagination for chat).

| Screen | Endpoints |
|---|---|
| Favorites | `GET /marketplace/favorites`, `DELETE /marketplace/favorites/{listing}` |
| My listings | `GET /marketplace/my-listings?status=`, `POST …/{id}/pause`, `…/reactivate`, `…/bump` (429 with `retry_after` inside the 24h window), `…/mark-sold` (buyer, price, currency), `DELETE …/{id}`, `POST /marketplace/my-listings/bulk` (`action`, `ids[]`), `GET …/{id}/buyer-candidates?q=` |
| Offers | `GET /marketplace/offers?side=sent\|received`, `POST /marketplace/offers/{id}/withdraw\|accept\|reject\|counter` and, once divergence 2 is fixed, a buyer-side `accept-counter` |
| Orders | `GET /marketplace/orders?side=&status=`, `POST /marketplace/orders/{id}/mark-paid\|complete\|dispute` |
| Inbox | `GET /marketplace/conversations?role=`, `GET /marketplace/conversations/{room}/messages?before=` (cursor), `POST …/messages`, `POST …/read`, plus the room's private channel (plan A5) |
| Insights | `GET /marketplace/my-listings/{id}/insights?days=7\|30\|90`: one payload with the KPIs, series, regions, sources and tip keys |
| Review | `GET /marketplace/listings/{slug}/review` (eligibility + existing), `PUT` the same |
| Saved searches | `GET /marketplace/saved-searches`, `PATCH /{id}` (name, notify_push, notify_email), `DELETE /{id}`, `POST /{id}/seen`, `POST /seen` |
| Drawer badges | Inbox unread, orders awaiting, saved-search new: one `GET /marketplace/badges`, fetched when the GoMarket menu opens |

The actions belong in the services plan A2 already names (`ListingService`, `OfferService`,
`OrderService`), so Livewire and the API call the same code. That is also where divergences
2–4 are fixed once, for both.

---

## 14. What the web leaves to us

| Native | Why |
|---|---|
| Confirms | Seven `wire:confirm` browser dialogs. Use `CupertinoAlertDialog`, with the destructive action in red. |
| Swipe actions | Favorites (remove) and the Inbox list are lists of rows on iOS; swipe-to-remove is expected. It is an addition, so it stays behind the visible buttons, not instead of them. |
| Pull to refresh | None of these screens can refresh on the web without a reload. The app should offer it on every list. |
| Edit mode | My listings' checkboxes → the iOS select mode with a bottom toolbar (fixes divergence 1). |
| Chart inspection | Tap a bar to read it (divergence 7). |

---

## 15. Decisions for the owner

1. **A "Your GoMarket" menu on the GoMarket bar in the app** instead of reaching these
   screens through the Filters drawer (§1). Recommended. The web could get the same
   menu, and then the app would be mirroring it again.
2. **One header style** for the seven card-style screens (§2): the header card.
3. **Fix the offer flow on the web first** (divergence 2): one counter implementation, a
   buyer-side accept, and notifications on every offer action. It's behaviour, not design,
   but it changes what these screens show.
4. **A web bug-fix batch** with tests, which keeps app and web in step: the bulk bar on a
   phone (1), bulk actions respecting status (3), the partial-sale review link (4), Inbox
   read-marking and the 200 cap (5, 6), the review counter (8), `WithPagination` (10),
   `withTrashed` and the missing pills (13), and a `lang/fr.json` for these strings (12). And
   for the Insights offers figure: `yellow-700` text instead of `cm-yellow`, which is a colour change, so it's your call.

No live code was changed in this pass. Decisions 3 and 4 were carried out afterwards; see the
note at the top of §12.

---

## 16. Dart for this pass

```dart
/// GoMarket's own-account screens. Phone values; see 07-gomarket-screens.md.
abstract final class CnMarketScreens {
  // ── Page ──
  static const pageBg = CnTw.slate100;
  static const gutterH = 24.0;          // layout p-3 + page px-3
  static const inboxGutterH = 20.0;
  static const cardRadius = 16.0;

  // ── Header card ──
  static const headerPad = 16.0;
  static const headerDisc = 44.0;
  static const headerIcon = 20.0;
  static const headerTitle = 25.0;      // text-xl extrabold
  static const headerSubtitle = 15.0;   // text-xs
  static const bareTitle = 30.0;        // Orders, Saved searches

  // ── Pill actions. 32 tall on the web; pad the hit area to 44.
  static const pillHeight = 32.0;
  static const pillFont = 15.0;         // text-xs bold
  static const pillPadH = 12.0;
  static const minTapTarget = 44.0;
  static const filterPillHeight = 40.0; // My listings' status row

  // ── Empty state ──
  static const emptyPadV = 64.0;
  static const emptyDisc = 80.0;
  static const emptyTitle = 22.5;
  static const emptyBody = 17.5;

  // ── My listings card (has a surface, unlike the feed card) ──
  static const mineImageAspect = 4 / 3;
  static const mineTitle = 20.0;
  static const minePrice = 22.5;
  static const mineCounters = 15.0;
  static const statusChipFont = 13.0;
  static const bumpCooldown = Duration(hours: 24);

  // ── Offers ──
  static const offerThumb = 64.0;
  static const offerAmount = 25.0;
  static const offerTabHeight = 53.0;   // py-3.5 + a 25px line
  static const offersPerPage = 10;

  // ── Orders ──
  static const orderTabsHeight = 53.0;  // measured
  static const ordersPerPage = 15;

  // ── Inbox ──
  static const inboxListWidth = 340.0;  // ≥1024 only; phone shows one pane
  static const inboxRowThumb = 48.0;
  static const inboxRowName = 18.0;     // text-[14px]
  static const inboxBubbleFont = 18.0;
  static const inboxBubbleMaxWidth = 0.70;
  static const inboxCardWidth = 0.60;
  static const inboxBubbleTail = 6.0;   // rounded-br-md / rounded-bl-md
  static const inboxSend = 40.0;
  static const inboxMaxChars = 4000;

  // ── Insights ──
  static const kpiValue = 30.0;
  static const kpiLabel = 14.0;
  static const chartHeight = 128.0;
  static const insightRanges = [7, 30, 90];
  /// cm-yellow on white is ~1.5:1. Pending decision 4: yellow-700 for text.
  static const offersFigure = CnColors.cmYellow;

  // ── Review ──
  static const starSize = 36.0;         // text-4xl, unscaled
  static const reviewMaxChars = 1000;

  // ── Drawer list ──
  static const navRowFont = 19.0;       // text-[15px]
  static const navDisc = 36.0;
  static const navBadgeMin = 20.0;
}

/// Status → chip colours, from the three enums. Same maps as the web.
abstract final class CnStatusChips {
  static const listing = {
    'active': (bg: CnTw.emerald100, fg: CnTw.emerald800),
    'sold': (bg: CnTw.blue100, fg: CnTw.blue800),
    'paused': (bg: CnTw.slate200, fg: CnTw.slate700),
    'expired': (bg: CnTw.orange100, fg: CnTw.orange800),
    'draft': (bg: CnTw.amber100, fg: CnTw.amber800),
    'pending_review': (bg: CnTw.amber100, fg: CnTw.amber800), // enum says amber; web shows gray
    'removed': (bg: CnTw.gray100, fg: CnTw.gray700),
  };
  static const offer = {
    'pending': (bg: CnTw.amber50, fg: CnTw.amber800, ring: CnTw.amber200),
    'accepted': (bg: CnTw.emerald50, fg: CnTw.emerald800, ring: CnTw.emerald200),
    'rejected': (bg: CnTw.rose50, fg: CnTw.rose800, ring: CnTw.rose200),
    'countered': (bg: CnTw.blue50, fg: CnTw.blue800, ring: CnTw.blue200),
    'withdrawn': (bg: CnTw.slate100, fg: CnTw.slate700, ring: CnTw.slate200),
    'expired': (bg: CnTw.slate100, fg: CnTw.slate700, ring: CnTw.slate200),
  };
  static const order = {
    'initiated': (bg: CnTw.amber100, fg: CnTw.amber800, ring: CnTw.amber200),
    'awaiting_payment': (bg: CnTw.amber100, fg: CnTw.amber800, ring: CnTw.amber200),
    'paid': (bg: CnTw.blue100, fg: CnTw.blue800, ring: CnTw.blue200),
    'released': (bg: CnTw.emerald100, fg: CnTw.emerald800, ring: CnTw.emerald200),
    'cancelled': (bg: CnTw.slate200, fg: CnTw.slate700, ring: CnTw.slate300),
    'disputed': (bg: CnTw.red100, fg: CnTw.red800, ring: CnTw.red200),
  };
}
```

`pending_review` is given the enum's amber rather than the web's accidental grey, which is the
one place this file follows the model over the view. Flagged here so it's a choice, not a drift.

---

Next: **Profile** (3 screens: own profile, public profile, and the seller profile, which is
the app-wide profile page despite its URL), then People and the static pages.
