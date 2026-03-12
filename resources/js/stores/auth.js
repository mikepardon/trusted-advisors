import { reactive } from 'vue';
import axios from 'axios';
import { isNativeApp } from 'webtonative';
import { initOneSignal, promptPushPermission } from '../onesignal';
import { generateCodeVerifier, generateCodeChallenge, generateState } from '../pkce';

const state = reactive({
    user: null,
    loading: true,
    streakNotification: null,
});

async function fetchUser() {
    try {
        const res = await axios.get('/api/auth/me');
        state.user = (res.status === 200 && res.data?.id) ? res.data : null;
    } catch {
        state.user = null;
    } finally {
        state.loading = false;
    }
}

async function startLogin(provider = null) {
    const verifier = generateCodeVerifier();
    const challenge = await generateCodeChallenge(verifier);
    const oauthState = generateState();

    // Use localStorage so PKCE values survive app backgrounding (native apps)
    localStorage.setItem('pkce_code_verifier', verifier);
    localStorage.setItem('oauth_state', oauthState);

    const authUrl = import.meta.env.VITE_AUTH_URL;
    const clientId = import.meta.env.VITE_OAUTH_CLIENT_ID;
    const redirectUri = import.meta.env.VITE_OAUTH_REDIRECT_URI;

    const params = new URLSearchParams({
        client_id: clientId,
        redirect_uri: redirectUri,
        response_type: 'code',
        scope: '',
        state: oauthState,
        code_challenge: challenge,
        code_challenge_method: 'S256',
        prompt: 'login',
    });

    if (provider) {
        params.set('provider', provider);
    }

    const url = `${authUrl}/oauth/authorize?${params.toString()}`;

    // Google blocks OAuth in WebViews, so open in system browser.
    // Apple works fine in WebViews, so keep it in-app.
    if (isNativeApp && provider === 'google') {
        window.location.href = url + '&loadIn=defaultBrowser';
    } else {
        window.location.href = url;
    }
}

async function handleCallback(code, returnedState) {
    const savedState = localStorage.getItem('oauth_state');
    if (returnedState !== savedState) {
        throw new Error('Invalid state parameter. Please try again.');
    }

    const verifier = localStorage.getItem('pkce_code_verifier');
    if (!verifier) {
        throw new Error('Missing PKCE verifier. Please try again.');
    }

    const res = await axios.post('/api/auth/callback', {
        code,
        code_verifier: verifier,
    });

    // Clean up
    localStorage.removeItem('pkce_code_verifier');
    localStorage.removeItem('oauth_state');

    if (res.data.streak_xp_awarded) {
        state.streakNotification = {
            streak: res.data.login_streak,
            xp: res.data.streak_xp_awarded,
        };
    }

    state.user = res.data;

    // Register push subscription
    initOneSignal().then(() => promptPushPermission());

    return res.data;
}

async function logout() {
    await axios.post('/api/auth/logout');
    state.user = null;
}

function updateUserStats({ xp, level, coins, is_premium }) {
    if (state.user) {
        if (xp !== undefined) state.user.xp = xp;
        if (level !== undefined) state.user.level = level;
        if (coins !== undefined) state.user.coins = coins;
        if (is_premium !== undefined) state.user.is_premium = is_premium;
    }
}

export function useAuth() {
    return { state, fetchUser, startLogin, handleCallback, logout, updateUserStats };
}
