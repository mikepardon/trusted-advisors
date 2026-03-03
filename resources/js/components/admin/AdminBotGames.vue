<template>
  <div class="bot-games">
    <h2 class="page-title">Bot Game Simulation</h2>
    <p class="page-desc">Run headless bot games to test game balance. Bots pick the best card each round.</p>

    <!-- Tab Switcher -->
    <div class="tab-switcher">
      <button class="tab-btn" :class="{ active: mode === 'cooperative' }" @click="mode = 'cooperative'; results = null; duelResults = null">Cooperative</button>
      <button class="tab-btn" :class="{ active: mode === 'duel' }" @click="mode = 'duel'; results = null; duelResults = null">Duel</button>
    </div>

    <!-- Cooperative Controls -->
    <div v-if="mode === 'cooperative'" class="controls">
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

    <!-- Duel Controls -->
    <div v-if="mode === 'duel'" class="controls">
      <div class="control-group">
        <label>Games to Run</label>
        <input v-model.number="duelNumGames" type="number" min="1" max="1000" />
      </div>
      <div class="control-group">
        <label>Starting Stats</label>
        <input v-model.number="duelStartingStats" type="number" min="3" max="15" />
      </div>
      <div class="control-group">
        <label>Bot Difficulty</label>
        <select v-model="duelBotDifficulty">
          <option value="easy">Easy</option>
          <option value="medium">Medium</option>
          <option value="hard">Hard</option>
        </select>
      </div>
      <button class="btn-primary run-btn" :disabled="running" @click="runDuelSimulation">
        {{ running ? 'Simulating...' : 'Run Duel Simulation' }}
      </button>
    </div>

    <!-- Cooperative Results -->
    <div v-if="mode === 'cooperative' && results" class="results">
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

    <!-- Duel Results -->
    <div v-if="mode === 'duel' && duelResults" class="results">
      <!-- Summary -->
      <div class="result-section">
        <h3>Summary</h3>
        <div class="summary-grid summary-grid-5">
          <div class="summary-card">
            <div class="summary-val" :class="duelResults.summary.p1_win_rate > 50 ? 'rate-p1' : ''">{{ duelResults.summary.p1_win_rate }}%</div>
            <div class="summary-label">P1 Win Rate</div>
          </div>
          <div class="summary-card">
            <div class="summary-val" :class="duelResults.summary.p2_win_rate > 50 ? 'rate-p2' : ''">{{ duelResults.summary.p2_win_rate }}%</div>
            <div class="summary-label">P2 Win Rate</div>
          </div>
          <div class="summary-card">
            <div class="summary-val">{{ duelResults.summary.avg_rounds_played }}</div>
            <div class="summary-label">Avg Rounds</div>
          </div>
          <div class="summary-card">
            <div class="summary-val">{{ duelResults.summary.avg_p1_score }}</div>
            <div class="summary-label">Avg P1 Score</div>
          </div>
          <div class="summary-card">
            <div class="summary-val">{{ duelResults.summary.avg_p2_score }}</div>
            <div class="summary-label">Avg P2 Score</div>
          </div>
        </div>
      </div>

      <!-- End Reason Breakdown -->
      <div class="result-section">
        <h3>End Reasons</h3>
        <div class="end-reasons">
          <div v-for="(count, reason) in duelResults.end_reasons" :key="reason" class="end-reason-row">
            <span class="end-reason-name">{{ formatReason(reason) }}</span>
            <div class="end-reason-bar-bg">
              <div class="end-reason-bar" :class="'reason-' + reason" :style="{ width: (count / duelResults.summary.total_games * 100) + '%' }"></div>
            </div>
            <span class="end-reason-count">{{ count }} ({{ Math.round(count / duelResults.summary.total_games * 100) }}%)</span>
          </div>
        </div>
      </div>

      <!-- Collapse Details -->
      <div v-if="Object.keys(duelResults.collapse_details).length > 0" class="result-section">
        <h3>Collapse Details ({{ duelResults.end_reasons.stat_collapse }} collapses)</h3>
        <div class="collapse-list">
          <div v-for="(count, reason) in duelResults.collapse_details" :key="reason" class="collapse-row">
            <span class="collapse-reason">{{ reason }}</span>
            <span class="collapse-count">{{ count }} ({{ Math.round(count / duelResults.end_reasons.stat_collapse * 100) }}%)</span>
          </div>
        </div>
      </div>

      <!-- Domination Details -->
      <div v-if="duelResults.end_reasons.stat_domination > 0" class="result-section">
        <h3>Domination Details ({{ duelResults.end_reasons.stat_domination }} dominations)</h3>
        <div class="collapse-list">
          <div v-for="(count, label) in duelResults.domination_details" :key="label" class="collapse-row domination-row">
            <span class="collapse-reason">{{ label }}</span>
            <span class="collapse-count" style="color: #27ae60;">{{ count }}</span>
          </div>
        </div>
      </div>

      <!-- Round-by-Round Averages (dual columns) -->
      <div class="result-section">
        <h3>Round Averages</h3>
        <div class="round-table-wrap">
          <table class="round-table">
            <thead>
              <tr>
                <th rowspan="2">Rnd</th>
                <th rowspan="2">Alive</th>
                <th colspan="7" class="player-header p1-header">Player 1</th>
                <th colspan="7" class="player-header p2-header">Player 2</th>
              </tr>
              <tr>
                <th>Win%</th>
                <th>Wlth</th>
                <th>Infl</th>
                <th>Sec</th>
                <th>Rel</th>
                <th>Food</th>
                <th>Hap</th>
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
              <tr v-for="r in duelResults.round_averages" :key="r.round">
                <td>{{ r.round }}</td>
                <td>{{ r.games_alive }}</td>
                <td>{{ r.p1_success_rate }}%</td>
                <td :class="getCellClass(r.p1_avg_wealth)">{{ r.p1_avg_wealth }}</td>
                <td :class="getCellClass(r.p1_avg_influence)">{{ r.p1_avg_influence }}</td>
                <td :class="getCellClass(r.p1_avg_security)">{{ r.p1_avg_security }}</td>
                <td :class="getCellClass(r.p1_avg_religion)">{{ r.p1_avg_religion }}</td>
                <td :class="getCellClass(r.p1_avg_food)">{{ r.p1_avg_food }}</td>
                <td :class="getCellClass(r.p1_avg_happiness)">{{ r.p1_avg_happiness }}</td>
                <td class="p2-divider">{{ r.p2_success_rate }}%</td>
                <td :class="getCellClass(r.p2_avg_wealth)">{{ r.p2_avg_wealth }}</td>
                <td :class="getCellClass(r.p2_avg_influence)">{{ r.p2_avg_influence }}</td>
                <td :class="getCellClass(r.p2_avg_security)">{{ r.p2_avg_security }}</td>
                <td :class="getCellClass(r.p2_avg_religion)">{{ r.p2_avg_religion }}</td>
                <td :class="getCellClass(r.p2_avg_food)">{{ r.p2_avg_food }}</td>
                <td :class="getCellClass(r.p2_avg_happiness)">{{ r.p2_avg_happiness }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Score Distribution (end_score games only) -->
      <div v-if="Object.keys(duelResults.score_distribution).length > 0" class="result-section">
        <h3>Score Distribution ({{ duelResults.end_reasons.end_score }} tiebreak games)</h3>
        <div class="dist-bars">
          <div v-for="(count, bucket) in duelResults.score_distribution" :key="'ds' + bucket" class="dist-row">
            <span class="dist-label">{{ bucket }}-{{ parseInt(bucket) + 9 }}</span>
            <div class="dist-bar-bg">
              <div class="dist-bar" :style="{ width: (count / duelMaxScoreCount * 100) + '%' }"></div>
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
      mode: 'cooperative',
      // Cooperative
      numGames: 100,
      numPlayers: 3,
      totalRounds: 24,
      startingStats: 10,
      negativeMultiplier: 1.0,
      running: false,
      results: null,
      // Duel
      duelNumGames: 100,
      duelStartingStats: 8,
      duelBotDifficulty: 'medium',
      duelResults: null,
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
    duelMaxScoreCount() {
      if (!this.duelResults) return 1;
      return Math.max(1, ...Object.values(this.duelResults.score_distribution));
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
    async runDuelSimulation() {
      this.running = true;
      this.duelResults = null;
      try {
        const res = await axios.post('/api/admin/bot-simulate-duel', {
          num_games: this.duelNumGames,
          starting_stats: this.duelStartingStats,
          bot_difficulty: this.duelBotDifficulty,
        });
        this.duelResults = res.data;
      } catch (e) {
        alert('Duel simulation failed: ' + (e.response?.data?.error || e.message));
      }
      this.running = false;
    },
    formatReason(reason) {
      return reason.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
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

/* Tab Switcher */
.tab-switcher {
  display: flex;
  gap: 0;
  margin-bottom: 20px;
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  overflow: hidden;
  width: fit-content;
  margin-left: auto;
  margin-right: auto;
}

.tab-btn {
  padding: 10px 28px;
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
  background: var(--bg-secondary);
  color: var(--text-secondary);
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-btn:not(:last-child) {
  border-right: 1px solid var(--border-gold);
}

.tab-btn.active {
  background: var(--accent-gold);
  color: var(--bg-primary);
  font-weight: 700;
}

.tab-btn:hover:not(.active) {
  background: rgba(138, 106, 46, 0.15);
  color: var(--text-bright);
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

.summary-grid-5 {
  grid-template-columns: repeat(5, 1fr);
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
.rate-p1 { color: #3498db; }
.rate-p2 { color: #e67e22; }

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

.domination-row {
  background: rgba(39, 174, 96, 0.08);
}

.collapse-reason {
  text-transform: capitalize;
  color: var(--text-primary);
}

.collapse-count {
  color: var(--accent-red);
  font-weight: 600;
}

/* End reasons */
.end-reasons {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.end-reason-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.end-reason-name {
  width: 120px;
  font-size: 0.9rem;
  color: var(--text-primary);
}

.end-reason-bar-bg {
  flex: 1;
  height: 18px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 4px;
  overflow: hidden;
}

.end-reason-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.reason-stat_collapse { background: #e74c3c; }
.reason-stat_domination { background: #27ae60; }
.reason-end_score { background: var(--accent-gold); }

.end-reason-count {
  width: 90px;
  font-size: 0.85rem;
  color: var(--text-bright);
  font-weight: 600;
  text-align: right;
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

.player-header {
  border-bottom: 1px solid var(--border-gold);
  padding-bottom: 4px;
}

.p1-header { color: #3498db; }
.p2-header { color: #e67e22; }

.round-table td {
  padding: 4px 8px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
  text-align: center;
  color: var(--text-primary);
}

.p2-divider {
  border-left: 2px solid var(--border-gold);
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
