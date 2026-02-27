<template>
  <div v-if="card" class="card-display">
    <div class="card-header">
      <h3 class="card-title">{{ card.title }}</h3>
      <span v-if="card.difficulty" class="diff-badge">
        Difficulty: {{ card.difficulty }}
      </span>
    </div>
    <p class="card-description">{{ card.description }}</p>
    <div class="card-effects">
      <div v-if="card.positive_effects" class="effect-row">
        <span class="effect-label">Positive:</span>
        <span
          v-for="(val, stat) in filterStatEffects(card.positive_effects)"
          :key="'p-' + stat"
          class="effect-chip"
          :class="val > 0 ? 'effect-positive' : 'effect-negative'"
        >
          {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
        </span>
      </div>
      <div v-if="card.negative_effects" class="effect-row">
        <span class="effect-label">Negative:</span>
        <span
          v-for="(val, stat) in card.negative_effects"
          :key="'n-' + stat"
          class="effect-chip"
          :class="val > 0 ? 'effect-positive' : 'effect-negative'"
        >
          {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
        </span>
      </div>
    </div>
  </div>
  <div v-else class="card-display no-card">
    <p>No card to display.</p>
  </div>
</template>

<script>
export default {
  name: 'CardDisplay',
  props: {
    card: { type: Object, default: null },
  },
  methods: {
    filterStatEffects(effects) {
      if (!effects) return {};
      const result = {};
      for (const [key, val] of Object.entries(effects)) {
        if (key !== 'grant_item_id') {
          result[key] = val;
        }
      }
      return result;
    },
  },
};
</script>

<style scoped>
.card-display {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
}

.diff-badge {
  font-size: 0.75rem;
  letter-spacing: 1px;
  padding: 3px 10px;
  border-radius: 4px;
  background: rgba(212, 168, 67, 0.2);
  color: #d4a843;
  font-weight: 700;
}

.card-description {
  text-align: center;
  color: var(--text-primary);
  line-height: 1.6;
  margin-bottom: 15px;
  font-size: 1.05rem;
}

.card-effects {
  font-size: 0.85rem;
}

.effect-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 6px;
}

.effect-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-right: 4px;
}

.effect-chip {
  padding: 3px 8px;
  border-radius: 4px;
  text-transform: capitalize;
  font-size: 0.8rem;
}

.effect-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #27ae60;
}

.effect-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #c0392b;
}

.no-card {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
}
</style>
