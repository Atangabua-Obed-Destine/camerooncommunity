# Pass 10 — Icons

The web has no icon font and no sprite sheet. Every icon is an `<svg>` pasted inline into a
Blade view, **553 of them in 61 views**, mostly Heroicons copied by hand over two years and
three Heroicons releases. This pass turns them into an asset set the app can load, and records
where the copies disagree. Sizes are **phone px**. Unlike text, icon sizes are **not** scaled
by the 1.25× rule: `w-4 h-4` is 16px on every screen.

The per-icon inventory is generated: [icons.md](icons.md) (readable, grouped by module),
[icons.json](icons.json) (everything, with every use site), `icons/*.svg` (one file per unique
icon) and [generated/cn_icons.dart](generated/cn_icons.dart). This document is the narrative.

---

## 1. Method

```bash
php tools/ui-parity/icons.php        # about a minute, no npm, no network
```

For every `<svg>` in `resources/views/**/*.blade.php` (minus `welcome.blade.php`, as in
`extract.php`), the tool does five things.

1. **It strips the framework.** Classes, `x-*`, `:attr`, `@event`, `wire:*`, `aria-*` and `data-*`
   are dropped. The root paint is kept: `viewBox`, `fill` or `stroke`, `stroke-width`,
   caps and joins. A child's `opacity-NN` class is kept as an `opacity` attribute, because it
   is part of the drawing (see Divergence 6).
2. **It normalises geometry.** Every path is rewritten as absolute `M/L/C/Q/A/Z`. `H`/`V`
   become `L`, `S`/`T` become `C`/`Q` with the reflected control point, and relative commands
   become absolute. Circles, rects, lines and polylines become paths too. Two encodings of one
   shape then compare equal. That matters here: the same X is written three different ways
   across the views.
3. **It dedupes on geometry, not on text.** The SHA of the normalised geometry identifies an
   icon. Paint is not part of the key, so an X drawn at stroke 2 and the same X at 2.5 are one
   icon with two *paint variants*.
