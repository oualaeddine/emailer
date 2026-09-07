import { useState, useEffect, useCallback } from 'react';

export interface BeforeInstallPromptEvent extends Event {
    readonly platforms: string[];
    readonly userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
    prompt(): Promise<void>;
}

const DISMISSED_KEY = 'pj_pwa_install_dismissed';
const DISMISS_DURATION_MS = 7 * 24 * 60 * 60 * 1000; // 7 days

export function usePwaInstall() {
    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [isInstallable, setIsInstallable] = useState(false);
    const [isInstalled, setIsInstalled] = useState(false);
    const [isDismissed, setIsDismissed] = useState(false);

    useEffect(() => {
        // 1. Check if already running in standalone PWA mode
        const isStandalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            (window.navigator as unknown as { standalone?: boolean }).standalone === true;

        if (isStandalone) {
            setIsInstalled(true);
            return;
        }

        // 2. Check if user previously dismissed the prompt recently
        const dismissedAt = localStorage.getItem(DISMISSED_KEY);
        if (dismissedAt) {
            const age = Date.now() - parseInt(dismissedAt, 10);
            if (age < DISMISS_DURATION_MS) {
                setIsDismissed(true);
            } else {
                localStorage.removeItem(DISMISSED_KEY);
            }
        }

        // 3. Listen for browser PWA installation trigger
        const handleBeforeInstallPrompt = (event: Event) => {
            event.preventDefault();
            const installEvent = event as BeforeInstallPromptEvent;
            setDeferredPrompt(installEvent);
            setIsInstallable(true);
        };

        const handleAppInstalled = () => {
            setIsInstalled(true);
            setIsInstallable(false);
            setDeferredPrompt(null);
            localStorage.removeItem(DISMISSED_KEY);
        };

        window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
        window.addEventListener('appinstalled', handleAppInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
            window.removeEventListener('appinstalled', handleAppInstalled);
        };
    }, []);

    const install = useCallback(async () => {
        if (!deferredPrompt) {
            return false;
        }

        try {
            await deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            if (choice.outcome === 'accepted') {
                setIsInstalled(true);
                setIsInstallable(false);
                setDeferredPrompt(null);
                return true;
            }
            return false;
        } catch (error) {
            console.error('PWA install prompt error:', error);
            return false;
        }
    }, [deferredPrompt]);

    const dismiss = useCallback(() => {
        setIsDismissed(true);
        localStorage.setItem(DISMISSED_KEY, Date.now().toString());
    }, []);

    return {
        isInstallable: isInstallable && !isInstalled,
        showBanner: isInstallable && !isInstalled && !isDismissed,
        isInstalled,
        install,
        dismiss,
    };
}
