<?php

/**
 * Render the extracted tokens as a page you can hold against the live site.
 *
 * The spec in tokens.md is exact but unreadable at a glance — nobody checks 191
 * font sizes in a table. This draws the same numbers: swatches at their real
 * hex, the type scale at its real size, and the components rebuilt from their
 * real padding and radius. If something here looks unlike the site, the
 * extraction is wrong and we find out before any Dart is written.
 *
 * Reads:  docs/ui-parity/tokens.json
 * Writes: docs/ui-parity/reference.html
 * Usage:  php tools/ui-parity/extract.php && php tools/ui-parity/reference-page.php
 */

const ROOT = __DIR__ . '/../..';
const SRC = ROOT . '/docs/ui-parity/tokens.json';
const OUT = ROOT . '/docs/ui-parity/reference.html';

$t = json_decode((string) file_get_contents(SRC), true);
if (! is_array($t)) {
    fwrite(STDERR, "Run extract.php first: " . SRC . " is missing or unreadable.\n");
    exit(1);
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function n(float|int|null $v): string
{
    if ($v === null) { return '—'; }
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
}

// ── Type scale rows: desktop vs phone, drawn at the phone size ─────────────
$scaleRows = '';
foreach ($t['text_scale_desktop_px'] as $cls => $deskPx) {
    $phone = $t['text_scale_mobile_px'][$cls] ?? null;
    if ($phone === null) { continue; }
    $ratio = $deskPx > 0 ? $phone / $deskPx : 0;
    $scaleRows .= sprintf(
        '<tr><th scope="row"><code>%s</code></th>'
        . '<td class="num">%spx</td>'
        . '<td class="num strong">%spx</td>'
        . '<td class="num dim">%s&times;</td>'
        . '<td class="spec"><span style="font-size:%spx">Grand Nord</span></td></tr>',
        e($cls), n($deskPx), n($phone), n($ratio), n($phone)
    );
}

// ── The arbitrary text-[Npx] utilities ─────────────────────────────────────
$arbRows = '';
foreach ($t['arbitrary_text_mobile_px'] as $cls => $phone) {
    preg_match('/(\d+)/', $cls, $m);
    $arbRows .= sprintf(
        '<tr><th scope="row"><code>%s</code></th>'
        . '<td class="num">%spx</td><td class="num strong">%spx</td>'
        . '<td class="spec"><span style="font-size:%spx">Douala &middot; Bamenda</span></td></tr>',
        e($cls), e($m[1]), n($phone), n($phone)
    );
}

// ── Colour, grouped the way the stylesheet groups it ──────────────────────
$groups = [
    'Brand — primary' => ['cm-green', 'cm-green-light', 'cm-green-dark', 'cm-green-deeper', 'cm-green-deep'],
    'Panel bar' => ['cm-bar', 'cm-bar-dark', 'cm-bar-deep'],
    'Flag accents' => ['cm-red', 'cm-red-light', 'cm-red-dark', 'cm-yellow', 'cm-yellow-light', 'cm-yellow-dark'],
    'Interactive blue' => ['cm-blue', 'cm-blue-light', 'cm-blue-dark'],
];
$slate = [];
foreach ($t['colors'] as $k => $v) {
    if (str_starts_with($k, 'slate-')) { $slate[] = $k; }
}
$groups['Neutrals'] = $slate;

/** Relative luminance, so each swatch gets legible label text. */
function luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $c = [];
    foreach ([0, 2, 4] as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }

    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

$colorBlocks = '';
foreach ($groups as $label => $keys) {
    $sw = '';
    foreach ($keys as $k) {
        if (! isset($t['colors'][$k])) { continue; }
        $hex = strtoupper($t['colors'][$k]);
        $ink = luminance($hex) > 0.45 ? '#16181d' : '#ffffff';
        $sw .= sprintf(
            '<div class="sw" style="background:%s;color:%s"><span class="sw-n">%s</span><span class="sw-h">%s</span></div>',
            e($hex), $ink, e($k), e($hex)
        );
    }
    $colorBlocks .= sprintf('<h3>%s</h3><div class="swatches">%s</div>', e($label), $sw);
}

// ── The 191-row lookup, filterable ────────────────────────────────────────
$compRows = '';
foreach ($t['component_font_mobile_px'] as $sel => $pxv) {
    $dart = $t['dart_identifiers'][$sel] ?? '';
    $compRows .= sprintf(
        '<tr><th scope="row"><code>%s</code></th><td class="num strong">%spx</td><td><code class="dim">%s</code></td></tr>',
        e($sel), n($pxv), e($dart)
    );
}

// ── The 18 avatar gradients, drawn at their real values ────────────────────
$avatarSwatches = '';
foreach ($t['avatar_palette'] ?? [] as $pair) {
    if (! $pair['from_hex'] || ! $pair['to_hex']) { continue; }
    $avatarSwatches .= sprintf(
        '<div class="av"><div class="av__disc" style="background:linear-gradient(to bottom right,%s,%s)">%s</div>'
        . '<div class="av__meta"><code>%d</code> %s<br><code class="dim">%s &rarr; %s</code></div></div>',
        e($pair['from_hex']),
        e($pair['to_hex']),
        chr(65 + ($pair['index'] % 26)),
        (int) $pair['index'],
        e(explode('-', $pair['from'])[0]),
        e($pair['from_hex']),
        e($pair['to_hex'])
    );
}
$avatarCount = count($t['avatar_palette'] ?? []);
$keyframeCount = (int) ($t['counts']['keyframe_sets'] ?? 0);
$inlineColorCount = (int) ($t['counts']['inline_style_colors'] ?? 0);
// ── Coverage, from the screen map ──────────────────────────────────────────
$screenMapPath = __DIR__ . '/../../docs/ui-parity/screens.json';
$coverageRows = '';
$coverDone = 0;
$coverTotal = 0;
$routeTotal = 0;

if (is_file($screenMapPath)) {
    $sm = json_decode((string) file_get_contents($screenMapPath), true) ?: [];
    $routeTotal = (int) ($sm['counts']['routes_total'] ?? 0);

    $byFeature = [];
    foreach ($sm['routes'] ?? [] as $r) {
        if (empty($r['is_screen'])) { continue; }
        $f = $r['feature'];
        $byFeature[$f]['total'] = ($byFeature[$f]['total'] ?? 0) + 1;
        $byFeature[$f]['done'] = ($byFeature[$f]['done'] ?? 0) + (empty($r['covered_by']) ? 0 : 1);
    }

    $titles = [
        'auth' => 'Auth', 'onboard' => 'Onboarding', 'home' => 'Home',
        'yard' => 'GoConnect', 'marketplace' => 'GoMarket', 'people' => 'People',
        'profile' => 'Profile', 'pages' => 'Static pages', 'coming_soon' => 'Coming soon',
    ];
    $order = ['home', 'yard', 'marketplace', 'coming_soon', 'auth', 'onboard', 'profile', 'people', 'pages'];

    foreach ($order as $key) {
        if (! isset($byFeature[$key])) { continue; }
        $done = (int) $byFeature[$key]['done'];
        $total = (int) $byFeature[$key]['total'];
        $coverDone += $done;
        $coverTotal += $total;
        $pct = $total > 0 ? (int) round(100 * $done / $total) : 0;

        $coverageRows .= sprintf(
            '<tr><th scope="row">%s</th><td class="num">%d / %d</td>'
            . '<td><span class="bar"><span class="bar__fill" style="width:%d%%"></span></span></td></tr>',
            e($titles[$key] ?? $key),
            $done,
            $total,
            $pct
        );
    }
}
$generated = e((string) ($t['generated_at'] ?? ''));
$ruleCount = (int) ($t['counts']['css_rules_parsed'] ?? 0);
$compCount = count($t['component_font_mobile_px']);
$colorCount = count($t['colors']);

// Values used in the specimens, quoted from the stylesheet so the page and the
// prose cannot disagree.
$bubbleFont = n($t['component_font_mobile_px']['.yard-msg__bubble'] ?? 18);
$timeFont = n($t['component_font_mobile_px']['.yard-msg__time'] ?? 13);
$roomName = n($t['component_font_mobile_px']['.yard-room__name'] ?? 19);
$roomPrev = n($t['component_font_mobile_px']['.yard-room__preview'] ?? 16.4);
$roomTime = n($t['component_font_mobile_px']['.yard-room__time'] ?? 14);
$roomUnread = n($t['component_font_mobile_px']['.yard-room__unread'] ?? 13);
$hdrTitle = n($t['component_font_mobile_px']['.yard-header__title'] ?? 24);

// ── Pass 6: auth & onboarding ──────────────────────────────────────────────
// The ribbon's stars are placed by an x-init script on the web. These are the
// positions that script arrives at, computed offline (see 06-auth-onboarding.md
// §3.2). Drawn here at those fixed positions: if they sit on the red stripe,
// the computation is right.
$ribbonPaths = [
    ['#009639', 'M 80,-10 C 110,20 140,60 170,100 C 200,140 260,140 310,90 C 350,45 390,45 420,90 C 450,135 480,135 515,90 C 550,45 580,65 610,110'],
    ['#CE1126', 'M 100,-10 C 130,20 160,60 190,100 C 220,140 280,140 330,90 C 370,45 410,45 440,90 C 470,135 500,135 535,90 C 570,45 600,65 630,110'],
    ['#FCD116', 'M 120,-10 C 150,20 180,60 210,100 C 240,140 300,140 350,90 C 390,45 430,45 460,90 C 490,135 520,135 555,90 C 590,45 620,65 650,110'],
];
$ribbonStars = [[144.8, 41.1], [326.2, 92.8], [536.0, 89.2]];
$ribbonSvg = '<svg viewBox="0 0 600 440" preserveAspectRatio="xMaxYMin meet" '
    . 'style="position:absolute;top:0;right:0;width:180px;height:140px;pointer-events:none" aria-hidden="true">'
    . '<defs><filter id="rbsh" x="-10%" y="-10%" width="120%" height="120%">'
    . '<feDropShadow dx="2" dy="3" stdDeviation="4" flood-color="#000" flood-opacity="0.15"/></filter></defs>'
    . '<g filter="url(#rbsh)">';
foreach ($ribbonPaths as [$c, $d]) {
    $ribbonSvg .= sprintf('<path d="%s" stroke="%s" stroke-width="20" fill="none" stroke-linecap="round" stroke-linejoin="round"/>', $d, $c);
}
$ribbonSvg .= '</g>';
foreach ($ribbonStars as [$x, $y]) {
    $ribbonSvg .= sprintf(
        '<polygon transform="translate(%.1f,%.1f)" points="0,-8 1.9,-2.6 7.6,-2.6 2.9,1 4.7,6 0,2.3 -4.7,6 -2.9,1 -7.6,-2.6 -1.9,-2.6" fill="#FCD116" stroke="#CE1126" stroke-width="0.5"/>',
        $x, $y
    );
}
$ribbonSvg .= '</svg>';

$tw = $t['tailwind_palette'] ?? [];
$blue400 = e($tw['blue-400'] ?? '#51a2ff');
$blue700 = e($tw['blue-700'] ?? '#1447e6');

$shotDir = __DIR__ . '/../../docs/ui-parity/shots';
$shotList = [
    ['login-iphone15', 'Login', 'iPhone 15, 393 × 852. Fits; centred.'],
    ['login-iphonese-safari', 'Login — the short-phone bug', 'iPhone SE in Safari, 375 × 553. The user disc and 120px above it can never be scrolled to.'],
    ['register-1-located', 'Register 1 — located', 'A precise fix in central London. Note the locked region reads “England”; London is what gets saved.'],
    ['register-1-denied', 'Register 1 — denied', 'Location refused. Fields locked; the member cannot continue.'],
    ['register-2-account', 'Register 2 — account', 'Strength bar and five-item checklist; the placeholder is cut off at this width.'],
    ['register-3-review', 'Register 3 — review', 'Opened with ?step=3, which skips the wizard: the account reads “@ -****”.'],
    ['forgot-iphone15', 'Forgot password', 'Family B. The white “Cameroon” wordmark vanishes on slate-50.'],
    ['reset-iphone15', 'Reset password', 'Family B.'],
];
$shotFigures = '';
$shotCount = 0;
foreach ($shotList as [$file, $title, $caption]) {
    if (! is_file($shotDir . '/' . $file . '.png')) { continue; }
    $shotCount++;
    $shotFigures .= sprintf(
        '<figure class="shot"><img src="shots/%s.png" alt="%s" loading="lazy"><figcaption><strong>%s</strong>%s</figcaption></figure>',
        e($file), e($title), e($title), e($caption)
    );
}

$strength = [
    ['#CE1126', 1, 'Weak, like watered garri', '#CE1126'],
    ['#FCD116', 2, 'Getting there...', '#E0B800'],
    ['#243a5c', 3, 'Solid! 💪', '#243a5c'],
    ['#243a5c', 4, 'Fort like ndolé! 🔥', '#243a5c'],
];
$strengthRows = '';
foreach ($strength as [$fill, $score, $label, $text]) {
    $bars = '';
    for ($i = 1; $i <= 4; $i++) {
        $bars .= sprintf('<span style="flex:1;height:6px;border-radius:9999px;background:%s"></span>', $i <= $score ? $fill : '#f1f5f9');
    }
    $strengthRows .= sprintf(
        '<div style="margin-bottom:14px"><div style="display:flex;gap:4px">%s</div>'
        . '<div style="margin-top:4px;font-size:15px;font-weight:700;color:%s">%s</div></div>',
        $bars, $text, e($label)
    );
}
// ── Pass 7: GoMarket's own-account screens ─────────────────────────────────
// Status chips drawn from the compiled palette, so a palette change shows here.
$twc = fn (string $k, string $fallback) => e($tw[$k] ?? $fallback);
$chipSets = [
    'Listing — My listings' => [
        ['Active', 'emerald-100', 'emerald-800', null], ['Sold', 'blue-100', 'blue-800', null],
        ['Paused', 'slate-200', 'slate-700', null], ['Expired', 'orange-100', 'orange-800', null],
        ['Draft', 'amber-100', 'amber-800', null], ['Pending review', 'gray-100', 'gray-700', null],
        ['Removed', 'gray-100', 'gray-700', null],
    ],
    'Offer — Offers' => [
        ['Pending', 'amber-50', 'amber-800', 'amber-200'], ['Accepted', 'emerald-50', 'emerald-800', 'emerald-200'],
        ['Rejected', 'rose-50', 'rose-800', 'rose-200'], ['Countered', 'blue-50', 'blue-800', 'blue-200'],
        ['Withdrawn', 'slate-100', 'slate-700', 'slate-200'],
    ],
    'Order — Orders' => [
        ['Awaiting payment', 'amber-100', 'amber-800', 'amber-200'], ['Paid', 'blue-100', 'blue-800', 'blue-200'],
        ['Released', 'emerald-100', 'emerald-800', 'emerald-200'], ['Cancelled', 'slate-200', 'slate-700', 'slate-300'],
        ['Disputed', 'red-100', 'red-800', 'red-200'],
    ],
];
$chipRows = '';
foreach ($chipSets as $setName => $chips) {
    $cells = '';
    foreach ($chips as [$label, $bg, $fg, $ring]) {
        $cells .= sprintf(
            '<span class="mpchip" style="background:%s;color:%s;%s">%s</span>',
            $twc($bg, '#eee'), $twc($fg, '#333'),
            $ring ? 'box-shadow:inset 0 0 0 1px ' . $twc($ring, '#ccc') : '',
            e($label)
        );
    }
    $chipRows .= sprintf('<div class="mpchips"><div class="mpchips__name">%s</div><div class="mpchips__row">%s</div></div>', e($setName), $cells);
}
$p7Shot = is_file($shotDir . '/gomarket-rebuild-se.png')
    ? '<figure class="shot" style="max-width:300px"><img src="shots/gomarket-rebuild-se.png" alt="My listings rebuilt at iPhone SE width" loading="lazy">'
      . '<figcaption><strong>My listings at 375px, rebuilt from its classes</strong>Two rows of actions per card. '
      . 'At the bottom, the bulk bar: Reactivate is cut off and Delete starts at x=412, off-screen.</figcaption></figure>'
    : '';
// ── Pass 9: People and the static pages ────────────────────────────────────
$p9List = [
    ['people-rebuild-se', 'People, rebuilt at 375px', 'Request card: “Accepter” is clipped to “Accept”. The English “Confirm” is clipped on the SE and the 15 too.'],
    ['static-about-iphone15', 'About', 'Rendered, iPhone 15. The white navbar hides the logo’s white “Cameroon”, leaving “Network”.'],
    ['static-contact-iphone15', 'Contact', 'Rendered. The only static page that switches to the app layout for members.'],
    ['static-privacy-iphone15', 'Privacy', 'Rendered. Its location and messaging promises are checked against the code in the doc.'],
    ['static-terms-iphone15', 'Terms', 'Rendered. Amber accent; section 3 describes Solidarity, which isn’t live.'],
];
$p9Figures = '';
foreach ($p9List as [$file, $title, $caption]) {
    if (! is_file($shotDir . '/' . $file . '.png')) { continue; }
    $p9Figures .= sprintf(
        '<figure class="shot"><img src="shots/%s.png" alt="%s" loading="lazy"><figcaption><strong>%s</strong>%s</figcaption></figure>',
        e($file), e($title), e($title), e($caption)
    );
}
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mobile UI Reference</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Layout: one measured column. Page chrome is achromatic on purpose — a tinted
   page would contaminate how the brand swatches read. Specimens are the only
   coloured things here. */
:root {
  --bg: #fbfbfc;
  --surface: #ffffff;
  --surface-2: #f4f5f8;
  --ink: #16181d;
  --ink-2: #585f6d;
  --ink-3: #8b92a1;
  --line: #e3e6ec;
  --accent: #0d6e6e;
  --warn-bg: #fff8e6;
  --warn-line: #e8c96a;
  --warn-ink: #6b4e06;
  --chrome: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;
  --mono: 'IBM Plex Mono', ui-monospace, monospace;
  --subject: 'Inter', ui-sans-serif, system-ui, sans-serif;
  color-scheme: light;
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --bg: #0f1115;
    --surface: #161920;
    --surface-2: #1d2128;
    --ink: #e8eaef;
    --ink-2: #9aa2b1;
    --ink-3: #6e7686;
    --line: #272c35;
    --accent: #45b3b0;
    --warn-bg: #2a2412;
    --warn-line: #6d5a1d;
    --warn-ink: #e8cf8a;
    color-scheme: dark;
  }
}
:root[data-theme="dark"] {
  --bg: #0f1115;
  --surface: #161920;
  --surface-2: #1d2128;
  --ink: #e8eaef;
  --ink-2: #9aa2b1;
  --ink-3: #6e7686;
  --line: #272c35;
  --accent: #45b3b0;
  --warn-bg: #2a2412;
  --warn-line: #6d5a1d;
  --warn-ink: #e8cf8a;
  color-scheme: dark;
}

