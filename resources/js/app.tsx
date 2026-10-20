import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { StrictMode, type ComponentType } from 'react';
import { ThemeRoot, organizationDefaultFromProps } from '@/Theme/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'PageJaunes Mailer';

/**
 * Fluent's modal dialogs hide the rest of the page (`aria-hidden` on #app)
 * while open. When a dialog unmounts because an Inertia visit replaced the
 * page, that attribute was left behind and the whole app vanished from the
 * accessibility tree until a reload. Clear it whenever no modal is open.
 */
function releaseStaleModalHiding(root: HTMLElement): void {
    const release = () => {
        if (root.getAttribute('aria-hidden') !== 'true') {
            return;
        }
        const modalOpen = document.querySelector('[role="dialog"][aria-modal="true"], [role="alertdialog"][aria-modal="true"]');
        if (!modalOpen) {
            root.removeAttribute('aria-hidden');
        }
    };
    // Let a dialog that is opening finish mounting before deciding.
    new MutationObserver(() => window.setTimeout(release, 50)).observe(root, { attributes: true, attributeFilter: ['aria-hidden'] });
    document.addEventListener('inertia:navigate', () => window.setTimeout(release, 50));
}

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
        releaseStaleModalHiding(el);
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

