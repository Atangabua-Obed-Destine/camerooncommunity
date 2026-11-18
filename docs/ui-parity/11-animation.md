# Pass 11 — Animation

The web moves in three ways. There are **38 CSS keyframe sets**: 27 in `app.css` and 11 in view
`<style>` blocks, all listed in `tokens.json` → `keyframes`. Passes 4 and the README say 26 and 12, but
the sources recorded in `tokens.json` give 27 and 11. Tailwind supplies **4 built-in
animations** (`animate-spin`, `-ping`, `-pulse`, `-bounce`). And **126 Alpine `x-transition`
elements** animate overlays and menus as they show and hide. On top of those there are 120
`transition:` declarations in `app.css`, and a handful of `requestAnimationFrame` loops.

This pass gives each one a Flutter equivalent. Timings are taken from the declarations. They
are not affected by the 1.25× text rule.

---

## 0. Two traps before the tables

**`ease-out` means two different curves.** In `app.css` it is the CSS keyword,
`Cubic(0, 0, 0.58, 1)`. Inside an `x-transition:enter="… ease-out …"` it is a **Tailwind
class**, and Tailwind v4 compiles it to `Cubic(0, 0, 0.2, 1)`. The same holds for `ease-in`
(CSS `0.42,0,1,1` against Tailwind `0.4,0,1,1`) and `ease-in-out` (CSS `0.42,0,0.58,1` against
Tailwind `0.4,0,0.2,1`). Port by **source**, never by name. The values come from
`public/build/assets/app-*.css`.

**A keyframe's easing applies per segment.** With stops at 0/50/100% and `ease-in-out`, *each
half* eases. A Flutter `CurvedAnimation` over the whole loop is wrong for every multi-stop set
here. Use a `TweenSequence` whose items each carry the curve (§6, `CnMotion.keyframes`).
`repeat(reverse: true)` is only equivalent for symmetric curves (`linear`, CSS `ease-in-out`).

Other CSS behaviours to copy. An `animation-delay` on an infinite loop applies **once**, so
staggered dots stay out of phase for good. Drive them from one controller with a phase offset
each, not one controller each. Fill modes matter too: `forwards` holds the last frame, and
`backwards` shows the first frame during the delay.

## 1. Keyframes in the app's screens

`file:line` is where the set is declared. The "used at" sites are the views that apply it.
Unless a row says otherwise, easing is the CSS keyword.

### GoConnect (12)

| Name | Declared | Used at | Timing | What it does | Flutter |
|---|---|---|---|---|---|
| `yard-tick-pulse` | app.css:1935 | `components/message-ticks.blade.php:16` (`.yard-tick--sending`) | 1.2s ease-in-out ∞ | opacity .5→1→.5 on the clock | `FadeTransition`, sequence 0.5→1→0.5, each half `easeInOut` |
| `yard-tick-blue-pop` | app.css:1939 | message-ticks:13 and :50 | 320ms ease-out ×1 | scale .85→1.12 (60%)→1 | `ScaleTransition`, sequence 60/40, `Curves.easeOut` per segment. **Once, not twice**, see Divergence 3 |
| `yard-typing-bounce` | app.css:3221 | `livewire/yard/chat-room.blade.php:1000` | 1.2s ease ∞, dots delayed 0/.2/.4s | 6px dots: opacity .3→1, y 0→−4px at 30%, rest 60–100% | one 1200ms controller, phase offsets 0/200/400ms, `Curves.ease` per segment |
| `yard-rec-blink` | app.css:3388 | chat-room:1342 (recorder dot) | 1s ease-in-out ∞ | opacity 1→.3→1 | `FadeTransition`, `easeInOut` halves |
| `spin` | app.css:3510 | `.yard-upload-spinner`, chat-room ×6 | **0.8s** linear ∞ | border ring rotates | `RotationTransition`, 800ms `repeat()` |
| `yard-msg-anchor-pulse` | app.css:1796 | added by JS at chat-room:50, removed after 1400ms | 1.4s ease-out ×1 | green ring `rgba(34,197,94,.45)` 0→6px at 35%→0 | `AnimatedBuilder` on a `BoxShadow` spread, 35/65 sequence |
| `previewSlideUp` | app.css:4539 | chat-room:1089 (media preview sheet) | **220ms ease** ×1 | y +20px→0, opacity 0→1 | `SlideTransition` + `FadeTransition`, `Curves.ease` |
| `previewFadeIn` | app.css:4719 | chat-room:1104 (image), :1110 (doc card) | 300ms ease ×1 | scale .92→1, fade in | `ScaleTransition` + fade, `Curves.ease` |
| `wa-toast-slide` | app.css:2672 | `livewire/yard/room-info.blade.php:15` (join-request banner) | 300ms ease-out ×1 | y −8px→0, fade in | slide + fade, `Curves.easeOut` |
| `yard-bubble-float` | app.css:1175 | `livewire/yard/room-list.blade.php:314–316` | 3.6s ease-in-out ∞, delays 0/1.2/2.4s | y 0→−6px, rotate 0→−4° | one 3600ms controller, three phases |
| `yardLocPulse` | app.css:6170 | room-list:98 (active-location dot) | 1.6s ease-in-out ∞ | scale 1→1.15, opacity 1→.7 | `ScaleTransition` + fade, `easeInOut` halves |
| `emoji-fall` | app.css:3512 | room-list:468, inline style built in JS | **random 2–4s** ease-out, delay i×.15s, forwards | 8 emoji, random 14–24px, at random x, fall −20→180px, rotate 45°, fade in at 20% and out | per-particle controller with the same random ranges |

