import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import {
    mkdtempSync,
    mkdirSync,
    readFileSync,
    existsSync,
    writeFileSync,
    rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { get } from 'node:http';
import { once } from 'node:events';
import { join, resolve } from 'node:path';
import { test } from 'node:test';
import { devContainerViteConfig } from '../../.devcontainer/vite.ts';
import laravel from 'laravel-vite-plugin';
import { createServer } from 'vite-plus';

const setup = resolve('.devcontainer/setup.mjs');

await test('setup preserves secrets and data while refreshing the Codespaces origin on restart', () => {
    const root = mkdtempSync(join(tmpdir(), 'events-container-'));
    const env = {
        ...process.env,
        CODESPACES: 'true',
        CODESPACE_NAME: 'first',
        GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN: 'app.github.dev',
    };
    try {
        writeFileSync(
            join(root, '.env.example'),
            'APP_KEY=existing-secret\nAPP_URL=http://localhost:8000\nOAUTH_SECRET=keep-me\n',
        );
        mkdirSync(join(root, 'public'));
        mkdirSync(join(root, 'database'));
        writeFileSync(join(root, 'database/database.sqlite'), 'existing-data');
        for (const name of ['first', 'second', 'second']) {
            writeFileSync(join(root, 'public/hot'), 'http://stale:5173');
            const result = spawnSync(process.execPath, [setup], {
                cwd: root,
                env: { ...env, CODESPACE_NAME: name },
                encoding: 'utf8',
            });
            assert.equal(result.status, 0, result.stderr);
        }
        const contents = readFileSync(join(root, '.env'), 'utf8');
        assert.match(
            contents,
            /^APP_URL=https:\/\/second-8080.app.github.dev$/m,
        );
        assert.match(
            contents,
            /^DEVCONTAINER_URL=https:\/\/second-8080.app.github.dev$/m,
        );
        assert.match(contents, /^TRUSTED_PROXIES=127.0.0.1$/m);
        assert.match(
            contents,
            /^INERTIA_SSR_HOT_URL=http:\/\/127.0.0.1:5173$/m,
        );
        assert.match(contents, /^APP_KEY=existing-secret$/m);
        assert.match(contents, /^OAUTH_SECRET=keep-me$/m);
        assert.equal(contents.match(/^APP_URL=/gm).length, 1);
        assert.equal(
            readFileSync(join(root, 'database/database.sqlite'), 'utf8'),
            'existing-data',
        );
        assert.equal(existsSync(join(root, 'public/hot')), false);
        const local = spawnSync(process.execPath, [setup], {
            cwd: root,
            env: { ...env, CODESPACES: 'false' },
            encoding: 'utf8',
        });
        assert.equal(local.status, 0, local.stderr);
        assert.match(
            readFileSync(join(root, '.env'), 'utf8'),
            /^APP_URL=http:\/\/localhost:8080$/m,
        );
        const invalid = spawnSync(process.execPath, [setup], {
            cwd: root,
            env: { ...env, CODESPACE_NAME: '' },
            encoding: 'utf8',
        });
        assert.notEqual(invalid.status, 0);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

await test('ordinary development and production builds keep their existing Vite defaults', () => {
    assert.deepEqual(devContainerViteConfig('serve'), {});
    assert.deepEqual(
        devContainerViteConfig('build', 'https://sample-8080.app.github.dev'),
        {},
    );
    const local = devContainerViteConfig('serve', 'http://localhost:8080');
    assert.equal(local.server.hmr.protocol, 'ws');
    assert.equal(local.server.hmr.clientPort, 8080);
});

await test('Vite serves assets and advertises hot reload through the HTTPS gateway', async () => {
    const root = mkdtempSync(join(tmpdir(), 'events-vite-'));
    let server;
    try {
        mkdirSync(join(root, 'public'));
        writeFileSync(join(root, '.env'), 'APP_ENV=local\n');
        const config = devContainerViteConfig(
            'serve',
            'https://sample-8080.app.github.dev',
        );
        server = await createServer({
            ...config,
            root,
            configFile: false,
            plugins: [
                laravel({
                    input: ['app.js'],
                    hotFile: join(root, 'public/hot'),
                }),
            ],
            optimizeDeps: { noDiscovery: true, entries: [] },
            server: { ...config.server, port: 0 },
        });
        await server.listen();
        assert.equal(
            readFileSync(join(root, 'public/hot'), 'utf8'),
            'https://sample-8080.app.github.dev/__vite',
        );
        const address = server.httpServer.address();
        const client = await fetch(
            `http://127.0.0.1:${address.port}/__vite/@vite/client`,
            {
                headers: {
                    Host: 'sample-8080.app.github.dev',
                    Origin: 'https://sample-8080.app.github.dev',
                },
            },
        );
        assert.equal(client.status, 200);
        assert.equal(
            client.headers.get('access-control-allow-origin'),
            'https://sample-8080.app.github.dev',
        );
        const source = await client.text();
        assert.match(source, /wss/);
        assert.match(source, /sample-8080.app.github.dev/);
        assert.match(source, /443/);
        const socket = new WebSocket(
            `ws://127.0.0.1:${address.port}/__vite/?token=${server.ws.token}`,
            'vite-hmr',
        );
        try {
            const [message] = await once(socket, 'message', {
                signal: AbortSignal.timeout(5000),
            });
            assert.deepEqual(JSON.parse(message.data), { type: 'connected' });
        } finally {
            socket.close();
        }
        const rejected = await new Promise((resolve, reject) => {
            get(
                `http://127.0.0.1:${address.port}/__vite/@vite/client`,
                { headers: { Host: 'untrusted.example' } },
                (response) => {
                    response.resume();
                    resolve(response.statusCode);
                },
            ).on('error', reject);
        });
        assert.equal(rejected, 403);
    } finally {
        await server?.close();
        rmSync(root, { recursive: true, force: true });
    }
});
