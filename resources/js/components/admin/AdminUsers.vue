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
              <button class="action-btn" title="Manage User" @click="viewUser(user.id)">&#128065;</button>
              <button
                v-if="!user.is_admin"
                class="action-btn"
                title="Login as this user"
                @click="impersonateUser(user)"
              >&#128100;</button>
              <button
                v-if="!user.is_admin"
                class="action-btn"
                :class="{ 'btn-unban': user.banned_at }"
                :title="user.banned_at ? 'Unban' : 'Ban'"
                @click="toggleBan(user)"
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
    <div v-if="selectedUser" class="detail-overlay" @click.self="selectedUser = undefined">
      <div class="detail-panel">
        <button class="detail-close" @click="selectedUser = undefined">&times;</button>

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
            <button class="btn-sm btn-action" :disabled="nameLoading || !editName.trim()" @click="saveName">
              {{ nameLoading ? 'Saving...' : 'Save' }}
            </button>
          </div>
          <p v-if="nameMessage" :class="['action-msg', nameMessage.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ nameMessage }}</p>
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
            <button class="btn-sm btn-action" :disabled="roleLoading" @click="saveRole">
              {{ roleLoading ? 'Saving...' : 'Update Role' }}
            </button>
          </div>
          <p v-if="roleMessage" :class="['action-msg', roleMessage.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ roleMessage }}</p>
        </div>

        <!-- Send Notification -->
        <div class="manage-section">
          <h3 class="section-title">Send Notification</h3>
          <div class="stacked-form">
            <input v-model="notifyTitle" class="form-input" placeholder="Notification title" />
            <textarea v-model="notifyMessage" class="form-input form-textarea" rows="2" placeholder="Message body"></textarea>
            <button class="btn-sm btn-action" :disabled="notifyLoading || !notifyTitle.trim() || !notifyMessage.trim()" @click="sendNotification">
              {{ notifyLoading ? 'Sending...' : 'Send' }}
            </button>
          </div>
          <p v-if="notifyResult" :class="['action-msg', notifyResult.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ notifyResult }}</p>
        </div>

        <!-- Gift Premium -->
        <div class="manage-section">
          <h3 class="section-title">Premium Subscription</h3>
          <div v-if="selectedUser.is_premium" class="premium-active">
            <p class="premium-status">Active — expires {{ formatDate(selectedUser.premium_expires_at) }}</p>
            <button class="btn-sm btn-danger" :disabled="giftLoading" @click="revokePremium">Revoke</button>
          </div>
          <div v-else class="inline-form">
            <select v-model="giftDuration" class="form-input">
              <option value="1_month">1 Month</option>
              <option value="3_months">3 Months</option>
              <option value="6_months">6 Months</option>
              <option value="1_year">1 Year</option>
              <option value="lifetime">Lifetime</option>
            </select>
            <button class="btn-sm btn-gift" :disabled="giftLoading" @click="giftPremium">
              {{ giftLoading ? 'Gifting...' : 'Gift Premium' }}
            </button>
          </div>
          <p v-if="giftMessage" :class="['action-msg', giftMessage.startsWith('Error') ? 'msg-error' : 'msg-success']">{{ giftMessage }}</p>
        </div>

        <!-- Login Logs -->
        <div class="manage-section">
          <h3 class="section-title">Recent Login History</h3>
          <div v-if="loginLogs.length > 0" class="logs-table-wrap">
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

<script setup lang="ts">
import axios, { isAxiosError } from "axios";
import { onMounted, ref } from "vue";
import { useToast } from "../../stores/toast";

interface AdminUser {
  id: number;
  name: string;
  email?: string;
  level: number;
  xp: number;
  elo_rating: number;
  coins: number;
  timeout_count: number;
  banned_at?: string;
  is_admin: boolean;
  admin_role?: string;
  is_premium: boolean;
  premium_expires_at?: string;
  last_login_at?: string;
  created_at?: string;
  games_played: number;
  games_won: number;
  achievement_count: number;
  login_streak: number;
  max_login_streak: number;
}

