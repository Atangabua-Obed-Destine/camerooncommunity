# Pass 9 — People and the static pages

The last five screens in the route map: **People** (`/people`), and the four static pages,
**About**, **Contact**, **Privacy** and **Terms**. With this pass every in-scope screen is written
up; what remains is icons, animation, and the overlays a route list can't see.

Sources: `PeopleController.php`, `people/{index,_card,_empty}.blade.php`,
`pages/{about,contact}.blade.php`, `legal/{privacy,terms}.blade.php`,
`components/legal/shell.blade.php`; and for the policy checks in §7,
`AIService::moderateText()`, `Livewire/Yard/ChatRoom.php` 579–600,
`LocationHistoryService.php` and `routes/console.php`.

**How this was checked.** The static pages are public, so they were **rendered** at iPhone 15
size with `shoot.mjs` (`shots/static-*-iphone15.png`). People needs a signed-in member, so its
card was rebuilt from the view's own classes and measured at SE, 15 and Pro Max width
(`shots/people-rebuild-se.png`).

---

## 1. People: what it is

A Facebook `/friends`-style directory in the plain `app` layout (no rails, no GoConnect shell).
Five sections, chosen with `?tab=`, each a **full page load**: People is a controller and
plain links, not Livewire.

| Tab | EN / FR | Shows |
|---|---|---|
| `home` (default) | Home / Accueil | **Connection requests** (up to 10, with "See all"), then **People you may know** (18, with "See all") |
| `requests` | Connection requests / Demandes | Incoming requests, up to 60. Red badge: count. |
| `suggestions` | Suggestions | Up to 60 members you have no connection record with |
| `all` | All people / Tous | Every member except you and blocked pairs, newest first, 24 a page |
| `connections` | My connections / Mes contacts | Accepted connections, most recent first, up to 200. Red badge: count. |

"People you may know" is **the newest accounts you aren't connected to**
(`orderByDesc('id')`). There's no region, room or mutual-connection signal. Mirror the result,
but don't promise more in the app's copy.

## 2. People on a phone

| Part | Value |
|---|---|
| Page | `bg-slate-50`, `px-4 pt-3 pb-6` (16px gutter) |
| Desktop sidebar | `hidden lg:flex`, so on a phone the five tabs are a **horizontal pill strip** instead |
| Tab strip | scrolls sideways; measured content **673px**, so on any phone the last two start off-screen. Pills `px-4 py-2`, `text-sm` semibold **17.5px**, fully round. Active is navy with white text; inactive is white with a `slate-200` border. Badge 16px tall, `red-500` (Tailwind red, not `cm-red`), **13px** bold. |
| Search | Only on Suggestions, All and Connections: a full-width pill, `py-3`, 20px icon, **17.5px**. It's a GET form, so a search reloads the page. |
| Section heading | `text-xl` bold **25px** on Home, with "See all" in navy at 17.5px. Single-tab pages use `text-2xl` extrabold, **30px**. Measured: the French heading "Personnes que vous pourriez connaître" wraps to **two lines** (70px tall) beside "Voir tout", which wraps too. |
| Grid | **2 columns**, 16px gap. Measured card width **163.5px** on an SE and **172.5px** on a 15. |
| Empty | 56px `slate-100` disc with a 28px icon, then the message at 20px `slate-500`, `py-12` |

### The card (`people/_card`)

