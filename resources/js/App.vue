<template>
  <div id="game-app" :class="{ 'is-admin': isAdmin }">
    <!-- Impersonation banner -->
    <div v-if="auth.state.user?.is_impersonating" class="impersonation-banner" @click="stopImpersonating">
      Impersonating {{ auth.state.user.name }} &mdash; Click to stop
    </div>

    <SplashScreen v-if="showSplash" @done="splashDone" />

    <!-- Full header for non-game, non-admin pages -->
    <header v-if="!isAdmin && !isGamePage" class="game-header">
      <div class="header-top-bar">
        <div v-if="auth.state.user" class="header-player" @click="$router.push('/profile')">
          <div class="avatar-ring-wrap">
            <svg class="xp-ring" viewBox="0 0 44 44">
              <circle class="xp-ring-bg" cx="22" cy="22" r="20" />
              <circle
                class="xp-ring-progress"
                cx="22" cy="22" r="20"
                :stroke-dasharray="xpRingCircumference"
                :stroke-dashoffset="xpRingOffset"
              />
            </svg>
            <div class="player-avatar">{{ auth.state.user.name?.charAt(0)?.toUpperCase() || '?' }}</div>
          </div>
          <span class="player-level">Lv.{{ auth.state.user.level ?? 1 }}</span>
        </div>
        <img
          src="/images/logo.png"
          alt="Trusted Advisors"
          class="header-logo"
          @click="$router.push('/')"
        />
        <div v-if="auth.state.user" class="header-right-icons">
          <span class="header-elo" @click="navSound(); $router.push('/leaderboard')">&#127942; {{ auth.state.user.elo_rating ?? 1000 }}</span>
          <span class="header-coins" @click="navSound(); $router.push('/shop')">&#129689; {{ auth.state.user.coins ?? 0 }}</span>
        </div>
      </div>
    </header>

    <main>
      <router-view />
    </main>

    <!-- Bottom nav -->
    <nav v-if="!isAdmin && auth.state.user" class="bottom-nav">
      <router-link to="/shop" class="nav-item" :class="{ active: $route.path === '/shop' }" @click="navSound">
        <span class="nav-icon">&#128722;</span>
        <span class="nav-label">Shop</span>
      </router-link>
      <router-link to="/collection" class="nav-item" :class="{ active: $route.path === '/collection' }" @click="navSound">
        <span class="nav-icon">&#127183;</span>
        <span class="nav-label">Collection</span>
      </router-link>
      <button class="nav-item" :class="{ active: $route.path === '/' }" @click="goHome">
        <span class="nav-icon">&#9876;</span>
        <span class="nav-label">Campaigns</span>
      </button>
      <router-link to="/friends" class="nav-item" :class="{ active: $route.path === '/friends' }" @click="navSound">
        <span class="nav-icon">&#128101;</span>
        <span class="nav-label">Friends</span>
      </router-link>
      <router-link to="/profile" class="nav-item" :class="{ active: $route.path === '/profile' }" @click="navSound">
        <span class="nav-icon">&#128100;</span>
        <span class="nav-label">Profile</span>
      </router-link>
    </nav>

    <HowToPlay v-if="showHowToPlay" @close="showHowToPlay = false" />
    <NotificationsDrawer
      :open="showNotifications"
      @close="showNotifications = false"
      @update:count="notifCount = $event"
    />

    <!-- Streak toast -->
    <transition name="toast-fade">
      <div v-if="streakToast" class="streak-toast">
        <span class="streak-fire">&#128293;</span>
        <span class="streak-text">{{ streakToast.streak }}-day streak! +{{ streakToast.xp }} XP</span>
      </div>
    </transition>

    <Tutorial v-if="showTutorial" @close="showTutorial = false" />
    <ToastContainer />

    <ConfirmModal
      :visible="showLogoutConfirm"
      title="Logout"
      message="Are you sure you want to log out?"
      confirm-text="Logout"
      :dangerous="true"
      @confirm="handleLogout"
      @cancel="showLogoutConfirm = false"
    />
  </div>
