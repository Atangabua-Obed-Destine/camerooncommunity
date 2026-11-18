<?php

/**
 * Read the web app's stylesheet and write down what the mobile UI actually is.
 *
 * The Flutter app has to match the web pixel for pixel, and the web's values
 * live in 7,319 lines of hand-written CSS plus Tailwind utilities scattered
 * through 104 Blade views. Transcribing that by hand would be wrong somewhere
 * and stale within a week, so it is derived instead: run this after changing
 * the UI and the spec follows.
 *
 * The one thing you cannot read off the base rules: inside `body.cn-app` on a
 * phone, every font size is 1.25x larger than the desktop value. A naive
 * reading of `text-sm` gives 14px where the phone actually renders 17.5px, so
 * the whole app would come out a quarter too small. That override block is
 * parsed and applied here rather than left for a human to remember.
 *
 * Usage:  php tools/ui-parity/extract.php
 * Writes: docs/ui-parity/tokens.json   (machine-readable, feeds the other docs
 *                                       and the visual reference page)
 *         docs/ui-parity/tokens.md     (human-readable)
 */

const ROOT = __DIR__ . '/../..';
const CSS = ROOT . '/resources/css/app.css';
const OUT_DIR = ROOT . '/docs/ui-parity';

/**
 * app.css is not the whole stylesheet.
 *
 * These views carry their own <style> blocks — 509 lines and 12 keyframe
 * sets between them — and everything in them was invisible to this script
 * until it read them too: the notification badge, the connection toast, the
 * connections badge, the Discover modal, the story strip. They are listed
 * rather than globbed so that a new one is a deliberate addition.
 *
 * resources/views/welcome.blade.php is deliberately absent: it is the stock
 * Laravel starter page, no route renders it, and its 247 CSS blocks are not
 * this product's.
 */
/**
 * Where Tailwind's own palette actually lives.
 *
 * app.css only redefines slate; every other colour comes from Tailwind's
 * defaults, which v4 writes as OKLCH and only resolves at build time. The
 * compiled bundle is therefore the only place the real values exist, and the
 * avatar palette needs 36 of them.
 */
const BUILD_CSS_GLOB = ROOT . '/public/build/assets/app-*.css';
const AVATAR_PALETTE_PHP = ROOT . '/app/Support/AvatarPalette.php';

const INLINE_STYLE_VIEWS = [
    'components/connection-notifier.blade.php',
    'livewire/notifications/notification-bell.blade.php',
    'livewire/onboarding/onboarding-flow.blade.php',
    'livewire/yard/connections-badge.blade.php',
    'partials/discover-modal.blade.php',
    'partials/home-stories.blade.php',
    'partials/pwa-head.blade.php',
    'pwa/offline.blade.php',
];

// Phones are anything the stylesheet narrows to. Tablet- and desktop-only
// blocks are deliberately skipped: v1 mirrors the mobile rendering only.
const MOBILE_MEDIA = [
    '(max-width: 1023px)',
    '(max-width: 767px)',
    '(max-width: 640px)',
    '(max-width: 480px)',
    '(hover: none), (max-width: 767px)',
    '(hover: none) and (pointer: coarse), (max-width: 767px)',
];

/** Tailwind's own defaults, which the mobile block overrides. */
const TW_BASE_TEXT = [
    'text-xs' => 0.75, 'text-sm' => 0.875, 'text-base' => 1.0,
    'text-lg' => 1.125, 'text-xl' => 1.25, 'text-2xl' => 1.5,
    'text-3xl' => 1.875, 'text-4xl' => 2.25, 'text-5xl' => 3.0,
];

// ─────────────────────────────────────────────────────────────────────────────

/**
 * Flatten the stylesheet into a list of rules, each tagged with the media
 * query it sits inside.
 *
 * A real CSS parser is unnecessary here: this file nests at most one level
 * (@media wrapping plain rules), so tracking brace depth is enough. Comments
 * are stripped first, but section headers are kept separately because they are
 * the only labelling the components have.
 *
 * @return array{rules: list<array{media: ?string, selector: string, decls: array<string,string>}>, sections: array<int,string>}
 */
