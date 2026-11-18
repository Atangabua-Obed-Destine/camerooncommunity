<?php

/**
 * Build the parity checklist: every route, the view behind it, and the app
 * screen that has to match it.
 *
 * This is item C1 of the mobile plan. It is generated rather than hand-kept
 * for the same reason the token layer is: a 134-route matrix maintained by
 * hand is wrong within a week, and "full parity" is only a claim if something
 * can check it.
 *
 * Usage:  php tools/ui-parity/screen-map.php
 * Writes: docs/ui-parity/screens.json      (machine-readable)
 *         docs/ui-parity/05-screen-map.md  (the checklist)
 */

const ROOT = __DIR__ . '/../..';
const OUT_DIR = ROOT . '/docs/ui-parity';

/**
 * Which feature each route belongs to, by name prefix, in order.
 *
 * First match wins, so the specific prefixes come before the loose ones —
 * `marketplace.seller` is the app-wide profile page rather than a shop
 * section, and it is listed before `marketplace.`.
 */
const FEATURES = [
    'admin.' => ['admin', 'out of scope'],
    'module.' => ['coming_soon', 'placeholder'],
    'api.' => ['_api', 'endpoint'],
    // A closure the generator cannot read, checked by hand: /u/{username} only
    // redirects to marketplace.seller, which is the profile page. Not a screen.
    'user.profile' => ['profile', 'redirect'],

    'login' => ['auth', 'screen'],
    'logout' => ['auth', 'action'],
    'register' => ['auth', 'screen'],
    'password.' => ['auth', 'screen'],
    'verification.' => ['auth', 'screen'],
    'auth.google' => ['auth', 'redirect'],
    'google.' => ['auth', 'redirect'],
    'onboarding' => ['onboard', 'screen'],

    'yard' => ['yard', 'screen'],
    'marketplace.seller' => ['profile', 'screen'],
    'marketplace.' => ['marketplace', 'screen'],
    'people' => ['people', 'screen'],
    'profile' => ['profile', 'screen'],

    'home' => ['home', 'screen'],
    'feed' => ['home', 'screen'],

    'contact' => ['pages', 'screen'],
    'about' => ['pages', 'screen'],
    'terms' => ['pages', 'screen'],
    'privacy' => ['pages', 'screen'],
    'legal.' => ['pages', 'screen'],

    'geo.' => ['_support', 'endpoint'],
    'location.' => ['_support', 'endpoint'],
    'ads.' => ['_support', 'endpoint'],
    'turn' => ['_support', 'endpoint'],
    'impersonate' => ['_support', 'action'],
    'pwa.' => ['_support', 'endpoint'],
    'livewire.' => ['_support', 'endpoint'],
    'storage.' => ['_support', 'endpoint'],
    'sanctum.' => ['_support', 'endpoint'],
];

/**
 * Which parity document describes each screen, route by route.
 *
 * Per route rather than per feature on purpose: pass 3 covered GoMarket's
 * feed, listing, composer and checkout, not its eight smaller screens, and a
 * per-feature map marked all fourteen as done. An over-reported checklist is
 * worse than none.
 */
const DOCUMENTED = [
    // Pass 1 — the shell both of these render inside.
    'home' => '01',

    // Pass 2 — GoConnect.
    'yard' => '02',
    'yard.room' => '02',

    // Pass 3 — GoMarket's main surfaces.
    'marketplace.index' => '03',
    'marketplace.category' => '03',
    'marketplace.show' => '03',
    'marketplace.sell' => '03',
    'marketplace.edit' => '03',
    'marketplace.checkout' => '03',

    // Pass 6 — auth and onboarding.
    'login' => '06',
    'register' => '06',
    'password.request' => '06',
    'password.reset' => '06',
    'verification.notice' => '06',
    'onboarding' => '06',

    // Pass 7 — GoMarket's own-account screens.
    'marketplace.favorites' => '07',
    'marketplace.inbox' => '07',
    'marketplace.insights' => '07',
    'marketplace.review' => '07',
    'marketplace.mine' => '07',
    'marketplace.offers' => '07',
    'marketplace.orders' => '07',
    'marketplace.saved' => '07',

    // Pass 8 — the profile page and the account page.
    'marketplace.seller' => '08',
    'profile' => '08',

    // Pass 9 — People and the static pages.
    'people' => '09',
    'about' => '09',
    'contact' => '09',
    'legal.privacy' => '09',
    'legal.terms' => '09',
];