### Calls (2, plus Tailwind `pulse`)

| Name | Declared | Used at | Timing | What it does | Flutter |
|---|---|---|---|---|---|
| `slideDown` | app.css:4925 | `livewire/yard/call-manager.blade.php:20` (incoming card) | 350ms ease-out ×1 | y −30px→0, fade in | slide + fade, `Curves.easeOut` |
| `ringPulse` | app.css:4932 | call-manager:32 (avatar ring, **1.5s**), :90–92 (outgoing rings, **2s**, delays 0/.5/1s) | ease-out ∞, no fill-mode | scale 1→1.9, opacity .4→0 | one controller per ring set, phase offsets. See Divergence 9 for the delay frames |
| `pulse` (Tailwind) | build | `.yard-call-btn--accept > svg` (1.5s ease-in-out), `.yard-call-modal__info-dot` (2s ease) | ∞ | opacity 1→.5 at 50% | fade sequence; note the **overridden** easings |

### Shell and global overlays (6)

| Name | Declared | Used at | Timing | What it does | Flutter |
|---|---|---|---|---|---|
| `nb-pulse` | notification-bell.blade.php:186 | bell:34 (`.nb-badge__pulse`) | 1.6s ease-out ∞ | red halo scale .6→1.6, opacity .8→0 by 80%, holds | 80/20 sequence, `easeOut` |
| `nb-shake` | bell:192 | bell:26, class on for 600ms per new notification | 500ms ease-out ×1 | rotate 0→−12→10→−6→4→0° | `RotationTransition`, 5 segments of 20% |
| `conn-badge-pulse` | `livewire/yard/connections-badge.blade.php:69` | badge:14/:20, mounted at `yard/index.blade.php:44,76,101` | 1.8s ease-out ∞ | halo scale .8→1.6, opacity .8→0 by 70% | 70/30 sequence |
| `kcSlideIn` | `components/connection-notifier.blade.php:164` | notifier:86 (`.kc-toast`) | 350ms `Cubic(.18,.89,.32,1.28)` both | x +40px→0, scale .95→1, fade in. Overshoots | slide + scale + fade with that `Cubic` (y > 1 is allowed) |
| `kamerTwinkle1`, `kamerTwinkle2` | app.css:253, :257 | `yard/partials/icon-sidebar.blade.php:14` | 2.4s ease-in-out ∞, second +1.2s | stars scale 1↔.6, rotate 72°, glow | **Desktop rail only** (hidden under 768px). Not ported |

### The Discover modal (7, all in `partials/discover-modal.blade.php`)

| Name | Declared | Used at | Timing | What it does | Flutter |
|---|---|---|---|---|---|
| `discoverGradient` | :277 | :34 hero | **12s ease** ∞ | 200% flag gradient pans 0→100→0% x | `AnimatedBuilder` moving `LinearGradient` begin/end |
| `orbFloat1/2/3` | :296–298 | :38–40 | 9s / 11s / 10s ease-in-out ∞ | 200/180/150px blurred orbs drift (60,40)/(−50,−30)/(−40,50)px, scale 1.15/1.1/1.2 | three controllers; `ImageFiltered` blur 40, `BlendMode.screen` |
| `sparkle` | :309 | :45, 14 sparkles, delay i×0.3s | 3s ease-in-out ∞ | 4px dots opacity 0→1, scale .5→1.4 | one controller, 14 phases |
| `discoverBounce` | :318 | :56 slide emoji | 2.5s ease-in-out ∞ | y 0/−8/−4px, rotate 0/−5/5° at 0/25/75% | 4-segment sequence |
| `cardIn` | :353 | :109/119/129 (0.6s, delay 0/100/200ms), :152 Soon cards (0.7s, idx×80ms) | `Cubic(.2,.9,.3,1.4)` backwards | y +20px→0, fade in, overshoot | staggered `Interval`s on one controller, that `Cubic` |

