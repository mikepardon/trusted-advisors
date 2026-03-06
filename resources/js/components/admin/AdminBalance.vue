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
          <tr v-for="card in sortedCards" :key="card.id">
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
          <tr v-for="ch in sortedCharacters" :key="ch.id">
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
            <div v-for="stat in ['strength', 'wisdom', 'morale', 'defence']" :key="stat" class="stat-bar-row">
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
</style>
