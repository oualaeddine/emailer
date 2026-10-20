import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { FluentProvider } from '@fluentui/react-components';
import { router } from '@inertiajs/react';
import { themes, type ThemeMode } from '@/Theme/theme';

/**
 * docs/07-ui-design.md §7.11 — Theming.
 *
 * Resolution order (first match wins):
 *   1. the user's own choice, stored in this browser (localStorage) —
 *      « Clair », « Sombre » or « Système »;
 *   2. otherwise (or « Par défaut de l'organisation »), the organisation
 *      default `branding.default_theme`, shared by HandleInertiaRequests as
 *      `branding.defaultTheme`;
 *   3. `system` follows `prefers-color-scheme`, live.
 *
 * The same resolution runs in an inline script in resources/views/app.blade.php
 * before the bundle loads, so the page never flashes white in dark mode.
 */
export type ThemePreference = 'light' | 'dark' | 'system';
export type UserThemeChoice = ThemePreference | 'organization';

export const THEME_STORAGE_KEY = 'pjm.theme';

const DARK_QUERY = '(prefers-color-scheme: dark)';

export function normalizePreference(value: unknown): ThemePreference {
    return value === 'dark' || value === 'system' ? value : 'light';
}

function normalizeChoice(value: unknown): UserThemeChoice {
    return value === 'light' || value === 'dark' || value === 'system' ? value : 'organization';
}

export function readStoredChoice(): UserThemeChoice {
    try {
        return normalizeChoice(window.localStorage.getItem(THEME_STORAGE_KEY));
    } catch {
        return 'organization';
    }
}

function writeStoredChoice(choice: UserThemeChoice): void {
    try {
        if (choice === 'organization') {
            window.localStorage.removeItem(THEME_STORAGE_KEY);
        } else {
            window.localStorage.setItem(THEME_STORAGE_KEY, choice);
        }
    } catch {
        // Private mode / blocked storage: the choice lasts for this page only.
    }
}

function systemPrefersDark(): boolean {
    try {
        return window.matchMedia(DARK_QUERY).matches;
    } catch {
        return false;
    }
}

/** Organisation default as shared by the backend on every Inertia page. */
export function organizationDefaultFromProps(props: unknown): ThemePreference {
    const branding = (props as { branding?: { defaultTheme?: unknown } } | undefined)?.branding;

    return normalizePreference(branding?.defaultTheme);
}

interface ThemeContextValue {
    /** What the user picked in the account menu. */
    choice: UserThemeChoice;
    setChoice: (choice: UserThemeChoice) => void;
    /** Organisation default (branding.default_theme). */
    organizationDefault: ThemePreference;
    setOrganizationDefault: (preference: ThemePreference) => void;
    /** The theme actually rendered. */
    mode: ThemeMode;
}

const ThemeContext = createContext<ThemeContextValue>({
    choice: 'organization',
    setChoice: () => undefined,
    organizationDefault: 'light',
    setOrganizationDefault: () => undefined,
    mode: 'light',
});

export function useThemeSettings(): ThemeContextValue {
    return useContext(ThemeContext);
}

/** Shortcut for components that only need to know whether the UI is dark. */
export function useIsDarkTheme(): boolean {
    return useContext(ThemeContext).mode === 'dark';
}

interface ThemeRootProps {
    initialOrganizationDefault: ThemePreference;
    children: ReactNode;
}

export function ThemeRoot({ initialOrganizationDefault, children }: ThemeRootProps) {
    const [choice, setChoiceState] = useState<UserThemeChoice>(readStoredChoice);
    const [organizationDefault, setOrganizationDefault] = useState<ThemePreference>(initialOrganizationDefault);
    const [systemDark, setSystemDark] = useState<boolean>(systemPrefersDark);

    // The organisation default can change while the app is open (branding
    // page saved, or another admin saved it): every Inertia response carries it.
    useEffect(() => {
        const update = (event: { detail: { page: { props: unknown } } }) => {
            setOrganizationDefault(organizationDefaultFromProps(event.detail.page.props));
        };
        const offNavigate = router.on('navigate', update);
        const offSuccess = router.on('success', update);

        return () => {
            offNavigate();
            offSuccess();
        };
    }, []);

    // `system` follows the OS live.
    useEffect(() => {
        let media: MediaQueryList;
        try {
            media = window.matchMedia(DARK_QUERY);
        } catch {
            return;
        }
        const onChange = (event: MediaQueryListEvent) => setSystemDark(event.matches);
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    // Another tab changed the choice.
    useEffect(() => {
        const onStorage = (event: StorageEvent) => {
            if (event.key === THEME_STORAGE_KEY || event.key === null) {
                setChoiceState(readStoredChoice());
            }
        };
        window.addEventListener('storage', onStorage);

        return () => window.removeEventListener('storage', onStorage);
    }, []);

    const setChoice = useCallback((next: UserThemeChoice) => {
        writeStoredChoice(next);
        setChoiceState(next);
    }, []);

    const effective: ThemePreference = choice === 'organization' ? organizationDefault : choice;
    const mode: ThemeMode = effective === 'system' ? (systemDark ? 'dark' : 'light') : effective;

    // Page canvas outside the provider (overscroll, body margins) and native
    // controls (scrollbars, date pickers) follow the theme too.
    useEffect(() => {
        const root = document.documentElement;
        root.dataset.theme = mode;
        root.style.colorScheme = mode;
    }, [mode]);

    const value = useMemo<ThemeContextValue>(
        () => ({ choice, setChoice, organizationDefault, setOrganizationDefault, mode }),
        [choice, setChoice, organizationDefault, mode],
    );

    return (
        <ThemeContext.Provider value={value}>
            <FluentProvider theme={themes[mode]}>
                {children}
            </FluentProvider>
        </ThemeContext.Provider>
    );
}