</template>

<script>
import axios from 'axios';
import ConfirmModal from './components/ConfirmModal.vue';
import HowToPlay from './components/HowToPlay.vue';
import NotificationsDrawer from './components/NotificationsDrawer.vue';
import SplashScreen from './components/SplashScreen.vue';
import ToastContainer from './components/ToastContainer.vue';
import Tutorial from './components/Tutorial.vue';
import { useAuth } from './stores/auth';
import { useToast } from './stores/toast';
import { playSound } from './sounds';
import { initOneSignal, promptPushPermission } from './onesignal';

export default {
  name: 'App',
  components: { ConfirmModal, HowToPlay, NotificationsDrawer, SplashScreen, ToastContainer, Tutorial },
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast };
  },
  provide() {
    return {
      openNotifications: () => { this.showNotifications = true; },
      openRules: () => { this.showHowToPlay = true; },
      openTutorial: () => { this.showTutorial = true; },
    };
  },
  data() {
    return {
      showSplash: !window.location.pathname.startsWith('/admin') && !document.cookie.includes('splash_seen=1'),
      showHowToPlay: false,
      showNotifications: false,
      showMenuPopup: false,
      showLogoutConfirm: false,
      notifCount: 0,
      streakToast: null,
      showTutorial: false,
    };
  },
  watch: {
    'auth.state.streakNotification'(val) {
      if (val) {
        this.streakToast = val;
        this.auth.state.streakNotification = null;
        setTimeout(() => { this.streakToast = null; }, 4000);
      }
    },
  },
  computed: {
    isAdmin() {
      return this.$route.path.startsWith('/admin');
    },
    xpProgress() {
      const user = this.auth.state.user;
      if (!user) return 0;
      const level = user.level ?? 1;
      const xp = user.xp ?? 0;
      const currentLevelXp = 100 * (level - 1) * level / 2;
      const nextLevelXp = 100 * level * (level + 1) / 2;
      const range = nextLevelXp - currentLevelXp;
      if (range <= 0) return 1;
      return Math.min(Math.max((xp - currentLevelXp) / range, 0), 1);
    },
    xpRingCircumference() {
      return 2 * Math.PI * 20; // r=20
    },
    xpRingOffset() {
      return this.xpRingCircumference * (1 - this.xpProgress);
    },
    isGamePage() {
      return /^\/game\/\d+(\/.*)?$/.test(this.$route.path);
    },
    isInGame() {
      return /^\/game\/\d+$/.test(this.$route.path);
    },
    isGameOver() {
      return /^\/game\/\d+\/over$/.test(this.$route.path);
    },
  },
  mounted() {
    // Auto-show tutorial for first-time visitors
    if (!localStorage.getItem('has_seen_tutorial')) {
      this.showTutorial = true;
      localStorage.setItem('has_seen_tutorial', '1');
    }
    // fetchUser() is already called in app.js router guard; just wait for it
    const check = () => {
      if (!this.auth.state.loading && this.auth.state.user) {
        this.fetchNotifCount();
        this.subscribeNotifChannel();
        initOneSignal().then(() => promptPushPermission());
      } else if (this.auth.state.loading) {
        setTimeout(check, 50);
      }
    };
    check();
    this._closeMenuOnClick = () => { this.showMenuPopup = false; };
    document.addEventListener('click', this._closeMenuOnClick);
  },
  beforeUnmount() {
    if (this._notifChannel) {
      this._notifChannel.stopListening('GameInviteReceived');
      this._notifChannel.stopListening('FriendRequestReceived');
      this._notifChannel.stopListening('UserNotificationReceived');
      this._notifChannel.stopListening('PremiumStatusChanged');
    }
    document.removeEventListener('click', this._closeMenuOnClick);
  },
  methods: {
    splashDone() {
      this.showSplash = false;
      const expires = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toUTCString();
      document.cookie = `splash_seen=1; expires=${expires}; path=/`;
    },
    goHome() {
      playSound('clickNav');
      if (this.$route.path === '/') {
        // Already on home - emit event to reset GameSetup
        this._homeReset = (this._homeReset || 0) + 1;
        this.$router.replace({ path: '/', query: { r: this._homeReset } });
      } else {
        this.$router.push('/');
      }
    },
    navSound() {
      playSound('clickNav');
    },
    menuSound() {
      playSound('clickMenu');
    },
    async stopImpersonating() {
      try {
        await axios.post('/api/impersonate/stop');
        window.location.href = '/admin';
      } catch {
        this.toast.error('Failed to stop impersonating');
      }
    },
    async handleLogout() {
      this.showLogoutConfirm = false;
      this.showMenuPopup = false;
      if (this._notifChannel) {
        this._notifChannel.stopListening('GameInviteReceived');
        this._notifChannel.stopListening('FriendRequestReceived');
        this._notifChannel.stopListening('UserNotificationReceived');
        this._notifChannel.stopListening('PremiumStatusChanged');
        this._notifChannel = null;
      }
      await this.auth.logout();
      this.notifCount = 0;
      if (this.$route.path === '/') {
        window.location.reload();
      } else {
        this.$router.replace('/');
      }
    },
    async fetchNotifCount() {
      if (!this.auth.state.user) return;
      try {
        const [invitesRes, friendsRes, unreadRes] = await Promise.all([
          axios.get('/api/game-invites/pending'),
          axios.get('/api/friends'),
          axios.get('/api/notifications/unread-count'),
        ]);
        const invites = invitesRes.data?.length || 0;
        const friends = friendsRes.data?.pending_received?.length || 0;
        const dbUnread = unreadRes.data?.count || 0;
        this.notifCount = invites + friends + dbUnread;
      } catch {
        // ignore
      }
    },
    subscribeNotifChannel() {
      const userId = this.auth.state.user?.id;
      if (!userId) return;
      this._notifChannel = window.Echo.private(`user.${userId}`)
        .listen('GameInviteReceived', () => {
          this.notifCount++;
        })
        .listen('FriendRequestReceived', () => {
          this.notifCount++;
        })
        .listen('UserNotificationReceived', () => {
          this.notifCount++;
        })
        .listen('PremiumStatusChanged', (data) => {
          this.auth.updateUserStats({ is_premium: data.is_premium });
        });
    },
  },
};
</script>

