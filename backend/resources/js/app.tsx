import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from '@/components/ui/sonner';

const appName = import.meta.env.VITE_APP_NAME || 'SMS Enterprise';

// Apply saved theme before first render to avoid flash
const savedTheme = localStorage.getItem('theme');
if (
    savedTheme === 'dark' ||
    (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)
) {
    document.documentElement.classList.add('dark');
}

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60 * 5, // 5 minutes
            retry: 1,
        },
    },
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx')
        ).catch(() =>
            // Fallback for pages that are not implemented yet:
            // render an elegant "coming soon" page instead of a blank error
            resolvePageComponent('./pages/Error.tsx', import.meta.glob('./pages/**/*.tsx'))
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <QueryClientProvider client={queryClient}>
                <App {...props} />
                <Toaster position="top-right" richColors />
            </QueryClientProvider>
        );
    },
    progress: {
        color: '#3b82f6',
        showSpinner: true,
    },
});