function parseCss(string $css): array
{
    $sections = [];

    // Keep the "/* ── Name ── */" headers before discarding comments: they are
    // how the stylesheet groups itself, and the docs reuse that grouping.
    if (preg_match_all('#/\*+\s*(?:[─=]+\s*)?(.+?)\s*(?:[─=]+\s*)?\*+/#s', $css, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $i => $cap) {
            $line = substr_count(substr($css, 0, $m[0][$i][1]), "\n") + 1;
            $title = trim(preg_replace('/\s+/', ' ', $cap[0]));
            if ($title !== '' && mb_strlen($title) < 90) {
                $sections[$line] = $title;
            }
        }
    }

    $clean = preg_replace('#/\*.*?\*/#s', '', $css);

    $rules = [];
    $media = null;
    $depth = 0;
    $buffer = '';
    $len = strlen($clean);

    for ($i = 0; $i < $len; $i++) {
        $ch = $clean[$i];

        if ($ch === '{') {
            $head = trim($buffer);
            $buffer = '';
            $depth++;

            if ($depth === 1) {
                // Either an at-rule wrapper or a top-level rule.
                if (str_starts_with($head, '@media')) {
                    $media = trim(substr($head, 6));
                    continue;
                }
                $pendingSelector = $head;
                // Read the declaration block directly.
                [$decls, $i] = readDecls($clean, $i + 1);
                $depth--;
                if (! str_starts_with($pendingSelector, '@')) {
                    $rules[] = ['media' => null, 'selector' => $pendingSelector, 'decls' => $decls];
                }
                continue;
            }

            if ($depth === 2 && $media !== null) {
                [$decls, $i] = readDecls($clean, $i + 1);
                $depth--;
                if (! str_starts_with($head, '@')) {
                    $rules[] = ['media' => $media, 'selector' => $head, 'decls' => $decls];
                }
                continue;
            }

            continue;
        }

        if ($ch === '}') {
            $depth--;
            $buffer = '';
            if ($depth <= 0) {
                $depth = 0;
                $media = null;
            }
            continue;
        }

        $buffer .= $ch;
    }

    return ['rules' => $rules, 'sections' => $sections];
}

/**
 * Read declarations from $from until the matching close brace.
 *
 * @return array{0: array<string,string>, 1: int}  the declarations, and the index of the closing brace
 */
function readDecls(string $s, int $from): array
{
    $depth = 1;
    $out = '';
    $i = $from;

    for ($len = strlen($s); $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '{') { $depth++; }
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) { break; }
        }
        $out .= $ch;
    }

    $decls = [];
    foreach (explode(';', $out) as $part) {
        $part = trim($part);
        if ($part === '' || ! str_contains($part, ':')) { continue; }
        [$prop, $val] = explode(':', $part, 2);
        $decls[trim($prop)] = trim($val);
    }

    return [$decls, $i];
}

function isMobile(?string $media): bool
{
    return $media !== null && in_array($media, MOBILE_MEDIA, true);
}

/** CSS length -> px, assuming the 16px root this app never changes. */
function toPx(string $v): ?float
{
    $v = trim($v);
    if (preg_match('/^(-?[\d.]+)rem$/', $v, $m)) { return ((float) $m[1]) * 16; }
    if (preg_match('/^(-?[\d.]+)px$/', $v, $m)) { return (float) $m[1]; }
    if (preg_match('/^(-?[\d.]+)em$/', $v, $m)) { return ((float) $m[1]) * 16; }
    return null;
}

function px(?float $n): string
{
    if ($n === null) { return '—'; }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') . 'px';
}

/**
 * OKLCH to an sRGB hex, the way a non-P3 screen shows it.
 *
 * OKLab -> LMS -> linear sRGB -> gamma, clamped. Out-of-gamut colours clip
 * per channel, which is what the browser does too.
 */