* { box-sizing: border-box; }
body {
  margin: 0;
  background: var(--bg);
  color: var(--ink);
  font-family: var(--chrome);
  font-size: 15px;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}
.wrap { max-width: 920px; margin: 0 auto; padding: 40px 16px 96px; }

header.top { border-bottom: 1px solid var(--line); padding-bottom: 24px; margin-bottom: 8px; }
.eyebrow {
  font-family: var(--mono); font-size: 12px; letter-spacing: .08em;
  text-transform: uppercase; color: var(--accent); margin: 0 0 10px;
}
h1 { font-size: 30px; line-height: 1.2; margin: 0 0 10px; text-wrap: balance; letter-spacing: -.015em; }
header.top p { margin: 0; color: var(--ink-2); max-width: 62ch; }
.meta { font-family: var(--mono); font-size: 12px; color: var(--ink-3); margin-top: 14px; }

section { margin-top: 44px; }
h2 {
  font-size: 13px; font-family: var(--mono); text-transform: uppercase;
  letter-spacing: .09em; color: var(--ink-2); margin: 0 0 4px;
  padding-bottom: 8px; border-bottom: 1px solid var(--line);
}
h2 + .lede { margin: 12px 0 20px; color: var(--ink-2); max-width: 64ch; }
h3 { font-size: 14px; margin: 26px 0 10px; color: var(--ink); }
p { max-width: 64ch; }
code { font-family: var(--mono); font-size: .88em; }
.dim { color: var(--ink-3); }