<style>
:root {
  --bg-primary: #0d0a06;
  --bg-secondary: #1a1209;
  --bg-card: #2a1f14;
  --accent-gold: #d4a843;
  --accent-gold-bright: #e8c468;
  --accent-red: #a03020;
  --accent-green: #4a8a3a;
  --text-primary: #e8d5b7;
  --text-secondary: #a08a6a;
  --text-bright: #f5e6cc;
  --border-gold: #8a6a2e;
  --wood-light: #3a2a1a;
  --wood-medium: #2a1f14;
  --wood-dark: #1a1209;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: var(--bg-primary);
  background-image:
    radial-gradient(ellipse at 50% 0%, rgba(212, 168, 67, 0.04) 0%, transparent 60%),
    radial-gradient(ellipse at 50% 100%, rgba(160, 48, 32, 0.03) 0%, transparent 60%);
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  height: 100dvh;
  overflow: hidden;
}

#game-app {
  display: flex;
  flex-direction: column;
  height: 100dvh;
  max-width: 1000px;
  margin: 0 auto;
  padding: 0;
}

#game-app > main {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  -webkit-overflow-scrolling: touch;
}

/* ---- Full header (non-game pages) ---- */
.game-header {
  padding: 10px 12px;
  border-bottom: 2px solid var(--border-gold);
  background: linear-gradient(180deg, rgba(42, 31, 20, 0.4) 0%, transparent 100%);
  flex-shrink: 0;
}

.header-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.header-player {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  cursor: pointer;
  flex-shrink: 0;
}

.player-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent-gold), #8a6a14);
  color: var(--wood-dark);
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--border-gold);
  box-shadow: 0 2px 8px rgba(212, 168, 67, 0.3);
  transition: transform 0.2s;
}

