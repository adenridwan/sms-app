import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@/components': path.resolve(__dirname, './resources/js/components'),
            '@/lib': path.resolve(__dirname, './resources/js/lib'),
            '@/hooks': path.resolve(__dirname, './resources/js/hooks'),
            '@/types': path.resolve(__dirname, './resources/js/types'),
            '@/stores': path.resolve(__dirname, './resources/js/stores'),
            '@/services': path.resolve(__dirname, './resources/js/services'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
    },
});
