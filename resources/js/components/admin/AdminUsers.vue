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
            <th>Timeouts</th>
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
            <td :class="{ 'text-warn': user.timeout_count > 0 }">{{ user.timeout_count || 0 }}</td>
            <td>
              <span v-if="user.banned_at" class="status-banned">Banned</span>
              <span v-else class="status-active">Active</span>
            </td>
            <td>{{ formatDate(user.last_login_at) }}</td>
            <td class="actions-cell">
              <button class="action-btn" @click="viewUser(user.id)" title="Manage User">&#128065;</button>
              <button
                v-if="!user.is_admin"
                class="action-btn"
                @click="impersonateUser(user)"
                title="Login as this user"
              >&#128100;</button>
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

    <!-- Manage User Panel -->
    <div v-if="selectedUser" class="detail-overlay" @click.self="selectedUser = null">
      <div class="detail-panel">
        <button class="detail-close" @click="selectedUser = null">&times;</button>

        <!-- Header -->
        <div class="detail-header">
          <h2 class="detail-name">{{ selectedUser.name }}</h2>
          <p class="detail-email">{{ selectedUser.email }}</p>
          <div class="detail-badges">
            <span v-if="selectedUser.is_admin" class="badge badge-admin">{{ selectedUser.admin_role ? selectedUser.admin_role.replace('_', ' ') : 'Admin' }}</span>
            <span v-if="selectedUser.banned_at" class="badge badge-banned">Banned</span>
            <span v-if="selectedUser.is_premium" class="badge badge-premium">Premium</span>
          </div>
        </div>

        <!-- Stats Grid -->
        <div class="detail-stats">
          <div class="stat-item"><span class="stat-label">Level</span><span class="stat-val">{{ selectedUser.level }}</span></div>
          <div class="stat-item"><span class="stat-label">XP</span><span class="stat-val">{{ selectedUser.xp }}</span></div>
          <div class="stat-item"><span class="stat-label">ELO</span><span class="stat-val">{{ selectedUser.elo_rating }}</span></div>
          <div class="stat-item"><span class="stat-label">Coins</span><span class="stat-val">{{ selectedUser.coins }}</span></div>
          <div class="stat-item"><span class="stat-label">Games Played</span><span class="stat-val">{{ selectedUser.games_played }}</span></div>
          <div class="stat-item"><span class="stat-label">Games Won</span><span class="stat-val">{{ selectedUser.games_won }}</span></div>
          <div class="stat-item"><span class="stat-label">Achievements</span><span class="stat-val">{{ selectedUser.achievement_count }}</span></div>
          <div class="stat-item"><span class="stat-label">Login Streak</span><span class="stat-val">{{ selectedUser.login_streak }} (max: {{ selectedUser.max_login_streak }})</span></div>
          <div class="stat-item"><span class="stat-label">Timeouts</span><span class="stat-val" :class="{ 'text-warn': selectedUser.timeout_count > 0 }">{{ selectedUser.timeout_count || 0 }}</span></div>
          <div class="stat-item"><span class="stat-label">Joined</span><span class="stat-val">{{ formatDate(selectedUser.created_at) }}</span></div>
        </div>

        <!-- Edit Name -->
        <div class="manage-section">
          <h3 class="section-title">Edit Name</h3>
          <div class="inline-form">
            <input v-model="editName" class="form-input" placeholder="New display name" />
            <button class="btn-sm btn-action" @click="saveName" :disabled="nameLoading || !editName.trim()">
              {{ nameLoading ? 'Saving...' : 'Save' }}
            </button>
          </div>
          <p v-if="nameMsg" :class="['action-msg', nameMsg.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ nameMsg }}</p>
        </div>

        <!-- Admin Role -->
        <div class="manage-section">
          <h3 class="section-title">Admin Role</h3>
          <div class="inline-form">
            <select v-model="editRole" class="form-input">
              <option value="">Not an admin</option>
              <option value="super_admin">Super Admin</option>
              <option value="content_admin">Content Admin</option>
              <option value="moderator">Moderator</option>
              <option value="analyst">Analyst</option>
            </select>
            <button class="btn-sm btn-action" @click="saveRole" :disabled="roleLoading">
              {{ roleLoading ? 'Saving...' : 'Update Role' }}
            </button>
          </div>
          <p v-if="roleMsg" :class="['action-msg', roleMsg.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ roleMsg }}</p>
        </div>

        <!-- Send Notification -->
        <div class="manage-section">
          <h3 class="section-title">Send Notification</h3>
          <div class="stacked-form">
            <input v-model="notifyTitle" class="form-input" placeholder="Notification title" />
            <textarea v-model="notifyMessage" class="form-input form-textarea" rows="2" placeholder="Message body"></textarea>
            <button class="btn-sm btn-action" @click="sendNotification" :disabled="notifyLoading || !notifyTitle.trim() || !notifyMessage.trim()">
              {{ notifyLoading ? 'Sending...' : 'Send' }}
            </button>
          </div>
          <p v-if="notifyMsg" :class="['action-msg', notifyMsg.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ notifyMsg }}</p>
        </div>

        <!-- Gift Premium -->
        <div class="manage-section">
          <h3 class="section-title">Premium Subscription</h3>
          <div v-if="selectedUser.is_premium" class="premium-active">
            <p class="premium-status">Active — expires {{ formatDate(selectedUser.premium_expires_at) }}</p>
            <button class="btn-sm btn-danger" @click="revokePremium" :disabled="giftLoading">Revoke</button>
          </div>
          <div v-else class="inline-form">
            <select v-model="giftDuration" class="form-input">
              <option value="1_month">1 Month</option>
              <option value="3_months">3 Months</option>
              <option value="6_months">6 Months</option>
              <option value="1_year">1 Year</option>
              <option value="lifetime">Lifetime</option>
            </select>
            <button class="btn-sm btn-gift" @click="giftPremium" :disabled="giftLoading">
              {{ giftLoading ? 'Gifting...' : 'Gift Premium' }}
            </button>
          </div>
          <p v-if="giftMsg" :class="['action-msg', giftMsg.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ giftMsg }}</p>
        </div>

        <!-- Login Logs -->
        <div class="manage-section">
          <h3 class="section-title">Recent Login History</h3>
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

  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';

