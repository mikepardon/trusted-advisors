import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { useAuth } from './stores/auth';
import GameSetup from './components/GameSetup.vue';
import GameBoard from './components/GameBoard.vue';
import GameOver from './components/GameOver.vue';
import GameHistory from './components/GameHistory.vue';
import GameReplay from './components/GameReplay.vue';
import PublicReplay from './components/PublicReplay.vue';
import FriendsList from './components/FriendsList.vue';
import ProfilePage from './components/ProfilePage.vue';
import LeaderboardPage from './components/LeaderboardPage.vue';
import AchievementsList from './components/AchievementsList.vue';
import AdminLayout from './components/admin/AdminLayout.vue';
import AdminDashboard from './components/admin/AdminDashboard.vue';
import AdminCharacters from './components/admin/AdminCharacters.vue';
import AdminCards from './components/admin/AdminCards.vue';
import AdminEvents from './components/admin/AdminEvents.vue';
import AdminItems from './components/admin/AdminItems.vue';
import AdminSounds from './components/admin/AdminSounds.vue';
import AdminBotGames from './components/admin/AdminBotGames.vue';
import AdminSeasons from './components/admin/AdminSeasons.vue';
import AdminAchievements from './components/admin/AdminAchievements.vue';
import AdminUnlockables from './components/admin/AdminUnlockables.vue';
import AdminChallenges from './components/admin/AdminChallenges.vue';
import AdminGames from './components/admin/AdminGames.vue';
import AdminXp from './components/admin/AdminXp.vue';
import AdminCoins from './components/admin/AdminCoins.vue';
import AdminAddons from './components/admin/AdminAddons.vue';
import AdminUsers from './components/admin/AdminUsers.vue';
import ShopPage from './components/ShopPage.vue';
import SettingsPage from './components/SettingsPage.vue';
import AuthCallback from './components/AuthCallback.vue';
import { fetchSoundUrls } from './sounds';

const routes = [
    { path: '/', component: GameSetup },
    { path: '/auth/callback', component: AuthCallback },
    { path: '/campaigns', component: GameHistory, meta: { auth: true } },
    { path: '/friends', component: FriendsList, meta: { auth: true } },
    { path: '/profile', component: ProfilePage, meta: { auth: true } },
    { path: '/leaderboard', component: LeaderboardPage, meta: { auth: true } },
    { path: '/achievements', component: AchievementsList, meta: { auth: true } },
    { path: '/shop', component: ShopPage, meta: { auth: true } },
    { path: '/settings', component: SettingsPage },
    { path: '/game/:id', component: GameBoard, props: true, meta: { auth: true } },
    { path: '/game/:id/over', component: GameOver, props: true, meta: { auth: true } },
    { path: '/game/:id/replay', component: GameReplay, props: true, meta: { auth: true } },
    { path: '/replay/:token', component: PublicReplay, props: true },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { auth: true, admin: true },
        children: [
            { path: '', component: AdminDashboard },
            { path: 'characters', component: AdminCharacters },
            { path: 'cards', component: AdminCards },
            { path: 'events', component: AdminEvents },
            { path: 'items', component: AdminItems },
            { path: 'sounds', component: AdminSounds },
            { path: 'bot-games', component: AdminBotGames },
            { path: 'games', component: AdminGames },
            { path: 'xp', component: AdminXp },
            { path: 'coins', component: AdminCoins },
            { path: 'addons', component: AdminAddons },
            { path: 'seasons', component: AdminSeasons },
            { path: 'achievements', component: AdminAchievements },
            { path: 'unlockables', component: AdminUnlockables },
            { path: 'challenges', component: AdminChallenges },
            { path: 'users', component: AdminUsers },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

const auth = useAuth();
const authReady = auth.fetchUser();

router.beforeEach(async (to, from, next) => {
    // Wait for initial auth check to complete before guarding
    if (auth.state.loading) {
        await authReady;
    }

    const requiresAuth = to.matched.some(r => r.meta.auth);
    const requiresAdmin = to.matched.some(r => r.meta.admin);

    if (requiresAuth && !auth.state.user) {
        next('/');
    } else if (requiresAdmin && !auth.state.user?.is_admin) {
        next('/');
    } else {
        next();
    }
});

fetchSoundUrls();

const app = createApp(App);
app.use(router);
app.mount('#app');