Slides cross-fade with `transition-all duration-700 ease-out`. They advance every 5000ms, and a
progress bar is updated by `setInterval`. Both are in JS, at `discover-modal.blade.php:52, 190, 244`.

### Auth, onboarding and the landing page (6)

| Name | Declared | Used at | Timing | Flutter |
|---|---|---|---|---|
| `fade-in` | app.css:79 | `livewire/onboarding/onboarding-flow.blade.php:73,96`, `home.blade.php:454` | 500ms ease-out both, opacity only | `FadeTransition`, `Curves.easeOut` ([06](06-auth-onboarding.md) finding 12) |
| `pulse-soft` | app.css:95 | `auth/login.blade.php:35,41`, register-wizard ×2, home ×9 | 2s ease-in-out ∞, opacity 1→.7 | fade sequence |
| `blink` | app.css:107 | home:232 (typewriter caret) | 0.7s **step-end** ∞ | opacity 1 then 0, no tween: `Threshold(0.5)` |
| `hero-line-draw` | app.css:113 | home ×11 | 1.5s ease-out forwards, `--delay` | landing only, **not in the app** |
| `hero-city-pop` | app.css:124 | home ×11 | 0.4s ease-out forwards, `--delay`+0.8s | landing only |
| `hero-progress-fill` | app.css:134 | home:396, :412 | 1.2s ease-out, 0.5s delay, forwards | landing only |

## 2. Unused and dead

- **Declared, never applied (5):** `fade-in-up`, `slide-in-left`, `slide-in-right` (their
  `.animate-*` classes appear in no view or JS), `count-up` (no selector at all; the landing's
  number count-up is JS, `resources/js/app.js:182`, ease-out cubic), and `skeleton-shimmer`
  (`.skeleton` is unused). The `[x-intersect] .stagger` delay rules (app.css:144) are unused
  too. **Do not port them.**
- **Dead block:** onboarding-flow:627–632 redefines `fade-in` (300ms, y +8px) inside
  `@push('styles')`, and no layout has `@stack('styles')` (`tokens.json` → `dead_style_blocks`).
  It renders as app.css's version.
- `livewire/yard/call-manager.blade.php.bak` has four `animate-ping` uses and is not a view.

## 3. Tailwind built-ins

| Class | Uses | Compiled | Where | Flutter |
|---|---|---|---|---|
| `animate-spin` | 21 | `spin 1s linear ∞` | every SVG spinner ([10](10-icons.md) §4) | `RotationTransition`, 1000ms |
| `animate-ping` | 12 | `ping 1s Cubic(0,0,.2,1) ∞`: scale→2, opacity→0 by 75% | location detection (register-wizard:371–373, location-tracker:45), onboarding:360 | 75/25 sequence |
| `animate-pulse` | 12 | `pulse 2s Cubic(.4,0,.6,1) ∞`: opacity .5 at 50% | skeleton cards (feed-browse:213), Kamer AI bubble dot (kamer-chat:10), room-list ×3 | fade sequence |
| `animate-bounce` | 6 | `bounce 1s ∞`: y −25%↔0, `Cubic(.8,0,1,1)` down, `Cubic(0,0,.2,1)` up | Kamer AI and onboarding typing dots, delays 0/150/300ms | 2-segment sequence, phases |

## 4. Alpine `x-transition`: 126 elements, 42 distinct recipes

Alpine 3.15's helper defaults are in `vendor/livewire/livewire/dist/livewire.esm.js:2375`. Enter is
**150ms**, leave **75ms**, `Cubic(0.4,0,0.2,1)`, scale from **0.95**, origin centre. `.duration.N`
sets **both** directions. `.opacity` alone fades without scaling, and **`.scale.N` alone
scales without fading.** Class recipes use the Tailwind curves from §0. A recipe with no
`leave` classes **hides instantly**.

