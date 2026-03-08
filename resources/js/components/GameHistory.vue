<template>
  <div class="history-page">
    <h2 class="section-title">Campaign History</h2>

    <HintBubble hint-id="campaigns-replay">
      Tap any completed game to see the results, or hit <strong>Replay</strong> to watch a round-by-round recap.
    </HintBubble>

    <div v-if="loading" class="history-loading">Loading...</div>

    <div v-else-if="activeGames.length === 0 && !hasCompleted" class="history-empty">
      <p>No campaigns yet. Start a new game from the Home page!</p>
    </div>

    <template v-else>
      <!-- ACTIVE GAMES — card layout -->
      <div v-if="activeGames.length > 0" class="active-section">
        <h3 class="sub-title">Active Games</h3>
        <div class="active-cards">
          <div
            v-for="game in visibleActiveGames"
            :key="'a-' + game.id"
            class="active-card"
            @click="resumeGame(game)"
          >
            <div class="active-card-top">
              <span class="type-badge" :class="'type-' + (game.game_type || 'cooperative')">
                {{ typeLabel(game.game_type) }}
              </span>
              <span :class="['mode-badge', 'mode-' + (game.game_mode || 'single')]">
                {{ modeLabel(game.game_mode) }}
              </span>
            </div>
            <div class="active-card-body">
              <div class="active-status">
                <span class="status-dot"></span>
                <span v-if="game.status === 'setup'">Setting Up</span>
                <span v-else>Month {{ game.current_round || 1 }} / {{ game.total_rounds }}</span>
              </div>
              <div class="active-players">
                <span v-for="(p, i) in game.players" :key="i" class="player-name">
                  {{ p.character_name || 'Unassigned' }}<template v-if="game.game_mode === 'online' && p.username"> ({{ p.username }})</template><span v-if="i < game.players.length - 1">, </span>
                </span>
                <span v-if="!game.players || game.players.length === 0" class="player-name dim">
                  {{ game.num_players }} player{{ game.num_players !== 1 ? 's' : '' }}
                </span>
              </div>
            </div>
            <div class="active-card-actions">
              <button class="btn-cancel" @click.stop="cancelGame(game)">Cancel</button>
              <span class="resume-link">Resume &rarr;</span>
            </div>
          </div>
        </div>
        <button v-if="activeGames.length > activeLimit" class="load-more-btn" @click="activeLimit += 10">
          Load More ({{ activeGames.length - activeLimit }} remaining)
        </button>
      </div>

      <!-- COMPLETED GAMES — Timeline -->
      <div class="completed-section">
        <h3 class="sub-title">Completed</h3>
        <MatchHistoryTimeline />
      </div>
    </template>

    <ConfirmModal
      :visible="!!cancelTarget"
      title="Cancel Game"
      message="Cancel this game? It will be ended as a loss."
      confirm-text="End Game"
      :dangerous="true"
      @confirm="doCancelGame"
      @cancel="cancelTarget = null"
    />
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../stores/toast';
import ConfirmModal from './ConfirmModal.vue';
import HintBubble from './HintBubble.vue';
import MatchHistoryTimeline from './MatchHistoryTimeline.vue';

export default {
  name: 'GameHistory',
  components: { ConfirmModal, HintBubble, MatchHistoryTimeline },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      games: [],
      activeGames: [],
      loading: true,
      cancelTarget: null,
      hasCompleted: false,
      activeLimit: 10,
    };
  },
  computed: {
    visibleActiveGames() {
      return this.activeGames.slice(0, this.activeLimit);
    },
  },
  async mounted() {
    await this.fetchGames();
  },
  methods: {
    async fetchGames() {
      this.loading = true;
      try {
        const res = await axios.get('/api/games/history');
        this.games = res.data.completed_games || [];
        this.activeGames = res.data.active_games || [];
        this.hasCompleted = this.games.length > 0;
      } catch {
        // silently fail
      }
      this.loading = false;
    },
    resumeGame(game) {
      this.$router.push('/game/' + game.id);
    },
    cancelGame(game) {
      this.cancelTarget = game;
    },
    async doCancelGame() {
      const game = this.cancelTarget;
      this.cancelTarget = null;
      try {
        await axios.post(`/api/games/${game.id}/cancel`);
        await this.fetchGames();
      } catch (e) {
        this.toast.error('Failed to cancel: ' + (e.response?.data?.error || e.message));
      }
    },
    modeLabel(mode) {
      const labels = { single: 'Solo', pass_and_play: 'Local', online: 'Online' };
      return labels[mode] || 'Solo';
    },
    typeLabel(type) {
      return type === 'duel' ? 'Duel' : 'Classic';
    },
    isWin(game) {
      if (game.game_type === 'duel') {
        return game.winner_player_number && game.winner_player_number === game.my_player_number;
      }
      return game.win;
    },
    outcomeLabel(game) {
      if (game.game_type === 'duel') {
        if (!game.winner_player_number) return 'Draw';
        return game.winner_player_number === game.my_player_number ? 'Victory' : 'Defeat';
      }
      return game.win ? 'Victory' : 'Defeat';
    },
    playerNames(game) {
      if (!game.players || game.players.length === 0) {
        return game.num_players + 'P';
      }
      return game.players.map(p => {
        const name = p.character_name || '?';
        if (game.game_mode === 'online' && p.username) {
          return `${name} (${p.username})`;
        }
        return name;
      }).join(', ');
    },
  },
};
</script>

