import { reactive } from 'vue';
import axios from 'axios';
import { initOneSignal, promptPushPermission } from '../onesignal';

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

async function login(name, password) {
    const res = await axios.post('/api/auth/login', { name, password });
    if (res.data.requires_verification) {
        return res.data; // { requires_verification: true, user_id }
    }
    if (res.data.streak_xp_awarded) {
        state.streakNotification = {
            streak: res.data.login_streak,
            xp: res.data.streak_xp_awarded,
        };
    }
    state.user = res.data;
    // Register push subscription for newly logged-in user
    initOneSignal().then(() => promptPushPermission());
    return res.data;
}

async function register(name, email, password, password_confirmation) {
    const res = await axios.post('/api/auth/register', { name, email, password, password_confirmation });
    return res.data; // { requires_verification: true, user_id }
}

async function verifyEmail(userId, code) {
    const res = await axios.post('/api/auth/verify-email', { user_id: userId, code });
    state.user = res.data;
    initOneSignal().then(() => promptPushPermission());
    return res.data;
}

async function resendVerification(userId) {
    const res = await axios.post('/api/auth/resend-verification', { user_id: userId });
    return res.data;
}

async function logout() {
    await axios.post('/api/auth/logout');
    state.user = null;
}

export function useAuth() {
    return { state, fetchUser, login, register, logout, verifyEmail, resendVerification };
}
