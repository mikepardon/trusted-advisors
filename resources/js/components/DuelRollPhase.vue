<template>
  <div class="duel-roll">
    <h4 class="phase-title">{{ playerName }}'s Challenge</h4>

    <!-- Show assigned card -->
    <div v-if="card" class="roll-card">
      <h3 class="card-title">{{ card.title }}</h3>
      <p class="card-desc">{{ card.description }}</p>
      <span class="card-difficulty">Difficulty {{ card.difficulty }}</span>
    </div>

    <!-- Dice rolling area -->
    <div class="dice-area">
      <div class="dice-row">
        <template v-if="hasRolled">
          <span
            v-for="(roll, ri) in rollData.rolls"
            :key="ri"
            class="face-badge"
            :class="roll.face === 'WILD' ? 'face-wild' : 'face-num'"
          >
            {{ roll.face === 'WILD' ? 'WILD ' + roll.value : roll.face }}
          </span>
        </template>

        <template v-else-if="isRolling">
          <span
            v-for="(face, ri) in rollingFaces"
            :key="'r-' + ri"
            class="face-badge face-rolling"
          >
            {{ face }}
          </span>
        </template>
      </div>

      <button
        v-if="!hasRolled && !isRolling && canRoll"
        class="btn-roll"
        :disabled="submitting"
        @click="startRolling"
      >
        Roll!
      </button>

      <p v-if="!canRoll && !hasRolled" class="waiting-text">
        Waiting for opponent to roll...
      </p>
    </div>

    <!-- Roll results -->
    <template v-if="hasRolled">
      <div v-if="rollData.ability_effects && rollData.ability_effects.length" class="wild-section">
        <div v-for="(desc, i) in rollData.ability_effects" :key="i" class="wild-trigger">
          {{ desc }}
        </div>
      </div>

      <div class="roll-summary">
        <span class="roll-total" :class="rollData.success ? 'roll-pass' : 'roll-fail'">
          Total Roll: {{ rollData.total_roll }}
        </span>
        <span class="roll-vs">vs</span>
        <span class="roll-difficulty">Difficulty: {{ rollData.difficulty }}</span>
        <span class="result-badge" :class="rollData.success ? 'badge-success' : 'badge-failure'">
          {{ rollData.success ? 'SUCCESS' : 'FAILURE' }}
        </span>
      </div>

      <!-- Effects -->
      <div v-if="Object.keys(statEffects).length" class="effects-row">
        <span
          v-for="(val, stat) in statEffects"
          :key="stat"
          class="effect-badge"
          :class="val > 0 ? 'effect-positive' : 'effect-negative'"
        >
          {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
        </span>
      </div>
      <div v-else class="no-effects">
        No stat changes this round.
      </div>
    </template>
  </div>
</template>

<script>
import { playSound } from '../sounds';

export default {
  name: 'DuelRollPhase',
  props: {
    card: { type: Object, default: null },
    playerName: { type: String, default: 'Player' },
    canRoll: { type: Boolean, default: false },
    rollData: { type: Object, default: null },
  },
  emits: ['roll'],
  data() {
    return {
      isRolling: false,
      rollingFaces: [],
      submitting: false,
    };
  },
  computed: {
    hasRolled() {
      return this.rollData !== null;
    },
    statEffects() {
      if (!this.rollData?.effects) return {};
      return this.rollData.effects;
    },
  },
  methods: {
    startRolling() {
      if (this.isRolling || this.hasRolled || this.submitting) return;

      playSound('dice');
      this.isRolling = true;
      this.submitting = true;
      this.rollingFaces = [1, 2, 3].map(() => '?');

      const possibleFaces = ['1', '2', '3', '4', '5', 'W'];
      let ticks = 0;
      const maxTicks = 12;

      const interval = setInterval(() => {
        ticks++;
        this.rollingFaces = this.rollingFaces.map(() =>
          possibleFaces[Math.floor(Math.random() * possibleFaces.length)]
        );

        if (ticks >= maxTicks) {
          clearInterval(interval);
          this.isRolling = false;
          this.$emit('roll');
        }
      }, 70);
    },
  },
  watch: {
    rollData(val) {
      if (val) {
        this.$nextTick(() => {
          if (val.success) {
            playSound('win');
          } else {
            playSound('fail');
          }
        });
      }
    },
  },
};
</script>

<style scoped>
.duel-roll {
  background: var(--bg-secondary);
  border-radius: 10px;
  padding: 18px;
  margin-bottom: 15px;
  border-left: 4px solid var(--accent-gold);
}

.phase-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1.1rem;
  margin-bottom: 12px;
  text-align: center;
}

