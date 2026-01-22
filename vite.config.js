import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.jsx"],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        cors: {
            origin: "*",
            credentials: true,
        },
        origin: process.env.CODESPACE_NAME
            ? `https://${process.env.CODESPACE_NAME}-5173.${process.env.GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}`
            : "http://localhost:5173",
        hmr: {
            host: process.env.CODESPACE_NAME
                ? `${process.env.CODESPACE_NAME}-5173.${process.env.GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}`
                : "localhost",
            protocol: process.env.CODESPACE_NAME ? "wss" : "ws",
            clientPort: process.env.CODESPACE_NAME ? 443 : 5173,
        },
        watch: {
            ignored: ["**/storage/framework/views/**"],
            usePolling: true,
        },
    },
});
