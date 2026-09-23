import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js-app/test-setup.js'],
        // Comfortably above the 5s `asyncUtilTimeout` in test-setup.js, so a
        // waiting assertion reports what it was waiting for rather than being
        // cut off by the test timeout and reporting nothing useful.
        testTimeout: 15000,
    },
});
