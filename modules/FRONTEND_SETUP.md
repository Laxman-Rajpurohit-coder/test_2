# 🎨 Inertia Page Resolver Standard

To resolve Inertia page components from both core `resources/js/Pages` and modular `resources/js/Modules/*/Pages`, update `resources/js/app.jsx`:

```js
import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        if (name.startsWith('Modules/')) {
            const parts = name.split('/');
            const moduleName = parts[1];
            const pagePath = parts.slice(2).join('/');
            return resolvePageComponent(
                `./Modules/${moduleName}/Pages/${pagePath}.jsx`,
                import.meta.glob('./Modules/**/Pages/**/*.jsx')
            );
        }
        return resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx')
        );
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
```

---

## 🔒 Authentication Middleware Standard

Our API endpoints use standard web/session authentication or Sanctum API token authentication (`auth:sanctum` or `web`).
