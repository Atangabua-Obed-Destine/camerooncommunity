# 06 — Auth and onboarding

Sources: `resources/views/auth/{login,forgot-password,reset-password,verify-email}.blade.php`,
`resources/views/livewire/auth/register-wizard.blade.php` + `app/Livewire/Auth/RegisterWizard.php`,
`resources/views/livewire/onboarding/onboarding-flow.blade.php` + `app/Livewire/Onboarding/OnboardingFlow.php`,
`resources/views/components/cameroon-ribbon.blade.php`, `app/Http/Controllers/Auth/*`.

Six screens: login, register (a three-step wizard), forgot password, reset password,
verify email, and onboarding (`/welcome`, four steps).

**Every number in this document marked _measured_ was read off the live page** at a true
iPhone viewport with `tools/ui-parity/shoot.mjs` (new in this pass, see §10). The rest are
derived from the classes using the phone column of `tokens.md`. Remember the 1.25× rule:
`text-sm` is **17.5px** on these screens, not 14. The guest shell applies it to every
auth page; only the marketing landing page is exempt.

Hexes come from `CnTw` / `CnColors` in `generated/cn_tokens.dart`. Note in particular that
`blue-700` is **`#1447E6`** in the build. The value most people remember, `#1D4ED8`, is
Tailwind v3's, and it is visibly wrong on every primary button here.

---

## 1. The flow

```
splash → GET /configuration
   │
   ├─ no token ──► LOGIN ◄────────────── "Sign in" pill ────────────┐
   │                │  "Sign up" pill                                │
   │                ▼                                                │
   │            REGISTER  step 1 Location → 2 Account → 3 Review ───┘
   │                │  register() logs in, then:
   │                ▼
   │      (verify-email, only if require_email_verification = '1' and not Google)
   │                ▼
   └─ token ──► ONBOARDING  1 Kamer → 2 Rooms → 3 Bio → 4 Launch ──► GoConnect (yard)
```

Where each entry point lands, from the controllers:

| Path in | Lands on |
|---|---|
| Password login, no `current_country` **or** no `onboarded_at` | onboarding |
| Password login, onboarded | `intended` URL, else GoConnect |
| Google, `google_id` known | `intended`, else GoConnect (no onboarding check here; `EnsureOnboarded` catches it on the next request) |
| Google, email matches an account | links `google_id`, marks the email verified, then as above |
| Google, new person | profile stashed in session → register **step 1**, the GPS step is still enforced; email locked, password skipped |
| Register complete | onboarding |
| Any signed-in route while `onboarded_at` is null | onboarding (`EnsureOnboarded`) |

The app needs the same decision tree, driven by `GET /auth/me`
(`current_country`, `onboarded_at`, `email_verified_at`) rather than by redirects.

---

## 2. Two visual families

The auth screens were not built at the same time, and it shows. **Both families are the
approved design and are mirrored as they are**, not unified.

| | **A — branded** (login, register) | **B — plain** (forgot, reset, verify) |
|---|---|---|
| Top | 65px gradient bar with logo + flag ribbon | logo alone, centred, 56px tall, on the page |
| Page | `fixed inset-0`: the page never scrolls, an inner panel does | `min-h-screen`: the page itself scrolls |
| Card | 353 wide (20px insets), r16, tricolour top line, `shadow-xl shadow-black/5`, border `slate-200/80` | 361 wide (16px insets), r16, padding 32, `shadow-xl`, border `slate-200`, no tricolour |
| Title | 30px / 800, centred (login) or left (register) | 30px / **700**, left |
| Labels | 17.5px / **700** `slate-900` | 17.5px / **500** `slate-700` |
| Subtitle | 17.5px / 700 `slate-700` | 17.5px / 400 `slate-500` |
| Primary button | **gradient** `cm-green → blue-700`, `shadow-lg shadow-cm-green/20` | **solid** `cm-green` `#243A5C`, no shadow |
| Inputs | leading icon on login (padding-left 44) | no icons |

---

## 3. Shared parts

### 3.1 The auth bar (family A) — _measured_ at 393px

```
┌──────────────────────────────────────────────────────┐  y=0
│ [logo 80×40]                         ╲╲╲ ribbon ╱╱╱  │  h=65 (12 + 40 + 12 + 1px border)
└──────────────────────────────────────────────────────┘  border-bottom 1px slate-200 #E2E8F0
```

- Background: `bg-gradient-to-l from-white via-blue-400 to-cm-green`. Read **right to left**:
  white `#FFFFFF` at the right edge, `blue-400` `#51A2FF` in the middle, `cm-green`
  `#243A5C` at the left. In Flutter: `LinearGradient(begin: Alignment.centerRight,
  end: Alignment.centerLeft, colors: [white, blue400, cmGreen])`.
- Logo: `$__siteLogo` (site settings, from `/configuration`), `h-10`, natural width
  (80px for the current logo), left inset 16. Fallback when no logo is set: 🇨🇲 at
  `text-2xl` (30px).
- `overflow: hidden`: the ribbon is clipped at the bar's bottom edge.
- This bar is not the signed-in header. It appears only on these two screens.

### 3.2 The Cameroon ribbon

Three flag-coloured waves with three stars, drawn in the top-right corner.

