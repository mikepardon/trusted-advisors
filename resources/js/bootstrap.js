import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.timeout = 15000;

// Retry once on CSRF token mismatch (419) by refreshing the token
window.axios.interceptors.response.use(
    response => response,
    async error => {
        const originalRequest = error.config;
        if (error.response?.status === 419 && !originalRequest._retried) {
            originalRequest._retried = true;
            await window.axios.get('/api/auth/me');
            return window.axios(originalRequest);
        }
        return Promise.reject(error);
    }
);

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const useTLS = (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https';

try {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        wsHost: import.meta.env.VITE_PUSHER_HOST,
        wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
        wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
        forceTLS: useTLS,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });
} catch (e) {
    console.warn('Echo initialization failed:', e);
}