interface LoginLog {
  id: number;
  logged_in_at: string;
  ip_address?: string;
  user_agent?: string;
}

interface Pagination {
  currentPage: number;
  lastPage: number;
}

interface UsersResponse {
  data: AdminUser[];
  current_page: number;
  last_page: number;
}

interface NameResponse {
  name: string;
}

interface BanResponse {
  banned_at?: string;
}

interface MessageResponse {
  message: string;
}

const toast = useToast();

const users = ref<AdminUser[]>([]);
const searchQuery = ref("");
const pagination = ref<Pagination>({ currentPage: 1, lastPage: 1 });
const selectedUser = ref<AdminUser | undefined>(undefined);
const loginLogs = ref<LoginLog[]>([]);
const searchTimer = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
// Edit name
const editName = ref("");
const nameLoading = ref(false);
const nameMessage = ref("");
// Role
const editRole = ref("");
const roleLoading = ref(false);
const roleMessage = ref("");
// Notification
const notifyTitle = ref("");
const notifyMessage = ref("");
const notifyLoading = ref(false);
const notifyResult = ref("");
// Premium
const giftDuration = ref("1_month");
const giftLoading = ref(false);
const giftMessage = ref("");

function errorMessage(error: unknown): string {
  return isAxiosError<{ error?: string; message?: string }>(error)
    ? error.response?.data?.error ?? error.response?.data?.message ?? error.message
    : "Failed";
}

async function fetchUsers(page = 1): Promise<void> {
  try {
    const parameters: { page: number; search?: string } = { page };
    if (searchQuery.value) parameters.search = searchQuery.value;
    const response = await axios.get<UsersResponse>("/api/admin/users", { params: parameters });
    users.value = response.data.data;
    pagination.value = {
      currentPage: response.data.current_page,
      lastPage: response.data.last_page,
    };
  } catch (error) {
    console.error("Failed to fetch users:", error);
  }
}

function debouncedSearch(): void {
  clearTimeout(searchTimer.value);
  searchTimer.value = setTimeout(() => {
    void fetchUsers(1);
  }, 300);
}

function goToPage(page: number): void {
  void fetchUsers(page);
}

async function viewUser(userId: number): Promise<void> {
  // Reset form state
  nameMessage.value = "";
  roleMessage.value = "";
  notifyResult.value = "";
  giftMessage.value = "";
  notifyTitle.value = "";
  notifyMessage.value = "";

  try {
    const [userResponse, logsResponse] = await Promise.all([
      axios.get<AdminUser>(`/api/admin/users/${userId}`),
      axios.get<LoginLog[]>(`/api/admin/users/${userId}/login-logs`),
    ]);
    selectedUser.value = userResponse.data;
    loginLogs.value = logsResponse.data;
    editName.value = selectedUser.value.name;
    editRole.value = selectedUser.value.admin_role || "";
  } catch (error) {
    console.error("Failed to fetch user details:", error);
  }
}

async function impersonateUser(user: AdminUser): Promise<void> {
  if (!confirm(`Login as ${user.name}? You will be redirected to the app as this user.`)) return;
  try {
    await axios.post(`/api/admin/users/${user.id}/impersonate`);
    window.location.assign("/");
  } catch (error) {
    toast.error(`Failed: ${errorMessage(error)}`);
  }
}

async function toggleBan(user: AdminUser): Promise<void> {
  const action = user.banned_at ? "unban" : "ban";
  if (!confirm(`Are you sure you want to ${action} ${user.name}?`)) return;
  try {
    const response = await axios.post<BanResponse>(`/api/admin/users/${user.id}/ban`);
    user.banned_at = response.data.banned_at;
  } catch (error) {
    toast.error(`Failed: ${errorMessage(error)}`);
  }
}

