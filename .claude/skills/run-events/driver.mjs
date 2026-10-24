// Drives the running Event Hub in headless Chromium. See SKILL.md.
//
//   node driver.mjs shot <path> [--as "<name>"] [--out <name|file.png>]
//   node driver.mjs run <flow.mjs> [--as "<name>"]
//
// A flow file default-exports async (app) => {}, where app is
// { page, base, go, settle, shot, signIn, dragTo, check }. app.page changes on every signIn,
// so always read it from app rather than destructuring it.
// Every run prints console errors, page errors and 5xx responses, and exits 1 if there are any.
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const HOME = resolve(process.env.RUN_EVENTS_HOME ?? `${homedir()}/.cache/run-events`);
// setup.sh installs Chromium here too, so the whole skill lives in one directory.
process.env.PLAYWRIGHT_BROWSERS_PATH ??= `${HOME}/browsers`;
// Playwright's own dependency check can't see the libraries setup.sh unpacked (they're found
// through LD_LIBRARY_PATH at launch), so it would refuse to start.
process.env.PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS = '1';
const { chromium } = createRequire(`${HOME}/package.json`)('playwright');
const shotsDir = `${HOME}/shots`;
mkdirSync(shotsDir, { recursive: true });

const [command, target, ...rest] = process.argv.slice(2);
const option = (name) => {
    const index = rest.indexOf(name);
    return index === -1 ? undefined : rest[index + 1];
};

// setup.sh unpacks the libraries Chromium is missing here rather than installing them.
const libs = `${HOME}/libs/root/usr/lib/x86_64-linux-gnu`;
const browser = await chromium.launch({
    args: ['--no-sandbox'],
    env: { ...process.env, LD_LIBRARY_PATH: [libs, process.env.LD_LIBRARY_PATH].filter(Boolean).join(':') },
});
const problems = [];
let context;

const app = {
    base: process.env.BASE_URL ?? `http://127.0.0.1:${process.env.PORT ?? 8123}`,
    page: null,

    // A fresh browser context, so a previous user's session is gone.
    async newPage() {
        await context?.close();
        context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        app.page = await context.newPage();
        app.page.on('console', (m) => m.type() === 'error' && problems.push(`console: ${m.text()}`));
        app.page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
        app.page.on('response', (r) => r.status() >= 500 && problems.push(`HTTP ${r.status()} ${r.url()}`));
    },

    // Inertia visits finish after networkidle; flash messages and redirects need a moment more.
    async settle() {
        await app.page.waitForLoadState('networkidle');
        await app.page.waitForTimeout(800);
    },

    async go(path) {
        await app.page.goto(app.base + path);
        await app.settle();
    },

    async shot(name) {
        const file = name.includes('/') ? name : `${shotsDir}/${name.replace(/\.png$/, '')}.png`;
        await app.page.screenshot({ path: file, fullPage: true });
        console.log(`screenshot: ${file}`);
        return file;
    },

    // Signs in through the dev login page, which lists every seeded user with a button.
    async signIn(name) {
        await app.newPage();
        await app.go('/login');
        await app.page
            .locator(`xpath=//*[normalize-space(text())="${name}"]/ancestor::*[.//button][1]`)
            .getByRole('button')
            .first()
            .click();
        // Navigating on before the redirect lands drops the session.
        await app.page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 60_000 });
        await app.settle();
    },

    // HTML5 drag-and-drop (the roster board). Playwright's dragTo doesn't fire it here. `at` is
    // where on the destination to drop, as fractions of its width and height.
    async dragTo(source, destination, at = { x: 0.5, y: 0.5 }) {
        const box = await destination.boundingBox();
        const dataTransfer = await app.page.evaluateHandle(() => new DataTransfer());
        const point = { dataTransfer, clientX: box.x + box.width * at.x, clientY: box.y + box.height * at.y };
        await source.dispatchEvent('dragstart', { dataTransfer });
        await destination.dispatchEvent('dragover', point);
        await destination.dispatchEvent('drop', point);
        await source.dispatchEvent('dragend', { dataTransfer });
        await app.page.waitForTimeout(400);
    },

    // Prints a labelled value; fails the run when `ok` is false.
    check(label, value, ok = true) {
        console.log(`${ok ? 'ok  ' : 'FAIL'} ${label}: ${value}`);
        if (!ok) {
            problems.push(`check failed: ${label}`);
        }
    },
};

let failed = false;
try {
    const userName = option('--as');
    await (userName ? app.signIn(userName) : app.newPage());

    if (command === 'shot') {
        await app.go(target ?? '/');
        console.log(`url: ${app.page.url()}\ntitle: ${await app.page.title()}`);
        const slug = (target ?? '/').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');
        await app.shot(option('--out') ?? (slug || 'home'));
    } else if (command === 'run') {
        const flow = (await import(pathToFileURL(resolve(target)).href)).default;
        await flow(app);
    } else {
        console.error('usage: node driver.mjs shot <path> [--as <name>] [--out <name>] | run <flow.mjs> [--as <name>]');
        failed = true;
    }
} catch (error) {
    console.error(error);
    await app.shot('error').catch(() => {});
    failed = true;
} finally {
    await browser.close();
}

console.log(problems.length ? problems.join('\n') : 'no problems');
process.exit(failed || problems.length ? 1 : 0);
