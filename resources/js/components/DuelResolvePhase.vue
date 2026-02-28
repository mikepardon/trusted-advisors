<template>
  <div class="duel-resolve">
    <h4 class="phase-title">Month Summary</h4>

    <div class="results-side-by-side">
      <div
        v-for="result in playerResults"
        :key="result.player_number"
        class="player-result"
        :class="{ 'result-success': result.success, 'result-failure': !result.success }"
      >
        <h5 class="result-player">{{ result.character_name }}</h5>
        <p class="result-card">{{ result.card?.title || 'Card' }}</p>

        <div class="result-roll">
          <span :class="result.success ? 'roll-pass' : 'roll-fail'">
            {{ result.total_roll }}
          </span>
          <span class="roll-vs">vs</span>
          <span>{{ result.difficulty }}</span>
        </div>

        <span class="result-badge" :class="result.success ? 'badge-success' : 'badge-failure'">
          {{ result.success ? 'SUCCESS' : 'FAILURE' }}
        </span>

        <div v-if="Object.keys(result.effects || {}).length" class="effects-row">
          <span
            v-for="(val, stat) in result.effects"
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
  border-left: 4px solid;
}

.result-success { border-left-color: var(--accent-green); }
.result-failure { border-left-color: var(--accent-red); }

.result-player {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  margin-bottom: 4px;
}

.result-card {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin-bottom: 10px;
}

.result-roll {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  margin-bottom: 8px;
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text-bright);
}

.roll-pass { color: var(--accent-green); }
.roll-fail { color: var(--accent-red); }
.roll-vs { color: var(--text-secondary); font-style: italic; font-weight: 400; font-size: 0.9rem; }

.result-badge {
  display: inline-block;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 3px 10px;
  border-radius: 4px;
  font-weight: 700;
  margin-bottom: 10px;
}

.badge-success { background: rgba(74, 138, 58, 0.2); color: #4a8a3a; }
.badge-failure { background: rgba(160, 48, 32, 0.2); color: #c0392b; }

.effects-row {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  justify-content: center;
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