4. **It names by matching libraries.** The normalised geometry is compared, with a tolerance
   of 0.06 units, against Heroicons 2.2.0, 2.0.18 and 1.0.6, Lucide and Feather, in that order
   of preference. A v1 match takes its v2 name (`location-marker` becomes `map-pin`) and gets
   `-v1` only if a v2 copy already took the plain name. Matches are cached in
   `tools/ui-parity/icon-names.json` and keyed by hash. A normal run therefore needs no
   packages. Pass `--library=DIR` only to re-identify from scratch (see the tool's header).
5. **It works out the rendered size.** The order is: inline style, then a rule in `app.css`
   (unlayered, so it beats utilities: `.yard-call-btn > svg` turns a `w-6` icon into a 56px
   box whose 14px padding leaves the drawing 28px), then
   `w-`/`h-`/`size-` classes, then the `width`/`height` attributes. `sm:` and wider variants are
   ignored. 526 of 556 sizes come from classes and 23 from a CSS rule.

Where a paint attribute is a Blade ternary (`fill="{{ $on ? 'currentColor' : 'none' }}"`), it
counts as one occurrence per state. Brand art is anything that carries its own colours, a
gradient, a mask, text, or comes from `cameroon-ribbon`. It is kept whole and must never be
tinted.

## 2. Counts

| | |
|---|---|
| `<svg>` elements / icon occurrences | 553 / 556 (three ternaries expand to two) |
| Unique icons | **222**. 221 appear in the app's modules. `glyph-7d1c417930` is admin/solidarity only |
| By kind | **96 heroicon**, **102 custom**, **15 brand**, **7 feather**, **2 lucide** |
| Heroicon sets | 49 from 2.2.0 outline, 1 from 2.0.18 outline, 1 from 2.2.0 solid, **45 from 1.0.6** |
| Uses by set | v2 170, **v1 150**, custom 197, brand 19, lucide 12, feather 8 |
| Icons drawn at more than one stroke width | 49 icons, 262 of the 556 uses |
| Emoji standing in for icons | 204 glyphs, **791 uses** |

## 3. Conventions, as measured

**Sizes** (occurrences): 16px **179**, 20px **165**, 14px 47, 24px 36, 12px 34, 22px 26,
28px 17, 18px 8, 32px 8, 56px 7, 11px 5, and art at 64–384px or `full`. So there are two
working sizes. 16px sits inline with text and in small buttons. 20px sits in toolbar and header
buttons. 22px is the drawer's row icon ([pass 1](01-layout-navigation.md)) and 24/28px are the
tab row's.

Because text is scaled and icons are not, an icon is **smaller relative to its label on a phone
than on a desktop**. A `w-4` icon beside `text-sm` is 16px beside 17.5px on a phone and 16px
beside 14px on a desktop. Reproduce this. Do not "fix" it by scaling icons with the text scale.

**Paint**: 446 uses are stroked, 89 filled, 19 brand and 2 fill-and-stroke.

**Stroke widths** across stroked uses: **2** (256), 2.5 (60), 1.7 (34), 2.2 (34), 1.8 (20),
3 (18), 1.5 (15), 2.4 (6), 1 (2), and one each of 1.6, 2.75 and a 2/3.5 two-weight check. The
house weight is **2**, which is Heroicons v1's native weight. Heroicons v2's native 1.5 appears
only 15 times, so most v2 icons were pasted and then re-weighted by hand. 1.7 is the drawer's
weight. 2.5 is mostly X buttons and the v1 chevrons. 3 is the small v1 check and plus.
Caps and joins are `round` throughout, except where noted below.

## 4. Inconsistencies

**One meaning, several drawings.** This is a sample, not the complete list:

| Meaning | Drawings (uses) |
|---|---|
| Search | `search` (Lucide, 11), `glyph-37a9370f1c` (Feather-style, 6), `magnifying-glass-v1` (2), `magnifying-glass` (1) |
| Check | `check-v1` (13), `check` (7), `check-lucide` (1), `glyph-48ae4e777f` (Material "done", filled, 3), `glyph-6c5237f34c` (circled, 1) |
| Loading | four SVG spinners (`glyph-fda6700327` 8, `glyph-10a2b3858d` 5, `glyph-bc480836c1` 3, `glyph-00012eddda` 1), a spun half `arrow-path` (`glyph-1341fb55fd`, 4), and a CSS border ring, `.yard-upload-spinner` (6) |
| Chat | `chat-bubble-oval-left-ellipsis` (v1 `chat`, 6), `glyph-9e804e1122` (v2 bubble, 3), `glyph-5429d962e9` (Material filled bubble, 10) |
| Location | `map-pin` (v1 outline, 10), `glyph-159028da77` (Material filled "place", 6), and 📍 as text (26) |
| Star | `glyph-2f11fdaa97` (Material filled, 5), ⭐ emoji (14), ★ text symbol (14) |
| Close | `x-mark` (38), `x-mark-2` (same lines, butt joins, 3), ✕ text symbol (22) |

**Heroicons v1 and v2 mixed.** There are 20 meanings with both a v1 and a v2 copy: `arrow-left`,
`arrow-path`, `arrow-right`, `bars-3`, `bookmark`, `check`, `chevron-left`, `chevron-right`,
`clock`, `cube`, `envelope`, `eye`, `language`, `lock-closed`, `magnifying-glass`, `plus`,
`shopping-cart`, `trash`, `user-group` and `user`. The v1 copy is the more used one for the
chevrons (17 and 13 uses against 5 and 3), `check` (13 against 7) and `plus` (10 against 4).
The two generations differ in visible ways. v1 is a 24-grid drawn for weight 2, and its
chevrons are shorter. v2 is drawn for 1.5. Both are shipped as separate assets, named `-v1`.

**The same icon at different weights.** `x-mark` appears at 2 (24 uses), 2.5 (12) and 2.2 (2).
`user-group` appears at five weights in five uses (1, 1.7, 1.8, 2, 2.5). `check` appears at
four. Each SVG carries its **most common** weight, and `icons.json` lists the others with their
sites under `paint_variants`. `CnIcon.strokeWidth` (§7) exists for the sites that differ.

**Material glyphs posing as custom.** The filled customs at the top of the list are Google
Material Design icons: `glyph-5429d962e9` is `chat`, `8abb1dcccd` is `push_pin`, `159028da77`
is `place`, `2f11fdaa97` is `star`, `de371837ce` is `send` and `48ae4e777f` is `done`. The
name cache was built without a Material package, so they are unnamed. They are filled, not
stroked, so they look heavier than the outline set around them.

**Dynamic paths.** Four `<svg>`s have Blade expressions for geometry. The drawer's "Soon" rows
(`partials/app-drawer.blade.php:93`, `d="{{ $path }}"`) resolve from the four string literals in
the same file: `heart`, `cube`, `truck` and `glyph-c546142a10`. The other three
(`livewire/yard/room-list.blade.php:39–41`) are written into `$filterIcons`, which is never
output. They are dead, so they were skipped.

**Emoji as icons.** There are 791 uses: 230 are an element's whole text, 199 lead a label, 179
are inline, 119 are in pickers or flag lists, and 64 are PHP `'icon' =>` values. The most used
are 💬 38, 🇨🇲 33, → 32, 🤖 30, 📍 26, ✓ 26, ✕ 22 and 🎉 20. How they render depends on the
glyph:

- **Colour emoji** come from the stack's `'Apple Color Emoji'` on an iPhone. Flutter on iOS
  falls back to the same font, so they match. **They do not match in `shoot.mjs` screenshots**,
  which render on Windows Chrome. There, emoji are Segoe UI Emoji and 🇨🇲 🇬🇧 🇫🇷 show as the
  letters "CM", "GB" and "FR". Never use a Windows screenshot as the reference for an emoji.
- **Text symbols** (→ ← ↑ ↓ ↩ ✓ ✕ ★ ⏸) take the text colour. ↑ and ↓ are in Inter's latin
  subset, which Bunny Fonts serves. → ← ✓ ✕ ★ are not, so iOS Safari draws them from a system
  fallback. A Flutter app that bundles the **full** Inter would draw them in Inter instead, and
  they would look different. Check them against an iPhone, or replace them with SVGs.
- ⭐ ✨ ✅ ❌ ⏳ ⏰ ✋ ❓ have Emoji_Presentation and render as colour emoji even without
  U+FE0F. (The generator classed them as text symbols until this pass. That is fixed.)

## 5. The 102 unnamed customs

102 drawings matched no library. They cover 197 uses, and 64 of them are used once. The
filenames are geometry hashes on purpose: a hash is stable across runs, and a guessed name is
not. **Do not rename the files.** To name one, add its hash to `MANUAL_NAMES` in `icons.php`
and re-run, and the SVG, the JSON and the Dart follow. Names suggested from the use sites:

| Hash | Uses | What it is, and where | Suggested name |
|---|---|---|---|
| `5429d962e9` | 10 | Material filled chat bubble: GoMarket "Message seller" | `chat-bubble-solid` |
| `fda6700327` | 8 | Tailwind spinner (ring 25%, arc 75%): GoMarket loading | `spinner` |
| `8abb1dcccd` | 7 | Material push-pin: pinned message, the Pin action | `pin-solid` |
| `159028da77` | 6 | Material filled place marker: GoMarket radius and location | `map-pin-solid` |
| `37a9370f1c` | 6 | Search glass, r=8: GoMarket search fields | `search-market` |
| `10a2b3858d` | 5 | Spinner, quarter-disc arc: register-wizard buttons | `spinner-wizard` |
| `2f11fdaa97` | 5 | Material filled star: starred messages | `star-solid` |
| `de371837ce` | 5 | Material send plane: chat and dock Send | `send-solid` |
| `1341fb55fd` | 4 | Half of `arrow-path`, spun while `wire:loading`: add members, block, unblock | `spinner-arrows` |
| `828d0583d6` | 4 | Heroicons v2 pencil, older coordinates: nickname, Edit cover | `pencil` |
| `b9ec628a19` | 4 | Heroicons 2.0 user-plus variant: Connect | `user-plus-alt` |
| `c5ddb4b7d5` | 4 | Price tag: Offers | `tag` |
| `dbd26b424d` | 4 | Folded-corner document: Saved searches | `document-saved` |
| `f2fa3bc463` | 4 | Three rising bars: polls | `poll` |
| `488b2e2da7` | 3 | Storefront: the GoMarket tab and its notification | `storefront` |
| `e49f651df0` | 3 | Rounded filled house: the Home tab | `home-solid` |
| `c546142a10` | 3 | Briefcase: GoPartner (Soon) | `briefcase` |
| `c47cf9163e` | 3 | Single four-point sparkle: Kamer AI | `sparkle` |
| `bc480836c1` | 3 | Spinner, fuller arc: onboarding and Coming Soon | `spinner-round` |
| `abace69f71` | 3 | v1 `user-group` without the side heads: member counts | `user-group-compact` |

Recommendation: name these 20 and leave the rest as hashes until a screen needs them. In the
app, collapse the six loading idioms into **one** `CnSpinner` (the `fda6700327` drawing, which
is the commonest), unless parity on a specific screen says otherwise.

## 6. Divergences

1. ***Code.*** **The six loading indicators** described in §4 are different drawings at
   different weights. Examples are `livewire/marketplace/feed-browse.blade.php:259`,
   `livewire/auth/register-wizard.blade.php:525` and `livewire/yard/room-info.blade.php:130`,
   and the CSS ring is at `resources/css/app.css:3502`.
2. ***Code.*** **Heroicons v1 and v2 are mixed in 20 meanings.** v1 is the more used copy of the
   chevrons, `check` and `plus`. One example is `chevron-left-v1` in
   `partials/discover-modal.blade.php:75` against v2 `chevron-left` on the profile pages
   (`profile/index.blade.php:18`).
3. ***Code.*** **The same icon is drawn at up to five stroke widths.** This affects 49 icons and
   262 uses. `x-mark` alone is at 2, 2.2 and 2.5.
4. ***Rendered.*** **Filled Material glyphs sit among outline Heroicons**: 6 of the 8 most-used
   customs (§4). The GoMarket "Message seller" bubble is filled
   (`livewire/marketplace/listing-detail.blade.php:196`), while the drawer's chat icon is
   outline.
5. ***Rendered.*** **Icons do not follow the 1.25× text scale**, so icon-to-text proportions
   differ between phone and desktop. This is intended, and the app must copy it.
6. ***Code.*** **The generator dropped child `opacity-25`/`opacity-75` classes**, so all three
   Tailwind spinners came out as solid rings with an invisible arc. **Fixed in this pass**
   (`tools/ui-parity/icons.php`, `parseSvgBody`). Five drawings now carry `opacity`.
7. ***Code.*** **Emoji were misclassed.** The generator marked ⭐ ✨ ✅ ❌ ⏳ ⏰ ✋ ❓ as text
   symbols, and a glyph's class was taken from its last occurrence. **Fixed in this pass**
   (`EMOJI_PRESENTATION_SYMBOLS`). Nine text symbols remain.
8. ***Rendered.*** **Flags and emoji differ in Windows screenshots** (§4). This is a trap for
   the method, not a defect of the web.
9. ***Code.*** **Three dead filter icons** at `livewire/yard/room-list.blade.php:39–41` are
   built into `$filterIcons` and never output.

## 7. Dart for this pass

Copy `docs/ui-parity/icons/` to `assets/icons/`, list the folder in `pubspec.yaml`, and add
`flutter_svg`. `CnIcons` in [generated/cn_icons.dart](generated/cn_icons.dart) holds the asset
paths. Each path's doc comment gives the source, weight, sizes and use count.
**Never use `Icons.*`.** Material's drawings differ from these at every size.

```dart
import 'package:flutter/services.dart' show rootBundle;
import 'package:flutter/widgets.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Icon sizes as measured (phone px). Not scaled with text.
abstract final class CnIconSize {
  static const xs = 12.0, sm = 14.0, md = 16.0, lg = 20.0;
  static const drawer = 22.0, tab = 24.0, tabWide = 28.0;
  static const callButton = 28.0;      // drawn size in .yard-call-btn's 56px circle
}

/// One-colour icon from CnIcons. `color` tints via srcIn, which keeps each
/// element's alpha, so the spinner's 25% ring and 75% arc survive. Brand art
/// (CnIcons.brand*) must pass `tint: false`.
class CnIcon extends StatelessWidget {
  const CnIcon(this.asset, {super.key, this.size = CnIconSize.md, this.color,
      this.strokeWidth, this.tint = true, this.semanticLabel});

  final String asset;
  final double size;
  final Color? color;            // defaults to the ambient text colour, like currentColor
  final double? strokeWidth;     // only for a site listed under paint_variants
  final bool tint;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final c = color ?? DefaultTextStyle.of(context).style.color ?? const Color(0xFF0F172A);
    final filter = tint ? ColorFilter.mode(c, BlendMode.srcIn) : null;
    if (strokeWidth == null) {
      return SvgPicture.asset(asset, width: size, height: size,
          colorFilter: filter, semanticsLabel: semanticLabel);
    }
    // Re-weight: every stroke-width in a one-colour icon is the icon's weight.
    return FutureBuilder<String>(
      future: rootBundle.loadString(asset),
      builder: (_, s) => s.hasData
          ? SvgPicture.string(
              s.data!.replaceAll(RegExp(r'stroke-width="[^"]*"'),
                  'stroke-width="$strokeWidth"'),
              width: size, height: size, colorFilter: filter,
              semanticsLabel: semanticLabel)
          : SizedBox.square(dimension: size),
    );
  }
}

// Usage — the header bell, the composer's X at its 2.5 site, a spinner:
//   CnIcon(CnIcons.bell, size: CnIconSize.lg, color: Colors.white)
//   CnIcon(CnIcons.xMark, size: CnIconSize.md, strokeWidth: 2.5)
//   RotationTransition(turns: _spin,          // 1s linear, repeat — see 11-animation.md
//       child: CnIcon(CnIcons.glyphFda6700327, size: CnIconSize.lg))
```

`glyph-6c5237f34c` mixes two weights (2 and 3.5), so do not pass `strokeWidth` to it. Cache
the re-weighted strings if a list draws many of them. Only 49 icons ever need it.
