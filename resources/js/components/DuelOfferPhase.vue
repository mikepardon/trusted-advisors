<template>
  <div class="duel-offer">
    <h4 class="phase-title">Choose a Card to Reveal</h4>
    <p class="phase-note">
      Select one card to reveal to your opponent. They will choose between
      the revealed card and the hidden one.
    </p>

    <div class="offer-cards">
      <div
        v-for="card in cards"
        :key="card.hand_id"
        class="parchment-card"
        :class="{ 'card-selected': selectedId === card.hand_id }"
        @click="selectCard(card.hand_id)"
      >
        <div v-if="selectedId === card.hand_id" class="card-ribbon acting">
          Revealing this
        </div>

        <h3 class="parchment-title">{{ card.card.title }}</h3>
        <p class="parchment-desc">{{ card.card.description }}</p>
        <span class="parchment-difficulty">Difficulty {{ card.card.difficulty }}</span>

        <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

        <div class="outcome-section">
          <p class="outcome-label">On Success:</p>
          <div class="outcome-chips">
            <span
              v-for="(val, stat) in filterStatEffects(card.card.positive_effects)"
              :key="'p-' + stat"
              class="stat-chip chip-positive"
            >
              {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
            </span>
          </div>
        </div>

        <div class="outcome-section">
          <p class="outcome-label">Always:</p>
          <div class="outcome-chips">
            <span
              v-for="(val, stat) in filterStatEffects(card.card.negative_effects)"
              :key="'n-' + stat"
              class="stat-chip chip-negative"
            >
              {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <button
      v-if="selectedId"
      class="btn-primary confirm-btn"
      :disabled="submitting"
      @click="confirm"
    >
      {{ submitting ? 'Offering...' : 'Reveal This Card' }}
    </button>
  </div>
</template>

<script>
import { playSound } from '../sounds';

export default {
  name: 'DuelOfferPhase',
  props: {
    cards: { type: Array, default: () => [] },
  },
  emits: ['offer'],
  data() {
    return {
      selectedId: null,
      submitting: false,
    };
  },
  methods: {
    selectCard(handId) {
      if (this.submitting) return;
      playSound('clickCard');
      this.selectedId = this.selectedId === handId ? null : handId;
    },
    confirm() {
      if (!this.selectedId || this.submitting) return;
      this.submitting = true;
      this.$emit('offer', this.selectedId);
    },
    filterStatEffects(effects) {
      if (!effects) return {};
      const result = {};
      const specialKeys = ['grant_item_id', 'draw_item', 'recover_die', 'lose_die', 'discard_item', 'remove_curse'];
      for (const [key, val] of Object.entries(effects)) {
        if (!specialKeys.includes(key)) {
          result[key] = val;
        }
      }
      return result;
    },
  },
  watch: {
    cards() {
      this.selectedId = null;
      this.submitting = false;
    },
  },
};
</script>

<style scoped>
.duel-offer {
  margin-bottom: 20px;
}

.phase-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  font-size: 1.2rem;
  margin-bottom: 6px;
}

.phase-note {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
  margin-bottom: 20px;
}

.offer-cards {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px 20px;
  width: 300px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
}

.parchment-card:hover {
  transform: translateY(-4px);
  border-color: var(--accent-gold);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6), 0 0 15px rgba(212, 168, 67, 0.15);
}

.parchment-card.card-selected {
  border-color: var(--accent-gold);
  box-shadow: 0 0 30px rgba(212, 168, 67, 0.35), 0 0 60px rgba(212, 168, 67, 0.1);
}

.card-ribbon {
  position: absolute;
  top: -1px;
  left: 0;
  right: 0;
  padding: 4px 18px;
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  border-radius: 0 0 6px 6px;
  text-align: center;
}

.card-ribbon.acting {
  background: linear-gradient(180deg, #b8942e, #8a6a14);
  color: #1a1209;
}

.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  text-align: center;
  margin-bottom: 8px;
  margin-top: 8px;
}

.parchment-desc {
  color: var(--text-primary);
  font-style: italic;
  font-size: 0.85rem;
  text-align: center;
  margin-bottom: 8px;
  line-height: 1.4;
}

.parchment-difficulty {
  display: block;
  text-align: center;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 3px 12px;
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  border-radius: 4px;
  width: fit-content;
  margin: 0 auto 10px;
}

.parchment-divider {
  position: relative;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold), transparent);
  margin: 10px 0;
}

.divider-ornament {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #2a1f14;
  color: var(--accent-gold);
  padding: 0 8px;
  font-size: 0.7rem;
}

.outcome-section {
  margin-bottom: 6px;
}

.outcome-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-align: center;
  margin-bottom: 4px;
  font-weight: 600;
}

.outcome-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: center;
}

.stat-chip {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
}

.chip-positive { background: rgba(39, 174, 96, 0.15); color: #4caf50; }
.chip-negative { background: rgba(192, 57, 43, 0.15); color: #e57373; }

.confirm-btn {
  display: block;
  margin: 20px auto 0;
  font-size: 1.1rem;
  padding: 12px 40px;
}

@media (max-width: 768px) {
  .offer-cards {
    flex-direction: column;
    align-items: center;
  }

  .parchment-card {
    width: 100%;
    max-width: 320px;
  }
}
</style>
