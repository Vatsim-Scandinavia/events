import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    id: 'event_hub',
    pages: {
        path: './pages',
        extension: '.tsx',
    },
});
