import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { useAuth } from './stores/auth';
import GameSetup from './components/GameSetup.vue';
import GameBoard from './components/GameBoard.vue';
import GameOver from './components/GameOver.vue';
import GameHistory from './components/GameHistory.vue';
import FriendsList from './components/FriendsList.vue';
import ProfilePage from './components/ProfilePage.vue';
import AdminLayout from './components/admin/AdminLayout.vue';
import AdminDashboard from './components/admin/AdminDashboard.vue';
import AdminCharacters from './components/admin/AdminCharacters.vue';
import AdminCards from './components/admin/AdminCards.vue';
import AdminEvents from './components/admin/AdminEvents.vue';
import AdminItems from './components/admin/AdminItems.vue';
import AdminSounds from './components/admin/AdminSounds.vue';
import SettingsPage from './components/SettingsPage.vue';
import { fetchSoundUrls } from './sounds';

const routes = [
    { path: '/', component: GameSetup },
    { path: '/campaigns', component: GameHistory },
    { path: '/friends', component: FriendsList },
    { path: '/profile', component: ProfilePage },
    { path: '/settings', component: SettingsPage },
    { path: '/game/:id', component: GameBoard, props: true },
    { path: '/game/:id/over', component: GameOver, props: true },
    {
        path: '/admin',
        component: AdminLayout,
        children: [
            { path: '', component: AdminDashboard },
            { path: 'characters', component: AdminCharacters },
            { path: 'cards', component: AdminCards },
            { path: 'events', component: AdminEvents },
            { path: 'items', component: AdminItems },
            { path: 'sounds', component: AdminSounds },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

const auth = useAuth();

router.beforeEach((to, from, next) => {
    if (to.path.startsWith('/game/') && !auth.state.user) {
        next('/');
    } else {
        next();
    }
});

fetchSoundUrls();

const app = createApp(App);
app.use(router);
app.mount('#app');
