<?php

/**
 * Pull every inline <svg> icon out of the Blade views and turn them into an
 * asset set the Flutter app can load.
 *
 * The web has no icon font and no sprite: 553 icons are pasted inline across
 * 60-odd views, mostly Heroicons copied by hand — some from v1, some from v2,
 * some re-optimised by an editor along the way. The same X is written three
 * different ways. Comparing strings would call those three icons; comparing
 * geometry calls them one. So every path is parsed and rewritten as absolute
 * M/L/C/Q/A/Z commands before anything is compared, and that normalised
 * geometry is what dedupes and what is matched against Heroicons.
 *
 * What it does, per svg:
 *   - reads the root paint (viewBox, fill vs stroke, stroke-width, caps, joins)
 *     and the children's geometry; drops classes and Blade/Alpine/Livewire
 *     attributes;
 *   - works out the rendered size: inline style, then a rule in app.css (which
 *     is unlayered, so it beats Tailwind utilities), then the w-/h-/size-
 *     classes, then width/height attributes. Phone sizes are the base classes:
 *     sm: and up are skipped, and w/h are NOT scaled by the 1.25x text rule;
 *   - expands a Blade ternary in a paint attribute (fill="{{ $on ? 'currentColor' : 'none' }}")
 *     into one occurrence per state, because those are two different-looking icons;
 *   - resolves `class="{{ $iconSize }}"` and `d="{{ $path }}"` from string
 *     literals in the same file, and reports anything it could not resolve.
 *
 * Icons are named by matching against icon libraries when copies of them are
 * given — Heroicons first, then Lucide and Feather, which most of the rest
 * turn out to be. The matches are cached in tools/ui-parity/icon-names.json so
 * a later run names the same icons without the packages. Icons that match
 * nothing are named from MANUAL_NAMES below, keyed by geometry hash, or get a
 * placeholder name and a line in the report.
 *
 * Usage:  php tools/ui-parity/icons.php                    names from the cache
 *         php tools/ui-parity/icons.php --library=DIR [--library=DIR ...]
 *         Each DIR is an unpacked package root (the folder holding package.json).
 *         The views mix three Heroicons generations plus Lucide/Feather, so to
 *         re-identify from scratch give all five:
 *           npm pack heroicons@2.2.0 heroicons@2.0.18 heroicons@1.0.6 lucide-static feather-icons
 *           then tar -xzf each into its own folder; DIR is its package/.
 *         Nothing is installed into the project.
 * Writes: docs/ui-parity/icons/<name>.svg     one per unique icon, flutter_svg-ready
 *         docs/ui-parity/icons.json           the inventory
 *         docs/ui-parity/icons.md             the inventory, readable
 *         docs/ui-parity/generated/cn_icons.dart   CnIcons, the asset paths
 *         tools/ui-parity/icon-names.json     the library match cache
 */

const ROOT = __DIR__ . '/../..';
const VIEWS = ROOT . '/resources/views';
const CSS = ROOT . '/resources/css/app.css';
const OUT_DIR = ROOT . '/docs/ui-parity';
const ICON_DIR = OUT_DIR . '/icons';
const NAME_CACHE = __DIR__ . '/icon-names.json';

/** Same exclusion as extract.php: the stock Laravel page, rendered by no route. */
const EXCLUDED_VIEWS = ['welcome.blade.php'];

/** Where each view belongs. Longest prefix wins. Admin is not in the app. */
const MODULES = [
    'admin/' => 'admin',
    'components/layouts/admin' => 'admin',
    'livewire/yard/' => 'goconnect',
    'yard/' => 'goconnect',
    'livewire/marketplace/' => 'gomarket',
    'components/marketplace/' => 'gomarket',
    'livewire/auth/' => 'auth-onboarding',
    'auth/' => 'auth-onboarding',
    'livewire/onboarding/' => 'auth-onboarding',
    'components/cameroon-ribbon' => 'auth-onboarding',
    'profile/' => 'profile',
    'people/' => 'people',
    'pages/' => 'static-pages',
    'legal/' => 'static-pages',
    'components/legal/' => 'static-pages',
    'partials/site-nav' => 'public-landing',
    'home.blade.php' => 'home',
    'feed.blade.php' => 'home',
    'partials/home-stories' => 'home',
    'livewire/notifications/' => 'shell',
    'livewire/location-tracker' => 'shell',
    'partials/' => 'shell',
    'components/layouts/' => 'shell',
    'components/' => 'shared',
    'livewire/ai/' => 'kamer-ai',
    'livewire/solidarity/' => 'solidarity',
    'modules/' => 'coming-soon',
    'pwa/' => 'pwa',
    'emails/' => 'email',
];

/** Modules the iPhone app does not have. Counted, but kept apart. */
const OUT_OF_SCOPE_MODULES = ['admin', 'email', 'solidarity'];

/**
 * Names for icons that are not Heroicons, keyed by the first 10 hex of the
 * geometry hash (printed in the report). `kind` is `custom` for a one-colour
 * glyph that takes the text colour, `brand` for anything that carries its own
 * colours — flags, logos, the ribbon, illustrations. A brand icon must not be
 * recoloured.
 */
const MANUAL_NAMES = [
];

/** Heroicons v1 names that v2 renamed. Used to name a v1 copy by its v2 name. */
const V1_TO_V2 = [
    'adjustments' => 'adjustments-vertical', 'annotation' => 'chat-bubble-bottom-center-text',
    'archive' => 'archive-box', 'arrow-circle-down' => 'arrow-down-circle', 'arrow-circle-left' => 'arrow-left-circle',
    'arrow-circle-right' => 'arrow-right-circle', 'arrow-circle-up' => 'arrow-up-circle',
    'arrow-narrow-down' => 'arrow-long-down', 'arrow-narrow-left' => 'arrow-long-left',
    'arrow-narrow-right' => 'arrow-long-right', 'arrow-narrow-up' => 'arrow-long-up',
    'arrow-sm-down' => 'arrow-small-down', 'arrow-sm-left' => 'arrow-small-left',
    'arrow-sm-right' => 'arrow-small-right', 'arrow-sm-up' => 'arrow-small-up',
    'arrows-expand' => 'arrows-pointing-out', 'badge-check' => 'check-badge', 'ban' => 'no-symbol',
    'bookmark-alt' => 'bookmark-square', 'cash' => 'banknotes', 'chart-square-bar' => 'chart-bar-square',
    'chat' => 'chat-bubble-oval-left-ellipsis', 'chat-alt' => 'chat-bubble-left-ellipsis',
    'chat-alt-2' => 'chat-bubble-left-right', 'chip' => 'cpu-chip', 'clipboard-check' => 'clipboard-document-check',
    'clipboard-copy' => 'clipboard-document', 'clipboard-list' => 'clipboard-document-list',
    'cloud-download' => 'cloud-arrow-down', 'cloud-upload' => 'cloud-arrow-up', 'code' => 'code-bracket',
    'cog' => 'cog-6-tooth', 'collection' => 'rectangle-stack', 'color-swatch' => 'swatch',
    'cursor-click' => 'cursor-arrow-rays', 'database' => 'circle-stack', 'desktop-computer' => 'computer-desktop',
    'device-mobile' => 'device-phone-mobile', 'device-tablet' => 'device-tablet',
    'document-add' => 'document-plus', 'document-download' => 'document-arrow-down',
    'document-remove' => 'document-minus', 'document-report' => 'document-chart-bar',
    'document-search' => 'document-magnifying-glass', 'dots-circle-horizontal' => 'ellipsis-horizontal-circle',
    'dots-horizontal' => 'ellipsis-horizontal', 'dots-vertical' => 'ellipsis-vertical',
    'download' => 'arrow-down-tray', 'duplicate' => 'square-2-stack', 'emoji-happy' => 'face-smile',
    'emoji-sad' => 'face-frown', 'exclamation' => 'exclamation-triangle', 'external-link' => 'arrow-top-right-on-square',
    'eye-off' => 'eye-slash', 'fast-forward' => 'forward', 'filter' => 'funnel', 'folder-add' => 'folder-plus',
    'folder-download' => 'folder-arrow-down', 'folder-remove' => 'folder-minus', 'globe' => 'globe-americas',
    'hand' => 'hand-raised', 'inbox-in' => 'inbox-arrow-down', 'library' => 'building-library',
    'lightning-bolt' => 'bolt', 'location-marker' => 'map-pin', 'login' => 'arrow-right-on-rectangle',
    'logout' => 'arrow-left-on-rectangle', 'mail' => 'envelope', 'mail-open' => 'envelope-open',
    'menu' => 'bars-3', 'menu-alt-1' => 'bars-3-center-left', 'menu-alt-2' => 'bars-3-bottom-left',
    'menu-alt-3' => 'bars-3-bottom-right', 'menu-alt-4' => 'bars-2', 'minus-sm' => 'minus-small',
    'music-note' => 'musical-note', 'office-building' => 'building-office', 'pencil-alt' => 'pencil-square',
    'phone-incoming' => 'phone-arrow-down-left', 'phone-missed-call' => 'phone-x-mark',
    'phone-outgoing' => 'phone-arrow-up-right', 'photograph' => 'photo', 'plus-sm' => 'plus-small',
    'puzzle' => 'puzzle-piece', 'qrcode' => 'qr-code', 'receipt-tax' => 'receipt-percent',
    'refresh' => 'arrow-path', 'reply' => 'arrow-uturn-left', 'rewind' => 'backward', 'save' => 'arrow-down-on-square',
    'save-as' => 'arrow-down-on-square-stack', 'search' => 'magnifying-glass', 'search-circle' => 'magnifying-glass-circle',
    'selector' => 'chevron-up-down', 'sort-ascending' => 'bars-arrow-up', 'sort-descending' => 'bars-arrow-down',
    'speakerphone' => 'megaphone', 'status-offline' => 'signal-slash', 'status-online' => 'signal',
    'support' => 'lifebuoy', 'switch-horizontal' => 'arrows-right-left', 'switch-vertical' => 'arrows-up-down',
    'table' => 'table-cells', 'template' => 'rectangle-group', 'terminal' => 'command-line',
    'thumb-down' => 'hand-thumb-down', 'thumb-up' => 'hand-thumb-up', 'translate' => 'language',
    'trending-down' => 'arrow-trending-down', 'trending-up' => 'arrow-trending-up', 'upload' => 'arrow-up-tray',
    'user-add' => 'user-plus', 'user-remove' => 'user-minus', 'view-boards' => 'view-columns',
    'view-grid' => 'squares-2x2', 'view-grid-add' => 'squares-plus', 'view-list' => 'queue-list',
    'volume-off' => 'speaker-x-mark', 'volume-up' => 'speaker-wave', 'x' => 'x-mark',
    'zoom-in' => 'magnifying-glass-plus', 'zoom-out' => 'magnifying-glass-minus',
];

