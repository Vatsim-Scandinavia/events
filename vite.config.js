import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/sass/vendor.scss',
                'resources/js/app.js',
                'resources/js/bootstrap-table.js',
                'resources/js/fullcalendar.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                additionalData: '$envColorPrimary: ' + (process.env.BOOTSTRAP_COLOR_PRIMARY || '#1a475f') + '; $envColorSecondary: ' + (process.env.BOOTSTRAP_COLOR_SECONDARY || '#484b4c') + '; $envColorTertiary: ' + (process.env.BOOTSTRAP_COLOR_TERTIARY || '#011328') + '; $envColorInfo: ' + (process.env.BOOTSTRAP_COLOR_INFO || '#17a2b8') + '; $envColorSuccess: ' + (process.env.BOOTSTRAP_COLOR_SUCCESS || '#41826e') + '; $envColorWarning: ' + (process.env.BOOTSTRAP_COLOR_WARNING || '#ff9800') + '; $envColorDanger: ' + (process.env.BOOTSTRAP_COLOR_DANGER || '#b63f3f') + '; $envBorderRadius: ' + (process.env.BOOTSTRAP_BORDER_RADIUS || '0px') + ';',
            }
        }
    },
});
