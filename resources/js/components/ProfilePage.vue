<template>
  <div class="profile-page">
    <div class="card-panel">
      <h2 class="section-title">Profile</h2>

      <div class="profile-info">
        <div class="profile-avatar">&#9876;</div>
        <div class="profile-details">
          <h3 class="profile-name">{{ auth.state.user?.name }}</h3>
          <p class="profile-joined">Level {{ gameStats.level || 1 }} Advisor</p>
        </div>
      </div>
    </div>

    <!-- XP & Level -->
    <div v-if="!statsLoading" class="card-panel">
      <h2 class="section-title">Progression</h2>
      <div class="xp-section">
        <div class="xp-header">
          <span class="xp-level-badge">Lv. {{ gameStats.level || 1 }}</span>
          <span class="xp-text">{{ gameStats.xp || 0 }} / {{ gameStats.xp_for_next_level || 300 }} XP</span>
        </div>
        <div class="xp-bar-track">
          <div class="xp-bar-fill" :style="{ width: xpPercent + '%' }"></div>
        </div>
        <div class="xp-elo-row">
          <div class="elo-display">
            <span class="elo-label">ELO Rating</span>
            <span class="elo-value">{{ gameStats.elo_rating || 1000 }}</span>
          </div>
          <div class="elo-display">
            <span class="elo-label">Login Streak</span>
            <span class="elo-value streak-value">&#128293; {{ gameStats.login_streak || 0 }}</span>
          </div>
          <div class="elo-display">
            <span class="elo-label">Best Streak</span>
            <span class="elo-value">{{ gameStats.max_login_streak || 0 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Game Stats -->
    <div class="card-panel">
      <h2 class="section-title">Campaign Record</h2>
      <div v-if="statsLoading" class="stats-loading">Loading stats...</div>
      <div v-else class="stats-grid">
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.total_games }}</span>
          <span class="stat-label">Total Games</span>
        </div>
        <div class="stat-card stat-win">
          <span class="stat-number">{{ gameStats.total_wins }}</span>
          <span class="stat-label">Victories</span>
        </div>
        <div class="stat-card stat-loss">
          <span class="stat-number">{{ gameStats.total_losses }}</span>
          <span class="stat-label">Defeats</span>
        </div>
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.online_wins }}</span>
          <span class="stat-label">Online Wins</span>
        </div>
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.single_wins }}</span>
          <span class="stat-label">Solo Wins</span>
        </div>
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.pnp_wins }}</span>
          <span class="stat-label">Local Wins</span>
        </div>
      </div>
    </div>

    <!-- Account Management -->
    <div class="card-panel">
      <h2 class="section-title">Account</h2>
      <div class="account-link-wrap">
        <a :href="authServiceUrl + '/settings'" target="_blank" rel="noopener" class="btn-primary account-link">
          Manage Account
        </a>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';

export default {
  name: 'ProfilePage',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      gameStats: {},
      statsLoading: true,
      authServiceUrl: import.meta.env.VITE_AUTH_URL || '',
    };
  },
  computed: {
    xpPercent() {
      const xp = this.gameStats.xp || 0;
      const level = this.gameStats.level || 1;
      const currentLevelXp = (100 * (level - 1) * level) / 2;
      const nextLevelXp = this.gameStats.xp_for_next_level || (100 * level * (level + 1) / 2);
      const range = nextLevelXp - currentLevelXp;
      if (range <= 0) return 0;
      return Math.min(100, Math.round(((xp - currentLevelXp) / range) * 100));
    },
  },
  async mounted() {
    try {
      const res = await axios.get('/api/auth/stats');
      this.gameStats = res.data;
    } catch {
      this.gameStats = {};
    }
    this.statsLoading = false;
  },
  methods: {},
};
</script>

<style scoped>
.profile-page {
  max-width: 600px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 15px;
  text-align: center;
}

.profile-info {
  display: flex;
  align-items: center;
  gap: 16px;
  justify-content: center;
}

.profile-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: rgba(212, 168, 67, 0.15);
  border: 2px solid var(--accent-gold);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
}

.profile-details {
  text-align: left;
}

.profile-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
}

.profile-joined {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
}

/* XP Section */
.xp-section {
  text-align: center;
}

.xp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.xp-level-badge {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.xp-text {
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.xp-bar-track {
  width: 100%;
  height: 12px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 6px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  overflow: hidden;
  margin-bottom: 14px;
}

.xp-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 6px;
  transition: width 0.5s ease;
}

.xp-elo-row {
  display: flex;
  justify-content: center;
  gap: 24px;
}

.streak-value {
  font-size: 1.4rem;
}

.elo-display {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.elo-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.elo-value {
  font-family: 'Cinzel', serif;
  font-size: 1.6rem;
  color: var(--accent-gold);
  font-weight: 700;
}

/* Stats grid */
.stats-loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-card {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  padding: 14px 10px;
  text-align: center;
}

.stat-number {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.6rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.stat-card .stat-label {
  display: block;
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

.stat-win .stat-number { color: #6abf50; }
.stat-loss .stat-number { color: #d05040; }

/* Account link */
.account-link-wrap {
  text-align: center;
}

.account-link {
  display: inline-block;
  padding: 10px 28px;
  font-size: 0.95rem;
  text-decoration: none;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