/**
 * Code points in U+2190–U+2BFF with Emoji_Presentation=Yes (Unicode
 * emoji-data.txt): drawn as colour emoji by default, with or without VS16.
 */
const EMOJI_PRESENTATION_SYMBOLS = [
    0x231A, 0x231B, 0x23E9, 0x23EA, 0x23EB, 0x23EC, 0x23F0, 0x23F3, 0x25FD, 0x25FE,
    0x2614, 0x2615, 0x2648, 0x2649, 0x264A, 0x264B, 0x264C, 0x264D, 0x264E, 0x264F,
    0x2650, 0x2651, 0x2652, 0x2653, 0x267F, 0x2693, 0x26A1, 0x26AA, 0x26AB, 0x26BD,
    0x26BE, 0x26C4, 0x26C5, 0x26CE, 0x26D4, 0x26EA, 0x26F2, 0x26F3, 0x26F5, 0x26FA,
    0x26FD, 0x2705, 0x270A, 0x270B, 0x2728, 0x274C, 0x274E, 0x2753, 0x2754, 0x2755,
    0x2757, 0x2795, 0x2796, 0x2797, 0x27B0, 0x27BF, 0x2B1B, 0x2B1C, 0x2B50, 0x2B55,
];

/** Tailwind v4 spacing unit. w-5 is 5 x 0.25rem. */
const TW_SPACING_PX = 4;

const VOID_TAGS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

/** Attributes that only exist for the browser or the framework. */
function isFrameworkAttr(string $name): bool
{
    return $name === 'class' || $name === 'role' || $name === 'focusable' || $name === 'tabindex'
        || $name === 'xmlns' || $name === 'version' || $name === 'title'
        || str_starts_with($name, 'aria-') || str_starts_with($name, 'data-')
        || str_starts_with($name, 'x-') || str_starts_with($name, ':') || str_starts_with($name, '@')
        || str_starts_with($name, 'wire:') || str_starts_with($name, 'xmlns:');
}

const PAINT_ATTRS = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule'];
const GEOMETRY_ATTRS = ['d', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'width', 'height', 'x1', 'y1', 'x2', 'y2', 'points', 'transform'];

// ─────────────────────────────────────────────────────────────────────────────
// Path geometry
// ─────────────────────────────────────────────────────────────────────────────

