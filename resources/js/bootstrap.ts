import axios from 'axios';

declare global {
    interface Window {
        axios: typeof axios;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

window.axios.interceptors.response.use(
    (response) => {
        sessionStorage.removeItem('auth_redirect_timestamp');
        return response;
    },
    (error) => {
        if (error.response) {
            const status = error.response.status;

            if (status === 401) {
                const isAuthPage =
                    window.location.pathname.startsWith('/login') ||
                    window.location.pathname.startsWith('/two-factor-challenge') ||
                    window.location.pathname.startsWith('/two-factor/setup');

                if (!isAuthPage) {
                    const REDIRECT_LOCK_KEY = 'auth_redirect_timestamp';
                    const now = Date.now();
                    const lastRedirect = Number(sessionStorage.getItem(REDIRECT_LOCK_KEY) || 0);

                    if (now - lastRedirect < 8000) {
                        console.error('[Auth] Boucle de redirection détectée après une erreur 401. Arrêt des redirections.');
                        window.dispatchEvent(
                            new CustomEvent('app:permission-denied', {
                                detail: {
                                    message: "Session expirée ou problème d'authentification. Veuillez vous déconnecter et vous reconnecter.",
                                    status: 401,
                                },
                            }),
                        );
                    } else {
                        sessionStorage.setItem(REDIRECT_LOCK_KEY, String(now));
                        console.warn('[Auth] Session expirée ou non authentifiée (401). Redirection vers /login.');
                        window.location.href = '/login';
                    }
                }
            } else if (status === 403) {
                const message =
                    error.response.data?.message ||
                    "Accès refusé : vous ne disposez pas des permissions nécessaires pour effectuer cette action.";

                console.warn(`[Permission] Accès refusé (403): ${message}`);

                window.dispatchEvent(
                    new CustomEvent('app:permission-denied', {
                        detail: { message, status: 403 },
                    }),
                );
            }
        }

        return Promise.reject(error);
    },
);
