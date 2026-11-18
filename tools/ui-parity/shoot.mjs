#!/usr/bin/env node
/**
 * Render a page at a real iPhone viewport and report what a phone sees.
 *
 *   node tools/ui-parity/shoot.mjs <url> [--device=se|15|max] [--height=px] [--out=dir] [--eval="js expr"]
 *
 * --geo=lat,lng[,accuracy] makes the page's location request succeed with that fix,
 * so the register wizard's GPS step can be shot in its success state. Without it
 * headless Chrome denies the prompt, which is the "blocked" state.
 *
 * --dpr overrides the pixel density (1 keeps reference screenshots small).
 * --css="..." injects a stylesheet before load, e.g. to hide web-only chrome
 * such as the PWA install banner: --css="[x-data='pwaInstall()']{display:none!important}"
 *
 * --height overrides the device height: Safari's toolbars take ~115px off an
 * iPhone SE, so `--device=se --height=553` is what a web visitor actually gets.
 *
 * Why this exists: `chrome --headless --window-size=375,667` does not give you
 * a 375px layout on Windows. Chrome will not make a window narrower than about
 * 500px, so the page lays out at 500 and the screenshot crops it — the first
 * pass at measuring the login screen produced a card cut off on the right that
 * no phone ever shows. Device emulation over the DevTools protocol sets the
 * layout viewport properly (width, DPR, mobile, touch), which is what the
 * parity docs need.
 *
 * Writes <out>/<slug>-<device>.png and prints a JSON report: viewport, document
 * scroll size, and every element that scrolls, with how far it overflows. Pass
 * --eval to add your own measurement; it runs in the page after load.
 *
 * Signed-out pages only. Nothing here logs in.
 *
 * No dependencies: Node 22's global WebSocket talks to Chrome directly.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, existsSync, mkdtempSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const DEVICES = {
    // iPhone SE (2nd/3rd gen): the smallest screen still sold and supported.
    se: { width: 375, height: 667, dpr: 2 },
    // iPhone 15 / 15 Pro / 16: the common case.
    15: { width: 393, height: 852, dpr: 3 },
    // iPhone 15/16 Pro Max.
    max: { width: 430, height: 932, dpr: 3 },
};

const IOS_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 '
    + '(KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

const CHROME_PATHS = [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
];

const args = process.argv.slice(2);
const url = args.find((a) => !a.startsWith('--'));
const opt = Object.fromEntries(
    args.filter((a) => a.startsWith('--')).map((a) => {
        const [k, ...v] = a.slice(2).split('=');
        return [k, v.join('=') || true];
    })
);

if (!url) {
    console.error('usage: node tools/ui-parity/shoot.mjs <url> [--device=se|15|max] [--out=dir] [--eval="expr"]');
    process.exit(2);
}

const device = DEVICES[opt.device ?? 'se'] && { ...DEVICES[opt.device ?? 'se'] };
if (!device) {
    console.error(`unknown device "${opt.device}" — one of ${Object.keys(DEVICES).join(', ')}`);
    process.exit(2);
}

if (device && opt.height) { device.height = Number(opt.height); }
if (device && opt.dpr) { device.dpr = Number(opt.dpr); }

const chrome = CHROME_PATHS.find((p) => existsSync(p));
if (!chrome) {
    console.error('no Chrome or Edge found');
    process.exit(2);
}

const port = 9300 + Math.floor(Math.random() * 500);
const profile = mkdtempSync(join(tmpdir(), 'cn-shoot-'));
const proc = spawn(chrome, [
    '--headless=new',
    '--disable-gpu',
    '--hide-scrollbars',
    '--no-first-run',
    `--user-data-dir=${profile}`,
    `--remote-debugging-port=${port}`,
    'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function pageSocketUrl() {
    for (let i = 0; i < 50; i++) {
        try {
            const list = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
            const page = list.find((t) => t.type === 'page');
            if (page) return page.webSocketDebuggerUrl;
        } catch { /* not up yet */ }
        await sleep(200);
    }
    throw new Error('Chrome did not open a debugging port');
}

function client(wsUrl) {
    const ws = new WebSocket(wsUrl);
    let id = 0;
    const pending = new Map();
    const listeners = [];
    ws.onmessage = (ev) => {
        const msg = JSON.parse(ev.data);
        if (msg.id && pending.has(msg.id)) {
            const { resolve, reject } = pending.get(msg.id);
            pending.delete(msg.id);
            msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
        } else if (msg.method) {
            listeners.forEach((l) => l(msg));
        }
    };
    return {
        open: new Promise((r) => { ws.onopen = r; }),
        send(method, params = {}) {
            return new Promise((resolve, reject) => {
                pending.set(++id, { resolve, reject });
                ws.send(JSON.stringify({ id, method, params }));
            });
        },
        once(method) {
            return new Promise((resolve) => {
                const l = (m) => { if (m.method === method) { listeners.splice(listeners.indexOf(l), 1); resolve(m.params); } };
                listeners.push(l);
            });
        },
        close() { ws.close(); },
    };
}

