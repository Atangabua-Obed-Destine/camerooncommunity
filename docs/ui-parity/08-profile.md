# Pass 8 — Profile

Three routes are called "profile". **There is one profile page.**

| Route | What it is |
|---|---|
| `marketplace.seller` `/marketplace/seller/{username}` | **The profile page**, for everyone, including yourself. `User::profileUrl()` sends every profile link in the product here: the header menu, the app drawer, GoConnect, People and GoMarket. The URL says "seller" for history's sake; the page is app-wide. |
| `user.profile` `/u/{username}` | A **redirect** to the above, kept so old links resolve. Not a screen; the screen map now says so. |
| `profile` `/profile` | The **account page**. `profileUrl()` falls back to it only for accounts **without a username**, so a member with one never reaches it from a link (§7, finding 5). |

Also present and **unreachable**: `ProfileController::showPublic()` and
`resources/views/profile/public.blade.php` (317 lines), the old `/u/` page. No route points
at them. Don't port them.

Sources: `livewire/marketplace/seller-profile.blade.php` (580 lines),
`app/Livewire/Marketplace/SellerProfile.php`, `profile/index.blade.php`,
`app/Http/Controllers/ProfileController.php`, `routes/web.php` 200–213, `User::profileUrl()` and
`avatarUrl()`. Phone values throughout (×1.25; `text-3xl` and up as compiled).

Both screens need a signed-in member, so this pass is read from the code. Nothing here was
rendered.

