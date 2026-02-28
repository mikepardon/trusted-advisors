<template>
  <div class="dashboard">
    <h2 class="page-title">Dashboard</h2>

    <div v-if="loading" class="loading">Loading stats...</div>

    <template v-else>
      <!-- Player Stats -->
      <h3 class="section-title">Players</h3>
      <div class="stats-grid cols-2">
        <div class="stat-card">
          <div class="stat-count">{{ stats.total_users }}</div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ stats.verified_users }}</div>
          <div class="stat-label">Verified Users</div>
        </div>
      </div>

      <!-- Game Stats -->
      <h3 class="section-title">Games</h3>
      <div class="stats-grid cols-4">
        <div class="stat-card">
          <div class="stat-count">{{ stats.completed_games }}</div>
          <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ stats.active_games }}</div>
          <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ stats.setup_games }}</div>
          <div class="stat-label">In Setup</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ winRate }}%</div>
          <div class="stat-label">Win Rate</div>
        </div>
      </div>

      <!-- By Mode -->
      <h3 class="section-title">By Mode</h3>
      <div class="stats-grid cols-3">
        <div class="stat-card">
          <div class="stat-count">{{ stats.games_by_mode?.single || 0 }}</div>
          <div class="stat-label">Single Player</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ stats.games_by_mode?.pass_and_play || 0 }}</div>
          <div class="stat-label">Pass & Play</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ stats.games_by_mode?.online || 0 }}</div>
          <div class="stat-label">Online</div>
        </div>
      </div>

      <!-- By Type -->
      <h3 class="section-title">By Type</h3>
      <div class="stats-grid cols-2">
        <div class="stat-card">
          <div class="stat-count">{{ stats.games_by_type?.cooperative || 0 }}</div>
          <div class="stat-label">Cooperative</div>
        </div>
        <div class="stat-card">
          <div class="stat-count">{{ stats.games_by_type?.duel || 0 }}</div>
          <div class="stat-label">Duel</div>
        </div>
      </div>

      <!-- Content -->
      <h3 class="section-title">Content</h3>
      <div class="stats-grid cols-4">
        <router-link to="/admin/characters" class="stat-card stat-link">
          <div class="stat-count">{{ stats.content_counts?.characters || 0 }}</div>
          <div class="stat-label">Characters</div>
        </router-link>
        <router-link to="/admin/cards" class="stat-card stat-link">
          <div class="stat-count">{{ stats.content_counts?.cards || 0 }}</div>
          <div class="stat-label">Cards</div>
        </router-link>
        <router-link to="/admin/events" class="stat-card stat-link">
          <div class="stat-count">{{ stats.content_counts?.events || 0 }}</div>
          <div class="stat-label">Events</div>
        </router-link>
        <router-link to="/admin/items" class="stat-card stat-link">
          <div class="stat-count">{{ stats.content_counts?.items || 0 }}</div>
          <div class="stat-label">Items</div>
        </router-link>
      </div>

      <!-- Progression & Competition -->
      <h3 class="section-title">Progression</h3>
      <div class="stats-grid cols-4">
        <router-link to="/admin/seasons" class="stat-card stat-link">
          <div class="stat-count">&#128197;</div>
          <div class="stat-label">Seasons</div>
        </router-link>
        <router-link to="/admin/achievements" class="stat-card stat-link">
          <div class="stat-count">&#127942;</div>
          <div class="stat-label">Achievements</div>
        </router-link>
        <router-link to="/admin/unlockables" class="stat-card stat-link">
          <div class="stat-count">&#128274;</div>
          <div class="stat-label">Unlockables</div>
        </router-link>
        <router-link to="/admin/challenges" class="stat-card stat-link">
          <div class="stat-count">&#128203;</div>
          <div class="stat-label">Challenges</div>
        </router-link>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminDashboard',
  data() {
    return {
      loading: true,
      stats: {},
    };
  },
  computed: {
    winRate() {
      const completed = this.stats.completed_games || 0;
      if (completed === 0) return 0;
      return Math.round((this.stats.wins / completed) * 100);
    },
  },
  async mounted() {
    try {
      const res = await axios.get('/api/admin/dashboard-stats');
      this.stats = res.data;
    } catch (e) {
      console.error('Failed to load dashboard stats', e);
    }
    this.loading = false;
  },
};
</script>

<style scoped>
.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  margin-bottom: 30px;
  font-size: 1.8rem;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 1rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  margin: 24px 0 12px;
}

.stats-grid {
  display: grid;
  gap: 12px;
}

.cols-2 { grid-template-columns: repeat(2, 1fr); }
.cols-3 { grid-template-columns: repeat(3, 1fr); }
.cols-4 { grid-template-columns: repeat(4, 1fr); }

.stat-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 20px;
  text-align: center;
}

.stat-link {
  text-decoration: none;
  transition: all 0.2s;
}

.stat-link:hover {
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.2);
  transform: translateY(-2px);
}

.stat-count {
  font-family: 'Cinzel', serif;
  font-size: 2.2rem;
  color: var(--accent-gold);
  font-weight: 900;
}

.stat-label {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-top: 4px;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

@media (max-width: 768px) {
  .cols-4 { grid-template-columns: repeat(2, 1fr); }
  .cols-3 { grid-template-columns: repeat(3, 1fr); }
  .stat-count { font-size: 1.6rem; }
  .stat-card { padding: 14px; }
}
</style>
