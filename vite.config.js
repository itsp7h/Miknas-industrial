import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js-app/main.jsx',
                // Guest entry: the login screen runs outside the /app shell,
                // which is behind auth+verified. See resources/js-app/auth.jsx.
                'resources/js-app/auth.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
