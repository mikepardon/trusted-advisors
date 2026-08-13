<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Player Retention</h2>
      <div class="period-selector">
        <button v-for="p in ['7d', '30d', '90d']" :key="p" :class="{ active: period === p }" @click="period = p; loadAll()">{{ p }}</button>
      </div>
    </div>

    <!-- Overview Cards -->
    <div class="metric-cards">
      <div class="metric-card">
        <div class="metric-value">{{ overview.dau ?? '—' }}</div>
        <div class="metric-label">DAU</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">{{ overview.wau ?? '—' }}</div>
        <div class="metric-label">WAU</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">{{ overview.mau ?? '—' }}</div>
        <div class="metric-label">MAU</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">{{ overview.new_today ?? '—' }}</div>
        <div class="metric-label">New Today</div>
      </div>
    </div>

    <!-- Active Users Chart -->
    <div class="section">
      <h3>Daily Active Users</h3>
      <div class="chart-area">
        <div v-if="activeUsers.length === 0" class="empty">No data for this period</div>
        <div v-else class="simple-chart">
          <div v-for="(day, i) in activeUsers" :key="i" class="chart-bar-wrap" :title="day.date + ': ' + day.active_users + ' active'">
            <div class="chart-bar" :style="{ height: barHeight(day.active_users) + '%' }"></div>
            <div v-if="i % labelInterval === 0" class="chart-bar-label">{{ shortDate(day.date) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cohort Table -->
    <div class="section">
      <h3>Retention Cohorts</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Cohort</th>
              <th>Size</th>
              <th>Day 1</th>
              <th>Day 7</th>
              <th>Day 14</th>
              <th>Day 30</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(c, i) in cohorts" :key="i">
              <td>{{ c.week }}</td>
              <td>{{ c.cohort_size }}</td>
              <td :class="retentionClass(c.day_1)">{{ c.day_1 !== null ? c.day_1 + '%' : '—' }}</td>
              <td :class="retentionClass(c.day_7)">{{ c.day_7 !== null ? c.day_7 + '%' : '—' }}</td>
              <td :class="retentionClass(c.day_14)">{{ c.day_14 !== null ? c.day_14 + '%' : '—' }}</td>
              <td :class="retentionClass(c.day_30)">{{ c.day_30 !== null ? c.day_30 + '%' : '—' }}</td>
            </tr>
            <tr v-if="cohorts.length === 0"><td colspan="6" class="empty">No cohort data</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Churn + Completion -->
    <div class="bottom-row">
      <div class="section half">
        <h3>Churn Indicators</h3>
        <div class="metric-cards">
          <div class="metric-card warn">
            <div class="metric-value">{{ churn.inactive_7d ?? '—' }}</div>
            <div class="metric-label">Inactive 7d</div>
          </div>
          <div class="metric-card warn">
            <div class="metric-value">{{ churn.inactive_14d ?? '—' }}</div>
            <div class="metric-label">Inactive 14d</div>
          </div>
          <div class="metric-card danger">
            <div class="metric-value">{{ churn.inactive_30d ?? '—' }}</div>
            <div class="metric-label">Inactive 30d</div>
          </div>
        </div>
      </div>
      <div class="section half">
        <h3>Game Completion</h3>
        <div class="metric-cards">
          <div class="metric-card">
            <div class="metric-value">{{ completion.completion_rate ?? '—' }}%</div>
            <div class="metric-label">Completed</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ completion.cancelled ?? '—' }}</div>
            <div class="metric-label">Cancelled</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ completion.timed_out ?? '—' }}</div>
            <div class="metric-label">Timed Out</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import axios from "axios";

interface Overview {
  dau?: number;
  wau?: number;
  mau?: number;
  new_today?: number;
}

interface ActiveUsersDay {
  date: string;
  active_users: number;
}

interface Cohort {
  week: string;
  cohort_size: number;
  day_1: number | undefined;
  day_7: number | undefined;
  day_14: number | undefined;
  day_30: number | undefined;
}

interface Churn {
  inactive_7d?: number;
  inactive_14d?: number;
  inactive_30d?: number;
}

interface Completion {
  completion_rate?: number;
  cancelled?: number;
  timed_out?: number;
}