const PATH_ARGS = ['M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4, 'Q' => 4, 'T' => 2, 'A' => 7, 'Z' => 0];

/**
 * Rewrite SVG path data as absolute M, L, C, Q, A and Z only.
 *
 * H/V become L, S/T become C/Q with the reflected control point, relative
 * becomes absolute, and an implicit move after Z is made explicit. Two
 * encodings of one shape come out identical, which is the whole point.
 *
 * @return ?list<list<string|float>>  null if the data is not parseable
 */
function normalisePath(string $d): ?array
{
    $ops = [];
    $i = 0;
    $n = strlen($d);
    $cx = $cy = $sx = $sy = 0.0;
    $cmd = null;
    $lastC = $lastQ = null;
    $needMove = false;

    $skip = function () use ($d, $n, &$i) {
        while ($i < $n && (ctype_space($d[$i]) || $d[$i] === ',')) { $i++; }
    };

    while (true) {
        $skip();
        if ($i >= $n) { break; }
        $ch = $d[$i];

        if (ctype_alpha($ch)) {
            if (! isset(PATH_ARGS[strtoupper($ch)])) { return null; }
            $cmd = $ch;
            $i++;
            if (strtoupper($cmd) === 'Z') {
                $ops[] = ['Z'];
                $cx = $sx;
                $cy = $sy;
                $lastC = $lastQ = null;
                $needMove = true;
            }
            continue;
        }
        if ($cmd === null) { return null; }

        $U = strtoupper($cmd);
        $rel = $cmd !== $U;
        $count = PATH_ARGS[$U];
        if ($count === 0) { return null; }

        $a = [];
        for ($k = 0; $k < $count; $k++) {
            $skip();
            if ($U === 'A' && ($k === 3 || $k === 4)) {
                if ($i < $n && ($d[$i] === '0' || $d[$i] === '1')) { $a[] = (float) $d[$i]; $i++; continue; }
                return null;
            }
            if (! preg_match('/\G[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $d, $m, 0, $i)) { return null; }
            $a[] = (float) $m[0];
            $i += strlen($m[0]);
        }

        if ($U !== 'M' && $needMove) {
            $ops[] = ['M', $sx, $sy];
        }
        $needMove = false;

        $ox = $rel ? $cx : 0.0;
        $oy = $rel ? $cy : 0.0;

        switch ($U) {
            case 'M':
                $cx = $a[0] + $ox; $cy = $a[1] + $oy;
                $sx = $cx; $sy = $cy;
                $ops[] = ['M', $cx, $cy];
                $cmd = $rel ? 'l' : 'L';
                $lastC = $lastQ = null;
                break;
            case 'L':
                $cx = $a[0] + $ox; $cy = $a[1] + $oy;
                $ops[] = ['L', $cx, $cy];
                $lastC = $lastQ = null;
                break;
            case 'H':
                $cx = $a[0] + $ox;
                $ops[] = ['L', $cx, $cy];
                $lastC = $lastQ = null;
                break;
            case 'V':
                $cy = $a[0] + $oy;
                $ops[] = ['L', $cx, $cy];
                $lastC = $lastQ = null;
                break;
            case 'C':
                $x1 = $a[0] + $ox; $y1 = $a[1] + $oy; $x2 = $a[2] + $ox; $y2 = $a[3] + $oy;
                $cx = $a[4] + $ox; $cy = $a[5] + $oy;
                $ops[] = ['C', $x1, $y1, $x2, $y2, $cx, $cy];
                $lastC = [$x2, $y2]; $lastQ = null;
                break;
            case 'S':
                [$x1, $y1] = $lastC ? [2 * $cx - $lastC[0], 2 * $cy - $lastC[1]] : [$cx, $cy];
                $x2 = $a[0] + $ox; $y2 = $a[1] + $oy;
                $cx = $a[2] + $ox; $cy = $a[3] + $oy;
                $ops[] = ['C', $x1, $y1, $x2, $y2, $cx, $cy];
                $lastC = [$x2, $y2]; $lastQ = null;
                break;
            case 'Q':
                $x1 = $a[0] + $ox; $y1 = $a[1] + $oy;
                $cx = $a[2] + $ox; $cy = $a[3] + $oy;
                $ops[] = ['Q', $x1, $y1, $cx, $cy];
                $lastQ = [$x1, $y1]; $lastC = null;
                break;
            case 'T':
                [$x1, $y1] = $lastQ ? [2 * $cx - $lastQ[0], 2 * $cy - $lastQ[1]] : [$cx, $cy];
                $cx = $a[0] + $ox; $cy = $a[1] + $oy;
                $ops[] = ['Q', $x1, $y1, $cx, $cy];
                $lastQ = [$x1, $y1]; $lastC = null;
                break;
            case 'A':
                $cx = $a[5] + $ox; $cy = $a[6] + $oy;
                $ops[] = ['A', abs($a[0]), abs($a[1]), $a[2], $a[3], $a[4], $cx, $cy];
                $lastC = $lastQ = null;
                break;
        }
    }

    return $ops;
}

function fmtNum(float $v, int $dp = 2): string
{
    $s = number_format(round($v, $dp), $dp, '.', '');
    return $s === '-' . number_format(0, $dp, '.', '') ? number_format(0, $dp, '.', '') : $s;
}

function opsKey(array $ops): string
{
    $out = [];
    foreach ($ops as $op) {
        $parts = [$op[0]];
        foreach (array_slice($op, 1) as $v) { $parts[] = fmtNum($v); }
        $out[] = implode(' ', $parts);
    }
    return implode(' ', $out);
}

/** Letters only: the shape of a path, for finding Heroicons candidates fast. */
function opsSignature(array $ops): string
{
    return implode('', array_map(fn ($op) => $op[0], $ops));
}

function opsNumbers(array $ops): array
{
    $nums = [];
    foreach ($ops as $op) { foreach (array_slice($op, 1) as $v) { $nums[] = $v; } }
    return $nums;
}

/**
 * Any drawable element as path ops, so a <circle> and a <line> can be
 * compared with a library that draws the same thing. Null for anything that
 * is not plain geometry (text, use, image) or has a transform.
 */
function shapeOps(string $tag, array $a): ?array
{
    if (isset($a['transform'])) { return null; }
    $f = fn ($k, $d = 0.0) => isset($a[$k]) && is_numeric($a[$k]) ? (float) $a[$k] : $d;
    switch ($tag) {
        case 'path':
            return normalisePath($a['d'] ?? '');
        case 'circle':
        case 'ellipse':
            $cx = $f('cx'); $cy = $f('cy');
            $rx = $tag === 'circle' ? $f('r') : $f('rx');
            $ry = $tag === 'circle' ? $f('r') : $f('ry', $rx);
            return [['M', $cx - $rx, $cy], ['A', $rx, $ry, 0.0, 1.0, 0.0, $cx + $rx, $cy], ['A', $rx, $ry, 0.0, 1.0, 0.0, $cx - $rx, $cy], ['Z']];
        case 'line':
            return [['M', $f('x1'), $f('y1')], ['L', $f('x2'), $f('y2')]];
        case 'polyline':
        case 'polygon':
            $pts = array_map('floatval', preg_split('/[\s,]+/', trim($a['points'] ?? '')));
            if (count($pts) < 4) { return null; }
            $ops = [['M', $pts[0], $pts[1]]];
            for ($i = 2; $i + 1 < count($pts); $i += 2) { $ops[] = ['L', $pts[$i], $pts[$i + 1]]; }
            if ($tag === 'polygon') { $ops[] = ['Z']; }
            return $ops;
        case 'rect':
            $x = $f('x'); $y = $f('y'); $w = $f('width'); $h = $f('height');
            $rx = $f('rx', $f('ry')); $ry = $f('ry', $rx);
            if ($rx <= 0) {
                return [['M', $x, $y], ['L', $x + $w, $y], ['L', $x + $w, $y + $h], ['L', $x, $y + $h], ['Z']];
            }
            return [
                ['M', $x + $rx, $y], ['L', $x + $w - $rx, $y], ['A', $rx, $ry, 0.0, 0.0, 1.0, $x + $w, $y + $ry],
                ['L', $x + $w, $y + $h - $ry], ['A', $rx, $ry, 0.0, 0.0, 1.0, $x + $w - $rx, $y + $h],
                ['L', $x + $rx, $y + $h], ['A', $rx, $ry, 0.0, 0.0, 1.0, $x, $y + $h - $ry],
                ['L', $x, $y + $ry], ['A', $rx, $ry, 0.0, 0.0, 1.0, $x + $rx, $y], ['Z'],
            ];
    }
    return null;
}

/**
 * The drawing as an order-free list of subpaths.
 *
 * Libraries reorder elements between releases (Lucide's search draws the
 * handle before the circle; older copies the other way round), and hand
 * copies merge several <path>s into one. Splitting at every M and sorting
 * makes all of those the same drawing.
 *
 * @param list<list<array>> $elementOps
 * @return array{sig: string, nums: list<float>}
 */
function canonicalOps(array $elementOps): array
{
    $subs = [];
    foreach ($elementOps as $ops) {
        $cur = [];
        foreach ($ops as $op) {
            if ($op[0] === 'M' && $cur) { $subs[] = $cur; $cur = []; }
            $cur[] = $op;
        }
        if ($cur) { $subs[] = $cur; }
    }
    $keyed = [];
    foreach ($subs as $s) {
        $nums = opsNumbers($s);
        $keyed[] = [opsSignature($s) . ':' . implode(',', array_map(fn ($v) => (string) (int) round($v), $nums)), opsSignature($s), $nums];
    }
    usort($keyed, fn ($a, $b) => strcmp($a[0], $b[0]));
    return [
        'sig' => implode('|', array_column($keyed, 1)),
        'nums' => array_merge([], ...array_column($keyed, 2)),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Markup
// ─────────────────────────────────────────────────────────────────────────────

/** Attributes of one tag, quote-aware. Blade inside a value stays verbatim. */
function parseAttrs(string $s): array
{
    $attrs = [];
    preg_match_all('/([^\s=\/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/s', $s, $m, PREG_SET_ORDER);
    foreach ($m as $row) {
        $name = $row[1];
        if (str_starts_with($name, '{{') || str_starts_with($name, '}}')) { continue; }
        $val = $row[2] ?? '';
        if (($row[3] ?? '') !== '') { $val = $row[3]; }
        if (($row[4] ?? '') !== '') { $val = $row[4]; }
        $attrs[$name] = $val;
    }
    return $attrs;
}

/** Same length, newlines kept, so offsets still give the right line. */
function blank(string $s): string
{
    return preg_replace('/[^\n]/', ' ', $s);
}

function lineAt(string $s, int $offset): int
{
    return substr_count($s, "\n", 0, $offset) + 1;
}

/**
 * Walk the view's tags and note, for each <svg>, the classes of the elements
 * around it. Needed because a rule like `.yard-ctx-item svg { width: ... }`
 * sizes an icon from its parent, and app.css beats the icon's own classes.
 *
 * @return array<int, list<array{tag: string, class: string, attrs: array}>>  svg offset => ancestors, outermost first
 */
function svgAncestors(string $src): array
{
    $out = [];
    $stack = [];
    $n = strlen($src);
    $i = 0;

    while (($i = strpos($src, '<', $i)) !== false) {
        if ($i + 1 >= $n) { break; }
        $next = $src[$i + 1];
        if (! (ctype_alpha($next) || $next === '/')) { $i++; continue; }

        // Read to the closing '>' outside quotes and outside {{ }}.
        $j = $i + 1;
        $quote = null;
        while ($j < $n) {
            $c = $src[$j];
            if ($quote) {
                if ($c === $quote) { $quote = null; }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '{' && ($src[$j + 1] ?? '') === '{') {
                $end = strpos($src, '}}', $j);
                $j = $end === false ? $n : $end + 1;
            } elseif ($c === '>') {
                break;
            }
            $j++;
        }
        $tagText = substr($src, $i, $j - $i + 1);
        $closing = $next === '/';
        if (! preg_match('/^<\/?([a-zA-Z][\w:.-]*)/', $tagText, $tm)) { $i++; continue; }
        $tag = strtolower($tm[1]);

        if ($closing) {
            for ($k = count($stack) - 1; $k >= 0; $k--) {
                if ($stack[$k]['tag'] === $tag) { array_splice($stack, $k); break; }
            }
            $i = $j + 1;
            continue;
        }

        $attrs = parseAttrs(substr($tagText, strlen($tm[0]), -1));
        $selfClosing = str_ends_with(rtrim(substr($tagText, 0, -1)), '/');

        if ($tag === 'svg') {
            $out[$i] = $stack;
            $end = stripos($src, '</svg>', $j);
            $i = $end === false ? $n : $end + 6;
            continue;
        }
        if ($tag === 'script' || $tag === 'style') {
            // Their bodies are not markup. An svg written into a JS string is
            // still found by the svg scan; it just gets this element's ancestors.
            $end = stripos($src, '</' . $tag, $j);
            $body = $end === false ? '' : substr($src, $j + 1, $end - $j - 1);
            $ctx = array_merge($stack, [['tag' => $tag, 'class' => '', 'attrs' => []]]);
            $off = 0;
            while (($p = stripos($body, '<svg', $off)) !== false) {
                $out[$j + 1 + $p] = $ctx;
                $off = $p + 4;
            }
            $i = $end === false ? $n : $end;
            continue;
        }
        if (! $selfClosing && ! in_array($tag, VOID_TAGS, true)) {
            $stack[] = ['tag' => $tag, 'class' => $attrs['class'] ?? '', 'attrs' => $attrs];
        }
        $i = $j + 1;
    }

    return $out;
}

// ─────────────────────────────────────────────────────────────────────────────
// Sizes
// ─────────────────────────────────────────────────────────────────────────────

function cssLenPx(string $v): ?float
{
    $v = trim($v);
    if (preg_match('/^(-?[\d.]+)px$/', $v, $m)) { return (float) $m[1]; }
    if (preg_match('/^(-?[\d.]+)rem$/', $v, $m)) { return (float) $m[1] * 16; }
    return null;
}

/**
 * Every rule that sets an svg's width or height, from app.css and the views'
 * own <style> blocks. Only base and max-width (phone) rules; hover and other
 * state rules are left out.
 *
 * @return list<array{ancestor: ?string, child: bool, self: ?string, w: ?float, h: ?float, src: string}>
 */
function svgSizeRules(): array
{
    $sources = ['resources/css/app.css' => file_get_contents(CSS)];
    foreach (viewFiles() as $rel => $path) {
        $s = file_get_contents($path);
        $s = preg_replace("/@push\(\s*['\"]styles['\"]\s*\).*?@endpush/s", '', $s);
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $s, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $blk) {
                $sources['resources/views/' . $rel . '@' . lineAt($s, $blk[1])] = $blk[0];
            }
        }
    }

    $rules = [];
    foreach ($sources as $name => $css) {
        $css = preg_replace_callback('#/\*.*?\*/#s', fn ($m) => blank($m[0]), $css);
        $media = [];
        $depth = 0;
        $buf = '';
        $bufStart = 0;
        $len = strlen($css);
        for ($i = 0; $i < $len; $i++) {
            $c = $css[$i];
            if ($c === '{') {
                $head = trim($buf);
                if (str_starts_with($head, '@')) {
                    $media[$depth] = $head;
                    $depth++;
                    $buf = '';
                    continue;
                }
                $end = strpos($css, '}', $i);
                $body = substr($css, $i + 1, $end - $i - 1);
                $mq = implode(' ', array_slice($media, 0, $depth));
                $phone = $mq === '' || (str_contains($mq, 'max-width') && ! str_contains($mq, 'min-width'));
                if ($phone && preg_match_all('/(?:^|;)\s*(width|height)\s*:\s*([^;]+)/', $body, $dm, PREG_SET_ORDER)) {
                    $w = $h = null;
                    foreach ($dm as $d) {
                        if ($d[1] === 'width') { $w = cssLenPx($d[2]); } else { $h = cssLenPx($d[2]); }
                    }
                    if ($w !== null || $h !== null) {
                        foreach (explode(',', $head) as $sel) {
                            $sel = trim(preg_replace('/^(body)?\.cn-app\s+/', '', trim($sel)));
                            if (str_contains($sel, ':')) { continue; }
                            $line = lineAt($css, $bufStart);
                            if (preg_match('/^\.([\w-]+)\s*(>)?\s*svg$/', $sel, $sm)) {
                                $rules[] = ['ancestor' => $sm[1], 'child' => ($sm[2] ?? '') === '>', 'self' => null, 'w' => $w, 'h' => $h, 'src' => "$name:$line", 'selector' => $sel];
                            } elseif (preg_match('/^(?:svg)?\.([\w-]+)$/', $sel, $sm)) {
                                $rules[] = ['ancestor' => null, 'child' => false, 'self' => $sm[1], 'w' => $w, 'h' => $h, 'src' => "$name:$line", 'selector' => $sel];
                            }
                        }
                    }
                }
                $i = $end;
                $buf = '';
                $bufStart = $i + 1;
                continue;
            }
            if ($c === '}') {
                if ($depth > 0) { $depth--; unset($media[$depth]); }
                $buf = '';
                $bufStart = $i + 1;
                continue;
            }
            if ($buf === '') { $bufStart = $i; }
            $buf .= $c;
        }
    }
    return $rules;
}

/** `w-5` -> ['w', 20.0]. Arbitrary values in px or rem; em is kept as a string. */
function twSize(string $tok): ?array
{
    $tok = ltrim($tok, '!');
    if (preg_match('/^(w|h|size)-(\d+(?:\.\d+)?)$/', $tok, $m)) { return [$m[1], (float) $m[2] * TW_SPACING_PX]; }
    if (preg_match('/^(w|h|size)-px$/', $tok, $m)) { return [$m[1], 1.0]; }
    if (preg_match('/^(w|h|size)-\[(\d*\.?\d+)px\]$/', $tok, $m)) { return [$m[1], (float) $m[2]]; }
    if (preg_match('/^(w|h|size)-\[(\d*\.?\d+)rem\]$/', $tok, $m)) { return [$m[1], (float) $m[2] * 16]; }
    if (preg_match('/^(w|h|size)-\[(\d*\.?\d+em)\]$/', $tok, $m)) { return [$m[1], $m[2]]; }
    if (preg_match('/^(w|h|size)-(full|auto|fit|screen)$/', $tok, $m)) { return [$m[1], $m[2]]; }
    return null;
}

function sizeLabel($w, $h): string
{
    $f = fn ($v) => $v === null ? '?' : (is_float($v) ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : $v);
    if ($w === null && $h === null) { return 'unsized'; }
    return $f($w) . 'x' . $f($h);
}

// ─────────────────────────────────────────────────────────────────────────────
// Views
// ─────────────────────────────────────────────────────────────────────────────

/** @return array<string, string>  path relative to resources/views => absolute path */
function viewFiles(): array
{
    static $files = null;
    if ($files !== null) { return $files; }
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(VIEWS, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! str_ends_with($f->getFilename(), '.blade.php')) { continue; }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(VIEWS) + 1));
        if (in_array($rel, EXCLUDED_VIEWS, true)) { continue; }
        $files[$rel] = $f->getPathname();
    }
    ksort($files);
    return $files;
}

