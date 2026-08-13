import { reactive } from 'vue';
import axios from 'axios';

const state = reactive({
    user: null,
    loading: true,
    streakNotification: null,
});

async function fetchUser() {
    try {
        const res = await axios.get('/api/auth/me');
        state.user = (res.status === 200 && res.data?.id) ? res.data : null;

        // Streak toast queued server-side by the OAuth callback
        if (state.user?.streak_notification) {
            state.streakNotification = state.user.streak_notification;
        }
    } catch {
        state.user = null;
    } finally {
        state.loading = false;
    }
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
    return { state, fetchUser, logout, updateUserStats };
}
