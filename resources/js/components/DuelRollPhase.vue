<template>
  <div class="duel-roll">
    <h4 class="phase-title">{{ playerName }}'s Challenge</h4>

    <p v-if="!canRoll && !hasRolled" class="waiting-text">
      Waiting for opponent to roll...
    </p>

    <!-- Item prompt (before rolling) -->
    <template v-if="canRoll && !hasRolled && !isRolling && !itemDecided">
      <div v-if="usableItems.length > 0" class="item-prompt-section">
        <p v-if="!choosingItem" class="item-prompt-text">Use an item before rolling?</p>
        <div v-if="!choosingItem" class="item-prompt-btns">
          <button class="btn-item-yes" @click="choosingItem = true">Yes</button>
          <button class="btn-item-no" @click="$emit('skip-item')">No</button>
        </div>
        <div v-else class="item-choose-list">
          <div
            v-for="pi in usableItems"
            :key="pi.id"
            class="item-choose-card"
            @click="$emit('use-item', pi.id)"
          >
            <span class="item-choose-name">{{ pi.item?.name }}</span>
            <span class="item-choose-effect">{{ itemEffectLabel(pi.item) }}</span>
          </div>
          <button class="btn-item-cancel" @click="choosingItem = false">Cancel</button>
        </div>
      </div>
    </template>

    <!-- Roll Dice button (manual) -->
    <div v-if="canRoll && !hasRolled && !isRolling && itemDecided" class="roll-btn-section">
      <button class="btn-roll-dice" @click="startRolling">Roll Dice</button>
    </div>

    <!-- Wild/ability trigger info -->
    <template v-if="hasRolled">
      <div v-if="rollData.ability_effects && rollData.ability_effects.length" class="wild-section">
        <div v-for="(desc, i) in rollData.ability_effects" :key="i" class="wild-trigger">
          {{ desc }}
        </div>
      </div>
    </template>

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

    <!-- Show assigned cards (1 or 2) -->
    <div v-if="displayCards.length" class="roll-cards">
      <div v-for="(c, idx) in displayCards" :key="idx" class="roll-card">
        <h3 class="card-title">{{ c.title }}</h3>
        <p v-if="!hasRolled" class="card-desc">{{ c.description }}</p>
        <p v-else class="card-desc" :class="cardResultMap[idx]?.success ? 'outcome-success' : 'outcome-failure'">
          {{ cardResultMap[idx]?.success ? (c.positive_flavor || c.description) : (c.negative_flavor || c.description) }}
        </p>
        <span class="card-difficulty">Difficulty {{ c.difficulty }}</span>
        <!-- Post-roll: show actual applied effects -->
        <div v-if="hasRolled && cardEffectsMap[idx]" class="effects-row card-effects">
          <span
            v-for="(val, stat) in cardEffectsMap[idx]"
            :key="stat"
            class="effect-badge"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
        <!-- Pre-roll: show potential positive & negative effects -->
        <template v-if="!hasRolled">
          <div v-if="c.positive_effects && Object.keys(c.positive_effects).length" class="effects-row card-effects">
            <span class="effects-label">On Success:</span>
            <span
              v-for="(val, stat) in c.positive_effects"
              :key="'pos-'+stat"
              class="effect-badge effect-positive"
            >
              {{ stat }}: +{{ val }}
            </span>
          </div>
          <div v-if="c.negative_effects && Object.keys(c.negative_effects).length" class="effects-row card-effects">
            <span class="effects-label">On Failure:</span>
            <span
              v-for="(val, stat) in c.negative_effects"
              :key="'neg-'+stat"
              class="effect-badge effect-negative"
            >
              {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
            </span>
          </div>
        </template>
      </div>
    </div>

    <!-- Post-roll actions -->
    <template v-if="hasRolled">
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
          {{ rerolling ? 'Rerolling...' : 'Skip' }}
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
    diceCount: { type: Number, default: 4 },
    canRoll: { type: Boolean, default: false },
    rollData: { type: Object, default: null },
    ability: { type: Object, default: null },
    abilityUses: { type: Number, default: 0 },
    abilityActivated: { type: Boolean, default: false },
    activatingAbility: { type: Boolean, default: false },
    peekedCards: { type: Array, default: null },
    rerolling: { type: Boolean, default: false },
    needsContinue: { type: Boolean, default: false },
    use3dDice: { type: Boolean, default: false },
    playerItems: { type: Array, default: () => [] },
    itemDecided: { type: Boolean, default: false },
  },
  emits: ['roll', 'use-ability', 'reroll', 'continue', 'use-item', 'skip-item'],
  data() {
    return {
      isRolling: false,
      submitting: false,
      choosingItem: false,
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
      return this.hasRolled && this.isRerollAbility
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
      // Fall back to cards from rollData (for opponent view)
      if (this.rollData?.cards) {
        return this.rollData.cards.map(cr => cr.card).filter(Boolean);
      }
      if (this.rollData?.card) return [this.rollData.card];
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
    cardResultMap() {
      if (!this.hasRolled || !this.cardResults.length) return {};
      const map = {};
      this.cardResults.forEach((cr, idx) => {
        map[idx] = cr;
      });
      return map;
    },
    cardEffectsMap() {
      if (!this.hasRolled || !this.cardResults.length) return {};
      const map = {};
      this.cardResults.forEach((cr, idx) => {
        if (cr.effects && Object.keys(cr.effects).length) {
          map[idx] = cr.effects;
        }
      });
      return map;
    },
    combinedEffects() {
      if (!this.rollData) return {};
      if (this.rollData.combined_effects) return this.rollData.combined_effects;
      if (this.rollData.effects) return this.rollData.effects;
      return {};
    },
    usableItems() {
      return (this.playerItems || []).filter(pi => !pi.is_used);
    },
  },
  methods: {
    startRolling() {
      if (this.isRolling || this.hasRolled || this.submitting) return;

      if (!this.use3dDice) {
        playSound('dice');
      }

      this.isRolling = true;
      this.submitting = true;

      this.$emit('roll');
    },
    itemEffectLabel(item) {
      if (!item?.effect) return '';
      const type = item.effect.bonus_type || '';
      const value = item.effect.bonus_value ?? 0;
      switch (type) {
        case 'roll_bonus': return `+${value} to roll`;
        case 'difficulty_reduction': return `-${Math.abs(value)} difficulty`;
        case 'stat_boost': return `+${value} ${item.effect.stat || 'stat'}`;
        case 'heal_die': return 'Recover a lost die';
        case 'shield_negative': return 'Block negative effects';
        case 'debuff_roll': return `${value} to opponent roll`;
        case 'increase_difficulty': return `+${Math.abs(value)} opponent difficulty`;
        case 'peek_cards': return 'Peek at opponent cards';
        case 'steal_stat': return `Steal ${value} stat point`;
        default: return item.description || 'Use this item';
      }
    },
  },
  watch: {
    rollData(val) {
      if (val) {
        this.isRolling = false;
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
  padding: 14px;
  margin-bottom: 12px;
  border-left: 4px solid var(--accent-gold);
}

.phase-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  margin-bottom: 10px;
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
  padding: 10px;
  text-align: center;
  flex: 1;
  min-width: 140px;
  max-width: 250px;
}

.card-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  margin-bottom: 4px;
}

.card-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.78rem;
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

.outcome-success {
  color: #4a8a3a;
  font-style: italic;
}

.outcome-failure {
  color: #c0392b;
  font-style: italic;
}

.card-effects {
  margin-top: 6px;
}

.waiting-text {
  color: var(--text-secondary);
  font-style: italic;
  padding: 12px;
  text-align: center;
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

.effects-label {
  font-size: 0.68rem;
  color: var(--text-secondary);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

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

/* Item prompt */
.item-prompt-section {
  text-align: center;
  margin-bottom: 12px;
  padding: 12px;
  background: rgba(212, 168, 67, 0.08);
  border: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 8px;
}

.item-prompt-text {
  color: var(--text-primary);
  font-size: 0.9rem;
  margin-bottom: 10px;
}

.item-prompt-btns {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.btn-item-yes {
  background: linear-gradient(135deg, #8b4513, #6b3410);
  color: var(--accent-gold);
  border: 1px solid var(--accent-gold);
  padding: 8px 24px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-item-yes:hover { background: linear-gradient(135deg, #a05a20, #8b4513); }

.btn-item-no {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-gold, #6b5b3a);
  color: var(--text-secondary);
  padding: 8px 24px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-item-no:hover { border-color: var(--accent-gold); color: var(--accent-gold); }

.item-choose-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.item-choose-card {
  background: rgba(212, 168, 67, 0.08);
  border: 1px solid var(--border-gold, #6b5b3a);
  border-radius: 8px;
  padding: 10px 16px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
  max-width: 280px;
  text-align: center;
}

.item-choose-card:hover {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
}

.item-choose-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.85rem;
  font-weight: 600;
}

.item-choose-effect {
  color: var(--text-secondary);
  font-size: 0.75rem;
  font-style: italic;
}

.btn-item-cancel {
  background: none;
  border: 1px solid var(--border-gold, #6b5b3a);
  color: var(--text-secondary);
  padding: 6px 16px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.78rem;
  cursor: pointer;
}

.btn-item-cancel:hover { color: var(--accent-gold); border-color: var(--accent-gold); }

/* Roll Dice button */
.roll-btn-section {
  text-align: center;
  margin-bottom: 14px;
}

.btn-roll-dice {
  background: linear-gradient(135deg, #8b4513, #6b3410);
  color: var(--accent-gold, #c9a84c);
  border: 2px solid var(--accent-gold, #c9a84c);
  padding: 12px 40px;
  border-radius: 8px;
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
}

.btn-roll-dice:hover {
  background: linear-gradient(135deg, #a05a20, #8b4513);
  box-shadow: 0 4px 20px rgba(212, 168, 67, 0.3);
}

@media (max-width: 768px) {
  .duel-roll { padding: 12px; }
  .btn-ability, .btn-reroll { font-size: 0.75rem; padding: 8px 12px; }
  .roll-cards { flex-direction: column; align-items: center; }
  .roll-card { max-width: 100%; }
  .btn-roll-dice { padding: 10px 30px; font-size: 0.9rem; }
}
</style>
