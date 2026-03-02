<template>
  <div class="leaderboard-page">
    <h2 class="section-title">Leaderboards</h2>

    <HintBubble hint-id="leaderboard-elo">
      Play <strong>online duels</strong> to earn ELO rating and climb the competitive rankings!
    </HintBubble>

    <!-- Tabs -->
    <div class="tab-row">
      <button class="tab-btn" :class="{ active: tab === 'global' }" @click="tab = 'global'; fetchData()">Global</button>
      <button class="tab-btn" :class="{ active: tab === 'friends' }" @click="tab = 'friends'; fetchData()">Friends</button>
    </div>

    <!-- Filters -->
    <div class="filters-row">
      <select v-model="metric" @change="fetchData()" class="filter-select">
        <option value="wins">Wins</option>
        <option value="score">Score</option>
        <option value="xp">XP</option>
        <option value="elo">ELO</option>
      </select>
      <select v-model="seasonId" @change="fetchData()" class="filter-select" v-if="metric !== 'elo' && metric !== 'xp'">
        <option :value="null">All Seasons</option>
        <option v-for="s in seasons" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="gameType" @change="fetchData()" class="filter-select" v-if="metric !== 'elo' && metric !== 'xp'">
        <option :value="null">All Types</option>
        <option value="cooperative">Cooperative</option>
        <option value="duel">Duel</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="lb-loading">Loading...</div>

    <!-- Empty -->
    <div v-else-if="entries.length === 0" class="lb-empty">No data yet.</div>

    <!-- Table -->
    <div v-else class="lb-table">
      <div
        v-for="entry in entries"
        :key="entry.user_id"
        class="lb-row"
        :class="{ 'lb-current': entry.is_current_user }"
        :ref="entry.is_current_user ? 'currentUserRow' : undefined"
      >
        <span class="lb-rank">{{ entry.rank }}</span>
        <span class="lb-name lb-clickable" @click="showProfileUserId = entry.user_id">
          {{ entry.username }}
          <span class="lb-level">Lv.{{ entry.level }}</span>
        </span>
        <span class="lb-value">{{ formatValue(entry.value) }}</span>
      </div>
    </div>

    <!-- Floating current player row (when scrolled out of view) -->
    <div v-if="currentUserEntry && !currentUserVisible" class="lb-float">
      <div class="lb-row lb-current lb-float-row">
        <span class="lb-rank">{{ currentUserEntry.rank }}</span>
        <span class="lb-name">
          {{ currentUserEntry.username }}
          <span class="lb-level">Lv.{{ currentUserEntry.level }}</span>
        </span>
        <span class="lb-value">{{ formatValue(currentUserEntry.value) }}</span>
      </div>
    </div>

    <PlayerProfile v-if="showProfileUserId" :userId="showProfileUserId" @close="showProfileUserId = null" />
  </div>
</template>

<script>
import axios from 'axios';
import HintBubble from './HintBubble.vue';
import PlayerProfile from './PlayerProfile.vue';

export default {
  name: 'LeaderboardPage',
  components: { HintBubble, PlayerProfile },
  data() {
    return {
      tab: 'global',
      showProfileUserId: null,
      metric: 'elo',
      seasonId: null,
      gameType: null,
      entries: [],
      seasons: [],
      loading: true,
      currentUserVisible: true,
    };
  },
  computed: {
    currentUserEntry() {
      return this.entries.find(e => e.is_current_user) || null;
    },
  },
  async mounted() {
    try {
      const res = await axios.get('/api/seasons');
      this.seasons = res.data;
    } catch {}
    this.fetchData();
  },
  beforeUnmount() {
    if (this._observer) this._observer.disconnect();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      try {
        const params = { metric: this.metric };
        if (this.seasonId) params.season_id = this.seasonId;
        if (this.gameType) params.game_type = this.gameType;

        const url = this.tab === 'friends' ? '/api/leaderboards/friends' : '/api/leaderboards/global';
        const res = await axios.get(url, { params });
        this.entries = res.data;
      } catch {}
      this.loading = false;
      this.$nextTick(() => this.setupVisibilityObserver());
    },
    setupVisibilityObserver() {
      if (this._observer) this._observer.disconnect();
      const row = Array.isArray(this.$refs.currentUserRow)
        ? this.$refs.currentUserRow[0]
        : this.$refs.currentUserRow;
      if (!row) {
        this.currentUserVisible = !this.currentUserEntry;
        return;
      }
      this._observer = new IntersectionObserver(
        ([entry]) => { this.currentUserVisible = entry.isIntersecting; },
        { threshold: 0.5 }
      );
      this._observer.observe(row);
    },
    formatValue(val) {
      if (val >= 1000) return (val / 1000).toFixed(1) + 'k';
      return val;
    },
  },
};
</script>

<style scoped>
.leaderboard-page {
  max-width: 600px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
  text-align: center;
}

.tab-row {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 12px;
}

.tab-btn {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--text-secondary);
  padding: 6px 20px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn.active {
  background: rgba(212, 168, 67, 0.15);
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.filters-row {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.filter-select {
  background: var(--bg-primary);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--text-bright);
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  font-family: inherit;
}

.lb-loading, .lb-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.lb-table {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.lb-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
}

.lb-row.lb-current {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.lb-rank {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  font-weight: 700;
  min-width: 30px;
  text-align: center;
}

.lb-name {
  flex: 1;
  color: var(--text-bright);
  font-size: 0.95rem;
}

.lb-clickable {
  cursor: pointer;
}

.lb-clickable:hover {
  color: var(--accent-gold);
  text-decoration: underline;
}

.lb-level {
  font-size: 0.7rem;
  color: var(--text-secondary);
  margin-left: 6px;
}

.lb-value {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  font-weight: 700;
}

/* Floating current user row */
.lb-float {
  position: sticky;
  bottom: 0;
  padding: 8px 0 0;
  background: linear-gradient(180deg, transparent, var(--bg-primary) 30%);
}

.lb-float-row {
  box-shadow: 0 -2px 12px rgba(212, 168, 67, 0.3);
  border-color: var(--accent-gold);
  animation: floatGlow 2s ease-in-out infinite;
}

@keyframes floatGlow {
  0%, 100% { box-shadow: 0 -2px 12px rgba(212, 168, 67, 0.2); }
  50% { box-shadow: 0 -2px 20px rgba(212, 168, 67, 0.4); }
}

@media (max-width: 768px) {
  .filters-row {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
