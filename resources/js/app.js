import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import * as Sentry from '@sentry/vue';
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
import SeasonPage from './components/SeasonPage.vue';
import CharactersPage from './components/CharactersPage.vue';
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
import AdminGifts from './components/admin/AdminGifts.vue';
import AdminAnnouncements from './components/admin/AdminAnnouncements.vue';
import AdminUsers from './components/admin/AdminUsers.vue';
import AdminPayments from './components/admin/AdminPayments.vue';
import ShopPage from './components/ShopPage.vue';
import SettingsPage from './components/SettingsPage.vue';
import StatsPage from './components/StatsPage.vue';
import RotatingEventPage from './components/RotatingEventPage.vue';
import PaymentProcessing from './components/PaymentProcessing.vue';
import PremiumPage from './components/PremiumPage.vue';
import TournamentPage from './components/TournamentPage.vue';
import AdminDice from './components/admin/AdminDice.vue';
import AdminKingdomStyles from './components/admin/AdminKingdomStyles.vue';
import AdminRotatingEvents from './components/admin/AdminRotatingEvents.vue';
import AuthCallback from './components/AuthCallback.vue';
import ChooseUsername from './components/ChooseUsername.vue';
import { fetchSoundUrls } from './sounds';

const routes = [
    { path: '/', component: GameSetup },
    { path: '/auth/callback', component: AuthCallback },
    { path: '/choose-username', component: ChooseUsername, meta: { auth: true } },
    { path: '/campaigns', component: GameHistory, meta: { auth: true } },
    { path: '/friends', component: FriendsList, meta: { auth: true } },
    { path: '/profile', component: ProfilePage, meta: { auth: true } },
    { path: '/leaderboard', component: LeaderboardPage, meta: { auth: true } },
    { path: '/achievements', component: AchievementsList, meta: { auth: true } },
    { path: '/season', component: SeasonPage, meta: { auth: true } },
    { path: '/collection', component: CharactersPage, meta: { auth: true } },
    { path: '/shop', component: ShopPage, meta: { auth: true } },
    { path: '/stats', component: StatsPage, meta: { auth: true } },
    { path: '/payment/processing', component: PaymentProcessing, meta: { auth: true } },
    { path: '/premium', component: PremiumPage, meta: { auth: true } },
    { path: '/tournaments', component: TournamentPage, meta: { auth: true } },
    { path: '/events/:id', component: RotatingEventPage, props: true, meta: { auth: true } },
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
            { path: 'dice', component: AdminDice },
            { path: 'kingdom-styles', component: AdminKingdomStyles },
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
            { path: 'gifts', component: AdminGifts },
            { path: 'announcements', component: AdminAnnouncements },
            { path: 'rotating-events', component: AdminRotatingEvents },
            { path: 'users', component: AdminUsers },
            { path: 'payments', component: AdminPayments },
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
    } else if (auth.state.user?.needs_username && to.path !== '/choose-username') {
        next('/choose-username');
    } else {
        next();
    }
});

fetchSoundUrls();

const app = createApp(App);

Sentry.init({
    app,
    dsn: 'https://f3c11bce67d48dd95f896b7abc09b667@o4510966117826560.ingest.de.sentry.io/4510966121627728',
    integrations: [
        Sentry.browserTracingIntegration({ router }),
        Sentry.replayIntegration(),
    ],
    tracesSampleRate: 1.0,
    replaysSessionSampleRate: 0,
    replaysOnErrorSampleRate: 1.0,
});

app.use(router);
app.mount('#app');
