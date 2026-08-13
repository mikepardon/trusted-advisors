import { reactive } from "vue";
import axios from "axios";

export interface StreakNotification {
    streak: number;
    xp: number;
}

/**
 * Shape returned by /api/auth/me (see App\Http\Resources\UserResource plus the
 * computed extras merged by AuthController::me).
 */
export interface User {
    id: number;
    name: string | undefined;
    email: string | undefined;
    avatar_url: string | undefined;
    level: number;
    xp: number;
    coins: number;
    elo_rating: number;
    is_admin: boolean;
    admin_role: string | undefined;
    is_premium: boolean;
    premium_expires_at: string | undefined;
    banned_at: string | undefined;
    timeout_count: number;
    login_streak: number;
    max_login_streak: number;
    last_login_at: string | undefined;
    username_chosen: boolean;
    notification_preferences: Record<string, unknown> | undefined;
    referred_by: number | undefined;
    active_dice_theme_slug: string | undefined;
    active_kingdom_style_slug: string | undefined;
    active_title: string | undefined;
    created_at: string | undefined;
    needs_username?: boolean;
    needs_advisors?: boolean;
    streak_notification?: StreakNotification;
    is_impersonating?: boolean;
    impersonator_name?: string;
    payments_enabled?: boolean;
    tournaments_enabled?: boolean;
}

interface AuthState {
    user: User | undefined;
    loading: boolean;
    streakNotification: StreakNotification | undefined;
}

interface UserStatsUpdate {
    xp?: number;
    level?: number;
    coins?: number;
    is_premium?: boolean;
}

const state = reactive<AuthState>({
    user: undefined,
    loading: true,
    streakNotification: undefined,
});

async function fetchUser(): Promise<void> {
    try {
        const response = await axios.get<User | null>("/api/auth/me");
        const data = response.data ?? undefined;
        state.user = response.status === 200 && data?.id ? data : undefined;

        // Streak toast queued server-side by the OAuth callback
        if (state.user?.streak_notification) {
            state.streakNotification = state.user.streak_notification;
        }
    } catch {
        state.user = undefined;
    } finally {
        state.loading = false;
    }
}

async function logout(): Promise<void> {
    await axios.post("/api/auth/logout");
    state.user = undefined;
}

function updateUserStats({ xp, level, coins, is_premium }: UserStatsUpdate): void {
    if (!state.user) {
        return;
    }

    if (xp !== undefined) {
        state.user.xp = xp;
    }
    if (level !== undefined) {
        state.user.level = level;
    }
    if (coins !== undefined) {
        state.user.coins = coins;
    }
    if (is_premium !== undefined) {
        state.user.is_premium = is_premium;
    }
}

export function useAuth() {
    return { state, fetchUser, logout, updateUserStats };
}
