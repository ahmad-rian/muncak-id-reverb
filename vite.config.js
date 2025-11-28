import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/live-cam/broadcaster-reverb.js',
                'resources/js/live-cam/viewer-reverb.js'
            ],
            refresh: true,
        }),
    ],
});