function moduleOf(string $rel): string
{
    static $sorted = null;
    if ($sorted === null) {
        $sorted = MODULES;
        uksort($sorted, fn ($a, $b) => strlen($b) <=> strlen($a));
    }
    foreach ($sorted as $prefix => $mod) {
        if (str_starts_with($rel, $prefix)) { return $mod; }
    }
    return 'other';
}

/**
 * `{{ $iconSize }}` -> the string literals assigned to $iconSize in the file.
 * A ternary gives several; all are returned, because which one renders on a
 * phone is a runtime question.
 */
function resolveBladeVars(string $value, string $src): array
{
    if (! str_contains($value, '{{')) { return [$value]; }
    $variants = [''];
    $pos = 0;
    while (preg_match('/\{\{\s*(.*?)\s*\}\}/s', $value, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $literal = substr($value, $pos, $m[0][1] - $pos);
        $expr = $m[1][0];
        $options = [];
        if (preg_match('/^\$(\w+)$/', $expr, $vm)
            && preg_match_all('/\$' . $vm[1] . '\s*=\s*([^;]+);/', $src, $am)) {
            foreach ($am[1] as $rhs) {
                if (preg_match_all('/\'([^\']*)\'|"([^"]*)"/', $rhs, $lm, PREG_SET_ORDER)) {
                    foreach ($lm as $l) { $options[] = ($l[2] ?? '') !== '' ? $l[2] : $l[1]; }
                }
            }
        } elseif (preg_match_all('/\'([^\']*)\'|"([^"]*)"/', $expr, $lm, PREG_SET_ORDER)) {
            foreach ($lm as $l) { $options[] = ($l[2] ?? '') !== '' ? $l[2] : $l[1]; }
            if (! str_contains($expr, ':') && ! str_contains($expr, '?')) { $options = []; }
        }
        if (! $options) { $options = ['']; }
        $next = [];
        foreach ($variants as $v) {
            foreach (array_unique($options) as $o) { $next[] = $v . $literal . $o; }
        }
        $variants = $next;
        $pos = $m[0][1] + strlen($m[0][0]);
    }
    return array_map(fn ($v) => $v . substr($value, $pos), $variants);
}

/** A Blade ternary in a paint attribute: one state per quoted literal. */
function paintStates(string $value): array
{
    if (! str_contains($value, '{{')) { return [$value]; }
    if (preg_match_all('/\'([^\']*)\'|"([^"]*)"/', $value, $m, PREG_SET_ORDER)) {
        return array_values(array_unique(array_map(fn ($l) => ($l[2] ?? '') !== '' ? $l[2] : $l[1], $m)));
    }
    return [$value];
}

/** Strings in the file that are path data, for `d="{{ $path }}"`. */
function pathLiterals(string $src): array
{
    $out = [];
    if (preg_match_all('/\'(M[-\d\s.,MmLlHhVvCcSsQqTtAaZz]{8,})\'/', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $hit) {
            if (normalisePath($hit[0])) { $out[] = ['d' => $hit[0], 'line' => lineAt($src, $hit[1])]; }
        }
    }
    return $out;
}

/** What the icon is for, from the markup around it: helps a human name it. */
function contextHint(array $ancestors, string $after): string
{
    $hint = [];
    foreach (array_reverse(array_slice($ancestors, -3)) as $a) {
        foreach (['aria-label', 'title', ':title', ':aria-label', 'wire:click', '@click', 'href'] as $k) {
            if (! empty($a['attrs'][$k])) { $hint[] = $k . '=' . mb_substr(trim(preg_replace('/[[:space:]]+/', ' ', $a['attrs'][$k])), 0, 60); break 2; }
        }
    }
    $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/\{\{.*?\}\}|@\w+(\([^)]*\))?/s', ' ', mb_substr($after, 0, 400)))));
    if ($text !== '') { $hint[] = 'text: ' . mb_substr($text, 0, 50); }
    return implode(' | ', $hint);
}

// ─────────────────────────────────────────────────────────────────────────────
// One svg
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Parse one svg's inner markup into kept elements.
 *
 * @return array{elements: list<array>, dynamic: ?string, colours: list<string>, has_text: bool}
 */
