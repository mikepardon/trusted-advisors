<template>
  <div class="admin-layout">
    <!-- Mobile hamburger -->
    <button class="sidebar-toggle" @click="sidebarOpen = !sidebarOpen" v-if="isMobile">
      <span class="hamburger-icon">&#9776;</span>
    </button>

    <!-- Backdrop for mobile -->
    <div v-if="isMobile && sidebarOpen" class="sidebar-backdrop" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <nav class="admin-sidebar" :class="{ open: sidebarOpen }">
      <router-link to="/admin" class="sidebar-brand">Admin Panel</router-link>

      <div class="nav-section">
        <router-link to="/admin" class="nav-item" exact-active-class="active">
          <span class="nav-icon">&#9670;</span> Dashboard
        </router-link>
      </div>

      <div class="nav-section">
        <span class="nav-group-label">Content</span>
        <router-link to="/admin/characters" class="nav-item" active-class="active">
          <span class="nav-icon">&#9812;</span> Characters
        </router-link>
        <router-link to="/admin/cards" class="nav-item" active-class="active">
          <span class="nav-icon">&#9830;</span> Cards
        </router-link>
        <router-link to="/admin/events" class="nav-item" active-class="active">
          <span class="nav-icon">&#9889;</span> Events
        </router-link>
        <router-link to="/admin/items" class="nav-item" active-class="active">
          <span class="nav-icon">&#9878;</span> Items
        </router-link>
        <router-link to="/admin/sounds" class="nav-item" active-class="active">
          <span class="nav-icon">&#9835;</span> Sounds
        </router-link>
        <router-link to="/admin/addons" class="nav-item" active-class="active">
          <span class="nav-icon">&#128230;</span> Addons
        </router-link>
      </div>

      <div class="nav-section">
        <span class="nav-group-label">Progression</span>
        <router-link to="/admin/seasons" class="nav-item" active-class="active">
          <span class="nav-icon">&#128197;</span> Seasons
        </router-link>
        <router-link to="/admin/xp" class="nav-item" active-class="active">
          <span class="nav-icon">&#11088;</span> XP &amp; Levels
        </router-link>
        <router-link to="/admin/achievements" class="nav-item" active-class="active">
          <span class="nav-icon">&#127942;</span> Achievements
        </router-link>
        <router-link to="/admin/unlockables" class="nav-item" active-class="active">
          <span class="nav-icon">&#128274;</span> Unlockables
        </router-link>
        <router-link to="/admin/challenges" class="nav-item" active-class="active">
          <span class="nav-icon">&#128203;</span> Challenges
        </router-link>
      </div>

      <div class="nav-section">
        <span class="nav-group-label">Tools</span>
        <router-link to="/admin/games" class="nav-item" active-class="active">
          <span class="nav-icon">&#127918;</span> Games
        </router-link>
        <router-link to="/admin/bot-games" class="nav-item" active-class="active">
          <span class="nav-icon">&#129302;</span> Bot Games
        </router-link>
      </div>

      <div class="nav-section nav-bottom">
        <router-link to="/" class="nav-item nav-back">
          <span class="nav-icon">&#8592;</span> Back to Game
        </router-link>
      </div>
    </nav>

    <!-- Main content -->
    <div class="admin-main">
      <router-view />
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminLayout',
  data() {
    return {
      sidebarOpen: false,
      isMobile: false,
    };
  },
  watch: {
    $route() {
      if (this.isMobile) {
        this.sidebarOpen = false;
      }
    },
  },
  mounted() {
    this.checkMobile();
    window.addEventListener('resize', this.checkMobile);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.checkMobile);
  },
  methods: {
    checkMobile() {
      this.isMobile = window.innerWidth <= 768;
      if (!this.isMobile) {
        this.sidebarOpen = false;
      }
    },
  },
};
</script>

<style scoped>
.admin-layout {
  min-height: 100vh;
  display: flex;
}

/* Sidebar */
.admin-sidebar {
  width: 220px;
  min-height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 100;
  background: var(--bg-secondary);
  border-right: 2px solid var(--border-gold);
  display: flex;
  flex-direction: column;
  padding: 0;
  overflow-y: auto;
}

.sidebar-brand {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  text-decoration: none;
  font-weight: 700;
  padding: 20px 18px 16px;
  border-bottom: 1px solid rgba(184, 148, 46, 0.2);
}

.nav-section {
  padding: 8px 0;
}

.nav-group-label {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 0.65rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 2px;
  padding: 10px 18px 4px;
  opacity: 0.7;
}

.nav-item {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  gap: 10px;
  padding: 9px 18px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 0.92rem;
  transition: all 0.2s;
  border-left: 3px solid transparent;
  white-space: nowrap;
}

.nav-item:hover {
  color: var(--text-bright);
  background: rgba(212, 168, 67, 0.08);
}

.nav-item.active {
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.12);
  border-left-color: var(--accent-gold);
}

.nav-icon {
  font-size: 1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.nav-bottom {
  margin-top: auto;
  border-top: 1px solid rgba(184, 148, 46, 0.2);
  padding-top: 8px;
  padding-bottom: 12px;
}

.nav-back {
  opacity: 0.7;
  font-size: 0.85rem;
}

.nav-back:hover {
  opacity: 1;
}

/* Main content area */
.admin-main {
  margin-left: 220px;
  flex: 1;
  padding: 24px 28px;
}

/* Mobile hamburger */
.sidebar-toggle {
  position: fixed;
  top: 10px;
  left: 10px;
  z-index: 150;
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  color: var(--accent-gold);
  width: 40px;
  height: 40px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.2rem;
}

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 90;
}

/* Mobile */
@media (max-width: 768px) {
  .admin-sidebar {
    transform: translateX(-100%);
    transition: transform 0.25s ease;
  }

  .admin-sidebar.open {
    transform: translateX(0);
  }

  .admin-main {
    margin-left: 0;
    padding: 60px 16px 24px;
  }
}
</style>
