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
      <input type="date" v-model="filters.date_from" @change="loadTab" />
      <input type="date" v-model="filters.date_to" @change="loadTab" />
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="{ active: tab === 'cards' }" @click="tab = 'cards'; loadTab()">Cards</button>
      <button :class="{ active: tab === 'characters' }" @click="tab = 'characters'; loadTab()">Characters</button>
      <button :class="{ active: tab === 'stats' }" @click="tab = 'stats'; loadTab()">Stats</button>
    </div>

    <!-- Cards Tab -->
    <div v-if="tab === 'cards'" class="table-wrap">
      <table>
        <thead>
          <tr>
            <th @click="sortBy('title')" class="sortable">Title</th>
            <th @click="sortBy('difficulty')" class="sortable">Diff</th>
            <th @click="sortBy('category')" class="sortable">Category</th>
            <th @click="sortBy('appearances')" class="sortable">Played</th>
            <th @click="sortBy('success_count')" class="sortable">Successes</th>
            <th @click="sortBy('success_rate')" class="sortable">Rate %</th>
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
            <th @click="sortBy('name')" class="sortable">Name</th>
            <th @click="sortBy('pick_count')" class="sortable">Picks</th>
            <th @click="sortBy('win_count')" class="sortable">Wins</th>
            <th @click="sortBy('win_rate')" class="sortable">Win Rate %</th>
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

    <!-- Stats Tab -->
    <div v-if="tab === 'stats'">
      <div class="stats-cards">
        <div v-for="(data, source) in statData" :key="source" class="stat-card" v-if="data">
          <h4>{{ source === 'cooperative' ? 'Cooperative' : 'Duel' }} ({{ data.game_count || 0 }} games)</h4>
          <div class="stat-bars">
            <div v-for="stat in ['wealth', 'influence', 'security', 'religion', 'food', 'happiness']" :key="stat" class="stat-bar-row">
              <span class="stat-label">{{ stat }}</span>
              <div class="bar-track">
                <div class="bar-fill" :style="{ width: ((data['avg_' + stat] || 0) / 20 * 100) + '%' }"></div>
              </div>
              <span class="stat-value">{{ data['avg_' + stat] || 0 }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card Detail Modal -->
    <div v-if="selectedCard" class="modal-overlay" @click.self="selectedCard = null">
      <div class="modal-content detail-modal">
        <button class="modal-close" @click="selectedCard = null">&times;</button>
        <div class="detail-header">
          <h3>{{ selectedCard.title }}</h3>
          <div class="detail-badges">
            <span class="badge">Difficulty: {{ selectedCard.difficulty }}</span>
            <span class="badge" v-if="selectedCard.category">{{ selectedCard.category }}</span>
          </div>
        </div>

        <p class="detail-description" v-if="selectedCard.description">{{ selectedCard.description }}</p>

        <!-- Balance Stats -->
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

        <!-- Effects -->
        <div class="effects-grid" v-if="selectedCard.positive_effects || selectedCard.negative_effects">
          <div class="effect-block" v-if="hasEffects(selectedCard.positive_effects)">
            <h4 class="effect-title success">On Success</h4>
            <p class="flavor-text" v-if="selectedCard.positive_flavor">{{ selectedCard.positive_flavor }}</p>
            <div class="effect-list">
              <div v-for="(val, key) in filteredEffects(selectedCard.positive_effects)" :key="key" class="effect-item">
                <span class="effect-key">{{ formatEffectKey(key) }}</span>
                <span class="effect-val" :class="val > 0 ? 'positive' : 'negative'">{{ val > 0 ? '+' : '' }}{{ val }}</span>
              </div>
            </div>
          </div>
          <div class="effect-block" v-if="hasEffects(selectedCard.negative_effects)">
            <h4 class="effect-title failure">On Failure</h4>
            <p class="flavor-text" v-if="selectedCard.negative_flavor">{{ selectedCard.negative_flavor }}</p>
            <div class="effect-list">
              <div v-for="(val, key) in filteredEffects(selectedCard.negative_effects)" :key="key" class="effect-item">
                <span class="effect-key">{{ formatEffectKey(key) }}</span>
                <span class="effect-val" :class="val > 0 ? 'positive' : 'negative'">{{ val > 0 ? '+' : '' }}{{ val }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Availability -->
        <div class="availability-row">
          <span :class="['avail-tag', selectedCard.available_cooperative ? 'active' : 'inactive']">Cooperative</span>
          <span :class="['avail-tag', selectedCard.available_duel ? 'active' : 'inactive']">Duel</span>
        </div>

        <div class="detail-actions">
          <router-link :to="'/admin/cards'" class="btn-edit" @click.native="selectedCard = null">Edit in Card Manager</router-link>
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
                <span class="badge" v-if="selectedCharacter.wild_ability">{{ selectedCharacter.wild_ability }}</span>
              </div>
            </div>
          </div>
        </div>

        <p class="detail-description" v-if="selectedCharacter.description">{{ selectedCharacter.description }}</p>

        <p class="wild-desc" v-if="selectedCharacter.wild_ability_description">
          <strong>Wild Ability:</strong> {{ selectedCharacter.wild_ability_description }}
        </p>

        <!-- Balance Stats -->
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

        <!-- Dice -->
        <div class="dice-section" v-if="selectedCharacter.dice">
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

        <!-- Availability -->
        <div class="availability-row">
          <span :class="['avail-tag', selectedCharacter.available_cooperative ? 'active' : 'inactive']">Cooperative</span>
          <span :class="['avail-tag', selectedCharacter.available_duel ? 'active' : 'inactive']">Duel</span>
        </div>

        <div class="detail-actions">
          <router-link :to="'/admin/characters'" class="btn-edit" @click.native="selectedCharacter = null">Edit in Character Manager</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminBalance',
  data() {
    return {
      tab: 'cards',
      filters: { game_mode: '', game_type: '', date_from: '', date_to: '' },
      cards: [],
      characters: [],
      statData: {},
      sortKey: '',
      sortAsc: true,
      selectedCard: null,
      selectedCardStats: {},
      selectedCharacter: null,
      selectedCharStats: {},
    };
  },
  computed: {
    sortedCards() {
      return this.sorted(this.cards);
    },
    sortedCharacters() {
      return this.sorted(this.characters);
    },
  },
  async mounted() {
    await this.loadTab();
  },
  methods: {
    async loadTab() {
      const params = {};
      Object.entries(this.filters).forEach(([k, v]) => { if (v) params[k] = v; });

      try {
        if (this.tab === 'cards') {
          const res = await axios.get('/api/admin/balance/cards', { params });
          this.cards = res.data;
        } else if (this.tab === 'characters') {
          const res = await axios.get('/api/admin/balance/characters', { params });
          this.characters = res.data;
        } else {
          const res = await axios.get('/api/admin/balance/stats', { params });
          this.statData = res.data;
        }
      } catch {}
    },
    async openCardModal(cardStats) {
      this.selectedCardStats = cardStats;
      try {
        const res = await axios.get(`/api/admin/cards/${cardStats.id}`);
        this.selectedCard = res.data;
      } catch {
        this.selectedCard = { title: cardStats.title, difficulty: cardStats.difficulty, category: cardStats.category };
      }
    },
    async openCharacterModal(charStats) {
      this.selectedCharStats = charStats;
      try {
        const res = await axios.get(`/api/admin/characters/${charStats.id}`);
        this.selectedCharacter = res.data;
      } catch {
        this.selectedCharacter = { name: charStats.name };
      }
    },
    hasEffects(effects) {
      if (!effects) return false;
      return Object.values(effects).some(v => v !== 0 && v !== false && v !== null);
    },
    filteredEffects(effects) {
      if (!effects) return {};
      const filtered = {};
      for (const [k, v] of Object.entries(effects)) {
        if (v !== 0 && v !== false && v !== null) filtered[k] = v;
      }
      return filtered;
    },
    formatEffectKey(key) {
      return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
    sortBy(key) {
      if (this.sortKey === key) {
        this.sortAsc = !this.sortAsc;
      } else {
        this.sortKey = key;
        this.sortAsc = true;
      }
    },
    sorted(arr) {
      if (!this.sortKey) return arr;
      return [...arr].sort((a, b) => {
        const va = a[this.sortKey], vb = b[this.sortKey];
        const cmp = typeof va === 'string' ? va.localeCompare(vb) : (va ?? 0) - (vb ?? 0);
        return this.sortAsc ? cmp : -cmp;
      });
    },
    rateClass(rate) {
      if (rate >= 70) return 'rate-high';
      if (rate >= 40) return 'rate-mid';
      return 'rate-low';
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }

.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-bar select, .filter-bar input { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 5px 8px; border-radius: 4px; font-family: inherit; font-size: 0.85rem; }

.tabs { display: flex; gap: 4px; margin-bottom: 16px; }
.tabs button { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); color: var(--text-secondary); padding: 6px 16px; border-radius: 4px; cursor: pointer; font-family: inherit; }
.tabs button.active { color: var(--accent-gold); border-color: var(--accent-gold); background: rgba(212, 168, 67, 0.1); }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
th { text-align: left; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.3); }
th.sortable { cursor: pointer; }
th.sortable:hover { color: var(--accent-gold); }
td { padding: 8px 10px; border-bottom: 1px solid rgba(138, 106, 46, 0.1); color: var(--text-bright); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

.clickable-row { cursor: pointer; transition: background 0.15s; }
.clickable-row:hover { background: rgba(212, 168, 67, 0.08); }

.rate-high { color: #2ecc71; font-weight: 600; }
.rate-mid { color: #f1c40f; font-weight: 600; }
.rate-low { color: #e74c3c; font-weight: 600; }

.stats-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 768px) { .stats-cards { grid-template-columns: 1fr; } }
.stat-card { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 8px; padding: 16px; }
.stat-card h4 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 12px; font-size: 1rem; }
.stat-bars { display: flex; flex-direction: column; gap: 8px; }
.stat-bar-row { display: flex; align-items: center; gap: 8px; }
.stat-label { width: 70px; font-size: 0.82rem; color: var(--text-secondary); text-transform: capitalize; }
.bar-track { flex: 1; height: 14px; background: rgba(0,0,0,0.3); border-radius: 7px; overflow: hidden; }
.bar-fill { height: 100%; background: linear-gradient(90deg, var(--accent-gold), #e6c54a); border-radius: 7px; transition: width 0.3s; }
.stat-value { width: 36px; text-align: right; font-size: 0.85rem; color: var(--text-bright); font-weight: 600; }

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

.detail-stats { display: flex; gap: 12px; margin-bottom: 16px; }
.detail-stat { flex: 1; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px; text-align: center; }
.detail-stat-label { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 4px; }
.detail-stat-value { display: block; font-size: 1.2rem; font-weight: 700; color: var(--text-bright); }

.effects-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
@media (max-width: 480px) { .effects-grid { grid-template-columns: 1fr; } }
.effect-block { background: rgba(0,0,0,0.15); border-radius: 8px; padding: 12px; }
.effect-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 6px; }
.effect-title.success { color: #2ecc71; }
.effect-title.failure { color: #e74c3c; }
.flavor-text { font-size: 0.78rem; color: var(--text-secondary); font-style: italic; margin: 0 0 8px; }
.effect-list { display: flex; flex-direction: column; gap: 3px; }
.effect-item { display: flex; justify-content: space-between; font-size: 0.82rem; }
.effect-key { color: var(--text-secondary); text-transform: capitalize; }
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
