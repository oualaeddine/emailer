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
    (response) => response,
    (error) => {
        if (error.response) {
            const status = error.response.status;

            if (status === 401) {
                const isAuthPage =
                    window.location.pathname.startsWith('/login') ||
                    window.location.pathname.startsWith('/two-factor-challenge');

                if (!isAuthPage) {
                    console.warn('[Auth] Session expirée ou non authentifiée (401). Redirection vers /login.');
                    window.location.href = '/login';
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