.roll-card {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 16px;
  text-align: center;
}

.card-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  margin-bottom: 4px;
}

.card-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin-bottom: 6px;
}

.card-difficulty {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 3px 12px;
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  border-radius: 4px;
}

.dice-area {
  text-align: center;
  margin-bottom: 12px;
}

.dice-row {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 12px;
  min-height: 40px;
}

.face-badge {
  padding: 6px 14px;
  border-radius: 5px;
  font-size: 0.9rem;
  font-weight: 700;
  font-family: 'Cinzel', serif;
  min-width: 32px;
  text-align: center;
}

.face-num { background: #6a4a2a; color: #f5e6cc; border: 1px solid #8a6a2e; }
.face-wild { background: #d4a843; color: #1a1209; font-weight: 900; }

.face-rolling {
  background: #4a3a24;
  color: var(--accent-gold);
  border: 1px solid var(--accent-gold);
  animation: diceShake 0.1s infinite alternate;
}

@keyframes diceShake {
  0% { transform: translateY(-1px) rotate(-2deg); }
  100% { transform: translateY(1px) rotate(2deg); }
}

.btn-roll {
  background: linear-gradient(135deg, #8b2020, #b83030);
  color: #f5e6cc;
  border: 2px solid #d4a843;
  padding: 10px 36px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: 1px;
  transition: all 0.2s ease;
  text-transform: uppercase;
}

.btn-roll:hover {
  background: linear-gradient(135deg, #b83030, #8b2020);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.4);
}

.btn-roll:active { transform: scale(0.97); }

.waiting-text {
  color: var(--text-secondary);
  font-style: italic;
  padding: 12px;
}

.wild-section {
  margin-bottom: 10px;
  padding: 8px;
  background: rgba(212, 168, 67, 0.1);
  border-radius: 6px;
}

.wild-trigger {
  color: #d4a843;
  font-size: 0.85rem;
  font-style: italic;
  margin-bottom: 3px;
}

.roll-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  padding: 10px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  justify-content: center;
}

.roll-total { font-weight: 700; font-size: 1.1rem; }
.roll-pass { color: var(--accent-green); }
.roll-fail { color: var(--accent-red); }
.roll-vs { color: var(--text-secondary); font-style: italic; }
.roll-difficulty { color: var(--text-bright); font-weight: 600; }

.result-badge {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 3px 10px;
  border-radius: 4px;
  font-weight: 700;
}

.badge-success { background: rgba(74, 138, 58, 0.2); color: #4a8a3a; }
.badge-failure { background: rgba(160, 48, 32, 0.2); color: #c0392b; }

.effects-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: center;
}

.effect-badge {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  text-transform: capitalize;
}

.effect-positive { background: rgba(74, 138, 58, 0.15); color: #4a8a3a; }
.effect-negative { background: rgba(160, 48, 32, 0.15); color: #c0392b; }

.no-effects {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  text-align: center;
}

@media (max-width: 768px) {
  .duel-roll { padding: 12px; }
  .face-badge { padding: 4px 10px; font-size: 0.8rem; }
  .roll-summary { flex-direction: column; gap: 6px; }
}
</style>