// Edit name
async function saveName(): Promise<void> {
  if (!selectedUser.value || !editName.value.trim()) return;
  nameLoading.value = true;
  nameMessage.value = "";
  try {
    const response = await axios.put<NameResponse>(`/api/admin/users/${selectedUser.value.id}/name`, {
      name: editName.value.trim(),
    });
    selectedUser.value.name = response.data.name;
    nameMessage.value = "Name updated";
    // Update table row too
    const row = users.value.find((u) => u.id === selectedUser.value?.id);
    if (row) row.name = response.data.name;
    setTimeout(() => {
      nameMessage.value = "";
    }, 3000);
  } catch (error) {
    nameMessage.value = `Error: ${errorMessage(error)}`;
  }
  nameLoading.value = false;
}

// Admin role
async function saveRole(): Promise<void> {
  if (!selectedUser.value) return;
  roleLoading.value = true;
  roleMessage.value = "";
  try {
    if (editRole.value) {
      await axios.put(`/api/admin/users/${selectedUser.value.id}/role`, { admin_role: editRole.value });
      selectedUser.value.admin_role = editRole.value;
      selectedUser.value.is_admin = true;
      roleMessage.value = `Role updated to ${editRole.value.replaceAll('_', " ")}`;
    } else {
      roleMessage.value = "Error: Select a role to assign";
      roleLoading.value = false;
      return;
    }
    // Update table row
    const row = users.value.find((u) => u.id === selectedUser.value?.id);
    if (row) row.is_admin = true;
    setTimeout(() => {
      roleMessage.value = "";
    }, 3000);
  } catch (error) {
    roleMessage.value = `Error: ${errorMessage(error)}`;
  }
  roleLoading.value = false;
}

// Send notification
async function sendNotification(): Promise<void> {
  if (!selectedUser.value || !notifyTitle.value.trim() || !notifyMessage.value.trim()) return;
  notifyLoading.value = true;
  notifyResult.value = "";
  try {
    await axios.post(`/api/admin/users/${selectedUser.value.id}/notify`, {
      title: notifyTitle.value.trim(),
      message: notifyMessage.value.trim(),
    });
    notifyResult.value = "Notification sent";
    notifyTitle.value = "";
    notifyMessage.value = "";
    setTimeout(() => {
      notifyResult.value = "";
    }, 3000);
  } catch (error) {
    notifyResult.value = `Error: ${errorMessage(error)}`;
  }
  notifyLoading.value = false;
}

// Premium
async function giftPremium(): Promise<void> {
  if (!selectedUser.value) return;
  giftLoading.value = true;
  giftMessage.value = "";
  try {
    const response = await axios.post<MessageResponse>(`/api/admin/users/${selectedUser.value.id}/grant-premium`, {
      duration: giftDuration.value,
    });
    giftMessage.value = response.data.message;
    const userResponse = await axios.get<AdminUser>(`/api/admin/users/${selectedUser.value.id}`);
    selectedUser.value = userResponse.data;
    editName.value = selectedUser.value.name;
    editRole.value = selectedUser.value.admin_role || "";
  } catch (error) {
    giftMessage.value = `Error: ${errorMessage(error)}`;
  }
  giftLoading.value = false;
}

async function revokePremium(): Promise<void> {
  if (!selectedUser.value || !confirm(`Revoke premium from ${selectedUser.value.name}?`)) return;
  giftLoading.value = true;
  giftMessage.value = "";
  try {
    const response = await axios.post<MessageResponse>(`/api/admin/users/${selectedUser.value.id}/revoke-premium`);
    giftMessage.value = response.data.message;
    const userResponse = await axios.get<AdminUser>(`/api/admin/users/${selectedUser.value.id}`);
    selectedUser.value = userResponse.data;
    editName.value = selectedUser.value.name;
    editRole.value = selectedUser.value.admin_role || "";
  } catch (error) {
    giftMessage.value = `Error: ${errorMessage(error)}`;
  }
  giftLoading.value = false;
}

function formatDate(dateString: string | undefined): string {
  if (!dateString) return "—";
  const date = new Date(dateString);
  return `${date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })} ${date.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" })}`;
}

onMounted(() => {
  void fetchUsers();
});
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
  border: 2px solid var(--border-gold);
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
