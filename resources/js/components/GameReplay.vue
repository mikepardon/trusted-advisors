<template>
  <div class="replay-page">
    <h2 class="section-title">Game Replay</h2>

    <div v-if="loading" class="replay-loading">Loading replay...</div>
    <div v-else-if="error" class="replay-error">{{ error }}</div>

    <template v-else>
      <!-- Game info header -->
      <div class="card-panel replay-info">
        <div class="replay-meta">
          <span class="type-badge" :class="'type-' + (game.game_type || 'cooperative')">
            {{ game.game_type === 'duel' ? 'Duel' : 'Classic' }}
          </span>
          <span class="mode-badge" :class="'mode-' + (game.game_mode || 'single')">
            {{ { single: 'Solo', pass_and_play: 'Local', online: 'Online' }[game.game_mode] || 'Solo' }}
          </span>
          <span class="replay-rounds">{{ totalRoundsPlayed }} rounds played</span>
        </div>
        <div class="replay-players">
          <span v-for="p in game.players" :key="p.id" class="replay-player">
            {{ p.character?.name || 'Player' }}
          </span>
        </div>
      </div>

      <!-- Round navigation -->
      <div class="round-nav">
        <button class="nav-btn" :disabled="currentRound <= 1" @click="currentRound--">&larr; Prev</button>
        <span class="round-label">Round {{ currentRound }} / {{ totalRoundsPlayed }}</span>
        <button class="nav-btn" :disabled="currentRound >= totalRoundsPlayed" @click="currentRound++">&rarr; Next</button>
      </div>

      <!-- Round details -->
      <div class="round-details">
        <div v-if="!currentRoundData || currentRoundData.length === 0" class="no-data">
          No data for this round.
        </div>
        <div v-for="result in currentRoundData" :key="result.id" class="result-card card-panel">
          <div class="result-header">
            <strong class="card-name">{{ result.card?.name || 'Unknown Card' }}</strong>
            <span v-if="result.player" class="player-tag">{{ result.player?.character?.name || 'Player' }}</span>
          </div>

          <div class="result-body">
            <!-- Dice rolls -->
            <div v-if="result.dice_results" class="dice-section">
              <span class="sub-label">Dice:</span>
              <span v-for="(d, i) in result.dice_results" :key="i" class="die-face">{{ d }}</span>
              <span class="dice-outcome" :class="result.success ? 'outcome-pass' : 'outcome-fail'">
                {{ result.success ? 'Success' : 'Failed' }}
              </span>
            </div>

            <!-- Stat changes -->
            <div v-if="result.effects_applied && result.effects_applied.length" class="effects-section">
              <span class="sub-label">Effects:</span>
              <div class="effects-list">
                <span v-for="(eff, i) in result.effects_applied" :key="i" class="effect-tag">
                  {{ eff }}
                </span>
              </div>
            </div>

            <!-- Stat totals after this round -->
            <div v-if="result.stat_totals" class="stats-after">
              <span class="sub-label">Stats After:</span>
              <div class="stat-chips">
                <span v-for="(val, stat) in result.stat_totals" :key="stat" class="stat-chip">
                  {{ stat }}: {{ val }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <button class="btn-primary back-btn" @click="$router.push('/campaigns')">Back to History</button>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'GameReplay',
  props: {
    id: { type: [String, Number], required: true },
  },
  data() {
    return {
      loading: true,
      error: null,
      game: null,
      rounds: {},
      totalRoundsPlayed: 0,
      currentRound: 1,
    };
  },
  computed: {
    currentRoundData() {
      return this.rounds[this.currentRound] || [];
    },
  },
  async mounted() {
    try {
      const res = await axios.get(`/api/games/${this.id}/replay`);
      this.game = res.data.game;
      this.rounds = res.data.rounds;
      this.totalRoundsPlayed = res.data.total_rounds_played;
    } catch (e) {
      this.error = e.response?.data?.error || 'Failed to load replay';
    }
    this.loading = false;
  },
};
</script>

<style scoped>
.replay-page {
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

.replay-loading, .replay-error, .no-data {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.replay-error { color: var(--accent-red); }

.replay-info {
  text-align: center;
  margin-bottom: 16px;
}

.replay-meta {
  display: flex;
  gap: 8px;
  justify-content: center;
  align-items: center;
  margin-bottom: 8px;
}

.replay-rounds {
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.replay-players {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
}

.replay-player {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
}

.type-badge, .mode-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.type-cooperative { background: rgba(100, 100, 160, 0.2); color: #a0a0d0; border: 1px solid rgba(100, 100, 160, 0.3); }
.type-duel { background: rgba(200, 80, 60, 0.2); color: #e08060; border: 1px solid rgba(200, 80, 60, 0.3); }
.mode-single { background: rgba(100, 100, 160, 0.15); color: #9090c0; border: 1px solid rgba(100, 100, 160, 0.3); }
.mode-pass_and_play { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); border: 1px solid rgba(212, 168, 67, 0.3); }
.mode-online { background: rgba(67, 160, 212, 0.15); color: #60b8e0; border: 1px solid rgba(67, 160, 212, 0.3); }

/* Round nav */
.round-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-bottom: 16px;
}

.nav-btn {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--accent-gold);
  padding: 6px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.nav-btn:disabled {
  opacity: 0.3;
  cursor: default;
}

.round-label {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1rem;
}

/* Result cards */
.round-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
}

.result-card {
  padding: 14px;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.card-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
}

.player-tag {
  font-size: 0.8rem;
  color: var(--text-secondary);
  background: rgba(0, 0, 0, 0.2);
  padding: 2px 8px;
  border-radius: 4px;
}

.sub-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-right: 6px;
}

.dice-section {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.die-face {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 4px;
  padding: 2px 8px;
  font-weight: 700;
  color: var(--text-bright);
  font-size: 0.9rem;
}

.dice-outcome {
  font-size: 0.8rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}

.outcome-pass { color: #6abf50; background: rgba(74, 138, 58, 0.15); }
.outcome-fail { color: #d05040; background: rgba(160, 48, 32, 0.15); }

.effects-section {
  margin-bottom: 8px;
}

.effects-list {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.effect-tag {
  font-size: 0.8rem;
  padding: 2px 8px;
  border-radius: 4px;
  background: rgba(100, 100, 160, 0.15);
  color: #a0a0d0;
}

.stats-after {
  margin-top: 4px;
}

.stat-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.stat-chip {
  font-size: 0.75rem;
  padding: 2px 6px;
  border-radius: 3px;
  background: rgba(0, 0, 0, 0.2);
  color: var(--text-primary);
}

.back-btn {
  display: block;
  margin: 0 auto;
  font-size: 1rem;
  padding: 10px 30px;
}

@media (max-width: 768px) {
  .result-header {
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
  }
}
</style>
