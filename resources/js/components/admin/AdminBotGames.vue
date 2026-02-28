<template>
  <div class="bot-games">
    <h2 class="page-title">Bot Game Simulation</h2>
    <p class="page-desc">Run headless bot games to test game balance. Bots pick the best card each round.</p>

    <div class="controls">
      <div class="control-group">
        <label>Games to Run</label>
        <input v-model.number="numGames" type="number" min="1" max="1000" />
      </div>
      <div class="control-group">
        <label>Players</label>
        <select v-model.number="numPlayers">
          <option :value="1">1 (Solo)</option>
          <option :value="2">2</option>
          <option :value="3">3</option>
          <option :value="4">4</option>
          <option :value="5">5</option>
          <option :value="6">6</option>
        </select>
      </div>
      <div class="control-group">
        <label>Rounds</label>
        <input v-model.number="totalRounds" type="number" min="1" max="48" />
      </div>
      <div class="control-group">
        <label>Starting Stats</label>
        <input v-model.number="startingStats" type="number" min="3" max="15" />
      </div>
      <div class="control-group">
        <label>Neg. Multiplier</label>
        <input v-model.number="negativeMultiplier" type="number" min="0.5" max="3.0" step="0.1" />
      </div>
      <button class="btn-primary run-btn" :disabled="running" @click="runSimulation">
        {{ running ? 'Simulating...' : 'Run Simulation' }}
      </button>
    </div>

    <div v-if="results" class="results">
      <!-- Summary -->
      <div class="result-section">
        <h3>Summary</h3>
        <div class="summary-grid">
          <div class="summary-card">
            <div class="summary-val" :class="winRateClass">{{ results.summary.win_rate }}%</div>
            <div class="summary-label">Win Rate</div>
          </div>
          <div class="summary-card">
            <div class="summary-val">{{ results.summary.wins }} / {{ results.summary.losses }}</div>
            <div class="summary-label">W / L</div>
          </div>
          <div class="summary-card">
            <div class="summary-val">{{ results.summary.avg_score }}</div>
            <div class="summary-label">Avg Score</div>
          </div>
          <div class="summary-card">
            <div class="summary-val">{{ results.summary.avg_rounds_survived }}</div>
            <div class="summary-label">Avg Rounds</div>
          </div>
        </div>
      </div>

      <!-- Average Final Stats -->
      <div class="result-section">
        <h3>Avg Final Stats</h3>
        <div class="stat-bars">
          <div v-for="(val, stat) in results.summary.avg_final_stats" :key="stat" class="stat-row">
            <span class="stat-name">{{ stat }}</span>
            <div class="stat-bar-bg">
              <div class="stat-bar" :style="{ width: (val / 20 * 100) + '%' }" :class="getBarClass(val)"></div>
            </div>
            <span class="stat-val">{{ val }}</span>
          </div>
        </div>
      </div>

      <!-- Collapse Reasons -->
      <div v-if="Object.keys(results.collapse_reasons).length > 0" class="result-section">
        <h3>Collapse Reasons ({{ results.summary.losses }} losses)</h3>
        <div class="collapse-list">
          <div v-for="(count, reason) in results.collapse_reasons" :key="reason" class="collapse-row">
            <span class="collapse-reason">{{ reason }}</span>
            <span class="collapse-count">{{ count }} ({{ Math.round(count / results.summary.losses * 100) }}%)</span>
          </div>
        </div>
      </div>

      <!-- Round-by-Round Averages -->
      <div class="result-section">
        <h3>Round Averages</h3>
        <div class="round-table-wrap">
          <table class="round-table">
            <thead>
              <tr>
                <th>Rnd</th>
                <th>Alive</th>
                <th>Win%</th>
                <th>Wlth</th>
                <th>Infl</th>
                <th>Sec</th>
                <th>Rel</th>
                <th>Food</th>
                <th>Hap</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in results.round_averages" :key="r.round">
                <td>{{ r.round }}</td>
                <td>{{ r.games_alive }}</td>
                <td>{{ r.success_rate }}%</td>
                <td :class="getCellClass(r.avg_wealth)">{{ r.avg_wealth }}</td>
                <td :class="getCellClass(r.avg_influence)">{{ r.avg_influence }}</td>
                <td :class="getCellClass(r.avg_security)">{{ r.avg_security }}</td>
                <td :class="getCellClass(r.avg_religion)">{{ r.avg_religion }}</td>
                <td :class="getCellClass(r.avg_food)">{{ r.avg_food }}</td>
                <td :class="getCellClass(r.avg_happiness)">{{ r.avg_happiness }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Score Distribution -->
      <div class="result-section">
        <h3>Score Distribution</h3>
        <div class="dist-bars">
          <div v-for="(count, bucket) in results.score_distribution" :key="'s' + bucket" class="dist-row">
            <span class="dist-label">{{ bucket }}-{{ parseInt(bucket) + 9 }}</span>
            <div class="dist-bar-bg">
              <div class="dist-bar" :style="{ width: (count / maxScoreCount * 100) + '%' }"></div>
            </div>
            <span class="dist-count">{{ count }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminBotGames',
  data() {
    return {
      numGames: 100,
      numPlayers: 3,
      totalRounds: 24,
      startingStats: 10,
      negativeMultiplier: 1.0,
      running: false,
      results: null,
    };
  },
  computed: {
    winRateClass() {
      if (!this.results) return '';
      const r = this.results.summary.win_rate;
      if (r >= 60) return 'rate-high';
      if (r >= 40) return 'rate-mid';
      return 'rate-low';
    },
    maxScoreCount() {
      if (!this.results) return 1;
      return Math.max(1, ...Object.values(this.results.score_distribution));
    },
  },
  methods: {
    async runSimulation() {
      this.running = true;
      this.results = null;
      try {
        const res = await axios.post('/api/admin/bot-simulate', {
          num_games: this.numGames,
          num_players: this.numPlayers,
          total_rounds: this.totalRounds,
          starting_stats: this.startingStats,
          negative_multiplier: this.negativeMultiplier,
        });
        this.results = res.data;
      } catch (e) {
        alert('Simulation failed: ' + (e.response?.data?.error || e.message));
      }
      this.running = false;
    },
    getBarClass(val) {
      if (val <= 3) return 'bar-critical';
      if (val <= 6) return 'bar-danger';
      if (val <= 9) return 'bar-caution';
      return 'bar-safe';
    },
    getCellClass(val) {
      if (val <= 3) return 'cell-critical';
      if (val <= 6) return 'cell-danger';
      if (val <= 9) return 'cell-caution';
      return '';
    },
  },
};
</script>

<style scoped>
.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  margin-bottom: 8px;
  font-size: 1.8rem;
}

