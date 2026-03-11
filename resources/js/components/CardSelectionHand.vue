<template>
  <div class="hand-container">
    <div v-if="loading" class="loading">Loading hand...</div>

    <div v-else-if="hasAssigned && !singlePlayer" class="hand-assigned">
      <div class="assigned-check">&#10003;</div>
      <p class="assigned-msg">Decision recorded. Waiting for the rest of the council...</p>
    </div>

    <!-- ===== MOBILE: Swiper carousel ===== -->
    <template v-else-if="isMobile">
      <div class="swiper-hand">
        <Swiper
          :modules="swiperModules"
          :effect="'cards'"
          :grab-cursor="true"
          :cards-effect="{ perSlideOffset: 8, perSlideRotate: 2, rotate: true, slideShadows: false }"
          :style="{ overflow: 'visible' }"
          @swiper="onSwiper"
          @slideChange="onSlideChange"
        >
          <SwiperSlide v-for="item in cards" :key="item.hand_id"
            :class="{ 'slide-hidden': singlePlayer && selectedPositive !== null && selectedPositive !== item.hand_id }"
          >
            <div
              class="parchment-card"
              :class="{
                'card-acting': selectedPositive === item.hand_id,
                'card-unattended': selectedPositive !== null && selectedPositive !== item.hand_id,
              }"
              @click="isResolving(item) && resolvePhase === 'results' ? $emit('continue') : selectAndConfirm(item.hand_id)"
            >
              <!-- Special effect badges (hide when resolving) -->
              <div v-if="hasSpecialEffects(item.card) && !isResolving(item)" class="special-badges">
                <span v-if="item.card.positive_effects?.reveal_stats" class="special-badge badge-foresight">Foresight</span>
                <span v-if="item.card.positive_effects?.draw_item" class="special-badge badge-item">Draws Item</span>
                <span v-if="item.card.positive_effects?.recover_die" class="special-badge badge-recover">Recover Die</span>
                <span v-if="item.card.negative_effects?.lose_die" class="special-badge badge-lose">Lose a Die</span>
                <span v-if="item.card.negative_effects?.discard_item" class="special-badge badge-discard">Lose Item</span>
              </div>

              <!-- Ribbon (hide when resolving or single-player) -->
              <template v-if="!isResolving(item) && !singlePlayer">
                <div v-if="selectedPositive === item.hand_id" class="card-ribbon acting">
                  Acting on this
                </div>
                <div v-else-if="selectedPositive !== null" class="card-ribbon unattended">
                  Left unattended
                </div>
              </template>

              <!-- Difficulty watermark -->
              <span v-if="!isResolving(item) || resolvePhase === 'rolling'" class="parchment-difficulty">{{ item.card.difficulty }}</span>

              <!-- Card text (visible during normal view + rolling phase) -->
              <p v-if="!isResolving(item) || resolvePhase === 'rolling'" class="parchment-desc">{{ item.card.description }}</p>
              <p v-if="item.card.question && (!isResolving(item) || resolvePhase === 'rolling')" class="parchment-question">{{ item.card.question }}</p>

              <!-- Normal card content (not resolving) -->
              <template v-if="!isResolving(item)">
                <h3 v-if="!singlePlayer" class="parchment-title">{{ item.card.title }}</h3>

                <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

                <div class="outcome-section outcome-top">
                  <p class="outcome-label outcome-label-positive">If you act</p>
                  <div class="outcome-arrows">
                    <span
                      v-for="arrow in effectArrows(item.card.positive_effects)"
                      :key="'p-' + arrow.stat"
                      class="arrow-chip"
                      :class="arrow.direction === 'up' ? 'arrow-up' : 'arrow-down'"
                    >
                      {{ formatStatName(arrow.stat) }}
                    </span>
                  </div>
                </div>

                <div class="outcome-section outcome-bottom">
                  <p class="outcome-label outcome-label-negative">If ignored</p>
                  <div class="outcome-arrows">
                    <span
                      v-for="arrow in effectArrows(item.card.negative_effects)"
                      :key="'n-' + arrow.stat"
                      class="arrow-chip"
                      :class="arrow.direction === 'up' ? 'arrow-up' : 'arrow-down'"
                    >
                      {{ formatStatName(arrow.stat) }}
                    </span>
                  </div>
                </div>

                <!-- Card redraw button -->
                <button
                  v-if="redraws > 0 && selectedPositive === null"
                  class="btn-redraw"
                  @click.stop="$emit('redraw', item.hand_id)"
                >Redraw</button>
              </template>

              <!-- Rolling indicator (single-player) -->
              <div v-if="isResolving(item) && resolvePhase === 'rolling'" class="resolve-rolling">Rolling the dice...</div>

              <!-- Results (single-player) -->
              <template v-if="isResolving(item) && resolvePhase === 'results'">
                <div class="resolve-dice">
                  <div class="resolve-dice-row">
                    <span v-for="(roll, ri) in diceRolls" :key="ri" class="resolve-die" :class="{ 'resolve-die-wild': roll.face === 'WILD' }">
                      {{ roll.face === 'WILD' ? 'W' : roll.value }}
                    </span>
                    <span class="resolve-dice-total">= {{ resolveData.positivePhase.total_roll }}</span>
                  </div>
                  <div class="resolve-outcome" :class="resolveData.positivePhase.success ? 'outcome-success' : 'outcome-fail'">
                    {{ resolveData.positivePhase.success ? 'Success!' : 'Failed!' }}
                  </div>
                </div>
                <div class="resolve-flavor">
                  <p class="resolve-flavor-positive">{{ positiveFlavor }}</p>
                  <template v-if="negativeFlavors.length">
                    <p class="resolve-flavor-meanwhile">Meanwhile...</p>
                    <p v-for="(f, fi) in negativeFlavors" :key="fi" class="resolve-flavor-negative">{{ f }}</p>
                  </template>
                </div>
                <div v-if="resolveSpecialEffects.length" class="resolve-specials">
                  <p v-for="(eff, ei) in resolveSpecialEffects" :key="ei" class="resolve-special">{{ eff.description }}</p>
                </div>
                <p class="resolve-tap-continue" @click="$emit('continue')">
                  {{ resolveData.gameOver ? 'Tap to view results' : 'Click to continue' }}
                </p>
              </template>
            </div>
          </SwiperSlide>
        </Swiper>
      </div>
    </template>

    <!-- ===== DESKTOP: Side-by-side flex ===== -->
    <div v-else class="hand-cards">
      <div
        v-for="item in cards"
        :key="item.hand_id"
        v-show="!singlePlayer || selectedPositive === null || selectedPositive === item.hand_id"
        class="parchment-card"
        :class="{
          'card-acting': selectedPositive === item.hand_id,
          'card-unattended': selectedPositive !== null && selectedPositive !== item.hand_id,
        }"
        @click="isResolving(item) && resolvePhase === 'results' ? $emit('continue') : selectAndConfirm(item.hand_id)"
        @mouseenter="onCardHover(item)"
        @mouseleave="onCardLeave"
      >
        <!-- Special effect badges (hide when resolving) -->
        <div v-if="hasSpecialEffects(item.card) && !isResolving(item)" class="special-badges">
          <span v-if="item.card.positive_effects?.reveal_stats" class="special-badge badge-foresight">Foresight</span>
          <span v-if="item.card.positive_effects?.draw_item" class="special-badge badge-item">Draws Item</span>
          <span v-if="item.card.positive_effects?.recover_die" class="special-badge badge-recover">Recover Die</span>
          <span v-if="item.card.negative_effects?.lose_die" class="special-badge badge-lose">Lose a Die</span>
          <span v-if="item.card.negative_effects?.discard_item" class="special-badge badge-discard">Lose Item</span>
        </div>

        <!-- Ribbon (hide when resolving or single-player) -->
        <template v-if="!isResolving(item) && !singlePlayer">
          <div v-if="selectedPositive === item.hand_id" class="card-ribbon acting">
            Acting on this
          </div>
          <div v-else-if="selectedPositive !== null" class="card-ribbon unattended">
            Left unattended
          </div>
        </template>

        <!-- Difficulty watermark -->
        <span v-if="!isResolving(item) || resolvePhase === 'rolling'" class="parchment-difficulty">{{ item.card.difficulty }}</span>

        <!-- Card text (visible during normal view + rolling phase) -->
        <p v-if="!isResolving(item) || resolvePhase === 'rolling'" class="parchment-desc">{{ item.card.description }}</p>
        <p v-if="item.card.question && (!isResolving(item) || resolvePhase === 'rolling')" class="parchment-question">{{ item.card.question }}</p>

        <!-- Normal card content (not resolving) -->
        <template v-if="!isResolving(item)">
          <h3 v-if="!singlePlayer" class="parchment-title">{{ item.card.title }}</h3>

          <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

          <div class="outcome-section outcome-top">
            <p class="outcome-label outcome-label-positive">If you act</p>
            <div class="outcome-arrows">
              <span
                v-for="arrow in effectArrows(item.card.positive_effects)"
                :key="'p-' + arrow.stat"
                class="arrow-chip"
                :class="arrow.direction === 'up' ? 'arrow-up' : 'arrow-down'"
              >
                {{ formatStatName(arrow.stat) }}
              </span>
            </div>
          </div>

          <div class="outcome-section outcome-bottom">
            <p class="outcome-label outcome-label-negative">If ignored</p>
            <div class="outcome-arrows">
              <span
                v-for="arrow in effectArrows(item.card.negative_effects)"
                :key="'n-' + arrow.stat"
                class="arrow-chip"
                :class="arrow.direction === 'up' ? 'arrow-up' : 'arrow-down'"
              >
                {{ formatStatName(arrow.stat) }}
              </span>
            </div>
          </div>

          <!-- Card redraw button -->
          <button
            v-if="redraws > 0 && selectedPositive === null"
            class="btn-redraw"
            @click.stop="$emit('redraw', item.hand_id)"
          >Redraw</button>
        </template>

        <!-- Rolling indicator (single-player) -->
        <div v-if="isResolving(item) && resolvePhase === 'rolling'" class="resolve-rolling">Rolling the dice...</div>

        <!-- Results (single-player) -->
        <template v-if="isResolving(item) && resolvePhase === 'results'">
          <div class="resolve-dice">
            <div class="resolve-dice-row">
              <span v-for="(roll, ri) in diceRolls" :key="ri" class="resolve-die" :class="{ 'resolve-die-wild': roll.face === 'WILD' }">
                {{ roll.face === 'WILD' ? 'W' : roll.value }}
              </span>
              <span class="resolve-dice-total">= {{ resolveData.positivePhase.total_roll }}</span>
            </div>
            <div class="resolve-outcome" :class="resolveData.positivePhase.success ? 'outcome-success' : 'outcome-fail'">
              {{ resolveData.positivePhase.success ? 'Success!' : 'Failed!' }}
            </div>
          </div>
          <div class="resolve-flavor">
            <p class="resolve-flavor-positive">{{ positiveFlavor }}</p>
            <template v-if="negativeFlavors.length">
              <p class="resolve-flavor-meanwhile">Meanwhile...</p>
              <p v-for="(f, fi) in negativeFlavors" :key="fi" class="resolve-flavor-negative">{{ f }}</p>
            </template>
          </div>
          <div v-if="resolveSpecialEffects.length" class="resolve-specials">
            <p v-for="(eff, ei) in resolveSpecialEffects" :key="ei" class="resolve-special">{{ eff.description }}</p>
          </div>
          <p class="resolve-tap-continue" @click="$emit('continue')">
            {{ resolveData.gameOver ? 'Tap to view results' : 'Click to continue' }}
          </p>
        </template>
      </div>
    </div>

    <!-- Redraws remaining indicator -->
    <div v-if="redraws > 0 && selectedPositive === null" class="redraws-remaining">
      {{ redraws }} redraw{{ redraws > 1 ? 's' : '' }} remaining
    </div>
  </div>
