<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Active Games</h2>
      <button class="btn-secondary" @click="fetch">Refresh</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else-if="games.length === 0" class="empty">No active or setup games.</div>

    <div v-else class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Mode</th>
            <th>Type</th>
            <th>Creator</th>
            <th>Players</th>
            <th>Round</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="g in games" :key="g.id">
            <td>{{ g.id }}</td>
            <td>
              <span class="status-badge" :class="'status-' + g.status">{{ g.status }}</span>
            </td>
            <td>{{ g.game_mode }}</td>
            <td>{{ g.game_type }}</td>
            <td class="name-col">{{ g.creator }}</td>
            <td>{{ g.players.join(', ') }}</td>
            <td>{{ g.status === 'active' ? g.current_round + '/' + g.total_rounds : '-' }}</td>
            <td class="date-col">{{ g.updated_at }}</td>
            <td class="actions-col">
              <button class="btn-danger btn-sm" @click="cancelGame(g)">Cancel</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';

export default {
  name: 'AdminGames',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      games: [],
      loading: true,
    };
  },
  async mounted() {
    await this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const res = await axios.get('/api/admin/games');
        this.games = res.data;
      } catch (e) {
        console.error('Failed to load games', e);
      }
      this.loading = false;
    },
    async cancelGame(g) {
      if (!confirm(`Cancel game #${g.id}? This will end it as a loss for all players.`)) return;
      try {
        await axios.post(`/api/admin/games/${g.id}/cancel`);
        await this.fetch();
      } catch (e) {
        this.toast.error('Cancel failed: ' + (e.response?.data?.error || e.message));
      }
    },
  },
};
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
}

.loading { text-align: center; color: var(--text-secondary); padding: 40px; }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 40px; }

.table-wrap { overflow-x: auto; }

.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.admin-table th, .admin-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid rgba(184, 148, 46, 0.2);
}

.admin-table th {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.admin-table tbody tr:hover { background: rgba(212, 168, 67, 0.05); }

.name-col { color: var(--text-bright); font-weight: 600; }
.date-col { font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap; }

.status-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 2px 10px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-setup { background: rgba(212, 168, 67, 0.2); color: #d4a843; }
.status-active { background: rgba(39, 174, 96, 0.2); color: #27ae60; }

.actions-col { white-space: nowrap; }

.btn-sm { padding: 5px 12px; font-size: 0.8rem; cursor: pointer; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border: 1px solid rgba(160, 48, 32, 0.3); border-radius: 4px; }
.btn-secondary { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
</style>