// Runs in the page. Reports every element that scrolls and how far it overflows,
// plus the vertical position of its first child: a negative top on a centred
// flex child is content the user can never scroll back to.
const MEASURE = `(() => {
    const out = {
        viewport: { width: innerWidth, height: innerHeight, dpr: devicePixelRatio },
        document: { scrollWidth: document.documentElement.scrollWidth, scrollHeight: document.documentElement.scrollHeight },
        horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
        scrollers: [],
    };
    for (const el of document.querySelectorAll('body *')) {
        const cs = getComputedStyle(el);
        if (!/(auto|scroll)/.test(cs.overflowY)) continue;
        if (el.scrollHeight <= el.clientHeight + 1) continue;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        const kids = [...el.children].map((c) => c.getBoundingClientRect()).filter((b) => b.height > 0);
        const contentTop = kids.length ? Math.min(...kids.map((b) => b.top)) : r.top;
        out.scrollers.push({
            el: el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + '.' + [...el.classList].slice(0, 4).join('.'),
            clientHeight: el.clientHeight,
            scrollHeight: el.scrollHeight,
            overflowBy: el.scrollHeight - el.clientHeight,
            alignItems: cs.alignItems,
            // How far the content starts above the scroller's top edge while
            // scrolled to 0. Anything > 0 cannot be reached by scrolling.
            unreachableAbove: Math.max(0, Math.round(r.top - contentTop)),
        });
    }
    return out;
})()`;

let exitCode = 0;
try {
    const cdp = client(await pageSocketUrl());
    await cdp.open;
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: device.width,
        height: device.height,
        deviceScaleFactor: device.dpr,
        mobile: true,
    });
    await cdp.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
    await cdp.send('Emulation.setUserAgentOverride', { userAgent: IOS_UA, platform: 'iPhone' });

    if (typeof opt.geo === 'string') {
        const [latitude, longitude, accuracy = 20] = opt.geo.split(',').map(Number);
        // Browser.grantPermissions / setPermission leave the state at "prompt"
        // in headless Chrome here, and headless answers a prompt with a denial.
        // So replace the API itself before any page script runs; the page's
        // own code (reverse geocoding, $wire.setLocation) still runs as-is.
        const fix = JSON.stringify({ latitude, longitude, accuracy });
        await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: `(() => {
            const c = ${fix};
            const pos = () => ({ coords: { ...c, altitude: null, altitudeAccuracy: null, heading: null, speed: null }, timestamp: Date.now() });
            Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
                getCurrentPosition: (ok) => setTimeout(() => ok(pos()), 50),
                watchPosition: (ok) => { setTimeout(() => ok(pos()), 50); return 1; },
                clearWatch: () => {},
            } });
        })();` });
    }

    if (typeof opt.css === 'string') {
        const css = JSON.stringify(opt.css);
        await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: `document.addEventListener('DOMContentLoaded', () => {
            const s = document.createElement('style'); s.textContent = ${css}; document.head.appendChild(s);
        });` });
    }

    const loaded = cdp.once('Page.loadEventFired');
    await cdp.send('Page.navigate', { url });
    await loaded;
    // Alpine and Livewire boot after load, and x-text fills most labels.
    await sleep(Number(opt.wait ?? 1500));

    const report = (await cdp.send('Runtime.evaluate', { expression: MEASURE, returnByValue: true })).result.value;
    report.url = url;
    report.device = opt.device ?? 'se';

    if (typeof opt.eval === 'string') {
        const r = await cdp.send('Runtime.evaluate', { expression: opt.eval, returnByValue: true, awaitPromise: true });
        report.eval = r.result.value ?? r.result.description;
    }

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png' });
    const outDir = typeof opt.out === 'string' ? opt.out : join(tmpdir(), 'cn-shoot');
    mkdirSync(outDir, { recursive: true });
    const slug = new URL(url).pathname.replace(/^\/+|\/+$/g, '').split('/').pop() || 'root';
    const file = join(outDir, `${slug}-${report.device}${opt.height ? '-h' + opt.height : ''}.png`);
    writeFileSync(file, Buffer.from(shot.data, 'base64'));
    report.screenshot = file;

    console.log(JSON.stringify(report, null, 2));
    cdp.close();
} catch (e) {
    console.error(e.message);
    exitCode = 1;
} finally {
    proc.kill();
}
process.exit(exitCode);
