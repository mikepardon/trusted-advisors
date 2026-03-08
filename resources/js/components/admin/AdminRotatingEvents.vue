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
              <input v-model.number="modStartingStats" type="number" min="1" max="20" placeholder="Default (8)" />
            </div>
            <div class="form-group">
              <label>XP Multiplier</label>
              <input v-model.number="modXpMultiplier" type="number" step="0.5" min="0.5" placeholder="Default (1)" />
            </div>
          </div>
          <div class="form-group">
            <label>Image URL</label>
            <input v-model="form.image_url" placeholder="Optional" />
          </div>

          <!-- Bot Difficulty (for single-player duel events) -->
          <div v-if="form.game_mode === 'single' && form.game_type === 'duel'" class="form-group">
            <label>Bot Difficulty</label>
            <select v-model="modBotDifficulty">
              <option value="easy">Easy</option>
              <option value="medium">Medium</option>
              <option value="hard">Hard</option>
            </select>
          </div>

          <!-- House Rules -->
          <div class="form-section-title">House Rules</div>
          <div class="form-group house-rules-grid">
            <label class="checkbox-label"><input type="checkbox" v-model="modHouseRules.no_negative_effects" /> No Negative Effects</label>
            <label class="checkbox-label"><input type="checkbox" v-model="modHouseRules.double_positive_effects" /> Double Positive Effects</label>
            <label class="checkbox-label"><input type="checkbox" v-model="modHouseRules.random_starting_stats" /> Random Starting Stats</label>
            <label class="checkbox-label"><input type="checkbox" v-model="modHouseRules.hardcore_mode" /> Hardcore (lose at stat &le; 3)</label>
            <label class="checkbox-label"><input type="checkbox" v-model="modHouseRules.draw_curse_per_round" /> Draw Curse Each Round</label>
          </div>

          <!-- Game Settings -->
          <div class="form-section-title">Game Settings</div>
          <div class="form-grid">
            <div class="form-group">
              <label>Total Rounds</label>
              <select v-model="form.total_rounds">
                <option :value="null">Player chooses</option>
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
                <input type="checkbox" v-model="form.affects_elo" /> Yes, affects ELO rating
              </label>
            </div>
          </div>

          <!-- XP Override -->
          <div class="form-section-title">XP Override</div>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="useCustomXp" /> Use custom XP config
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
              <input type="color" v-model="form.theme_color" class="color-input" />
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
              <span class="pool-chip" v-if="form.card_pool && form.card_pool.length">{{ form.card_pool.length }} selected</span>
              <span class="pool-chip pool-all" v-else>All cards included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('cards')">{{ showPool.cards ? 'Hide' : 'Select Cards' }}</button>
              <button v-if="form.card_pool && form.card_pool.length" type="button" class="btn-sm" @click="form.card_pool = null">Clear</button>
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
              <span class="pool-chip" v-if="form.item_pool && form.item_pool.length">{{ form.item_pool.length }} selected</span>
              <span class="pool-chip pool-all" v-else>All items included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('items')">{{ showPool.items ? 'Hide' : 'Select Items' }}</button>
              <button v-if="form.item_pool && form.item_pool.length" type="button" class="btn-sm" @click="form.item_pool = null">Clear</button>
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
              <span class="pool-chip" v-if="form.event_pool && form.event_pool.length">{{ form.event_pool.length }} selected</span>
              <span class="pool-chip pool-all" v-else>All events included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('events')">{{ showPool.events ? 'Hide' : 'Select Events' }}</button>
              <button v-if="form.event_pool && form.event_pool.length" type="button" class="btn-sm" @click="form.event_pool = null">Clear</button>
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
              <span class="pool-chip" v-if="form.character_pool && form.character_pool.length">{{ form.character_pool.length }} selected</span>
              <span class="pool-chip pool-all" v-else>All characters included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('characters')">{{ showPool.characters ? 'Hide' : 'Select Characters' }}</button>
              <button v-if="form.character_pool && form.character_pool.length" type="button" class="btn-sm" @click="form.character_pool = null">Clear</button>
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
              <span class="pool-chip" v-if="form.curse_pool && form.curse_pool.length">{{ form.curse_pool.length }} selected</span>
              <span class="pool-chip pool-all" v-else>All curses included</span>
            </label>
            <div class="pool-controls">
              <button type="button" class="btn-sm" @click="togglePool('curses')">{{ showPool.curses ? 'Hide' : 'Select Curses' }}</button>
              <button v-if="form.curse_pool && form.curse_pool.length" type="button" class="btn-sm" @click="form.curse_pool = null">Clear</button>
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
              <option :value="null">Rotate every 3 rounds (default)</option>
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
                <input type="checkbox" v-model="form.is_active" /> Active
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

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';

