<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Balance Dashboard</h2>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filters.game_mode" @change="loadTab">
        <option value="">All Modes</option>
        <option value="single">Single</option>
        <option value="pass_and_play">Pass & Play</option>
        <option value="online">Online</option>
      </select>
      <select v-model="filters.game_type" @change="loadTab">
        <option value="">All Types</option>
        <option value="cooperative">Cooperative</option>
        <option value="duel">Duel</option>
      </select>
      <input v-model="filters.date_from" type="date" @change="loadTab" />
      <input v-model="filters.date_to" type="date" @change="loadTab" />
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="{ active: tab === 'cards' }" @click="tab = 'cards'; loadTab()">Cards</button>
      <button :class="{ active: tab === 'characters' }" @click="tab = 'characters'; loadTab()">Characters</button>
      <button :class="{ active: tab === 'items' }" @click="tab = 'items'; loadTab()">Items</button>
      <button :class="{ active: tab === 'events' }" @click="tab = 'events'; loadTab()">Events</button>
    </div>

    <!-- Cards Tab -->
    <div v-if="tab === 'cards'" class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="sortable" @click="sortBy('title')">Title</th>
            <th class="sortable" @click="sortBy('difficulty')">Diff</th>
            <th class="sortable" @click="sortBy('category')">Category</th>
            <th class="sortable" @click="sortBy('appearances')">Played</th>
            <th class="sortable" @click="sortBy('success_count')">Successes</th>
            <th class="sortable" @click="sortBy('success_rate')">Rate %</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="card in sortedCards" :key="card.id" class="clickable-row" @click="openCardModal(card)">
            <td>{{ card.title }}</td>
            <td>{{ card.difficulty }}</td>
            <td>{{ card.category || '—' }}</td>
            <td>{{ card.appearances }}</td>
            <td>{{ card.success_count }}</td>
            <td><span :class="rateClass(card.success_rate)">{{ card.success_rate }}%</span></td>
          </tr>
          <tr v-if="cards.length === 0"><td colspan="6" class="empty">No data</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Characters Tab -->
    <div v-if="tab === 'characters'" class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="sortable" @click="sortBy('name')">Name</th>
            <th class="sortable" @click="sortBy('pick_count')">Picks</th>
            <th class="sortable" @click="sortBy('win_count')">Wins</th>
            <th class="sortable" @click="sortBy('win_rate')">Win Rate %</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ch in sortedCharacters" :key="ch.id" class="clickable-row" @click="openCharacterModal(ch)">
            <td>{{ ch.name }}</td>
            <td>{{ ch.pick_count }}</td>
            <td>{{ ch.win_count }}</td>
            <td><span :class="rateClass(ch.win_rate)">{{ ch.win_rate }}%</span></td>
          </tr>
          <tr v-if="characters.length === 0"><td colspan="4" class="empty">No data</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Items Tab -->
    <div v-if="tab === 'items'" class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="sortable" @click="sortBy('name')">Name</th>
            <th class="sortable" @click="sortBy('effect_type')">Type</th>
            <th class="sortable" @click="sortBy('times_acquired')">Acquired</th>
            <th class="sortable" @click="sortBy('games_appeared_in')">Games</th>
            <th class="sortable" @click="sortBy('times_used')">Used</th>
            <th class="sortable" @click="sortBy('times_cursed')">Cursed</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in sortedItems" :key="item.id" class="clickable-row" @click="openItemModal(item)">
            <td>
              <span :class="{ 'negative-item': item.is_negative }">{{ item.name }}</span>
            </td>
            <td><span class="type-badge">{{ item.effect_type }}</span></td>
            <td>{{ item.times_acquired }}</td>
            <td>{{ item.games_appeared_in }}</td>
            <td>{{ item.times_used }}</td>
            <td>{{ item.times_cursed }}</td>
          </tr>
          <tr v-if="items.length === 0"><td colspan="6" class="empty">No data</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Events Tab -->
    <div v-if="tab === 'events'" class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="sortable" @click="sortBy('title')">Title</th>
            <th class="sortable" @click="sortBy('mechanic')">Mechanic</th>
            <th class="sortable" @click="sortBy('times_drawn')">Times Drawn</th>
            <th>of Games</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ev in sortedEvents" :key="ev.id" class="clickable-row" @click="openEventModal(ev)">
            <td>{{ ev.title }}</td>
            <td><span v-if="ev.mechanic" class="type-badge">{{ ev.mechanic }}</span><span v-else class="muted">—</span></td>
            <td>{{ ev.times_drawn }}</td>
            <td class="muted">/ {{ ev.total_games }}</td>
          </tr>
          <tr v-if="events.length === 0"><td colspan="4" class="empty">No data</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Card Detail Modal -->
    <div v-if="selectedCard" class="modal-overlay" @click.self="selectedCard = null">
      <div class="modal-content detail-modal">
        <button class="modal-close" @click="selectedCard = null">&times;</button>
        <div class="detail-header">
          <h3>{{ selectedCard.title }}</h3>
          <div class="detail-badges">
            <span class="badge">Difficulty: {{ selectedCard.difficulty }}</span>
            <span v-if="selectedCard.category" class="badge">{{ selectedCard.category }}</span>
          </div>
        </div>

        <p v-if="selectedCard.description" class="detail-description">{{ selectedCard.description }}</p>

        <div class="detail-stats">
          <div class="detail-stat">
            <span class="detail-stat-label">Appearances</span>
            <span class="detail-stat-value">{{ selectedCardStats.appearances }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Successes</span>
            <span class="detail-stat-value">{{ selectedCardStats.success_count }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Success Rate</span>
            <span class="detail-stat-value" :class="rateClass(selectedCardStats.success_rate)">{{ selectedCardStats.success_rate }}%</span>
          </div>
        </div>

        <div v-if="selectedCard.positive_effects || selectedCard.negative_effects" class="effects-grid">
          <div v-if="hasEffects(selectedCard.positive_effects)" class="effect-block">
            <h4 class="effect-title success">On Success</h4>
            <p v-if="selectedCard.positive_flavor" class="flavor-text">{{ selectedCard.positive_flavor }}</p>
            <div class="effect-list">
              <div v-for="(val, key) in filteredEffects(selectedCard.positive_effects)" :key="key" class="effect-item">
                <span class="effect-key">{{ formatEffectKey(key) }}</span>
                <span class="effect-val" :class="val > 0 ? 'positive' : 'negative'">{{ val > 0 ? '+' : '' }}{{ val }}</span>
              </div>
            </div>
          </div>
          <div v-if="hasEffects(selectedCard.negative_effects)" class="effect-block">
            <h4 class="effect-title failure">On Failure</h4>
            <p v-if="selectedCard.negative_flavor" class="flavor-text">{{ selectedCard.negative_flavor }}</p>
            <div class="effect-list">
              <div v-for="(val, key) in filteredEffects(selectedCard.negative_effects)" :key="key" class="effect-item">
                <span class="effect-key">{{ formatEffectKey(key) }}</span>
                <span class="effect-val" :class="val > 0 ? 'positive' : 'negative'">{{ val > 0 ? '+' : '' }}{{ val }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="availability-row">
          <span :class="['avail-tag', selectedCard.available_cooperative ? 'active' : 'inactive']">Cooperative</span>
          <span :class="['avail-tag', selectedCard.available_duel ? 'active' : 'inactive']">Duel</span>
        </div>

        <div class="detail-actions">
          <router-link to="/admin/cards" class="btn-edit" @click="selectedCard = null">Edit in Card Manager</router-link>
        </div>
      </div>
    </div>

    <!-- Character Detail Modal -->
    <div v-if="selectedCharacter" class="modal-overlay" @click.self="selectedCharacter = null">
      <div class="modal-content detail-modal">
        <button class="modal-close" @click="selectedCharacter = null">&times;</button>
        <div class="detail-header">
          <div class="char-header-row">
            <img v-if="selectedCharacter.image_url" :src="selectedCharacter.image_url" class="char-avatar" />
            <div>
              <h3>{{ selectedCharacter.name }}</h3>
              <div class="detail-badges">
                <span class="badge">Wild: {{ selectedCharacter.wild_value }}</span>
                <span v-if="selectedCharacter.wild_ability" class="badge">{{ selectedCharacter.wild_ability }}</span>
              </div>
            </div>
          </div>
        </div>

        <p v-if="selectedCharacter.description" class="detail-description">{{ selectedCharacter.description }}</p>

        <p v-if="selectedCharacter.wild_ability_description" class="wild-desc">
          <strong>Wild Ability:</strong> {{ selectedCharacter.wild_ability_description }}
        </p>

        <div class="detail-stats">
          <div class="detail-stat">
            <span class="detail-stat-label">Picks</span>
            <span class="detail-stat-value">{{ selectedCharStats.pick_count }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Wins</span>
            <span class="detail-stat-value">{{ selectedCharStats.win_count }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Win Rate</span>
            <span class="detail-stat-value" :class="rateClass(selectedCharStats.win_rate)">{{ selectedCharStats.win_rate }}%</span>
          </div>
        </div>

        <div v-if="selectedCharacter.dice" class="dice-section">
          <h4 class="section-label">Dice</h4>
          <div class="dice-grid">
            <div v-for="(die, i) in selectedCharacter.dice" :key="i" class="die-row">
              <span class="die-label">Die {{ i + 1 }}</span>
              <div class="die-faces">
                <span v-for="(face, j) in die" :key="j" :class="['die-face', face === 'WILD' ? 'wild' : '']">{{ face }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="availability-row">
          <span :class="['avail-tag', selectedCharacter.available_cooperative ? 'active' : 'inactive']">Cooperative</span>
          <span :class="['avail-tag', selectedCharacter.available_duel ? 'active' : 'inactive']">Duel</span>
        </div>

        <div class="detail-actions">
          <router-link to="/admin/characters" class="btn-edit" @click="selectedCharacter = null">Edit in Character Manager</router-link>
        </div>
      </div>
    </div>

    <!-- Item Detail Modal -->
    <div v-if="selectedItem" class="modal-overlay" @click.self="selectedItem = null">
      <div class="modal-content detail-modal">
        <button class="modal-close" @click="selectedItem = null">&times;</button>
        <div class="detail-header">
          <h3 :class="{ 'negative-item': selectedItem.is_negative }">{{ selectedItem.name }}</h3>
          <div class="detail-badges">
            <span class="badge">{{ selectedItem.effect_type }}</span>
            <span v-if="selectedItem.is_negative" class="badge negative-badge">Negative</span>
            <span v-if="selectedItem.is_consumable" class="badge">Consumable</span>
          </div>
        </div>

        <p v-if="selectedItem.description" class="detail-description">{{ selectedItem.description }}</p>

        <div class="detail-stats">
          <div class="detail-stat">
            <span class="detail-stat-label">Acquired</span>
            <span class="detail-stat-value">{{ selectedItemStats.times_acquired }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Games</span>
            <span class="detail-stat-value">{{ selectedItemStats.games_appeared_in }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Used</span>
            <span class="detail-stat-value">{{ selectedItemStats.times_used }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Cursed</span>
            <span class="detail-stat-value">{{ selectedItemStats.times_cursed }}</span>
          </div>
        </div>

        <div v-if="selectedItem.effect && Object.keys(selectedItem.effect).length > 0" class="effect-block">
          <h4 class="section-label">Effect</h4>
          <div class="effect-list">
            <div v-for="(val, key) in selectedItem.effect" :key="key" class="effect-item">
              <span class="effect-key">{{ formatEffectKey(key) }}</span>
              <span class="effect-val">{{ val }}</span>
            </div>
          </div>
        </div>

        <div class="availability-row">
          <span :class="['avail-tag', selectedItem.available_cooperative ? 'active' : 'inactive']">Cooperative</span>
          <span :class="['avail-tag', selectedItem.available_duel ? 'active' : 'inactive']">Duel</span>
        </div>

        <div class="detail-actions">
          <router-link to="/admin/items" class="btn-edit" @click="selectedItem = null">Edit in Item Manager</router-link>
        </div>
      </div>
    </div>

    <!-- Event Detail Modal -->
    <div v-if="selectedEvent" class="modal-overlay" @click.self="selectedEvent = null">
      <div class="modal-content detail-modal">
        <button class="modal-close" @click="selectedEvent = null">&times;</button>
        <div class="detail-header">
          <h3>{{ selectedEvent.title }}</h3>
          <div class="detail-badges">
            <span v-if="selectedEvent.mechanic" class="badge">{{ selectedEvent.mechanic }}</span>
          </div>
        </div>

        <p v-if="selectedEvent.effect" class="detail-description">{{ selectedEvent.effect }}</p>

        <div class="detail-stats">
          <div class="detail-stat">
            <span class="detail-stat-label">Times Drawn</span>
            <span class="detail-stat-value">{{ selectedEventStats.times_drawn }}</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-label">Total Games</span>
            <span class="detail-stat-value">{{ selectedEventStats.total_games }}</span>
          </div>
        </div>

        <div v-if="selectedEvent.stat_modifiers && hasEffects(selectedEvent.stat_modifiers)" class="effect-block">
          <h4 class="section-label">Stat Modifiers</h4>
          <div class="effect-list">
            <div v-for="(val, key) in filteredEffects(selectedEvent.stat_modifiers)" :key="key" class="effect-item">
              <span class="effect-key">{{ formatEffectKey(key) }}</span>
              <span class="effect-val" :class="val > 0 ? 'positive' : 'negative'">{{ val > 0 ? '+' : '' }}{{ val }}</span>
            </div>
          </div>
        </div>

        <div v-if="selectedEvent.mechanic_data && Object.keys(selectedEvent.mechanic_data).length > 0" class="effect-block">
          <h4 class="section-label">Mechanic Data</h4>
          <div class="effect-list">
            <div v-for="(val, key) in selectedEvent.mechanic_data" :key="key" class="effect-item">
              <span class="effect-key">{{ formatEffectKey(key) }}</span>
              <span class="effect-val">{{ val }}</span>
            </div>
          </div>
        </div>

        <div class="availability-row">
          <span :class="['avail-tag', selectedEvent.available_cooperative ? 'active' : 'inactive']">Cooperative</span>
          <span :class="['avail-tag', selectedEvent.available_duel ? 'active' : 'inactive']">Duel</span>
        </div>

        <div class="detail-actions">
          <router-link to="/admin/events" class="btn-edit" @click="selectedEvent = null">Edit in Event Manager</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";

type EffectMap = Record<string, number | boolean | string | undefined>;

interface CardStats {
  id: number;
  title: string;
  difficulty: number;
  category: string | undefined;
  appearances: number;
  success_count: number;
  success_rate: number;
}

interface CardDetail {
  title: string;
  difficulty?: number;
  category?: string;
  description?: string;
  positive_effects?: EffectMap;
  negative_effects?: EffectMap;
  positive_flavor?: string;
  negative_flavor?: string;
  available_cooperative?: boolean;
  available_duel?: boolean;
}

interface CharacterStats {
  id: number;
  name: string;
  pick_count: number;
  win_count: number;
  win_rate: number;
}

interface CharacterDetail {
  name: string;
  description?: string;
  image_url?: string;
  wild_value?: number;
  wild_ability?: string;
  wild_ability_description?: string;
  dice?: string[][];
  available_cooperative?: boolean;
  available_duel?: boolean;
}

interface ItemStats {
  id: number;
  name: string;
  effect_type: string;
  is_negative: boolean;
  times_acquired: number;
  games_appeared_in: number;
  times_used: number;
  times_cursed: number;
}

interface ItemDetail {
  name: string;
  effect_type: string;
  is_negative?: boolean;
  is_consumable?: boolean;
  description?: string;
  effect?: EffectMap;
  available_cooperative?: boolean;
  available_duel?: boolean;
}

interface EventStats {
  id: number;
  title: string;
  mechanic: string | undefined;
  effect?: string;
  times_drawn: number;
  total_games: number;
}

interface EventDetail {
  title: string;
  mechanic?: string;
  effect?: string;
  stat_modifiers?: EffectMap;
  mechanic_data?: EffectMap;
  available_cooperative?: boolean;
  available_duel?: boolean;
}

interface BalanceFilters {
  game_mode: string;
  game_type: string;
  date_from: string;
  date_to: string;
}

const tab = ref("cards");
const filters = reactive<BalanceFilters>({ game_mode: "", game_type: "", date_from: "", date_to: "" });
const cards = ref<CardStats[]>([]);
const characters = ref<CharacterStats[]>([]);
const items = ref<ItemStats[]>([]);
const events = ref<EventStats[]>([]);
const sortKey = ref("");
const sortAsc = ref(true);
const selectedCard = ref<CardDetail | undefined>(undefined);
const selectedCardStats = ref<Partial<CardStats>>({});
const selectedCharacter = ref<CharacterDetail | undefined>(undefined);
const selectedCharStats = ref<Partial<CharacterStats>>({});
const selectedItem = ref<ItemDetail | undefined>(undefined);
const selectedItemStats = ref<Partial<ItemStats>>({});
const selectedEvent = ref<EventDetail | undefined>(undefined);
const selectedEventStats = ref<Partial<EventStats>>({});

const sortedCards = computed<CardStats[]>(() => sorted(cards.value));
const sortedCharacters = computed<CharacterStats[]>(() => sorted(characters.value));
const sortedItems = computed<ItemStats[]>(() => sorted(items.value));
const sortedEvents = computed<EventStats[]>(() => sorted(events.value));

async function loadTab(): Promise<void> {
  const parameters: Record<string, string> = {};
  for (const [key, value] of Object.entries(filters)) {
    if (value) {
      parameters[key] = value;
    }
  }
  sortKey.value = "";

  try {
    switch (tab.value) {
    case "cards": {
      const response = await axios.get<CardStats[]>("/api/admin/balance/cards", { params: parameters });
      cards.value = response.data;
    
    break;
    }
    case "characters": {
      const response = await axios.get<CharacterStats[]>("/api/admin/balance/characters", { params: parameters });
      characters.value = response.data;
    
    break;
    }
    case "items": {
      const response = await axios.get<ItemStats[]>("/api/admin/balance/items", { params: parameters });
      items.value = response.data;
    
    break;
    }
    case "events": {
      const response = await axios.get<EventStats[]>("/api/admin/balance/events", { params: parameters });
      events.value = response.data;
    
    break;
    }
    // No default
    }
  } catch { /* ignore */ }
}

async function openCardModal(cardStats: CardStats): Promise<void> {
  selectedCardStats.value = cardStats;
  try {
    const response = await axios.get<CardDetail>(`/api/admin/cards/${cardStats.id}`);
    selectedCard.value = response.data;
  } catch {
    selectedCard.value = { title: cardStats.title, difficulty: cardStats.difficulty, category: cardStats.category };
  }
}

async function openCharacterModal(charStats: CharacterStats): Promise<void> {
  selectedCharStats.value = charStats;
  try {
    const response = await axios.get<CharacterDetail>(`/api/admin/characters/${charStats.id}`);
    selectedCharacter.value = response.data;
  } catch {
    selectedCharacter.value = { name: charStats.name };
  }
}

async function openItemModal(itemStats: ItemStats): Promise<void> {
  selectedItemStats.value = itemStats;
  try {
    const response = await axios.get<ItemDetail>(`/api/admin/items/${itemStats.id}`);
    selectedItem.value = response.data;
  } catch {
    selectedItem.value = { name: itemStats.name, effect_type: itemStats.effect_type, is_negative: itemStats.is_negative };
  }
}

async function openEventModal(eventStats: EventStats): Promise<void> {
  selectedEventStats.value = eventStats;
  try {
    const response = await axios.get<EventDetail>(`/api/admin/events/${eventStats.id}`);
    selectedEvent.value = response.data;
  } catch {
    selectedEvent.value = { title: eventStats.title, effect: eventStats.effect, mechanic: eventStats.mechanic };
  }
}

function hasEffects(effects: EffectMap | undefined): boolean {
  if (!effects) {
    return false;
  }
  return Object.values(effects).some((value) => value !== 0 && value !== false && value !== undefined);
}

function filteredEffects(effects: EffectMap | undefined): EffectMap {
  if (!effects) {
    return {};
  }
  const filtered: EffectMap = {};
  for (const [key, value] of Object.entries(effects)) {
    if (value !== 0 && value !== false && value !== undefined) {
      filtered[key] = value;
    }
  }
  return filtered;
}

function formatEffectKey(key: string): string {
  return key.replaceAll('_', " ").replaceAll(/\b\w/g, (character) => character.toUpperCase());
}

function sortBy(key: string): void {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value;
  } else {
    sortKey.value = key;
    sortAsc.value = true;
  }
}

function fieldValue(row: object, key: string): unknown {
  const record: Record<string, unknown> = { ...row };
  return record[key];
}

function sorted<T extends object>(rows: T[]): T[] {
  if (!sortKey.value) {
    return rows;
  }
  const key = sortKey.value;
  return rows.toSorted((first, second) => {
    const firstValue = fieldValue(first, key);
    const secondValue = fieldValue(second, key);
    const comparison = typeof firstValue === "string"
      ? firstValue.localeCompare(typeof secondValue === "string" ? secondValue : "")
      : (typeof firstValue === "number" ? firstValue : 0) - (typeof secondValue === "number" ? secondValue : 0);
    return sortAsc.value ? comparison : -comparison;
  });
}

function rateClass(rate: number): string {
  if (rate >= 70) {
    return "rate-high";
  }
  if (rate >= 40) {
    return "rate-mid";
  }
  return "rate-low";
}

onMounted(async () => {
  await loadTab();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }

.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-bar select, .filter-bar input { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 5px 8px; border-radius: 4px; font-family: inherit; font-size: 0.85rem; }

.tabs { display: flex; gap: 4px; margin-bottom: 16px; flex-wrap: wrap; }
.tabs button { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); color: var(--text-secondary); padding: 6px 16px; border-radius: 4px; cursor: pointer; font-family: inherit; }
.tabs button.active { color: var(--accent-gold); border-color: var(--accent-gold); background: rgba(212, 168, 67, 0.1); }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
th { text-align: left; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.3); }
th.sortable { cursor: pointer; }
th.sortable:hover { color: var(--accent-gold); }
td { padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.1); color: var(--text-bright); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
.muted { color: var(--text-secondary); }

.clickable-row { cursor: pointer; transition: background 0.15s; }
.clickable-row:hover { background: rgba(212, 168, 67, 0.08); }

.negative-item { color: #e74c3c; }
.type-badge { background: rgba(138, 106, 46, 0.12); border: 1px solid rgba(138, 106, 46, 0.2); color: var(--text-secondary); padding: 1px 6px; border-radius: 3px; font-size: 0.75rem; text-transform: capitalize; }
.negative-badge { background: rgba(231, 76, 60, 0.12); border-color: rgba(231, 76, 60, 0.25); color: #e74c3c; }

.rate-high { color: #2ecc71; font-weight: 600; }
.rate-mid { color: #f1c40f; font-weight: 600; }
.rate-low { color: #e74c3c; font-weight: 600; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-content { background: var(--bg-primary, #1a1a2e); border: 1px solid rgba(138, 106, 46, 0.4); border-radius: 12px; max-width: 560px; width: 100%; max-height: 85vh; overflow-y: auto; position: relative; }
.detail-modal { padding: 24px; }
.modal-close { position: absolute; top: 12px; right: 16px; background: none; border: none; color: var(--text-secondary); font-size: 1.6rem; cursor: pointer; line-height: 1; }
.modal-close:hover { color: var(--text-bright); }

.detail-header { margin-bottom: 12px; }
.detail-header h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.25rem; margin: 0 0 6px; }
.detail-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.badge { background: rgba(138, 106, 46, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-secondary); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; text-transform: capitalize; }

.char-header-row { display: flex; gap: 14px; align-items: center; }
.char-avatar { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(138, 106, 46, 0.4); flex-shrink: 0; }

.detail-description { color: var(--text-secondary); font-size: 0.88rem; line-height: 1.5; margin: 0 0 14px; }
.wild-desc { color: var(--text-secondary); font-size: 0.85rem; margin: 0 0 14px; }
.wild-desc strong { color: var(--text-bright); }

.detail-stats { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.detail-stat { flex: 1; min-width: 70px; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px; text-align: center; }
.detail-stat-label { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 4px; }
.detail-stat-value { display: block; font-size: 1.2rem; font-weight: 700; color: var(--text-bright); }

.effects-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
@media (max-width: 480px) { .effects-grid { grid-template-columns: 1fr; } }
.effect-block { background: rgba(0,0,0,0.15); border-radius: 8px; padding: 12px; margin-bottom: 12px; }
.effect-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 6px; }
.effect-title.success { color: #2ecc71; }
.effect-title.failure { color: #e74c3c; }
.flavor-text { font-size: 0.78rem; color: var(--text-secondary); font-style: italic; margin: 0 0 8px; }
.effect-list { display: flex; flex-direction: column; gap: 3px; }
.effect-item { display: flex; justify-content: space-between; font-size: 0.82rem; }
.effect-key { color: var(--text-secondary); text-transform: capitalize; }
.effect-val { color: var(--text-bright); }
.effect-val.positive { color: #2ecc71; font-weight: 600; }
.effect-val.negative { color: #e74c3c; font-weight: 600; }

.section-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin: 0 0 8px; }

.dice-section { margin-bottom: 16px; }
.dice-grid { display: flex; flex-direction: column; gap: 6px; }
.die-row { display: flex; align-items: center; gap: 8px; }
.die-label { font-size: 0.78rem; color: var(--text-secondary); width: 44px; flex-shrink: 0; }
.die-faces { display: flex; gap: 4px; }
.die-face { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.25); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 4px; font-size: 0.78rem; color: var(--text-bright); font-weight: 600; }
.die-face.wild { background: rgba(138, 106, 46, 0.2); border-color: var(--accent-gold); color: var(--accent-gold); font-size: 0.6rem; }

.availability-row { display: flex; gap: 6px; margin-bottom: 16px; }
.avail-tag { padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.avail-tag.active { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.3); }
.avail-tag.inactive { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.2); opacity: 0.6; }

.detail-actions { display: flex; justify-content: flex-end; padding-top: 4px; border-top: 1px solid rgba(138, 106, 46, 0.15); }
.btn-edit { display: inline-block; background: linear-gradient(135deg, rgba(138, 106, 46, 0.3), rgba(138, 106, 46, 0.15)); border: 1px solid rgba(138, 106, 46, 0.5); color: var(--accent-gold); padding: 8px 18px; border-radius: 6px; font-size: 0.85rem; font-family: inherit; cursor: pointer; text-decoration: none; transition: background 0.2s; }
.btn-edit:hover { background: linear-gradient(135deg, rgba(138, 106, 46, 0.5), rgba(138, 106, 46, 0.25)); }
</style>
