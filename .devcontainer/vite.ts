import type { UserConfig } from 'vite-plus';

export function devContainerViteConfig(
    command: string,
    origin?: string,
): UserConfig {
    if (command !== 'serve' || !origin) {
        return {};
    }

    const url = new URL(origin);

    return {
        base: '/__vite/',
        server: {
            host: '127.0.0.1',
            port: 5173,
            strictPort: true,
            origin: url.origin,
            allowedHosts: [url.hostname],
            cors: { origin: url.origin },
            hmr: {
                host: url.hostname,
                protocol: url.protocol === 'https:' ? 'wss' : 'ws',
                clientPort: Number(
                    url.port || (url.protocol === 'https:' ? 443 : 80),
                ),
            },
        },
    };
}