function oklchToHex(float $lPct, float $c, float $hDeg): string
{
    $l = $lPct / 100;
    $h = deg2rad($hDeg);
    $a = $c * cos($h);
    $b = $c * sin($h);

    $lCube = ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $mCube = ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $sCube = ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

    $channels = [
        4.0767416621 * $lCube - 3.3077115913 * $mCube + 0.2309699292 * $sCube,
        -1.2684380046 * $lCube + 2.6097574011 * $mCube - 0.3413193965 * $sCube,
        -0.0041960863 * $lCube - 0.7034186147 * $mCube + 1.7076147010 * $sCube,
    ];

    $out = '';
    foreach ($channels as $v) {
        $v = $v <= 0.0031308 ? 12.92 * $v : 1.055 * ($v ** (1 / 2.4)) - 0.055;
        $out .= sprintf('%02x', (int) max(0, min(255, round($v * 255))));
    }

    return '#' . $out;
}

/** Every `--color-NAME: VALUE` the compiled bundle defines, as hex. */
function tailwindPalette(): array
{
    $matches = glob(BUILD_CSS_GLOB);
    if (! $matches) { return []; }

    $css = file_get_contents($matches[0]);
    if ($css === false) { return []; }

    preg_match_all('/--color-([a-z0-9-]+):\s*([^;}]+)/i', $css, $m, PREG_SET_ORDER);

    $out = [];
    foreach ($m as $row) {
        $name = $row[1];
        $value = trim($row[2]);

        if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            $out[$name] = strtolower($value);
            continue;
        }
        // The bundle minifies black and white to #000 / #fff.
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $h3)) {
            $out[$name] = strtolower('#' . $h3[1] . $h3[1] . $h3[2] . $h3[2] . $h3[3] . $h3[3]);
            continue;
        }
        if (preg_match('/^oklch\(\s*([\d.]+)%\s+([\d.]+)\s+([\d.]+)/i', $value, $k)) {
            $out[$name] = oklchToHex((float) $k[1], (float) $k[2], (float) $k[3]);
        }
    }

    return $out;
}

/**
 * The 18 avatar gradients, in the order AvatarPalette declares them.
 *
 * The order is load-bearing: a colour is picked with crc32($seed) % 18, so
 * reordering the list repaints every member in the product.
 */
function avatarPalette(array $tw): array
{
    if (! is_file(AVATAR_PALETTE_PHP)) { return []; }

    $src = file_get_contents(AVATAR_PALETTE_PHP);
    preg_match_all("/'bg-gradient-to-br from-([a-z0-9-]+) to-([a-z0-9-]+)'/i", $src, $m, PREG_SET_ORDER);

    $out = [];
    foreach ($m as $i => $row) {
        $out[] = [
            'index' => $i,
            'from' => $row[1],
            'to' => $row[2],
            'from_hex' => $tw[$row[1]] ?? null,
            'to_hex' => $tw[$row[2]] ?? null,
        ];
    }

    return $out;
}
// ─────────────────────────────────────────────────────────────────────────────

$css = file_get_contents(CSS);
if ($css === false) {
    fwrite(STDERR, "Cannot read " . CSS . "\n");
    exit(1);
}

$parsed = parseCss($css);
$rules = $parsed['rules'];

// ── Brand tokens, straight out of @theme ────────────────────────────────────
$colors = [];
$fontSans = null;

foreach ($rules as $r) {
    if ($r['selector'] !== '@theme' && $r['selector'] !== '') { /* fallthrough */ }
}

