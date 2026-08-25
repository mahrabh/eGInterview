import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js', // (or app.tsx depending on your setup)
                'resources/js/live-interview.tsx' // <-- This MUST be here
            ],
            refresh: true,
        }),
        react(),
    ],
});