</template>

<script>
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCards } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-cards';
import { playSound } from '../sounds';
import dddiceService from '../dddiceService';

const STAT_KEYS = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
const SPECIAL_KEYS_POSITIVE = ['grant_item_id', 'draw_item', 'recover_die', 'remove_curse', 'draw_curse', 'bonus_score', 'end_game_modifier', 'reveal_stats'];
const SPECIAL_KEYS_NEGATIVE = ['lose_die', 'discard_item', 'draw_item', 'draw_curse', 'bonus_score', 'end_game_modifier'];

export default {
  name: 'CardSelectionHand',
  components: { Swiper, SwiperSlide },
  props: {
    cards: { type: Array, default: () => [] },
    hasAssigned: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    redraws: { type: Number, default: 0 },
    showPreviews: { type: Boolean, default: false },
    singlePlayer: { type: Boolean, default: false },
    resolveData: { type: Object, default: null },
  },
  emits: ['assign', 'preview', 'redraw', 'continue', 'resolve-shown'],
  data() {
    return {
      selectedPositive: null,
      isMobile: false,
      activeSlideIndex: 0,
      swiperInstance: null,
      mediaQuery: null,
      hoveredId: null,
      resolvePhase: null,
    };
  },
  computed: {
    swiperModules() {
      return [EffectCards];
    },
    activeSlideHandId() {
      if (this.cards.length === 0) return null;
      const card = this.cards[this.activeSlideIndex];
      return card ? card.hand_id : null;
    },
    hasForesight() {
      return this.cards.some(c => c.card.positive_effects?.reveal_stats);
    },
    previewsEnabled() {
      return this.showPreviews || this.hasForesight;
    },
    selectedCard() {
      if (!this.selectedPositive) return null;
      return this.cards.find(c => c.hand_id === this.selectedPositive) || null;
    },
    diceRolls() {
      if (!this.resolveData?.positivePhase?.dice_results) return [];
      const rolls = [];
      for (const dr of this.resolveData.positivePhase.dice_results) {
        if (dr.rolls) rolls.push(...dr.rolls);
      }
      return rolls;
    },
    positiveFlavor() {
      if (!this.selectedCard) return '';
      return this.resolveData?.positivePhase?.success
        ? (this.selectedCard.card.positive_flavor || '')
        : (this.selectedCard.card.negative_flavor || '');
    },
    negativeFlavors() {
      if (!this.selectedPositive) return [];
      return this.cards
        .filter(c => c.hand_id !== this.selectedPositive && c.card.negative_flavor)
        .map(c => c.card.negative_flavor);
    },
    allEffects() {
      const merged = {};
      const combined = this.resolveData?.combinedEffects || {};
      const events = this.resolveData?.eventEffects || {};
      for (const [stat, val] of Object.entries(combined)) {
        if (typeof val === 'number') merged[stat] = (merged[stat] || 0) + val;
      }
      for (const [stat, val] of Object.entries(events)) {
        if (typeof val === 'number') merged[stat] = (merged[stat] || 0) + val;
      }
      // Filter out zero values
      const result = {};
      for (const [stat, val] of Object.entries(merged)) {
        if (val !== 0) result[stat] = val;
      }
      return result;
    },
    resolveSpecialEffects() {
      if (!this.resolveData?.specialEffects) return [];
      return this.resolveData.specialEffects.filter(e => e.description);
    },
  },
  mounted() {
    this.mediaQuery = window.matchMedia('(max-width: 768px)');
    this.isMobile = this.mediaQuery.matches;
    this.mediaQuery.addEventListener('change', this.onMediaChange);
    if (this.isMobile && this.cards.length) {
      this.$nextTick(() => {
        if (this.previewsEnabled) this.emitPreview(this.cards[0]);
      });
    }
  },
  beforeUnmount() {
    if (this.mediaQuery) {
      this.mediaQuery.removeEventListener('change', this.onMediaChange);
    }
  },
  methods: {
    onMediaChange(e) {
      this.isMobile = e.matches;
    },
    onSwiper(swiper) {
      this.swiperInstance = swiper;
    },
    onSlideChange(swiper) {
      this.activeSlideIndex = swiper.activeIndex;
      if (this.selectedPositive !== null) return;
      const item = this.cards[swiper.activeIndex];
      if (item && this.previewsEnabled) this.emitPreview(item);
    },
    selectAndConfirm(handId) {
      if (this.selectedPositive !== null) return;
      playSound('clickCard');
      this.selectedPositive = handId;
      // Ensure Swiper shows the selected card on top
      if (this.singlePlayer && this.swiperInstance) {
        const idx = this.cards.findIndex(c => c.hand_id === handId);
        if (idx >= 0) this.swiperInstance.slideTo(idx, 300);
      }
      this.$emit('preview', null);
      const negativeIds = this.cards
        .filter(c => c.hand_id !== handId)
        .map(c => c.hand_id);
      if (negativeIds.length > 0) {
        this.$emit('assign', {
          positive_hand_id: handId,
          negative_hand_ids: negativeIds,
        });
      }
    },
    emitPreview(item) {
      if (!item) {
        this.$emit('preview', null);
        return;
      }
      const net = {};
      const pos = this.filterStatEffects(item.card.positive_effects);
      for (const [stat, val] of Object.entries(pos)) {
        net[stat] = (net[stat] || 0) + val;
      }
      const otherCards = this.cards.filter(c => c.hand_id !== item.hand_id);
      for (const other of otherCards) {
        const neg = this.filterNegativeEffects(other.card.negative_effects);
        for (const [stat, val] of Object.entries(neg)) {
          net[stat] = (net[stat] || 0) + val;
        }
      }
      const positive = {};
      const negative = {};
      for (const [stat, val] of Object.entries(net)) {
        if (val > 0) positive[stat] = val;
        else if (val < 0) negative[stat] = val;
      }
      if (!Object.keys(positive).length && !Object.keys(negative).length) {
        this.$emit('preview', null);
        return;
      }
      this.$emit('preview', { positive, negative });
    },
    onCardHover(item) {
      if (this.selectedPositive !== null) return;
      this.hoveredId = item.hand_id;
      if (this.previewsEnabled) {
        this.emitPreview(item);
      }
    },
    onCardLeave() {
      this.hoveredId = null;
      this.$emit('preview', null);
    },
    filterStatEffects(effects) {
      if (!effects) return {};
      const result = {};
      for (const [key, val] of Object.entries(effects)) {
        if (STAT_KEYS.includes(key)) {
          result[key] = val;
        }
      }
      return result;
    },
    filterNegativeEffects(effects) {
      if (!effects) return {};
      const result = {};
      for (const [key, val] of Object.entries(effects)) {
        if (STAT_KEYS.includes(key)) {
          result[key] = val;
        }
      }
      return result;
    },
    hasSpecialEffects(card) {
      const pos = card.positive_effects || {};
      const neg = card.negative_effects || {};
      return pos.draw_item || pos.recover_die || pos.reveal_stats || neg.lose_die || neg.discard_item;
    },
    isResolving(item) {
      return this.resolveData && this.selectedPositive === item.hand_id;
    },
    isNegativeSelected(handId) {
      return this.selectedPositive !== null && this.selectedPositive !== handId;
    },
    effectArrows(effects) {
      if (!effects) return [];
      const arrows = [];
      for (const stat of STAT_KEYS) {
        const val = effects[stat];
        if (val && val !== 0) {
          const direction = val > 0 ? 'up' : 'down';
          const magnitude = Math.abs(val) >= 3 ? 2 : 1;
          arrows.push({ stat, direction, magnitude, value: val });
        }
      }
      return arrows;
    },
    arrowSymbol(arrow) {
      if (this.hasForesight) {
        return arrow.value > 0 ? `+${arrow.value}` : `${arrow.value}`;
      }
      if (arrow.direction === 'up') {
        return arrow.magnitude >= 2 ? '\u2191\u2191' : '\u2191';
      }
      return arrow.magnitude >= 2 ? '\u2193\u2193' : '\u2193';
    },
    formatStatName(stat) {
      return stat.charAt(0).toUpperCase() + stat.slice(1);
    },
    statEmoji(stat) {
      const emojis = { wealth: '\u{1FA99}', influence: '\u{1F451}', security: '\u{1F6E1}\uFE0F', religion: '\u26EA', food: '\u{1F33E}', happiness: '\u{1F60A}' };
      return emojis[stat] || '';
    },
  },
  watch: {
    async resolveData(val) {
      if (val) {
        this.resolvePhase = 'rolling';
        const use3D = dddiceService.isReady();
        if (use3D) {
          const diceSpecs = this.diceRolls.map(roll => ({
            theme: 'dddice-standard',
            value: roll.value,
          }));
          await dddiceService.roll(diceSpecs);
        } else {
          playSound('dice');
          await new Promise(r => setTimeout(r, 1500));
        }
        if (this.resolveData) {
          this.resolvePhase = 'results';
          this.$emit('resolve-shown');
        }
      } else {
        this.resolvePhase = null;
      }
    },
    cards(newCards) {
      this.selectedPositive = null;
      this.activeSlideIndex = 0;
      if (this.swiperInstance) {
        this.$nextTick(() => {
          this.swiperInstance.slideTo(0, 0);
        });
      }
      if (this.isMobile && newCards?.length) {
        this.$nextTick(() => {
          if (this.previewsEnabled) this.emitPreview(newCards[0]);
        });
      }
    },
  },
};
</script>