/** Features where one document covers every route in them. */
const DOCUMENTED_FEATURES = [
    // Ten slugs, one teaser page, described with the module registry.
    'coming_soon' => '01',
];

/** Features that are screens we have to build, in dependency order. */
const BUILD_ORDER = [
    'auth', 'onboard', 'home', 'yard', 'marketplace', 'people', 'profile',
    'pages', 'coming_soon',
];

// ─────────────────────────────────────────────────────────────────────────────

/** Ask Laravel for its own route table. */
function routes(): array
{
    $json = shell_exec('php ' . escapeshellarg(ROOT . '/artisan') . ' route:list --json 2>&1');
    $rows = json_decode((string) $json, true);

    if (! is_array($rows)) {
        fwrite(STDERR, "Could not read the route list. artisan said:\n" . $json . "\n");
        exit(1);
    }

    return $rows;
}

function featureFor(?string $name, string $uri): array
{
    if (str_starts_with($uri, 'api/')) {
        return ['_api', 'endpoint'];
    }
    if (str_starts_with($uri, 'admin')) {
        return ['admin', 'out of scope'];
    }

    // The framework's own unnamed routes: Livewire's asset and update
    // endpoints, the broadcasting auth route, the health check. Matched on the
    // URI because they have no name to match on.
    foreach (['livewire', 'broadcasting/', 'up', 'storage/', 'vendor/'] as $prefix) {
        if ($uri === $prefix || str_starts_with($uri, $prefix)) {
            return ['_framework', 'endpoint'];
        }
    }

    // POST /login and friends share a URI with their GET screen but carry no
    // name of their own.
    if ($name === null) {
        foreach (['login', 'register', 'logout', 'u/'] as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return ['auth', 'action'];
            }
        }
    }

    foreach (FEATURES as $prefix => $pair) {
        if ($name !== null && str_starts_with($name, $prefix)) {
            return $pair;
        }
    }

    return ['_unmapped', 'screen'];
}

/**
 * What renders this route.
 *
 * A Livewire component names itself. A controller does not, so its file is
 * scanned for the views it returns — all of them, because one action can
 * branch (HomeController serves the landing page to guests and the feed to
 * members, which is exactly the sort of thing a hand-written matrix misses).
 */
function rendererFor(string $action, ?string $name): array
{
    if ($action === 'Closure') {
        return ['kind' => 'closure', 'detail' => 'closure'];
    }

    if (str_starts_with($action, 'App\\Livewire\\')) {
        $short = substr($action, strlen('App\\Livewire\\'));

        // Auth\RegisterWizard -> livewire.auth.register-wizard. Kebabbing the
        // whole string hyphenated across the dot and gave `.-register-wizard`.
        $segments = array_map(
            fn (string $seg) => strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $seg)),
            explode('\\', $short)
        );

        return [
            'kind' => 'livewire',
            'detail' => $short,
            'view' => 'livewire.' . implode('.', $segments),
        ];
    }

    if ($action === 'Illuminate\\Routing\\ViewController') {
        // Route::view('uri', 'the.view') — the view is in the definition, so
        // read it back out of the route file.
        $web = @file_get_contents(ROOT . '/routes/web.php') ?: '';
        if ($name !== null && preg_match(
            "/Route::view\(\s*'[^']*'\s*,\s*'([^']+)'\s*\)[^;]*->name\(\s*'" . preg_quote($name, '/') . "'/",
            $web,
            $m
        )) {
            return ['kind' => 'view', 'detail' => $m[1], 'view' => $m[1]];
        }

        return ['kind' => 'view', 'detail' => 'Route::view', 'view' => null];
    }

    if (! str_contains($action, '@')) {
        return ['kind' => 'other', 'detail' => $action];
    }

    [$class, $method] = explode('@', $action, 2);
    $file = ROOT . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';

    $views = [];
    if (is_file($file)) {
        $src = file_get_contents($file);
        // Narrow to the method body before looking for view() calls, so a
        // controller with twenty actions does not attribute all its views to
        // every route.
        $start = strpos($src, "function {$method}(");
        if ($start !== false) {
            $body = substr($src, $start, 4000);
            if (preg_match_all("/view\(\s*'([a-z0-9_.\-]+)'/i", $body, $m)) {
                $views = array_values(array_unique($m[1]));
            }
            // `$view = auth()->check() ? 'feed' : 'home';`
            if (preg_match_all("/\\\$view\s*=[^;]*?'([a-z0-9_.\-]+)'[^;]*?:\s*'([a-z0-9_.\-]+)'/i", $body, $m2)) {
                foreach ($m2[1] as $i => $a) {
                    $views[] = $a;
                    $views[] = $m2[2][$i];
                }
                $views = array_values(array_unique(array_filter($views, fn ($v) => $v !== 'view')));
            }
        }
    }

    return [
        'kind' => 'controller',
        'detail' => class_basename_lite($class) . '@' . $method,
        'view' => $views ? implode(', ', $views) : null,
    ];
}