<style scoped>
.history-page {
  max-width: 700px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
  text-align: center;
}

.sub-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.history-loading,
.history-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px 0;
}

/* === ACTIVE GAMES — Cards === */
.active-section {
  margin-bottom: 24px;
}

.active-cards {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.active-card {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-left: 4px solid var(--accent-gold);
  border-radius: 8px;
  padding: 12px 14px;
  cursor: pointer;
  transition: background 0.2s, box-shadow 0.2s;
}

.active-card:hover {
  background: rgba(212, 168, 67, 0.06);
  box-shadow: 0 2px 12px rgba(212, 168, 67, 0.15);
}

.active-card-top {
  display: flex;
  gap: 6px;
  margin-bottom: 8px;
}

.active-card-body {
  margin-bottom: 8px;
}

.active-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 1rem;
  color: var(--text-bright);
  font-weight: 600;
  margin-bottom: 4px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #4a8a3a;
  box-shadow: 0 0 6px rgba(74, 138, 58, 0.6);
  flex-shrink: 0;
}

.active-players {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.player-name.dim {
  font-style: italic;
}

.active-card-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.resume-link {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  color: var(--accent-gold);
  letter-spacing: 1px;
}

.btn-cancel {
  background: rgba(160, 48, 32, 0.12);
  border: 1px solid rgba(160, 48, 32, 0.3);
  color: #d05040;
  padding: 3px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: rgba(160, 48, 32, 0.25);
}

.load-more-btn {
  display: block;
  width: 100%;
  margin-top: 10px;
  padding: 10px;
  background: rgba(138, 106, 46, 0.12);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.2s;
}

.load-more-btn:hover {
  background: rgba(138, 106, 46, 0.25);
}

/* === COMPLETED GAMES — Compact rows === */
.completed-section {
  margin-bottom: 16px;
}

.completed-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.completed-row {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  padding: 10px 12px;
  cursor: pointer;
  transition: background 0.2s;
}

.completed-row:hover {
  background: rgba(212, 168, 67, 0.06);
}

.completed-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.completed-badges {
  display: flex;
  gap: 4px;
}

.completed-details {
  display: flex;
  gap: 16px;
}

.detail-item {
  display: flex;
  gap: 4px;
  align-items: baseline;
  min-width: 0;
}

.detail-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  flex-shrink: 0;
}

.detail-value {
  font-size: 0.9rem;
  color: var(--text-bright);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* === Badges === */
.type-badge,
.type-badge-sm {
  display: inline-block;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.type-badge {
  padding: 2px 8px;
}

.type-badge-sm {
  padding: 1px 6px;
  font-size: 0.7rem;
}

.type-cooperative {
  background: rgba(100, 100, 160, 0.2);
  color: #a0a0d0;
  border: 1px solid rgba(100, 100, 160, 0.3);
}

.type-duel {
  background: rgba(200, 80, 60, 0.2);
  color: #e08060;
  border: 1px solid rgba(200, 80, 60, 0.3);
}

.mode-badge,
.mode-badge-sm {
  display: inline-block;
  border-radius: 4px;
  font-weight: 600;
}

.mode-badge {
  padding: 2px 8px;
  font-size: 0.75rem;
}

.mode-badge-sm {
  padding: 1px 6px;
  font-size: 0.7rem;
}

.mode-single {
  background: rgba(100, 100, 160, 0.15);
  color: #9090c0;
  border: 1px solid rgba(100, 100, 160, 0.3);
}

.mode-pass_and_play {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 67, 0.3);
}

.mode-online {
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
  border: 1px solid rgba(67, 160, 212, 0.3);
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

.replay-btn {
  background: rgba(100, 100, 160, 0.15);
  border: 1px solid rgba(100, 100, 160, 0.3);
  color: #a0a0d0;
  padding: 2px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
  transition: all 0.2s;
  flex-shrink: 0;
}

.replay-btn:hover {
  background: rgba(100, 100, 160, 0.25);
  color: #c0c0e0;
}

/* === Mobile === */
@media (max-width: 768px) {
  .section-title {
    font-size: 1.1rem;
  }

  .completed-details {
    flex-direction: column;
    gap: 2px;
  }

  .detail-value {
    font-size: 0.85rem;
  }
}
</style>
