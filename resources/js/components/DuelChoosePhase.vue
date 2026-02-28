<template>
  <div class="duel-choose">
    <h4 class="phase-title">Choose Your Card</h4>
    <p class="phase-note">
      Pick the revealed card or take a chance on the hidden one.
      Your opponent gets whichever you don't choose.
    </p>

    <div class="choose-cards">
      <div
        v-for="card in cards"
        :key="card.hand_id"
        class="choose-card-wrap"
        :class="{ 'card-selected': selectedId === card.hand_id }"
        @click="selectCard(card.hand_id)"
      >
        <!-- REVEALED CARD -->
        <div v-if="card.revealed && card.card" class="parchment-card">
          <div v-if="selectedId === card.hand_id" class="card-ribbon acting">
            Taking this
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

        <!-- HIDDEN CARD (mystery) -->
        <div v-else class="mystery-card">
          <div v-if="selectedId === card.hand_id" class="card-ribbon acting">
            Taking this
          </div>

          <div class="mystery-content">
            <div class="mystery-icon">?</div>
            <h3 class="mystery-title">Hidden Card</h3>
            <p class="mystery-desc">
              This card's contents are unknown.
              Do you dare take the risk?
            </p>
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
      {{ submitting ? 'Choosing...' : 'Take This Card' }}
    </button>
  </div>
</template>

<script>
import { playSound } from '../sounds';

export default {
  name: 'DuelChoosePhase',
  props: {
    cards: { type: Array, default: () => [] },
  },
  emits: ['choose'],
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
      this.$emit('choose', this.selectedId);
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
.duel-choose {
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

.choose-cards {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

.choose-card-wrap {
  cursor: pointer;
  transition: transform 0.2s;
}

.choose-card-wrap:hover {
  transform: translateY(-4px);
}

.choose-card-wrap.card-selected .parchment-card,
.choose-card-wrap.card-selected .mystery-card {
  border-color: var(--accent-gold);
  box-shadow: 0 0 30px rgba(212, 168, 67, 0.35), 0 0 60px rgba(212, 168, 67, 0.1);
}

.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px 20px;
  width: 300px;
  position: relative;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
}

.mystery-card {
  background: linear-gradient(180deg, #1a1a2e, #16213e, #0f3460);
  border: 2px solid rgba(100, 120, 180, 0.5);
  border-radius: 12px;
  padding: 24px 20px;
  width: 300px;
  min-height: 300px;
  position: relative;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.mystery-content {
  text-align: center;
}

.mystery-icon {
  font-size: 4rem;
  color: rgba(100, 120, 180, 0.6);
  font-family: 'Cinzel', serif;
  font-weight: 900;
  margin-bottom: 12px;
  text-shadow: 0 0 20px rgba(100, 120, 180, 0.3);
}

.mystery-title {
  font-family: 'Cinzel', serif;
  color: rgba(150, 170, 220, 0.8);
  font-size: 1.2rem;
  margin-bottom: 8px;
}

.mystery-desc {
  color: rgba(150, 170, 220, 0.5);
  font-style: italic;
  font-size: 0.85rem;
  line-height: 1.5;
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

.outcome-section { margin-bottom: 6px; }
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
  .choose-cards {
    flex-direction: column;
    align-items: center;
  }

  .parchment-card,
  .mystery-card {
    width: 100%;
    max-width: 320px;
  }
}
</style>
