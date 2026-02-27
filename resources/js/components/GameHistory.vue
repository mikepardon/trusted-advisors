<template>
  <div class="card-panel history-panel">
    <h2 class="section-title">Campaign History</h2>

    <div v-if="loading" class="history-loading">Loading...</div>

    <div v-else-if="activeGames.length === 0 && games.length === 0" class="history-empty">
      <p>No campaigns yet. Start a new game from the Home page!</p>
    </div>

    <div v-else class="history-table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th>Game</th>
            <th>Mode</th>
            <th>Outcome</th>
            <th>Score</th>
            <th>Rounds</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="game in activeGames"
            :key="'a-' + game.id"
            class="history-row row-active"
            @click="resumeGame(game)"
          >
            <td class="cell-id">#{{ game.id }}</td>
            <td><span :class="['mode-badge', 'mode-' + (game.game_mode || 'single')]">{{ modeLabel(game.game_mode) }}</span></td>
            <td>
              <span class="outcome-badge outcome-active">
                Active
              </span>
            </td>
            <td class="cell-score">&mdash;</td>
            <td class="cell-rounds">{{ game.current_round || 0 }}/{{ game.total_rounds }}</td>
          </tr>
          <tr
            v-for="game in games"
            :key="game.id"
            class="history-row"
            @click="$router.push('/game/' + game.id + '/over')"
          >
            <td class="cell-id">#{{ game.id }}</td>
            <td><span :class="['mode-badge', 'mode-' + (game.game_mode || 'single')]">{{ modeLabel(game.game_mode) }}</span></td>
            <td>
              <span :class="['outcome-badge', game.win ? 'outcome-win' : 'outcome-loss']">
                {{ game.win ? 'Victory' : 'Defeat' }}
              </span>
            </td>
            <td class="cell-score">{{ game.score }}</td>
            <td class="cell-rounds">{{ game.rounds_survived }}/{{ game.total_rounds }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'GameHistory',
  data() {
    return {
      games: [],
      activeGames: [],
      loading: true,
    };
  },
  async mounted() {
    try {
      const res = await axios.get('/api/games/history');
      this.games = res.data.completed_games || [];
      this.activeGames = res.data.active_games || [];
    } catch {
      // silently fail
    }
    this.loading = false;
  },
  methods: {
    resumeGame(game) {
      this.$router.push('/game/' + game.id);
    },
    modeLabel(mode) {
      const labels = { single: 'Solo', pass_and_play: 'Local', online: 'Online' };
      return labels[mode] || 'Solo';
    },
  },
};
</script>

<style scoped>
.history-panel {
  margin-top: 20px;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 15px;
  text-align: center;
}

.history-loading,
.history-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px 0;
}

.history-table-wrap {
  overflow-x: auto;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  font-family: 'Crimson Text', Georgia, serif;
}

.history-table th {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 8px 12px;
  border-bottom: 2px solid var(--border-gold);
  text-align: left;
}

.history-table td {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
}

.history-row {
  cursor: pointer;
  transition: background 0.2s;
}

.history-row:hover {
  background: rgba(212, 168, 67, 0.08);
}

.row-active {
  background: rgba(74, 138, 58, 0.06);
}

.row-active:hover {
  background: rgba(74, 138, 58, 0.12);
}

.cell-id {
  color: var(--text-secondary);
  font-weight: 600;
}

.cell-score,
.cell-rounds {
  color: var(--text-bright);
}

.outcome-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 600;
}

.outcome-win {
  background: rgba(74, 138, 58, 0.2);
  color: #6abf50;
  border: 1px solid rgba(74, 138, 58, 0.4);
}

.outcome-loss {
  background: rgba(160, 48, 32, 0.2);
  color: #d05040;
  border: 1px solid rgba(160, 48, 32, 0.4);
}

.outcome-active {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 67, 0.4);
}

.mode-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
}

.mode-single {
  background: rgba(100, 100, 160, 0.2);
  color: #a0a0d0;
  border: 1px solid rgba(100, 100, 160, 0.4);
}

.mode-pass_and_play {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 67, 0.4);
}

.mode-online {
  background: rgba(67, 160, 212, 0.2);
  color: #60b8e0;
  border: 1px solid rgba(67, 160, 212, 0.4);
}

@media (max-width: 768px) {
  .section-title {
    font-size: 1.1rem;
  }

  .history-table th,
  .history-table td {
    padding: 8px;
    font-size: 0.9rem;
  }
}
</style>