.avatar-ring-wrap {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.avatar-ring-wrap .player-avatar {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.xp-ring {
  width: 44px;
  height: 44px;
  transform: rotate(-90deg);
}

.xp-ring-bg {
  fill: none;
  stroke: rgba(138, 106, 46, 0.25);
  stroke-width: 2.5;
}

.xp-ring-progress {
  fill: none;
  stroke: var(--accent-gold);
  stroke-width: 2.5;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s ease;
}

.header-player:hover .player-avatar {
  transform: translate(-50%, -50%) scale(1.08);
}

.player-level {
  font-family: 'Cinzel', serif;
  font-size: 0.6rem;
  color: var(--accent-gold);
  letter-spacing: 1px;
}

.header-logo {
  max-width: 200px;
  width: 100%;
  height: auto;
  cursor: pointer;
  transition: transform 0.2s ease, filter 0.2s ease;
  filter: drop-shadow(0 2px 8px rgba(212, 168, 67, 0.3));
}

.header-logo:hover {
  transform: scale(1.03);
  filter: drop-shadow(0 4px 16px rgba(212, 168, 67, 0.5));
}

.header-right-icons {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.header-elo,
.header-coins {
  display: flex;
  align-items: center;
  gap: 4px;
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  color: var(--accent-gold);
  cursor: pointer;
  padding: 4px 10px;
  border-radius: 12px;
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(138, 106, 46, 0.3);
  transition: all 0.2s;
  white-space: nowrap;
}

.header-elo:hover,
.header-coins:hover {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
}

.header-bell {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.1rem;
  color: var(--text-secondary);
  padding: 0;
  transition: all 0.2s;
  letter-spacing: 0;
}

.header-bell:hover {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
  transform: none;
  box-shadow: none;
}

.subtitle {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 1.1rem;
  margin-top: 8px;
}

.header-user {
  display: flex;
  gap: 10px;
  justify-content: center;
  align-items: center;
  margin-top: 10px;
}

.user-greeting {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  letter-spacing: 1px;
}

.header-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  align-items: center;
  margin-top: 10px;
}

.htp-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 0.85rem;
  padding: 4px 14px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  letter-spacing: 0;
}

.htp-btn:hover {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
  box-shadow: none;
  transform: none;
}

button {
  font-family: 'Cinzel', serif;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  color: var(--accent-gold);
  border: 2px solid var(--border-gold);
  padding: 10px;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  letter-spacing: 1px;
  border-radius: 4px;
}

button:hover:not(:disabled) {
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  box-shadow: 0 0 15px rgba(212, 168, 67, 0.25);
  border-color: var(--accent-gold);
  transform: translateY(-1px);
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(180deg, #b8942e, #8a6a14);
  color: #1a1209;
  font-weight: 700;
  border-color: var(--accent-gold);
  text-shadow: 0 1px 0 rgba(255, 255, 255, 0.15);
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(180deg, #d4a843, #b8942e);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.4);
}

.btn-danger {
  border-color: var(--accent-red);
  color: var(--accent-red);
}

.btn-danger:hover:not(:disabled) {
  background: linear-gradient(180deg, rgba(160, 48, 32, 0.3), var(--wood-dark));
  box-shadow: 0 0 15px rgba(160, 48, 32, 0.25);
}

.btn-success {
  border-color: var(--accent-green);
  color: var(--accent-green);
}

.card-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.admin-link {
  display: inline-block;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 0.85rem;
  padding: 4px 12px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 4px;
  transition: all 0.2s;
}

.admin-link:hover {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
}

#game-app.is-admin {
  max-width: none;
  padding: 0;
}

/* ---- Compact in-game toolbar ---- */
.game-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  border-bottom: 1px solid var(--border-gold);
  background: linear-gradient(180deg, rgba(42, 31, 20, 0.6) 0%, var(--bg-primary) 100%);
  flex-shrink: 0;
}

.toolbar-logo {
  height: 28px;
  width: auto;
  cursor: pointer;
  filter: drop-shadow(0 1px 4px rgba(212, 168, 67, 0.3));
  transition: transform 0.2s;
}

.toolbar-logo:hover {
  transform: scale(1.05);
}

.toolbar-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.toolbar-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  font-size: 1rem;
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  border-radius: 6px;
  color: var(--text-secondary);
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
  letter-spacing: 0;
}

