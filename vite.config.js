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
                // Public entry: the supplier quote portal is reached by token
                // with no account at all. See resources/js-app/rfq.jsx.
                'resources/js-app/rfq.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
