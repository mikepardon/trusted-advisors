<template>
  <div class="duel-roll">
    <h4 class="phase-title">{{ playerName }}'s Challenge</h4>

    <!-- Show assigned cards (1 or 2) -->
    <div v-if="displayCards.length" class="roll-cards">
      <div v-for="(c, idx) in displayCards" :key="idx" class="roll-card">
        <h3 class="card-title">{{ c.title }}</h3>
        <p class="card-desc">{{ c.description }}</p>
        <span class="card-difficulty">Difficulty {{ c.difficulty }}</span>
      </div>
    </div>

    <!-- Ability activation (before roll) — only for NON-reroll abilities -->
    <div v-if="canRoll && !hasRolled && !isRolling && ability && abilityUses > 0 && !isRerollAbility" class="ability-section">
      <button
        v-if="!abilityActivated"
        class="btn-ability"
        :disabled="activatingAbility"
        @click="$emit('use-ability')"
      >
        &#9733; {{ ability.name }}: {{ ability.description }}
        <span class="ability-uses-badge">({{ abilityUses }} use{{ abilityUses > 1 ? 's' : '' }} left)</span>
      </button>
      <div v-else class="ability-activated">
        &#9733; {{ ability.name }} activated!
      </div>
    </div>

    <!-- Shadow peek (if shadow ability was used) -->
    <div v-if="peekedCards && peekedCards.length" class="peek-section">
      <p class="peek-title">&#128065; Glimpse of the Future:</p>
      <div v-for="(pc, i) in peekedCards" :key="i" class="peek-card">
        {{ pc.title }} (Difficulty {{ pc.difficulty }})
      </div>
    </div>

    <!-- Dice rolling area -->
    <div class="dice-area">
      <div class="dice-row">
        <template v-if="hasRolled">
          <span
            v-for="(roll, ri) in rollData.rolls"
            :key="ri"
            class="face-badge"
            :class="[
              roll.face === 'WILD' ? 'face-wild' : 'face-num',
              roll.rerolled ? 'face-rerolled' : '',
            ]"
          >
            {{ roll.face === 'WILD' ? 'W ' + roll.value : roll.face }}
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

      <p v-if="!canRoll && !hasRolled" class="waiting-text">
        Waiting for opponent to roll...
      </p>
    </div>

    <!-- Roll results per card -->
    <template v-if="hasRolled">
      <div v-if="rollData.ability_effects && rollData.ability_effects.length" class="wild-section">
        <div v-for="(desc, i) in rollData.ability_effects" :key="i" class="wild-trigger">
          {{ desc }}
        </div>
      </div>

      <div class="roll-total-banner">
        Roll: <strong>{{ rollData.total_roll }}</strong>
        <span v-if="totalDifficulty" class="roll-vs">vs</span>
        <span v-if="totalDifficulty" class="roll-difficulty">Difficulty: <strong>{{ totalDifficulty }}</strong></span>
        <span v-if="totalDifficulty" class="result-badge roll-result-badge" :class="rollData.total_roll >= totalDifficulty ? 'badge-success' : 'badge-failure'">
          {{ rollData.total_roll >= totalDifficulty ? 'SUCCESS' : 'FAILURE' }}
        </span>
      </div>

      <!-- Per-card results -->
      <div v-for="(cr, idx) in cardResults" :key="idx" class="card-result">
        <div class="card-result-header">
          <span class="card-result-name">{{ cr.card?.title || 'Card ' + (idx + 1) }}</span>
          <span class="card-result-diff">Difficulty {{ cr.difficulty }}</span>
        </div>
        <div v-if="Object.keys(cr.effects || {}).length" class="effects-row">
          <span
            v-for="(val, stat) in cr.effects"
            :key="stat"
            class="effect-badge"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
      </div>

      <!-- Combined effects summary -->
      <div v-if="Object.keys(combinedEffects).length" class="combined-effects">
        <p class="combined-label">Combined Effects:</p>
        <div class="effects-row">
          <span
            v-for="(val, stat) in combinedEffects"
            :key="stat"
            class="effect-badge"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
      </div>
      <div v-else-if="!cardResults.length" class="no-effects">
        No stat changes this round.
      </div>

      <!-- Post-roll reroll option (for rally/gamble abilities) -->
      <div v-if="showRerollOption" class="reroll-section">
        <button
          class="btn-reroll"
          :disabled="rerolling"
          @click="$emit('reroll')"
        >
          &#9733; {{ ability.name }}: {{ rerollLabel }}
          <span class="ability-uses-badge">({{ abilityUses }} use{{ abilityUses > 1 ? 's' : '' }} left)</span>
        </button>
        <button
          class="btn-continue"
          :disabled="rerolling"
          @click="$emit('continue')"
        >
          {{ rerolling ? 'Rerolling...' : 'Continue' }}
        </button>
      </div>

      <!-- Continue button (when no reroll available or already rerolled) -->
      <div v-else-if="canRoll && needsContinue" class="reroll-section">
        <button class="btn-continue" @click="$emit('continue')">
          Continue
        </button>
      </div>
    </template>
  </div>