function parseSvgBody(string $inner): array
{
    $elements = [];
    $dynamic = null;
    $colours = [];
    $hasText = false;
    $depth = 0;

    preg_match_all('/<(\/?)([a-zA-Z][\w:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)(\/?)>|([^<]+)/s', $inner, $m, PREG_SET_ORDER);
    foreach ($m as $tok) {
        if (($tok[5] ?? '') !== '') {
            $t = trim($tok[5]);
            if ($t !== '' && $elements && in_array(end($elements)['tag'], ['text', 'tspan'], true)) {
                $elements[count($elements) - 1]['text'] = $t;
                $hasText = true;
            }
            continue;
        }
        $tag = $tok[2];
        if ($tag === 'title' || $tag === 'desc') { continue; }
        if ($tok[1] === '/') {
            $depth = max(0, $depth - 1);
            $elements[] = ['tag' => '/' . $tag, 'attrs' => [], 'depth' => $depth];
            continue;
        }
        $attrs = [];
        foreach (parseAttrs($tok[3]) as $k => $v) {
            // A child's opacity-NN utility is part of the picture: the Tailwind
            // spinner is an opacity-25 ring under an opacity-75 arc, and without
            // them both draw solid and the arc disappears into the ring.
            if ($k === 'class' && preg_match('/(?:^|\s)opacity-(\d{1,3})(?:\s|$)/', $v, $om)) {
                $attrs['opacity'] = fmtNum(min(100, (int) $om[1]) / 100);
            }
            if (isFrameworkAttr($k)) { continue; }
            if (str_contains($v, '{{') || str_contains($v, '{!!') || str_contains($v, '${')) {
                if (in_array($k, GEOMETRY_ATTRS, true)) { $dynamic = "$tag $k=\"$v\""; }
                // An id or a url(#id) carrying a per-instance suffix: fix it.
                $v = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', 'u', $v);
            }
            if (in_array($k, ['fill', 'stroke', 'stop-color'], true)
                && ! in_array(strtolower($v), ['none', 'currentcolor', 'transparent', ''], true)
                && ! str_starts_with($v, 'url(')) {
                $colours[] = strtolower($v);
            }
            $attrs[$k] = $v;
        }
        $self = $tok[4] === '/' || in_array($tag, ['path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'stop', 'use'], true) && $tok[4] === '/';
        $elements[] = ['tag' => $tag, 'attrs' => $attrs, 'depth' => $depth, 'self' => $tok[4] === '/'];
        if ($tok[4] !== '/') { $depth++; }
    }

    return ['elements' => $elements, 'dynamic' => $dynamic, 'colours' => array_values(array_unique($colours)), 'has_text' => $hasText];
}

/** Geometry of the kept elements as one comparable string, and the path ops. */
function geometryOf(array $elements, bool $brand): array
{
    $parts = [];
    $ops = [];
    $plain = true;
    foreach ($elements as $el) {
        $a = $el['attrs'];
        $tag = $el['tag'];
        if (str_starts_with($tag, '/')) { $parts[] = $tag; continue; }
        $g = [$tag];
        if ($tag === 'path') {
            $p = normalisePath($a['d'] ?? '');
            if ($p === null) { $g[] = 'raw:' . ($a['d'] ?? ''); $plain = false; }
            else { $g[] = opsKey($p); $ops[] = $p; }
        } else {
            $shape = shapeOps($tag, $a);
            if ($shape !== null) { $ops[] = $shape; }
            elseif (! in_array($tag, ['g', 'defs'], true)) { $plain = false; }
            foreach (GEOMETRY_ATTRS as $k) {
                if (! isset($a[$k])) { continue; }
                $v = $a[$k];
                if ($k === 'points') {
                    $v = implode(' ', array_map(fn ($x) => fmtNum((float) $x), preg_split('/[\s,]+/', trim($v))));
                } elseif (is_numeric($v)) {
                    $v = fmtNum((float) $v);
                }
                $g[] = "$k=$v";
            }
        }
        // A child that paints differently draws a different picture: a dot
        // filled inside an outline icon, a stroke on a filled one.
        foreach (['fill', 'stroke', 'opacity', 'fill-opacity'] as $k) {
            if (isset($a[$k])) { $g[] = "$k=" . ($brand ? strtolower($a[$k]) : (in_array(strtolower($a[$k]), ['none', '0'], true) ? 'none' : 'paint')); }
        }
        if ($brand) {
            foreach ($a as $k => $v) {
                if (! in_array($k, GEOMETRY_ATTRS, true) && ! in_array($k, ['fill', 'stroke', 'opacity', 'fill-opacity'], true)) { $g[] = "$k=$v"; }
            }
            if (isset($el['text'])) { $g[] = 'text=' . $el['text']; }
        }
        $parts[] = implode(' ', $g);
    }
    // ops: one list per drawable element, for matching against libraries.
    return ['key' => implode("\n", $parts), 'ops' => $plain && $ops ? $ops : null];
}

// ─────────────────────────────────────────────────────────────────────────────
// Icon libraries: Heroicons, Lucide, Feather
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Every icon in the packages given, indexed by the letters of its canonical
 * subpaths so a candidate is found without comparing all of them.
 *
 * Heroicons first, because that is what the views mostly copied, and in three
 * generations: some icons only match 2.0.x, whose drawings 2.1 moved
 * (user-plus starts at x=19 in 2.0.18 and at x=18 in 2.2.0). Lucide and its
 * parent Feather account for most of the rest — the search glass with a
 * <circle r=8>, the copy, clock and camera glyphs.
 *
 * @param list<string> $packages  unpacked package roots
 */
function libraryIndex(array $packages): array
{
    $index = [];
    foreach ($packages as $pkg) {
        $meta = json_decode((string) @file_get_contents($pkg . '/package.json'), true) ?: [];
        $version = $meta['version'] ?? '0';
        $sets = match ($meta['name'] ?? '') {
            'heroicons' => (int) $version >= 2
                ? ['v2/24/outline' => '24/outline', 'v2/24/solid' => '24/solid', 'v2/20/solid' => '20/solid', 'v2/16/solid' => '16/solid']
                : ['v1/outline' => 'outline', 'v1/solid' => 'solid'],
            'lucide-static' => ['lucide' => 'icons'],
            'feather-icons' => ['feather' => 'dist/icons'],
            '@material-design-icons/svg' => ['material/filled' => 'filled', 'material/outlined' => 'outlined', 'material/round' => 'round', 'material/sharp' => 'sharp'],
            '@mdi/svg' => ['mdi' => 'svg'],
            default => [],
        };
        $library = $meta['name'] ?? '?';
        foreach ($sets as $set => $sub) {
            foreach (glob("$pkg/$sub/*.svg") ?: [] as $file) {
                $svg = file_get_contents($file);
                if (! preg_match('/<svg\b([^>]*)>(.*)<\/svg>/s', $svg, $sm)) { continue; }
                $root = parseAttrs($sm[1]);
                $els = [];
                foreach (parseSvgBody($sm[2])['elements'] as $el) {
                    if (str_starts_with($el['tag'], '/') || in_array($el['tag'], ['g', 'defs'], true)) { continue; }
                    $o = shapeOps($el['tag'], $el['attrs']);
                    if ($o === null) { $els = []; break; }
                    $els[] = $o;
                }
                if (! $els) { continue; }
                $canon = canonicalOps($els);
                $index[$canon['sig']][] = [
                    'library' => $library, 'set' => $set, 'version' => $version, 'name' => basename($file, '.svg'),
                    'viewBox' => preg_replace('/\s+/', ' ', trim($root['viewBox'] ?? '')), 'nums' => $canon['nums'],
                ];
            }
        }
    }
    return $index;
}

function libraryMatch(array $index, array $elementOps, string $viewBox, string $paint): ?array
{
    $canon = canonicalOps($elementOps);
    $best = null;
    foreach ($index[$canon['sig']] ?? [] as $c) {
        if ($c['viewBox'] !== $viewBox) { continue; }
        $max = 0.0;
        foreach ($canon['nums'] as $k => $v) { $max = max($max, abs($v - $c['nums'][$k])); if ($max > 0.06) { break; } }
        if ($max > 0.06) { continue; }
        // Prefer Heroicons v2, then v1, then Lucide, then Feather; then a set
        // whose paint agrees with the icon's; then the newest release.
        $rank = match (true) {
            str_starts_with($c['set'], 'v2') => 0,
            str_starts_with($c['set'], 'v1') => 10,
            $c['set'] === 'lucide' => 20,
            $c['set'] === 'feather' => 30,
            $c['set'] === 'material/filled' => 40,
            str_starts_with($c['set'], 'material') => 41,
            default => 50,
        };
        // Material and MDI are fill-drawn throughout.
        $outlineSet = ! str_contains($c['set'], 'solid') && ! str_starts_with($c['set'], 'material') && $c['set'] !== 'mdi';
        $score = $rank + (($outlineSet xor $paint === 'stroke') ? 5 : 0) + $max;
        if ($best === null || $score < $best[0] - 1e-9
            || (abs($score - $best[0]) < 1e-9 && version_compare($c['version'], $best[1]['version'], '>'))) {
            $best = [$score, $c];
        }
    }
    return $best ? [
        'library' => $best[1]['library'], 'set' => $best[1]['set'],
        'name' => $best[1]['name'], 'version' => $best[1]['version'],
    ] : null;
}

/** A library match -> the asset name. Heroicons v2 names; solid sets get a suffix. */
function libraryAssetName(array $h): string
{
    $name = str_replace('_', '-', $h['name']);
    if (str_starts_with($h['set'], 'v1/')) {
        $name = V1_TO_V2[$name] ?? $name;
    }
    $suffix = match ($h['set']) {
        'v2/24/solid', 'v1/solid' => '-solid',
        'v2/20/solid' => '-mini',
        'v2/16/solid' => '-micro',
        'material/outlined' => '-outlined',
        'material/round' => '-round',
        'material/sharp' => '-sharp',
        default => '',
    };
    return $name . $suffix;
}

function libraryKind(array $h): string
{
    return match ($h['library']) {
        'heroicons' => 'heroicon',
        'lucide-static' => 'lucide',
        'feather-icons' => 'feather',
        '@material-design-icons/svg' => 'material',
        '@mdi/svg' => 'mdi',
        default => 'custom',
    };
}

/** `heroicons@2.0.18 v2/24/outline/user-plus` */
function libraryRef(array $h): string
{
    return $h['library'] . '@' . $h['version'] . ' ' . $h['set'] . '/' . $h['name'];
}

// ─────────────────────────────────────────────────────────────────────────────
// Emoji
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Emoji used where an icon would be. They are not in the svg set but the app
 * has to draw them, and iOS draws them with Apple Color Emoji, which looks
 * nothing like Windows' Segoe or Android's Noto that the web was designed on.
 */
function emojiScan(array $files): array
{
    $re = '/(?:[\x{1F1E6}-\x{1F1FF}]{2}|[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B50}\x{2B55}\x{2190}-\x{21FF}\x{2300}-\x{23FF}])\x{FE0F}?/u';
    $glyphs = [];
    foreach ($files as $rel => $path) {
        $src = file_get_contents($path);
        $src = preg_replace_callback('/\{\{--.*?--\}\}|<!--.*?-->/s', fn ($m) => blank($m[0]), $src);
        $lines = explode("\n", $src);
        foreach ($lines as $ln => $line) {
            if (! preg_match_all($re, $line, $m, PREG_OFFSET_CAPTURE)) { continue; }
            $many = count($m[0]) >= 5;   // an emoji picker row or a flag list, not an icon
            foreach ($m[0] as [$g, $off]) {
                $base = preg_replace('/\x{FE0F}/u', '', $g);
                $before = substr($line, max(0, $off - 40), min(40, $off));
                $after = substr($line, $off + strlen($g), 40);
                if ($many) { $ctx = 'list'; }
                elseif (preg_match("/['\"]icon['\"]\s*=>\s*['\"]$/", $before) || preg_match("/icon\s*:\s*['\"]$/", $before)) { $ctx = 'icon-key'; }
                elseif (preg_match('/>\s*$/', $before) && preg_match('/^\s*</', $after)) { $ctx = 'standalone'; }
                elseif (preg_match('/^\s*[\p{L}{]/u', $after)) { $ctx = 'label'; }
                else { $ctx = 'inline'; }
                $mod = moduleOf($rel);
                $glyphs[$base]['count'] = ($glyphs[$base]['count'] ?? 0) + 1;
                $glyphs[$base]['contexts'][$ctx] = ($glyphs[$base]['contexts'][$ctx] ?? 0) + 1;
                $glyphs[$base]['modules'][$mod] = true;
                $glyphs[$base]['at'][] = $rel . ':' . ($ln + 1) . ($ctx === 'list' ? '' : " ($ctx)");
                // A text symbol is one the font draws in the text colour: in the
                // symbol blocks, never followed by VS16 anywhere, and not one of
                // the Emoji_Presentation=Yes code points there (⭐ ✨ ✅ ❌ ⏳ …),
                // which render as colour emoji even without VS16.
                $cp = mb_ord(mb_substr($base, 0, 1));
                $isText = $cp >= 0x2190 && $cp <= 0x2BFF && ! str_contains($g, "\u{FE0F}") && ! in_array($cp, EMOJI_PRESENTATION_SYMBOLS, true);
                $glyphs[$base]['text_symbol'] = ($glyphs[$base]['text_symbol'] ?? true) && $isText;
            }
        }
    }
    uasort($glyphs, fn ($a, $b) => $b['count'] <=> $a['count']);
    foreach ($glyphs as &$g) {
        $g['modules'] = array_keys($g['modules']);
        sort($g['modules']);
        $g['at'] = array_values(array_unique($g['at']));
    }
    return $glyphs;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────

$opts = getopt('', ['library:']);
$libPackages = (array) ($opts['library'] ?? []);
$libVersions = [];
foreach ($libPackages as $dir) {
    $meta = is_file($dir . '/package.json') ? json_decode(file_get_contents($dir . '/package.json'), true) : null;
    if (! in_array($meta['name'] ?? null, ['heroicons', 'lucide-static', 'feather-icons', '@material-design-icons/svg', '@mdi/svg'], true)) {
        fwrite(STDERR, "--library: not an unpacked heroicons, lucide-static or feather-icons package: $dir\n");
        exit(1);
    }
    $libVersions[] = $meta['name'] . '@' . $meta['version'];
}
$libIndex = libraryIndex($libPackages);
$nameCache = is_file(NAME_CACHE) ? (json_decode(file_get_contents(NAME_CACHE), true)['matches'] ?? []) : [];

$sizeRules = svgSizeRules();
$files = viewFiles();

$groups = [];      // geometry hash => group
$dynamic = [];     // what could not be read as an icon, and why
$scanned = 0;
$occurrences = 0;

foreach ($files as $rel => $path) {
    $raw = file_get_contents($path);
    // Blade comments out, same length, so offsets and lines survive.
    $src = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($m) => blank($m[0]), $raw);
    // @php blocks hold strings, not markup; keep them for literal lookup only.
    $markup = preg_replace_callback('/@php\b(?!\s*\().*?@endphp/s', fn ($m) => blank($m[0]), $src);
    $ancestorsAt = svgAncestors($markup);
    $module = moduleOf($rel);
    $phpBlocks = [];
    if (preg_match_all('/@php\b(?!\s*\().*?@endphp/s', $src, $pb, PREG_OFFSET_CAPTURE)) {
        foreach ($pb[0] as [$text, $at]) { $phpBlocks[] = [$at, $at + strlen($text)]; }
    }

    if (! preg_match_all('/<svg\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/svg>/s', $src, $svgs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($svgs as $svg) {
        $scanned++;
        $offset = $svg[0][1];
        $line = lineAt($src, $offset);

        // An svg written as a PHP string. Rendered only if the variable is.
        foreach ($phpBlocks as [$from, $to]) {
            if ($offset < $from || $offset > $to) { continue; }
            $before = substr($src, $from, $offset - $from);
            $var = preg_match_all('/\$(\w+)\s*=/', $before, $vm) ? end($vm[1]) : null;
            $outside = substr($src, 0, $from) . substr($src, $to);
            if ($var === null || ! preg_match('/\$' . $var . '\b/', $outside)) {
                $dynamic[] = ['view' => $rel, 'line' => $line, 'module' => $module, 'dead' => true,
                    'reason' => 'written into $' . $var . ' inside @php, and $' . $var . ' is never output: dead', 'resolved' => []];
                continue 2;
            }
        }
        $rootAttrs = parseAttrs($svg[1][0]);
        $body = parseSvgBody($svg[2][0]);
        $ancestors = $ancestorsAt[$offset] ?? [];
        $hint = contextHint($ancestors, substr($markup, $offset + strlen($svg[0][0]), 400));

        // ── Path data that is a Blade expression ──
        $bodies = [[$body, $line, null]];
        if ($body['dynamic'] !== null) {
            $literals = pathLiterals($src);
            $entry = ['view' => $rel, 'line' => $line, 'module' => $module, 'reason' => 'geometry is a Blade expression: ' . $body['dynamic']];
            if (preg_match('/^path d="\{\{\s*\$\w+\s*\}\}"$/', $body['dynamic']) && $literals) {
                $entry['resolved'] = array_map(fn ($l) => $rel . ':' . $l['line'], $literals);
                $bodies = [];
                foreach ($literals as $l) {
                    $tpl = $svg[2][0];
                    $tpl = preg_replace('/d="\{\{\s*\$\w+\s*\}\}"/', 'd="' . $l['d'] . '"', $tpl);
                    $bodies[] = [parseSvgBody($tpl), $l['line'], 'path from a literal in this file'];
                }
            } else {
                $entry['resolved'] = [];
                $bodies = [];
            }
            $dynamic[] = $entry;
        }

        // ── States: a Blade ternary in fill / stroke / stroke-width ──
        $states = [[]];
        foreach (['fill', 'stroke', 'stroke-width'] as $k) {
            if (! isset($rootAttrs[$k]) || ! str_contains($rootAttrs[$k], '{{')) { continue; }
            $next = [];
            foreach ($states as $s) {
                foreach (paintStates($rootAttrs[$k]) as $opt) { $next[] = $s + [$k => $opt]; }
            }
            $states = $next;
        }

        // ── Size ──
        $classVariants = resolveBladeVars($rootAttrs['class'] ?? '', $src);
        $sizes = [];
        $responsive = [];
        $classTransforms = [];
        $colourClasses = [];
        foreach ($classVariants as $cv) {
            $w = $h = null;
            $how = null;
            foreach (preg_split('/\s+/', trim($cv)) as $tok) {
                if ($tok === '') { continue; }
                if (preg_match('/^(sm|md|lg|xl|2xl|group-hover|hover|focus):/', $tok)) {
                    if (twSize(substr($tok, strpos($tok, ':') + 1))) { $responsive[$tok] = true; }
                    continue;
                }
                if (preg_match('/^-?(rotate|scale|-?scale-x|-?scale-y|translate)-|^-scale-|^(animate)-/', $tok)) { $classTransforms[$tok] = true; }
                if (preg_match('/^(text|fill|stroke)-(?!xs$|sm$|base$|lg$|[0-9]?xl$|left$|center$|right$|\[\d)/', $tok)) { $colourClasses[$tok] = true; }
                $sz = twSize($tok);
                if (! $sz) { continue; }
                [$axis, $v] = $sz;
                if ($axis === 'w' || $axis === 'size') { $w = $v; }
                if ($axis === 'h' || $axis === 'size') { $h = $v; }
                $how = 'class';
            }
            if ($w === null && $h === null) {
                $aw = isset($rootAttrs['width']) && is_numeric($rootAttrs['width']) ? (float) $rootAttrs['width'] : null;
                $ah = isset($rootAttrs['height']) && is_numeric($rootAttrs['height']) ? (float) $rootAttrs['height'] : null;
                if ($aw !== null || $ah !== null) { $w = $aw; $h = $ah; $how = 'attribute'; }
            }
            // app.css is unlayered and Tailwind's utilities are in a layer,
            // so a hand-written rule wins over the icon's own w-/h- classes.
            $svgClasses = preg_split('/\s+/', trim($cv));
            foreach ($sizeRules as $r) {
                $hit = false;
                if ($r['self'] !== null) {
                    $hit = in_array($r['self'], $svgClasses, true);
                } else {
                    $anc = $r['child'] ? array_slice($ancestors, -1) : $ancestors;
                    foreach ($anc as $a) {
                        if (in_array($r['ancestor'], preg_split('/\s+/', $a['class']), true)) { $hit = true; break; }
                    }
                }
                if ($hit) {
                    if ($r['w'] !== null) { $w = $r['w']; }
                    if ($r['h'] !== null) { $h = $r['h']; }
                    $how = 'css ' . $r['src'] . ' `' . $r['selector'] . '`';
                }
            }
            if (isset($rootAttrs['style']) && preg_match_all('/(width|height)\s*:\s*([^;]+)/', $rootAttrs['style'], $sm, PREG_SET_ORDER)) {
                foreach ($sm as $d) {
                    $px = cssLenPx($d[2]);
                    if ($px === null) { continue; }
                    if ($d[1] === 'width') { $w = $px; } else { $h = $px; }
                    $how = 'inline style';
                }
            }
            $sizes[sizeLabel($w, $h)] = $how ?? 'none';
        }
        if (isset($rootAttrs[':class']) && preg_match('/\b(w|h|size)-[\d\[]/', $rootAttrs[':class'])) {
            $responsive[':class ' . mb_substr($rootAttrs[':class'], 0, 60)] = true;
        }

        foreach ($bodies as [$b, $occLine, $via]) {
            foreach ($states as $state) {
                $root = [];
                foreach (PAINT_ATTRS as $k) {
                    if (isset($state[$k])) { $root[$k] = $state[$k]; }
                    elseif (isset($rootAttrs[$k]) && ! str_contains($rootAttrs[$k], '{{')) { $root[$k] = $rootAttrs[$k]; }
                }
                foreach (['opacity', 'fill-opacity', 'stroke-opacity', 'stroke-dasharray', 'transform', 'overflow'] as $k) {
                    if (isset($rootAttrs[$k]) && ! str_contains($rootAttrs[$k], '{{')) { $root[$k] = $rootAttrs[$k]; }
                }
                $viewBox = trim(preg_replace('/[\s,]+/', ' ', $rootAttrs['viewBox'] ?? ''));
                if ($viewBox === '' && isset($rootAttrs['width'], $rootAttrs['height'])) {
                    $viewBox = '0 0 ' . (float) $rootAttrs['width'] . ' ' . (float) $rootAttrs['height'];
                }

                $rootColours = [];
                foreach (['fill', 'stroke'] as $k) {
                    $v = strtolower($root[$k] ?? '');
                    if ($v !== '' && ! in_array($v, ['none', 'currentcolor', 'transparent'], true) && ! str_starts_with($v, 'url(')) { $rootColours[] = $v; }
                }
                $colours = array_values(array_unique(array_merge($rootColours, $b['colours'])));
                $structural = array_filter($b['elements'], fn ($e) => preg_match('/^(linearGradient|radialGradient|filter|mask|pattern|image|text|use|clipPath)$/', $e['tag']));
                $isBrand = $colours || $structural || $b['has_text'] || $rel === 'components/cameroon-ribbon.blade.php';

                $fillRoot = strtolower($root['fill'] ?? 'black');   // SVG's default fill is black
                $strokeRoot = strtolower($root['stroke'] ?? 'none');
                $paint = $isBrand ? 'brand'
                    : ($strokeRoot !== 'none' && $fillRoot === 'none' ? 'stroke'
                    : ($strokeRoot === 'none' ? 'fill' : 'fill+stroke'));

                $geo = geometryOf(array_values(array_filter($b['elements'], fn ($e) => $e['tag'] !== 'g' || $isBrand)), $isBrand);
                if (! $isBrand) {
                    // Drop <g> wrappers for plain icons: they only regroup.
                    $geo = geometryOf(array_values(array_filter($b['elements'], fn ($e) => ! in_array(ltrim($e['tag'], '/'), ['g'], true))), false);
                }
                if (trim($geo['key']) === '') {
                    $dynamic[] = ['view' => $rel, 'line' => $occLine, 'module' => $module, 'reason' => 'empty svg (no drawable children)', 'resolved' => []];
                    continue;
                }
                $hash = substr(sha1($viewBox . '|' . $paint . '|' . $geo['key']), 0, 10);

                $sig = [];
                foreach (['stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule'] as $k) {
                    $vals = [];
                    if (isset($root[$k])) { $vals[] = $root[$k]; }
                    foreach ($b['elements'] as $e) { if (isset($e['attrs'][$k])) { $vals[] = $e['attrs'][$k]; } }
                    $vals = array_values(array_unique($vals));
                    if ($vals) { $sig[$k] = implode('/', $vals); }
                }
                if ($paint === 'stroke' && ! isset($sig['stroke-width'])) { $sig['stroke-width'] = '1'; }
                $sigKey = json_encode($sig);

                if (! isset($groups[$hash])) {
                    $groups[$hash] = [
                        'hash' => $hash, 'viewBox' => $viewBox, 'paint' => $paint, 'brand' => $isBrand,
                        'colours' => $colours, 'ops' => $geo['ops'], 'variants' => [], 'occurrences' => [],
                    ];
                }
                if (! isset($groups[$hash]['variants'][$sigKey])) {
                    $groups[$hash]['variants'][$sigKey] = ['paint' => $sig, 'root' => $root, 'elements' => $b['elements'], 'count' => 0];
                }
                $groups[$hash]['variants'][$sigKey]['count']++;

                $occ = [
                    'at' => $rel . ':' . $occLine,
                    'module' => $module,
                    'size' => array_keys($sizes),
                    'size_from' => array_values(array_unique(array_values($sizes))),
                ];
                if ($sig) { $occ['paint'] = $sig; }
                if ($state) { $occ['state'] = $state; }
                if ($via) { $occ['via'] = $via; }
                if ($responsive) { $occ['responsive'] = array_keys($responsive); }
                if ($classTransforms) { $occ['class_transforms'] = array_keys($classTransforms); }
                if ($colourClasses) { $occ['colour'] = array_keys($colourClasses); }
                if ($hint !== '') { $occ['hint'] = $hint; }
                $groups[$hash]['occurrences'][] = $occ;
                $occurrences++;
            }
        }
    }
}

// ── Names ───────────────────────────────────────────────────────────────────
$matchesForCache = [];
foreach ($groups as $hash => &$g) {
    $g['match'] = null;
    if (! $g['brand'] && $g['ops']) {
        if ($libIndex) {
            $g['match'] = libraryMatch($libIndex, $g['ops'], $g['viewBox'], $g['paint']);
        } elseif (isset($nameCache[$hash])) {
            $g['match'] = $nameCache[$hash];
        }
    }
    if ($g['match']) { $matchesForCache[$hash] = $g['match']; }
}
unset($g);

// Heroicons v2 claim their names first, then v1, Lucide, Feather, the
// manual names, and last the placeholders.
$rankOf = function (array $g): int {
    if ($g['match']) {
        return match (true) {
            str_starts_with($g['match']['set'], 'v2') => 0,
            str_starts_with($g['match']['set'], 'v1') => 1,
            $g['match']['set'] === 'lucide' => 2,
            $g['match']['set'] === 'feather' => 3,
            str_starts_with($g['match']['set'], 'material') => 4,
            default => 5,
        };
    }
    return isset(MANUAL_NAMES[$g['hash']]) ? 6 : 7;
};
$taken = [];
$order = array_keys($groups);
usort($order, fn ($a, $b) => [$rankOf($groups[$a]), -count($groups[$a]['occurrences']), $a] <=> [$rankOf($groups[$b]), -count($groups[$b]['occurrences']), $b]);
$unnamed = [];
foreach ($order as $hash) {
    $g = &$groups[$hash];
    if ($g['match']) {
        $base = libraryAssetName($g['match']);
        $g['kind'] = libraryKind($g['match']);
        if (isset($taken[$base])) {
            $base .= match ($g['kind']) { 'lucide' => '-lucide', 'feather' => '-feather', 'material' => '-material', 'mdi' => '-mdi', default => str_starts_with($g['match']['set'], 'v1/') ? '-v1' : '' };
        }
    } elseif (isset(MANUAL_NAMES[$hash])) {
        $base = MANUAL_NAMES[$hash]['name'];
        $g['kind'] = MANUAL_NAMES[$hash]['kind'];
        if (! empty(MANUAL_NAMES[$hash]['note'])) { $g['note'] = MANUAL_NAMES[$hash]['note']; }
    } else {
        $g['kind'] = $g['brand'] ? 'brand' : 'custom';
        $base = ($g['brand'] ? 'brand-' : 'glyph-') . $hash;
        $unnamed[] = $hash;
    }
    if ($g['brand']) { $g['kind'] = 'brand'; }
    $name = $base;
    $n = 2;
    while (isset($taken[$name])) { $name = $base . '-' . $n++; }
    $taken[$name] = true;
    $g['name'] = $name;
}
unset($g);

// ── Write the svgs ──────────────────────────────────────────────────────────
function attrString(array $attrs): string
{
    $s = '';
    foreach ($attrs as $k => $v) { $s .= ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES | ENT_XML1) . '"'; }
    return $s;
}

function renderSvg(array $g, array $variant): string
{
    $root = ['xmlns' => 'http://www.w3.org/2000/svg', 'viewBox' => $g['viewBox']];
    foreach ($variant['root'] as $k => $v) { $root[$k] = $v; }
    if ($g['paint'] === 'stroke' && ! isset($root['fill'])) { $root['fill'] = 'none'; }
    $usesXlink = false;
    $lines = [];
    foreach ($variant['elements'] as $el) {
        $pad = str_repeat('  ', $el['depth'] + 1);
        if (str_starts_with($el['tag'], '/')) {
            $lines[] = $pad . '<' . $el['tag'] . '>';
            continue;
        }
        if (! $g['brand'] && $el['tag'] === 'g' && ! $el['attrs']) { continue; }
        if (isset($el['attrs']['xlink:href'])) { $usesXlink = true; }
        if (isset($el['text'])) {
            $lines[] = $pad . '<' . $el['tag'] . attrString($el['attrs']) . '>' . htmlspecialchars($el['text'], ENT_XML1) . '</' . $el['tag'] . '>';
            continue;
        }
        $lines[] = $pad . '<' . $el['tag'] . attrString($el['attrs']) . ($el['self'] ? '/>' : '>');
    }
    // A closing tag whose opener was text-collapsed above is now orphaned.
    $out = [];
    foreach ($lines as $l) {
        if (preg_match('/^\s*<\/(text|tspan)>$/', $l)) { continue; }
        if (preg_match('/^\s*<\/g>$/', $l) && ! $g['brand']) { continue; }
        $out[] = $l;
    }
    if ($usesXlink) { $root['xmlns:xlink'] = 'http://www.w3.org/1999/xlink'; }
    return '<svg' . attrString($root) . ">\n" . implode("\n", $out) . "\n</svg>\n";
}

if (! is_dir(ICON_DIR)) { mkdir(ICON_DIR, 0777, true); }
foreach (glob(ICON_DIR . '/*.svg') ?: [] as $old) { unlink($old); }

$icons = [];
foreach ($groups as $hash => $g) {
    // The file is drawn the way the icon is drawn most often; the other ways
    // are listed, because a different stroke-width is a visibly different icon.
    $variants = array_values($g['variants']);
    usort($variants, fn ($a, $b) => $b['count'] <=> $a['count']);
    $canonical = $variants[0];
    file_put_contents(ICON_DIR . '/' . $g['name'] . '.svg', renderSvg($g, $canonical));

    $sizeCount = [];
    $moduleCount = [];
    $swCount = [];
    foreach ($g['occurrences'] as $o) {
        foreach ($o['size'] as $s) { $sizeCount[$s] = ($sizeCount[$s] ?? 0) + 1; }
        $moduleCount[$o['module']] = ($moduleCount[$o['module']] ?? 0) + 1;
        if (isset($o['paint']['stroke-width'])) { $swCount[$o['paint']['stroke-width']] = ($swCount[$o['paint']['stroke-width']] ?? 0) + 1; }
    }
    arsort($sizeCount);
    arsort($moduleCount);
    arsort($swCount);

    $icons[] = [
        'name' => $g['name'],
        'kind' => $g['kind'],
        'source' => $g['match'] ? libraryRef($g['match']) : null,
        'note' => $g['note'] ?? null,
        'file' => 'icons/' . $g['name'] . '.svg',
        'hash' => $hash,
        'viewBox' => $g['viewBox'],
        'paint' => $g['paint'],
        'stroke_width' => $canonical['paint']['stroke-width'] ?? null,
        'linecap' => $canonical['paint']['stroke-linecap'] ?? null,
        'linejoin' => $canonical['paint']['stroke-linejoin'] ?? null,
        'colours' => $g['colours'],
        'stroke_widths_used' => $swCount,
        'paint_variants' => count($variants) > 1
            ? array_map(fn ($v) => ['paint' => $v['paint'], 'count' => $v['count']], $variants)
            : null,
        'sizes' => $sizeCount,
        'modules' => $moduleCount,
        'count' => count($g['occurrences']),
        'occurrences' => $g['occurrences'],
    ];
}
usort($icons, fn ($a, $b) => [$a['kind'] === 'brand', $a['name']] <=> [$b['kind'] === 'brand', $b['name']]);

$emoji = emojiScan($files);

$kinds = array_count_values(array_column($icons, 'kind'));
ksort($kinds);
$heroSets = [];
foreach ($icons as $i) { if ($i['source']) { $s = substr($i['source'], 0, strrpos($i['source'], '/')); $heroSets[$s] = ($heroSets[$s] ?? 0) + 1; } }
ksort($heroSets);
$inScope = array_filter($icons, fn ($i) => array_diff(array_keys($i['modules']), OUT_OF_SCOPE_MODULES));

$payload = [
    'generated_from' => 'resources/views/**/*.blade.php',
    'generated_at' => date('c'),
    'note' => 'Generated by tools/ui-parity/icons.php. Do not edit by hand.',
    'libraries_matched_with' => $libIndex ? $libVersions : ['cache: tools/ui-parity/icon-names.json'],
    'size_note' => 'Rendered px on a phone. w-/h- are NOT scaled by the 1.25x text rule; sm: and wider variants are listed under `responsive` and ignored. A css source wins over the classes because app.css is unlayered.',
    'counts' => [
        'views_scanned' => count($files),
        'svg_elements' => $scanned,
        'icon_occurrences' => $occurrences,
        'unique_icons' => count($icons),
        'unique_icons_in_app_scope' => count($inScope),
        'by_kind' => $kinds,
        'by_library_set' => $heroSets,
        'with_paint_variants' => count(array_filter($icons, fn ($i) => $i['paint_variants'])),
        'unresolved' => count(array_filter($dynamic, fn ($d) => ! $d['resolved'] && empty($d['dead']))),
        'emoji_glyphs' => count($emoji),
        'emoji_occurrences' => array_sum(array_column($emoji, 'count')),
    ],
    'out_of_scope_modules' => OUT_OF_SCOPE_MODULES,
    'icons' => $icons,
    'dynamic' => $dynamic,
    'emoji' => $emoji,
];
file_put_contents(OUT_DIR . '/icons.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

if ($libIndex) {
    ksort($matchesForCache);
    file_put_contents(NAME_CACHE, json_encode([
        'note' => 'Written by tools/ui-parity/icons.php when run with a Heroicons package. Geometry hash => the Heroicon it matches. Lets a run without the package name the same icons.',
        'matches' => $matchesForCache,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

// ── The readable catalogue ──────────────────────────────────────────────────
$md = [];
$md[] = '# Icon inventory';
$md[] = '';
$md[] = '<!-- Generated by tools/ui-parity/icons.php from resources/views. Do not edit by hand. -->';
$md[] = '';
$md[] = sprintf(
    '%d svg elements in %d views, %d icon occurrences, **%d unique icons** (%s). Sizes are phone px. The narrative, conventions and Dart are in [10-icons.md](10-icons.md).',
    $scanned, count($files), $occurrences, count($icons),
    implode(', ', array_map(fn ($k, $v) => "$v $k", array_keys($kinds), $kinds))
);
$md[] = '';
$modOrder = ['shell', 'home', 'goconnect', 'gomarket', 'auth-onboarding', 'profile', 'people', 'static-pages', 'shared', 'kamer-ai', 'coming-soon', 'public-landing', 'pwa', 'solidarity', 'admin', 'email', 'other'];
foreach ($modOrder as $mod) {
    $rows = array_filter($icons, fn ($i) => isset($i['modules'][$mod]));
    if (! $rows) { continue; }
    $md[] = '## ' . $mod . (in_array($mod, OUT_OF_SCOPE_MODULES, true) ? ' (not in the app)' : '');
    $md[] = '';
    $md[] = '| Icon | Kind | Paint | Sizes | Uses here | Where |';
    $md[] = '|---|---|---|---|---|---|';
    foreach ($rows as $i) {
        $here = array_filter($i['occurrences'], fn ($o) => $o['module'] === $mod);
        $at = array_map(fn ($o) => $o['at'], $here);
        $shownAt = array_slice($at, 0, 4);
        $sizes = [];
        foreach ($here as $o) { foreach ($o['size'] as $s) { $sizes[$s] = true; } }
        $paint = $i['paint'] . ($i['stroke_width'] ? ' ' . $i['stroke_width'] : '');
        if ($i['stroke_widths_used'] && count($i['stroke_widths_used']) > 1) { $paint .= ' (also ' . implode(', ', array_slice(array_keys($i['stroke_widths_used']), 1)) . ')'; }
        $md[] = sprintf(
            '| [`%s`](%s) | %s | %s | %s | %d | %s |',
            $i['name'], $i['file'], $i['kind'] . ($i['source'] ? ' `' . $i['source'] . '`' : ''),
            $paint, implode(', ', array_keys($sizes)), count($here),
            implode(', ', array_map(fn ($a) => '`' . $a . '`', $shownAt)) . (count($at) > 4 ? ' +' . (count($at) - 4) : '')
        );
    }
    $md[] = '';
}
$md[] = '## Not read as icons';
$md[] = '';
$md[] = '| View | Why | Resolved from |';
$md[] = '|---|---|---|';
foreach ($dynamic as $d) {
    $md[] = sprintf('| `%s:%d` | %s | %s |', $d['view'], $d['line'], str_replace('|', '\\|', $d['reason']), $d['resolved'] ? implode(', ', array_map(fn ($r) => '`' . $r . '`', $d['resolved'])) : '**unresolved**');
}
$md[] = '';
$md[] = '## Emoji drawn where an icon would be';
$md[] = '';
$md[] = 'Contexts: `standalone` is the whole text of an element, `icon-key` is a PHP `\'icon\' =>` value, `label` leads a text label, `list` is a row of five or more (a picker, a flag list). A *text symbol* (✓ ✕ ★) is drawn by the font, not as colour emoji.';
$md[] = '';
$md[] = '| Glyph | Uses | Contexts | Modules | Text symbol |';
$md[] = '|---|---|---|---|---|';
foreach ($emoji as $g => $e) {
    $ctx = [];
    foreach ($e['contexts'] as $k => $v) { $ctx[] = "$k $v"; }
    $md[] = sprintf('| %s | %d | %s | %s | %s |', $g, $e['count'], implode(', ', $ctx), implode(', ', $e['modules']), $e['text_symbol'] ? 'yes' : '');
}
$md[] = '';
file_put_contents(OUT_DIR . '/icons.md', implode("\n", $md) . "\n");

// ── CnIcons ─────────────────────────────────────────────────────────────────
function dartIdent(string $name): string
{
    $parts = preg_split('/[^a-z0-9]+/i', $name);
    $id = '';
    foreach ($parts as $i => $p) { $id .= $i === 0 ? lcfirst($p) : ucfirst($p); }
    if (ctype_digit($id[0] ?? 'x')) { $id = 'i' . $id; }
    if (in_array($id, ['switch', 'case', 'default', 'class', 'new', 'null', 'true', 'false', 'is', 'in', 'do', 'if', 'for'], true)) { $id .= 'Icon'; }
    return $id;
}

$dart = [];
$dart[] = '// GENERATED by tools/ui-parity/icons.php from resources/views.';
$dart[] = '// Do not edit by hand — re-run the script.';
$dart[] = '//';
$dart[] = '// Asset paths for every icon the web draws inline. Copy docs/ui-parity/icons/';
$dart[] = '// to assets/icons/ and list that folder in pubspec.yaml. Draw them with CnIcon';
$dart[] = '// (see docs/ui-parity/10-icons.md), never with Icons.* — Material icons are';
$dart[] = '// a different drawing and the difference shows at every size.';
$dart[] = '';
$dart[] = 'abstract final class CnIcons {';
$dart[] = "  static const _dir = 'assets/icons';";
$lastKind = null;
foreach ($icons as $i) {
    $kindLabel = $i['kind'] === 'brand' ? 'brand' : 'glyph';
    if ($kindLabel !== $lastKind) {
        $dart[] = '';
        $dart[] = $kindLabel === 'brand'
            ? '  // ── Brand: carries its own colours. Do NOT tint (CnIcon(tint: false)). ──'
            : '  // ── One-colour glyphs: take the colour they are given. ──';
        $lastKind = $kindLabel;
    }
    $sizes = implode(', ', array_slice(array_keys($i['sizes']), 0, 3));
    $doc = $i['source'] ?? $i['kind'];
    $dart[] = sprintf('  /// %s · %s%s · %s · %d use%s', $doc, $i['paint'], $i['stroke_width'] ? ' ' . $i['stroke_width'] : '', $sizes, $i['count'], $i['count'] === 1 ? '' : 's');
    $dart[] = sprintf("  static const %s = '\$_dir/%s.svg';", dartIdent($i['name']), $i['name']);
}
$dart[] = '}';
$dart[] = '';
if (! is_dir(OUT_DIR . '/generated')) { mkdir(OUT_DIR . '/generated', 0777, true); }
file_put_contents(OUT_DIR . '/generated/cn_icons.dart', implode("\n", $dart) . "\n");

// ── Report ──────────────────────────────────────────────────────────────────
printf(
    "wrote docs/ui-parity/icons/*.svg, icons.{json,md}, generated/cn_icons.dart\n"
    . "  %d views, %d svg elements, %d icon occurrences\n  %d unique icons (%s)\n"
    . "  libraries: %s\n  %d dynamic svgs, %d unresolved\n  %d emoji glyphs, %d uses\n",
    count($files), $scanned, $occurrences, count($icons),
    implode(', ', array_map(fn ($k, $v) => "$v $k", array_keys($kinds), $kinds)),
    $libIndex ? 'matched against ' . implode(', ', $libVersions) : (is_file(NAME_CACHE) ? 'named from the cache' : 'not matched (no package, no cache)'),
    count($dynamic), count(array_filter($dynamic, fn ($d) => ! $d['resolved'] && empty($d['dead']))),
    count($emoji), array_sum(array_column($emoji, 'count'))
);
if ($unnamed) {
    echo "\n  Not named yet (add to MANUAL_NAMES by hash):\n";
    foreach ($unnamed as $h) {
        $g = $groups[$h];
        $o = $g['occurrences'][0];
        printf("    %s  %-12s %3d use(s)  %s  %s\n", $h, $g['paint'], count($g['occurrences']), $o['at'], mb_substr($o['hint'] ?? '', 0, 90));
    }
}