| Pattern | Count | Recipe | Typical sites |
|---|---|---|---|
| Menu / inline reveal | **40** | bare `x-transition`: fade + scale .95, 150 in / 75 out | header account menu (`components/layouts/app.blade.php:186`), chat overflow and translate menus (chat-room:174, :206), FAB menu (`yard/index.blade.php:281`), emoji picker (chat-room:1264), typing row, call cards |
| Scrim / full-screen fade | **21** + 3 | `.opacity`: 150 / 75. The three class-based scrims use 200–300 `ease-out` in and 150–200 `ease-in` out | user-preview overlay (`partials/user-preview.blade.php:28`), lightbox (chat-room:1860), GoMarket filters scrim (feed-browse:62), poll overlays, `comm-backdrop`; Kamer AI scrim (kamer-chat:139) |
| Slower scrims | 12 | `.opacity.duration.300ms` (5) / `200ms` (3) / `150ms` (4), both directions | Discover scrim 300; drawer scrim (`partials/app-drawer.blade.php:22`) and media preview 200; message-action backdrop (chat-room:1588) and tooltips 150 |
| Pop without fade | 8 | `.scale.95` (3), `.scale.95.origin.center` (2), `.scale.90`, `.scale.80.origin.top`, `.scale.origin.top` | user-preview card (:34), communities and connections modals, forward modal (chat-room:1786), message context menu (chat-room:1593, from **80%**, top), room context menu (room-list:226) |
| Centred modal | 9 | in 300 `ease-out` (fade, y +32px, scale .95); out 200 `ease-in` (fade, scale .95). Variants: new-chat dialog 250/150 (y +16px), poll builder and voters 200 in only (y +8px), join preview 400 in only | Discover (`discover-modal.blade.php:19`), `yard/index.blade.php:426`, chat-room:1387/1470, room-list:436 |
| Side drawer / sheet | 5 | left drawer 200 `ease-out` / 150 `ease-in` from −100% x; GoMarket filters 300/200, default curve; Kamer AI and solidarity from +100% x, 300/200; location sheet 300 `ease-out` from y +32px, in only | app-drawer:27, feed-browse:65, `livewire/ai/kamer-chat.blade.php:15`, room-list:595 |
| Toast | 4 | yardToasts: 200 `ease-out` from x +16px with fade, out 150 fade. Location toast: 500 from x +120%, out 300 `ease-in`. Join banner: `.opacity`, out 300 with scale. `kc-toast`: undefined classes (Divergence 4) | `partials/toasts.blade.php:35`, `livewire/location-tracker.blade.php:23`, room-info:15, connection-notifier:29 |
| Small popover | 8 | 150 `ease-out` from y ±4px (+ scale .95 on the bell panel), out 100 or none. Attach menu 200/150 from y +16px, scale .95. Selection reactions scale .9 | bell panel (bell:41), seller popover, sidebar filters ×2, jump-to-latest (chat-room:979), attach menu (chat-room:1219), selection bar and reactions (chat-room:1700/1730) |
| Onboarding choreography | 15 | 300–700ms in only, delays 200–700ms, from scale 0–.75, rotations of 12° and −180° | `auth/onboarding.blade.php:23…257`, onboarding-flow:50–470, register-wizard:334 |