function class_basename_lite(string $class): string
{
    $parts = explode('\\', $class);
    return end($parts);
}

/** `Yard\ChatRoom` -> `chat_room_screen.dart` */
function screenFile(string $name): string
{
    $leaf = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
    $leaf = preg_replace('/[^a-z0-9]+/i', '_', $leaf);
    $leaf = strtolower(trim(preg_replace('/(?<!^)[A-Z]/', '_$0', $leaf), '_'));
    $leaf = preg_replace('/_+/', '_', $leaf);

    return $leaf === '' ? 'index_screen.dart' : $leaf . '_screen.dart';
}

// ─────────────────────────────────────────────────────────────────────────────

$rows = routes();
$screens = [];
$counts = [];

foreach ($rows as $r) {
    $name = $r['name'] ?? null;
    $uri = '/' . ltrim((string) $r['uri'], '/');
    $methods = array_values(array_filter(
        explode('|', (string) $r['method']),
        fn ($m) => $m !== 'HEAD'
    ));

    [$feature, $kind] = featureFor($name, (string) $r['uri']);
    $renderer = rendererFor((string) ($r['action'] ?? ''), $name);

    $counts[$feature] = ($counts[$feature] ?? 0) + 1;

    $isScreen = $kind === 'screen' || $kind === 'placeholder';
    $isGet = in_array('GET', $methods, true);

    // A GET route under a feature is not automatically a screen.
    // YardController answers /yard/users/search and
    // /yard/connections/state/{id} with JSON, and listing those as screens
    // to build would inflate the checklist with endpoints. So: a screen is
    // a route that renders something. A closure cannot be read statically,
    // so it counts and is flagged instead of guessed at.
    $rendersView = $renderer['kind'] === 'livewire'
        || $renderer['kind'] === 'closure'
        || ! empty($renderer['view']);

    $screens[] = [
        'name' => $name,
        'uri' => $uri,
        'methods' => $methods,
        'feature' => $feature,
        'kind' => $kind,
        'renderer' => $renderer,
        'is_screen' => $isScreen && $isGet && $rendersView,
        'renders_json' => $isScreen && $isGet && ! $rendersView,
        'covered_by' => DOCUMENTED[$name] ?? DOCUMENTED_FEATURES[$feature] ?? null,
        'flutter' => $isScreen && $isGet && $rendersView && $name
            ? 'lib/features/' . $feature . '/screens/' . screenFile($name)
            : null,
    ];
}

// ── Write the machine-readable copy ─────────────────────────────────────────
if (! is_dir(OUT_DIR)) { mkdir(OUT_DIR, 0777, true); }

$screenRoutes = array_values(array_filter($screens, fn ($s) => $s['is_screen']));

