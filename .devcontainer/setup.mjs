import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

const codespace = process.env.CODESPACE_NAME;
const domain = process.env.GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN;

if (process.env.CODESPACES === 'true' && (!codespace || !domain)) {
    throw new Error('Codespaces forwarding environment variables are missing.');
}

const origin =
    process.env.CODESPACES === 'true'
        ? `https://${codespace}-8080.${domain}`
        : 'http://localhost:8080';

let environment = readFileSync(
    existsSync('.env') ? '.env' : '.env.example',
    'utf8',
);

for (const [key, value] of Object.entries({
    APP_URL: origin,
    DEVCONTAINER_URL: origin,
    TRUSTED_PROXIES: '127.0.0.1',
    INERTIA_SSR_HOT_URL: 'http://127.0.0.1:5173',
})) {
    const entry = new RegExp(`^${key}=.*$`, 'gm');
    environment = entry.test(environment)
        ? environment.replace(entry, () => `${key}=${value}`)
        : `${environment.trimEnd()}\n${key}=${value}\n`;
}

writeFileSync('.env', environment, { mode: 0o600 });
rmSync('public/hot', { force: true });
console.log(
    'Environment ready. Run composer dev, then open forwarded port 8080.',
);
