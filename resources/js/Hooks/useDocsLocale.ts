import { useCallback, useEffect, useState } from 'react';
import { docsUi, type DocsLocale, type DocsUiStrings } from '@/Lib/docs/ui';

const STORAGE_KEY = 'docs.locale';
const CHANGE_EVENT = 'docs-locale-changed';

function read(): DocsLocale {
    if (typeof window === 'undefined') return 'fr';
    return window.localStorage.getItem(STORAGE_KEY) === 'ar' ? 'ar' : 'fr';
}

/**
 * Reader-chosen language for the documentation surfaces only — the app
 * interface itself stays French (docs/07-ui-design.md §7.12). Persisted in
 * localStorage and broadcast on a window event so a help dialog opened over
 * the docs center stays in sync with it.
 */
export function useDocsLocale(): {
    locale: DocsLocale;
    setLocale: (locale: DocsLocale) => void;
    dir: 'ltr' | 'rtl';
    ui: DocsUiStrings;
} {
    const [locale, setLocaleState] = useState<DocsLocale>(read);

    useEffect(() => {
        function sync() {
            setLocaleState(read());
        }
        window.addEventListener(CHANGE_EVENT, sync);
        window.addEventListener('storage', sync);
        return () => {
            window.removeEventListener(CHANGE_EVENT, sync);
            window.removeEventListener('storage', sync);
        };
    }, []);

    const setLocale = useCallback((next: DocsLocale) => {
        window.localStorage.setItem(STORAGE_KEY, next);
        setLocaleState(next);
        window.dispatchEvent(new Event(CHANGE_EVENT));
    }, []);

    return { locale, setLocale, dir: locale === 'ar' ? 'rtl' : 'ltr', ui: docsUi[locale] };
}
