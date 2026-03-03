<template>
  <div class="stats-page">
    <h2 class="page-title">Statistics</h2>

    <div v-if="loading" class="loading">Loading stats...</div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card">
          <span class="summary-value">{{ overview.total_games }}</span>
          <span class="summary-label">Games</span>
        </div>
        <div class="summary-card">
          <span class="summary-value win-color">{{ overview.win_rate }}%</span>
          <span class="summary-label">Win Rate</span>
        </div>
        <div class="summary-card">
          <span class="summary-value">{{ overview.best_score }}</span>
          <span class="summary-label">Best Score</span>
        </div>
        <div class="summary-card">
          <span class="summary-value">{{ overview.current_elo }}</span>
          <span class="summary-label">ELO</span>
        </div>
      </div>

      <!-- Streaks -->
      <div class="card-panel">
        <h3 class="sub-title">Streaks &amp; Records</h3>
        <div class="records-grid">
          <div class="record-item">
            <span class="record-value">{{ overview.current_streak }}</span>
            <span class="record-label">Current Win Streak</span>
          </div>
          <div class="record-item">
            <span class="record-value">{{ overview.best_streak }}</span>
            <span class="record-label">Best Win Streak</span>
          </div>
          <div class="record-item">
            <span class="record-value">{{ overview.highest_elo }}</span>
            <span class="record-label">Peak ELO</span>
          </div>
          <div class="record-item">
            <span class="record-value">{{ overview.favorite_character || '-' }}</span>
            <span class="record-label">Favorite Advisor</span>
          </div>
        </div>
      </div>

      <!-- Win Rate by Mode -->
      <div class="card-panel">
        <h3 class="sub-title">Win Rate by Mode</h3>
        <div class="mode-bars">
          <div v-for="(data, mode) in overview.by_mode" :key="mode" class="mode-bar-row">
            <span class="mode-bar-label">{{ modeLabel(mode) }}</span>
            <div class="mode-bar-track">
              <div class="mode-bar-fill" :style="{ width: winRate(data) + '%' }"></div>
            </div>
            <span class="mode-bar-stat">{{ data.wins }}/{{ data.games }} ({{ winRate(data) }}%)</span>
          </div>
        </div>
      </div>

      <!-- Win Rate by Type -->
      <div class="card-panel">
        <h3 class="sub-title">Win Rate by Type</h3>
        <div class="mode-bars">
          <div v-for="(data, type) in overview.by_type" :key="type" class="mode-bar-row">
            <span class="mode-bar-label">{{ type === 'duel' ? 'Duel' : 'Classic' }}</span>
            <div class="mode-bar-track">
              <div class="mode-bar-fill" :style="{ width: winRate(data) + '%' }"></div>
            </div>
            <span class="mode-bar-stat">{{ data.wins }}/{{ data.games }} ({{ winRate(data) }}%)</span>
          </div>
        </div>
      </div>

      <!-- ELO History Chart -->
      <div v-if="history.elo_history && history.elo_history.length > 1" class="card-panel">
        <h3 class="sub-title">ELO History</h3>
        <div class="chart-wrap">
          <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="chart-svg">
            <!-- Grid lines -->
            <line v-for="i in 4" :key="'g'+i"
              :x1="chartPad" :x2="chartWidth - chartPad"
              :y1="chartPad + (i-1) * (chartInnerH / 3)"
              :y2="chartPad + (i-1) * (chartInnerH / 3)"
              stroke="rgba(138,106,46,0.15)" stroke-width="1"
            />
            <!-- Line -->
            <polyline :points="eloPoints" fill="none" stroke="#d4a843" stroke-width="2" stroke-linejoin="round" />
            <!-- Dots -->
            <circle v-for="(pt, i) in eloCoords" :key="i" :cx="pt.x" :cy="pt.y" r="3" fill="#d4a843" />
            <!-- Labels -->
            <text :x="chartPad" :y="chartHeight - 2" fill="rgba(200,180,140,0.5)" font-size="9">{{ history.elo_history[0]?.date }}</text>
            <text :x="chartWidth - chartPad" :y="chartHeight - 2" fill="rgba(200,180,140,0.5)" font-size="9" text-anchor="end">{{ history.elo_history[history.elo_history.length-1]?.date }}</text>
          </svg>
        </div>
      </div>

      <!-- Activity Chart (last 30 days) -->
      <div v-if="history.activity" class="card-panel">
        <h3 class="sub-title">Activity (Last 30 Days)</h3>
        <div class="chart-wrap">
          <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="chart-svg">
            <rect
              v-for="(day, i) in history.activity" :key="i"
              :x="chartPad + i * barWidth"
              :y="chartPad + chartInnerH - (day.count / maxActivity * chartInnerH)"
              :width="Math.max(barWidth - 2, 1)"
              :height="day.count / maxActivity * chartInnerH || 1"
              fill="rgba(212,168,67,0.5)"
              rx="1"
            />
          </svg>
        </div>
      </div>

      <!-- Character Stats -->
      <div v-if="characters.length > 0" class="card-panel">
        <h3 class="sub-title">Advisor Stats</h3>
        <div class="char-stats-list">
          <div v-for="c in characters" :key="c.character_id" class="char-stat-row">
            <img :src="c.image_url || '/images/character.png'" class="char-stat-img" />
            <div class="char-stat-info">
              <span class="char-stat-name">{{ c.name }}</span>
              <span class="char-stat-detail">{{ c.games }} games, {{ c.wins }}W {{ c.losses }}L</span>
            </div>
            <div class="char-stat-rate">
              <span class="rate-value">{{ c.win_rate }}%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Achievements Progress -->
      <div class="card-panel">
        <h3 class="sub-title">Achievements</h3>
        <div class="ach-progress">
          <div class="ach-bar-track">
            <div class="ach-bar-fill" :style="{ width: achievementPercent + '%' }"></div>
          </div>
          <span class="ach-text">{{ achievementsEarned }} / {{ achievementsTotal }} unlocked ({{ achievementPercent }}%)</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'StatsPage',
  data() {
    return {
      overview: {},
      history: {},
      characters: [],
      achievementsEarned: 0,
      achievementsTotal: 0,
      loading: true,
      chartWidth: 320,
      chartHeight: 150,
      chartPad: 16,
    };
  },
  computed: {
    chartInnerH() { return this.chartHeight - this.chartPad * 2; },
    chartInnerW() { return this.chartWidth - this.chartPad * 2; },
    eloValues() { return (this.history.elo_history || []).map(h => h.elo); },
    eloMin() { return Math.min(...this.eloValues) - 20; },
    eloMax() { return Math.max(...this.eloValues) + 20; },
    eloCoords() {
      const vals = this.eloValues;
      if (vals.length < 2) return [];
      const range = this.eloMax - this.eloMin || 1;
      return vals.map((v, i) => ({
        x: this.chartPad + (i / (vals.length - 1)) * this.chartInnerW,
        y: this.chartPad + this.chartInnerH - ((v - this.eloMin) / range) * this.chartInnerH,
      }));
    },
    eloPoints() { return this.eloCoords.map(p => `${p.x},${p.y}`).join(' '); },
    maxActivity() { return Math.max(...(this.history.activity || []).map(d => d.count), 1); },
    barWidth() { return this.chartInnerW / 30; },
    achievementPercent() {
      if (!this.achievementsTotal) return 0;
      return Math.round(this.achievementsEarned / this.achievementsTotal * 100);
    },
  },
  async mounted() {
    this.loading = true;
    const [overviewRes, historyRes, charsRes, achRes] = await Promise.allSettled([
      axios.get('/api/stats/overview'),
      axios.get('/api/stats/history'),
      axios.get('/api/stats/characters'),
      axios.get('/api/achievements'),
    ]);
    this.overview = overviewRes.status === 'fulfilled' ? overviewRes.value.data : {};
    this.history = historyRes.status === 'fulfilled' ? historyRes.value.data : {};
    this.characters = charsRes.status === 'fulfilled' ? charsRes.value.data : [];
    if (achRes.status === 'fulfilled') {
      const achs = achRes.value.data;
      this.achievementsTotal = achs.length;
      this.achievementsEarned = achs.filter(a => a.earned).length;
    }
    this.loading = false;
  },
  methods: {
    modeLabel(mode) {
      const labels = { single: 'Solo', pass_and_play: 'Local', online: 'Online' };
      return labels[mode] || mode;
    },
    winRate(data) {
      if (!data.games) return 0;
      return Math.round(data.wins / data.games * 100);
    },
  },
};
</script>