const period = ref("30d");
const overview = ref<Overview>({});
const activeUsers = ref<ActiveUsersDay[]>([]);
const cohorts = ref<Cohort[]>([]);
const churn = ref<Churn>({});
const completion = ref<Completion>({});

const maxActive = computed(() => Math.max(1, ...activeUsers.value.map((d) => d.active_users)));

const labelInterval = computed(() => {
  if (activeUsers.value.length <= 14) {
    return 1;
  }
  if (activeUsers.value.length <= 30) {
    return 3;
  }
  return 7;
});

async function loadOverview(): Promise<void> {
  try {
    const response = await axios.get<Overview>("/api/admin/retention/overview");
    overview.value = response.data;
  } catch {
    // Ignore load failures; UI shows placeholder dashes.
  }
}

async function loadActiveUsers(): Promise<void> {
  try {
    const response = await axios.get<ActiveUsersDay[]>("/api/admin/retention/active-users", {
      params: { period: period.value },
    });
    activeUsers.value = response.data;
  } catch {
    // Ignore load failures; UI shows placeholder dashes.
  }
}

async function loadCohorts(): Promise<void> {
  try {
    const response = await axios.get<Cohort[]>("/api/admin/retention/cohorts");
    cohorts.value = response.data;
  } catch {
    // Ignore load failures; UI shows placeholder dashes.
  }
}

async function loadChurn(): Promise<void> {
  try {
    const response = await axios.get<Churn>("/api/admin/retention/churn");
    churn.value = response.data;
  } catch {
    // Ignore load failures; UI shows placeholder dashes.
  }
}

async function loadCompletion(): Promise<void> {
  try {
    const response = await axios.get<Completion>("/api/admin/retention/completion", {
      params: { period: period.value },
    });
    completion.value = response.data;
  } catch {
    // Ignore load failures; UI shows placeholder dashes.
  }
}

async function loadAll(): Promise<void> {
  await Promise.all([
    loadOverview(),
    loadActiveUsers(),
    loadCohorts(),
    loadChurn(),
    loadCompletion(),
  ]);
}

function barHeight(value: number): number {
  return Math.max(2, (value / maxActive.value) * 100);
}

function shortDate(d: string): string {
  const dt = new Date(d);
  return dt.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function retentionClass(value: number | undefined): string {
  if (value == undefined) {
    return "";
  }
  if (value >= 40) {
    return "ret-good";
  }
  if (value >= 20) {
    return "ret-mid";
  }
  return "ret-low";
}

onMounted(async () => {
  await loadAll();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.period-selector { display: flex; gap: 4px; }
.period-selector button { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); color: var(--text-secondary); padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 0.82rem; }
.period-selector button.active { color: var(--accent-gold); border-color: var(--accent-gold); background: rgba(212, 168, 67, 0.1); }

.metric-cards { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.metric-card { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 8px; padding: 14px 18px; min-width: 100px; flex: 1; }
.metric-card.warn { border-color: rgba(241, 196, 15, 0.3); }
.metric-card.danger { border-color: rgba(231, 76, 60, 0.3); }
.metric-value { font-size: 1.5rem; font-weight: 700; color: var(--accent-gold); }
.metric-label { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

.section { margin-bottom: 24px; }
.section h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.1rem; margin-bottom: 12px; }

.chart-area { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 8px; padding: 16px; }
.simple-chart { display: flex; align-items: flex-end; gap: 2px; height: 120px; }
.chart-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.chart-bar { width: 100%; max-width: 16px; background: linear-gradient(180deg, var(--accent-gold), rgba(212, 168, 67, 0.4)); border-radius: 2px 2px 0 0; min-height: 2px; transition: height 0.3s; }
.chart-bar-label { font-size: 0.6rem; color: var(--text-secondary); margin-top: 4px; white-space: nowrap; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
th { text-align: left; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.3); }
td { padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.1); color: var(--text-bright); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

.ret-good { color: #2ecc71; font-weight: 600; }
.ret-mid { color: #f1c40f; font-weight: 600; }
.ret-low { color: #e74c3c; font-weight: 600; }

.bottom-row { display: flex; gap: 16px; }
.half { flex: 1; }
@media (max-width: 768px) { .bottom-row { flex-direction: column; } }
</style>
