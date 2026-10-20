import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { StrictMode, type ComponentType } from 'react';
import { ThemeRoot, organizationDefaultFromProps } from '@/Theme/ThemeContext';

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
                <ThemeRoot initialOrganizationDefault={organizationDefaultFromProps(props.initialPage.props)}>
                    <App {...props} />
                </ThemeRoot>
            </StrictMode>,
        );
    },
});

if ('serviceWorker' in navigator && window.location.protocol.startsWith('http')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .catch((error) => {
                console.error('Service worker registration failed:', error);
            });
    });
}