<style scoped>
.hand-container {
  margin-bottom: 20px;
  overflow: hidden;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
  padding: 30px;
}

.hand-assigned {
  text-align: center;
  padding: 40px 20px;
}

.assigned-check {
  font-size: 2.5rem;
  color: var(--accent-green);
  margin-bottom: 8px;
}

.assigned-msg {
  color: var(--accent-green);
  font-size: 1.1rem;
  font-style: italic;
}

/* Card grid (desktop) */
.hand-cards {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

/* Swiper hand (mobile) */
.swiper-hand {
  max-width: 340px;
  margin: 0 auto;
  padding: 10px 0;
}

.swiper-hand .swiper-slide {
    padding: 10px 20px 10px;
}

/* Parchment card */
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px 20px;
  width: 300px;
  height: 365px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.parchment-card.card-acting {
  border-color: var(--accent-gold);
}

.parchment-card.card-unattended {
  opacity: 0.55;
  filter: saturate(0.6);
  transform: scale(0.97);
}

/* Ribbon */
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
  z-index: 1;
    text-align: center;
}

.card-ribbon.acting {
  background: linear-gradient(180deg, #b8942e, #8a6a14);
  color: #1a1209;
}

.card-ribbon.unattended {
  background: rgba(100, 80, 60, 0.6);
  color: var(--text-secondary);
}

/* Card content */
.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 10px;
  margin-top: 8px;
  line-height: 1.3;
}

