import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { FluentProvider } from '@fluentui/react-components';
import { StrictMode, type ComponentType } from 'react';
import { pageJaunesLightTheme } from '@/Theme/theme';

const appName = import.meta.env.VITE_APP_NAME || 'PageJaunes Mailer';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Page introuvable : ./Pages/${name}.tsx`);
        }

        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <FluentProvider theme={pageJaunesLightTheme}>
                    <App {...props} />
                </FluentProvider>
            </StrictMode>,
        );
    },
});