file_put_contents(
    OUT_DIR . '/screens.json',
    json_encode([
        'generated_from' => 'php artisan route:list --json',
        'generated_at' => date('c'),
        'note' => 'Generated by tools/ui-parity/screen-map.php. Do not edit by hand.',
        'counts' => [
            'routes_total' => count($screens),
            'screens_in_scope' => count($screenRoutes),
            'by_feature' => $counts,
        ],
        'routes' => $screens,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

// ── The checklist ───────────────────────────────────────────────────────────
$inScope = [];
foreach ($screenRoutes as $s) {
    $inScope[$s['feature']][] = $s;
}

$md = [];
$md[] = '# Pass 5 — The screen map';
$md[] = '';
$md[] = '<!-- Generated by tools/ui-parity/screen-map.php from `php artisan route:list`.';
$md[] = '     Do not edit by hand: re-run the script. -->';
$md[] = '';
$md[] = 'Item C1 of the mobile plan: every route, what renders it, and the app screen that has to';
$md[] = 'match. Generated, because a matrix this size kept by hand stops being true quickly — and';
$md[] = '"full parity" is only a claim if something can check it.';
$md[] = '';
$jsonRoutes = array_values(array_filter($screens, fn ($s) => ! empty($s['renders_json'])));

$md[] = sprintf(
    '**%d routes in all. %d are screens the app has to build.** The rest are the admin panel,'
    . ' the API, support endpoints, non-GET actions, and %d GET routes that answer with JSON'
    . ' rather than a view — those belong to the API workstream, not this one.',
    count($screens),
    count($screenRoutes),
    count($jsonRoutes)
);
$md[] = '';
$md[] = 'The JSON ones are worth naming, because they are the session-authenticated endpoints';
$md[] = 'the app cannot use and the API has to replace:';
$md[] = '';
foreach ($jsonRoutes as $j) {
    $md[] = sprintf('- `%s` — `%s` (%s)', $j['name'] ?? '(unnamed)', $j['uri'], $j['renderer']['detail']);
}
$md[] = '';

// Summary
$md[] = '## Where the routes go';
$md[] = '';
$md[] = '| Area | Routes | In the app |';
$md[] = '|---|---|---|';

$areaNote = [
    'admin' => '**No** — web only, and it keeps steering the app through `/api/v1/configuration`',
    '_api' => 'It *is* the app\'s back end',
    '_support' => 'No — endpoints, not screens',
    'auth' => 'Yes',
    'onboard' => 'Yes',
    'home' => 'Yes',
    'yard' => 'Yes',
    'marketplace' => 'Yes',
    'people' => 'Yes',
    'profile' => 'Yes',
    'pages' => 'Yes',
    'coming_soon' => 'Yes — one screen, ten slugs',
    '_framework' => 'No — Livewire, broadcasting and the health check',
    '_unmapped' => '**Unclassified — look at these**',
];

arsort($counts);
foreach ($counts as $feature => $n) {
    $md[] = sprintf('| `%s` | %d | %s |', $feature, $n, $areaNote[$feature] ?? '?');
}
$md[] = '';

// The checklist proper
$md[] = '## The checklist';
$md[] = '';
$md[] = 'Walk it screen by screen against the live site. `Covered` is the parity document that';
$md[] = 'describes the screen in detail; a blank means nobody has written it down yet.';
$md[] = '';

$ordered = [];
foreach (BUILD_ORDER as $f) {
    if (isset($inScope[$f])) { $ordered[$f] = $inScope[$f]; }
}
foreach ($inScope as $f => $list) {
    if (! isset($ordered[$f])) { $ordered[$f] = $list; }
}

$titles = [
    'auth' => 'Auth',
    'onboard' => 'Onboarding',
    'home' => 'Home',
    'yard' => 'GoConnect',
    'marketplace' => 'GoMarket',
    'people' => 'People',
    'profile' => 'Profile',
    'pages' => 'Static pages',
    'coming_soon' => 'Coming-soon placeholders',
    '_unmapped' => 'Unclassified',
];

$done = 0;
foreach ($ordered as $feature => $list) {
    $md[] = sprintf('### %s — %d screen%s', $titles[$feature] ?? $feature, count($list), count($list) === 1 ? '' : 's');
    $md[] = '';
    $md[] = '| | Route | Renders | Covered | App screen |';
    $md[] = '|---|---|---|---|---|';

    foreach ($list as $s) {
        $box = $s['covered_by'] ? '[x]' : '[ ]';
        if ($s['covered_by']) { $done++; }

        $renders = $s['renderer']['detail'];
        if (! empty($s['renderer']['view'])) {
            $renders .= ' → `' . $s['renderer']['view'] . '`';
        } elseif ($s['renderer']['kind'] === 'closure') {
            $renders .= ' — **view not readable from the route; check by hand**';
        }

        $md[] = sprintf(
            '| %s | `%s`<br>`%s` | %s | %s | `%s` |',
            $box,
            $s['name'] ?? '(unnamed)',
            $s['uri'],
            $renders,
            $s['covered_by'] ? '[' . $s['covered_by'] . '](' . $s['covered_by'] . '-' . [
                '01' => 'layout-navigation',
                '02' => 'goconnect',
                '03' => 'gomarket',
                '04' => 'shared-components',
                '06' => 'auth-onboarding',
                '07' => 'gomarket-screens',
                '08' => 'profile',
                '09' => 'people-static',
            ][$s['covered_by']] . '.md)' : '—',
            $s['flutter']
        );
    }
    $md[] = '';
}

$md[] = sprintf(
    '**%d of %d screens are documented.** The %d still open, in build order:',
    $done,
    count($screenRoutes),
    count($screenRoutes) - $done
);
$md[] = '';
foreach ($ordered as $feature => $list) {
    $open = array_values(array_filter($list, fn ($s) => ! $s['covered_by']));
    if (! $open) { continue; }
    $md[] = sprintf(
        '- **%s** (%d): %s',
        $titles[$feature] ?? $feature,
        count($open),
        implode(', ', array_map(fn ($s) => '`' . ($s['name'] ?? $s['uri']) . '`', $open))
    );
}
$md[] = '';

// Overlays, which are not routes at all
$md[] = '## The screens that are not routes';
$md[] = '';
$md[] = 'A route list cannot see these, and between them they are a large part of what the app';
$md[] = 'does. Each is an overlay opened by an event — see';
$md[] = '[pass 1 §7](01-layout-navigation.md) for the nine mounted globally.';
$md[] = '';
$md[] = '| Overlay | Opened by | Covered |';
$md[] = '|---|---|---|';
foreach ([
    ['App drawer', '`open-app-menu`', '[01](01-layout-navigation.md)'],
    ['Discover modal', '`open-discover`', '—'],
    ['Kamer AI chat', '`open-kamer-ai`', '—'],
    ['User preview card', '`open-user-preview`', '[04](04-shared-components.md)'],
    ['GoMarket chat dock', '`open-gomarket-chat`', '[03](03-gomarket.md)'],
    ['Connection notifier', 'realtime', '[04](04-shared-components.md)'],
    ['Room info panel', '`open-room-info`', '[02](02-goconnect.md)'],
    ['Message selection', 'long press', '[02](02-goconnect.md)'],
    ['Image lightbox', 'tap a photo', '[04](04-shared-components.md)'],
    ['Forward sheet', '`open-forward`', '[04](04-shared-components.md)'],
    ['Communities modal', '`open-communities`', '—'],
    ['Connections modal', '`open-connections`', '—'],
    ['Starred messages', '`open-starred`', '—'],
    ['Archived chats', '`open-archived`', '—'],
    ['Call manager', 'realtime', '— deferred with calls'],
    ['Share to chat', '`open-share`', '—'],
    ['Location switcher', 'the location strip', '—'],
    ['Listing item modal', '`mp-open-listing`', '[03](03-gomarket.md)'],
    ['Toasts', '`toast` / `yard-toast`', '[04](04-shared-components.md)'],
    ['PWA install banner', '—', 'no app equivalent'],
] as [$what, $how, $cov]) {
    $md[] = sprintf('| %s | %s | %s |', $what, $how, $cov);
}
$md[] = '';
$md[] = 'Twenty overlays against ' . count($screenRoutes) . ' routed screens. **The app\'s route';
$md[] = 'count is not its screen count**, and an overlay is where most of the interaction detail';
$md[] = 'in this product lives.';
$md[] = '';

file_put_contents(OUT_DIR . '/05-screen-map.md', implode("\n", $md) . "\n");

printf(
    "wrote docs/ui-parity/{screens.json,05-screen-map.md}\n"
    . "  %d routes, %d in-scope screens, %d documented\n",
    count($screens),
    count($screenRoutes),
    $done
);

if (isset($counts['_unmapped'])) {
    printf("  %d routes did not match a feature prefix — check them\n", $counts['_unmapped']);
}