.page-desc {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  margin-bottom: 24px;
}

.controls {
  display: flex;
  gap: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
  margin-bottom: 30px;
  padding: 16px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.control-group label {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.control-group input,
.control-group select {
  background: var(--bg-primary);
  border: 1px solid var(--border-gold);
  color: var(--text-bright);
  padding: 8px 12px;
  border-radius: 4px;
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1rem;
  width: 120px;
}

.run-btn {
  padding: 10px 24px;
  font-size: 1rem;
}

/* Results */
.results {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.result-section h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 12px;
}

/* Summary */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.summary-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 16px;
  text-align: center;
}

.summary-val {
  font-family: 'Cinzel', serif;
  font-size: 2rem;
  color: var(--text-bright);
  font-weight: 900;
}

.summary-label {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-top: 4px;
}

.rate-high { color: #e74c3c; }
.rate-mid { color: var(--accent-gold); }
.rate-low { color: #27ae60; }

/* Stat bars */
.stat-bars {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-name {
  width: 80px;
  text-transform: capitalize;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.stat-bar-bg {
  flex: 1;
  height: 10px;
  background: rgba(0, 0, 0, 0.4);
  border-radius: 5px;
  overflow: hidden;
}

.stat-bar {
  height: 100%;
  border-radius: 5px;
  transition: width 0.3s ease;
}

.bar-critical { background: #e74c3c; }
.bar-danger { background: #e67e22; }
.bar-caution { background: #d4a843; }
.bar-safe { background: #27ae60; }

.stat-val {
  width: 35px;
  text-align: right;
  font-weight: 700;
  color: var(--text-bright);
}

/* Collapse reasons */
.collapse-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.collapse-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 10px;
  background: rgba(160, 48, 32, 0.08);
  border-radius: 4px;
}

.collapse-reason {
  text-transform: capitalize;
  color: var(--text-primary);
}

.collapse-count {
  color: var(--accent-red);
  font-weight: 600;
}

/* Round table */
.round-table-wrap {
  overflow-x: auto;
}

.round-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.round-table th {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.75rem;
  text-transform: uppercase;
  padding: 6px 8px;
  border-bottom: 2px solid var(--border-gold);
  text-align: center;
}

.round-table td {
  padding: 4px 8px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
  text-align: center;
  color: var(--text-primary);
}

.cell-critical { color: #e74c3c; font-weight: 700; }
.cell-danger { color: #e67e22; }
.cell-caution { color: #d4a843; }

/* Score distribution */
.dist-bars {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dist-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dist-label {
  width: 55px;
  font-size: 0.85rem;
  color: var(--text-secondary);
  text-align: right;
}

.dist-bar-bg {
  flex: 1;
  height: 14px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 3px;
  overflow: hidden;
}

.dist-bar {
  height: 100%;
  background: var(--accent-gold);
  border-radius: 3px;
  transition: width 0.3s ease;
}

.dist-count {
  width: 35px;
  font-size: 0.85rem;
  color: var(--text-bright);
  font-weight: 600;
}
</style>
