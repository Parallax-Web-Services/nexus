import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

// Extra dev-server origins (comma-separated), for apps served on local hostnames.
const devOrigins = (process.env.VITE_DEV_ORIGINS ?? '').split(',').map((origin) => origin.trim()).filter(Boolean);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
        extensions: ['.js', '.ts', '.vue'], // 👈 Add this if needed
    },
    build: {
        rollupOptions: {
            external: /\.svg$/, // Exclude all SVG files
        },
    },
    server: {
        cors: {
            origin: [
                'http://localhost:8000',
                'http://127.0.0.1:8000',
                ...devOrigins,
            ],
        },
    },
});
