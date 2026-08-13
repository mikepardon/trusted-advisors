<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Audit Log</h2>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filters.action" @change="load">
        <option value="">All Actions</option>
        <option v-for="a in actionOptions" :key="a" :value="a">{{ a }}</option>
      </select>
      <select v-model="filters.target_type" @change="load">
        <option value="">All Types</option>
        <option v-for="t in targetTypeOptions" :key="t" :value="t">{{ t }}</option>
      </select>
      <input v-model="filters.date_from" type="date" placeholder="From" @change="load" />
      <input v-model="filters.date_to" type="date" placeholder="To" @change="load" />
      <input v-model="filters.search" type="text" placeholder="Search..." @input="debouncedLoad" />
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Admin</th>
            <th>Action</th>
            <th>Target</th>
            <th>ID</th>
            <th>Changes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td class="nowrap">{{ formatDate(log.created_at) }}</td>
            <td>{{ log.admin?.name || 'Unknown' }}</td>
            <td><span class="action-badge">{{ log.action }}</span></td>
            <td>{{ log.target_type }}</td>
            <td>{{ log.target_id }}</td>
            <td>
              <button v-if="log.changes" class="btn-sm" @click="toggleExpand(log.id)">
                {{ expanded === log.id ? 'Hide' : 'View' }}
              </button>
              <span v-else class="text-muted">&mdash;</span>
              <div v-if="expanded === log.id && log.changes" class="changes-detail">
                <div v-for="(change, field) in log.changes" :key="field" class="change-row">
                  <strong>{{ field }}:</strong>
                  <span class="old-val">{{ formatValue(change.old) }}</span>
                  <span class="arrow">&rarr;</span>
                  <span class="new-val">{{ formatValue(change.new) }}</span>
                </div>
              </div>
            </td>
          </tr>
          <tr v-if="logs.length === 0">
            <td colspan="6" class="empty">No audit logs found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="pagination">
      <button :disabled="pagination.currentPage <= 1" @click="goPage(pagination.currentPage - 1)">Prev</button>
      <span>Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
      <button :disabled="pagination.currentPage >= pagination.lastPage" @click="goPage(pagination.currentPage + 1)">Next</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { onMounted, reactive, ref } from "vue";

interface AuditChange {
  old: unknown;
  new: unknown;
}

interface AuditLogEntry {
  id: number;
  created_at: string | undefined;
  admin: { name: string } | undefined;
  action: string;
  target_type: string;
  target_id: number | undefined;
  changes: Record<string, AuditChange> | undefined;
}

interface AuditLogResponse {
  data: AuditLogEntry[];
  current_page: number;
  last_page: number;
}

const logs = ref<AuditLogEntry[]>([]);
const filters = reactive({ action: "", target_type: "", date_from: "", date_to: "", search: "" });
const pagination = reactive({ currentPage: 1, lastPage: 1 });
const expanded = ref<number | undefined>(undefined);
const debounceTimer = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
const actionOptions = ["create", "update", "delete", "ban", "unban", "impersonate", "end_season", "update_role", "grant_premium", "revoke_premium", "cancel"];
const targetTypeOptions = ["Card", "Character", "Event", "Item", "Season", "Announcement", "User", "GameRule", "AdminGift"];

async function load(page = 1): Promise<void> {
  try {
    const parameters = { ...filters, page };
    const response = await axios.get<AuditLogResponse>("/api/admin/audit-log", { params: parameters });
    logs.value = response.data.data;
    pagination.currentPage = response.data.current_page;
    pagination.lastPage = response.data.last_page;
  } catch {
    // ignore fetch errors
  }
}

function goPage(page: number): void {
  load(page);
}

function debouncedLoad(): void {
  clearTimeout(debounceTimer.value);
  debounceTimer.value = setTimeout(() => load(), 400);
}

function toggleExpand(id: number): void {
  expanded.value = expanded.value === id ? undefined : id;
}

function formatDate(date: string | undefined): string {
  if (!date) return "";
  return new Date(date).toLocaleString(undefined, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return "null";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

onMounted(async () => {
  await load();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }

.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-bar select, .filter-bar input { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 5px 8px; border-radius: 4px; font-family: inherit; font-size: 0.85rem; }
.filter-bar input[type="text"] { min-width: 150px; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
th { text-align: left; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.3); }
td { padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.1); color: var(--text-bright); vertical-align: top; }
.nowrap { white-space: nowrap; }
.action-badge { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); padding: 2px 8px; border-radius: 3px; font-size: 0.8rem; }
.text-muted { color: var(--text-secondary); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

.btn-sm { background: transparent; border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 2px 10px; border-radius: 3px; cursor: pointer; font-size: 0.78rem; }
.btn-sm:hover { background: rgba(212, 168, 67, 0.1); }

.changes-detail { margin-top: 6px; padding: 8px; background: rgba(0,0,0,0.2); border-radius: 4px; font-size: 0.8rem; }
.change-row { margin-bottom: 3px; }
.change-row strong { color: var(--text-secondary); }
.old-val { color: #e74c3c; }
.new-val { color: #2ecc71; }
.arrow { margin: 0 4px; color: var(--text-secondary); }

.pagination { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 16px; }
.pagination button { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 5px 14px; border-radius: 4px; cursor: pointer; }
.pagination button:disabled { opacity: 0.4; cursor: default; }
.pagination span { color: var(--text-secondary); font-size: 0.85rem; }
</style>
