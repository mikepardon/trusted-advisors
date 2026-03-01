<template>
  <div class="admin-users">
    <h1 class="page-title">User Management</h1>

    <!-- Search -->
    <div class="search-bar">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search by name or email..."
        class="search-input"
        @input="debouncedSearch"
      />
    </div>

    <!-- Users table -->
    <div class="table-wrap">
      <table class="users-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Level</th>
            <th>ELO</th>
            <th>Coins</th>
            <th>Status</th>
            <th>Last Login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" :class="{ 'row-banned': user.banned_at }">
            <td>{{ user.id }}</td>
            <td class="name-cell">
              <span class="user-name" @click="viewUser(user.id)">{{ user.name }}</span>
              <span v-if="user.is_admin" class="admin-badge">Admin</span>
            </td>
            <td>{{ user.email }}</td>
            <td>{{ user.level }}</td>
            <td>{{ user.elo_rating }}</td>
            <td>{{ user.coins }}</td>
            <td>
              <span v-if="user.banned_at" class="status-banned">Banned</span>
              <span v-else class="status-active">Active</span>
            </td>
            <td>{{ formatDate(user.last_login_at) }}</td>
            <td class="actions-cell">
              <button class="action-btn" @click="viewUser(user.id)" title="View Details">&#128065;</button>
              <button
                v-if="!user.is_admin"
                class="action-btn"
                :class="{ 'btn-unban': user.banned_at }"
                @click="toggleBan(user)"
                :title="user.banned_at ? 'Unban' : 'Ban'"
              >{{ user.banned_at ? '&#9989;' : '&#128683;' }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="pagination">
      <button :disabled="pagination.currentPage <= 1" @click="goToPage(pagination.currentPage - 1)">Prev</button>
      <span class="page-info">{{ pagination.currentPage }} / {{ pagination.lastPage }}</span>
      <button :disabled="pagination.currentPage >= pagination.lastPage" @click="goToPage(pagination.currentPage + 1)">Next</button>
    </div>

    <!-- User Detail Panel -->
    <div v-if="selectedUser" class="detail-overlay" @click.self="selectedUser = null">
      <div class="detail-panel">
        <button class="detail-close" @click="selectedUser = null">&times;</button>
        <h2 class="detail-name">{{ selectedUser.name }}</h2>
        <p class="detail-email">{{ selectedUser.email }}</p>

        <div class="detail-stats">
          <div class="stat-item"><span class="stat-label">Level</span><span class="stat-val">{{ selectedUser.level }}</span></div>
          <div class="stat-item"><span class="stat-label">XP</span><span class="stat-val">{{ selectedUser.xp }}</span></div>
          <div class="stat-item"><span class="stat-label">ELO</span><span class="stat-val">{{ selectedUser.elo_rating }}</span></div>
          <div class="stat-item"><span class="stat-label">Coins</span><span class="stat-val">{{ selectedUser.coins }}</span></div>
          <div class="stat-item"><span class="stat-label">Games Played</span><span class="stat-val">{{ selectedUser.games_played }}</span></div>
          <div class="stat-item"><span class="stat-label">Games Won</span><span class="stat-val">{{ selectedUser.games_won }}</span></div>
          <div class="stat-item"><span class="stat-label">Achievements</span><span class="stat-val">{{ selectedUser.achievement_count }}</span></div>
          <div class="stat-item"><span class="stat-label">Login Streak</span><span class="stat-val">{{ selectedUser.login_streak }} (max: {{ selectedUser.max_login_streak }})</span></div>
          <div class="stat-item"><span class="stat-label">Joined</span><span class="stat-val">{{ formatDate(selectedUser.created_at) }}</span></div>
          <div class="stat-item"><span class="stat-label">Status</span><span class="stat-val" :class="{ 'text-banned': selectedUser.banned_at }">{{ selectedUser.banned_at ? 'Banned ' + formatDate(selectedUser.banned_at) : 'Active' }}</span></div>
        </div>

        <!-- Login Logs -->
        <h3 class="logs-title">Recent Login History</h3>
        <div v-if="loginLogs.length" class="logs-table-wrap">
          <table class="logs-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>IP</th>
                <th>User Agent</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in loginLogs" :key="log.id">
                <td>{{ formatDate(log.logged_in_at) }}</td>
                <td>{{ log.ip_address }}</td>
                <td class="ua-cell">{{ log.user_agent }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="no-logs">No login logs recorded yet.</p>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminUsers',
  data() {
    return {
      users: [],
      searchQuery: '',
      pagination: { currentPage: 1, lastPage: 1 },
      selectedUser: null,
      loginLogs: [],
      _searchTimer: null,
    };
  },
  mounted() {
    this.fetchUsers();
  },
  methods: {
    async fetchUsers(page = 1) {
      try {
        const params = { page };
        if (this.searchQuery) params.search = this.searchQuery;
        const res = await axios.get('/api/admin/users', { params });
        this.users = res.data.data;
        this.pagination = {
          currentPage: res.data.current_page,
          lastPage: res.data.last_page,
        };
      } catch (e) {
        console.error('Failed to fetch users:', e);
      }
    },
    debouncedSearch() {
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => {
        this.fetchUsers(1);
      }, 300);
    },
    goToPage(page) {
      this.fetchUsers(page);
    },
    async viewUser(userId) {
      try {
        const [userRes, logsRes] = await Promise.all([
          axios.get(`/api/admin/users/${userId}`),
          axios.get(`/api/admin/users/${userId}/login-logs`),
        ]);
        this.selectedUser = userRes.data;
        this.loginLogs = logsRes.data;
      } catch (e) {
        console.error('Failed to fetch user details:', e);
      }
    },
    async toggleBan(user) {
      const action = user.banned_at ? 'unban' : 'ban';
      if (!confirm(`Are you sure you want to ${action} ${user.name}?`)) return;
      try {
        const res = await axios.post(`/api/admin/users/${user.id}/ban`);
        user.banned_at = res.data.banned_at;
      } catch (e) {
        alert('Failed: ' + (e.response?.data?.error || e.message));
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return '—';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    },
  },
};
</script>

<style scoped>
.admin-users {
  max-width: 1100px;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 20px;
}

.search-bar {
  margin-bottom: 16px;
}

.search-input {
  width: 100%;
  max-width: 400px;
  padding: 10px 14px;
  background: var(--bg-primary);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', serif;
  font-size: 1rem;
}

.search-input::placeholder {
  color: var(--text-secondary);
}

.table-wrap {
  overflow-x: auto;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.users-table th {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: left;
  padding: 10px 12px;
  border-bottom: 2px solid var(--border-gold);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  white-space: nowrap;
}

.users-table td {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
  color: var(--text-primary);
}

.users-table tr:hover {
  background: rgba(212, 168, 67, 0.05);
}

.row-banned {
  opacity: 0.6;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.user-name {
  cursor: pointer;
  color: var(--accent-gold);
  transition: color 0.2s;
}

.user-name:hover {
  color: var(--accent-gold-bright);
  text-decoration: underline;
}

.admin-badge {
  font-size: 0.6rem;
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 1px 6px;
  border-radius: 3px;
  font-family: 'Cinzel', serif;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.status-active {
  color: var(--accent-green);
  font-size: 0.8rem;
}

.status-banned {
  color: var(--accent-red);
  font-size: 0.8rem;
  font-weight: 700;
}

.actions-cell {
  display: flex;
  gap: 4px;
}

.action-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 4px;
  padding: 4px 8px;
  cursor: pointer;
  font-size: 0.9rem;
  color: var(--text-secondary);
  transition: all 0.2s;
}

.action-btn:hover {
  border-color: var(--accent-gold);
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
  transform: none;
  box-shadow: none;
}

.pagination {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
  justify-content: center;
}

.page-info {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

/* Detail overlay */
.detail-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
  padding: 20px;
}

.detail-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 28px;
  max-width: 600px;
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
}

.detail-close {
  position: absolute;
  top: 10px;
  right: 14px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.6rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.detail-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.detail-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin: 0 0 4px;
}

.detail-email {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 18px;
}

.detail-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 20px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 4px;
}

.stat-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.stat-val {
  color: var(--text-bright);
  font-weight: 600;
  font-size: 0.85rem;
}

.text-banned {
  color: var(--accent-red) !important;
}

.logs-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-bottom: 8px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
  padding-bottom: 4px;
}

.logs-table-wrap {
  overflow-x: auto;
}

.logs-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}

.logs-table th {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: left;
  padding: 6px 8px;
  border-bottom: 1px solid var(--border-gold);
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.logs-table td {
  padding: 6px 8px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.1);
  color: var(--text-primary);
}

.ua-cell {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.no-logs {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
}

@media (max-width: 768px) {
  .detail-stats {
    grid-template-columns: 1fr;
  }
}
</style>
