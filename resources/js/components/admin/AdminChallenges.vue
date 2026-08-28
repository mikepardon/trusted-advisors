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
    <table class="admin-table challenge-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Name</th>
          <th class="col-plays">Plays</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in challenges" :key="c.id">
          <td>{{ formatDate(c.date) }}</td>
          <td>{{ c.title }}</td>
          <td class="col-plays">{{ c.entries_count ?? 0 }}</td>
          <td class="col-actions">
            <button class="btn-sm" @click="openPreview(c)">Preview</button>
            <button class="btn-sm" @click="openEdit(c)">Edit</button>
            <button class="btn-sm btn-danger" @click="deleteChallenge(c)">Del</button>
          </td>
        </tr>
        <tr v-if="challenges.length === 0">
          <td colspan="4" class="empty">No challenges yet.</td>
        </tr>
      </tbody>
    </table>

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
              <label>Goal Type</label>
              <select v-model="form.goal_type">
                <option value="stat_threshold">Single stat target</option>
                <option value="stat_threshold_all">All stats target</option>
                <option value="no_stat_below">No stat below</option>
              </select>
            </div>
            <div class="form-group">
              <label>Starting Stats (all)</label>
              <input v-model.number="form.start_all" type="number" min="0" max="20" />
            </div>
            <template v-if="form.goal_type === 'stat_threshold'">
              <div class="form-group">
                <label>Target Stat</label>
                <select v-model="form.goal_stat">
                  <option v-for="stat in STATS" :key="stat" :value="stat">{{ statLabel(stat) }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Target Value (reach to win)</label>
                <input v-model.number="form.goal_value" type="number" min="1" max="20" />
              </div>
            </template>
            <div v-else-if="form.goal_type === 'no_stat_below'" class="form-group">
              <label>Floor (keep every stat at or above)</label>
              <input v-model.number="form.goal_value" type="number" min="1" max="20" />
            </div>
            <div v-else-if="form.goal_type === 'stat_threshold_all'" class="form-group full">
              <label>Per-Stat Targets (leave 0 to ignore a stat)</label>
              <div class="targets-grid">
                <div v-for="stat in STATS" :key="stat" class="target-cell">
                  <span class="target-label">{{ statLabel(stat) }}</span>
                  <input v-model.number="form.goal_targets[stat]" type="number" min="0" max="20" />
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Rounds Cap (safety limit)</label>
              <input v-model.number="form.rounds" type="number" min="1" max="120" />
            </div>
            <div class="form-group">
              <label>Assigned Advisor</label>
              <select v-model="form.seed_character_id">
                <option :value="null">Random (any)</option>
                <option v-for="ch in characters" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Reward XP</label>
              <input v-model.number="form.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Reward Coins</label>
              <input v-model.number="form.reward_coins" type="number" min="0" max="100000" />
            </div>
            <div class="form-group full">
              <label>Seed Loadout (up to 3 items)</label>
              <div class="loadout-picker">
                <label
                  v-for="it in items"
                  :key="it.id"
                  class="loadout-option"
                  :class="{ selected: form.seed_loadout.includes(it.id) }"
                >
                  <input
                    type="checkbox"
                    :value="it.id"
                    :checked="form.seed_loadout.includes(it.id)"
                    :disabled="!form.seed_loadout.includes(it.id) && form.seed_loadout.length >= 3"
                    @change="toggleLoadoutItem(it.id)"
                  />
                  {{ it.name }}
                </label>
              </div>
            </div>
            <div class="form-group full">
              <label>Addon</label>
              <select v-model="form.addon_id">
                <option :value="null">Base Game</option>
                <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </div>
          </div>

          <!-- House Rules -->
          <div class="form-section-title">House Rules</div>
          <div class="house-rules-grid">
            <div class="rule-item">
              <label class="checkbox-label"><input v-model="form.house_rules.no_negative_effects" type="checkbox" /> No Negative Effects</label>
              <span class="info-icon" title="Failed cards and events never apply their negative stat changes — only the positive outcomes land. A very forgiving reign.">&#9432;</span>
            </div>
            <div class="rule-item">
              <label class="checkbox-label"><input v-model="form.house_rules.double_positive_effects" type="checkbox" /> Double Positive Effects</label>
              <span class="info-icon" title="Every positive stat gain is doubled (e.g. +2 Wealth becomes +4). Makes targets easier to hit.">&#9432;</span>
            </div>
            <div class="rule-item">
              <label class="checkbox-label"><input v-model="form.house_rules.hardcore_mode" type="checkbox" /> Hardcore Mode</label>
              <span class="info-icon" title="The reign ends the moment any stat falls to 3 or below, instead of the usual 0. Much less room for error.">&#9432;</span>
            </div>
            <div class="rule-item">
              <label class="checkbox-label"><input v-model="form.house_rules.random_starting_stats" type="checkbox" /> Random Starting Stats</label>
              <span class="info-icon" title="Each of the six stats starts at a random value (1–15) instead of the set starting values. Seeded by the challenge, so it's the same for every player. Overrides the per-stat starting values above.">&#9432;</span>
            </div>
            <div class="rule-item">
              <label class="checkbox-label"><input v-model="form.house_rules.draw_curse_per_round" type="checkbox" /> Draw Curse Each Round</label>
              <span class="info-icon" title="A fresh curse is drawn every month, on top of any curse already on the land — steady, mounting pressure.">&#9432;</span>
            </div>
          </div>

          <!-- Content Pools -->
          <div class="form-section-title">Content Pools</div>
          <div v-for="pool in POOLS" :key="pool.type" class="form-group">
            <label>
              {{ pool.label }}
              <span v-if="poolCount(pool.key) > 0" class="pool-chip">{{ poolCount(pool.key) }} selected</span>
              <span v-else class="pool-chip pool-all">All included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool(pool.type)">{{ showPool[pool.type] ? 'Hide' : 'Select' }}</button>
              <button v-if="poolCount(pool.key) > 0" type="button" class="btn-sm" @click="form[pool.key] = undefined">Clear</button>
              <button type="button" class="btn-sm" @click="selectAllPool(pool.type)">All</button>
            </div>
            <div v-if="showPool[pool.type]" class="pool-list">
              <input v-model="poolSearch[pool.type]" placeholder="Search..." class="pool-search" />
              <div class="pool-tile-grid">
                <div
                  v-for="entry in filteredPool(pool.type)"
                  :key="entry.id"
                  :class="['pool-tile', { 'pool-tile-selected': isInPool(pool.key, entry.id) }]"
                  @click="togglePoolItem(pool.key, entry.id)"
                >
                  {{ entry.name }}
                </div>
                <div v-if="filteredPool(pool.type).length === 0" class="pool-empty">No matches.</div>
              </div>
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

    <!-- In-game preview -->
    <AdminPreviewModal
      :visible="previewChallenge !== undefined"
      :title="previewChallenge?.title"
      @close="previewChallenge = undefined"
    >
      <ChallengePreviewCard
        v-if="previewChallenge"
        :challenge="previewChallenge"
        :characters="characters"
        :items="items"
      />
    </AdminPreviewModal>
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
import { computed, onMounted, reactive, ref } from "vue";
import axios, { isAxiosError } from "axios";
import AdminPreviewModal from "./AdminPreviewModal.vue";
import ChallengePreviewCard from "./ChallengePreviewCard.vue";

type Stat = "wealth" | "influence" | "security" | "religion" | "food" | "happiness";

const STATS: Stat[] = ["wealth", "influence", "security", "religion", "food", "happiness"];

const STAT_LABELS: Record<Stat, string> = {
  wealth: "Wealth",
  influence: "Influence",
  security: "Security",
  religion: "Religion",
  food: "Food",
  happiness: "Happiness",
};

function statLabel(stat: Stat): string {
  return STAT_LABELS[stat];
}

type PoolType = "cards" | "items" | "events" | "curses";
type PoolKey = "card_pool" | "item_pool" | "event_pool" | "curse_pool";

interface PoolDescriptor {
  type: PoolType;
  key: PoolKey;
  label: string;
}

const POOLS: PoolDescriptor[] = [
  { type: "cards", key: "card_pool", label: "Cards" },
  { type: "items", key: "item_pool", label: "Items" },
  { type: "events", key: "event_pool", label: "Events" },
  { type: "curses", key: "curse_pool", label: "Curses" },
];

const poolTypeToKey: Record<PoolType, PoolKey> = {
  cards: "card_pool",
  items: "item_pool",
  events: "event_pool",
  curses: "curse_pool",
};

interface HouseRules {
  no_negative_effects: boolean;
  double_positive_effects: boolean;
  hardcore_mode: boolean;
  random_starting_stats: boolean;
  draw_curse_per_round: boolean;
}

/**
A named entry for a pool tile. Cards/events expose `title`, items/curses expose `name`.
*/
interface PoolEntry {
  id: number;
  name: string;
}

interface RawPoolEntry {
  id: number;
  name?: string;
  title?: string;
}

interface DailyGoal {
  type: string;
  stat?: string;
  value?: number;
  targets?: Record<string, number>;
}

interface Criteria {
  // Endless daily shape.
  mode?: string;
  rounds?: number;
  start?: { all?: number };
  goal?: DailyGoal;
  seed_character_id?: number;
  seed_loadout?: number[];
  house_rules?: Partial<HouseRules>;
  card_pool?: number[];
  item_pool?: number[];
  event_pool?: number[];
  curse_pool?: number[];
  reward_coins?: number;
  // Weekly (and legacy) flat shape.
  type?: string;
  stat?: string;
  value?: number;
  character_id?: number;
  count?: number;
}

interface Character {
  id: number;
  name: string;
}

interface Item {
  id: number;
  name: string;
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
  entries_count?: number;
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
  goal_type: string;
  goal_stat: Stat;
  goal_value: number;
  goal_targets: Record<Stat, number>;
  start_all: number;
  rounds: number;
  seed_character_id: number | undefined;
  seed_loadout: number[];
  reward_xp: number;
  reward_coins: number;
  addon_id: number | undefined;
  house_rules: HouseRules;
  card_pool: number[] | undefined;
  item_pool: number[] | undefined;
  event_pool: number[] | undefined;
  curse_pool: number[] | undefined;
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

function emptyHouseRules(): HouseRules {
  return {
    no_negative_effects: false,
    double_positive_effects: false,
    hardcore_mode: false,
    random_starting_stats: false,
    draw_curse_per_round: false,
  };
}

function emptyTargets(): Record<Stat, number> {
  return { wealth: 0, influence: 0, security: 0, religion: 0, food: 0, happiness: 0 };
}

function emptyForm(): ChallengeForm {
  return {
    date: "", title: "", description: "",
    goal_type: "stat_threshold",
    goal_stat: "wealth", goal_value: 14,
    goal_targets: emptyTargets(),
    start_all: 8, rounds: 120,
    seed_character_id: undefined, seed_loadout: [],
    reward_xp: 125, reward_coins: 0, addon_id: undefined,
    house_rules: emptyHouseRules(),
    card_pool: undefined, item_pool: undefined, event_pool: undefined, curse_pool: undefined,
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
const characters = ref<Character[]>([]);
const items = ref<Item[]>([]);
const cardPool = ref<PoolEntry[]>([]);
const itemPool = computed<PoolEntry[]>(() => items.value.map((it) => ({ id: it.id, name: it.name })));
const eventPool = ref<PoolEntry[]>([]);
const cursePool = ref<PoolEntry[]>([]);
const showPool = reactive<Record<PoolType, boolean>>({ cards: false, items: false, events: false, curses: false });
const poolSearch = reactive<Record<PoolType, string>>({ cards: "", items: "", events: "", curses: "" });
const showModal = ref(false);
const editing = ref<number | undefined>(undefined);
const formError = ref("");
const form = reactive<ChallengeForm>(emptyForm());
const previewChallenge = ref<Challenge | undefined>(undefined);
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

// The API serialises the date cast as an ISO string; show just the calendar date.
function formatDate(value: string): string {
  return value.slice(0, 10);
}

async function fetchAddons(): Promise<void> {
  try {
    const response = await axios.get<Addon[]>("/api/admin/addons");
    addons.value = response.data;
  } catch { /* ignore */ }
}

async function fetchCharacters(): Promise<void> {
  try {
    const response = await axios.get<Character[]>("/api/admin/characters");
    characters.value = response.data;
  } catch { /* ignore */ }
}

async function fetchItems(): Promise<void> {
  try {
    const response = await axios.get<Item[]>("/api/admin/items");
    items.value = response.data;
  } catch { /* ignore */ }
}

/**
Normalise a pool endpoint response (either a bare array or a `{ data }` envelope).
*/
function normalisePool(payload: RawPoolEntry[] | { data: RawPoolEntry[] }): PoolEntry[] {
  const rows = Array.isArray(payload) ? payload : payload.data;
  return rows.map((row) => ({ id: row.id, name: row.title ?? row.name ?? `#${row.id}` }));
}

async function fetchPoolData(): Promise<void> {
  try {
    const [cards, events, curses] = await Promise.all([
      axios.get<RawPoolEntry[] | { data: RawPoolEntry[] }>("/api/admin/cards"),
      axios.get<RawPoolEntry[] | { data: RawPoolEntry[] }>("/api/admin/events"),
      axios.get<RawPoolEntry[] | { data: RawPoolEntry[] }>("/api/admin/curses"),
    ]);
    cardPool.value = normalisePool(cards.data);
    eventPool.value = normalisePool(events.data);
    cursePool.value = normalisePool(curses.data);
  } catch { /* ignore */ }
}

function poolEntries(type: PoolType): PoolEntry[] {
  return type === "cards" ? cardPool.value
    : type === "items" ? itemPool.value
    : type === "events" ? eventPool.value
    : cursePool.value;
}

function filteredPool(type: PoolType): PoolEntry[] {
  const query = poolSearch[type].toLowerCase();
  const entries = poolEntries(type);
  return query ? entries.filter((entry) => entry.name.toLowerCase().includes(query)) : entries;
}

function poolCount(key: PoolKey): number {
  return form[key]?.length ?? 0;
}

function togglePool(type: PoolType): void {
  const current = showPool[type];
  showPool[type] = !current;
}

function isInPool(key: PoolKey, id: number): boolean {
  const pool = form[key];
  return pool !== undefined && pool.includes(id);
}

function togglePoolItem(key: PoolKey, id: number): void {
  const pool = form[key];
  if (!pool) {
    form[key] = [id];
    return;
  }
  if (pool.includes(id)) {
    const next = pool.filter((existing) => existing !== id);
    form[key] = next.length === 0 ? undefined : next;
    return;
  }
  form[key] = [...pool, id];
}

function selectAllPool(type: PoolType): void {
  const key = poolTypeToKey[type];
  const allIds = poolEntries(type).map((entry) => entry.id);
  const pool = form[key];
  form[key] = pool && pool.length === allIds.length ? undefined : [...allIds];
}

function toggleLoadoutItem(itemId: number): void {
  const index = form.seed_loadout.indexOf(itemId);
  if (index === -1) {
    if (form.seed_loadout.length >= 3) {
      return;
    }
    form.seed_loadout.push(itemId);
  } else {
    form.seed_loadout.splice(index, 1);
  }
}

function buildGoal(): DailyGoal {
  if (form.goal_type === "stat_threshold_all") {
    const targets: Record<string, number> = {};
    for (const stat of STATS) {
      const value = form.goal_targets[stat];
      if (value > 0) {
        targets[stat] = value;
      }
    }
    return { type: "stat_threshold_all", targets };
  }
  if (form.goal_type === "no_stat_below") {
    return { type: "no_stat_below", value: form.goal_value };
  }
  return { type: "stat_threshold", stat: form.goal_stat, value: form.goal_value };
}

function buildCriteria(): Criteria {
  const criteria: Criteria = {
    mode: "cooperative",
    rounds: form.rounds,
    start: { all: form.start_all },
    goal: buildGoal(),
    seed_character_id: form.seed_character_id,
    seed_loadout: [...form.seed_loadout],
    reward_coins: form.reward_coins,
  };

  const houseRules: Partial<HouseRules> = {};
  if (form.house_rules.no_negative_effects) {
    houseRules.no_negative_effects = true;
  }
  if (form.house_rules.double_positive_effects) {
    houseRules.double_positive_effects = true;
  }
  if (form.house_rules.hardcore_mode) {
    houseRules.hardcore_mode = true;
  }
  if (form.house_rules.random_starting_stats) {
    houseRules.random_starting_stats = true;
  }
  if (form.house_rules.draw_curse_per_round) {
    houseRules.draw_curse_per_round = true;
  }
  if (Object.keys(houseRules).length > 0) {
    criteria.house_rules = houseRules;
  }

  if (form.card_pool && form.card_pool.length > 0) {
    criteria.card_pool = [...form.card_pool];
  }
  if (form.item_pool && form.item_pool.length > 0) {
    criteria.item_pool = [...form.item_pool];
  }
  if (form.event_pool && form.event_pool.length > 0) {
    criteria.event_pool = [...form.event_pool];
  }
  if (form.curse_pool && form.curse_pool.length > 0) {
    criteria.curse_pool = [...form.curse_pool];
  }

  return criteria;
}

function openPreview(challenge: Challenge): void {
  previewChallenge.value = challenge;
}

function resetPoolUi(): void {
  Object.assign(showPool, { cards: false, items: false, events: false, curses: false });
  Object.assign(poolSearch, { cards: "", items: "", events: "", curses: "" });
}

function isStat(value: string): value is Stat {
  return (STATS as string[]).includes(value);
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, emptyForm());
  resetPoolUi();
  formError.value = "";
  showModal.value = true;
}

function openEdit(challenge: Challenge): void {
  editing.value = challenge.id;
  const criteria = challenge.criteria ?? {};
  const empty = emptyForm();
  const goal = criteria.goal;
  const goalType = goal?.type ?? "stat_threshold";
  const goalStat = goal?.stat !== undefined && isStat(goal.stat) ? goal.stat : empty.goal_stat;

  // Hydrate the per-stat target grid from goal.targets (older challenges lack it).
  const targets = emptyTargets();
  const rawTargets = goal?.targets;
  if (rawTargets) {
    for (const stat of STATS) {
      const value = rawTargets[stat];
      if (typeof value === "number") {
        targets[stat] = value;
      }
    }
  }

  const houseRules = emptyHouseRules();
  const rawHouseRules = criteria.house_rules;
  if (rawHouseRules) {
    houseRules.no_negative_effects = rawHouseRules.no_negative_effects === true;
    houseRules.double_positive_effects = rawHouseRules.double_positive_effects === true;
    houseRules.hardcore_mode = rawHouseRules.hardcore_mode === true;
    houseRules.random_starting_stats = rawHouseRules.random_starting_stats === true;
    houseRules.draw_curse_per_round = rawHouseRules.draw_curse_per_round === true;
  }

  Object.assign(form, {
    date: formatDate(challenge.date),
    title: challenge.title,
    description: challenge.description,
    goal_type: goalType,
    goal_stat: goalStat,
    goal_value: goal?.value ?? empty.goal_value,
    goal_targets: targets,
    start_all: criteria.start?.all ?? empty.start_all,
    rounds: criteria.rounds ?? empty.rounds,
    seed_character_id: criteria.seed_character_id ?? undefined,
    seed_loadout: [...(criteria.seed_loadout ?? [])],
    reward_xp: challenge.reward_xp,
    reward_coins: criteria.reward_coins ?? empty.reward_coins,
    addon_id: challenge.addon_id ?? undefined,
    house_rules: houseRules,
    card_pool: criteria.card_pool && criteria.card_pool.length > 0 ? [...criteria.card_pool] : undefined,
    item_pool: criteria.item_pool && criteria.item_pool.length > 0 ? [...criteria.item_pool] : undefined,
    event_pool: criteria.event_pool && criteria.event_pool.length > 0 ? [...criteria.event_pool] : undefined,
    curse_pool: criteria.curse_pool && criteria.curse_pool.length > 0 ? [...criteria.curse_pool] : undefined,
  });
  resetPoolUi();
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
  await Promise.all([load(), fetchAddons(), fetchCharacters(), fetchItems(), fetchPoolData()]);
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
.loadout-picker { display: flex; flex-wrap: wrap; gap: 6px; max-height: 160px; overflow-y: auto; padding: 8px; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); border-radius: 4px; }
.loadout-option { display: flex; align-items: center; gap: 5px; font-size: 0.78rem; color: var(--text-secondary); background: rgba(138, 106, 46, 0.08); border: 1px solid rgba(138, 106, 46, 0.25); border-radius: 4px; padding: 3px 8px; cursor: pointer; }
.loadout-option.selected { color: var(--accent-gold); border-color: var(--accent-gold); background: rgba(212, 168, 67, 0.15); }
.loadout-option input { width: auto; }
.gen-desc { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 14px; }
.challenge-table .col-plays { text-align: center; width: 80px; }
.challenge-table .col-actions { width: 1%; white-space: nowrap; text-align: right; }
.challenge-table .col-actions .btn-sm { margin-left: 4px; }
.challenge-table .empty { text-align: center; font-style: italic; }
.house-rules-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
.rule-item { display: flex; align-items: center; gap: 6px; }
.info-icon { cursor: help; color: var(--text-secondary); font-size: 1rem; line-height: 1; flex-shrink: 0; }
.info-icon:hover { color: var(--accent-gold); }
@media (max-width: 640px) { .house-rules-grid { grid-template-columns: 1fr; } }
.gen-result { font-size: 0.9rem; color: #6abf50; margin: 10px 0; }

/* House rules & content pools */
.form-section-title { font-family: 'Cinzel', serif; font-size: 0.9rem; color: var(--accent-gold); margin: 16px 0 8px; padding-top: 12px; border-top: 1px solid rgba(138, 106, 46, 0.15); }
.house-rules-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.82rem; color: var(--text-secondary); }
.checkbox-label input[type="checkbox"] { width: auto; margin: 0; }
.targets-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 8px; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); border-radius: 4px; }
.target-cell { display: flex; flex-direction: column; gap: 2px; }
.target-label { font-size: 0.72rem; color: var(--text-secondary); }
.pool-chip { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; margin-left: 6px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); }
.pool-chip.pool-all { background: rgba(74, 138, 58, 0.15); color: #6abf50; }
.pool-controls { display: flex; gap: 6px; margin-bottom: 6px; }
.pool-list { background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.15); border-radius: 6px; padding: 8px; margin-top: 4px; }
.pool-search { margin-bottom: 8px; }
.pool-tile-grid { display: flex; flex-wrap: wrap; gap: 6px; max-height: 200px; overflow-y: auto; padding: 2px; }
.pool-tile { padding: 4px 10px; border-radius: 4px; font-size: 0.76rem; color: var(--text-secondary); background: rgba(138, 106, 46, 0.08); border: 1px solid rgba(138, 106, 46, 0.25); cursor: pointer; user-select: none; }
.pool-tile:hover { border-color: rgba(212, 168, 67, 0.5); }
.pool-tile-selected { border-color: var(--accent-gold); color: var(--accent-gold); background: rgba(212, 168, 67, 0.15); }
.pool-empty { font-size: 0.78rem; color: var(--text-secondary); font-style: italic; padding: 4px; }

@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .house-rules-grid { grid-template-columns: 1fr; } .targets-grid { grid-template-columns: 1fr 1fr; } }
</style>
