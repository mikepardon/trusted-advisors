<template>
  <div>
    <!-- Tab switcher -->
    <div class="tab-bar">
      <button class="tab-btn" :class="{ active: activeTab === 'daily' }" @click="activeTab = 'daily'">Daily</button>
      <button class="tab-btn" :class="{ active: activeTab === 'weekly' }" @click="activeTab = 'weekly'; loadWeekly()">Weekly</button>
    </div>

    <!-- DAILY TAB -->
    <template v-if="activeTab === 'daily'">
    <div class="page-header">
      <h2 class="page-title">Daily Challenges</h2>
      <div class="page-header-actions">
        <button class="btn-secondary" @click="showGenerateModal = true">Generate Range</button>
        <button class="btn-primary" @click="openCreate">+ New Challenge</button>
      </div>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="c in challenges" :key="c.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ c.title }}</strong>
            <span class="date-badge">{{ c.date }}</span>
            <span v-if="c.is_manual" class="manual-badge">Manual</span>
            <span v-if="c.addon_id" class="addon-badge">Addon</span>
          </div>
          <div class="list-sub">{{ c.description }} (+{{ c.reward_xp }} XP)</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(c)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteChallenge(c)">Del</button>
        </div>
      </div>
      <div v-if="challenges.length === 0" class="empty">No challenges yet.</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Challenge' : 'New Challenge' }}</h3>
        <form @submit.prevent="save">
          <div class="form-grid">
            <div class="form-group">
              <label>Date</label>
              <input v-model="form.date" type="date" required />
            </div>
            <div class="form-group">
              <label>Title</label>
              <input v-model="form.title" required />
            </div>
            <div class="form-group full">
              <label>Description</label>
              <input v-model="form.description" required />
            </div>
            <div class="form-group">
              <label>Criteria Type</label>
              <select v-model="form.criteria_type">
                <option value="play_game">Play Game</option>
                <option value="win_game">Win Game</option>
                <option value="stat_threshold">Stat Threshold</option>
                <option value="use_character">Use Character</option>
                <option value="no_stat_below">No Stat Below</option>
                <option value="no_stat_above">No Stat Above</option>
                <option value="all_stats_below">All Stats Below</option>
                <option value="all_stats_above">All Stats Above</option>
                <option value="total_score_above">Total Score Above</option>
                <option value="total_score_below">Total Score Below</option>
                <option value="login_streak">Login Streak</option>
                <option value="online_plays">Online Plays</option>
                <option value="total_friends">Total Friends</option>
                <option value="duel_plays">Duel Plays</option>
                <option value="wins_with_characters">Wins With Characters</option>
              </select>
            </div>
            <div v-if="form.criteria_type === 'play_game' || form.criteria_type === 'win_game'" class="form-group">
              <label>Mode</label>
              <select v-model="form.criteria_mode">
                <option value="any">Any</option>
                <option value="single">Solo</option>
                <option value="pass_and_play">Local</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div v-if="form.criteria_type === 'stat_threshold'" class="form-group">
              <label>Stat</label>
              <select v-model="form.criteria_stat">
                <option value="wealth">Wealth</option>
                <option value="influence">Influence</option>
                <option value="security">Security</option>
                <option value="religion">Religion</option>
                <option value="food">Food</option>
                <option value="happiness">Happiness</option>
              </select>
            </div>
            <div v-if="['stat_threshold', 'no_stat_below', 'no_stat_above', 'all_stats_below', 'all_stats_above', 'total_score_above', 'total_score_below'].includes(form.criteria_type)" class="form-group">
              <label>Value</label>
              <input v-model.number="form.criteria_value" type="number" min="1" :max="['total_score_above', 'total_score_below'].includes(form.criteria_type) ? 120 : 20" />
            </div>
            <div v-if="['login_streak', 'online_plays', 'total_friends', 'duel_plays', 'wins_with_characters'].includes(form.criteria_type)" class="form-group">
              <label>Count</label>
              <input v-model.number="form.criteria_count" type="number" min="1" />
            </div>
            <div v-if="form.criteria_type === 'use_character'" class="form-group">
              <label>Character ID</label>
              <input v-model.number="form.criteria_character_id" type="number" min="1" />
            </div>
            <div class="form-group">
              <label>Reward XP</label>
              <input v-model.number="form.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Addon</label>
              <select v-model="form.addon_id">
                <option :value="null">Base Game</option>
                <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </div>
          </div>
          <div v-if="formError" class="form-error">{{ formError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editing ? 'Update' : 'Create' }}</button>
            <button type="button" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Generate Range Modal -->
    <div v-if="showGenerateModal" class="modal-overlay" @click.self="showGenerateModal = false">
      <div class="modal-content">
        <h3>Generate Daily Challenges</h3>
        <p class="gen-desc">Auto-generate one challenge per day from the template pool. Existing dates are skipped.</p>
        <form @submit.prevent="generateRange">
          <div class="form-grid">
            <div class="form-group">
              <label>Start Date</label>
              <input v-model="genForm.start_date" type="date" required />
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input v-model="genForm.end_date" type="date" required />
            </div>
          </div>
          <div v-if="genResult" class="gen-result">{{ genResult }}</div>
          <div v-if="genError" class="form-error">{{ genError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary" :disabled="generating">{{ generating ? 'Generating...' : 'Generate' }}</button>
            <button type="button" @click="showGenerateModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    </template>

    <!-- WEEKLY TAB -->
    <template v-if="activeTab === 'weekly'">
    <div class="page-header">
      <h2 class="page-title">Weekly Challenges</h2>
      <div class="page-header-actions">
        <button class="btn-secondary" @click="showWeeklyGenerateModal = true">Generate Range</button>
        <button class="btn-primary" @click="openWeeklyCreate">+ New Weekly</button>
      </div>
    </div>

    <div class="list-panel">
      <div v-for="w in weeklyChallenges" :key="w.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ w.title }}</strong>
            <span class="date-badge">{{ w.week_start }} &mdash; {{ w.week_end }}</span>
            <span v-if="w.is_manual" class="manual-badge">Manual</span>
          </div>
          <div class="list-sub">{{ w.description }} (+{{ w.reward_xp }} XP, +{{ w.reward_coins }} coins)</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openWeeklyEdit(w)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteWeekly(w)">Del</button>
        </div>
      </div>
      <div v-if="weeklyChallenges.length === 0" class="empty">No weekly challenges yet.</div>
    </div>

    <!-- Weekly Modal -->
    <div v-if="showWeeklyModal" class="modal-overlay" @click.self="showWeeklyModal = false">
      <div class="modal-content">
        <h3>{{ editingWeekly ? 'Edit Weekly Challenge' : 'New Weekly Challenge' }}</h3>
        <form @submit.prevent="saveWeekly">
          <div class="form-grid">
            <div class="form-group">
              <label>Week Start</label>
              <input v-model="weeklyForm.week_start" type="date" required />
            </div>
            <div class="form-group">
              <label>Week End</label>
              <input v-model="weeklyForm.week_end" type="date" required />
            </div>
            <div class="form-group">
              <label>Title</label>
              <input v-model="weeklyForm.title" required />
            </div>
            <div class="form-group full">
              <label>Description</label>
              <input v-model="weeklyForm.description" required />
            </div>
            <div class="form-group">
              <label>Criteria Type</label>
              <select v-model="weeklyForm.criteria_type">
                <option value="play_games">Play Games</option>
                <option value="win_games">Win Games</option>
                <option value="win_duel_games">Win Duel Games</option>
                <option value="unique_characters_week">Unique Characters</option>
                <option value="stat_threshold_count">Stat Threshold Count</option>
              </select>
            </div>
            <div v-if="weeklyForm.criteria_type === 'play_games' || weeklyForm.criteria_type === 'win_games'" class="form-group">
              <label>Mode</label>
              <select v-model="weeklyForm.criteria_mode">
                <option value="any">Any</option>
                <option value="single">Solo</option>
                <option value="pass_and_play">Local</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div class="form-group">
              <label>Target Count</label>
              <input v-model.number="weeklyForm.criteria_count" type="number" min="1" />
            </div>
            <div v-if="weeklyForm.criteria_type === 'stat_threshold_count'" class="form-group">
              <label>Stat Value</label>
              <input v-model.number="weeklyForm.criteria_value" type="number" min="1" max="20" />
            </div>
            <div class="form-group">
              <label>Reward XP</label>
              <input v-model.number="weeklyForm.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Reward Coins</label>
              <input v-model.number="weeklyForm.reward_coins" type="number" min="0" />
            </div>
          </div>
          <div v-if="weeklyFormError" class="form-error">{{ weeklyFormError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editingWeekly ? 'Update' : 'Create' }}</button>
            <button type="button" @click="showWeeklyModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Weekly Generate Range Modal -->
    <div v-if="showWeeklyGenerateModal" class="modal-overlay" @click.self="showWeeklyGenerateModal = false">
      <div class="modal-content">
        <h3>Generate Weekly Challenges</h3>
        <p class="gen-desc">Auto-generate one challenge per week from the template pool. Existing weeks are skipped.</p>
        <form @submit.prevent="generateWeeklyRange">
          <div class="form-grid">
            <div class="form-group">
              <label>Start Date</label>
              <input v-model="weeklyGenForm.start_date" type="date" required />
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input v-model="weeklyGenForm.end_date" type="date" required />
            </div>
          </div>
          <div v-if="weeklyGenResult" class="gen-result">{{ weeklyGenResult }}</div>
          <div v-if="weeklyGenError" class="form-error">{{ weeklyGenError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary" :disabled="weeklyGenerating">{{ weeklyGenerating ? 'Generating...' : 'Generate' }}</button>
            <button type="button" @click="showWeeklyGenerateModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import axios, { isAxiosError } from "axios";

interface Criteria {
  type?: string;
  mode?: string;
  stat?: string;
  value?: number;
  character_id?: number;
  count?: number;
}

interface Challenge {
  id: number;
  date: string;
  title: string;
  description: string;
  criteria: Criteria | undefined;
  reward_xp: number;
  addon_id: number | undefined;
  is_manual: boolean;
}

interface WeeklyChallenge {
  id: number;
  week_start: string;
  week_end: string;
  title: string;
  description: string;
  criteria: Criteria | undefined;
  reward_xp: number;
  reward_coins: number;
  is_manual: boolean;
}

interface Addon {
  id: number;
  name: string;
}

interface ChallengeForm {
  date: string;
  title: string;
  description: string;
  criteria_type: string;
  criteria_mode: string;
  criteria_stat: string;
  criteria_value: number;
  criteria_character_id: number;
  criteria_count: number;
  reward_xp: number;
  addon_id: number | undefined;
}

interface WeeklyForm {
  week_start: string;
  week_end: string;
  title: string;
  description: string;
  criteria_type: string;
  criteria_mode: string;
  criteria_count: number;
  criteria_value: number;
  reward_xp: number;
  reward_coins: number;
}

interface GenerateForm {
  start_date: string;
  end_date: string;
}

function emptyForm(): ChallengeForm {
  return {
    date: "", title: "", description: "",
    criteria_type: "play_game", criteria_mode: "any",
    criteria_stat: "wealth", criteria_value: 15,
    criteria_character_id: 1, criteria_count: 5,
    reward_xp: 100, addon_id: undefined,
  };
}

function emptyWeeklyForm(): WeeklyForm {
  return {
    week_start: "", week_end: "", title: "", description: "",
    criteria_type: "play_games", criteria_mode: "any",
    criteria_count: 3, criteria_value: 18,
    reward_xp: 400, reward_coins: 75,
  };
}

const activeTab = ref("daily");
const challenges = ref<Challenge[]>([]);
const addons = ref<Addon[]>([]);
const showModal = ref(false);
const editing = ref<number | undefined>(undefined);
const formError = ref("");
const form = reactive<ChallengeForm>(emptyForm());
// Generate range
const showGenerateModal = ref(false);
const generating = ref(false);
const genForm = reactive<GenerateForm>({ start_date: "", end_date: "" });
const genResult = ref("");
const genError = ref("");
// Weekly
const weeklyChallenges = ref<WeeklyChallenge[]>([]);
const showWeeklyModal = ref(false);
const editingWeekly = ref<number | undefined>(undefined);
const weeklyFormError = ref("");
const weeklyForm = reactive<WeeklyForm>(emptyWeeklyForm());
const showWeeklyGenerateModal = ref(false);
const weeklyGenerating = ref(false);
const weeklyGenForm = reactive<GenerateForm>({ start_date: "", end_date: "" });
const weeklyGenResult = ref("");
const weeklyGenError = ref("");

async function load(): Promise<void> {
  const response = await axios.get<Challenge[]>("/api/admin/daily-challenges");
  challenges.value = response.data;
}

async function fetchAddons(): Promise<void> {
  try {
    const response = await axios.get<Addon[]>("/api/admin/addons");
    addons.value = response.data;
  } catch { /* ignore */ }
}

function buildCriteria(): Criteria {
  const type = form.criteria_type;
  if (type === "play_game") {
    return { type, mode: form.criteria_mode };
  }
  if (type === "win_game") {
    return { type, mode: form.criteria_mode };
  }
  if (type === "stat_threshold") {
    return { type, stat: form.criteria_stat, value: form.criteria_value };
  }
  if (type === "use_character") {
    return { type, character_id: form.criteria_character_id };
  }
  if (["no_stat_below", "no_stat_above", "all_stats_below", "all_stats_above", "total_score_above", "total_score_below"].includes(type)) {
    return { type, value: form.criteria_value };
  }
  if (["login_streak", "online_plays", "total_friends", "duel_plays", "wins_with_characters"].includes(type)) {
    return { type, count: form.criteria_count };
  }
  return { type };
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, emptyForm());
  formError.value = "";
  showModal.value = true;
}

function openEdit(challenge: Challenge): void {
  editing.value = challenge.id;
  const criteria = challenge.criteria ?? {};
  Object.assign(form, {
    date: challenge.date,
    title: challenge.title,
    description: challenge.description,
    criteria_type: criteria.type || "play_game",
    criteria_mode: criteria.mode || "any",
    criteria_stat: criteria.stat || "wealth",
    criteria_value: criteria.value || 15,
    criteria_character_id: criteria.character_id || 1,
    criteria_count: criteria.count || 5,
    reward_xp: challenge.reward_xp,
    addon_id: challenge.addon_id ?? undefined,
  });
  formError.value = "";
  showModal.value = true;
}

async function save(): Promise<void> {
  formError.value = "";
  const data = {
    date: form.date,
    title: form.title,
    description: form.description,
    criteria: buildCriteria(),
    reward_xp: form.reward_xp,
    addon_id: form.addon_id ?? undefined,
  };
  try {
    if (editing.value) {
      await axios.put(`/api/admin/daily-challenges/${editing.value}`, data);
    } else {
      await axios.post("/api/admin/daily-challenges", data);
    }
    showModal.value = false;
    load();
  } catch (error) {
    formError.value = isAxiosError<{ error?: string; message?: string }>(error)
      ? (error.response?.data?.error ?? error.response?.data?.message ?? "Error")
      : "Error";
  }
}

async function deleteChallenge(challenge: Challenge): Promise<void> {
  if (!confirm(`Delete "${challenge.title}"?`)) {
    return;
  }
  await axios.delete(`/api/admin/daily-challenges/${challenge.id}`);
  load();
}

async function generateRange(): Promise<void> {
  generating.value = true;
  genResult.value = "";
  genError.value = "";
  try {
    const response = await axios.post<{ message: string }>("/api/admin/daily-challenges/generate", genForm);
    genResult.value = response.data.message;
    load();
  } catch (error) {
    genError.value = isAxiosError<{ message?: string }>(error)
      ? (error.response?.data?.message ?? "Error generating challenges")
      : "Error generating challenges";
  }
  generating.value = false;
}

// Weekly challenge methods
async function loadWeekly(): Promise<void> {
  if (weeklyChallenges.value.length > 0) {
    return; // already loaded
  }
  try {
    const response = await axios.get<WeeklyChallenge[]>("/api/admin/weekly-challenges");
    weeklyChallenges.value = response.data;
  } catch { /* ignore */ }
}

function buildWeeklyCriteria(): Criteria {
  const type = weeklyForm.criteria_type;
  const base: Criteria = { type, count: weeklyForm.criteria_count };
  if (type === "play_games" || type === "win_games") {
    base.mode = weeklyForm.criteria_mode;
  } else if (type === "stat_threshold_count") {
    base.stat = "any";
    base.value = weeklyForm.criteria_value;
  }
  return base;
}

function openWeeklyCreate(): void {
  editingWeekly.value = undefined;
  Object.assign(weeklyForm, emptyWeeklyForm());
  weeklyFormError.value = "";
  showWeeklyModal.value = true;
}

function openWeeklyEdit(weekly: WeeklyChallenge): void {
  editingWeekly.value = weekly.id;
  const criteria = weekly.criteria ?? {};
  Object.assign(weeklyForm, {
    week_start: weekly.week_start,
    week_end: weekly.week_end,
    title: weekly.title,
    description: weekly.description,
    criteria_type: criteria.type || "play_games",
    criteria_mode: criteria.mode || "any",
    criteria_count: criteria.count || 3,
    criteria_value: criteria.value || 18,
    reward_xp: weekly.reward_xp,
    reward_coins: weekly.reward_coins,
  });
  weeklyFormError.value = "";
  showWeeklyModal.value = true;
}

async function saveWeekly(): Promise<void> {
  weeklyFormError.value = "";
  const data = {
    week_start: weeklyForm.week_start,
    week_end: weeklyForm.week_end,
    title: weeklyForm.title,
    description: weeklyForm.description,
    criteria: buildWeeklyCriteria(),
    reward_xp: weeklyForm.reward_xp,
    reward_coins: weeklyForm.reward_coins,
  };
  try {
    if (editingWeekly.value) {
      await axios.put(`/api/admin/weekly-challenges/${editingWeekly.value}`, data);
    } else {
      await axios.post("/api/admin/weekly-challenges", data);
    }
    showWeeklyModal.value = false;
    const response = await axios.get<WeeklyChallenge[]>("/api/admin/weekly-challenges");
    weeklyChallenges.value = response.data;
  } catch (error) {
    weeklyFormError.value = isAxiosError<{ error?: string; message?: string }>(error)
      ? (error.response?.data?.error ?? error.response?.data?.message ?? "Error")
      : "Error";
  }
}

async function deleteWeekly(weekly: WeeklyChallenge): Promise<void> {
  if (!confirm(`Delete "${weekly.title}"?`)) {
    return;
  }
  await axios.delete(`/api/admin/weekly-challenges/${weekly.id}`);
  weeklyChallenges.value = weeklyChallenges.value.filter((entry) => entry.id !== weekly.id);
}

async function generateWeeklyRange(): Promise<void> {
  weeklyGenerating.value = true;
  weeklyGenResult.value = "";
  weeklyGenError.value = "";
  try {
    const response = await axios.post<{ message: string }>("/api/admin/weekly-challenges/generate", weeklyGenForm);
    weeklyGenResult.value = response.data.message;
    const reloadResponse = await axios.get<WeeklyChallenge[]>("/api/admin/weekly-challenges");
    weeklyChallenges.value = reloadResponse.data;
  } catch (error) {
    weeklyGenError.value = isAxiosError<{ message?: string }>(error)
      ? (error.response?.data?.message ?? "Error generating weekly challenges")
      : "Error generating weekly challenges";
  }
  weeklyGenerating.value = false;
}

onMounted(async () => {
  await Promise.all([load(), fetchAddons()]);
});
</script>

<style scoped>
.tab-bar { display: flex; gap: 4px; margin-bottom: 20px; }
.tab-btn { background: rgba(138, 106, 46, 0.1); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-secondary); padding: 6px 18px; border-radius: 6px; cursor: pointer; font-family: 'Cinzel', serif; font-size: 0.85rem; transition: all 0.2s; }
.tab-btn.active { background: rgba(212, 168, 67, 0.2); border-color: var(--accent-gold); color: var(--accent-gold); }
.tab-btn:hover { color: var(--accent-gold); }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.date-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; }
.manual-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); }
.addon-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(138, 58, 138, 0.2); color: #c080d0; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 550px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group.full { grid-column: 1 / -1; }
.form-group { margin-bottom: 0; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.page-header-actions { display: flex; gap: 8px; }
.btn-secondary { background: rgba(138, 106, 46, 0.15); border: 1px solid rgba(138, 106, 46, 0.4); color: var(--accent-gold); padding: 6px 14px; border-radius: 6px; cursor: pointer; font-family: 'Cinzel', serif; font-size: 0.85rem; }
.gen-desc { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 14px; }
.gen-result { font-size: 0.9rem; color: #6abf50; margin: 10px 0; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