<style scoped>
.stats-page {
  max-width: 600px;
  margin: 0 auto;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
  text-align: center;
  margin-bottom: 16px;
}

.sub-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 40px 0;
}

/* Summary Grid */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 16px;
}

.summary-card {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 8px;
  padding: 14px 8px;
  text-align: center;
}

.summary-value {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.4rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.summary-label {
  display: block;
  font-size: 0.7rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

.win-color { color: #6abf50; }

/* Records */
.records-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.record-item {
  text-align: center;
  padding: 10px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 6px;
}

.record-value {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.record-label {
  display: block;
  font-size: 0.7rem;
  color: var(--text-secondary);
  margin-top: 4px;
}

/* Mode bars */
.mode-bars {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mode-bar-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.mode-bar-label {
  min-width: 50px;
  font-size: 0.85rem;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
}

.mode-bar-track {
  flex: 1;
  height: 12px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 6px;
  overflow: hidden;
}

.mode-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 6px;
  transition: width 0.5s ease;
}

.mode-bar-stat {
  min-width: 80px;
  text-align: right;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

/* Charts */
.chart-wrap {
  overflow: hidden;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.15);
  padding: 4px;
}

.chart-svg {
  width: 100%;
  height: auto;
}

/* Character stats */
.char-stats-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.char-stat-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 6px;
}

.char-stat-img {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid rgba(138, 106, 46, 0.3);
  flex-shrink: 0;
}

.char-stat-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.char-stat-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
}

.char-stat-detail {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.char-stat-rate {
  text-align: right;
}

.rate-value {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  font-weight: 700;
}

/* Achievements progress */
.ach-progress {
  text-align: center;
}

.ach-bar-track {
  width: 100%;
  height: 14px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 7px;
  overflow: hidden;
  margin-bottom: 8px;
}

.ach-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 7px;
  transition: width 0.5s ease;
}

.ach-text {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

@media (max-width: 768px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