</template>

<script>
import { playSound } from '../sounds';

export default {
  name: 'DuelRollPhase',
  props: {
    cards: { type: Array, default: () => [] },
    card: { type: Object, default: null },
    playerName: { type: String, default: 'Player' },
    canRoll: { type: Boolean, default: false },
    rollData: { type: Object, default: null },
    ability: { type: Object, default: null },
    abilityUses: { type: Number, default: 0 },
    abilityActivated: { type: Boolean, default: false },
    activatingAbility: { type: Boolean, default: false },
    peekedCards: { type: Array, default: null },
    rerolling: { type: Boolean, default: false },
    needsContinue: { type: Boolean, default: false },
  },
  emits: ['roll', 'use-ability', 'reroll', 'continue'],
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
    isRerollAbility() {
      if (!this.ability) return false;
      return ['rally', 'gamble'].includes(this.ability.name);
    },
    showRerollOption() {
      return this.hasRolled && this.canRoll && this.isRerollAbility
        && this.abilityUses > 0 && !this.abilityActivated && !this.rollData?.rerolled;
    },
    rerollLabel() {
      if (!this.ability) return '';
      if (this.ability.name === 'rally') return 'Reroll lowest die?';
      if (this.ability.name === 'gamble') return 'Reroll all dice?';
      return this.ability.description;
    },
    displayCards() {
      if (this.cards && this.cards.length) {
        return this.cards.map(c => c.card || c);
      }
      if (this.card) return [this.card];
      return [];
    },
    cardResults() {
      if (!this.rollData) return [];
      if (this.rollData.cards) return this.rollData.cards;
      if (this.rollData.card) {
        return [{
          card: this.rollData.card,
          difficulty: this.rollData.difficulty,
          success: this.rollData.success,
          effects: this.rollData.effects || {},
        }];
      }
      return [];
    },
    totalDifficulty() {
      if (!this.cardResults.length) return 0;
      return this.cardResults.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
    },
    combinedEffects() {
      if (!this.rollData) return {};
      if (this.rollData.combined_effects) return this.rollData.combined_effects;
      if (this.rollData.effects) return this.rollData.effects;
      return {};
    },
  },
  methods: {
    scheduleAutoRoll() {
      const hasPreRollAbility = this.ability && this.abilityUses > 0 && !this.abilityActivated && !this.isRerollAbility;
      const delay = hasPreRollAbility ? 3000 : 600;
      this._autoRollTimer = setTimeout(() => this.startRolling(), delay);
    },
    startRolling() {
      if (this._autoRollTimer) clearTimeout(this._autoRollTimer);
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
  mounted() {
    if (this.canRoll && !this.hasRolled && !this.isRolling) {
      this.scheduleAutoRoll();
    }
  },
  watch: {
    canRoll(val) {
      if (val && !this.hasRolled && !this.isRolling) {
        this.scheduleAutoRoll();
      }
    },
    rollData(val) {
      if (val) {
        this.$nextTick(() => {
          const anySuccess = (val.cards || []).some(c => c.success) || val.success;
          if (anySuccess) {
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

.roll-cards {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  justify-content: center;
}

.roll-card {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 8px;
  padding: 12px;
  text-align: center;
  flex: 1;
  min-width: 140px;
  max-width: 250px;
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

.face-rerolled {
  animation: rerollGlow 0.6s ease;
}

@keyframes rerollGlow {
  0% { box-shadow: 0 0 12px rgba(138, 96, 192, 0.8); }
  100% { box-shadow: none; }
}

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

.roll-total-banner {
  text-align: center;
  font-size: 1.15rem;
  color: var(--text-bright);
  padding: 10px 12px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}

.roll-vs {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
}

.roll-difficulty {
  font-size: 1.05rem;
}

.roll-result-badge {
  margin-left: 4px;
}

.card-result {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 6px;
  padding: 10px;
  margin-bottom: 8px;
}

.card-result-header {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
  margin-bottom: 6px;
}

.card-result-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
}

.card-result-diff {
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.result-badge {
  font-size: 0.7rem;
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

.combined-effects {
  margin-top: 8px;
  padding: 8px;
  background: rgba(212, 168, 67, 0.08);
  border-radius: 6px;
}

.combined-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-align: center;
  margin-bottom: 4px;
  font-weight: 600;
}

.no-effects {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  text-align: center;
}

/* Ability section (pre-roll for non-reroll abilities) */
.ability-section {
  text-align: center;
  margin-bottom: 12px;
}

.btn-ability {
  background: linear-gradient(135deg, #2a1a40, #4a2a6a);
  color: #d4a0ff;
  border: 2px solid #8a60c0;
  padding: 10px 16px;
  border-radius: 8px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
  text-align: center;
  line-height: 1.4;
}

.btn-ability:hover:not(:disabled) {
  background: linear-gradient(135deg, #3a2a50, #5a3a7a);
  box-shadow: 0 0 15px rgba(138, 96, 192, 0.4);
  border-color: #b080e0;
}

.btn-ability:disabled { opacity: 0.5; cursor: not-allowed; }

.ability-uses-badge {
  font-size: 0.7rem;
  opacity: 0.7;
}

.ability-activated {
  color: #d4a0ff;
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  font-weight: 700;
  padding: 8px;
  background: rgba(138, 96, 192, 0.15);
  border: 1px solid rgba(138, 96, 192, 0.3);
  border-radius: 6px;
}

/* Post-roll reroll section */
.reroll-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 14px;
  text-align: center;
}

.btn-reroll {
  background: linear-gradient(135deg, #2a1a40, #4a2a6a);
  color: #d4a0ff;
  border: 2px solid #8a60c0;
  padding: 10px 16px;
  border-radius: 8px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
  text-align: center;
  line-height: 1.4;
}

.btn-reroll:hover:not(:disabled) {
  background: linear-gradient(135deg, #3a2a50, #5a3a7a);
  box-shadow: 0 0 15px rgba(138, 96, 192, 0.4);
  border-color: #b080e0;
}

.btn-reroll:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-continue {
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
  padding: 8px 20px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-continue:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
}

.btn-continue:disabled { opacity: 0.5; cursor: not-allowed; }

/* Shadow peek */
.peek-section {
  background: rgba(138, 96, 192, 0.1);
  border: 1px solid rgba(138, 96, 192, 0.25);
  border-radius: 6px;
  padding: 8px 12px;
  margin-bottom: 12px;
}

.peek-title {
  color: #d4a0ff;
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 4px;
}

.peek-card {
  color: var(--text-primary);
  font-size: 0.8rem;
  padding: 2px 0;
}

@media (max-width: 768px) {
  .duel-roll { padding: 12px; }
  .face-badge { padding: 4px 10px; font-size: 0.8rem; }
  .btn-ability, .btn-reroll { font-size: 0.75rem; padding: 8px 12px; }
  .roll-cards { flex-direction: column; align-items: center; }
  .roll-card { max-width: 100%; }
}
</style>