| Part | Value |
|---|---|
| Card | white, radius 16, `slate-200` border, `shadow-sm` |
| Photo | **square**, full card width (162px on an SE). With no photo, a `cm-green → emerald-700` diagonal gradient and one white initial at **48px** bold (`text-5xl`, not scaled). Hover zooms the photo 1.05. |
| Body | `p-3`. Name **20px** bold, one line (your saved nickname first, then username, then name, which is GoConnect's rule). Location at **15px** `slate-500`: *region, country*. |
| Buttons | 37px tall (measured), radius 8, `px-2 py-1.5`, **17.5px** semibold, 8px apart |

Tapping the photo or the name opens the **user preview** overlay (pass 1), not the profile. The
"View profile" button goes to the profile.

Buttons per mode:

| Mode | Buttons |
|---|---|
| request | **Accept** (navy) above **Decline** (`slate-100`), stacked. After accepting: one "Connected ✓"; after declining, a disabled "Request declined". |
| suggest | **Connect** (navy, full width) above **View profile** (`slate-100`). After connecting: a disabled "Request sent". |
| connected | **View profile** (navy) |
| all | **View profile** (`slate-100`) |

The actions `fetch()` the GoConnect connection endpoints as JSON. On success the card flips
in place; on failure it raises an error toast and stays as it was (§6, findings 1–5, fixed
2026-11-06). French labels: Accepter / Refuser / Se connecter. Measured on an SE, the longest,
"Se connecter", needs 112px of the 122px the button has (`shots/people-fixed-se.png`).

---

## 3. The static pages: one family

About, Privacy and Terms always use the **guest** layout. Contact switches to the app layout
for signed-in members. All four share one look: the marketing navbar (64px, measured), a soft
hero, then white cards.

| Part | Value (rendered, iPhone 15) |
|---|---|
| Navbar | 64px, navy `#233957` on Privacy and Terms; white "scrolled" state on About and Contact, where the logo loses its white "Cameroon" (§6, finding 11). Logo, an EN/FR toggle and ☰. |
| Page | gradient `slate-50 → white → slate-50`. `pt-24` (96px) clears the fixed navbar. |
| Hero | centred, `px-6` (24px gutter), `pt-10 pb-10`. An accent glow (`emerald-400/20`, from the top-left). |
| Eyebrow chip | `text-[11px]` → **14px** bold uppercase, wide tracking, pill, ringed: emerald for About, Contact and Privacy; amber for Terms (`accent="yellow"`). |
| H1 | `text-4xl` extrabold, **36px** (measured; `text-4xl` isn't scaled), `tracking-tight`, centred |
| Lede | `text-base` → **20px** (measured), `slate-600`, `leading-relaxed` |
| Body copy | `text-sm` → **17.5px** in cards; About's story at **19px** (`text-[15px]`) |
| Cards | white, radius 16, `slate-200` border, `p-6` |
| Footer links | Privacy · Terms · Home at 17.5px `slate-500`, separated by `slate-300` dots |

The Kamer AI chat bubble also shows on these pages (bottom-right in the shots).

### About

Hero ("A network built by Cameroonians, for Cameroonians.") → three mission cards
(🤝 Connect · 💛 Support · 🇨🇲 Belong), each with a 48px tinted tile → **Our story** (three
paragraphs) → **What we stand for** (Privacy first, Bilingual by design, Safety as a feature,
Built in the open) → a `cm-green → emerald-700` CTA card with **Create account**
(`cm-yellow` pill) and **Contact us** (white/10 pill). Rendered height on a 15: **3,384px**.

### Contact

Hero → **six mailto cards**, one column on a phone: General (hello@), Privacy & data (privacy@),
Security (security@), Partnerships (partnerships@), Press & media (press@), Account help
(support@). Each has its own tint (emerald, indigo, rose, amber, sky, violet), a 48px emoji tile,
a 20px title, a 15px description, and the address at 17.5px semibold in the tint. Then **Send a
quick message**: name, email, subject and message fields that build a `mailto:` link ("Open in
email app"). There's no backend; it opens the visitor's mail app.

### Privacy and Terms (`x-legal.shell`)

The same component with different content: an eyebrow, title, subtitle and "Last updated: May
2026", then **numbered section cards**. Each has a 48px tile with an emoji, a `01`–`05` index at
14px bold `slate-400`, a title at **22.5px** bold, and the body at 17.5px. A dark `slate-900`
contact card follows ("Questions? We're listening.") with a `cm-yellow` hello@ pill.
Privacy: 5 sections. Terms: 5 sections, including "Solidarity with integrity".

All their text is put in place by Alpine (`x-text`), so **with JavaScript off, or for anything
that reads the HTML without running it, the policy is blank**.

---

## 4. For the app

- **People**: native, mirroring §2. Keep the pill strip, the 2-up square cards and the button
  modes. Use pull-to-refresh and infinite scroll in place of the GET reloads and numbered pages.
- **Static pages**: **don't rebuild them natively.** Open the live URLs in an in-app browser
  (`SFSafariViewController`). They're marketing pages with their own navbar, they change without
  an app release, and Apple wants the privacy policy reachable inside the app *and* given as a
  URL in App Store Connect, so the URL has to exist anyway. *Decided 2026-11-06, and the web
  side is built:* see §4.1.

### 4.1 The URL contract (built 2026-11-06)

The app opens `{baseUrl}/about`, `/contact`, `/privacy` or `/terms` with two parameters,
handled by `StaticPageContext` (route middleware on those four routes only):

| Parameter | Does |
|---|---|
| `lang=en\|fr` | Sets the page's language, server and browser alike. **Required from the app:** the in-app browser shares no storage with the app or with Safari, so without it a French member reads English. Anything else is ignored. |
| `app=1` | The page alone. It hides the marketing navbar (and the room reserved for it), the Kamer bubble, the PWA install banner, About's **Create account**, and the **Home** links. Contact stays the bare guest page even for a signed-in member. Links between the four pages keep both parameters. |

What's left inside the sheet: the hero, the cards, mailto links (they open Mail), and About's
**Contact us**. Measured on an iPhone 15, the eyebrow chip starts 40px below the sheet's bar
(the hero's own `pt-10`) and the H1 at 89px.

Two consequences of how the web side was built:

- **The pages' text is in the HTML now** (finding 8, fixed). Every `x-text` on these four pages
  also renders its text on the server, in the page's language (`App\Support\Bilingual::t`, the
  twin of `$store.lang.t`). App Review's fetch, search engines and link previews see the words,
  and Alpine takes over as before once it runs.
- **No flash between languages.** A short script in the guest layout's `<head>` saves `?lang=` as
  the browser's choice. Where the saved choice differs from the server's, `x-text` stays hidden
  until Alpine fills it, as it always did.

The App Store Connect privacy URL is the plain one: `https://cameroonnetwork.org/privacy`.

**Where the app opens them.** On the web, signed-in members reach only **Contact** (the ☰
drawer and the GoConnect rail). Privacy and Terms are linked only from sign-up (Terms, in the
step 2 checkbox) and the public site. Apple wants the privacy policy reachable from inside the
app, so the app's drawer adds **About, Privacy and Terms** under Contact. This is a native
addition where the web has no answer; adding the same three links to the web drawer would
bring the two back into line.

---

## 5. Endpoints

| Need | Endpoint |
|---|---|
| People sections | `GET /api/v1/people?tab=home\|requests\|suggestions\|all\|connections&q=&cursor=`: one shape, `UserResource` with region and country, plus each person's `connection_state` |
| Counts for the strip | in the same response: `requests_count`, `connections_count` |
| Connect / Accept / Decline | the connection endpoints from pass 8 §8 (`ConnectionService`); on the web, `yard/connections/{request,accept,decline}` |
| Static pages | none: the four URLs with `?app=1&lang=`, in an in-app browser (§4.1) |

---

## 6. Divergences found in this pass

> **Findings 1–5 are fixed on the web (2026-11-06).** This was approved with the passes 6–8 batch,
> and People went first. The verbs are **Connect / Accept** everywhere, by your decision. Two
> more things turned up while fixing them:
> - **Pages outside GoConnect had no toast listener at all.** That covers GoMarket, the
>   profile, People and the home page, so every `toast` they raised was dropped, including the
>   profile's "Request sent" and its errors. `partials/toasts` now mounts in the app layout and
>   stands down on `/yard`, which has its own.
> - **`POST yard/connections/accept` answered "connected" even when there was nothing to
>   accept**, for example a withdrawn request. It now answers 422 `no_pending_request`, and a
>   second tap on an accepted request still succeeds. There's a new
>   `POST yard/connections/decline` beside it.
>
> Tests: `tests/Feature/PeopleConnectionsTest.php`.

1. **On an iPhone, the request card's Confirm button is clipped.** *Rendered (rebuild).* In
   request mode, Confirm and View share the card width in two equal columns, **65px** each on
   an SE and **69px** on a 15. "Confirm" needs **77px**, and the French "**Accepter**" needs
   **85px**. So it's cut off on the SE and the 15 in English, and on **every iPhone** in French,
   where it reads "Accept" (see `shots/people-rebuild-se.png`). *App:* stack the two buttons,
   as suggest mode already does, or shorten the labels. *Web:* the same one-class fix
   (`grid-cols-2` → stacked).

2. **Search on My connections does nothing.** *Code.* The search field shows on the
   Connections tab, but `myConnections()` never reads `q`, so the list comes back unchanged.

3. **Failed connection actions are silent.** *Code.* The card's `send()` sets `done` only when
   `r.ok`. Otherwise (a 422 because a request already exists, a 419 after the session's CSRF
   token expires, a network drop) the spinner stops and **nothing tells the member**. The
   profile page reports errors as toasts; People should too.

4. **There's no Decline on People.** *Code.* A request card offers Confirm and View only.
   Declining needs a trip to the profile or GoConnect → Connections. The wording also differs
   by screen: People says **Add / Confirm**; the profile page says **Connect / Accept request**.
   One verb pair in the app.

5. **The red badge on "My connections" isn't a notification.** *Code.* It's styled exactly like
   the Requests badge (red, bold) but shows your **total** connections, so a member with 40
   connections sees a permanent red "40". The app should use a grey count for that one.

6. **Signed-in members are sent to the marketing site.** *Rendered.* About, Privacy and Terms
   always use the guest layout. Following "Privacy Policy" from inside the product drops a
   signed-in member onto the public navbar, with a **Create account** call to action on About.
   Contact alone switches layouts. (The app avoids this with the in-app browser.)

7. **The privacy policy's promises don't match what the code does.** *Code.* This is a
   compliance question for you, and probably a lawyer, not a styling one. It matters for the app
   because Apple checks the policy against the **App Privacy** answers you'll give in App Store
   Connect.
   - *"…your name, email, language, and **approximate** location"* and *"We use your **city
     and region**…"*. In fact the register wizard takes a **precise GPS fix** (pass 6), and while
     the site is open the location tracker re-reads GPS **every ten minutes**.
     `LocationHistoryService` stores a **trail of coordinates** (a new row for every move of
     ~200m, or every 15 minutes) with the IP address, kept **90 days** (`locations:prune`). The
     policy doesn't mention a history.
   - *"You can **clear your location at any time from your profile**."* No such control exists
     on either profile screen (pass 8).
   - *"Conversations in GoConnect… **We do not read them.** … Reports of abuse are reviewed **by
     humans, not algorithms**."* Every GoConnect text message is sent to **OpenAI** for
     automated moderation as it's posted (`ChatRoom.php` 579–600), and messages are flagged
     automatically above a score threshold. Auto-translate and thread summaries also send message
     text to OpenAI. The policy names no third-party processors.
   - *"…delete your account at any time."* By emailing privacy@. Apple additionally requires
     **in-app** deletion (pass 8, finding 2).

   The About page has a sibling claim: *"Every screen, every button, every email: French and
   English, equally."* Passes 6–8 found the server messages English-only (no `lang/fr.json`).

8. **The policies render blank without JavaScript.** *Fixed 2026-11-06 (§4.1).* *Code.* All Privacy and Terms text arrives
   via `x-text`. Search engines, link previews, accessibility tools that read the HTML and anyone
   with scripts blocked see empty headings. Legal text should be server-rendered in the
   visitor's language. *App:* unaffected (the in-app browser runs JS), but App Review sometimes
   reads the policy with a simple fetch.

9. **The legal pages and About advertise Solidarity.** *Code.* Terms §3 ("Solidarity with
   integrity") and About's 💛 Support card describe fundraising that isn't live. It's the same
   question as passes 6 and 8.

10. **Brand and addresses.** *Resolved 2026-11-11:* the platform is **Cameroon Network**
    (the owner's decision), which is what these pages, the logo and the addresses already
    say. Only the `site_name` setting said "Cameroon Community"; a migration changes it.

11. **Half the logo disappears on About and Contact.** *Rendered.* Those two pages set the
    navbar to its white "scrolled" state (`$forceScrolled = true`), but the logo's
    **"Cameroon" is white**, so it vanishes on white and the header reads just "*Network*" (see
    `shots/static-about-iphone15.png`). Privacy and Terms keep the navy bar and are fine. It's the
    same logo bug pass 6 found on forgot, reset and verify. One fix: a dark wordmark for light
    backgrounds. *Fixed 2026-11-06:* the navbar shows a navy-wordmark copy of the logo whenever
    it is white (pass 6, finding 10).

Recorded, not counted: "Last updated: May 2026" is hand-typed; the search uses `LIKE` without
escaping `%` and `_`; the People page title falls back to the hardcoded "Cameroon Network".

---

## 7. Decisions for the owner

1. **Static pages in the app as an in-app browser** on the live URLs, not native screens (§4).
   *Decided 2026-11-06; the web side is built (§4.1).*
2. **Review the privacy policy against the code before the App Store submission** (finding 7).
   *Agreed 2026-11-06.*
3. **People fixes on the web.** *Approved and done 2026-11-06* (see the note at the top of §6).
4. **One verb pair: Connect / Accept.** *Decided 2026-11-06.* French: Se connecter / Accepter,
   and Decline / Refuser. Applied on People; the other screens already used it.

No live code was changed in this pass. The People fixes came after it.

---

## 8. Dart for this pass

```dart
/// People and the static pages. Phone values; see docs/ui-parity/09-people-static.md.
abstract final class CnPeople {
  static const gutter = 16.0;
  static const columns = 2;
  static const gridGap = 16.0;

  // ── Tab strip ──
  static const pillFont = 17.5;          // text-sm semibold
  static const pillPadH = 16.0;
  static const pillPadV = 8.0;
  static const badgeHeight = 16.0;
  static const badgeFont = 13.0;
  static const badgeColor = CnTw.red500; // Tailwind red, not cm-red: Requests only
  /// My connections is a total, not something to answer: a grey count.
  static const countBg = CnTw.slate100;
  static const countFg = CnTw.slate600;

  // ── Card ──
  static const cardRadius = 16.0;
  static const photoAspect = 1.0;
  static const initialSize = 48.0;       // text-5xl, unscaled
  static const name = 20.0;
  static const location = 15.0;
  static const buttonHeight = 37.0;      // measured
  static const buttonRadius = 8.0;
  static const buttonFont = 17.5;
  /// Accept above Decline. Side by side, "Accepter" was clipped on every iPhone.
  static const stackRequestButtons = true;

  // ── Headings ──
  static const sectionTitle = 25.0;      // Home's two sections
  static const pageTitle = 30.0;         // single-tab pages
  static const searchFont = 17.5;
}

/// About, Contact, Privacy and Terms: the live pages in SFSafariViewController
/// (url_launcher's inAppBrowserView on iOS), never rebuilt. §4.1.
abstract final class CnStaticPages {
  static const about = '/about';
  static const contact = '/contact';
  static const privacy = '/privacy';
  static const terms = '/terms';

  /// app=1: the page alone, without the website's navbar and links.
  /// lang: always, because the in-app browser shares no storage with the app.
  static Uri url(String path, String languageCode) =>
      Uri.parse('${AppConstants.baseUrl}$path').replace(queryParameters: {
        'app': '1',
        'lang': languageCode == 'fr' ? 'fr' : 'en',
      });

  static Future<bool> open(String path) => launchUrl(
        url(path, Get.locale?.languageCode ?? 'en'),
        mode: LaunchMode.inAppBrowserView,
      );
}
```

---

That's every route. What's left in the README's list: **icons** (inline SVG paths into an asset
set), **animation** (38 keyframe sets to Flutter equivalents), and the **overlays** a route list
can't see.