// @theme is an at-rule block, so pull it out of the raw text instead.
if (preg_match('/@theme\s*\{(.*?)\n\}/s', $css, $m)) {
    foreach (explode(';', $m[1]) as $line) {
        $line = trim(preg_replace('#/\*.*?\*/#s', '', $line));
        if (! str_contains($line, ':')) { continue; }
        [$k, $v] = explode(':', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (str_starts_with($k, '--color-')) {
            $colors[substr($k, 8)] = $v;
        } elseif ($k === '--font-sans') {
            $fontSans = $v;
        }
    }
}

// ── The mobile type scale, and every component font size on a phone ─────────
$textScale = [];      // text-xs ... text-3xl  => px on a phone
$arbitrary = [];      // text-[11px]           => px on a phone
$componentFont = [];  // .yard-msg__bubble     => px on a phone

foreach ($rules as $r) {
    if (! isMobile($r['media'])) { continue; }

    // body.cn-app { --text-sm: 1.0938rem; ... }
    if (str_contains($r['selector'], 'body.cn-app')) {
        foreach ($r['decls'] as $prop => $val) {
            if (str_starts_with($prop, '--text-')) {
                $textScale['text-' . substr($prop, 7)] = toPx($val);
            }
        }
        continue;
    }

    if (! isset($r['decls']['font-size'])) { continue; }

    $size = toPx($r['decls']['font-size']);
    if ($size === null) { continue; }

    foreach (explode(',', $r['selector']) as $sel) {
        $sel = trim($sel);

        // .cn-app .text-\[11px\]
        if (preg_match('/\.text-\\\\\[(\d+)px\\\\\]/', $sel, $m)) {
            $arbitrary['text-[' . $m[1] . 'px]'] = $size;
            continue;
        }

        // .cn-app .yard-msg__bubble  ->  .yard-msg__bubble
        $sel = preg_replace('/^\.cn-app\s+/', '', $sel);
        if (preg_match('/^\.([a-z0-9_-]+)/i', $sel, $m)) {
            $componentFont[$sel] = $size;
        }
    }
}

ksort($arbitrary, SORT_NATURAL);
ksort($componentFont);

// ── Geometry that is not font-size: radii, heights, spacing actually used ───
$radii = [];
$heights = [];

foreach ($rules as $r) {
    if ($r['media'] !== null && ! isMobile($r['media'])) { continue; }
    foreach (['border-radius' => &$radii, 'height' => &$heights] as $prop => &$bag) {
        if (! isset($r['decls'][$prop])) { continue; }
        $v = $r['decls'][$prop];
        $n = toPx($v);
        if ($n !== null) { $bag[$v] = ($bag[$v] ?? 0) + 1; }
    }
    unset($bag);
}

arsort($radii);
arsort($heights);

// ── The CSS that does not live in app.css ──────────────────────────────────
//
// Colours and animations only. Font sizes are left alone on purpose: these
// blocks sit outside any media query, so the phone renders them at the
// declared size and there is no override to record.

$knownColors = array_map('strtolower', array_values($colors));
$inlineColors = [];   // #2c4a3e => ['notification-bell', ...]
$keyframes = [];      // name    => [source file, ...] — some names are declared twice
$deadStyles = [];     // views whose <style> is pushed to a stack nothing outputs

if (preg_match_all('/@keyframes\s+([a-zA-Z0-9_-]+)/', file_get_contents(CSS), $m)) {
    foreach ($m[1] as $name) { $keyframes[$name][] = 'resources/css/app.css'; }
}

// A <style> inside @push('styles') only renders if some layout has
// @stack('styles'). None does today, so those blocks are dead code and must
// not be described as part of the design.
$stylesStackRendered = false;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/resources/views', FilesystemIterator::SKIP_DOTS)) as $view) {
    if (str_ends_with($view->getFilename(), '.blade.php')
        && preg_match("/@stack\(\s*['\"]styles['\"]/", file_get_contents($view->getPathname()))) {
        $stylesStackRendered = true;
        break;
    }
}

foreach (INLINE_STYLE_VIEWS as $rel) {
    $path = ROOT . '/resources/views/' . $rel;
    if (! is_file($path)) { continue; }

    $source = file_get_contents($path);
    if (! $stylesStackRendered) {
        $stripped = preg_replace("/@push\(\s*['\"]styles['\"]\s*\).*?@endpush/s", '', $source);
        if ($stripped !== $source) {
            $deadStyles[] = $rel;
            $source = $stripped;
        }
    }

    if (! preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $source, $m)) {
        continue;
    }
    $blocks = implode("\n", $m[1]);
    $short = basename($rel, '.blade.php');

    foreach (['/@keyframes\s+([a-zA-Z0-9_-]+)/'] as $re) {
        if (preg_match_all($re, $blocks, $k)) {
            foreach ($k[1] as $name) { $keyframes[$name][] = $rel; }
        }
    }

    if (preg_match_all('/#[0-9a-fA-F]{6}\b/', $blocks, $h)) {
        foreach ($h[0] as $hex) {
            $hex = strtolower($hex);
            // #fff and the token palette are not news.
            if (in_array($hex, $knownColors, true)) { continue; }
            if (! isset($inlineColors[$hex])) { $inlineColors[$hex] = []; }
            if (! in_array($short, $inlineColors[$hex], true)) {
                $inlineColors[$hex][] = $short;
            }
        }
    }
}

