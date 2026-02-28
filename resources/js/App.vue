<template>
  <div id="game-app" :class="{ 'is-admin': isAdmin }">
    <SplashScreen v-if="showSplash" @done="showSplash = false" />

    <!-- Compact in-game toolbar -->
    <header v-if="isInGame" class="game-toolbar">
      <img
        src="/images/logo.png"
        alt="Trusted Advisors"
        class="toolbar-logo"
        @click="$router.push('/')"
      />
      <div class="toolbar-actions">
        <button class="toolbar-btn" @click="showHowToPlay = true" title="How to Play">?</button>
      </div>
    </header>

    <!-- Full header for non-game, non-admin pages -->
    <header v-else-if="!isAdmin" class="game-header">
      <img
        src="/images/logo.png"
        alt="Trusted Advisors"
        class="header-logo"
        @click="$router.push('/')"
      />
      <p class="subtitle">England, 1280 AD</p>
      <div v-if="auth.state.user" class="header-user">
        <span class="user-greeting">Hail, {{ auth.state.user.name }}</span>
      </div>
    </header>

    <main>
      <router-view />
    </main>

    <!-- Bottom nav -->
    <nav v-if="!isAdmin" class="bottom-nav">
      <button class="nav-item" :class="{ active: $route.path === '/' }" @click="goHome">
        <span class="nav-icon">&#127968;</span>
        <span class="nav-label">Home</span>
      </button>
      <router-link v-if="auth.state.user" to="/campaigns" class="nav-item" :class="{ active: $route.path === '/campaigns' }" @click="navSound">
        <span class="nav-icon">&#9876;</span>
        <span class="nav-label">Campaigns</span>
      </router-link>
      <router-link v-if="auth.state.user" to="/friends" class="nav-item" :class="{ active: $route.path === '/friends' }" @click="navSound">
        <span class="nav-icon">&#129309;</span>
        <span class="nav-label">Friends</span>
      </router-link>
      <button v-if="auth.state.user" class="nav-item" @click="navSound(); showNotifications = true">
        <span class="nav-icon notif-icon-wrap">
          &#128276;
          <span v-if="notifCount > 0" class="notif-badge">{{ notifCount > 9 ? '9+' : notifCount }}</span>
        </span>
        <span class="nav-label">Alerts</span>
      </button>
      <div class="nav-menu-wrap">
        <button class="nav-item" @click.stop="menuSound(); showMenuPopup = !showMenuPopup">
          <span class="nav-icon">&#9776;</span>
          <span class="nav-label">Menu</span>
        </button>
        <div v-if="showMenuPopup" class="menu-popup">
          <button class="menu-popup-item" @click="menuSound(); showHowToPlay = true; showMenuPopup = false">Rules</button>
          <router-link v-if="auth.state.user" to="/profile" class="menu-popup-item" @click="menuSound(); showMenuPopup = false">Profile</router-link>
          <router-link to="/settings" class="menu-popup-item" @click="menuSound(); showMenuPopup = false">Settings</router-link>
          <router-link v-if="auth.state.user?.is_admin" to="/admin" class="menu-popup-item" @click="menuSound(); showMenuPopup = false">Admin</router-link>
          <template v-if="auth.state.user">
            <div class="menu-popup-divider"></div>
            <button v-if="!confirmingLogout" class="menu-popup-item menu-popup-logout" @click.stop="confirmingLogout = true">Logout</button>
            <button v-else class="menu-popup-item menu-popup-logout" @click.stop="handleLogout">Are you sure?</button>
          </template>
        </div>
      </div>
    </nav>

    <HowToPlay v-if="showHowToPlay" @close="showHowToPlay = false" />
    <NotificationsDrawer
      :open="showNotifications"
      @close="showNotifications = false"
      @update:count="notifCount = $event"
    />
  </div>
</template>

<script>
import axios from 'axios';
import HowToPlay from './components/HowToPlay.vue';
import NotificationsDrawer from './components/NotificationsDrawer.vue';
import SplashScreen from './components/SplashScreen.vue';
import { useAuth } from './stores/auth';
import { playSound } from './sounds';
import { initOneSignal, promptPushPermission } from './onesignal';

export default {
  name: 'App',
  components: { HowToPlay, NotificationsDrawer, SplashScreen },
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      showSplash: !window.location.pathname.startsWith('/admin'),
      showHowToPlay: false,
      showNotifications: false,
      showMenuPopup: false,
      confirmingLogout: false,
      notifCount: 0,
    };
  },
  computed: {
    isAdmin() {
      return this.$route.path.startsWith('/admin');
    },
    isInGame() {
      return /^\/game\/\d+$/.test(this.$route.path);
    },
    isGameOver() {
      return /^\/game\/\d+\/over$/.test(this.$route.path);
    },
  },
  mounted() {
    // fetchUser() is already called in app.js router guard; just wait for it
    const check = () => {
      if (!this.auth.state.loading && this.auth.state.user) {
        this.pollNotifCount();
        initOneSignal().then(() => promptPushPermission());
      } else if (this.auth.state.loading) {
        setTimeout(check, 50);
      }
    };
    check();
    this._closeMenuOnClick = () => { this.showMenuPopup = false; this.confirmingLogout = false; };
    document.addEventListener('click', this._closeMenuOnClick);
  },
  beforeUnmount() {
    if (this._notifTimer) clearInterval(this._notifTimer);
    document.removeEventListener('click', this._closeMenuOnClick);
  },
  methods: {
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
    async handleLogout() {
      this.showMenuPopup = false;
      this.confirmingLogout = false;
      if (this._notifTimer) clearInterval(this._notifTimer);
      await this.auth.logout();
      this.notifCount = 0;
      this.$router.push('/');
    },
    async pollNotifCount() {
      const fetch = async () => {
        if (!this.auth.state.user) return;
        try {
          const [invitesRes, friendsRes] = await Promise.all([
            axios.get('/api/game-invites/pending'),
            axios.get('/api/friends'),
          ]);
          const invites = invitesRes.data?.length || 0;
          const friends = friendsRes.data?.pending_received?.length || 0;
          this.notifCount = invites + friends;
        } catch {
          // ignore
        }
      };
      await fetch();
      this._notifTimer = setInterval(fetch, 30000);
    },
  },
};
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap');

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
  text-align: center;
  padding: 15px 0 20px;
  border-bottom: 2px solid var(--border-gold);
  background: linear-gradient(180deg, rgba(42, 31, 20, 0.4) 0%, transparent 100%);
  flex-shrink: 0;
}

.header-logo {
  max-width: 320px;
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
  padding: 4px 16px;
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

/* ---- Hide header/nav on mobile for game-header ---- */
@media (max-width: 768px) {
  .game-header {
    padding: 10px 0 12px;
  }

  .header-logo {
    max-width: 200px;
  }

  .subtitle {
    font-size: 0.9rem;
    margin-top: 4px;
  }

  .header-actions {
    margin-top: 6px;
  }
}
</style>