export default {
  name: 'AdminUsers',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      users: [],
      searchQuery: '',
      pagination: { currentPage: 1, lastPage: 1 },
      selectedUser: null,
      loginLogs: [],
      _searchTimer: null,
      // Edit name
      editName: '',
      nameLoading: false,
      nameMsg: '',
      // Role
      editRole: '',
      roleLoading: false,
      roleMsg: '',
      // Notification
      notifyTitle: '',
      notifyMessage: '',
      notifyLoading: false,
      notifyMsg: '',
      // Premium
      giftDuration: '1_month',
      giftLoading: false,
      giftMsg: '',
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
      // Reset form state
      this.nameMsg = '';
      this.roleMsg = '';
      this.notifyMsg = '';
      this.giftMsg = '';
      this.notifyTitle = '';
      this.notifyMessage = '';

      try {
        const [userRes, logsRes] = await Promise.all([
          axios.get(`/api/admin/users/${userId}`),
          axios.get(`/api/admin/users/${userId}/login-logs`),
        ]);
        this.selectedUser = userRes.data;
        this.loginLogs = logsRes.data;
        this.editName = this.selectedUser.name;
        this.editRole = this.selectedUser.admin_role || '';
      } catch (e) {
        console.error('Failed to fetch user details:', e);
      }
    },
    async impersonateUser(user) {
      if (!confirm(`Login as ${user.name}? You will be redirected to the app as this user.`)) return;
      try {
        await axios.post(`/api/admin/users/${user.id}/impersonate`);
        window.location.href = '/';
      } catch (e) {
        this.toast.error('Failed: ' + (e.response?.data?.error || e.message));
      }
    },
    async toggleBan(user) {
      const action = user.banned_at ? 'unban' : 'ban';
      if (!confirm(`Are you sure you want to ${action} ${user.name}?`)) return;
      try {
        const res = await axios.post(`/api/admin/users/${user.id}/ban`);
        user.banned_at = res.data.banned_at;
      } catch (e) {
        this.toast.error('Failed: ' + (e.response?.data?.error || e.message));
      }
    },
    // Edit name
    async saveName() {
      if (!this.selectedUser || !this.editName.trim()) return;
      this.nameLoading = true;
      this.nameMsg = '';
      try {
        const res = await axios.put(`/api/admin/users/${this.selectedUser.id}/name`, { name: this.editName.trim() });
        this.selectedUser.name = res.data.name;
        this.nameMsg = 'Name updated';
        // Update table row too
        const row = this.users.find(u => u.id === this.selectedUser.id);
        if (row) row.name = res.data.name;
        setTimeout(() => { this.nameMsg = ''; }, 3000);
      } catch (e) {
        this.nameMsg = 'Error: ' + (e.response?.data?.message || 'Failed');
      }
      this.nameLoading = false;
    },
    // Admin role
    async saveRole() {
      if (!this.selectedUser) return;
      this.roleLoading = true;
      this.roleMsg = '';
      try {
        if (this.editRole) {
          await axios.put(`/api/admin/users/${this.selectedUser.id}/role`, { admin_role: this.editRole });
          this.selectedUser.admin_role = this.editRole;
          this.selectedUser.is_admin = true;
          this.roleMsg = 'Role updated to ' + this.editRole.replace(/_/g, ' ');
        } else {
          this.roleMsg = 'Error: Select a role to assign';
          this.roleLoading = false;
          return;
        }
        // Update table row
        const row = this.users.find(u => u.id === this.selectedUser.id);
        if (row) row.is_admin = true;
        setTimeout(() => { this.roleMsg = ''; }, 3000);
      } catch (e) {
        this.roleMsg = 'Error: ' + (e.response?.data?.message || 'Failed');
      }
      this.roleLoading = false;
    },
    // Send notification
    async sendNotification() {
      if (!this.selectedUser || !this.notifyTitle.trim() || !this.notifyMessage.trim()) return;
      this.notifyLoading = true;
      this.notifyMsg = '';
      try {
        await axios.post(`/api/admin/users/${this.selectedUser.id}/notify`, {
          title: this.notifyTitle.trim(),
          message: this.notifyMessage.trim(),
        });
        this.notifyMsg = 'Notification sent';
        this.notifyTitle = '';
        this.notifyMessage = '';
        setTimeout(() => { this.notifyMsg = ''; }, 3000);
      } catch (e) {
        this.notifyMsg = 'Error: ' + (e.response?.data?.message || 'Failed');
      }
      this.notifyLoading = false;
    },
    // Premium
    async giftPremium() {
      if (!this.selectedUser) return;
      this.giftLoading = true;
      this.giftMsg = '';
      try {
        const res = await axios.post(`/api/admin/users/${this.selectedUser.id}/grant-premium`, {
          duration: this.giftDuration,
        });
        this.giftMsg = res.data.message;
        const userRes = await axios.get(`/api/admin/users/${this.selectedUser.id}`);
        this.selectedUser = userRes.data;
        this.editName = this.selectedUser.name;
        this.editRole = this.selectedUser.admin_role || '';
      } catch (e) {
        this.giftMsg = 'Error: ' + (e.response?.data?.message || 'Failed');
      }
      this.giftLoading = false;
    },
    async revokePremium() {
      if (!this.selectedUser || !confirm(`Revoke premium from ${this.selectedUser.name}?`)) return;
      this.giftLoading = true;
      this.giftMsg = '';
      try {
        const res = await axios.post(`/api/admin/users/${this.selectedUser.id}/revoke-premium`);
        this.giftMsg = res.data.message;
        const userRes = await axios.get(`/api/admin/users/${this.selectedUser.id}`);
        this.selectedUser = userRes.data;
        this.editName = this.selectedUser.name;
        this.editRole = this.selectedUser.admin_role || '';
      } catch (e) {
        this.giftMsg = 'Error: ' + (e.response?.data?.message || 'Failed');
      }
      this.giftLoading = false;
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
  max-width: 640px;
  width: 100%;
  max-height: 85vh;
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

/* Header */
.detail-header {
  margin-bottom: 18px;
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
  margin: 0 0 8px;
}

.detail-badges {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.badge {
  font-size: 0.65rem;
  padding: 2px 8px;
  border-radius: 3px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-admin {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
}

.badge-banned {
  background: rgba(231, 76, 60, 0.15);
  color: #e74c3c;
}

.badge-premium {
  background: rgba(155, 89, 182, 0.15);
  color: #9b59b6;
}

/* Stats grid */
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

.text-warn {
  color: #e67e22 !important;
  font-weight: 600;
}

/* Manage sections */
.manage-section {
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.manage-section:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-bottom: 8px;
}

.inline-form {
  display: flex;
  gap: 8px;
  align-items: center;
}

.stacked-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-input {
  flex: 1;
  background: var(--bg-primary);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--text-bright);
  padding: 6px 10px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.88rem;
}

.form-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.form-textarea {
  resize: vertical;
  min-height: 40px;
}

.btn-sm {
  background: var(--bg-primary);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--text-bright);
  padding: 6px 14px;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.82rem;
  white-space: nowrap;
  transition: all 0.2s;
}

.btn-sm:hover {
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.btn-sm:disabled {
  opacity: 0.4;
  cursor: default;
}

.btn-action {
  background: rgba(212, 168, 67, 0.1);
  border-color: rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.btn-danger {
  background: rgba(231, 76, 60, 0.1) !important;
  color: #e74c3c !important;
  border-color: rgba(231, 76, 60, 0.3) !important;
}

.btn-gift {
  background: rgba(106, 191, 80, 0.15) !important;
  color: #6abf50 !important;
  border-color: rgba(106, 191, 80, 0.3) !important;
}

.action-msg {
  font-size: 0.8rem;
  margin-top: 6px;
}

.msg-success {
  color: #6abf50;
}

.msg-error {
  color: #e74c3c;
}

/* Premium */
.premium-active {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.premium-status {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
}

/* Login logs */
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
  .inline-form {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