ksort($inlineColors);
ksort($keyframes);

// ── The avatar palette, resolved out of the compiled bundle ────────────────
$tailwind = tailwindPalette();
$avatarPalette = avatarPalette($tailwind);
// ── Write it out ────────────────────────────────────────────────────────────
if (! is_dir(OUT_DIR)) { mkdir(OUT_DIR, 0777, true); }

$payload = [
    'generated_from' => 'resources/css/app.css',
    'generated_at' => date('c'),
    'note' => 'Generated by tools/ui-parity/extract.php. Do not edit by hand.',
    'root_font_px' => 16,
    'mobile_text_multiplier' => 1.25,
    'font_family' => $fontSans,
    'colors' => $colors,
    'text_scale_mobile_px' => $textScale,
    'text_scale_desktop_px' => array_map(fn ($rem) => $rem * 16, TW_BASE_TEXT),
    'arbitrary_text_mobile_px' => $arbitrary,
    'component_font_mobile_px' => $componentFont,
    'border_radius_frequency' => $radii,
    'height_frequency' => $heights,

    // Colours declared in a view's own <style> block that are not in the
    // @theme palette. Each lists the views it appears in.
    'inline_style_colors' => $inlineColors,

    // Every animation in the product, and where it is declared.
    'keyframes' => $keyframes,
    'dead_style_blocks' => $deadStyles,

    'inline_style_views' => INLINE_STYLE_VIEWS,

    // The 18 default-avatar gradients, in declaration order, resolved from
    // the compiled bundle because Tailwind v4 keeps them in OKLCH.
    'avatar_palette' => $avatarPalette,

    // Every colour the compiled bundle defines, resolved to sRGB. The views
    // reach far past the brand tokens (blue-700, amber-50, indigo-700...), and
    // a hex typed from memory is Tailwind v3's, not the v4 OKLCH value shipped.
    'tailwind_palette' => $tailwind,

    'counts' => [
        'css_rules_parsed' => count($rules),
        'mobile_component_font_overrides' => count($componentFont),
        'arbitrary_text_overrides' => count($arbitrary),
        'keyframe_sets' => count($keyframes),
        'inline_style_colors' => count($inlineColors),
        'avatar_gradients' => count($avatarPalette),
    ],
];