.callout {
  background: var(--warn-bg); border: 1px solid var(--warn-line);
  border-left-width: 4px; border-radius: 8px;
  padding: 16px 18px; margin: 24px 0 0; color: var(--warn-ink);
}
.callout h2 { border: 0; padding: 0; margin: 0 0 8px; color: inherit; opacity: .85; }
.callout p { margin: 0; max-width: 66ch; color: inherit; }
.callout strong { font-weight: 700; }

table { width: 100%; border-collapse: collapse; font-size: 14px; }
.scroll { overflow-x: auto; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); }
th, td { text-align: left; padding: 9px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
thead th {
  font-family: var(--mono); font-size: 11px; text-transform: uppercase;
  letter-spacing: .06em; color: var(--ink-3); font-weight: 500;
  background: var(--surface-2); position: sticky; top: 0;
}
tbody tr:last-child th, tbody tr:last-child td { border-bottom: 0; }
tbody th { font-weight: 500; white-space: nowrap; }
.num { font-family: var(--mono); font-variant-numeric: tabular-nums; white-space: nowrap; }
.num.strong { font-weight: 500; color: var(--ink); }
.num.dim { color: var(--ink-3); }
.spec { font-family: var(--subject); color: var(--ink); line-height: 1.25; }

.swatches { display: grid; grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); gap: 8px; }
.sw {
  border-radius: 8px; padding: 14px 12px; min-height: 76px;
  display: flex; flex-direction: column; justify-content: space-between;
  border: 1px solid rgba(0,0,0,.08);
}
.sw-n { font-size: 12px; font-weight: 600; }
.sw-h { font-family: var(--mono); font-size: 11px; opacity: .82; }
.gradient-strip {
  height: 72px; border-radius: 10px; margin-top: 10px;
  background: linear-gradient(135deg, #015083 0%, #01395e 60%, #012a47 100%);
  display: flex; align-items: center; padding: 0 18px;
  color: #fff; font-family: var(--mono); font-size: 12px;
}

/* ── Specimens ───────────────────────────────────────────────────────────
   Fixed colours, not themed: these reproduce a light-mode web app, so they
   must look the same whichever theme the viewer is in. */
.specimen { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; background: #fff; }
.specimen-label {
  font-family: var(--mono); font-size: 11px; color: var(--ink-3);
  padding: 8px 12px; background: var(--surface-2); border-bottom: 1px solid var(--line);
  display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.phone { font-family: var(--subject); background: #fff; }

/* .yard-header on mobile: white, 1px slate-200 underline */
.sp-header {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; background: #ffffff; border-bottom: 1px solid #e2e8f0;
}
.sp-header .t { font-size: {$hdrTitle}px; font-weight: 700; color: #0f172a; flex: 1; }
.sp-header .b { width: 34px; height: 34px; border-radius: 9999px; display: grid; place-items: center; color: #64748b; }
.sp-header-blue { background: linear-gradient(135deg,#015083 0%,#01395e 60%,#012a47 100%); border-bottom: 0; }
.sp-header-blue .t { color: #fff; }
.sp-header-blue .b { color: rgba(255,255,255,.82); }

/* .yard-room */
.sp-room { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 12px; background: transparent; }
.sp-room--active {
  background: rgba(252,209,22,.14);
  border-left: 5px solid #FCD116; padding-left: 7px;
  box-shadow: inset 0 0 0 1px rgba(252,209,22,.25);
}
.sp-room__avatar {
  width: 52px; height: 52px; border-radius: 9999px; flex-shrink: 0;
  display: grid; place-items: center; color: #fff; font-size: 16px; font-weight: 600;
  background: linear-gradient(to bottom right, #38bdf8, #0369a1);
}
.sp-room__body { flex: 1; min-width: 0; }
.sp-room__top { display: flex; align-items: baseline; gap: 8px; }
.sp-room__name { font-size: {$roomName}px; font-weight: 600; color: #1e293b; flex: 1; }
.sp-room--active .sp-room__name { color: #243a5c; font-weight: 700; }
.sp-room__time { font-size: {$roomTime}px; color: #94a3b8; }
.sp-room--active .sp-room__time { color: #243a5c; }
.sp-room__bot { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
.sp-room__preview {
  font-size: {$roomPrev}px; color: #64748b; flex: 1;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sp-room__unread {
  font-size: {$roomUnread}px; font-weight: 700; color: #fff;
  background: #243a5c;
  min-width: 20px; height: 20px; padding: 0 6px; border-radius: 9999px;
  display: grid; place-items: center;
}

/* Chat thread: WhatsApp-ish paper, own bubble #e1ffc7, other #fff */
.sp-thread { background: #e8ded4; padding: 14px 10px; display: flex; flex-direction: column; gap: 8px; }
.sp-row { display: flex; }
.sp-row--own { justify-content: flex-end; }
.sp-bubble {
  padding: 7px 10px; border-radius: 12px; min-width: 60px; max-width: 78%;
  font-size: {$bubbleFont}px; line-height: 1.45; word-break: break-word;
  box-shadow: 0 1px 1px rgba(0,0,0,.06);
}
.sp-bubble--own { background: #e1ffc7; border-bottom-right-radius: 4px; color: #0f172a; }
.sp-bubble--other { background: #fff; border-bottom-left-radius: 4px; color: #1e293b; }
.sp-time { display: block; text-align: right; font-size: {$timeFont}px; margin-top: 2px; line-height: 1; color: #64748b; }
.sp-sender { font-size: 14px; font-weight: 600; color: #015083; margin-bottom: 1px; }

/* ── Shell: the signed-in header and the drawer, at 390px (iPhone logical width) ── */
.sp-phone {
  width: 390px; max-width: 100%; border: 1px solid #cbd5e1; border-radius: 10px;
  overflow: hidden; background: #f8fafc;
}
.sp-appbar { background: linear-gradient(to right,#1b2d4a 0%,#243a5c 30%,#2e4a6e 60%,#3a5a80 100%); }
.sp-appbar__row { height: 56px; display: flex; align-items: center; padding: 0 12px; gap: 4px; }
.sp-burger {
  width: 40px; height: 40px; border-radius: 9999px; display: grid; place-items: center;
  color: rgba(255,255,255,.9); margin-left: -4px; margin-right: 4px; flex-shrink: 0;
}
.sp-burger svg { width: 24px; height: 24px; }
.sp-logo {
  height: 56px; display: flex; align-items: center; margin-right: auto;
  font-size: 30px; line-height: 1; filter: drop-shadow(0 2px 6px rgba(0,0,0,.35));
}
.sp-act {
  width: 36px; height: 36px; border-radius: 9999px; border: 1px solid rgba(255,255,255,.2);
  display: grid; place-items: center; color: rgba(255,255,255,.85); font-size: 20px; flex-shrink: 0;
}
.sp-lang {
  height: 30px; border-radius: 9999px; border: 1px solid rgba(255,255,255,.2);
  padding: 0 12px; display: grid; place-items: center;
  color: rgba(255,255,255,.8); font-size: 15px; font-weight: 600; flex-shrink: 0;
}
.sp-bell { width: 36px; height: 36px; display: grid; place-items: center; color: rgba(255,255,255,.8); flex-shrink: 0; }
.sp-bell svg { width: 20px; height: 20px; }
.sp-av {
  width: 32px; height: 32px; border-radius: 9999px; box-shadow: 0 0 0 2px rgba(255,255,255,.25);
  background: linear-gradient(to bottom right,#60a5fa,#2563eb); display: grid; place-items: center;
  color: #fff; font-size: 13px; font-weight: 700; margin-left: 4px; flex-shrink: 0;
}
.sp-tabs { height: 40px; display: flex; align-items: stretch; justify-content: space-around; padding: 0 4px; }
.sp-tab { flex: 1; min-width: 0; display: grid; place-items: center; position: relative; }
.sp-tab svg { width: 24px; height: 24px; }
.sp-tab--on { color: #FCD116; filter: drop-shadow(0 2px 4px rgba(0,0,0,.55)); transform: scale(1.1); }
.sp-tab--off { color: #fff; filter: drop-shadow(0 1px 2px rgba(0,0,0,.45)); }
.sp-tab--soon { color: rgba(255,255,255,.4); }
.sp-soon {
  position: absolute; top: 100%; margin-top: 4px; right: 4px; z-index: 2;
  background: #FCD116; color: #0f172a; font-size: 14px; font-weight: 700;
  padding: 4px 8px; border-radius: 6px; white-space: nowrap;
  box-shadow: 0 10px 15px -3px rgba(0,0,0,.25);
}
.sp-under { height: 92px; padding: 8px 12px 0; font-family: var(--mono); font-size: 12px; color: #94a3b8; }
.sp-rule { border-top: 1px dashed #cbd5e1; padding-top: 6px; }

/* The drawer: 82vw, capped at 320 */
.sp-drawer { width: 320px; max-width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; }
.sp-drawer__top {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
}
.sp-mark { font-size: 22.5px; font-weight: 800; letter-spacing: -0.02em; }
.sp-x { width: 36px; height: 36px; border-radius: 9999px; display: grid; place-items: center; color: #94a3b8; }
.sp-x svg { width: 20px; height: 20px; }
.sp-drawer__nav { padding: 12px 8px; }
.sp-drow {
  display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px;
  font-size: 19px; font-weight: 600; color: #334155;
}
.sp-drow svg { width: 22px; height: 22px; flex-shrink: 0; }
.sp-drow--on { background: rgba(36,58,92,.1); color: #1b2d4a; }
.sp-drow--soon { color: #94a3b8; }
.sp-dlabel {
  margin: 16px 0 4px; padding: 0 12px; font-size: 14px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em; color: #94a3b8;
}
.sp-pill {
  margin-left: auto; border-radius: 9999px; background: #fef3c7; color: #b45309;
  font-size: 13px; font-weight: 700; text-transform: uppercase; padding: 2px 8px;
}
.sp-drawer__foot { border-top: 1px solid #f1f5f9; padding: 12px 8px; }
.sp-circ {
  width: 22px; height: 22px; border-radius: 9999px; border: 1px solid #cbd5e1;
  display: grid; place-items: center; font-size: 13px; font-weight: 800; color: #475569; flex-shrink: 0;
}
/* ── GoConnect interaction states ── */
.sp-chatwrap { position: relative; width: 390px; max-width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; }
.sp-selbar {
  height: 56px; display: flex; align-items: center; gap: 2px; padding: 0 6px;
  background: #fff; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(2,25,45,.08);
}
.sp-selbar__count { margin-left: 10px; font-size: 16.8px; font-weight: 700; color: #0f172a; min-width: 18px; }
.sp-selbar__spacer { flex: 1; }
.sp-selbar__btn { width: 40px; height: 40px; border-radius: 9999px; display: grid; place-items: center; color: #334155; flex-shrink: 0; }
.sp-selbar__btn svg { width: 20px; height: 20px; }
.sp-selbar__btn--danger { color: #e11d48; }
.sp-row--picked { background: rgba(1,80,131,.10); margin: 0 -10px; padding: 0 10px; }
.sp-bubble--picked { outline: 2px solid rgba(1,80,131,.45); outline-offset: 2px; }
.sp-jumpwrap { position: relative; height: 150px; }
.sp-jump {
  position: absolute; right: 16px; bottom: 76px; width: 38px; height: 38px;
  border-radius: 9999px; background: #fff; color: #475569; display: grid; place-items: center;
  box-shadow: 0 2px 10px rgba(0,0,0,.18);
}
.sp-jump svg { width: 20px; height: 20px; }
.sp-jump__badge {
  position: absolute; top: -6px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px;
  border-radius: 9999px; background: #243a5c; color: #fff; font-weight: 800; line-height: 1;
  display: grid; place-items: center; box-shadow: 0 0 0 2px #fff;
}
.sp-typing { display: flex; align-items: center; gap: 8px; padding: 4px 16px 6px; background: #fff; }
.sp-dots { display: flex; gap: 3px; }
.sp-dots i { width: 6px; height: 6px; border-radius: 50%; background: #243a5c; opacity: .3; }
.sp-dots i:nth-child(2) { opacity: .65; transform: translateY(-2px); }
.sp-dots i:nth-child(3) { opacity: 1; transform: translateY(-4px); }
.sp-typing__text { font-size: 14px; font-style: italic; color: #243a5c; }
.sp-inputbar { background: #fff; border-top: 1px solid #f1f5f9; padding: 6px 8px; display: flex; align-items: flex-end; gap: 6px; }
.sp-pill { flex: 1; min-width: 0; display: flex; align-items: flex-end; background: #f1f5f9; border-radius: 24px; padding: 2px 4px; }
.sp-pill__btn { width: 36px; height: 36px; display: grid; place-items: center; color: #64748b; font-size: 23px; line-height: 1; flex-shrink: 0; }
.sp-pill__text { flex: 1; min-height: 36px; padding: 7px 4px; font-size: 18px; line-height: 1.4; color: #94a3b8; }
.sp-mic { width: 38px; height: 38px; border-radius: 9999px; background: #243a5c; color: #FCD116; border: 2.5px solid #243a5c; display: grid; place-items: center; flex-shrink: 0; }
.sp-mic svg, .sp-send svg { width: 18px; height: 18px; }
.sp-send { width: 40px; height: 40px; border-radius: 9999px; background: #243a5c; color: #fff; display: grid; place-items: center; flex-shrink: 0; }
/* ── GoMarket ── */
.sp-mpbar {
  background: #fff; border-bottom: 1px solid #e2e8f0; padding: 10px 12px;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
.sp-mpbar__title { font-size: 22.5px; font-weight: 800; color: #0f172a; }
.sp-mpbar__right { display: flex; align-items: center; gap: 6px; }
.sp-mpbar__pill {
  display: inline-flex; align-items: center; gap: 6px; border-radius: 9999px;
  padding: 6px 12px; font-size: 15px; font-weight: 600; background: #f1f5f9; color: #334155;
}
.sp-mpbar__pill svg { width: 14px; height: 14px; }
.sp-mpbar__sell { background: #FCD116; color: #1b2d4a; font-weight: 700; }
.sp-mpsort { display: flex; align-items: center; justify-content: space-between; padding: 8px 4px 0; }
.sp-mpsort__count { font-size: 17.5px; color: #475569; }
.sp-mpsort__sel { font-size: 15px; font-weight: 500; color: #334155; border: 1px solid #cbd5e1; border-radius: 9999px; padding: 4px 10px; background: #fff; }
.sp-mpgrid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 12px; row-gap: 16px; padding: 12px; background: #f1f5f9; }
.sp-mpcard { display: block; }
.sp-mpcard__img {
  position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden;
  background: linear-gradient(to bottom right, #e2e8f0, #cbd5e1);
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.05);
  display: grid; place-items: center; font-size: 40px;
}
.sp-mpcard__badges { position: absolute; top: 8px; left: 8px; display: flex; flex-direction: column; gap: 4px; }
.sp-mpcard__badge {
  font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .025em;
  padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,.1);
  background: rgba(255,255,255,.95); color: #0f172a;
}
.sp-mpcard__badge--feat { background: #FCD116; color: #0f172a; }
.sp-mpcard__sold { position: absolute; inset: 0; background: rgba(0,0,0,.5); display: grid; place-items: center; }
.sp-mpcard__sold span {
  background: #CE1126; color: #fff; font-size: 15px; font-weight: 700; text-transform: uppercase;
  padding: 6px 12px; border-radius: 9999px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.2);
}
.sp-mpcard__body { padding-top: 6px; padding-left: 2px; padding-right: 2px; }
.sp-mpcard__price { font-size: 19px; font-weight: 600; color: #0f172a; line-height: 1.25; }
.sp-mpcard__title { margin-top: 2px; font-size: 16px; color: #475569; line-height: 1.375; }
.sp-mpcard__loc { margin-top: 2px; font-size: 15px; color: #64748b; }
.sp-mpcard__dist { color: #94a3b8; }
.sp-mpstick {
  display: flex; align-items: center; gap: 8px; padding: 12px;
  background: #fff; border-top: 1px solid #e2e8f0;
  box-shadow: 0 -4px 6px -1px rgba(0,0,0,.1);
}
.sp-mpstick__btn {
  flex: 1; display: grid; place-items: center; padding: 12px 0; border-radius: 12px;
  font-size: 17.5px; color: #fff;
}
.sp-mpstick__btn--buy { background: #CE1126; font-weight: 800; }
.sp-mpstick__btn--msg { background: #243a5c; font-weight: 700; }
.sp-indicator { height: 5px; width: 134px; margin: 8px auto 10px; border-radius: 9999px; background: #0f172a; opacity: .85; }
/* ── Shared components ── */
.avgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
.av { display: flex; align-items: center; gap: 10px; }
.av__disc {
  width: 46px; height: 46px; border-radius: 9999px; flex-shrink: 0;
  display: grid; place-items: center; color: #fff; font-weight: 700; font-size: 17px;
  font-family: var(--subject);
}
.av__meta { font-size: 11px; line-height: 1.5; color: var(--ink-2); font-family: var(--mono); }
.badgerow { display: flex; flex-wrap: wrap; gap: 22px; align-items: flex-end; padding: 18px 14px; background: #fff; }
.bdg { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.bdg__host { position: relative; width: 44px; height: 44px; border-radius: 10px; background: #e2e8f0; }
.bdg__host--bar { background: linear-gradient(to right,#243a5c,#3a5a80); }
.bdg__pill {
  position: absolute; display: grid; place-items: center; border-radius: 9999px;
  color: #fff; font-weight: 700; line-height: 1; font-family: var(--subject);
}
.bdg__label { font-family: var(--mono); font-size: 10.5px; color: var(--ink-3); text-align: center; max-width: 92px; }
.toastbox { display: flex; flex-direction: column; gap: 12px; padding: 16px; background: #eef2f6; }
.tst {
  width: 288px; max-width: 100%; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
  box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); padding: 12px 16px; display: flex; gap: 12px; align-items: flex-start;
}
.tst--success { border-color: #a7f3d0; }
.tst__icon { width: 36px; height: 36px; border-radius: 9999px; display: grid; place-items: center; flex-shrink: 0; }
.tst__icon svg { width: 20px; height: 20px; }
.tst__title { font-size: 17.5px; font-weight: 600; color: #0f172a; }
.tst__body { font-size: 15px; color: #64748b; margin-top: 1px; }
.kct {
  width: 380px; max-width: 100%; background: #fff; border-radius: 14px; padding: 14px;
  box-shadow: 0 12px 32px rgba(15,23,42,.18); border-left: 4px solid #16a34a;
  display: flex; gap: 12px; position: relative; overflow: hidden;
}
.kct::before { content: ''; position: absolute; inset: 0; background: linear-gradient(120deg, rgba(22,163,74,.10), transparent 60%); }
.kct__av {
  position: relative; width: 44px; height: 44px; border-radius: 9999px; flex-shrink: 0;
  background: linear-gradient(135deg,#e0e7ff,#ddd6fe); color: #4338ca; font-weight: 700;
  display: grid; place-items: center; font-family: var(--subject);
}
.kct__badge {
  position: absolute; bottom: -2px; right: -2px; width: 18px; height: 18px; border-radius: 9999px;
  background: #16a34a; color: #fff; font-size: 11px; font-weight: 800; border: 2px solid #fff;
  display: grid; place-items: center;
}
.kct__title { font-size: 14px; font-weight: 700; color: #0f172a; }
.kct__text { font-size: 13px; color: #475569; margin-top: 2px; }
.kct__btns { display: flex; gap: 8px; margin-top: 8px; }
.kct__btn { padding: 5px 10px; font-size: 12px; font-weight: 600; border-radius: 8px; background: #16a34a; color: #fff; }
.kct__btn--ghost { background: transparent; color: #64748b; }
/* ── Coverage ── */
.bar { display: block; height: 8px; border-radius: 9999px; background: var(--line); overflow: hidden; min-width: 120px; }
.bar__fill { display: block; height: 100%; background: var(--accent); border-radius: 9999px; }
/* ── Auth ── */
.authbar { position: relative; overflow: hidden; height: 65px; box-sizing: border-box;
  border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; padding: 0 16px; }
.authbar .logo { position: relative; z-index: 1; font: 800 15px/1 var(--subject); color: #fff;
  letter-spacing: -.01em; }
.authbar .logo span { display: block; color: #fcd116; font-style: italic; font-size: 13px; }
.shots { display: grid; gap: 18px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); align-items: start; }
.shot { margin: 0; }
.shot img { display: block; width: 100%; height: auto; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
.shot figcaption { font-size: 13px; color: var(--ink-2); margin-top: 8px; line-height: 1.45; }
.shot figcaption strong { display: block; color: var(--ink); font-size: 14px; margin-bottom: 2px; }
.obpills { display: flex; gap: 8px; align-items: center; justify-content: center; padding: 18px; background: #fff; }
.obpills span { display: block; height: 8px; border-radius: 9999px; }
/* ── GoMarket screens ── */
.mpchips { display: grid; gap: 6px; margin-bottom: 14px; }
.mpchips__name { font-size: 13px; color: var(--ink-2); }
.mpchips__row { display: flex; flex-wrap: wrap; gap: 6px; }
.mpchip { font: 700 13px/1 var(--subject); text-transform: uppercase; letter-spacing: .05em; padding: 5px 10px; border-radius: 9999px; }
.mppill { display: inline-flex; align-items: center; height: 32px; padding: 0 12px; border-radius: 9999px; font: 700 15px/1 var(--subject); }
.pair { display: grid; gap: 14px; grid-template-columns: 1fr; }
@media (min-width: 680px) { .pair { grid-template-columns: 1fr 1fr; } }

.filter {
  display: flex; gap: 10px; align-items: center; margin: 0 0 12px; flex-wrap: wrap;
}
.filter input {
  flex: 1; min-width: 200px; font-family: var(--mono); font-size: 13px;
  padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px;
  background: var(--surface); color: var(--ink);
}
.filter input:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
.count { font-family: var(--mono); font-size: 12px; color: var(--ink-3); }
.tall { max-height: 480px; overflow-y: auto; }
a { color: var(--accent); }
footer { margin-top: 56px; padding-top: 20px; border-top: 1px solid var(--line); color: var(--ink-3); font-size: 13px; }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
</style>
</head>
<body>
<div class="wrap">

<header class="top">
  <p class="eyebrow">Cameroon Network &middot; UI parity</p>
  <h1>Mobile UI Reference</h1>
  <p>Every value on this page was read out of <code>resources/css/app.css</code> by
  <code>tools/ui-parity/extract.php</code> — none of it is estimated. It is here so you can
  hold it against the live site: if a swatch, a size or a component below looks unlike
  what you approved, the extraction is wrong and we fix it before any Dart gets written.</p>
  <p class="meta">{$ruleCount} CSS rules parsed &middot; {$colorCount} colours &middot; {$compCount} component sizes &middot; generated {$generated}</p>
</header>

<div class="callout">
  <h2>Read this before using any number</h2>
  <p>Inside the signed-in shell (<code>body.cn-app</code>) on a screen under 768px, <strong>every
  font size is 1.25&times; the desktop value</strong>. The public landing page is excluded, and
  spacing and fixed heights are deliberately <em>not</em> scaled, so the override enlarges text
  rather than zooming the interface. <strong>Every size on this page is already the phone
  value.</strong> Reading a Tailwind class at its documented size would build the app a quarter
  too small.</p>
</div>

<section>
  <h2>Type scale</h2>
  <p class="lede">The right-hand column is drawn at the real phone size in Inter, so the specimen
  is the measurement.</p>
  <div class="scroll">
    <table>
      <thead><tr><th>Class</th><th>Desktop</th><th>Phone</th><th>Factor</th><th>Specimen</th></tr></thead>
      <tbody>{$scaleRows}</tbody>
    </table>
  </div>

  <h3>Arbitrary sizes used in the views</h3>
  <p>Ten <code>text-[Npx]</code> utilities appear throughout the Blade templates. Each is
  remapped on a phone, and the mapping is not a clean multiple — it is hand-tuned per step.</p>
  <div class="scroll">
    <table>
      <thead><tr><th>Class</th><th>Written</th><th>Phone</th><th>Specimen</th></tr></thead>
      <tbody>{$arbRows}</tbody>
    </table>
  </div>
</section>

<section>
  <h2>Colour</h2>
  <p class="lede">Straight from the stylesheet's <code>@theme</code> block. Note that
  <code>cm-green</code> is a deep navy despite its name — the token was never renamed.</p>
  {$colorBlocks}

  <h3>Shared bar gradient</h3>
  <p>Used by <code>.yard-header</code>, <code>.mp-header</code> and the GoMarket mobile bar, so
  every branded bar reads as one surface.</p>
  <div class="gradient-strip">135deg &middot; #015083 0% &rarr; #01395e 60% &rarr; #012a47 100%</div>
</section>

<section>
  <h2>Typeface</h2>
  <p class="lede">Inter, with DM Sans behind it. The app must bundle Inter as an asset: a phone
  will not have it, and falling back to the system face changes every metric on every screen.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Inter 400 / 500 / 600 / 700 at 20px (<code>text-base</code> on a phone)</span></div>
    <div style="padding:16px;font-family:var(--subject);font-size:20px;line-height:1.5;color:#16181d;background:#fff">
      <div style="font-weight:400">Les gens du Grand Nord &mdash; regular</div>
      <div style="font-weight:500">Les gens du Grand Nord &mdash; medium</div>
      <div style="font-weight:600">Les gens du Grand Nord &mdash; semibold</div>
      <div style="font-weight:700">Les gens du Grand Nord &mdash; bold</div>
    </div>
  </div>
</section>

<section>
  <h2>The shell and its navigation</h2>
  <p class="lede">Drawn at 390px &mdash; an iPhone&rsquo;s logical width &mdash; from
  <code>components/layouts/app.blade.php</code>, <code>partials/fb-nav-tabs.blade.php</code> and
  <code>partials/app-drawer.blade.php</code>. The icons are the same SVG paths the views use, so
  the shapes are comparable rather than approximate. Full working, including the divergences
  found: <code>docs/ui-parity/01-layout-navigation.md</code>.</p>

  <h3>Header &mdash; a 56px row over a 40px tab row, 96px in all</h3>
  <p>Below 640px there is no location strip, no inline tab row, no Discover button and no chevron
  beside the avatar. GoConnect is shown selected: on a phone the <strong>only</strong> selected
  signal is the yellow plus a 10% scale, because the yellow underline the desktop draws is
  suppressed on mobile. The last tab is shown tapped &mdash; a coming-soon tab navigates nowhere
  and flashes its label for 1600ms, anchored to its right edge so it cannot clip the viewport.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Signed-in header &middot; GoConnect active &middot; GoPartner tapped</span></div>
    <div class="sp-phone">
      <div class="sp-appbar">
        <div class="sp-appbar__row">
          <span class="sp-burger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg></span>
          <span class="sp-logo">&#127464;&#127474;</span>
          <span class="sp-act">&#129302;</span>
          <span class="sp-lang">FR</span>
          <span class="sp-bell"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></span>
          <span class="sp-av">N</span>
        </div>
        <div class="sp-tabs">
          <span class="sp-tab sp-tab--off"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.03 2.59a1.5 1.5 0 0 1 1.94 0l8.5 7.25a1.5 1.5 0 0 1 .53 1.14V20a2 2 0 0 1-2 2h-4a1 1 0 0 1-1-1v-6a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2v-9.02c0-.44.19-.86.53-1.14l8.5-7.25z"/></svg></span>
          <span class="sp-tab sp-tab--on"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.13 2 11.2c0 2.88 1.46 5.45 3.75 7.13V22l3.43-1.88c.9.25 1.85.38 2.82.38 5.52 0 10-4.13 10-9.2S17.52 2 12 2zm1.05 12.3-2.55-2.72-4.95 2.72 5.45-5.78 2.6 2.72 4.9-2.72-5.45 5.78z"/></svg></span>
          <span class="sp-tab sp-tab--off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 13h6"/></svg></span>
          <span class="sp-tab sp-tab--soon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg></span>
          <span class="sp-tab sp-tab--soon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm10.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-10.5 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg></span>
          <span class="sp-tab sp-tab--soon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/></svg><span class="sp-soon">GoPartner (Coming Soon)</span></span>
        </div>
      </div>
      <div class="sp-under">
        <div class="sp-rule">content starts 104px from the top on a scrolling page,
        and flush at 96px inside the chat shell</div>
      </div>
    </div>
  </div>

  <h3>The drawer &mdash; the only way to half the app on a phone</h3>
  <p>82vw capped at 320px, in from the left over a <code>slate-900/50</code> backdrop, 200ms out
  and 150ms back. It exists only below 768px, and it carries Discover, Kamer AI, Contact, the
  module teasers and the account actions &mdash; everything the icon rail offers on a desktop and
  that has nowhere else to go at this width. Solidarity is drawn here because the web draws it
  here, marked Soon, which is right: nothing on the web mounts it.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Drawer panel &middot; Home active</span></div>
    <div class="sp-drawer">
      <div class="sp-drawer__top">
        <span class="sp-mark"><span style="color:#009639">KA</span><span style="color:#CE1126">M</span><span style="color:#FCD116">ER</span></span>
        <span class="sp-x"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></span>
      </div>
      <div class="sp-drawer__nav">
        <div class="sp-drow sp-drow--on"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.03 2.59a1.5 1.5 0 0 1 1.94 0l8.5 7.25a1.5 1.5 0 0 1 .53 1.14V20a2 2 0 0 1-2 2h-4a1 1 0 0 1-1-1v-6a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2v-9.02c0-.44.19-.86.53-1.14l8.5-7.25z"/></svg><span>Home</span></div>
        <div class="sp-drow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg><span>GoConnect</span></div>
        <div class="sp-drow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918"/></svg><span>Discover</span></div>
        <div class="sp-drow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg><span>Kamer AI</span></div>
        <div class="sp-drow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg><span>GoMarket</span></div>
        <p class="sp-dlabel">Coming soon</p>
        <div class="sp-drow sp-drow--soon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg><span>Solidarity</span><span class="sp-pill">Soon</span></div>
        <div class="sp-drow sp-drow--soon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg><span>GoParcel</span><span class="sp-pill">Soon</span></div>
      </div>
      <div class="sp-drawer__foot">
        <div class="sp-drow"><span class="sp-circ">FR</span><span>Fran&ccedil;ais</span></div>
      </div>
    </div>
  </div>

  <h3>The header is not the bar gradient</h3>
  <p>Two unrelated branded gradients, and the difference is easy to miss from the palette alone:
  the site header is navy and horizontal, every panel bar is blue and diagonal. The header&rsquo;s
  middle stops are literals, not tokens &mdash; <code>#2e4a6e</code> is two units off
  <code>cm-green-light</code> and <code>#3a5a80</code> is not a token at all.</p>
  <div class="pair">
    <div class="gradient-strip" style="background:linear-gradient(to right,#1b2d4a 0%,#243a5c 30%,#2e4a6e 60%,#3a5a80 100%)">site header &middot; to right</div>
    <div class="gradient-strip">panel bars &middot; 135deg</div>
  </div>
</section>

<section>
  <h2>Components, rebuilt from their real values</h2>
  <p class="lede">Each of these is drawn from the padding, radius, colour and font size in the
  stylesheet, at phone sizes. They are reproductions of a light-mode web app, so they keep fixed
  colours in either theme.</p>

  <h3>Chat list header &mdash; the mobile override</h3>
  <p>On a phone the header turns white with a 1px <code>slate-200</code> underline so it reads as
  part of the phone's own chrome. Tablet and desktop keep the brand bar. The app wants the white
  one.</p>
  <div class="pair">
    <div class="specimen">
      <div class="specimen-label"><span>under 768px &mdash; what the app builds</span><span>#ffffff</span></div>
      <div class="phone"><div class="sp-header">
        <div class="b">&#9776;</div><div class="t">Chats</div><div class="b">&#9906;</div><div class="b">&#8942;</div>
      </div></div>
    </div>
    <div class="specimen">
      <div class="specimen-label"><span>768px and up &mdash; not used by the app</span><span>gradient</span></div>
      <div class="phone"><div class="sp-header sp-header-blue">
        <div class="b">&#9776;</div><div class="t">Chats</div><div class="b">&#9906;</div><div class="b">&#8942;</div>
      </div></div>
    </div>
  </div>

  <h3>Room list row</h3>
  <p>52px avatar, 12px gap, 10px/12px padding. The active row is the one worth getting exactly
  right: a 5px <code>cm-yellow</code> left border with the left padding cut to 7px so the content
  stays aligned, a 14%-opacity yellow wash, and a 25% inset ring.</p>
  <div class="specimen">
    <div class="specimen-label"><span><code>.yard-room</code> &middot; normal, active, unread</span><span>padding 10px 12px &middot; gap 12px</span></div>
    <div class="phone">
      <div class="sp-room">
        <div class="sp-room__avatar">BA</div>
        <div class="sp-room__body">
          <div class="sp-room__top"><span class="sp-room__name">Bamenda</span><span class="sp-room__time">14:32</span></div>
          <div class="sp-room__bot"><span class="sp-room__preview">Ndong: na so e dey for we side</span></div>
        </div>
      </div>
      <div class="sp-room sp-room--active">
        <div class="sp-room__avatar" style="background:linear-gradient(to bottom right,#fb923c,#c2410c)">DL</div>
        <div class="sp-room__body">
          <div class="sp-room__top"><span class="sp-room__name">Douala</span><span class="sp-room__time">14:28</span></div>
          <div class="sp-room__bot"><span class="sp-room__preview">You: I go reach by 6</span></div>
        </div>
      </div>
      <div class="sp-room">
        <div class="sp-room__avatar" style="background:linear-gradient(to bottom right,#a78bfa,#6d28d9)">SL</div>
        <div class="sp-room__body">
          <div class="sp-room__top"><span class="sp-room__name">Solidarity &mdash; Mbeng</span><span class="sp-room__time">12:04</span></div>
          <div class="sp-room__bot"><span class="sp-room__preview">Marie: thank you all plenty</span><span class="sp-room__unread">3</span></div>
        </div>
      </div>
    </div>
  </div>

  <h3>Message bubbles</h3>
  <p>7px/10px padding, 12px radius with the tail corner cut to 4px, <code>min-width: 60px</code>,
  and a 1px shadow at 6% black. Own messages are <code>#e1ffc7</code>; everyone else's are white.
  Neither colour is in the token set &mdash; both are literals in the stylesheet.</p>
  <div class="specimen">
    <div class="specimen-label"><span><code>.yard-msg__bubble</code></span><span>{$bubbleFont}px / 1.45 &middot; radius 12px, tail 4px</span></div>
    <div class="sp-thread">
      <div class="sp-row">
        <div class="sp-bubble sp-bubble--other">
          <div class="sp-sender">Ngwa</div>
          Who dey for Bamenda this weekend? I wan organise something small.
          <span class="sp-time">14:30</span>
        </div>
      </div>
      <div class="sp-row sp-row--own">
        <div class="sp-bubble sp-bubble--own">
          I dey. Make we talk am.
          <span class="sp-time">14:32</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <h2>GoConnect, in the states a static page can show</h2>
  <p class="lede">The interaction states this module spends its character on. Values from
  <code>app.css</code> lines 1590&ndash;3350 and 7028&ndash;7319, and from
  <code>resources/js/yard-chat.js</code>. Full working:
  <code>docs/ui-parity/02-goconnect.md</code>.</p>

  <h3>Long-press selection</h3>
  <p>A 450ms hold, cancelled by more than 16px of horizontal or 24px of vertical movement.
  The toolbar covers the chat header; the tint runs the <strong>full width of the row</strong>
  rather than the bubble, so a column of picked messages reads as one block. There is no
  cancel button &mdash; selection is left by unpicking, by Escape, or by the back gesture.
  Reply and Pin appear only when exactly one message is picked; Delete only when the
  selection contains something the member may delete.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Selection toolbar &middot; two messages picked</span></div>
    <div class="sp-chatwrap">
      <div class="sp-selbar">
        <span class="sp-selbar__count">2</span>
        <span class="sp-selbar__spacer"></span>
        <span class="sp-selbar__btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" d="M5 15V5a2 2 0 012-2h10"/></svg></span>
        <span class="sp-selbar__btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5l2.2 4.46 4.92.72-3.56 3.47.84 4.9-4.4-2.31-4.4 2.31.84-4.9L4.36 8.68l4.92-.72 2.2-4.46z"/></svg></span>
        <span class="sp-selbar__btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 10H11a5 5 0 00-5 5v5m15-10l-5-5m5 5l-5 5"/></svg></span>
        <span class="sp-selbar__btn sp-selbar__btn--danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></span>
      </div>
      <div class="sp-thread">
        <div class="sp-row sp-row--picked">
          <div class="sp-bubble sp-bubble--other sp-bubble--picked">
            <div class="sp-sender">Ngwa</div>
            Na you dey for Douala?
            <span class="sp-time">14:28</span>
          </div>
        </div>
        <div class="sp-row sp-row--own sp-row--picked">
          <div class="sp-bubble sp-bubble--own sp-bubble--picked">
            I dey. Make we talk am.
            <span class="sp-time">14:32</span>
          </div>
        </div>
        <div class="sp-row">
          <div class="sp-bubble sp-bubble--other">
            Ok, I call you now.
            <span class="sp-time">14:33</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <h3>Jump to the newest message</h3>
  <p>Appears once the thread is more than 120px from the bottom &mdash; a margin, not an exact
  match, because the composer&rsquo;s own growth means it rarely lands exactly at the end. The
  badge counts what arrived while the reader was away, and reaching the bottom clears it.
  Arrivals do not scroll the thread while someone is reading back through it.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Jump button at its phone size, 38px, with 3 unseen</span></div>
    <div class="sp-chatwrap">
      <div class="sp-thread sp-jumpwrap">
        <div class="sp-row"><div class="sp-bubble sp-bubble--other">Earlier message<span class="sp-time">09:14</span></div></div>
        <button class="sp-jump" aria-label="Jump to new messages">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
          <span class="sp-jump__badge" style="font-size:11px">3</span>
        </button>
      </div>
      <div class="sp-typing">
        <span class="sp-dots"><i></i><i></i><i></i></span>
        <span class="sp-typing__text">Ngwa is typing&hellip;</span>
      </div>
      <div class="sp-inputbar">
        <span class="sp-pill">
          <span class="sp-pill__btn">&#128206;</span>
          <span class="sp-pill__text">Message</span>
          <span class="sp-pill__btn">&#128522;</span>
        </span>
        <span class="sp-mic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5M12 18.75a6 6 0 01-6-6v-1.5m6 7.5v3m0 0h3m-3 0H9m3-6a3 3 0 01-3-3V6a3 3 0 116 0v3.75a3 3 0 01-3 3z"/></svg></span>
      </div>
    </div>
    <p class="meta" style="padding:10px 12px 0">The badge is drawn at 11px because that is what
    the web renders: it is declared outside the phone type-scale block, so it is the one chat
    badge that was never scaled. Its siblings are 13px and 16.8px.</p>
  </div>
</section>

<section>
  <h2>GoMarket</h2>
  <p class="lede">This module has <strong>12 lines of CSS of its own</strong> &mdash; everything
  else is Tailwind utilities in the Blade, so the sizes below come from the class strings and
  the 1.25&times; mapping, not from the stylesheet. <code>text-[15px]</code> renders at 19px,
  <code>text-[13px]</code> at 16px, <code>text-[12px]</code> at 15px, <code>text-[10px]</code>
  at 13px. Full working: <code>docs/ui-parity/03-gomarket.md</code>.</p>

  <h3>Top bar and the two-up grid</h3>
  <p>White below 768px, the brand gradient from 768 to 1023 &mdash; the same treatment as the
  chat list header, written twice. The card is Facebook&rsquo;s: a rounded image sitting on the
  page background with text beneath it, and <strong>no card surface, no border, no lift and no
  zoom</strong> &mdash; the view says so in a comment, which is the kind of thing that gets
  &ldquo;improved&rdquo; into a white card with a hover lift. Price comes first, then title,
  then location.</p>
  <div class="specimen">
    <div class="specimen-label"><span>GoMarket feed at 390px &middot; 2 columns, 12px across, 16px down</span></div>
    <div class="sp-phone">
      <div class="sp-mpbar">
        <span class="sp-mpbar__title">GoMarket</span>
        <span class="sp-mpbar__right">
          <span class="sp-mpbar__pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 12h12M10 20h4"/></svg>Filters</span>
          <span class="sp-mpbar__pill sp-mpbar__sell"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Sell</span>
        </span>
      </div>
      <div style="background:#f1f5f9;padding:0 12px">
        <div class="sp-mpsort">
          <span class="sp-mpsort__count">248 listings</span>
          <span class="sp-mpsort__sel">Newest</span>
        </div>
      </div>
      <div class="sp-mpgrid">
        <span class="sp-mpcard">
          <span class="sp-mpcard__img">&#128241;
            <span class="sp-mpcard__badges"><span class="sp-mpcard__badge">Just listed</span></span>
          </span>
          <span class="sp-mpcard__body">
            <span class="sp-mpcard__price" style="display:block">85,000 XAF</span>
            <span class="sp-mpcard__title" style="display:block">Tecno Spark 10 Pro, 256GB</span>
            <span class="sp-mpcard__loc" style="display:block">Douala, Littoral <span class="sp-mpcard__dist">&middot; 4 km</span></span>
          </span>
        </span>
        <span class="sp-mpcard">
          <span class="sp-mpcard__img">&#128666;
            <span class="sp-mpcard__badges"><span class="sp-mpcard__badge sp-mpcard__badge--feat">&starf; Featured</span></span>
            <span class="sp-mpcard__sold"><span>Sold</span></span>
          </span>
          <span class="sp-mpcard__body">
            <span class="sp-mpcard__price" style="display:block">1,250,000 XAF</span>
            <span class="sp-mpcard__title" style="display:block">Toyota Corolla 2008</span>
            <span class="sp-mpcard__loc" style="display:block">Yaound&eacute;, Centre</span>
          </span>
        </span>
      </div>
    </div>
  </div>

  <h3>The listing&rsquo;s sticky action bar &mdash; and the safe area it does not have</h3>
  <p>Buy and Message, pinned to the bottom of the item view. The class meant to clear the home
  indicator is <code>pb-safe</code>, which <strong>does not exist</strong> &mdash; not in
  Tailwind, not in <code>app.css</code>, not in the compiled bundle. And no layout sets
  <code>viewport-fit=cover</code>, so <code>env(safe-area-inset-*)</code> returns zero even
  where it is written correctly elsewhere. The web has no working safe-area handling anywhere;
  the app needs a real inset here and under the chat dock.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Bottom action bar, drawn with the home indicator it currently sits under</span></div>
    <div class="sp-phone">
      <div style="background:#f1f5f9;height:60px"></div>
      <div class="sp-mpstick">
        <span class="sp-mpstick__btn sp-mpstick__btn--buy">Buy now &middot; 85,000</span>
        <span class="sp-mpstick__btn sp-mpstick__btn--msg">Message</span>
      </div>
      <div class="sp-indicator"></div>
    </div>
  </div>
</section>

<section>
  <h2>Shared components</h2>
  <p class="lede">The pieces that appear on more than one screen. Most exist in several
  slightly different versions, which is what this part is for. Full working:
  <code>docs/ui-parity/04-shared-components.md</code>.</p>

  <h3>The {$avatarCount} default-avatar gradients</h3>
  <p>Picked with <code>crc32(seed) % 18</code>, seeded <code>user:{id}</code>, so one person
  keeps one colour across chat, header, drawer, forward list and member list. <strong>The order
  is load-bearing</strong> — reordering this list repaints every member in the product.</p>
  <p>These are resolved from the compiled bundle, not transcribed. Tailwind v4 keeps its palette
  in <strong>OKLCH</strong> and only resolves it at build time, so the v3 hexes everyone
  remembers are wrong by a visible margin on exactly these saturated hues:
  <code>rose-400</code> is <code>#ff637e</code>, not <code>#fb7185</code>.</p>
  <div class="specimen">
    <div class="specimen-label"><span>AvatarPalette, in declaration order, at 46px</span></div>
    <div style="padding:16px;background:#fff">
      <div class="avgrid">{$avatarSwatches}</div>
    </div>
  </div>

  <h3>Seven badges for one idea</h3>
  <p>Three different grounds, four ring treatments, sizes from 10 to 14.4px. At least two are
  plainly unintended: the jump counter never got the phone type scale, and the bell&rsquo;s ring
  is <code>#2c4a3e</code> &mdash; a dark green in no token, on a navy header.</p>
  <div class="specimen">
    <div class="specimen-label"><span>Drawn at their real sizes, on the surface each actually sits on</span></div>
    <div class="badgerow">
      <div class="bdg">
        <div class="bdg__host"><span class="bdg__pill" style="top:-6px;right:-6px;min-width:20px;height:20px;padding:0 6px;font-size:13px;background:#243a5c">7</span></div>
        <span class="bdg__label">room unread<br>13px</span>
      </div>
      <div class="bdg">
        <div class="bdg__host"><span class="bdg__pill" style="top:-6px;right:-6px;width:20px;height:20px;font-size:14px;font-weight:800;background:#243a5c">@</span></div>
        <span class="bdg__label">mention<br>14px</span>
      </div>
      <div class="bdg">
        <div class="bdg__host"><span class="bdg__pill" style="top:-6px;right:-6px;min-width:20px;height:20px;padding:0 6px;font-size:14.4px;background:linear-gradient(135deg,#007A5E,#00a37a);border:1.5px solid #fff">12</span></div>
        <span class="bdg__label">filter count<br>14.4px</span>
      </div>
      <div class="bdg">
        <div class="bdg__host bdg__host--bar"><span class="bdg__pill" style="top:2px;right:2px;min-width:16px;height:16px;padding:0 4px;font-size:12px;background:linear-gradient(135deg,#007A5E,#00a37a);border:1.5px solid #01395e">3</span></div>
        <span class="bdg__label">header button<br>12px</span>
      </div>
      <div class="bdg">
        <div class="bdg__host bdg__host--bar"><span class="bdg__pill" style="top:-2px;right:-2px;min-width:18px;height:18px;padding:0 4px;font-size:10px;font-weight:800;background:linear-gradient(135deg,#ef4444,#dc2626);border:2px solid #2c4a3e">9</span></div>
        <span class="bdg__label">bell<br>10px, #2c4a3e ring</span>
      </div>
      <div class="bdg">
        <div class="bdg__host" style="background:#e8ded4"><span class="bdg__pill" style="top:-6px;right:-4px;min-width:20px;height:20px;padding:0 5px;font-size:11px;font-weight:800;background:#243a5c;box-shadow:0 0 0 2px #fff">3</span></div>
        <span class="bdg__label">jump to latest<br>11px &mdash; unscaled</span>
      </div>
      <div class="bdg">
        <div class="bdg__host" style="background:#fff"><span class="bdg__pill" style="top:12px;right:-14px;padding:2px 8px;font-size:13px;background:#fef3c7;color:#b45309;text-transform:uppercase">Soon</span></div>
        <span class="bdg__label">coming soon<br>13px</span>
      </div>
    </div>
  </div>

  <h3>Two of the three toast systems</h3>
  <p>The plain queue holds three at a time and dismisses after 5s; the social one is unbounded
  and lasts 8s, because an actionable toast should outlast an informational one. On a phone the
  social toast goes full width from <code>top: 76px</code>. It also chimes, pulses the document
  title and &mdash; on an acceptance &mdash; throws confetti.</p>
  <div class="specimen">
    <div class="specimen-label"><span>yardToasts (288px, 5s) and kc-notifier (380px, 8s)</span></div>
    <div class="toastbox">
      <div class="tst tst--success">
        <span class="tst__icon" style="background:#d1fae5;color:#059669"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></span>
        <span>
          <span class="tst__title" style="display:block">Listing published</span>
          <span class="tst__body" style="display:block">Tecno Spark 10 Pro, 256GB</span>
        </span>
      </div>
      <div class="kct">
        <span class="kct__av">N<span class="kct__badge">&check;</span></span>
        <span style="flex:1;min-width:0;position:relative">
          <span class="kct__title" style="display:block">Ngwa accepted your request</span>
          <span class="kct__text" style="display:block">You are now connected.</span>
          <span class="kct__btns">
            <span class="kct__btn">Message</span>
            <span class="kct__btn kct__btn--ghost">Dismiss</span>
          </span>
        </span>
      </div>
    </div>
  </div>

  <h3>What is no longer missing</h3>
  <p><code>app.css</code> was never the whole stylesheet: eight views ship their own
  <code>&lt;style&gt;</code> blocks, 509 lines between them, and the extractor only read
  <code>app.css</code>. It reads them now, so <code>tokens.json</code> finally records all
  <strong>{$keyframeCount}</strong> keyframe sets in the product and the
  <strong>{$inlineColorCount}</strong> colours declared outside the token palette.</p>
</section>

<section>
  <h2>Every component font size</h2>
  <p class="lede">All {$compCount} of them, with the identifier the generated Dart gives each one.
  Where a component appears here, this value wins over whatever its base rule says.</p>
  <div class="filter">
    <input id="q" type="search" placeholder="filter — try yard-msg, wa-info, comm-card" aria-label="Filter component sizes">
    <span class="count" id="count">{$compCount} shown</span>
  </div>
  <div class="scroll tall">
    <table id="comp">
      <thead><tr><th>Selector</th><th>Phone</th><th>Dart</th></tr></thead>
      <tbody>{$compRows}</tbody>
    </table>
  </div>
</section>

<section>
  <h2>Auth and onboarding</h2>
  <p class="lede">Login, the three-step register wizard, forgot, reset and verify, and the
  four-step onboarding. Full working, with every measurement:
  <code>docs/ui-parity/06-auth-onboarding.md</code>.</p>

  <h3>The auth bar and the flag ribbon</h3>
  <p>65px; the gradient runs right to left, white &rarr; <code>blue-400</code> {$blue400} &rarr;
  <code>cm-green</code>. The ribbon&rsquo;s green is the flag&rsquo;s <code>#009639</code>, not
  <code>cm-green</code>. Its three stars are drawn here at <strong>precomputed</strong> positions;
  on the web a script searches for them at runtime, which Flutter can&rsquo;t do and doesn&rsquo;t need to.</p>
  <div class="specimen" style="max-width:393px">
    <div class="specimen-label"><span>login / register bar at 393px</span><span>clipped at 65px</span></div>
    <div class="phone">
      <div class="authbar" style="background:linear-gradient(to left,#ffffff,{$blue400},#243a5c)">
        <div class="logo">Cameroon<span>Network</span></div>
        {$ribbonSvg}
      </div>
    </div>
  </div>

  <h3>Primary buttons, two families</h3>
  <div class="pair">
    <div class="specimen" style="max-width:393px">
      <div class="specimen-label"><span>A &mdash; login, register</span><span>53px, r12</span></div>
      <div class="phone" style="padding:20px">
        <div style="height:53px;border-radius:12px;display:grid;place-items:center;color:#fff;font-weight:700;font-size:17.5px;background:linear-gradient(to right,#243a5c,{$blue700});box-shadow:0 10px 15px -3px rgba(36,58,92,.2),0 4px 6px -4px rgba(36,58,92,.2)">Sign In</div>
      </div>
    </div>
    <div class="specimen" style="max-width:393px">
      <div class="specimen-label"><span>B &mdash; forgot, reset, verify</span><span>53px, r12</span></div>
      <div class="phone" style="padding:20px">
        <div style="height:53px;border-radius:12px;display:grid;place-items:center;color:#fff;font-weight:700;font-size:17.5px;background:#243a5c">Send Reset Link</div>
      </div>
    </div>
  </div>
  <p class="meta"><code>blue-700</code> is {$blue700} in the build. The value most people remember,
  <code>#1d4ed8</code>, is Tailwind v3&rsquo;s.</p>

  <h3>Password strength and onboarding progress</h3>
  <div class="pair">
    <div class="specimen" style="max-width:393px">
      <div class="specimen-label"><span>strength = length, uppercase, digit, symbol</span></div>
      <div class="phone" style="padding:20px">{$strengthRows}</div>
    </div>
    <div class="specimen" style="max-width:393px">
      <div class="specimen-label"><span>onboarding, on step 2 of 4</span><span>8px tall</span></div>
      <div class="obpills">
        <span style="width:24px;background:rgba(36,58,92,.4)"></span>
        <span style="width:40px;background:#243a5c"></span>
        <span style="width:8px;background:#cbd5e1"></span>
        <span style="width:8px;background:#cbd5e1"></span>
      </div>
    </div>
  </div>

  <h3>As rendered, at a real iPhone viewport</h3>
  <p>{$shotCount} screenshots taken with <code>tools/ui-parity/shoot.mjs</code>, which emulates the
  device rather than shrinking a desktop window. Tall steps use an extended height so the whole
  step shows; the width is the phone&rsquo;s. Verify and onboarding need a signed-in member and
  aren&rsquo;t shot here.</p>
  <div class="shots">{$shotFigures}</div>
</section>
<section>
  <h2>GoMarket: your listings, offers and orders</h2>
  <p class="lede">The eight screens a member uses to manage their side of GoMarket. Full working:
  <code>docs/ui-parity/07-gomarket-screens.md</code>. On a phone the only way to reach them is the
  <strong>Filters</strong> drawer on the feed, which is the first decision that pass puts to the owner.</p>

  <h3>Status chips</h3>
  <p>Three enums, three slightly different chip recipes: listings have no ring; offers sit on
  the <code>-50</code> tints and orders on <code>-100</code>, both ringed. Colours resolved from the build.</p>
  <div class="specimen" style="max-width:520px"><div class="phone" style="padding:18px">{$chipRows}</div></div>

  <h3>The action pill, and why it needs a bigger hit area</h3>
  <div class="specimen" style="max-width:393px">
    <div class="specimen-label"><span>text-xs bold, px-3 py-1.5</span><span>32px tall; Apple asks for 44</span></div>
    <div class="phone" style="padding:18px;display:flex;flex-wrap:wrap;gap:6px">
      <span class="mppill" style="background:#f1f5f9;color:#334155">✏️ Edit</span>
      <span class="mppill" style="background:#fcd116;color:#0f172a">🚀 Bump</span>
      <span class="mppill" style="background:{$twc('amber-50','#fffbeb')};color:{$twc('amber-700','#b45309')};box-shadow:inset 0 0 0 1px {$twc('amber-200','#fde68a')}">⏸ Pause</span>
      <span class="mppill" style="background:{$twc('blue-50','#eff6ff')};color:{$twc('blue-700','#1d4ed8')};box-shadow:inset 0 0 0 1px {$twc('blue-200','#bfdbfe')}">💰 Sold</span>
      <span class="mppill" style="background:#f1f5f9;color:#334155">📊 Stats</span>
    </div>
  </div>

  <h3>Measured</h3>
  <div class="shots">{$p7Shot}</div>
  <p class="meta">These screens need a signed-in member, and the screenshot tool does not log in.
  This one is the views' own markup with sample text, against the live stylesheet.</p>
</section>
<section>
  <h2>Profile</h2>
  <p class="lede">Three routes, one page. <code>/u/{username}</code> redirects; <code>/profile</code> is an
  account page only members without a username ever reach; the profile itself lives at
  <code>/marketplace/seller/{username}</code> for everyone. Full working: <code>docs/ui-parity/08-profile.md</code>.</p>

  <h3>The header at 393px</h3>
  <div class="specimen" style="max-width:393px">
    <div class="specimen-label"><span>cover 96 · avatar 96, 56 over the cover</span><span>gutter 12</span></div>
    <div class="phone" style="background:#f1f5f9;padding:12px">
      <div style="background:#fff;border-radius:16px;box-shadow:0 0 0 1px #e2e8f0">
        <div style="height:96px;border-radius:16px 16px 0 0;background:linear-gradient(to right,#243a5c,#2e4a70)"></div>
        <div style="padding:0 16px 20px">
          <div style="display:flex;align-items:flex-end;gap:16px">
            <div style="margin-top:-56px;width:96px;height:96px;flex:none;border-radius:9999px;border:4px solid #fff;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);background:#e5e8ed;display:grid;place-items:center;font:700 37.5px/1 var(--subject);color:#243a5c">A</div>
            <div style="min-width:0;padding-bottom:4px">
              <div style="font:800 30px/1.2 var(--subject);color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Ama Ngono</div>
              <div style="font-size:16px;color:#64748b;margin-top:2px">Joined Nov 2026 <span style="color:#cbd5e1">·</span> <strong style="color:#334155;font-weight:600">3 active listings</strong></div>
            </div>
          </div>
          <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px">
            <span class="mppill" style="height:41px;padding:0 16px;font-size:17.5px;background:#243a5c;color:#fff">+ Follow</span>
            <span class="mppill" style="height:41px;padding:0 16px;font-size:17.5px;background:#243a5c;color:#fff">Connect</span>
            <span class="mppill" style="height:41px;padding:0 16px;font-size:17.5px;background:#f1f5f9;color:#94a3b8">Message</span>
            <span class="mppill" style="height:36px;width:36px;padding:0;justify-content:center;background:#f1f5f9;color:#334155">⋯</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <p class="meta">A stranger's view. The disabled-looking <strong>Message</strong> is deliberate: tapping it
  explains that connecting comes first. The name is sample text.</p>

  <h3>One value drives the action row</h3>
  <div class="scroll"><table>
    <thead><tr><th>connection_state</th><th>Row</th></tr></thead>
    <tbody>
      <tr><th scope="row">self</th><td>Settings</td></tr>
      <tr><th scope="row">none</th><td>Follow · Connect · Message (explains) · ⋯</td></tr>
      <tr><th scope="row">outgoing</th><td>Follow · “Request sent” chip · Cancel request · ⋯</td></tr>
      <tr><th scope="row">incoming</th><td>Follow · Accept request · Decline · ⋯</td></tr>
      <tr><th scope="row">connected</th><td>Follow · Message · “Connected” chip · ⋯ (copy link, save as contact, disconnect)</td></tr>
    </tbody>
  </table></div>
</section>
<section>
  <h2>People and the static pages</h2>
  <p class="lede">The last five routes. People is a Facebook-style directory with five tabs and a
  2-up card; the four static pages share one marketing look and are best opened in an in-app
  browser rather than rebuilt. Full working: <code>docs/ui-parity/09-people-static.md</code>.</p>
  <div class="shots">{$p9Figures}</div>
  <p class="meta">The static pages are public, so they are true renders at 393 × 852. People needs a
  signed-in member, so its card is the view’s own markup with sample text.</p>
</section>
<section>
  <h2>Coverage</h2>
  <p class="lede">From <code>docs/ui-parity/screens.json</code>, generated by
  <code>tools/ui-parity/screen-map.php</code> out of Laravel&rsquo;s own route table &mdash; so
  it cannot drift from the site. <strong>{$routeTotal}</strong> routes in all;
  <strong>{$coverTotal}</strong> of them are screens the app has to build, and
  <strong>{$coverDone}</strong> are written up in detail so far.</p>
  <div class="scroll">
    <table>
      <thead><tr><th>Area</th><th>Documented</th><th></th></tr></thead>
      <tbody>{$coverageRows}</tbody>
    </table>
  </div>
  <p class="meta">The admin panel is excluded on purpose: 35 routes, web only. Twenty overlays
  are excluded too, because a route list cannot see them &mdash; and that is where most of this
  product&rsquo;s interaction detail lives. Both are listed in the screen map.</p>
</section>

<section>
  <h2>What this page does not cover yet</h2>
  <p class="lede">Being explicit, so nothing is assumed to be settled:</p>
  <ul>
    <li>Onboarding, profile, auth and the smaller GoMarket screens (offers, orders, saved
    searches, insights). The shell, GoConnect and the main GoMarket surfaces are covered.</li>
    <li>Icons. The views use inline SVG paths; they need extracting into an asset set.</li>
    <li>Animation. The stylesheet defines a dozen keyframe sets that have no Flutter equivalent
    yet.</li>
    <li>Anything behind a state this page cannot show: empty states, error states, the long-press
    selection toolbar, the jump-to-latest button.</li>
  </ul>
</section>

<footer>
  Generated from <code>resources/css/app.css</code> by <code>tools/ui-parity/extract.php</code>
  and <code>tools/ui-parity/reference-page.php</code>. Re-run both after changing the web UI
  and this page follows.
</footer>

</div>
<script>
(function () {
  var q = document.getElementById('q');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#comp tbody tr'));
  var count = document.getElementById('count');
  if (!q || !rows.length) { return; }
  q.addEventListener('input', function () {
    var term = q.value.trim().toLowerCase();
    var shown = 0;
    rows.forEach(function (r) {
      var hit = term === '' || r.textContent.toLowerCase().indexOf(term) !== -1;
      r.style.display = hit ? '' : 'none';
      if (hit) { shown++; }
    });
    count.textContent = shown + ' shown';
  });
})();
</script>
</body>
</html>

HTML;

if (! is_dir(dirname(OUT))) { mkdir(dirname(OUT), 0777, true); }
file_put_contents(OUT, $html);

printf("wrote docs/ui-parity/reference.html (%d KB)\n", (int) round(strlen($html) / 1024));
