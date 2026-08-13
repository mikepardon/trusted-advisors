<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Rotating Events</h2>
      <button class="btn-primary" @click="openCreate">New Event</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="e in events" :key="e.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <span v-if="e.theme_color" class="theme-dot" :style="{ background: e.theme_color }"></span>
            <strong>{{ e.name }}</strong>
            <span :class="['status-badge', isActive(e) ? 'active' : 'inactive']">
              {{ isActive(e) ? 'Active' : 'Inactive' }}
            </span>
            <span v-if="e.visibility && e.visibility !== 'all'" :class="['status-badge', e.visibility === 'premium' ? 'badge-premium' : 'badge-admin']">{{ e.visibility }}</span>
            <span class="date-badge">{{ formatDateRange(e.starts_at, e.ends_at) }}</span>
          </div>
          <div class="list-sub">
            {{ truncate(e.description, 80) }}
            &mdash; {{ e.game_type }} / {{ e.game_mode }}
            <span v-if="e.reward_coins"> &mdash; {{ e.reward_coins }} coins</span>
            <span v-if="e.total_rounds"> &mdash; {{ e.total_rounds }} rounds</span>
            <span v-if="e.card_pool"> &mdash; {{ e.card_pool.length }} cards</span>
            <span v-if="e.curse_pool"> &mdash; {{ e.curse_pool.length }} curses</span>
            <span v-if="e.creator"> &mdash; by {{ e.creator.name }}</span>
          </div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(e)">Edit</button>
          <button class="btn-sm btn-danger" @click="remove(e)">Delete</button>
        </div>
      </div>
      <div v-if="events.length === 0" class="empty">No rotating events yet.</div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Event' : 'New Event' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required placeholder="Event name" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" required placeholder="Event description"></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Game Type</label>
              <select v-model="form.game_type">
                <option value="cooperative">Cooperative</option>
                <option value="duel">Duel</option>
              </select>
            </div>
            <div class="form-group">
              <label>Game Mode</label>
              <select v-model="form.game_mode">
                <option value="single">Single</option>
                <option value="pass_and_play">Pass & Play</option>
                <option value="online">Online</option>
              </select>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Reward Coins</label>
              <input v-model.number="form.reward_coins" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Max Attempts</label>
              <input v-model.number="form.max_attempts" type="number" min="1" placeholder="Unlimited" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Starting Stats Override</label>
              <input v-model.number="overrideStartingStats" type="number" min="1" max="20" placeholder="Default (8)" />
            </div>
            <div class="form-group">
              <label>XP Multiplier</label>
              <input v-model.number="overrideXpMultiplier" type="number" step="0.5" min="0.5" placeholder="Default (1)" />
            </div>
          </div>
          <div class="form-group">
            <label>Image URL</label>
            <input v-model="form.image_url" placeholder="Optional" />
          </div>

          <!-- Bot Difficulty (for single-player duel events) -->
          <div v-if="form.game_mode === 'single' && form.game_type === 'duel'" class="form-group">
            <label>Bot Difficulty</label>
            <select v-model="overrideBotDifficulty">
              <option value="easy">Easy</option>
              <option value="medium">Medium</option>
              <option value="hard">Hard</option>
            </select>
          </div>

          <!-- House Rules -->
          <div class="form-section-title">House Rules</div>
          <div class="form-group house-rules-grid">
            <label class="checkbox-label"><input v-model="overrideHouseRules.no_negative_effects" type="checkbox" /> No Negative Effects</label>
            <label class="checkbox-label"><input v-model="overrideHouseRules.double_positive_effects" type="checkbox" /> Double Positive Effects</label>
            <label class="checkbox-label"><input v-model="overrideHouseRules.random_starting_stats" type="checkbox" /> Random Starting Stats</label>
            <label class="checkbox-label"><input v-model="overrideHouseRules.hardcore_mode" type="checkbox" /> Hardcore (lose at stat &le; 3)</label>
            <label class="checkbox-label"><input v-model="overrideHouseRules.draw_curse_per_round" type="checkbox" /> Draw Curse Each Round</label>
          </div>

          <!-- Game Settings -->
          <div class="form-section-title">Game Settings</div>
          <div class="form-grid">
            <div class="form-group">
              <label>Total Rounds</label>
              <select v-model="form.total_rounds">
                <option :value="undefined">Player chooses</option>
                <option :value="12">12 (1 Year)</option>
                <option :value="24">24 (2 Years)</option>
                <option :value="36">36 (3 Years)</option>
                <option :value="48">48 (4 Years)</option>
                <option :value="60">60 (5 Years)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Affects ELO</label>
              <label class="checkbox-label">
                <input v-model="form.affects_elo" type="checkbox" /> Yes, affects ELO rating
              </label>
            </div>
          </div>

          <!-- XP Override -->
          <div class="form-section-title">XP Override</div>
          <div class="form-group">
            <label class="checkbox-label">
              <input v-model="useCustomXp" type="checkbox" /> Use custom XP config
            </label>
          </div>
          <div v-if="useCustomXp" class="form-grid form-grid-3">
            <div class="form-group">
              <label>Base XP</label>
              <input v-model.number="xpBase" type="number" min="0" placeholder="50" />
            </div>
            <div class="form-group">
              <label>Win Bonus</label>
              <input v-model.number="xpWinBonus" type="number" min="0" placeholder="100" />
            </div>
            <div class="form-group">
              <label>Online Multiplier</label>
              <input v-model.number="xpOnlineMultiplier" type="number" step="0.1" min="0.5" placeholder="1.5" />
            </div>
          </div>

          <!-- Theme -->
          <div class="form-section-title">Theme</div>
          <div class="form-group">
            <label>Theme Color</label>
            <div class="color-picker-row">
              <input v-model="form.theme_color" type="color" class="color-input" />
              <input v-model="form.theme_color" placeholder="#8a3ab9" class="color-text" />
              <button v-if="form.theme_color" type="button" class="btn-sm" @click="form.theme_color = ''">Clear</button>
            </div>
          </div>

          <!-- Content Pools -->
          <div class="form-section-title">Content Pools</div>

          <!-- Cards Pool -->
          <div class="form-group">
            <label>
              Cards
              <span v-if="form.card_pool && form.card_pool.length > 0" class="pool-chip">{{ form.card_pool.length }} selected</span>
              <span v-else class="pool-chip pool-all">All cards included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('cards')">{{ showPool.cards ? 'Hide' : 'Select Cards' }}</button>
              <button v-if="form.card_pool && form.card_pool.length > 0" type="button" class="btn-sm" @click="form.card_pool = undefined">Clear</button>
              <button type="button" class="btn-sm" @click="selectAllPool('cards')">All</button>
            </div>
            <div v-if="showPool.cards" class="pool-list">
              <input v-model="poolSearch.cards" placeholder="Search cards..." class="pool-search" />
              <div class="card-tile-grid">
                <div
                  v-for="item in filteredCards"
                  :key="item.id"
                  :class="['card-tile', { 'card-tile-selected': isInPool('card_pool', item.id) }]"
                  @click="togglePoolItem('card_pool', item.id)"
                >
                  <div class="card-tile-header">
                    <span class="card-tile-title">{{ item.title }}</span>
                    <span class="card-tile-diff">{{ item.difficulty }}</span>
                  </div>
                  <div class="card-tile-effects">
                    <span v-for="(val, stat) in filterStatEffects(item.positive_effects)" :key="'p-'+stat" class="card-tile-chip chip-pos">{{ shortStat(stat) }} {{ val > 0 ? '+' : '' }}{{ val }}</span>
                    <span v-if="item.positive_effects?.draw_item" class="card-tile-chip chip-special">Draw Item</span>
                    <span v-if="item.positive_effects?.recover_die" class="card-tile-chip chip-special">Recover Die</span>
                  </div>
                  <div class="card-tile-effects">
                    <span v-for="(val, stat) in filterStatEffects(item.negative_effects)" :key="'n-'+stat" class="card-tile-chip chip-neg">{{ shortStat(stat) }} {{ val > 0 ? '+' : '' }}{{ val }}</span>
                    <span v-if="item.negative_effects?.lose_die" class="card-tile-chip chip-special-neg">Lose Die</span>
                    <span v-if="item.negative_effects?.discard_item" class="card-tile-chip chip-special-neg">Lose Item</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Items Pool -->
          <div class="form-group">
            <label>
              Items
              <span v-if="form.item_pool && form.item_pool.length > 0" class="pool-chip">{{ form.item_pool.length }} selected</span>
              <span v-else class="pool-chip pool-all">All items included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('items')">{{ showPool.items ? 'Hide' : 'Select Items' }}</button>
              <button v-if="form.item_pool && form.item_pool.length > 0" type="button" class="btn-sm" @click="form.item_pool = undefined">Clear</button>
              <button type="button" class="btn-sm" @click="selectAllPool('items')">All</button>
            </div>
            <div v-if="showPool.items" class="pool-list">
              <input v-model="poolSearch.items" placeholder="Search items..." class="pool-search" />
              <div class="card-tile-grid">
                <div
                  v-for="item in filteredItems"
                  :key="item.id"
                  :class="['card-tile', { 'card-tile-selected': isInPool('item_pool', item.id) }]"
                  @click="togglePoolItem('item_pool', item.id)"
                >
                  <div class="card-tile-header">
                    <span class="card-tile-title">{{ item.name }}</span>
                    <span :class="['card-tile-diff', item.is_negative ? 'diff-neg' : 'diff-pos']">{{ item.is_negative ? 'Curse' : (item.is_consumable ? 'Use' : 'Passive') }}</span>
                  </div>
                  <div class="card-tile-effects">
                    <span class="card-tile-chip" :class="item.is_negative ? 'chip-neg' : 'chip-pos'">{{ itemEffectLabel(item) }}</span>
                    <span v-if="item.effect_type === 'active'" class="card-tile-chip chip-special">Active</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Events Pool -->
          <div class="form-group">
            <label>
              Events
              <span v-if="form.event_pool && form.event_pool.length > 0" class="pool-chip">{{ form.event_pool.length }} selected</span>
              <span v-else class="pool-chip pool-all">All events included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('events')">{{ showPool.events ? 'Hide' : 'Select Events' }}</button>
              <button v-if="form.event_pool && form.event_pool.length > 0" type="button" class="btn-sm" @click="form.event_pool = undefined">Clear</button>
              <button type="button" class="btn-sm" @click="selectAllPool('events')">All</button>
            </div>
            <div v-if="showPool.events" class="pool-list">
              <input v-model="poolSearch.events" placeholder="Search events..." class="pool-search" />
              <div class="card-tile-grid">
                <div
                  v-for="item in filteredEvents"
                  :key="item.id"
                  :class="['card-tile', { 'card-tile-selected': isInPool('event_pool', item.id) }]"
                  @click="togglePoolItem('event_pool', item.id)"
                >
                  <div class="card-tile-header">
                    <span class="card-tile-title">{{ item.title }}</span>
                  </div>
                  <div class="card-tile-effects">
                    <span class="card-tile-chip" :class="eventMechanicClass(item.mechanic)">{{ eventMechanicLabel(item.mechanic) }}</span>
                    <span v-for="(val, stat) in (item.stat_modifiers || {})" :key="stat" class="card-tile-chip" :class="val > 0 ? 'chip-pos' : 'chip-neg'">{{ shortStat(stat) }} {{ val > 0 ? '+' : '' }}{{ val }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Characters Pool -->
          <div class="form-group">
            <label>
              Characters
              <span v-if="form.character_pool && form.character_pool.length > 0" class="pool-chip">{{ form.character_pool.length }} selected</span>
              <span v-else class="pool-chip pool-all">All characters included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('characters')">{{ showPool.characters ? 'Hide' : 'Select Characters' }}</button>
              <button v-if="form.character_pool && form.character_pool.length > 0" type="button" class="btn-sm" @click="form.character_pool = undefined">Clear</button>
              <button type="button" class="btn-sm" @click="selectAllPool('characters')">All</button>
            </div>
            <div v-if="showPool.characters" class="pool-list">
              <input v-model="poolSearch.characters" placeholder="Search characters..." class="pool-search" />
              <div class="card-tile-grid char-tile-grid">
                <div
                  v-for="item in filteredCharacters"
                  :key="item.id"
                  :class="['card-tile char-tile', { 'card-tile-selected': isInPool('character_pool', item.id) }]"
                  @click="togglePoolItem('character_pool', item.id)"
                >
                  <img v-if="item.image_url" :src="item.image_url" class="char-tile-img" />
                  <div class="char-tile-info">
                    <span class="card-tile-title">{{ item.name }}</span>
                    <div class="card-tile-effects">
                      <span class="card-tile-chip chip-special">{{ item.wild_ability || 'No ability' }}</span>
                      <span class="card-tile-chip chip-pos">Wild: {{ item.wild_value }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Curses Pool -->
          <div class="form-group">
            <label>
              Curses
              <span v-if="form.curse_pool && form.curse_pool.length > 0" class="pool-chip">{{ form.curse_pool.length }} selected</span>
              <span v-else class="pool-chip pool-all">All curses included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('curses')">{{ showPool.curses ? 'Hide' : 'Select Curses' }}</button>
              <button v-if="form.curse_pool && form.curse_pool.length > 0" type="button" class="btn-sm" @click="form.curse_pool = undefined">Clear</button>
              <button type="button" class="btn-sm" @click="selectAllPool('curses')">All</button>
            </div>
            <div v-if="showPool.curses" class="pool-list">
              <input v-model="poolSearch.curses" placeholder="Search curses..." class="pool-search" />
              <div class="card-tile-grid">
                <div
                  v-for="item in filteredCurses"
                  :key="item.id"
                  :class="['card-tile', { 'card-tile-selected': isInPool('curse_pool', item.id) }]"
                  @click="togglePoolItem('curse_pool', item.id)"
                >
                  <div class="card-tile-header">
                    <span class="card-tile-title">{{ item.name }}</span>
                  </div>
                  <div class="card-tile-effects">
                    <span class="card-tile-chip chip-neg">{{ curseNegLabel(item) }}</span>
                    <span class="card-tile-chip chip-pos">{{ cursePosLabel(item) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Fixed Event -->
          <div class="form-section-title">Fixed Event</div>
          <div class="form-group">
            <label>Event for all rounds</label>
            <select v-model="form.fixed_event_id">
              <option :value="undefined">Rotate every 3 rounds (default)</option>
              <option v-for="ev in allEvents" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
            </select>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>Starts At</label>
              <input v-model="form.starts_at" type="datetime-local" required />
            </div>
            <div class="form-group">
              <label>Ends At</label>
              <input v-model="form.ends_at" type="datetime-local" required />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>
                <input v-model="form.is_active" type="checkbox" /> Active
              </label>
            </div>
            <div class="form-group">
              <label>Visibility</label>
              <select v-model="form.visibility">
                <option value="all">Everyone</option>
                <option value="premium">Premium Only</option>
                <option value="admin">Admin Only</option>
              </select>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn-secondary" @click="showModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../../stores/toast";

type PoolType = "cards" | "items" | "events" | "characters" | "curses";
type PoolKey = "card_pool" | "item_pool" | "event_pool" | "character_pool" | "curse_pool";

interface HouseRules {
  no_negative_effects: boolean;
  double_positive_effects: boolean;
  random_starting_stats: boolean;
  hardcore_mode: boolean;
  draw_curse_per_round: boolean;
}

interface EventModifiers {
  starting_stats?: number;
  xp_multiplier?: number;
  bot_difficulty?: string;
  house_rules?: Partial<HouseRules>;
}

interface XpConfig {
  base_xp?: number;
  win_bonus?: number;
  online_multiplier?: number;
}

interface Creator {
  name: string;
}

interface RotatingEvent {
  id: number;
  name: string;
  description: string;
  image_url?: string;
  game_type: string;
  game_mode: string;
  reward_coins: number;
  max_attempts?: number;
  starts_at?: string;
  ends_at?: string;
  is_active: boolean;
  visibility?: string;
  card_pool?: number[];
  item_pool?: number[];
  event_pool?: number[];
  character_pool?: number[];
  curse_pool?: number[];
  fixed_event_id?: number;
  total_rounds?: number;
  affects_elo?: boolean;
  theme_color?: string;
  modifiers?: EventModifiers;
  xp_config?: XpConfig;
  creator?: Creator;
}

interface EventForm {
  name: string;
  description: string;
  image_url: string;
  game_type: string;
  game_mode: string;
  reward_coins: number;
  max_attempts: number | undefined;
  starts_at: string;
  ends_at: string;
  is_active: boolean;
  visibility: string;
  card_pool: number[] | undefined;
  item_pool: number[] | undefined;
  event_pool: number[] | undefined;
  character_pool: number[] | undefined;
  curse_pool: number[] | undefined;
  fixed_event_id: number | undefined;
  total_rounds: number | undefined;
  affects_elo: boolean;
  theme_color: string;
}

interface CardEffects {
  draw_item?: number;
  recover_die?: number;
  lose_die?: number;
  discard_item?: number;
  [key: string]: number | undefined;
}

interface CardPoolEntry {
  id: number;
  title?: string;
  difficulty?: number;
  positive_effects?: CardEffects;
  negative_effects?: CardEffects;
}

interface ItemEffect {
  bonus_type?: string;
  bonus_value?: number;
  stat?: string;
}

interface ItemPoolEntry {
  id: number;
  name?: string;
  is_negative?: boolean;
  is_consumable?: boolean;
  effect_type?: string;
  effect?: ItemEffect;
}

interface EventPoolEntry {
  id: number;
  title?: string;
  mechanic?: string;
  stat_modifiers?: Record<string, number>;
}

interface CharacterPoolEntry {
  id: number;
  name?: string;
  image_url?: string;
  wild_ability?: string;
  wild_value?: number;
}

interface CurseEffect {
  type: string;
  stat?: string;
  value?: number;
  count?: number;
}

interface CursePoolEntry {
  id: number;
  name?: string;
  negative_effect?: CurseEffect;
  positive_effect?: CurseEffect;
}

const poolTypeToKey: Record<PoolType, PoolKey> = {
  cards: "card_pool",
  items: "item_pool",
  events: "event_pool",
  characters: "character_pool",
  curses: "curse_pool",
};

function defaultHouseRules(): HouseRules {
  return {
    no_negative_effects: false,
    double_positive_effects: false,
    random_starting_stats: false,
    hardcore_mode: false,
    draw_curse_per_round: false,
  };
}

function emptyForm(): EventForm {
  return {
    name: "",
    description: "",
    image_url: "",
    game_type: "cooperative",
    game_mode: "single",
    reward_coins: 0,
    max_attempts: undefined,
    starts_at: "",
    ends_at: "",
    is_active: true,
    visibility: "all",
    card_pool: undefined,
    item_pool: undefined,
    event_pool: undefined,
    character_pool: undefined,
    curse_pool: undefined,
    fixed_event_id: undefined,
    total_rounds: undefined,
    affects_elo: false,
    theme_color: "",
  };
}

const toast = useToast();

const events = ref<RotatingEvent[]>([]);
const showModal = ref(false);
const editing = ref<RotatingEvent | undefined>(undefined);
const saving = ref(false);
const form = reactive<EventForm>(emptyForm());
const overrideStartingStats = ref<number | undefined>(undefined);
const overrideXpMultiplier = ref<number | undefined>(undefined);
const overrideBotDifficulty = ref("medium");
const overrideHouseRules = reactive<HouseRules>(defaultHouseRules());
const useCustomXp = ref(false);
const xpBase = ref(50);
const xpWinBonus = ref(100);
const xpOnlineMultiplier = ref(1.5);
const allCards = ref<CardPoolEntry[]>([]);
const allItems = ref<ItemPoolEntry[]>([]);
const allEvents = ref<EventPoolEntry[]>([]);
const allCharacters = ref<CharacterPoolEntry[]>([]);
const allCurses = ref<CursePoolEntry[]>([]);
const showPool = reactive<Record<PoolType, boolean>>({ cards: false, items: false, events: false, characters: false, curses: false });
const poolSearch = reactive<Record<PoolType, string>>({ cards: "", items: "", events: "", characters: "", curses: "" });

const filteredCards = computed<CardPoolEntry[]>(() => {
  const query = poolSearch.cards.toLowerCase();
  return query ? allCards.value.filter((card) => (card.title || "").toLowerCase().includes(query)) : allCards.value;
});

const filteredItems = computed<ItemPoolEntry[]>(() => {
  const query = poolSearch.items.toLowerCase();
  return query ? allItems.value.filter((item) => (item.name || "").toLowerCase().includes(query)) : allItems.value;
});

const filteredEvents = computed<EventPoolEntry[]>(() => {
  const query = poolSearch.events.toLowerCase();
  return query ? allEvents.value.filter((event) => (event.title || "").toLowerCase().includes(query)) : allEvents.value;
});

const filteredCharacters = computed<CharacterPoolEntry[]>(() => {
  const query = poolSearch.characters.toLowerCase();
  return query ? allCharacters.value.filter((character) => (character.name || "").toLowerCase().includes(query)) : allCharacters.value;
});

const filteredCurses = computed<CursePoolEntry[]>(() => {
  const query = poolSearch.curses.toLowerCase();
  return query ? allCurses.value.filter((curse) => (curse.name || "").toLowerCase().includes(query)) : allCurses.value;
});

async function fetch(): Promise<void> {
  try {
    const response = await axios.get<RotatingEvent[]>("/api/admin/rotating-events");
    events.value = response.data;
  } catch {
    // ignore
  }
}

async function fetchPoolData(): Promise<void> {
  try {
    const [cards, items, poolEvents, characters, curses] = await Promise.all([
      axios.get<CardPoolEntry[] | { data: CardPoolEntry[] }>("/api/admin/cards"),
      axios.get<ItemPoolEntry[] | { data: ItemPoolEntry[] }>("/api/admin/items"),
      axios.get<EventPoolEntry[] | { data: EventPoolEntry[] }>("/api/admin/events"),
      axios.get<CharacterPoolEntry[] | { data: CharacterPoolEntry[] }>("/api/admin/characters"),
      axios.get<CursePoolEntry[] | { data: CursePoolEntry[] }>("/api/admin/curses"),
    ]);
    allCards.value = Array.isArray(cards.data) ? cards.data : cards.data.data;
    allItems.value = Array.isArray(items.data) ? items.data : items.data.data;
    allEvents.value = Array.isArray(poolEvents.data) ? poolEvents.data : poolEvents.data.data;
    allCharacters.value = Array.isArray(characters.data) ? characters.data : characters.data.data;
    allCurses.value = Array.isArray(curses.data) ? curses.data : curses.data.data;
  } catch {
    // ignore
  }
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
  } else if (pool.includes(id)) {
    const next = pool.filter((existing) => existing !== id);
    form[key] = next.length === 0 ? undefined : next;
  } else {
    form[key] = [...pool, id];
  }
}

function selectAllPool(type: PoolType): void {
  const allMap: Record<PoolType, { id: number }[]> = {
    cards: allCards.value,
    items: allItems.value,
    events: allEvents.value,
    characters: allCharacters.value,
    curses: allCurses.value,
  };
  const key = poolTypeToKey[type];
  const allIds = allMap[type].map((entry) => entry.id);
  const pool = form[key];
  form[key] = pool && pool.length === allIds.length ? undefined : [...allIds];
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, emptyForm());
  overrideStartingStats.value = undefined;
  overrideXpMultiplier.value = undefined;
  overrideBotDifficulty.value = "medium";
  Object.assign(overrideHouseRules, defaultHouseRules());
  useCustomXp.value = false;
  xpBase.value = 50;
  xpWinBonus.value = 100;
  xpOnlineMultiplier.value = 1.5;
  Object.assign(showPool, { cards: false, items: false, events: false, characters: false, curses: false });
  Object.assign(poolSearch, { cards: "", items: "", events: "", characters: "", curses: "" });
  showModal.value = true;
}

function openEdit(event: RotatingEvent): void {
  editing.value = event;
  Object.assign(form, {
    name: event.name,
    description: event.description,
    image_url: event.image_url || "",
    game_type: event.game_type,
    game_mode: event.game_mode,
    reward_coins: event.reward_coins,
    max_attempts: event.max_attempts || undefined,
    starts_at: event.starts_at ? event.starts_at.slice(0, 16) : "",
    ends_at: event.ends_at ? event.ends_at.slice(0, 16) : "",
    is_active: event.is_active,
    visibility: event.visibility || "all",
    card_pool: event.card_pool || undefined,
    item_pool: event.item_pool || undefined,
    event_pool: event.event_pool || undefined,
    character_pool: event.character_pool || undefined,
    curse_pool: event.curse_pool || undefined,
    fixed_event_id: event.fixed_event_id || undefined,
    total_rounds: event.total_rounds || undefined,
    affects_elo: event.affects_elo || false,
    theme_color: event.theme_color || "",
  });
  overrideStartingStats.value = event.modifiers?.starting_stats || undefined;
  overrideXpMultiplier.value = event.modifiers?.xp_multiplier || undefined;
  overrideBotDifficulty.value = event.modifiers?.bot_difficulty || "medium";
  const houseRules = event.modifiers?.house_rules || {};
  Object.assign(overrideHouseRules, {
    no_negative_effects: !!houseRules.no_negative_effects,
    double_positive_effects: !!houseRules.double_positive_effects,
    random_starting_stats: !!houseRules.random_starting_stats,
    hardcore_mode: !!houseRules.hardcore_mode,
    draw_curse_per_round: !!houseRules.draw_curse_per_round,
  });
  if (event.xp_config) {
    useCustomXp.value = true;
    xpBase.value = event.xp_config.base_xp ?? 50;
    xpWinBonus.value = event.xp_config.win_bonus ?? 100;
    xpOnlineMultiplier.value = event.xp_config.online_multiplier ?? 1.5;
  } else {
    useCustomXp.value = false;
    xpBase.value = 50;
    xpWinBonus.value = 100;
    xpOnlineMultiplier.value = 1.5;
  }
  Object.assign(showPool, { cards: false, items: false, events: false, characters: false, curses: false });
  Object.assign(poolSearch, { cards: "", items: "", events: "", characters: "", curses: "" });
  showModal.value = true;
}

async function save(): Promise<void> {
  saving.value = true;
  const modifiers: EventModifiers = {};
  if (overrideStartingStats.value) {
    modifiers.starting_stats = overrideStartingStats.value;
  }
  if (overrideXpMultiplier.value) {
    modifiers.xp_multiplier = overrideXpMultiplier.value;
  }
  if (form.game_mode === "single" && form.game_type === "duel") {
    modifiers.bot_difficulty = overrideBotDifficulty.value;
  }
  const hasHouseRules = Object.values(overrideHouseRules).some(Boolean);
  if (hasHouseRules) {
    modifiers.house_rules = { ...overrideHouseRules };
  }

  const payload = {
    ...form,
    modifiers: Object.keys(modifiers).length > 0 ? modifiers : undefined,
    xp_config: useCustomXp.value
      ? {
          base_xp: xpBase.value,
          win_bonus: xpWinBonus.value,
          online_multiplier: xpOnlineMultiplier.value,
        }
      : undefined,
    theme_color: form.theme_color || undefined,
  };

  try {
    const current = editing.value;
    if (current) {
      await axios.put(`/api/admin/rotating-events/${current.id}`, payload);
    } else {
      await axios.post("/api/admin/rotating-events", payload);
    }
    showModal.value = false;
    await fetch();
  } catch (error) {
    toast.error(isAxiosError<{ message?: string }>(error)
      ? (error.response?.data?.message ?? "Failed to save")
      : "Failed to save");
  }
  saving.value = false;
}

async function remove(event: RotatingEvent): Promise<void> {
  if (!confirm(`Delete "${event.name}"?`)) {
    return;
  }
  try {
    await axios.delete(`/api/admin/rotating-events/${event.id}`);
    await fetch();
  } catch {
    // ignore
  }
}

function filterStatEffects(effects: CardEffects | undefined): Record<string, number | undefined> {
  if (!effects) {
    return {};
  }
  const result: Record<string, number | undefined> = {};
  const specialKeys = new Set(["grant_item_id", "draw_item", "recover_die", "lose_die", "discard_item", "remove_curse"]);
  for (const [key, value] of Object.entries(effects)) {
    if (!specialKeys.has(key)) {
      result[key] = value;
    }
  }
  return result;
}

function shortStat(stat: string): string {
  const map: Record<string, string> = { wealth: "WLT", influence: "INF", security: "SEC", religion: "REL", food: "FOD", happiness: "HAP" };
  return map[stat] || stat.slice(0, 3).toUpperCase();
}

function itemEffectLabel(item: ItemPoolEntry): string {
  if (!item.effect) {
    return "?";
  }
  const effect = item.effect;
  const bonusValue = effect.bonus_value ?? 0;
  const labels: Record<string, string> = {
    roll_bonus: `Roll ${bonusValue > 0 ? "+" : ""}${effect.bonus_value}`,
    difficulty_reduction: `Diff -${effect.bonus_value}`,
    stat_boost: `${effect.stat ? shortStat(effect.stat) : "Stat"} +${effect.bonus_value}`,
    heal_die: `Heal ${effect.bonus_value} Die`,
    score_bonus: `Score +${effect.bonus_value}`,
    shield_negative: `Shield ${effect.bonus_value}x`,
    debuff_roll: `Roll ${effect.bonus_value}`,
    increase_difficulty: `Diff +${effect.bonus_value}`,
    peek_cards: `Peek ${effect.bonus_value}`,
    steal_stat: `Steal ${effect.bonus_value}`,
  };
  return (effect.bonus_type ? labels[effect.bonus_type] : undefined) || effect.bonus_type || "?";
}

function eventMechanicLabel(mechanic: string | undefined): string {
  if (!mechanic) {
    return "";
  }
  const labels: Record<string, string> = {
    stat_modifier: "Stat Modifier",
    grant_items: "Grant Items",
    reduce_dice: "Reduce Dice",
    altered_deal: "Altered Deal",
    score_event: "Score Event",
  };
  return labels[mechanic] || mechanic;
}

function eventMechanicClass(mechanic: string | undefined): string {
  if (mechanic && ["reduce_dice", "stat_modifier"].includes(mechanic)) {
    return "chip-neg";
  }
  if (mechanic && ["grant_items", "score_event"].includes(mechanic)) {
    return "chip-pos";
  }
  return "chip-special";
}

function isActive(event: RotatingEvent): boolean {
  if (!event.is_active) {
    return false;
  }
  const now = new Date();
  return event.starts_at !== undefined
    && event.ends_at !== undefined
    && new Date(event.starts_at) <= now
    && new Date(event.ends_at) >= now;
}

function formatDateRange(start: string | undefined, end: string | undefined): string {
  if (!start || !end) {
    return "";
  }
  const startDate = new Date(start).toLocaleDateString();
  const endDate = new Date(end).toLocaleDateString();
  return `${startDate} - ${endDate}`;
}

function curseNegLabel(curse: CursePoolEntry): string {
  const effect = curse.negative_effect;
  if (!effect) {
    return "?";
  }
  const type = effect.type;
  if (type === "lose_die") {
    return `Lose ${effect.value || 1} die`;
  }
  if (type === "stat_per_round") {
    return `${effect.value} ${effect.stat}/round`;
  }
  if (type === "difficulty_modifier") {
    return `+${effect.value} diff`;
  }
  if (type === "double_negative") {
    return "Double neg";
  }
  return type;
}

function cursePosLabel(curse: CursePoolEntry): string {
  const effect = curse.positive_effect;
  if (!effect) {
    return "?";
  }
  const type = effect.type;
  if (type === "xp_multiplier") {
    return `${effect.value}x XP`;
  }
  if (type === "stat_per_round") {
    return `+${effect.value} ${effect.stat}/round`;
  }
  if (type === "auto_max_stat") {
    return `Max ${effect.count || 1} stat`;
  }
  if (type === "score_bonus") {
    return `+${effect.value} score`;
  }
  if (type === "opponent_difficulty") {
    return `+${effect.value} opp diff`;
  }
  if (type === "opponent_lose_die") {
    return "Opp lose die";
  }
  return type;
}

function truncate(value: string | undefined, length: number): string {
  if (!value) {
    return "";
  }
  return value.length > length ? `${value.slice(0, length)}...` : value;
}

onMounted(async () => {
  await Promise.all([fetch(), fetchPoolData()]);
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.4rem; }
.list-panel { display: flex; flex-direction: column; gap: 8px; }
.list-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg-secondary); border: 1px solid rgba(138,106,46,0.2); border-radius: 8px; }
.list-info { flex: 1; min-width: 0; }
.list-top { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 4px; }
.list-top strong { color: var(--text-bright); }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); }
.list-actions { display: flex; gap: 6px; flex-shrink: 0; }
.status-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
.status-badge.active { background: rgba(74,138,58,0.2); color: #6abf50; }
.status-badge.inactive { background: rgba(160,48,32,0.2); color: #d05040; }
.badge-premium { background: rgba(138,58,185,0.2); color: #c890e0; }
.badge-admin { background: rgba(200,80,60,0.2); color: #e08060; }
.date-badge { font-size: 0.7rem; color: var(--text-secondary); }
.theme-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
.btn-sm { padding: 4px 12px; font-size: 0.8rem; border-radius: 4px; cursor: pointer; background: rgba(212,168,67,0.1); border: 1px solid rgba(212,168,67,0.3); color: var(--accent-gold); }
.btn-danger { background: rgba(160,48,32,0.15); border-color: rgba(160,48,32,0.3); color: #d05040; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; display: flex; align-items: center; justify-content: center; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 12px; padding: 24px; max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 16px; }
.form-group { margin-bottom: 12px; }
.form-group label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; background: rgba(0,0,0,0.3); border: 2px solid var(--border-gold); border-radius: 6px; color: var(--text-primary); font-family: inherit; font-size: 0.9rem; padding: 8px 10px; outline: none; box-sizing: border-box; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--accent-gold); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.btn-secondary { padding: 8px 16px; background: none; border: 1px solid rgba(138,106,46,0.4); color: var(--text-secondary); border-radius: 6px; cursor: pointer; }
.form-section-title { font-family: 'Cinzel', serif; font-size: 0.9rem; color: var(--accent-gold); margin: 16px 0 8px; padding-top: 12px; border-top: 1px solid rgba(138,106,46,0.15); }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; }
.checkbox-label input[type="checkbox"] { width: auto; margin: 0; }
.house-rules-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.pool-chip { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; margin-left: 6px; }
.pool-chip:not(.pool-all) { background: rgba(212,168,67,0.15); color: var(--accent-gold); }
.pool-all { background: rgba(74,138,58,0.15); color: #6abf50; }
.pool-controls { display: flex; gap: 6px; margin-bottom: 6px; }
.pool-list { background: rgba(0,0,0,0.2); border: 1px solid rgba(138,106,46,0.15); border-radius: 6px; padding: 8px; margin-top: 4px; }
.pool-search { margin-bottom: 8px; padding: 6px 8px !important; font-size: 0.8rem !important; }
.tile-grid { display: flex; flex-wrap: wrap; gap: 6px; max-height: 240px; overflow-y: auto; padding: 2px; }
.card-tile-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px; max-height: 300px; overflow-y: auto; padding: 2px; }
.card-tile { padding: 8px; border-radius: 6px; background: rgba(0,0,0,0.3); border: 2px solid transparent; cursor: pointer; transition: border-color 0.15s; user-select: none; }
.card-tile:hover { border-color: rgba(212,168,67,0.3); }
.card-tile-selected { border-color: var(--accent-gold); background: rgba(212,168,67,0.08); }
.card-tile-header { display: flex; justify-content: space-between; align-items: start; gap: 4px; margin-bottom: 4px; }
.card-tile-title { font-size: 0.75rem; font-weight: 600; color: var(--text-bright); line-height: 1.2; }
.card-tile-diff { font-size: 0.65rem; background: rgba(212,168,67,0.15); color: var(--accent-gold); padding: 1px 5px; border-radius: 3px; flex-shrink: 0; white-space: nowrap; }
.diff-pos { background: rgba(74,138,58,0.2); color: #6abf50; }
.diff-neg { background: rgba(160,48,32,0.2); color: #d05040; }
.card-tile-effects { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 3px; }
.card-tile-chip { font-size: 0.6rem; padding: 1px 5px; border-radius: 3px; font-weight: 600; white-space: nowrap; }
.chip-pos { background: rgba(74,138,58,0.2); color: #6abf50; }
.chip-neg { background: rgba(160,48,32,0.2); color: #d05040; }
.chip-special { background: rgba(67,160,212,0.2); color: #60b8e0; }
.chip-special-neg { background: rgba(160,48,32,0.15); color: #d07050; }
.char-tile-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
.char-tile { display: flex; align-items: center; gap: 8px; }
.char-tile-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.char-tile-info { flex: 1; min-width: 0; }
.pool-tile { padding: 6px 12px; border-radius: 6px; font-size: 0.78rem; color: var(--text-secondary); background: rgba(0,0,0,0.25); border: 2px solid transparent; cursor: pointer; transition: border-color 0.15s, color 0.15s; user-select: none; }
.pool-tile:hover { border-color: rgba(212,168,67,0.3); }
.pool-tile-selected { border-color: var(--accent-gold); color: var(--text-bright); background: rgba(212,168,67,0.08); }
.pool-tile-char { display: flex; align-items: center; gap: 6px; }
.tile-char-thumb { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.tile-name { white-space: nowrap; }
.color-picker-row { display: flex; gap: 8px; align-items: center; }
.color-input { width: 40px; height: 34px; padding: 2px; border: 2px solid var(--border-gold); border-radius: 4px; background: rgba(0,0,0,0.3); cursor: pointer; }
.color-text { flex: 1; }
</style>
