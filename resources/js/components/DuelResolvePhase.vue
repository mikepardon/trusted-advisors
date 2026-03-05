<template>

  <div class="duel-resolve">
    <h4 class="phase-title">Month Summary</h4>

    <div class="results-side-by-side">
      <div
        v-for="result in playerResults"
        :key="result.player_number"
        class="player-result"
      >
        <h5 class="result-player">{{ result.character_name }}</h5>

        <div class="result-roll-total">
          Roll: <strong>{{ result.total_roll }}</strong>
        </div>

        <!-- Per-card outcomes -->
        <div v-for="(cr, idx) in getCardResults(result)" :key="idx" class="card-outcome">
          <span class="card-outcome-name">{{ cr.card?.title || 'Card ' + (idx + 1) }}</span>
          <span class="card-outcome-diff">Diff {{ cr.difficulty }}</span>
          <span class="result-badge" :class="cr.success ? 'badge-success' : 'badge-failure'">
            {{ cr.success ? 'SUCCESS' : 'FAILURE' }}
          </span>
        </div>

        <!-- Combined effects -->
        <div v-if="Object.keys(getCombinedEffects(result)).length" class="effects-row">
          <span
            v-for="(val, stat) in getCombinedEffects(result)"
            :key="stat"
            class="effect-badge"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
      </div>
    </div>

    <button
        v-if="canAdvance"
        class="btn-primary next-btn"
        @click="$emit('next-round')"
    >
        {{ gameOver ? 'View Results' : 'Next Month' }}
    </button>
    <p v-else class="waiting-host-text">Waiting for host to advance...</p>
  </div>
</template>

<script>
export default {
  name: 'DuelResolvePhase',
  props: {
    offererResult: { type: Object, default: null },
    chooserResult: { type: Object, default: null },
    canAdvance: { type: Boolean, default: true },
    gameOver: { type: Boolean, default: false },
  },
  emits: ['next-round'],
  computed: {
    playerResults() {
      const results = [];
      if (this.offererResult) results.push(this.offererResult);
      if (this.chooserResult) results.push(this.chooserResult);
      return results;
    },
  },
  methods: {
    getCardResults(result) {
      // New 2-card format
      if (result.cards) return result.cards;
      // Legacy single-card format
      if (result.card) {
        return [{
          card: result.card,
          difficulty: result.difficulty,
          success: result.success,
          effects: result.effects || {},
        }];
      }
      return [];
    },
    getCombinedEffects(result) {
      if (result.combined_effects) return result.combined_effects;
      if (result.effects) return result.effects;
      return {};
    },
  },
};
</script>

<style scoped>
.duel-resolve {
  margin-bottom: 20px;
}

.phase-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  font-size: 1.2rem;
  margin-bottom: 16px;
}

.results-side-by-side {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}

.player-result {
  background: var(--bg-secondary);
  border-radius: 10px;
  padding: 16px;
  text-align: center;
  border-left: 4px solid var(--accent-gold);
}

.result-player {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  margin-bottom: 6px;
}

.result-roll-total {
  font-size: 1rem;
  color: var(--text-bright);
  margin-bottom: 10px;
}

.card-outcome {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.card-outcome-name {
  font-family: 'Cinzel', serif;
  color: var(--text-primary);
  font-size: 0.85rem;
}

.card-outcome-diff {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.result-badge {
  display: inline-block;
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
}

.badge-success { background: rgba(74, 138, 58, 0.2); color: #4a8a3a; }
.badge-failure { background: rgba(160, 48, 32, 0.2); color: #c0392b; }

.effects-row {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 8px;
}

.effect-badge {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  text-transform: capitalize;
}

.effect-positive { background: rgba(74, 138, 58, 0.15); color: #4a8a3a; }
.effect-negative { background: rgba(160, 48, 32, 0.15); color: #c0392b; }

.next-btn {
  display: block;
  margin: 0 auto;
  font-size: 1.1rem;
  padding: 12px 40px;
}

.waiting-host-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 16px;
}

@media (max-width: 768px) {
  .results-side-by-side {
    grid-template-columns: 1fr;
  }

  .player-result {
    padding: 12px;
  }
}
</style>
