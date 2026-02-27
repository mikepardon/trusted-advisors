import { reactive } from 'vue';
import axios from 'axios';

const state = reactive({
    user: null,
    loading: true,
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
    state.user = res.data;
    return res.data;
}

async function register(name, password, password_confirmation) {
    const res = await axios.post('/api/auth/register', { name, password, password_confirmation });
    state.user = res.data;
    return res.data;
}

async function logout() {
    await axios.post('/api/auth/logout');
    state.user = null;
}

export function useAuth() {
    return { state, fetchUser, login, register, logout };
}
