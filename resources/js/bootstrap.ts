import axios from 'axios';
import type { AxiosError, InternalAxiosRequestConfig } from 'axios';

Reflect.set(globalThis, 'axios', axios);

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.timeout = 15_000;

// Custom flag we set on a request config to avoid retrying more than once.
type RetryableRequestConfig = InternalAxiosRequestConfig & { _retried?: boolean };

// Retry once on CSRF token mismatch (419) by refreshing the token
axios.interceptors.response.use(
    response => response,
    async (error: AxiosError) => {
        const originalRequest = error.config as RetryableRequestConfig | undefined;
        const hasAlreadyRetried = originalRequest?._retried === true;
        if (originalRequest && !hasAlreadyRetried && error.response?.status === 419) {
            originalRequest._retried = true;
            await axios.get('/api/auth/me');
            return axios(originalRequest);
        }
        throw error;
    },
);

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

Reflect.set(globalThis, 'Pusher', Pusher);

const isUseTLS = (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https';

try {
    const echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        wsHost: import.meta.env.VITE_PUSHER_HOST,
        wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
        wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
        forceTLS: isUseTLS,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });
    Reflect.set(globalThis, 'Echo', echo);
} catch (error) {
    console.warn('Echo initialization failed:', error);
}
