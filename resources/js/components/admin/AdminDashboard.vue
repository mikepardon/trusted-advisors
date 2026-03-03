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
      <div class="stats-grid cols-5">
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
          <div class="stat-count">{{ stats.cancelled_games || 0 }}</div>
          <div class="stat-label">Cancelled</div>
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

      <!-- Charts -->
      <div class="charts-grid" v-if="hasChartData">
        <div class="chart-card">
          <h4 class="chart-title">Games by Mode</h4>
          <Doughnut :data="modeChartData" :options="chartOptions" />
        </div>
        <div class="chart-card">
          <h4 class="chart-title">Games by Type</h4>
          <Doughnut :data="typeChartData" :options="chartOptions" />
        </div>
        <div class="chart-card">
          <h4 class="chart-title">Win Rate</h4>
          <Doughnut :data="winChartData" :options="chartOptions" />
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
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

export default {
  name: 'AdminDashboard',
  components: { Doughnut },
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
    hasChartData() {
      return this.stats.completed_games > 0 || this.stats.active_games > 0;
    },
    modeChartData() {
      const modes = this.stats.games_by_mode || {};
      return {
        labels: ['Single', 'Pass & Play', 'Online'],
        datasets: [{
          data: [modes.single || 0, modes.pass_and_play || 0, modes.online || 0],
          backgroundColor: ['#d4a843', '#8a6a2e', '#e8c468'],
          borderColor: 'rgba(30, 25, 18, 0.8)',
          borderWidth: 2,
        }],
      };
    },
    typeChartData() {
      const types = this.stats.games_by_type || {};
      return {
        labels: ['Cooperative', 'Duel'],
        datasets: [{
          data: [types.cooperative || 0, types.duel || 0],
          backgroundColor: ['#4a8a3a', '#a03020'],
          borderColor: 'rgba(30, 25, 18, 0.8)',
          borderWidth: 2,
        }],
      };
    },
    winChartData() {
      const wins = this.stats.wins || 0;
      const completed = this.stats.completed_games || 0;
      const losses = Math.max(0, completed - wins);
      return {
        labels: ['Wins', 'Losses'],
        datasets: [{
          data: [wins, losses],
          backgroundColor: ['#4a8a3a', '#a03020'],
          borderColor: 'rgba(30, 25, 18, 0.8)',
          borderWidth: 2,
        }],
      };
    },
    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#b8a67a',
              padding: 14,
              font: { size: 12 },
            },
          },
          tooltip: {
            backgroundColor: 'rgba(30, 25, 18, 0.95)',
            titleColor: '#d4a843',
            bodyColor: '#e0d6c2',
            borderColor: '#8a6a2e',
            borderWidth: 1,
          },
        },
      };
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
.cols-5 { grid-template-columns: repeat(5, 1fr); }

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

/* Charts */
.charts-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin: 24px 0;
}

.chart-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 18px;
  text-align: center;
}

.chart-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  margin-bottom: 12px;
}

@media (max-width: 768px) {
  .cols-5 { grid-template-columns: repeat(2, 1fr); }
  .cols-4 { grid-template-columns: repeat(2, 1fr); }
  .cols-3 { grid-template-columns: repeat(3, 1fr); }
  .stat-count { font-size: 1.6rem; }
  .stat-card { padding: 14px; }
  .charts-grid { grid-template-columns: 1fr; }
}
</style>
