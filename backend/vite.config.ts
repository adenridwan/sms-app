import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // Alamat yang ditulis laravel-vite-plugin ke `public/hot`, yaitu alamat
    // yang dipakai BROWSER untuk mengambil aset saat `npm run dev`.
    //
    // Dulu ini di-hardcode ke satu IP LAN. Begitu IP mesin berubah (pindah
    // jaringan/DHCP), `public/hot` menunjuk alamat mati, semua JS/CSS gagal
    // dimuat, dan setiap halaman — termasuk /login — tampil putih polos tanpa
    // pesan error apa pun. Default `localhost` selalu benar untuk browser di
    // mesin ini; untuk uji dari HP/perangkat lain, set VITE_HMR_HOST di `.env`
    // ke IP LAN saat itu (server tetap mendengar di 0.0.0.0, jadi terjangkau).
    const hmrHost = env.VITE_HMR_HOST || 'localhost';

    return {
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
            cors: true,
            hmr: {
                host: hmrHost,
            },
        },
    };
});
