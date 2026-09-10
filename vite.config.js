import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // Three entries, three host pages. `resources/js/app.js` — the
            // Alpine bundle Breeze shipped — went with layouts/guest, its
            // last consumer.
            input: [
                'resources/css/app.css',
                'resources/js-app/main.jsx',
                // Guest entry: the five Breeze auth screens run outside the
                // /app shell, which is behind auth+verified — they are what a
                // visitor without it sees. See resources/js-app/auth.jsx.
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
