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
            <strong class="card-name">{{ resultCardName(result) }}</strong>
            <span v-if="result.player" class="player-tag">{{ result.player?.character?.name || 'Player' }}</span>
          </div>

          <div class="result-body">
            <!-- Duel dice (array of player roll objects) -->
            <template v-if="isDuelResult(result)">
              <div v-for="(pr, pi) in result.dice_results" :key="pi" class="dice-section">
                <span class="sub-label">{{ pr.character_name }}:</span>
                <span v-for="(roll, ri) in pr.rolls" :key="ri" class="die-face">{{ roll.value }}</span>
                <span class="dice-total">= {{ pr.rolls.reduce((s, r) => s + r.value, 0) }}</span>
              </div>
              <div class="dice-section">
                <span class="sub-label">vs Difficulty {{ result.stat_totals?.total_difficulty }}</span>
                <span class="dice-outcome" :class="result.success ? 'outcome-pass' : 'outcome-fail'">
                  {{ result.success ? 'Success' : 'Failed' }}
                </span>
              </div>
            </template>

            <!-- Cooperative dice (flat array of numbers) -->
            <template v-else-if="result.dice_results">
              <div class="dice-section">
                <span class="sub-label">Dice:</span>
                <span v-for="(d, i) in result.dice_results" :key="i" class="die-face">{{ d }}</span>
                <span class="dice-outcome" :class="result.success ? 'outcome-pass' : 'outcome-fail'">
                  {{ result.success ? 'Success' : 'Failed' }}
                </span>
              </div>
            </template>

            <!-- Stat changes (effects_applied as object for duel, array for cooperative) -->
            <div v-if="hasEffects(result)" class="effects-section">
              <span class="sub-label">Effects:</span>
              <div class="effects-list">
                <template v-if="Array.isArray(result.effects_applied)">
                  <span v-for="(eff, i) in result.effects_applied" :key="i" class="effect-tag">{{ eff }}</span>
                </template>
                <template v-else>
                  <span v-for="(val, stat) in result.effects_applied" :key="stat" class="effect-tag" :class="val > 0 ? 'effect-positive' : 'effect-negative'">
                    {{ formatStatName(stat) }} {{ val > 0 ? '+' : '' }}{{ val }}
                  </span>
                </template>
              </div>
            </div>

            <!-- Stat totals (only show kingdom stats, not roll/difficulty) -->
            <div v-if="hasKingdomStats(result)" class="stats-after">
              <span class="sub-label">Stats After:</span>
              <div class="stat-chips">
                <span v-for="(val, stat) in result.stat_totals" :key="stat" class="stat-chip">
                  {{ formatStatName(stat) }}: {{ val }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="replay-actions">
        <button v-if="!shareToken" class="btn-primary back-btn" @click="$router.push('/campaigns')">Back to History</button>
        <button v-else class="btn-primary back-btn" @click="$router.push('/')">Home</button>
        <button v-if="!shareToken" class="share-btn" @click="shareReplay">
          {{ shareCopied ? 'Copied!' : 'Share Replay' }}
        </button>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'GameReplay',
  props: {
    id: { type: [String, Number], default: null },
    shareToken: { type: String, default: null },
  },
  data() {
    return {
      loading: true,
      error: null,
      game: null,
      rounds: {},
      totalRoundsPlayed: 0,
      currentRound: 1,
      shareCopied: false,
    };
  },
  computed: {
    currentRoundData() {
      return this.rounds[this.currentRound] || [];
    },
    isDuel() {
      return this.game?.game_type === 'duel';
    },
  },
  async mounted() {
    try {
      const url = this.shareToken
        ? `/api/replays/${this.shareToken}`
        : `/api/games/${this.id}/replay`;
      const res = await axios.get(url);
      this.game = res.data.game;
      this.rounds = res.data.rounds;
      this.totalRoundsPlayed = res.data.total_rounds_played;
    } catch (e) {
      this.error = e.response?.data?.error || 'Failed to load replay';
    }
    this.loading = false;
  },
  methods: {
    isDuelResult(result) {
      return Array.isArray(result.dice_results) && result.dice_results.length > 0 && result.dice_results[0]?.rolls;
    },
    resultCardName(result) {
      if (result.card?.name) return result.card.name;
      // For duel results, use the player's character name as context
      if (this.isDuel && result.player?.character?.name) {
        return result.player.character.name + "'s Turn";
      }
      return 'Card';
    },
    hasEffects(result) {
      if (!result.effects_applied) return false;
      if (Array.isArray(result.effects_applied)) return result.effects_applied.length > 0;
      return Object.keys(result.effects_applied).length > 0;
    },
    hasKingdomStats(result) {
      if (!result.stat_totals) return false;
      // Don't show total_roll/total_difficulty as "stats after" — those are roll metadata
      const keys = Object.keys(result.stat_totals);
      return keys.some(k => !['total_roll', 'total_difficulty'].includes(k));
    },
    formatStatName(stat) {
      return stat.charAt(0).toUpperCase() + stat.slice(1).replace(/_/g, ' ');
    },
    async shareReplay() {
      try {
        const res = await axios.post(`/api/games/${this.id}/share`);
        await navigator.clipboard.writeText(res.data.share_url);
        this.shareCopied = true;
        setTimeout(() => { this.shareCopied = false; }, 2000);
      } catch {
        // Fallback: try to copy URL manually
        alert('Failed to generate share link');
      }
    },
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

.dice-total {
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 0.9rem;
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

.effect-positive {
  background: rgba(74, 138, 58, 0.15);
  color: #6abf50;
}

.effect-negative {
  background: rgba(160, 48, 32, 0.15);
  color: #d05040;
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

.replay-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.back-btn {
  font-size: 1rem;
  padding: 10px 30px;
}

.share-btn {
  font-size: 1rem;
  padding: 10px 30px;
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
  border: 1px solid rgba(67, 160, 212, 0.3);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.share-btn:hover {
  background: rgba(67, 160, 212, 0.25);
  border-color: rgba(67, 160, 212, 0.5);
}

@media (max-width: 768px) {
  .result-header {
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
  }
}
</style>