export default {
  name: 'AdminRotatingEvents',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      events: [],
      showModal: false,
      editing: null,
      saving: false,
      form: this.emptyForm(),
      modStartingStats: null,
      modXpMultiplier: null,
      modBotDifficulty: 'medium',
      modHouseRules: { no_negative_effects: false, double_positive_effects: false, random_starting_stats: false, hardcore_mode: false, draw_curse_per_round: false },
      // XP override
      useCustomXp: false,
      xpBase: 50,
      xpWinBonus: 100,
      xpOnlineMultiplier: 1.5,
      // Content pools data
      allCards: [],
      allItems: [],
      allEvents: [],
      allCharacters: [],
      allCurses: [],
      showPool: { cards: false, items: false, events: false, characters: false, curses: false },
      poolSearch: { cards: '', items: '', events: '', characters: '', curses: '' },
    };
  },
  computed: {
    filteredCards() {
      const q = this.poolSearch.cards.toLowerCase();
      return q ? this.allCards.filter(c => (c.title || '').toLowerCase().includes(q)) : this.allCards;
    },
    filteredItems() {
      const q = this.poolSearch.items.toLowerCase();
      return q ? this.allItems.filter(c => (c.name || '').toLowerCase().includes(q)) : this.allItems;
    },
    filteredEvents() {
      const q = this.poolSearch.events.toLowerCase();
      return q ? this.allEvents.filter(c => (c.title || '').toLowerCase().includes(q)) : this.allEvents;
    },
    filteredCharacters() {
      const q = this.poolSearch.characters.toLowerCase();
      return q ? this.allCharacters.filter(c => (c.name || '').toLowerCase().includes(q)) : this.allCharacters;
    },
    filteredCurses() {
      const q = this.poolSearch.curses.toLowerCase();
      return q ? this.allCurses.filter(c => (c.name || '').toLowerCase().includes(q)) : this.allCurses;
    },
  },
  async mounted() {
    await Promise.all([this.fetch(), this.fetchPoolData()]);
  },
  methods: {
    emptyForm() {
      return {
        name: '',
        description: '',
        image_url: '',
        game_type: 'cooperative',
        game_mode: 'single',
        reward_coins: 0,
        max_attempts: null,
        starts_at: '',
        ends_at: '',
        is_active: true,
        visibility: 'all',
        card_pool: null,
        item_pool: null,
        event_pool: null,
        character_pool: null,
        curse_pool: null,
        fixed_event_id: null,
        total_rounds: null,
        affects_elo: false,
        theme_color: '',
      };
    },
    async fetch() {
      try {
        const res = await axios.get('/api/admin/rotating-events');
        this.events = res.data;
      } catch {}
    },
    async fetchPoolData() {
      try {
        const [cards, items, events, chars, curses] = await Promise.all([
          axios.get('/api/admin/cards'),
          axios.get('/api/admin/items'),
          axios.get('/api/admin/events'),
          axios.get('/api/admin/characters'),
          axios.get('/api/admin/curses'),
        ]);
        this.allCards = cards.data.data || cards.data;
        this.allItems = items.data.data || items.data;
        this.allEvents = events.data.data || events.data;
        this.allCharacters = chars.data.data || chars.data;
        this.allCurses = curses.data.data || curses.data;
      } catch {}
    },
    togglePool(type) {
      this.showPool[type] = !this.showPool[type];
    },
    isInPool(key, id) {
      return this.form[key] && this.form[key].includes(id);
    },
    togglePoolItem(key, id) {
      if (!this.form[key]) {
        this.form[key] = [id];
      } else if (this.form[key].includes(id)) {
        this.form[key] = this.form[key].filter(x => x !== id);
        if (this.form[key].length === 0) this.form[key] = null;
      } else {
        this.form[key] = [...this.form[key], id];
      }
    },
    selectAllPool(type) {
      const allMap = { cards: this.allCards, items: this.allItems, events: this.allEvents, characters: this.allCharacters, curses: this.allCurses };
      const key = { cards: 'card_pool', items: 'item_pool', events: 'event_pool', characters: 'character_pool', curses: 'curse_pool' }[type];
      const allIds = allMap[type].map(i => i.id);
      // Toggle: if all selected, clear; otherwise select all
      if (this.form[key] && this.form[key].length === allIds.length) {
        this.form[key] = null;
      } else {
        this.form[key] = [...allIds];
      }
    },
    openCreate() {
      this.editing = null;
      this.form = this.emptyForm();
      this.modStartingStats = null;
      this.modXpMultiplier = null;
      this.modBotDifficulty = 'medium';
      this.modHouseRules = { no_negative_effects: false, double_positive_effects: false, random_starting_stats: false, hardcore_mode: false, draw_curse_per_round: false };
      this.useCustomXp = false;
      this.xpBase = 50;
      this.xpWinBonus = 100;
      this.xpOnlineMultiplier = 1.5;
      this.showPool = { cards: false, items: false, events: false, characters: false, curses: false };
      this.poolSearch = { cards: '', items: '', events: '', characters: '', curses: '' };
      this.showModal = true;
    },
    openEdit(e) {
      this.editing = e;
      this.form = {
        name: e.name,
        description: e.description,
        image_url: e.image_url || '',
        game_type: e.game_type,
        game_mode: e.game_mode,
        reward_coins: e.reward_coins,
        max_attempts: e.max_attempts || null,
        starts_at: e.starts_at ? e.starts_at.substring(0, 16) : '',
        ends_at: e.ends_at ? e.ends_at.substring(0, 16) : '',
        is_active: e.is_active,
        visibility: e.visibility || 'all',
        card_pool: e.card_pool || null,
        item_pool: e.item_pool || null,
        event_pool: e.event_pool || null,
        character_pool: e.character_pool || null,
        curse_pool: e.curse_pool || null,
        fixed_event_id: e.fixed_event_id || null,
        total_rounds: e.total_rounds || null,
        affects_elo: e.affects_elo || false,
        theme_color: e.theme_color || '',
      };
      this.modStartingStats = e.modifiers?.starting_stats || null;
      this.modXpMultiplier = e.modifiers?.xp_multiplier || null;
      this.modBotDifficulty = e.modifiers?.bot_difficulty || 'medium';
      const hr = e.modifiers?.house_rules || {};
      this.modHouseRules = {
        no_negative_effects: !!hr.no_negative_effects,
        double_positive_effects: !!hr.double_positive_effects,
        random_starting_stats: !!hr.random_starting_stats,
        hardcore_mode: !!hr.hardcore_mode,
        draw_curse_per_round: !!hr.draw_curse_per_round,
      };
      // XP config
      if (e.xp_config) {
        this.useCustomXp = true;
        this.xpBase = e.xp_config.base_xp ?? 50;
        this.xpWinBonus = e.xp_config.win_bonus ?? 100;
        this.xpOnlineMultiplier = e.xp_config.online_multiplier ?? 1.5;
      } else {
        this.useCustomXp = false;
        this.xpBase = 50;
        this.xpWinBonus = 100;
        this.xpOnlineMultiplier = 1.5;
      }
      this.showPool = { cards: false, items: false, events: false, characters: false, curses: false };
      this.poolSearch = { cards: '', items: '', events: '', characters: '', curses: '' };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      const payload = { ...this.form };
      const modifiers = {};
      if (this.modStartingStats) modifiers.starting_stats = this.modStartingStats;
      if (this.modXpMultiplier) modifiers.xp_multiplier = this.modXpMultiplier;
      if (this.form.game_mode === 'single' && this.form.game_type === 'duel') modifiers.bot_difficulty = this.modBotDifficulty;
      const hasHouseRules = Object.values(this.modHouseRules).some(v => v);
      if (hasHouseRules) modifiers.house_rules = { ...this.modHouseRules };
      payload.modifiers = Object.keys(modifiers).length ? modifiers : null;

      // XP config
      if (this.useCustomXp) {
        payload.xp_config = {
          base_xp: this.xpBase,
          win_bonus: this.xpWinBonus,
          online_multiplier: this.xpOnlineMultiplier,
        };
      } else {
        payload.xp_config = null;
      }

      // Clean empty theme_color
      if (!payload.theme_color) payload.theme_color = null;

      try {
        if (this.editing) {
          await axios.put(`/api/admin/rotating-events/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/rotating-events', payload);
        }
        this.showModal = false;
        await this.fetch();
      } catch (e) {
        this.toast.error(e.response?.data?.message || 'Failed to save');
      }
      this.saving = false;
    },
    async remove(e) {
      if (!confirm(`Delete "${e.name}"?`)) return;
      try {
        await axios.delete(`/api/admin/rotating-events/${e.id}`);
        await this.fetch();
      } catch {}
    },
    filterStatEffects(effects) {
      if (!effects) return {};
      const result = {};
      const specialKeys = ['grant_item_id', 'draw_item', 'recover_die', 'lose_die', 'discard_item', 'remove_curse'];
      for (const [key, val] of Object.entries(effects)) {
        if (!specialKeys.includes(key)) result[key] = val;
      }
      return result;
    },
    shortStat(stat) {
      const map = { wealth: 'WLT', influence: 'INF', security: 'SEC', religion: 'REL', food: 'FOD', happiness: 'HAP' };
      return map[stat] || stat.substring(0, 3).toUpperCase();
    },
    itemEffectLabel(item) {
      const e = item.effect;
      if (!e) return '?';
      const labels = {
        roll_bonus: `Roll ${e.bonus_value > 0 ? '+' : ''}${e.bonus_value}`,
        difficulty_reduction: `Diff -${e.bonus_value}`,
        stat_boost: `${e.stat ? this.shortStat(e.stat) : 'Stat'} +${e.bonus_value}`,
        heal_die: `Heal ${e.bonus_value} Die`,
        score_bonus: `Score +${e.bonus_value}`,
        shield_negative: `Shield ${e.bonus_value}x`,
        debuff_roll: `Roll ${e.bonus_value}`,
        increase_difficulty: `Diff +${e.bonus_value}`,
        peek_cards: `Peek ${e.bonus_value}`,
        steal_stat: `Steal ${e.bonus_value}`,
      };
      return labels[e.bonus_type] || e.bonus_type;
    },
    eventMechanicLabel(mechanic) {
      const labels = {
        stat_modifier: 'Stat Modifier',
        grant_items: 'Grant Items',
        reduce_dice: 'Reduce Dice',
        altered_deal: 'Altered Deal',
        score_event: 'Score Event',
      };
      return labels[mechanic] || mechanic;
    },
    eventMechanicClass(mechanic) {
      const neg = ['reduce_dice', 'stat_modifier'];
      const pos = ['grant_items', 'score_event'];
      if (neg.includes(mechanic)) return 'chip-neg';
      if (pos.includes(mechanic)) return 'chip-pos';
      return 'chip-special';
    },
    isActive(e) {
      if (!e.is_active) return false;
      const now = new Date();
      return new Date(e.starts_at) <= now && new Date(e.ends_at) >= now;
    },
    formatDateRange(start, end) {
      if (!start || !end) return '';
      const s = new Date(start).toLocaleDateString();
      const e = new Date(end).toLocaleDateString();
      return `${s} - ${e}`;
    },
    curseNegLabel(curse) {
      const e = curse.negative_effect;
      if (!e) return '?';
      const t = e.type;
      if (t === 'lose_die') return `Lose ${e.value || 1} die`;
      if (t === 'stat_per_round') return `${e.value} ${e.stat}/round`;
      if (t === 'difficulty_modifier') return `+${e.value} diff`;
      if (t === 'double_negative') return 'Double neg';
      return t;
    },
    cursePosLabel(curse) {
      const e = curse.positive_effect;
      if (!e) return '?';
      const t = e.type;
      if (t === 'xp_multiplier') return `${e.value}x XP`;
      if (t === 'stat_per_round') return `+${e.value} ${e.stat}/round`;
      if (t === 'auto_max_stat') return `Max ${e.count || 1} stat`;
      if (t === 'score_bonus') return `+${e.value} score`;
      if (t === 'opponent_difficulty') return `+${e.value} opp diff`;
      if (t === 'opponent_lose_die') return `Opp lose die`;
      return t;
    },
    truncate(str, len) {
      if (!str) return '';
      return str.length > len ? str.substring(0, len) + '...' : str;
    },
  },
};
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