**For the app:** one **Profile** screen, built from the profile page, with an **Edit profile**
sheet (the profile page's own Settings dialog). The account page's only unique contents are
Log out (also in the drawer) and the admin link (out of scope). So it doesn't need its own
screen; Log out goes in the sheet. That's decision 1.

---

## 1. The profile page: layout

`x-layouts.rails` with `active => 'profile'`, **not** the marketplace layout, so there is no
GoMarket sidebar and no `main` padding. That makes the side gutter **12px** (`px-3`), half the
GoMarket screens' 24.

Order on a phone, top to bottom: back · header card · stats grid · Intro · Badges · Activity
· **Listings**. The two-column split (`lg:grid-cols-3`) only exists from 1024px. On a phone the
listings come **after** three info cards, so a buyer arriving from a GoMarket listing scrolls
past points and rooms to reach the seller's other items. That is the approved order and the app
mirrors it; it is listed as decision 3 in case it wasn't intended.

| Part | Value |
|---|---|
| Page | `bg-slate-100`, `max-w-5xl`, `px-3 py-4` |
| Back | chevron (16px) + "Back" at **17.5px** weight 500 `slate-600`, 12 below. `history.back()`, else GoConnect. |
| Header card | white, radius 16, `ring-slate-200`, `shadow-sm`, 20 below. **No** `overflow-hidden`, on purpose: it would clip the ⋯ menu. |

## 2. The header card

### Cover

**96px** tall (128 from 640px). With no photo, a gradient `cm-green → cm-green-light`. With
one, `background-size: cover`, centred. Top corners rounded 16 inline (the view explains:
`rounded-t-2xl` isn't in the compiled CSS). When a photo exists, tapping it opens the photo
viewer (pass 4's `open-user-photo`).

### Avatar row

Avatar **96px** (112 from 640px) hangs **56px** over the cover's bottom edge, with a 4px white
border and `shadow-lg`. With no photo it shows **one** initial at **37.5px** bold `cm-green` on
`cm-green/10`. Tapping it opens the viewer; with no photo the viewer shows the initial on the
member's palette colour (pass 4).

Beside it, aligned to the bottom (16px gap):

- **Name**: `text-2xl` extrabold, **30px**, `slate-900`, one line.
- **Meta row**: **16px** `slate-500`, wrapping, separated by `slate-300` dots:
  Joined *Nov 2026* (translated) · **N active listings** (semibold `slate-700`) ·
  ★ 4.8 (12) if rated, with the star `cm-yellow` and the count `slate-400` · *N followers* if any.

### Below the row, full width

- **Location**: **16px**, a 14px `cm-red` pin. **Privacy rule:** the city is shown only to the
  profile's owner; everyone else sees the country.
- **Trust badges** from `TrustBadges::forSeller()`: chips at **14px** semibold, `px-2 py-0.5`,
  ringed, toned per badge.
- **Actions**, 16 above (§4).
- **Bio**, if any: **19px** (`text-[15px]`), `leading-relaxed`, `slate-700`, line breaks kept.

## 3. Below the header

**Stats**: a 2-column grid on a phone (4 from 640px), 12px gap. Each is white, radius 12,
bordered, `p-4`, centred: value `text-2xl` bold **30px** `cm-green` (navy), label **15px**
`slate-500`. **Points · Rooms · Contributions · Listings.**

Three cards follow. Each is white, radius 12, bordered, `p-5`, with a 20px bold title:

| Card | Contents (17.5px rows, emoji bullets) |
|---|---|
| **Intro** / À propos | 🏠 From *home region* (with *home city* for the owner only) · 📍 Lives in *city (owner only), country* · 🗣 language · 📅 Joined *November 2026* · ⭐ Founding member (navy semibold) |
| **Badges** (only if any) | chips on `cm-yellow/10` with a `cm-yellow/20` border: icon + label at 15px weight 500, from `badge_type` |
| **Activity** | 💬 Member of **N** rooms · 🤝 **N** solidarity contributions · 🏆 **N** community points earned |

**Listings**:

- Heading `text-lg` extrabold, **22.5px**: "Your listings" plus a navy **Manage** link (to My
  listings), or "*Name*'s listings".
- Under it, a search pill (176px wide, 16px icon, 400ms debounce) and a sort select (Newest,
  Price ↑, Price ↓, Popular), both pill-shaped and ringed `slate-300`, at 17.5px.
- Grid: **the feed card and grid exactly** (pass 3 §4), 2 columns, 18 per page. Search and sort
  live in the URL (`?q=`, `?sort=`).
- The **owner** also sees their non-public listings, each with a top-left ribbon
  (`slate-900/80`, white, 13px uppercase) giving the raw status ("paused", "pending review").
  Visitors see only what the feed shows.
- Empty: 🛍️ at 48px. "You have no listings yet" plus a **Sell something** pill for the owner;
  "No active listings" for everyone else.

## 4. Actions: one state, one row

The view keys every action off a single `connectionState`, so the row can't contradict itself.
Another member's change arrives over Echo as `connection-state-changed` and refreshes the row.

Pills: `text-sm` bold **17.5px**, `px-4 py-2`, about **41px** tall, `rounded-full`. Primary is
navy with a shadow.

| State | Row |
|---|---|
| **self** | ⚙ **Settings** (navy). Opens the Settings dialog (§5). |
| **none** (strangers) | **+ Follow** · **Connect** (navy, people icon) · **Message**, deliberately *shown* in a disabled look (`slate-100`, `slate-400`). Tapping it explains: "Send a connection request first…" · ⋯ |
| **outgoing** | Follow · an amber **"Request sent"** chip (15px) · **Cancel request** (white, ringed; rose on hover; "Cancelling…" while busy) · ⋯ |
| **incoming** | Follow · **✓ Accept request** (navy) · **Decline** (`slate-100`) · ⋯ |
| **connected** | Follow · **Message** (navy, opens the DM in GoConnect) · an emerald **"✓ Connected"** chip · ⋯ |
| blocked-by-me / blocked-by-them | Unblock, or an "Unavailable" chip. **Never shown** (§7, finding 4). |

**Follow** is independent of connecting: navy "+ Follow", or `slate-100` "✓ Following". It
appears in every state but self.

**⋯** is a 36px `slate-100` disc. Its menu (224px, radius 12, `shadow-xl`) holds 📋 **Copy
link**, and when connected also 🏷 **Save as a contact** (to the DM's info panel) and, after a
divider, 🚫 **Disconnect** in red with a confirm.

The view's own comments carry two lessons the app should keep:

- The status chip and the action are separate elements, because "a single button that changed
  label on hover told a phone user nothing".
- Message stays visible for strangers, because people look for it.

## 5. The Settings dialog (owner only)

Fixed, centred, `max-w-lg`, radius 16, `shadow-2xl`, at most **85vh** tall with its own
scroll. Behind it, `black/50` with `backdrop-blur-sm`. Back, Escape or a tap outside closes it
(`x-overlay`). The header ("Settings", 20px bold, and a 36px close disc) sticks to the top.

1. **Photos** box (bordered, radius 12): a 64px avatar with **Change** (navy) and **Remove**
   (red) as 16px text links, then a divider, then "Cover photo" with the same two links. Each
   link is its own multipart form that submits on file choice. Limits: avatar jpg/png/webp
   ≤ 5 MB, cover ≤ 8 MB.
2. **Display name**: required, 100 characters max.
3. **Language**: two big radio tiles, "🇬🇧 English" / "🇫🇷 Français", radius 12, a 2px border
   that turns navy on `cm-green/5` when chosen.
4. **Region of origin**: a select of Cameroon's ten regions.
5. **City of origin**: free text, placeholder "e.g. Bamenda, Douala...".
6. **Show away chats in GoConnect**: a 40×24 switch, navy when on, with a 15px explanation.
7. **Save Changes**: full-width navy, radius 12, `py-3`, **17.5px** bold.

It's a plain POST that redirects back. The dialog reopens itself after the round trip
(seeded open on a success flash or validation errors) and shows a blue success banner.

## 6. The account page (`/profile`)

The same form again, laid out as a page inside the GoConnect shell (`yard-container`, icon rail
and ads rail on desktop):

- **Cover 160px** (208 from 640px), gradient `cm-green → cm-green/80 → cm-yellow`, with
  **Add / Edit cover** and **Remove** as dark translucent pills on it. A ⭐ Founding Member pill
  sits top-right.
- **Avatar 96px**, overlapping 48px. It is a button that opens a small menu (📷 Upload/Change
  photo, 🗑️ Remove photo), with **two** initials when there's no photo.
- Name 25px bold, **email** at 17.5px, then chips: 📍 country · 🗺️ region · 🏠 home region ·
  📅 Joined.
- A 3-up stats row: Points, Rooms, Contributions.
- Badges, then the Settings form (identical to §5), then an **Account** card: ⚙️ Admin Panel
  (admins only) and 🚪 **Log out** in red.

---

## 7. Divergences found in this pass

All from the code.

> **Finding 1 is fixed on the web (2026-11-06).** All 49 places that built a photo URL by hand
> (avatars, room photos and cover photos, in views, `CallManager` and `YardController`) now go
> through `App\Support\StorageUrl::for()`, which passes a full URL through and turns a stored
> path into one. Google sign-up already saved its photo locally; the older accounts that still
> hold a Google URL show again. Running `php artisan users:localise-avatars` on production would
> still copy those photos over: 2 of the 3 local accounts with a photo still had one.
> Finding 10 is partly fixed: the toasts and messages now have French (`lang/fr.json`).
>
> **Second round (2026-11-11/12):** **2** account deletion exists (anonymise, the owner's
> choice; `App\Services\AccountDeletion`, in Settings and `DELETE /api/v1/me`), **4** the
> blocker sees the profile with Unblock and only the blocked member gets 404, **6** initials
> are multibyte everywhere (24 views), **7** Rooms counts community rooms only, **8** the
> Contributions stat is hidden until `SOLIDARITY_LIVE`, **10** status ribbons, "Link copied"
> and "Joined" months are translated, and French members' language now shows correctly
> (four views compared the language enum with a string).

1. **Members who signed up with Google have broken photos almost everywhere.** `avatar`
   holds either a path on our disk or, for Google accounts, a **full `https://` URL**
   (`GoogleAuthController` stores the Google picture; the comment on `User::avatarUrl()` says
   so). Only `<x-user-avatar>` and `User::avatarUrl()` tell the two apart. **40 places in 20
   views** write `asset('storage/' . $x->avatar)` directly, producing
   `…/storage/https://lh3.googleusercontent.com/…`, a 404. That covers both profile screens and
   the photo viewer they open, the GoMarket inbox, chat dock, listing page, composer and My
   listings, and in GoConnect the chat room, room list, room info, connections, starred
   messages and share sheet, plus People cards and the seller popover. The image fails with
   `alt=""`, so the circle shows **neither the photo nor the initial**. Cover photos use the same
   pattern; Google doesn't supply one, so they're unaffected today.
   *App:* not affected, as long as it uses `avatar_url` from `UserResource` (it does), and never a
   raw path. *Web:* one fix, `$x->avatarUrl()` at each site.

2. **There is no way to delete an account, which App Store review will reject.** Nothing in
   routes, controllers or views deletes an account. Apple's guideline **5.1.1(v)** requires an
   app that lets people create an account to let them delete it **inside the app**. This blocks
   the iOS release, not just parity. It needs an endpoint (deleting or anonymising the member's
   messages, listings, orders and connections, which is a policy decision) and a visible entry
   in the app's Edit profile sheet. The web should get the same, for the same reason.
   **Decision 2.**

3. **Several basic account settings can't be changed anywhere.** There's no change of
   **password** (only the emailed reset, which doesn't arrive while production runs
   `MAIL_MAILER=log`), **email** or **username**, and **bio** can't be edited after onboarding,
   though the profile shows it. The app would mirror these gaps exactly. Recorded so the absence
   is a decision, not an oversight.

4. **The profile's Unblock button never appears.** `mount()` returns 404 when a block exists
   in **either** direction, so the `blocked-by-me` and `blocked-by-them` states (Unblock, and
   "Unavailable") never render on load. They could only appear if a block happened while the page
   was open. Unblocking does work, from **GoConnect → Connections**. *App:* the 404 is right
   for the blocked side; the blocker should get the profile with Unblock, or the app should drop
   that state and point to Connections. Small, but it's a choice.

5. **The account page is orphaned, and Log out lives on it.** Every profile link goes to the
   profile page, so `/profile` is reached only by members without a username (or by typing
   it). Its unique contents are **Log out** (also in the app drawer) and the **Admin Panel**
   link. The Settings form exists in **two copies** (`profile/index.blade.php` and the dialog in
   `seller-profile.blade.php`, about 70 identical lines). One edit to either and they drift.

6. **The same member gets three different initials.** The profile page shows one letter
   (`mb_substr`, uppercased properly). The account page shows **two**, via byte-wise
   `substr` + `strtoupper`, so "élodie" gives a **lowercase "é"**, and a name starting with a
   three-byte character gives **half a character**. `<x-user-avatar>` has its own rule
   (pass 4). *App:* one function, matching `<x-user-avatar>`.

7. **"Rooms" counts private chats.** The Rooms stat and "Member of N rooms" count every
   `YardRoomMember` row, and **one-to-one DMs are rooms**. So the public number grows with each
   private conversation, and anyone can watch someone's DM count change. *Decision 4:* count
   community rooms only.

8. **Solidarity appears on every profile and is always 0.** "Contributions" (stat) and "N
   solidarity contributions" (Activity) count `SolidarityContribution`. Solidarity isn't live
   (only three admin routes exist), so every member shows 0. It's approved copy, and it's the
   same question pass 6 raised for the auth screens: keep it, or hide it until the module ships.

9. **Two different cover heights.** The same cover photo is 96px on the profile page and
   160px on the account page, so a photo chosen to look right on one is cropped differently on
   the other.

10. **English leaking into French.** The owner's status ribbons print raw enum values
    ("paused", "pending review"); **Copy link** toasts "Link copied"; the account page's
    "Joined" uses `format('M Y')` (English months) where the profile page uses
    `translatedFormat`; and validation and success messages ("Profile updated successfully.")
    have no `lang/fr.json` (pass 7, finding 12).

Recorded, not counted: the account page's cover button wraps a `<form>` and a file input
inside a `<button>`, which is invalid HTML that works today; `home_region` isn't validated
against the region list it's chosen from.

---

## 8. Endpoints these screens need

| Need | Endpoint |
|---|---|
| Profile | `GET /api/v1/users/{username}`: `UserResource`, plus stats, trust badges, badges, follower count, `connection_state`, and an existing `dm_room_id` |
| Listings on it | `GET /api/v1/users/{username}/listings?q=&sort=&page=` (owner: every status but removed) |
| Follow | `POST` / `DELETE /api/v1/users/{id}/follow` |
| Connection actions | `POST /api/v1/connections/{user}` with `request`, `cancel`, `accept`, `decline`, `disconnect` and `unblock`, all through `ConnectionService`, which already exists |
| Message | `POST /api/v1/dm/{user}`: `DirectMessageService::findOrCreate`, connected members only |
| Edit profile | `PATCH /api/v1/me` (name, language, home region and city, show away chats; and bio, if finding 3 is taken up) |
| Photos | `POST` / `DELETE /api/v1/me/avatar` and `/me/cover` (multipart, same limits) |
| Delete account | `DELETE /api/v1/me` (finding 2), with re-authentication |

The app keys **everything** off `connection_state`, the same single value the web uses, so it
can't show two contradictory things either.

---

## 9. What the web leaves to us

| Native | Why |
|---|---|
| Photo picking | The web has a file input. Offer **camera or library**, then a crop: square for the avatar, and the cover's real aspect, 393:96 on a phone. |
| Copy link | Use the share sheet with the profile URL, which also copies. |
| Confirm Disconnect | `CupertinoActionSheet`, the destructive action in red. |
| Settings | A full-height sheet, not a centred dialog: an 85vh dialog with its own scroll is a web workaround. |

---

## 10. Decisions for the owner

1. **One Profile screen in the app**, from the profile page, with Edit profile as a sheet and
   Log out in it, and no separate account page. Recommended.
2. **Account deletion**: required for the App Store (finding 2). Choose what deletion does to a
   member's messages, listings and orders: delete or anonymise. Add it on the web too.
3. **Listings position on a phone**: mirror the approved order (listings last), or move them
   up for profiles reached from GoMarket.
4. **"Rooms" stat**: count community rooms only, so private chats aren't counted publicly (finding 7).
5. **Fix the avatar URLs on the web** (finding 1): mechanical, 40 sites, no design change.
   Recommended, and it can go in the same batch as passes 6 and 7's fixes.

No live code was changed in this pass. Decision 5 (the avatar URLs) was carried out afterwards; see
the note at the top of §7.

---

## 11. Dart for this pass

```dart
/// The profile page. Phone values; see docs/ui-parity/08-profile.md.
abstract final class CnProfile {
  static const pageGutter = 12.0;        // rails layout: no main padding
  static const cardRadius = 16.0;

  // ── Header ──
  static const coverHeight = 96.0;       // 128 at ≥640
  static const avatar = 96.0;            // 112 at ≥640
  static const avatarOverlap = 56.0;     // -mt-14
  static const avatarBorder = 4.0;
  static const avatarInitial = 37.5;     // text-3xl, one letter
  static const name = 30.0;              // text-2xl extrabold
  static const meta = 16.0;              // text-[13px]
  static const trustChip = 14.0;
  static const bio = 19.0;               // text-[15px], leading-relaxed

  // ── Actions ──
  static const actionFont = 17.5;
  static const actionHeight = 41.0;      // py-2 + one 25px line
  static const overflowButton = 36.0;
  static const overflowMenuWidth = 224.0;

  // ── Below ──
  static const statColumns = 2;          // 4 at ≥640
  static const statValue = 30.0;
  static const statLabel = 15.0;
  static const infoCardRadius = 12.0;
  static const infoCardTitle = 20.0;
  static const infoRow = 17.5;
  static const listingsTitle = 22.5;
  static const listingsPerPage = 18;
  static const searchDebounce = Duration(milliseconds: 400);
  static const searchWidth = 176.0;

  // ── Edit sheet (the web's Settings dialog) ──
  static const avatarMaxBytes = 5 * 1024 * 1024;
  static const coverMaxBytes = 8 * 1024 * 1024;
  static const nameMax = 100;
  static const switchSize = Size(40, 24);
}

/// The single value every action on the profile is derived from.
enum ConnectionState { self, none, outgoing, incoming, connected, blockedByMe, blockedByThem }
```

---

Next: **People** (one screen, the `/friends`-style directory), then the four static pages.
