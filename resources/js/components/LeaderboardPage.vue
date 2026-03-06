<template>
  <div class="leaderboard-page">
    <h2 class="section-title">Leaderboards</h2>

    <!-- Current Season Banner -->
    <div v-if="activeSeason" class="season-banner">
      <div class="season-banner-top">
        <span class="season-banner-name">{{ activeSeason.name }}</span>
        <span class="season-banner-dates">{{ formatDate(activeSeason.starts_at) }} &ndash; {{ formatDate(activeSeason.ends_at) }}</span>
      </div>
      <div class="season-banner-bar">
        <div class="season-banner-fill" :style="{ width: seasonPercent + '%' }"></div>
      </div>
      <div class="season-banner-time">{{ seasonTimeLeft }}</div>
    </div>

    <HintBubble hint-id="leaderboard-elo">
      Play <strong>online duels</strong> to earn ELO rating and climb the competitive rankings!
    </HintBubble>

    <!-- Tabs -->
    <div class="tab-row">
      <button class="tab-btn" :class="{ active: tab === 'global' }" @click="tab = 'global'; fetchData()">Global</button>
      <button class="tab-btn" :class="{ active: tab === 'friends' }" @click="tab = 'friends'; fetchData()">Friends</button>
      <button class="tab-btn" :class="{ active: tab === 'rewards' }" @click="tab = 'rewards'; fetchRewards()">Rewards</button>
    </div>

    <!-- Leaderboard view -->
    <template v-if="tab !== 'rewards'">
      <!-- Filters -->
      <div class="filters-row">
        <select v-model="metric" @change="onMetricChange" class="filter-select">
          <option value="wins">Wins</option>
          <option value="score">Score</option>
          <option value="xp">XP</option>
          <option value="elo">ELO</option>
        </select>
        <select v-model="seasonId" @change="fetchData()" class="filter-select" v-if="metric !== 'elo' && metric !== 'xp'">
          <option :value="null">All Seasons</option>
          <option v-for="s in seasons" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="gameType" @change="fetchData()" class="filter-select" v-if="metric === 'wins'">
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
    </template>

    <!-- Ranking Rewards view -->
    <template v-if="tab === 'rewards'">
      <div v-if="loadingRewards" class="lb-loading">Loading rewards...</div>
      <div v-else-if="!Object.keys(rewardsByMetric).length" class="lb-empty">No ranking rewards set for this season.</div>
      <div v-else>
        <!-- Metric sub-tabs -->
        <div class="metric-tabs">
          <button
            v-for="m in rewardMetrics"
            :key="m"
            class="metric-tab"
            :class="{ active: rewardMetric === m }"
            @click="rewardMetric = m"
          >{{ metricLabel(m) }}</button>
        </div>

        <!-- Reward cards for selected metric -->
        <div v-if="rewardsByMetric[rewardMetric]" class="rewards-grid">
          <div
            v-for="tier in rewardsByMetric[rewardMetric]"
            :key="rewardMetric + '-' + tier.label"
            class="reward-card"
            :class="tier.tierClass"
          >
            <div class="reward-placement">{{ tier.label }}</div>
            <div class="reward-details">
              <span v-if="tier.xp" class="reward-item reward-xp">+{{ tier.xp }} XP</span>
              <span v-if="tier.coins" class="reward-item reward-coins">+{{ tier.coins }} &#129689;</span>
              <span v-if="tier.character" class="reward-item reward-char">&#128081; {{ tier.character }}</span>
              <span v-if="tier.title" class="reward-item reward-title">&#127941; "{{ tier.title }}"</span>
            </div>
          </div>
        </div>
      </div>
    </template>

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
      loadingRewards: false,
      rewardsByMetric: {},
      rewardMetric: 'elo',
    };
  },
  computed: {
    currentUserEntry() {
      return this.entries.find(e => e.is_current_user) || null;
    },
    rewardMetrics() {
      return Object.keys(this.rewardsByMetric);
    },
    activeSeason() {
      const now = new Date();
      return this.seasons.find(s => new Date(s.starts_at) <= now && new Date(s.ends_at) >= now) || null;
    },
    seasonPercent() {
      if (!this.activeSeason) return 0;
      const start = new Date(this.activeSeason.starts_at).getTime();
      const end = new Date(this.activeSeason.ends_at).getTime();
      const now = Date.now();
      return Math.min(100, Math.max(0, ((now - start) / (end - start)) * 100));
    },
    seasonTimeLeft() {
      if (!this.activeSeason) return '';
      const end = new Date(this.activeSeason.ends_at).getTime();
      const diff = end - Date.now();
      if (diff <= 0) return 'Ended';
      const days = Math.floor(diff / 86400000);
      if (days > 1) return days + ' days left';
      const hours = Math.floor(diff / 3600000);
      return hours + ' hours left';
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
    onMetricChange() {
      if (this.metric === 'score') {
        if (this.activeSeason) {
          this.seasonId = this.activeSeason.id;
        }
        this.gameType = 'cooperative';
      } else if (this.metric === 'elo' || this.metric === 'xp') {
        this.seasonId = null;
        this.gameType = null;
      }
      this.fetchData();
    },
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
    async fetchRewards() {
      if (!this.activeSeason) return;
      this.loadingRewards = true;
      try {
        const res = await axios.get(`/api/seasons/${this.activeSeason.id}`);
        const rewards = res.data.season?.rewards || [];
        this.rewardsByMetric = this.buildGroupedTiers(rewards);
        const metrics = Object.keys(this.rewardsByMetric);
        if (metrics.length && !metrics.includes(this.rewardMetric)) {
          this.rewardMetric = metrics[0];
        }
      } catch {}
      this.loadingRewards = false;
    },
    buildGroupedTiers(rewards) {
      if (!rewards.length) return {};

      // Group by metric first
      const byMetric = {};
      for (const r of rewards) {
        const m = r.metric || 'elo';
        if (!byMetric[m]) byMetric[m] = [];
        byMetric[m].push(r);
      }

      // Build tiers per metric
      const result = {};
      for (const [metric, items] of Object.entries(byMetric)) {
        items.sort((a, b) => a.placement - b.placement);
        result[metric] = this.buildTiers(items);
      }
      return result;
    },
    buildTiers(rewards) {
      const tiers = [];
      let i = 0;
      while (i < rewards.length) {
        const current = rewards[i];
        let end = i;
        while (
          end + 1 < rewards.length &&
          rewards[end + 1].reward_xp === current.reward_xp &&
          rewards[end + 1].reward_coins === current.reward_coins &&
          rewards[end + 1].reward_character_id === current.reward_character_id &&
          rewards[end + 1].reward_title === current.reward_title
        ) {
          end++;
        }

        const startPlace = current.placement;
        const endPlace = rewards[end].placement;
        const label = startPlace === endPlace
          ? this.ordinal(startPlace)
          : `${this.ordinal(startPlace)} - ${this.ordinal(endPlace)}`;

        let tierClass = '';
        if (startPlace === 1) tierClass = 'reward-gold';
        else if (startPlace <= 3) tierClass = 'reward-silver';
        else if (startPlace <= 10) tierClass = 'reward-bronze';

        tiers.push({
          label,
          xp: current.reward_xp || 0,
          coins: current.reward_coins || 0,
          character: current.reward_character?.name || null,
          title: current.reward_title || null,
          tierClass,
        });

        i = end + 1;
      }
      return tiers;
    },
    metricLabel(m) {
      const labels = { elo: 'ELO Rating', score: 'Highest Score', wins: 'Most Wins' };
      return labels[m] || m;
    },
    ordinal(n) {
      const s = ['th', 'st', 'nd', 'rd'];
      const v = n % 100;
      return n + (s[(v - 20) % 10] || s[v] || s[0]);
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
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
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

/* Season Banner */
.season-banner {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 14px;
}

.season-banner-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.season-banner-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  font-weight: 700;
}

.season-banner-dates {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.season-banner-bar {
  width: 100%;
  height: 5px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 4px;
}

.season-banner-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 3px;
  transition: width 0.5s ease;
}

.season-banner-time {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-align: right;
}

.tab-row {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 12px;
}

.tab-btn {
  padding: 6px 16px;
  font-size: 0.8rem;
}

.tab-btn.active {
  background: var(--accent-gold);
  border-color: var(--accent-gold);
  color: black;
  box-shadow: 0 4px 0 #7a5a14, inset 0 1px 0 rgba(255,255,255,0.2);
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
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
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

/* Ranking Rewards */
.metric-tabs {
  display: flex;
  gap: 6px;
  justify-content: center;
  margin-bottom: 14px;
}

.metric-tab {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.25);
  color: var(--text-secondary);
  padding: 5px 16px;
  border-radius: 5px;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s;
}

.metric-tab.active {
  background: rgba(212, 168, 67, 0.12);
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.rewards-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: center;
}

.reward-card {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid rgba(138, 106, 46, 0.25);
  border-radius: 10px;
  padding: 16px 20px;
  min-width: 140px;
  flex: 1;
  max-width: 200px;
  text-align: center;
}

.reward-card.reward-gold {
  border-color: #d4a843;
  background: linear-gradient(180deg, #3a2a10, #1a1209);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.reward-card.reward-silver {
  border-color: #a0a0a0;
  background: linear-gradient(180deg, #2a2a2a, #1a1209);
}

.reward-card.reward-bronze {
  border-color: #8a5c2e;
}

.reward-placement {
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--accent-gold);
  margin-bottom: 10px;
}

.reward-gold .reward-placement {
  font-size: 1.3rem;
}

.reward-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.reward-item {
  font-size: 0.85rem;
  color: var(--text-bright);
}

.reward-xp {
  color: #7ec8e3;
}

.reward-coins {
  color: #d4a843;
}

.reward-char {
  color: #e0b0ff;
}

.reward-title {
  color: #90ee90;
  font-style: italic;
}

@media (max-width: 768px) {
  .filters-row {
    flex-direction: column;
    align-items: stretch;
  }

  .reward-card {
    min-width: 120px;
    padding: 12px 14px;
  }

  .season-banner-top {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }
}
</style>