Of these 126, 19 are outside the app (admin, solidarity, the landing's `site-nav*`, `pwa-install`).

Elsewhere in `app.css`, `transition:` timings are: **150ms** 126 times, 200ms 21, 120ms 14,
100ms 11, and 300ms for the mobile panel slide (`Cubic(.4,0,.2,1)`, app.css:529, see
[01](01-layout-navigation.md)). The others are the tick colour at 240ms `ease-out`, the poll bar
at 450ms `Cubic(.22,1,.36,1)` (app.css:5626) and the voters bar at 400ms `ease` (app.css:5805).
Confetti is JS: 90 pieces over 2200ms for an accepted connection (connection-notifier:387, :415), and
120 particles in onboarding. The voice waveform is a canvas rAF loop (`resources/js/yard-chat.js:1436`).

## 5. Reduced motion

**The web has none.** There is no `prefers-reduced-motion` query in `app.css`, in the eight
view `<style>` blocks, in `resources/js` or in the built CSS, and no `motion-safe:` or
`motion-reduce:` utility in any view. A member with iOS Reduce Motion on gets every loop,
including the Discover modal's 14 sparkles, three blurred orbs and 12s gradient.

The web has no answer here, so the app should take the native one. Read
`MediaQuery.disableAnimationsOf(context)`, and on iOS also check
`PlatformDispatcher.instance.accessibilityFeatures.reduceMotion`. When either is true:

- infinite decorative loops stop on their rest frame (badges, orbs, sparkles, bubbles,
  twinkles, the gradient, rings);
- enter and leave collapse to a cross-fade of the same duration;
- spinners, the recording dot and the typing dots keep moving, because they carry state.

## 6. Divergences

> **Fixed on the web (2026-11-11):** reduced motion is honoured (`app.css` ends with a
> `prefers-reduced-motion` block; spinners keep turning); the read tick pops once, on the
> icon; and the connection toast's leave classes now have rules, so it slides out. The app
> should still use `MediaQuery.disableAnimationsOf` as described.

1. ***Code.*** **No reduced-motion handling anywhere** (§5). The app will diverge on purpose.
2. ***Code.*** **Two meanings of `ease-out`** (§0). `app.css` keyframes use CSS's
   `0,0,.58,1`. Tailwind classes in `x-transition` use `0,0,.2,1`. Mixing them up changes every
   overlay's feel.
3. ***Rendered.*** **The read tick pops twice, compounded.** The wrapper `.yard-tick--read`
   and the inserted SVG `.yard-tick__svg--read` both run `yard-tick-blue-pop`
   (`components/message-ticks.blade.php:13,50`; app.css:1930). The wrapper is `inline-flex`, so
   both transforms apply, and the rendered scale goes **.72→1.25→1**, not the .85→1.12→1 that
   [04 §2](04-shared-components.md) gives. The double pop is plainly unintended. Port the
   single pop.
4. ***Code.*** **The connection toast has no leave animation.** `kc-toast--enter`, `--leave`
   and their `-start`/`-end` classes (connection-notifier:32–37) are defined nowhere, so Alpine
   finds a zero duration and hides it at once. The enter is the `kcSlideIn` keyframe. [04
   §4](04-shared-components.md) says the leave mirrors the enter. It does not.
5. ***Rendered.*** **`.scale.N` overlays pop at full opacity** while their scrim fades. This
   applies to the user-preview card, the forward modal, both context menus, and the
   communities and connections modals. Copy it: a fade-in here looks unlike the web.
6. ***Code.*** **25 of 126 transitions define only an enter.** This is harmless for the
   onboarding reveals. But the poll builder and voters, the GoMarket sidebar filters, the seller
   popover, the join preview and the location sheet all vanish instantly on close.
7. ***Rendered.*** **Delayed loops without a fill-mode show their resting style during the
   delay.** Outgoing-call rings 2 and 3 sit visible at scale 1 for 0.5s and 1s before they
   first pulse (app.css:5016–5017). Typing dots 2 and 3 show at full opacity for 0.2s and 0.4s.
8. ***Code.*** **Five keyframe sets, the `.skeleton` rule and the stagger rules are dead**, and
   onboarding's `fade-in` override never ships (§2).
9. ***Code.*** **Spinners turn at two speeds.** `animate-spin` turns at 1s and
   `.yard-upload-spinner` at 0.8s, alongside the six spinner drawings in
   [10](10-icons.md). The app should have one `CnSpinner` with one speed, unless a screen's
   parity says otherwise.
10. ***Code.*** **`emoji-fall` is random** in duration (2–4s) and size (14–24px), so parity means
    the same distribution, not the same frames.

## 7. Dart for this pass

```dart
import 'package:flutter/widgets.dart';

/// Motion. See docs/ui-parity/11-animation.md.
abstract final class CnMotion {
  // ── Curves. CSS keywords (app.css keyframes and transitions) ──
  static const cssEase = Cubic(0.25, 0.1, 0.25, 1.0);        // == Curves.ease
  static const cssEaseIn = Cubic(0.42, 0.0, 1.0, 1.0);
  static const cssEaseOut = Cubic(0.0, 0.0, 0.58, 1.0);
  static const cssEaseInOut = Cubic(0.42, 0.0, 0.58, 1.0);
  // ── Tailwind v4 classes (x-transition:enter="… ease-out …"), from the build ──
  static const twEaseIn = Cubic(0.4, 0.0, 1.0, 1.0);
  static const twEaseOut = Cubic(0.0, 0.0, 0.2, 1.0);
  static const twEaseInOut = Cubic(0.4, 0.0, 0.2, 1.0);       // also Tailwind's default
  static const alpine = Cubic(0.4, 0.0, 0.2, 1.0);           // x-transition helper
  static const twPulse = Cubic(0.4, 0.0, 0.6, 1.0);
  static const twPing = Cubic(0.0, 0.0, 0.2, 1.0);
  static const twBounceDown = Cubic(0.8, 0.0, 1.0, 1.0);
  // ── Overshoots ──
  static const cardIn = Cubic(0.2, 0.9, 0.3, 1.4);           // Discover cards
  static const socialToast = Cubic(0.18, 0.89, 0.32, 1.28);  // kcSlideIn
  static const pollBar = Cubic(0.22, 1.0, 0.36, 1.0);

  // ── Overlays ──
  static const alpineIn = Duration(milliseconds: 150);
  static const alpineOut = Duration(milliseconds: 75);
  static const scrimSlow = Duration(milliseconds: 300);      // Discover
  static const scrimDrawer = Duration(milliseconds: 200);
  static const modalIn = Duration(milliseconds: 300);        // twEaseOut, y +32, scale .95
  static const modalOut = Duration(milliseconds: 200);       // twEaseIn
  static const drawerIn = Duration(milliseconds: 200);
  static const drawerOut = Duration(milliseconds: 150);
  static const sheetIn = Duration(milliseconds: 300);        // filters, Kamer AI
  static const sheetOut = Duration(milliseconds: 200);
  static const popoverIn = Duration(milliseconds: 150);
  static const popoverOut = Duration(milliseconds: 100);
  static const toastIn = Duration(milliseconds: 200);        // x +16
  static const toastOut = Duration(milliseconds: 150);
  static const panelSlide = Duration(milliseconds: 300);     // with twEaseInOut
  static const hover = Duration(milliseconds: 150);          // the commonest transition

  // ── Chat ──
  static const tickColour = Duration(milliseconds: 240);     // cssEaseOut
  static const tickPop = Duration(milliseconds: 320);        // .85 → 1.12 @60% → 1
  static const tickSending = Duration(milliseconds: 1200);
  static const typingLoop = Duration(milliseconds: 1200);
  static const typingStagger = Duration(milliseconds: 200);
  static const recBlink = Duration(milliseconds: 1000);
  static const anchorPulse = Duration(milliseconds: 1400);
  static const mediaSheetIn = Duration(milliseconds: 220);   // cssEase
  static const mediaFadeIn = Duration(milliseconds: 300);    // cssEase, scale .92
  static const bubbleFloat = Duration(milliseconds: 3600);
  static const bubbleStagger = Duration(milliseconds: 1200);
  static const locPulse = Duration(milliseconds: 1600);

  // ── Calls, badges, toasts ──
  static const callCardIn = Duration(milliseconds: 350);     // y -30
  static const callerRing = Duration(milliseconds: 1500);    // scale 1 → 1.9
  static const outgoingRing = Duration(milliseconds: 2000);
  static const outgoingRingStagger = Duration(milliseconds: 500);
  static const badgePulse = Duration(milliseconds: 1600);
  static const connBadgePulse = Duration(milliseconds: 1800);
  static const bellShake = Duration(milliseconds: 500);
  static const socialToastIn = Duration(milliseconds: 350);

  // ── Tailwind built-ins ──
  static const spin = Duration(milliseconds: 1000);          // linear
  static const ping = Duration(milliseconds: 1000);
  static const pulse = Duration(milliseconds: 2000);
  static const bounce = Duration(milliseconds: 1000);
  static const fadeIn = Duration(milliseconds: 500);         // onboarding, cssEaseOut

  /// CSS keyframes -> TweenSequence, easing applied per segment as CSS does.
  /// stops: [(0.0, 1.0), (0.5, 0.5), (1.0, 1.0)] with curve cssEaseInOut.
  static Animatable<double> keyframes(List<(double, double)> stops, Curve curve) =>
      TweenSequence([
        for (var i = 0; i < stops.length - 1; i++)
          TweenSequenceItem(
            tween: Tween(begin: stops[i].$2, end: stops[i + 1].$2)
                .chain(CurveTween(curve: curve)),
            weight: (stops[i + 1].$1 - stops[i].$1) * 100,
          ),
      ]);

  /// Respect iOS Reduce Motion: the web does not (11-animation.md §5).
  /// accessibilityFeatures.reduceMotion is iOS-only (Flutter 3.13+).
  static bool reduced(BuildContext context) =>
      MediaQuery.disableAnimationsOf(context) ||
      WidgetsBinding.instance.platformDispatcher.accessibilityFeatures.reduceMotion;
}
```

For a segment that holds still (`nb-pulse` from 80% to 100%), give equal begin and end values.