.parchment-desc {
  color: var(--text-primary);
  font-size: 1.2rem;
  line-height: 1.2;
  font-weight: 900;
  text-align: center;
  margin-bottom: 8px;
}

.parchment-question {
  color: var(--accent-gold);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.2;
  text-align: center;
  margin-bottom: 10px;
  opacity: 0.9;
}

.parchment-difficulty {
  position: absolute;
  bottom: 10px;
  left: 10px;
  font-size: 6rem;
  font-weight: 700;
  line-height: 4rem;
  color: var(--accent-gold);
  opacity: 0.3;
  background: transparent;
  padding: 0;
  pointer-events: none;
}


/* Divider */
.parchment-divider {
  position: relative;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold), transparent);
  margin: 12px 0;
}

.parchment-divider.divider-thin {
  background: linear-gradient(90deg, transparent, rgba(138, 106, 46, 0.4), transparent);
  margin: 8px 0;
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

.divider-ornament.small {
  font-size: 0.5rem;
  color: var(--text-secondary);
}

/* Outcome sections */
.outcome-section {
  padding: 4px 0;
}

.outcome-label {
  font-family: 'Cinzel', serif;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  text-align: center;
  margin-bottom: 6px;
}

.outcome-label-positive {
  color: #4caf50;
}

.outcome-label-negative {
  color: #e57373;
}

.outcome-arrows {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.arrow-chip {
  padding: 3px 9px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.arrow-up {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.arrow-down {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
}

/* Special badges */
.special-badges {
  display: flex;
  gap: 5px;
  justify-content: center;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.special-badge {
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-foresight {
  background: rgba(100, 149, 237, 0.2);
  color: #6495ed;
  border: 1px solid rgba(100, 149, 237, 0.4);
}

.badge-item {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 67, 0.4);
}

.badge-recover {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
  border: 1px solid rgba(39, 174, 96, 0.3);
}

.badge-lose {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
  border: 1px solid rgba(192, 57, 43, 0.3);
}

.badge-discard {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
  border: 1px solid rgba(192, 57, 43, 0.3);
}

/* ---- Mobile card compacting ---- */
@media (max-width: 768px) {
  .parchment-card {
    width: 300px;
    max-width: 300px;
    height: 365px;
    padding: 18px 16px;
  }

  .parchment-title {
    font-size: 1.05rem;
  }

  .parchment-desc {
    font-size: 1.2rem;
  }

  .parchment-question {
    font-size: 1.2rem;
  }

}

/* Card redraw */
.btn-redraw {
  display: block;
  margin: 10px auto 0;
  padding: 5px 16px;
  font-family: 'Cinzel', serif;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  background: rgba(138, 106, 200, 0.15);
  border: 1px solid rgba(138, 106, 200, 0.4);
  color: #b896e8;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-redraw:hover {
  background: rgba(138, 106, 200, 0.25);
  border-color: #b896e8;
  box-shadow: 0 0 8px rgba(138, 106, 200, 0.3);
}

.redraws-remaining {
  text-align: center;
  color: #b896e8;
  font-size: 0.72rem;
  font-style: italic;
  margin-top: 8px;
}

/* ---- Single-player inline resolve ---- */
.resolve-rolling {
  text-align: center;
  font-style: italic;
  color: var(--accent-gold);
  font-size: 1.1rem;
  padding: 30px 0;
  animation: resolve-pulse 1s ease-in-out infinite;
}

@keyframes resolve-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

.resolve-dice {
  text-align: center;
}

.resolve-dice-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-bottom: 6px;
}

.resolve-die {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--text-primary);
}

.resolve-die-wild {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.resolve-dice-total {
  font-weight: 700;
  font-size: 1rem;
  color: var(--accent-gold);
  margin-left: 4px;
}

.resolve-outcome {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  padding: 4px 0;
}

.outcome-success {
  color: #4caf50;
}

.outcome-fail {
  color: #e57373;
}

.resolve-flavor {
  padding: 8px 0;
}

.resolve-flavor-positive {
  font-style: italic;
  color: var(--text-primary);
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  margin-bottom: 6px;
}

.resolve-flavor-meanwhile {
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #e57373;
  text-align: center;
  margin: 8px 0 4px;
}

.resolve-flavor-negative {
  font-style: italic;
  color: #e57373;
  font-size: 1.2rem;
  line-height: 1.4;
  text-align: center;
  opacity: 0.9;
  margin-bottom: 4px;
}

.resolve-specials {
  text-align: center;
  padding: 4px 0 8px;
}

.resolve-special {
  font-size: 0.8rem;
  color: var(--accent-gold);
  font-style: italic;
  margin-bottom: 2px;
}

.resolve-tap-continue {
  text-align: center;
  color: var(--text-secondary, #a09080);
  font-size: 0.78rem;
  font-style: italic;
  margin-top: auto;
  padding-top: 10px;
  cursor: pointer;
  transition: color 0.2s;
}

.resolve-tap-continue:hover {
  color: var(--accent-gold, #c9a84c);
}

/* Hide non-selected Swiper slides without breaking layout */
:deep(.slide-hidden) {
  visibility: hidden !important;
  pointer-events: none;
}

</style>