file_put_contents(
    OUT_DIR . '/tokens.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

// ── The readable version ────────────────────────────────────────────────────
$md = [];
$md[] = '# Design tokens, as the phone actually renders them';
$md[] = '';
$md[] = '<!-- Generated by tools/ui-parity/extract.php from resources/css/app.css.';
$md[] = '     Do not edit by hand: re-run the script. -->';
$md[] = '';
$md[] = 'Every value here is read out of `resources/css/app.css`, not estimated. Sizes are';
$md[] = 'in px at the 16px root this app never changes.';
$md[] = '';
$md[] = '## The thing to get right first';
$md[] = '';
$md[] = 'Inside the signed-in shell (`body.cn-app`) on a screen under 768px, **every font';
$md[] = 'size is 1.25x the desktop value**. The public landing page is excluded, and';
$md[] = 'spacing and fixed heights are deliberately *not* scaled — the override enlarges';
$md[] = 'text only, rather than zooming the interface.';
$md[] = '';
$md[] = 'So reading a Tailwind class at its documented size gives the wrong answer on a';
$md[] = 'phone. Use the mobile column:';
$md[] = '';
$md[] = '| Class | Desktop | **Phone** |';
$md[] = '|---|---|---|';
foreach (TW_BASE_TEXT as $cls => $rem) {
    $mobile = $textScale[$cls] ?? null;
    $md[] = sprintf('| `%s` | %s | **%s** |', $cls, px($rem * 16), $mobile ? px($mobile) : 'not overridden');
}
$md[] = '';
$md[] = sprintf('The views also use %d arbitrary sizes, each with its own phone value:', count($arbitrary));
$md[] = '';
$md[] = '| Class | Phone |';
$md[] = '|---|---|';
foreach ($arbitrary as $cls => $size) {
    $md[] = sprintf('| `%s` | %s |', $cls, px($size));
}
$md[] = '';
$md[] = '## Colour';
$md[] = '';
$md[] = '| Token | Hex | Use |';
$md[] = '|---|---|---|';
$useOf = [
    'cm-bar' => 'Panel top bars — GoConnect chat list, GoMarket sidebar. The brand surface.',
    'cm-green' => 'Primary brand. Deep navy despite the name.',
    'cm-red' => 'Flag red. Accent and destructive.',
    'cm-yellow' => 'Flag yellow. The + Sell button.',
    'cm-blue' => 'Interactive and buttons.',
];
foreach ($colors as $name => $hex) {
    $note = $useOf[$name] ?? '';
    $md[] = sprintf('| `%s` | `%s` | %s |', $name, $hex, $note);
}
$md[] = '';
$md[] = '**Shared bar gradient** (`.bg-cm-bar-gradient`, used by `.yard-header`, `.mp-header`';
$md[] = 'and the GoMarket mobile bar, so every branded bar reads as one surface):';
$md[] = '';
$md[] = '```';
$md[] = 'linear-gradient(135deg, #015083 0%, #01395e 60%, #012a47 100%)';
$md[] = '```';
$md[] = '';
$md[] = '## Typeface';
$md[] = '';
$md[] = '```';
$md[] = $fontSans ?? '(not found)';
$md[] = '```';
$md[] = '';
$md[] = 'Inter is the real face; everything after it is fallback. The app must ship Inter';
$md[] = 'as a bundled asset — a phone will not have it, and falling back to the system';
$md[] = 'font changes every metric on every screen.';
$md[] = '';
$md[] = '## Corner radius, by how often it is used';
$md[] = '';
$md[] = '| Value | Rules |';
$md[] = '|---|---|';
$shown = 0;
foreach ($radii as $v => $n) {
    if ($shown++ >= 14) { break; }
    $md[] = sprintf('| `%s` (%s) | %d |', $v, px(toPx($v)), $n);
}
$md[] = '';
$md[] = '## Per-component font size on a phone';
$md[] = '';
$md[] = sprintf('All %d of them, read from the override block. This is the authoritative list:', count($componentFont));
$md[] = 'if a component is here, use this number and ignore whatever the base rule says.';
$md[] = '';
$md[] = '| Selector | Phone |';
$md[] = '|---|---|';
foreach ($componentFont as $sel => $size) {
    $md[] = sprintf('| `%s` | %s |', $sel, px($size));
}
$md[] = '';

$md[] = '## Colour declared outside app.css';
$md[] = '';
$md[] = sprintf(
    'Eight views carry their own `<style>` block. These %d colours are declared there and are'
    . ' in no `@theme` token, so nothing above would have caught them.',
    count($inlineColors)
);
$md[] = '';
$md[] = '| Hex | Declared in |';
$md[] = '|---|---|';
foreach ($inlineColors as $hex => $where) {
    $md[] = sprintf('| `%s` | %s |', $hex, implode(', ', $where));
}
$md[] = '';
$md[] = '## Animations';
$md[] = '';
$md[] = sprintf('All %d keyframe sets in the product, and where each is declared.', count($keyframes));
$md[] = '';
$md[] = '| Name | Source |';
$md[] = '|---|---|';
foreach ($keyframes as $name => $src) {
    $md[] = sprintf('| `%s` | %s |', $name, implode(', ', array_map(fn ($x) => '`' . $x . '`', $src)));
}
$md[] = '';
if ($deadStyles) {
    $md[] = 'Not counted above: these views push a `<style>` block to a `styles` stack, and no layout';
    $md[] = 'outputs `@stack(\'styles\')`, so the browser never receives it. Whatever it declares is not';
    $md[] = 'part of the design as shipped:';
    $md[] = '';
    foreach ($deadStyles as $d) { $md[] = '- `resources/views/' . $d . '`'; }
    $md[] = '';
}
file_put_contents(OUT_DIR . '/tokens.md', implode("\n", $md) . "\n");

// ── The Flutter side, generated so nobody retypes 191 numbers ──────────────

/** `.yard-msg__bubble` -> `yardMsgBubble`, unique within the file. */
function dartName(string $selector, array &$taken): string
{
    $s = preg_replace('/[^a-z0-9]+/i', ' ', ltrim($selector, '.'));
    $parts = array_values(array_filter(explode(' ', trim($s))));

    $name = '';
    foreach ($parts as $i => $p) {
        $name .= $i === 0 ? lcfirst($p) : ucfirst($p);
    }

    if ($name === '' || ctype_digit($name[0])) { $name = 't' . ucfirst($name); }

    $base = $name;
    $n = 2;
    while (isset($taken[$name])) { $name = $base . $n++; }
    $taken[$name] = true;

    return $name;
}

function dartColor(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    return '0xFF' . strtoupper($hex);
}

function dartDouble(?float $n): string
{
    if ($n === null) { return '0'; }

    // The CSS values are rem with four decimals, so they land on things like
    // 17.5008px. Rounded to two places: the difference is invisible and the
    // generated file stays readable.
    return number_format(round($n, 2), 2, '.', '');
}

$dart = [];
$dart[] = '// GENERATED by tools/ui-parity/extract.php from resources/css/app.css.';
$dart[] = '// Do not edit by hand — re-run the script.';
$dart[] = '//';
$dart[] = '// These are the values the web app renders on a phone, which is what the app';
$dart[] = '// has to match. Note that every font size here already includes the 1.25x';
$dart[] = '// mobile multiplier the stylesheet applies inside body.cn-app, so these are';
$dart[] = '// final rendered sizes, not the desktop values.';
$dart[] = '';
$dart[] = "import 'package:flutter/material.dart';";
$dart[] = '';
$dart[] = '/// The brand palette, verbatim from the stylesheet\'s @theme block.';
$dart[] = 'abstract final class CnColors {';
$taken = [];
foreach ($colors as $name => $hex) {
    if (! preg_match('/^#?[0-9a-f]{3,8}$/i', trim($hex))) { continue; }
    $id = dartName($name, $taken);
    $dart[] = sprintf('  static const %s = Color(%s);', $id, dartColor($hex));
}
$dart[] = '';
$dart[] = '  /// Shared by the GoConnect header, the GoMarket sidebar and the GoMarket';
$dart[] = '  /// mobile bar, so every branded bar reads as one surface.';
$dart[] = '  static const barGradient = LinearGradient(';
$dart[] = '    begin: Alignment.topLeft,';
$dart[] = '    end: Alignment.bottomRight,';
$dart[] = '    colors: [Color(0xFF015083), Color(0xFF01395E), Color(0xFF012A47)],';
$dart[] = '    stops: [0.0, 0.6, 1.0],';
$dart[] = '  );';
$dart[] = '}';
$dart[] = '';
$dart[] = '/// Tailwind\'s scale as a phone renders it. `text-sm` is 17.5, not 14.';
$dart[] = 'abstract final class CnTextScale {';
$taken = [];
foreach ($textScale as $cls => $size) {
    $dart[] = sprintf('  static const %s = %s;', dartName($cls, $taken), dartDouble($size));
}
$dart[] = '}';
$dart[] = '';
$dart[] = '/// The arbitrary `text-[Npx]` utilities, which the phone also rescales.';
$dart[] = 'abstract final class CnTextPx {';
$taken = [];
foreach ($arbitrary as $cls => $size) {
    preg_match('/(\d+)/', $cls, $m);
    $dart[] = sprintf('  /// `%s` on desktop.', $cls);
    $dart[] = sprintf('  static const px%s = %s;', $m[1], dartDouble($size));
}
$dart[] = '}';
$dart[] = '';
$dart[] = sprintf('/// Font size per component, all %d of them, as the phone renders them.', count($componentFont));
$dart[] = '/// Where a component appears here, this value wins over any base rule.';
$dart[] = 'abstract final class CnType {';
$taken = [];
$map = [];
foreach ($componentFont as $sel => $size) {
    $id = dartName($sel, $taken);
    $map[$sel] = $id;
    $dart[] = sprintf('  /// `%s`', $sel);
    $dart[] = sprintf('  static const %s = %s;', $id, dartDouble($size));
}
$dart[] = '}';
$dart[] = '';

$dart[] = '/// The 18 default-avatar gradients, in the order AvatarPalette declares them.';
$dart[] = '///';
$dart[] = '/// Pick with `crc32(seed) % 18`, seeded `user:{id}` (or `room:{id}`), so one';
$dart[] = '/// person keeps one colour everywhere. The ORDER is load-bearing: reordering';
$dart[] = '/// this list repaints every member in the product.';
$dart[] = '///';
$dart[] = '/// Resolved from the compiled bundle, not typed by hand: Tailwind v4 stores';
$dart[] = '/// its palette in OKLCH, and the v3 hexes differ visibly on saturated hues.';
$dart[] = 'abstract final class CnAvatarPalette {';
$dart[] = '  static const gradients = <List<Color>>[';
foreach ($avatarPalette as $pair) {
    if (! $pair['from_hex'] || ! $pair['to_hex']) {
        $dart[] = sprintf('    // MISSING: %s -> %s (not in the compiled bundle)', $pair['from'], $pair['to']);
        continue;
    }
    $dart[] = sprintf(
        '    [Color(%s), Color(%s)], // %s',
        dartColor($pair['from_hex']),
        dartColor($pair['to_hex']),
        $pair['from'] . ' -> ' . $pair['to']
    );
}
$dart[] = '  ];';
$dart[] = '}';
$dart[] = '';
// Every colour in the compiled bundle, so no widget ever retypes a hex.
$dart[] = '/// The full Tailwind palette as the web build actually renders it.';
$dart[] = '///';
$dart[] = '/// `blue-700` is `CnTw.blue700`. Resolved from OKLCH in the compiled bundle:';
$dart[] = '/// Tailwind v4 changed these values, so v3 hexes from memory are wrong.';
$dart[] = '/// Brand colours live in [CnColors]; this is for everything the views use';
$dart[] = '/// besides them.';
$dart[] = 'abstract final class CnTw {';
foreach ($tailwind as $twName => $twHex) {
    if (str_starts_with($twName, 'cm-')) { continue; }
    $ident = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $twName))));
    $ident = preg_replace('/(?<=[a-z])(?=\d)/', '', $ident);
    $dart[] = sprintf('  static const %s = Color(%s);', $ident, dartColor($twHex));
}
$dart[] = '}';
$dart[] = '';

$dartDir = OUT_DIR . '/generated';
if (! is_dir($dartDir)) { mkdir($dartDir, 0777, true); }
file_put_contents($dartDir . '/cn_tokens.dart', implode("\n", $dart) . "\n");

$payload['dart_identifiers'] = $map;
file_put_contents(
    OUT_DIR . '/tokens.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

printf(
    "wrote docs/ui-parity/tokens.{json,md} and docs/ui-parity/generated/cn_tokens.dart\n"
    . "  %d rules parsed\n  %d colours\n  %d mobile component font sizes\n  %d arbitrary text sizes\n"
    . "  %d keyframe sets, %d colours declared outside app.css\n  %d avatar gradients resolved from the build\n",
    count($rules),
    count($colors),
    count($componentFont),
    count($arbitrary),
    count($keyframes),
    count($inlineColors),
    count($avatarPalette)
);
