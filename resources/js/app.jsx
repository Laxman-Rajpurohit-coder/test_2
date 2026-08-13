import '../css/app.css';
import './bootstrap';

// Global back-button / popstate interceptor
// To maintain instant SPA transition speeds when switching features, we only force
// a hard reload on back/forward navigation if the user has logged out (i.e. 'is_logged_in' flag is removed).
window.addEventListener('popstate', (e) => {
    if (!localStorage.getItem('is_logged_in')) {
        window.location.reload();
    }
}, true);


import { createInertiaApp } from '@inertiajs/react';

import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob([
            './Pages/**/*.jsx',
            './Modules/**/Pages/**/*.jsx'
        ]);

        if (name.startsWith('Modules/')) {
            const parts = name.split('/');
            const moduleName = parts[1];
            const pagePath = parts.slice(2).join('/');
            return resolvePageComponent(`./Modules/${moduleName}/Pages/${pagePath}.jsx`, pages);
        }

        // Check if component exists in core ./Pages/ or in a module ./Modules/
        const corePath = `./Pages/${name}.jsx`;
        if (pages[corePath]) {
            return resolvePageComponent(corePath, pages);
        }

        // Fallback: Check if name matches a module page (e.g. "Analytics/Index" -> "./Modules/Analytics/Pages/Index.jsx")
        const parts = name.split('/');
        if (parts.length >= 2) {
            const moduleName = parts[0];
            const pagePath = parts.slice(1).join('/');
            const modulePath = `./Modules/${moduleName}/Pages/${pagePath}.jsx`;
            if (pages[modulePath]) {
                return resolvePageComponent(modulePath, pages);
            }
        }

        return resolvePageComponent(corePath, pages);
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