.toolbar-btn:hover {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
  transform: none;
  box-shadow: none;
}

/* ---- Bottom navigation ---- */
.bottom-nav {
  display: flex;
  justify-content: space-around;
  align-items: center;
  border-top: 2px solid var(--border-gold);
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  padding: 6px 0;
  padding-bottom: calc(6px + env(safe-area-inset-bottom));
  flex-shrink: 0;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 4px 0;
  background: none;
  border: none;
  color: var(--text-secondary);
  text-decoration: none;
  cursor: pointer;
  transition: color 0.2s;
  font-family: 'Cinzel', serif;
  letter-spacing: 0;
}

.nav-item:hover,
.nav-item.active {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.nav-icon {
  font-size: 1.3rem;
  line-height: 1;
}

.nav-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* ---- Notification badge ---- */
.notif-icon-wrap {
  position: relative;
  display: inline-block;
}

.notif-badge {
  position: absolute;
  top: -6px;
  right: -10px;
  background: #e74c3c;
  color: #fff;
  font-size: 0.55rem;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  min-width: 16px;
  height: 16px;
  line-height: 16px;
  text-align: center;
  border-radius: 8px;
  padding: 0 4px;
}

/* ---- Coin display ---- */
.nav-coins {
  display: flex;
  align-items: center;
  gap: 4px;
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  color: var(--accent-gold);
  cursor: pointer;
  padding: 4px 10px;
  border-radius: 12px;
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(138, 106, 46, 0.3);
  transition: all 0.2s;
  white-space: nowrap;
}

.nav-coins:hover {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
}

/* ---- Menu popup ---- */
.nav-menu-wrap {
  position: relative;
}

.menu-popup {
  position: absolute;
  bottom: 100%;
  right: 0;
  margin-bottom: 8px;
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 6px 0;
  z-index: 100;
  min-width: 140px;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
}

.menu-popup-item {
  display: block;
  width: 100%;
  padding: 10px 18px;
  background: none;
  border: none;
  color: var(--text-primary);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  text-align: left;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
  letter-spacing: 0;
}

.menu-popup-item:hover {
  background: rgba(212, 168, 67, 0.1);
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.menu-popup-divider {
  height: 1px;
  background: rgba(138, 106, 46, 0.3);
  margin: 4px 0;
}

.menu-popup-logout {
  color: var(--accent-red);
}

.menu-popup-logout:hover {
  background: rgba(160, 48, 32, 0.15);
  color: #d05040;
}

/* ---- Responsive header ---- */
@media (max-width: 768px) {
  .game-header {
    display: none !important;
  }
}

/* ---- Streak toast ---- */
.streak-toast {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--accent-gold);
  border-radius: 10px;
  padding: 12px 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  z-index: 9999;
  box-shadow: 0 4px 20px rgba(212, 168, 67, 0.4);
}

.streak-fire {
  font-size: 1.5rem;
}

.streak-text {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  font-weight: 700;
  white-space: nowrap;
}

.toast-fade-enter-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}

.toast-fade-leave-active {
  transition: opacity 0.6s ease, transform 0.6s ease;
}

.toast-fade-enter-from {
  opacity: 0;
  transform: translateX(-50%) translateY(-20px);
}

.toast-fade-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(-10px);
}

/* Impersonation banner */
.impersonation-banner {
  background: #c0392b;
  color: #fff;
  text-align: center;
  padding: 8px 16px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 1px;
  cursor: pointer;
  flex-shrink: 0;
  z-index: 9999;
}

.impersonation-banner:hover {
  background: #e74c3c;
}
</style>