| | |
|---|---|
| Box on a phone | 180 × 140, anchored top-right (`size="sm"`) |
| viewBox | 600 × 440, `xMaxYMin meet` → scale **0.30**, drawn 180 × 132, flush right and top |
| Visible on the auth bar | the top 65px only (≈ viewBox y ≤ 216) |
| Stroke | 20 viewBox units = **6px** on the phone, round caps and joins |
| Colours | green **`#009639`** (the flag's green, *not* `cm-green`), red `#CE1126`, yellow `#FCD116` |
| Shadow | `feDropShadow dx=2 dy=3 σ=4 black 15%` → 0.6 / 0.9 / 1.2px on the phone |

The three paths are identical cubic curves offset 20 units apart on x:

```
green  M 80,-10  C 110,20 140,60 170,100  C 200,140 260,140 310,90  C 350,45 390,45 420,90  C 450,135 480,135 515,90  C 550,45 580,65 610,110
red    (same, +20 on every x)
yellow (same, +40 on every x)
```

**Star positions are precomputed here.** On the web, an `x-init` script walks the paths at
runtime (point at 10%, 44% and 82% of the red path, nearest of 501 samples on green and
yellow, midpoint). Flutter can't run that, and doesn't need to: the paths never change. I
ran the same algorithm offline (path length 680.2 for all three):

| Star | viewBox | phone (×0.3, from the ribbon box's top-left) |
|---|---|---|
| 1 | (144.8, 41.1) | (43.4, 12.3) |
| 2 | (326.2, 92.8) | (97.9, 27.8) |
| 3 | (536.0, 89.2) | (160.8, 26.8) |

Each star is a 10-point polygon, radius 8 units (2.4px), fill `#FCD116`, stroke `#CE1126`
0.5 units:
`0,-8 1.9,-2.6 7.6,-2.6 2.9,1 4.7,6 0,2.3 -4.7,6 -2.9,1 -7.6,-2.6 -1.9,-2.6`.

Implement as one `CustomPainter` with the viewBox transform. Do not ship it as a PNG: the
bar's height changes with the Dynamic Island inset, and the clip must follow.

### 3.3 Card, line, fields, buttons

| Part | Spec (phone) |
|---|---|
| Tricolour line | 4px, `to-r` `cm-green #243A5C → cm-red #CE1126 → cm-yellow #FCD116`, inside the card's top edge |
| Text input | **51px** _measured_, r12, border 1px `slate-300 #CBD5E1`, padding 12/16, text 17.5px. Focus: border `cm-green` plus a 1px ring of `cm-green` (2px of green in total) |
| Input with icon (login) | icon 20px `slate-400` at left 14, text padding-left 44 |
| Password eye | 20px `slate-400` at right 12, toggles visibility, separate "eye" / "eye-off" paths |
| Field error | 15px (`text-xs`) `cm-red`, 4px below the field |
| Primary, family A | **53px** _measured_, r12, gradient `to-r cm-green → blue-700 #1447E6`, 17.5px / 700 white, shadow `0 10px 15px -3px` + `0 4px 6px -4px` `cm-green` @ 20% |
| Primary, family B | 53px, r12, solid `cm-green`; pressed: `cm-green-light #2E4A70` |
| Secondary ("Back") | 53px, r12, border `slate-300`, 17.5px / 700 `slate-600`, `flex-1` against the primary's `flex-[2]` |
| Google button | **51px** _measured_, r12, white, border `slate-300`, `shadow-sm`, 17.5px / 700 `slate-800`, the 4-colour G at 20px, gap 12 |
| "or …" divider | hairlines `slate-200`, label 14px / 700, uppercase, `tracking-wider`, `slate-400`, gap 12, 20px above and below |
| Footer pill | 37px _measured_, full radius, `cm-yellow`, 17.5px / **800** `cm-green-dark #1B2D4A`, padding 6/16, 8px after the prompt text |
| Status banner (success) | r12, bg `cm-green` @ 10%, border `cm-green` @ 20%, 17.5px `cm-green`, padding 12/16 |
| Status banner (session expired) | r12, `amber-50 #FFFBEB`, border `amber-200 #FEE685`, text `amber-800 #973C00`, 16px alert icon |

Loading state on every primary button: the label is swapped for a 20px spinner (a
25%-opacity ring with a 75%-opacity quarter arc), and the button sits at 50% opacity while
disabled.

---

## 4. Login

_Measured_ at 393 × 852 (iPhone 15):

| Element | y | h | Notes |
|---|---|---|---|
| Auth bar | 0 | 65 | §3.1 |
| User disc | 134 | 64 | gradient `br cm-green → blue-700`, white 32px user glyph (1.5 stroke), `shadow-lg shadow-cm-green/20`, 20px below |
| Card | 218 | 504 | x=20, w=353; inner padding 24 |
| "Welcome Back" | 247 | 40 | 30px / 800 `slate-900`, centred |
| "Sign in to your community." | 291 | 25 | 17.5 / 700 `slate-700`, centred |
| Continue with Google | 340 | 51 | 24px below the subtitle |
| "OR SIGN IN WITH EMAIL" | 411 | 21 | |
| Email label → input | 452 → 481 | 25, 51 | envelope icon |
| Password label row | ~548 | | label left, **"Forgot password?"** right: 15px / 700 `cm-green` |
| Password input | | 51 | lock icon, eye toggle |
| Sign In | 644 | 53 | 16px gap between fields |
| "Don't have an account?" + **Sign up** pill | 746 | 37 | 24px below the card |

There is **no "Remember me"**, on purpose: a web sign-in always persists. The app does the
same, and the token lasts until sign-out.

States: the success banner shows a flash `status` (e.g. after a reset); `?expired=1` shows
the amber "Your session has expired" banner; a failed sign-in shows **one** error, under
the email field ("These credentials do not match our records.").

### The short-phone bug — don't copy it

The scrolling panel is `flex items-center justify-center overflow-y-auto`. When the
content is taller than the panel, centring pushes it out **both** ways, and the part above
the top edge can never be scrolled to. _Measured_:

| Viewport | Content overflow | Unreachable at the top |
|---|---|---|
| iPhone 15, 393 × 852 | none | 0 |
| iPhone SE, 375 × 667 | 64px | **63px** |
| iPhone SE in Safari with toolbars, 375 × 553 | 121px | **120px**: the whole user disc and its padding |

Register avoided this with `items-start`. The app should **centre when the content fits
and scroll from the top when it doesn't** (`LayoutBuilder` →
`SingleChildScrollView` → `ConstrainedBox(minHeight: viewport)` → `Center`). That matches
exactly what an iPhone 15 user sees today, without the SE defect.

---

## 5. Register — the wizard

Same shell as login (bar, `bg-slate-50/50` panel), but the panel is `items-start`, the
column is `max-w-lg` (fills the phone), padding 20 / 32 top.

### 5.1 Progress row

Three 36px discs joined by 2px lines (`mx-2`, r-full). Below 640px the step labels are
hidden: **icons only**.

| Disc state | Look |
|---|---|
| Done | `cm-green`, white ✓ (16px, stroke 3), `shadow-md shadow-cm-green/30`, tappable → `goToStep(i)` |
| Current | `cm-green`, the step emoji (👋 👤 🚀), **scale 1.10**, 4px ring of `cm-green` @ 20%, `shadow-lg shadow-cm-green/40` |
| Ahead | `slate-100`, emoji, `slate-400`, not tappable |
| Line | `cm-green` once the step on its left is done, else `slate-200`; 700ms colour transition |

### 5.2 Kamer companion bubble

40px gradient disc (`br cm-green → blue-700`, 🤖) + a white bubble, r16 with a **6px
top-left corner** (`rounded-tl-md`), border `slate-200/80`, `shadow-sm`, padding 12/16,
and a 8px rotated-square tail at left −8, top 12. Text 17.5px / 700 `slate-900`. Footer: a
6px pulsing `cm-green` dot + "Kamer AI" 13px / 800 `cm-green`. Enters 300ms after load:
fade + 8px upward slide, 500ms ease-out.

The message is computed server-side from the wizard's state. In the app it is a pure
function and can live in the client:

| Step | Condition | EN | FR |
|---|---|---|---|
| 1 | nothing detected yet | Hey there! 👋 Let me find where you are... | Salut ! 👋 Laissez-moi vous localiser... |
| 1 | members already in the country | Found you! 📍 You're in {loc}. Your community is waiting for you! 🇨🇲 | Trouvé ! 📍 Vous êtes à {loc}. Votre communauté vous attend ! 🇨🇲 |
| 1 | no members there yet | Found you! 📍 {loc}. You'll be among the first here, a true pioneer! 🚀 | Trouvé ! 📍 {loc}. Vous serez parmi les premiers, un vrai pionnier ! 🚀 |
| 2 | username typed | Nice to meet you, {username}! 🙌 Just a few more details. | Enchanté, {username} ! 🙌 Encore quelques détails et c'est bon. |
| 2 | otherwise | Let's set up your space! This is quick, I promise. 🚀 | Créons votre espace ! C'est rapide, promis. 🚀 |
| 3 | username set | Everything's set, {username}! 🎊 One click and you're family. Welcome home! | Tout est prêt, {username} ! 🎊 Un clic et vous êtes de la famille. Bienvenue chez vous ! |
| 3 | otherwise | All set! 🎊 One click and let's go. | Tout est prêt ! 🎊 Un clic et c'est parti. |

`{loc}` is "Region, Country", or the country alone. "Members already in the country" needs
a count from the server (§9).

### 5.3 Step 1 — location

The card opens with **48px of empty space** (`mt-6` inside `p-6`, no heading). That is the
approved look; keep it.

Detection starts by itself on load. The location fields are **locked** while detecting,
when denied, and when a precise fix succeeded. They are editable only when the result is
approximate.

| State | Banner | Fields |
|---|---|---|
| Detecting | radar: three concentric `cm-green` rings (30/40/50% opacity, insets 0/8/16 in an 80px box) pinging 0.4s apart, a 32px pin, then "Setting things up for you..." 17.5 / 700 `cm-green`, pulsing | locked |
| Precise fix | `to-r cm-green/5 → blue-50 #EFF6FF`, border `cm-green/20`, ✓ disc, "Welcome! {Region, Country}" 17.5 / 600 `cm-green`, plus "Join your community today!" when members exist there | locked |
| Coarse fix (> 50 km) | amber: `amber-50` / `amber-200` / title `amber-800` / body `amber-700 #BB4D00`, "Location is approximate", plus "Accuracy: N km" | editable |
| IP fallback | amber, "Using approximate location" | editable |
| Denied | red: `red-50 #FEF2F2` / `red-200 #FFC9C9`, title 17.5 / 600 `red-800 #9F0712`, body 15px `red-700 #C10007`, "Location access blocked" + browser instructions | **locked: the member cannot proceed** |
| Insecure origin | amber, "GPS unavailable on this connection" + the host in monospace | editable |

Accuracy is classed `< 100 m` GPS, `< 5 km` Wi-Fi, otherwise cell. Above **50 km** the fix
is flagged poor.

Below the banner: **Current Country** select, **Region / State / Province** text field
(placeholder "England, Bavaria, California..."), a **Preferred Language** row with a
compact pill (`slate-100` track, 2px padding; selected segment `cm-yellow` with `cm-green`
text and `shadow-sm`; "🇬🇧 EN" / "🇫🇷 FR", 15px / 600). Choosing a language switches the
whole UI immediately. Then a "Not your area? Refresh" text button (15px / 700
`cm-green`, 14px refresh icon) and **Continue →**.

**What the app does differently, because the web's answer doesn't apply:**

- *Insecure origin* cannot happen in an app. Drop that state.
- *Denied*: the web tells the member to tap the padlock in the address bar. An app has no
  address bar. Keep the red banner and the lock, but replace the instructions with one line
  and an **Open Settings** button (`app-settings:`), and re-check permission on resume.
- Use `CLLocationManager` with full accuracy, and ask with
  `NSLocationWhenInUseUsageDescription`. If the member grants only *approximate* location
  (iOS 14+), treat it like the coarse-fix state: amber banner, editable fields.
- Reverse geocoding: see divergence 6. The app should send coordinates to the API and
  display the region the **server** returns.

### 5.4 Step 2 — account

Title "Create Your Account" (30 / 800), subtitle "This takes under a minute, we promise."

- **Not from Google:** the Google button, then "OR FILL IN YOUR DETAILS".
- **From Google:** instead, a `cm-green/5` card with the 40px Google avatar (2px white
  ring), "SIGNED IN WITH GOOGLE" (15px / 700, uppercase, `cm-green`) and the email. The
  email field is read-only, and the password fields are not shown.

Fields:

| Field | Behaviour |
|---|---|
| Username | validated as you type (500ms debounce): 2–50 chars, `^[a-zA-Z0-9 _-]+$`. Under it, live: "You'll be known as: **calann-**`*****`" (spaces removed, lowercased; the `*****` in `slate-400`). The real handle gets `-` plus 5 random characters (letter-digit-letter-digit-letter) at creation. |
| Email | checked as you type (600ms debounce), including uniqueness. "Checking availability..." while in flight, then ✓ "Email is available" in `cm-green`, or the error |
| Password | eye toggle. A **4-segment strength bar** (6px segments, gap 4) and a **5-item checklist** in two columns |
| Confirm | border turns `cm-green` when it matches and `cm-red` when it doesn't, with "✓ Passwords match" / "⚠ Passwords do not match" |

Strength = one point each for length ≥ 8, an uppercase letter, a digit and a symbol
(lowercase is shown in the checklist but **not counted**). The server requires only
length, uppercase and digit.

| Score | Segments | Label |
|---|---|---|
| 0 | none | (empty) |
| 1 | `cm-red` | Weak, like watered garri · *Faible, comme du garri dilué* |
| 2 | `cm-yellow`, text `cm-yellow-dark #E0B800` | Getting there... · *On y arrive...* |
| 3 | `cm-green` | Solid! 💪 · *Solide ! 💪* |
| 4 | `cm-green` | Fort like ndolé! 🔥 · *Fort comme le ndolé ! 🔥* |

Checklist rows: 16px ✓ (stroke 3) in `cm-green` when met, otherwise a 16px empty circle in
`slate-400`; text 15px. Left column: 8 characters, uppercase, lowercase. Right column:
number, symbol.

For the app: set `textContentType` `.username` / `.emailAddress` / `.newPassword` so iOS
offers a strong password. A suggested password fills both fields and scores 4.

### 5.5 Step 3 — review

Title "Welcome Home! 🎉". Two summary rows (`slate-50`, border `slate-100`, r12):
📍 location with an "AUTO" tag, and 👤 `@handle-****` + the email, with an **Edit** link
back to step 2. Then:

- **What Awaits You** (`br cm-green/5 → blue-50`, border `cm-green/10`): three columns,
  💬 GoConnect "Chat rooms", 🤝 **Solidarity** "Crowdfund", 🤖 Kamer AI "Your guide" (see
  divergence 9).
- A founding-member tease (`cm-yellow/10`, border `cm-yellow/30`, ⭐): "You're early! You
  could be a Founding Member." It fades in after 500ms (see divergence 8).
- The terms checkbox (20px, `cm-green`), with **Terms of Service** and **Privacy Policy**
  linked (open in the in-app browser).
- **Back** | **Join the Family 🎊** (the primary here is 56px, `py-4`). While creating:
  spinner + "Creating your space...".

What `register()` does, all of which the API endpoint must do identically: create the
user with `active_country`/`active_region` set to the signup location; grant
`is_founding_member` while the tenant's user count is ≤ `founding_member_cap` (default 20);
auto-verify when Google or when verification is off; seed a Kamer welcome message; fire
`Registered` only when verification is on.

---

## 6. Forgot, reset, verify (family B)

_Measured_ (forgot, 393 × 852): logo 112 × 56 centred at y=192, 32px above the card;
card x=16, w=361, padding 32; title y=319; field 51; button 53. The card bottom is at 660
and the page is centred vertically in a `min-h-screen` box with 48px vertical padding.

| Screen | Content |
|---|---|
| Forgot | "Reset Password" · "Enter your email and we'll send you a reset link." · Email · **Send Reset Link** · "Back to login" (17.5px `cm-green`, centred) |
| Reset | "Set New Password" · Email (pre-filled from `?email=`) · New Password · Confirm Password · **Reset Password** |
| Verify | centred: 64px `cm-yellow/20` disc with a 32px envelope in `cm-yellow-dark`, "Check Your Email", body text, **Resend Verification Email**, then "Log out" as a plain 17.5px `slate-500` text button |

**In the app:**

- Forgot → `POST /auth/forgot-password` → the same success banner.
- The reset email links to the web (`/reset-password/{token}?email=`). Make
  `cameroonnetwork.org/reset-password/*` a **Universal Link** that opens the app's Reset
  screen, falling back to the web page when the app isn't installed. Until the
  `apple-app-site-association` file is live, the web page is an acceptable fallback,
  because it signs the member in nowhere and only sets the password.
- Verify: the link is a signed web URL. Show this screen while `email_verified_at` is null
  and re-check `GET /auth/me` whenever the app returns to the foreground. That way, tapping
  the link in Mail and coming back simply continues.
- **None of this works in production yet:** `MAIL_MAILER=log` means no reset or
  verification email is ever sent (plan C3 item 2).

---

## 7. Onboarding (`/welcome`)

Rendered inside the **signed-in app shell**: header, tab row and drawer are all present,
and the content starts at the shell's 104px offset. But `EnsureOnboarded` redirects every
other route back here, so on the web **every tab and drawer item bounces back to
onboarding**.

**Recommendation for the app: present onboarding full-screen, without the shell.**
Showing navigation that cannot be used is the one place where copying the web would copy
a defect. This is a visible change from the web, so it is listed as a decision in §11
rather than assumed.

Column: `max-w-2xl` (fills the phone), padding 8 top / 32 bottom / 16 sides.

### 7.1 Progress

Four pills, 8px tall, in a 24px tap target, gap 8, 32px below:

| State | Width | Colour |
|---|---|---|
| Current | 40 | `cm-green` |
| Unlocked | 24 | `cm-green` @ 40%, tappable |
| Locked | 8 | `slate-300` |

Labels row below (`justify-between`, 14px / 500): 🤖 Chat with Kamer · 🏠 Discover · ✨
Profile · 🚀 Launch, `cm-green` once unlocked, else `slate-400`. Every unlocked step is
reachable by tap **and by horizontal swipe** anywhere outside the chat, inputs and
selects: more than 60px, at least 1.3× more horizontal than vertical, within 800ms. Left
moves forward (only to unlocked steps), right moves back. In the app this is a
`PageView` whose forward scroll is clamped to `maxStep`.

All four steps are white cards (r16, `shadow-xl`, border `slate-200`) with a footer bar
(`slate-50/50`, top border `slate-100`, padding 16/24).

Card entrance: the source declares an 8px rise over 300ms, but that `<style>` is pushed to a
stack no layout outputs, so it **never ships** (divergence 12). What actually renders is
`app.css`'s `fade-in`, **opacity only, 500ms ease-out**. Copy that.

### 7.2 Step 1 — chat with Kamer

- Header: `to-r cm-green → blue-700`, padding 16/24, a 40px `white/20` disc with 🤖,
  "Kamer AI" 22.5px / 700 white, an 8px pulsing `blue-300 #8EC5FF` dot + "Your personal
  guide" 15px `white/70`.
- Transcript: a **fixed 288px** scroll area, padding 16/24, 16px between messages, always
  scrolled to the bottom. Kamer: 28px `cm-green/10` disc + `slate-50` bubble (r16, 6px
  top-left corner), 17.5px `slate-700`, markdown rendered, max 85% wide. Member:
  `cm-green` bubble (6px top-right corner), white, max 80%. Typing: three 8px `slate-400`
  dots bouncing 150ms apart.
- Quick-reply chips: 15px / 500 `cm-green` on `cm-green/5`, border `cm-green/20`, full
  radius, padding 8/16, gap 8, wrapping. They rotate with the number of member messages:

| Round | Chips |
|---|---|
| 0 | 🤝 Connect with Cameroonians near me · 🤲 Support the community · 🔍 Just exploring |
| 1 | 💼 Find jobs & opportunities · 🏠 Housing & accommodation · 🍲 Food & cultural events · 📦 Send a parcel home · 🚗 Car pooling & rides |
| 2 | 💬 I want to chat in GoConnect · 🎉 Show me upcoming events · ❤️ Help someone in need |
| 3+ | 🚀 I'm ready, take me in! · ❓ Tell me more about Kamer AI |

  The FR labels are in the view. **Two of these chips are broken on the web today**
  (divergence 4). The app should show all of them working.
- Composer: a pill input on `slate-50` ("Type a message to Kamer..."), and a 40px
  `cm-green` send disc.
- Footer: "You can always chat with Kamer later ✨" (14px `slate-400`) | **Discover
  Communities →** (solid `cm-green`, r12, padding 10/24).

The first message is the welcome seeded at registration. When the AI is unavailable (as now:
the OpenAI credit is exhausted), each reply falls back to "That's great! Let's discover
your communities now. 👇".

### 7.3 Step 2 — your communities

Header `br blue-600 #155DFC → indigo-700 #432DD7`, two decorative 10%-white circles,
🏠 at 36px, "Your Communities" 30 / 700, then "You've been added to your national and
regional rooms automatically." in `white/70`.

A location chip row (`slate-50`, r12: 📍 country / region, plus "N Cameroonians" in a
`cm-green/10` pill), then one card per room (2px border, r12, padding 16):

- 56px rounded-square icon: 🇨🇲 on `cm-green/10` (national) or 📍 on `blue-500/10`
  (regional).
- Name 20px / 700, an **AUTO-JOINED** pill (13px / 700 `cm-green` on `cm-green/10`) on
  default rooms, the description 15px `slate-500`, and a meta row with 👥 member count and
  💬 "Active" when the room has messages.
- A 28px selection disc: default rooms are filled `cm-green` with a **lock** and cannot be
  untoggled; selected rooms are filled with ✓; others are an empty `slate-300` ring.
  Selected card: border `cm-green`, bg `cm-green/5`, `shadow-md`.

Then a 💡 tip (`blue-50`, border `blue-100`, 14px `blue-700`). Footer: **Continue**, or
**Continue: Join N more Room(s)** when the member picked extras. The button is disabled
when nothing is selected.

### 7.4 Step 3 — make it yours

Header `br violet-600 #7F22FE → purple-700 #8200DB`, ✨, "Make it yours", "Help others know
a little about you."

- **Founding members only:** an `amber-50 → yellow-50` card with a staged reveal: four
  pinging sparkles, then 🏅 (60px) scales and rotates in at 200ms, the title at 500ms, the
  text at 700ms.
- Bio: a 3-row textarea on `slate-50`, 500 max, a placeholder about Douala and London, and
  under it "Optional, you can always add this later" | `N/500` (13px `slate-400`).
- Rooms joined: `blue-50` card with chips (`🇨🇲 name` / `📍 name`).
- Footer: **Skip** (plain text, `slate-500`) | **Continue →**.

### 7.5 Step 4 — launch

Hero `br cm-green → blue-700 → cm-green-light`, padding 40/32: 🎉 at 72px spins in
(−180° and scale 0 → 1, 700ms), then "You're all set, {name}!" (37.5px / 700) at 300ms,
then "Welcome to the family. GoConnect awaits." at 500ms. A three-tile grid (💬 Chat /
GoConnect, 🤲 Support / **Solidarity**, 🤖 Ask / Kamer AI) and **Enter GoConnect 🚀**
(full width, solid `cm-green`, 60px, `text-base` = 20px, `shadow-xl`).

**Confetti**, once: 120 rectangles, 3–11 × 2–6px, colours `#243A5C #CE1126 #FCD116
#FFFFFF #FFD700 #38BDF8`, starting above the card and falling 2–5px per frame with
±0.5px drift and ±0.1 rad spin, fading linearly over 250 frames (~4s), then cleared.

**Completing:** `onboarded_at = now()`, then GoConnect.

---

## 8. What these screens need from the API

| Need | Endpoint | Exists |
|---|---|---|
| Sign in | `POST /auth/login` | ✅ |
| Who am I, gate decisions | `GET /auth/me` | ✅ |
| Sign out | `POST /auth/logout` | ✅ |
| Create account | `POST /auth/register` (all of §5.5's side effects) | ❌ |
| Live email check | `GET /auth/email-available?email=` (throttled) | ❌ |
| Region normalisation and the member count for step 1 | `POST /location/resolve` {lat, lng, country?, region?, city?} → normalised country/region + `country_users` | ❌ |
| Forgot | `POST /auth/forgot-password` | ❌ |
| Reset | `POST /auth/reset-password` | ❌ |
| Resend verification | `POST /auth/email/resend` | ❌ |
| Google | `POST /auth/social` {provider: google, id_token} | ❌ |
| Apple (mandatory once Google ships) | `POST /auth/social` {provider: apple, identity_token, name?} | ❌ |
| Onboarding rooms | `GET /onboarding/rooms` (defaults + discoverable), `POST /onboarding/rooms` {ids} | ❌ |
| Bio | `PATCH /me` {bio} | ❌ |
| Kamer onboarding chat | `POST /ai/kamer/messages` | ❌ |
| Finish | `POST /onboarding/complete` | ❌ |

`POST /auth/social` must create the account through the same location step. A new Google or
Apple user gets a short-lived **signup ticket** instead of a token, and the app runs steps 1
and 3 with it. That is the web's session stash, made stateless.

---

## 9. Divergences found in this pass

Nothing has been changed except the extractor (§10). Ordered by what would actually hurt.

> **Fixed on the web (2026-11-06)**, with tests in `tests/Feature/WebFixesBatchTest.php`:
> - **1.** `POST /login` is limited to 10 a minute, forgot and reset to 6. Going over shows
>   "Too many attempts. Try again in N minute(s)." on the form, in the member's language, not a
>   bare 429 page.
> - **2.** One rule, `App\Support\PasswordPolicy`, for register and reset, and for the API when
>   it gets them: 8+ characters, an uppercase letter and a digit.
> - **4.** `@js()` for the onboarding chips (`wire:click` too, which also broke on
>   "I'm ready"), "You're all set" and the feed's "Welcome back". The feed's initial is now
>   `mb_substr` + `mb_strtoupper`.
> - **5.** Login scrolls from the top when it doesn't fit (`items-start` plus `my-auto`):
>   nothing is cut off above now, measured on an SE in Safari (553px), where 120px was.
> - **7.** A link or reload on `?step=` goes back to the first step that isn't complete.
> - **10.** Forgot, reset and verify show a copy of the logo with "Cameroon" in navy
>   (`SiteSettings::logoOnLightUrl()`), made once from whatever logo is uploaded. The
>   marketing navbar swaps to it when it turns white on scroll, which had the same bug.
> - **11 (partly).** French `auth.php`, `passwords.php` and the validation messages these forms
>   use, plus `lang/fr.json` (pass 7, finding 12). The API still needs its stable error codes.
> - **13.** The two `âš` are `⚠` again.
>
> **Second round (2026-11-11/12),** tests in `tests/Feature/GoLiveFixesTest.php`:
> **3** (the web's Google callback refuses to link or create from an unverified email; the
> native endpoint must do the same), **6** (the wizard resolves a UK region exactly as
> registration does, through `LocationService::normaliseUkRegion()`), **8** (the founding
> tease shows only while seats remain, and onboarding prints the real cap), **9** (Solidarity
> is labelled "Coming soon", the owner's choice), **14** (no throwaway User row), **15**
> (Continue is enabled when there are no rooms to pick), **17** (the placeholder is "At least
> 8 characters"; the checklist below it has the rules).
>
> Not changed: 12 (a dead style block, harmless) and 16 (dead code).

1. **No rate limit on the web's `POST /login`, `/forgot-password` or `/reset-password`.**
   Only verification resend is throttled. Password guessing and reset-mail flooding are
   open. (The API's login already has `throttle:10,1`.) One-line fix per route.
2. **Two password policies.** Registration requires 8+ characters with an uppercase letter
   and a digit; **reset accepts any 8 characters**. A member can reset to `password`. The
   API should define one `Password::` rule used by register and reset, web and app alike.
3. **Google linking trusts the email without checking it is verified.** Branch 2 of the
   callback attaches Google to whichever account has the same email. Google normally
   returns verified addresses, but the native ID-token endpoint must check the
   `email_verified` claim before linking, or it becomes an account-takeover path.
4. **Apostrophes break Blade values inside Alpine strings.** `{{ }}` escapes `'` to
   `&#039;`, the browser decodes it back inside the attribute, and the JS string ends early.
   _Confirmed by rendering:_ the onboarding chips **"🚀 I'm ready, take me in!"** and
   **"❤️ Help someone in need"** (its FR text, "quelqu'un", breaks it) render as **empty
   pills and do nothing when tapped**. The same happens to the feed's "Welcome back,
   {first name}!" and onboarding's "You're all set, {name}!" for any member whose name has
   an apostrophe (N'Golo, N'Dri), because the profile form accepts any name. Only the
   member's own name is shown, so this is not cross-user XSS. Fix: `@js()` instead of
   `'{{ }}'`.
5. **Login's top is unreachable on short phones** (§4, measured: 63px on an SE, 120px in
   Safari on an SE).
6. **The UK region shown is not the region saved.** _Measured_ with a central-London fix:
   the reverse geocoder returns city "City of Westminster", state "England"; neither is in
   `gb_city_to_region` or the alias map, so the wizard shows **England** in a locked field
   and on the review card. `register()` then re-normalises with the coordinates and saves
   the real ITL1 region. Every London borough is affected. In the app, the server resolves
   and returns the region (`/location/resolve`), so what is shown is what is saved. That
   also moves Nominatim off the client, where its usage policy requires an identifying
   User-Agent.
7. **`/register?step=3` skips the wizard.** The step is URL-bound and only clamped on
   change, not on load. Earlier steps' errors are only rendered on those steps, so a
   skipped-to step 3 fails with **no visible message**; the review card even shows the
   account as "@ -****" (see `shots/register-3-review.png`). The app owns its step state, so
   this does not carry over. Worth a guard on the web.
8. **The founding-member tease is unconditional.** Step 3 says "You could be a Founding
   Member" to everybody, whether or not the cap is long gone. Onboarding says "one of the
   first **20**" while the cap is a per-tenant setting. The app should show the tease only
   when `/configuration` says seats remain, and print the real cap.
9. **Solidarity is advertised on four auth surfaces** (register step 3, onboarding step 4,
   the onboarding chip "Support the community", login's desktop panel), and it is not live
   (pass 1). This is approved copy, so it is **not** changed without asking. Recorded so
   the app doesn't promise a module it can't open.
10. **The logo disappears on forgot, reset and verify.** The current logo has a white
    "Cameroon" wordmark; on `slate-50` only "Network" (yellow) is legible. Login and
    register put it on the navy end of the gradient, where it works.
11. **Server messages are English only.** `auth.failed`, the `passwords.*` statuses and
    "Verification link sent!" come from the framework's English files or are hardcoded;
    there is no `lang/fr/auth.php` or `passwords.php`. French members see English errors.
    The API should return a stable code plus a localised message.
12. **Onboarding's own `<style>` never ships.** It is pushed to `@stack('styles')`, which no
    layout outputs. Harmless today, because `app.css` has a `fade-in` of its own, but my
    token extractor had credited the dead copy (fixed, §10).
13. **Mojibake on register step 2:** the email-taken and password-mismatch warnings show
    `âš` where `⚠` was meant (2 places).
14. **A throwaway `User` row per UK signup.** `register()` saves a temporary user, runs
    `LocationService` on it, and deletes it. That fires model events and burns IDs. Don't
    port it: extract a pure normaliser that both paths call (plan A2).
15. **Possible dead end in onboarding step 2.** Continue is disabled when no room is
    selected. A member with no `current_country` has no default rooms and none to discover,
    so they can never leave step 2, and `EnsureOnboarded` keeps them there. New signups
    always have a country; older or admin-created accounts may not.
16. **Dead code:** `aiOriginMessage()`, and `country_of_origin` / `home_region` /
    `home_city` are validated and saved but no step collects them (the "Roots" step was
    removed; the header comment still says "4-step").
17. **The password placeholder is cut off on phones.** "Min 8 chars, 1 uppercase, 1 number"
    is longer than the field at 17.5px; an iPhone 15 shows "…1 uppercase, 1 n". The checklist
    under the field already says all of it, so the app can shorten it to "Min 8 characters".

---

## 10. Tooling added in this pass

- **`tools/ui-parity/shoot.mjs`**: renders a URL at a real iPhone viewport over the
  DevTools protocol, with no dependencies. Writes a PNG and prints a report: viewport,
  document size, every scrolling element with its overflow and **how much content is
  unreachable above it**. Options: `--device=se|15|max`, `--height` (Safari's toolbars),
  `--geo=lat,lng[,acc]` (makes the location request succeed), `--eval` (your own
  measurement, promises awaited).
  Written because `chrome --headless --window-size=375,…` silently lays out at about 500px
  on Windows, and the first login screenshot showed a clipped card no phone ever sees.
- **`extract.php`**: keyframes declared in two files now keep both sources, and `<style>`
  blocks inside `@push('styles')` are recognised as never rendered when no layout has
  `@stack('styles')` (`dead_style_blocks` in `tokens.json`).
- **`extract.php`**: the whole compiled Tailwind palette (257 colours, OKLCH → sRGB) is
  now in `tokens.json` as `tailwind_palette` and in Dart as **`CnTw`** (`CnTw.blue700`).

---

## 11. Decisions for the owner

| | Recommendation |
|---|---|
| Onboarding inside the shell (web) or full-screen (app)? | Full-screen. The web's navigation there cannot be used (§7). |
| Fix the login short-phone bug on the web too? | Yes. `items-center` → `items-start` plus a `my-auto` child gives the same look when it fits. |
| Batch of small web fixes: throttles (1), one password rule (2), `@js()` for the four strings (4), mojibake (13) | Yes, one commit with tests. These are bugs, not design changes. |
| Solidarity on auth screens (9) | Owner's call: it is approved copy. |

---

## 12. Flutter constants

```dart
abstract final class CnAuth {
  // Family A bar
  static const barHeight = 65.0;               // 12 + 40 + 12 + 1 border
  static const barLogoHeight = 40.0;
  static const barGradient = [CnColors.cmGreen, CnTw.blue400, CnTw.white]; // left → right

  // Ribbon (viewBox 600 × 440, scale 0.30 on a phone, box 180 × 140 top-right)
  static const ribbonBox = Size(180, 140);
  static const ribbonScale = 0.30;
  static const ribbonStroke = 20.0;            // viewBox units
  static const ribbonGreen = Color(0xFF009639); // flag green, NOT cmGreen
  static const ribbonStars = [Offset(144.8, 41.1), Offset(326.2, 92.8), Offset(536.0, 89.2)];

  // Card and controls (measured, iPhone 15)
  static const cardRadius = 16.0;
  static const cardInsetA = 20.0;              // login / register
  static const cardInsetB = 16.0;              // forgot / reset / verify
  static const cardPaddingA = 24.0;
  static const cardPaddingB = 32.0;
  static const tricolourHeight = 4.0;
  static const fieldHeight = 51.0;
  static const fieldRadius = 12.0;
  static const buttonHeight = 53.0;
  static const joinButtonHeight = 56.0;
  static const footerPillHeight = 37.0;
  static const userDisc = 64.0;
  static const primaryGradient = [CnColors.cmGreen, CnTw.blue700];

  // Register
  static const stepDisc = 36.0;
  static const strengthSegment = 6.0;
  static const usernameDebounce = Duration(milliseconds: 500);
  static const emailDebounce = Duration(milliseconds: 600);
  static const poorAccuracyMeters = 50000.0;

  // Onboarding
  static const transcriptHeight = 288.0;
  static const swipeMinDx = 60.0;
  static const swipeRatio = 1.3;
  static const swipeMaxDuration = Duration(milliseconds: 800);
  static const cardFade = Duration(milliseconds: 500); // opacity only
  static const confettiCount = 120;
  static const confettiFrames = 250;
}
```
