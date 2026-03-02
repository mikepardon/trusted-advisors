<template>
  <div class="hand-container">
    <div v-if="loading" class="loading">Loading hand...</div>

    <div v-else-if="hasAssigned" class="hand-assigned">
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
          <SwiperSlide v-for="item in cards" :key="item.hand_id">
            <div
              class="parchment-card"
              :class="{
                'card-acting': selectedPositive === item.hand_id,
                'card-unattended': selectedPositive !== null && selectedPositive !== item.hand_id,
              }"
              @click="selectAndConfirm(item.hand_id)"
            >
              <!-- Special effect badges -->
              <div v-if="hasSpecialEffects(item.card)" class="special-badges">
                <span v-if="item.card.positive_effects?.draw_item" class="special-badge badge-item">Draws Item</span>
                <span v-if="item.card.positive_effects?.recover_die" class="special-badge badge-recover">Recover Die</span>
                <span v-if="item.card.negative_effects?.lose_die" class="special-badge badge-lose">Lose a Die</span>
                <span v-if="item.card.negative_effects?.discard_item" class="special-badge badge-discard">Lose Item</span>
              </div>

              <!-- Ribbon -->
              <div v-if="selectedPositive === item.hand_id" class="card-ribbon acting">
                Acting on this
              </div>
              <div v-else-if="selectedPositive !== null" class="card-ribbon unattended">
                Left unattended
              </div>

              <h3 class="parchment-title">{{ item.card.title }}</h3>
              <p class="parchment-desc">{{ item.card.description }}</p>
              <span class="parchment-difficulty">Difficulty {{ item.card.difficulty }}</span>
              <span v-if="item.success_odds != null" class="odds-badge" :class="oddsClass(item.success_odds)">
                {{ item.success_odds }}% odds
              </span>

              <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

              <div class="outcome-section outcome-top">
                <p class="outcome-text">{{ item.card.positive_flavor || 'The council addresses the matter.' }}</p>
                <div class="outcome-chips">
                  <span v-for="(val, stat) in filterStatEffects(item.card.positive_effects)" :key="'p-' + stat" class="stat-chip chip-positive">
                    {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
                  </span>
                </div>
              </div>

              <div class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

              <div class="outcome-section outcome-bottom">
                <p class="outcome-text">{{ item.card.negative_flavor || 'The matter festers if ignored.' }}</p>
                <div class="outcome-chips">
                  <span v-for="(val, stat) in filterNegativeEffects(item.card.negative_effects)" :key="'n-' + stat" class="stat-chip chip-negative">
                    {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
                  </span>
                </div>
              </div>
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
        class="parchment-card"
        :class="{
          'card-acting': selectedPositive === item.hand_id,
          'card-unattended': selectedPositive !== null && selectedPositive !== item.hand_id,
        }"
        @click="selectAndConfirm(item.hand_id)"
      >
        <!-- Special effect badges -->
        <div v-if="hasSpecialEffects(item.card)" class="special-badges">
          <span v-if="item.card.positive_effects?.draw_item" class="special-badge badge-item">Draws Item</span>
          <span v-if="item.card.positive_effects?.recover_die" class="special-badge badge-recover">Recover Die</span>
          <span v-if="item.card.negative_effects?.lose_die" class="special-badge badge-lose">Lose a Die</span>
          <span v-if="item.card.negative_effects?.discard_item" class="special-badge badge-discard">Lose Item</span>
        </div>

        <!-- Ribbon -->
        <div v-if="selectedPositive === item.hand_id" class="card-ribbon acting">
          Acting on this
        </div>
        <div v-else-if="selectedPositive !== null && !isNegativeSelected(item.hand_id)" class="card-ribbon unattended">
          Left unattended
        </div>
        <div v-else-if="isNegativeSelected(item.hand_id)" class="card-ribbon unattended">
          Left unattended
        </div>

        <h3 class="parchment-title">{{ item.card.title }}</h3>
        <p class="parchment-desc">{{ item.card.description }}</p>
        <span class="parchment-difficulty">Difficulty {{ item.card.difficulty }}</span>
        <span v-if="item.success_odds != null" class="odds-badge" :class="oddsClass(item.success_odds)">
          {{ item.success_odds }}% odds
        </span>

        <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

        <div class="outcome-section outcome-top">
          <p class="outcome-text">{{ item.card.positive_flavor || 'The council addresses the matter.' }}</p>
          <div class="outcome-chips">
            <span v-for="(val, stat) in filterStatEffects(item.card.positive_effects)" :key="'p-' + stat" class="stat-chip chip-positive">
              {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
            </span>
          </div>
        </div>

        <div class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

        <div class="outcome-section outcome-bottom">
          <p class="outcome-text">{{ item.card.negative_flavor || 'The matter festers if ignored.' }}</p>
          <div class="outcome-chips">
            <span v-for="(val, stat) in filterNegativeEffects(item.card.negative_effects)" :key="'n-' + stat" class="stat-chip chip-negative">
              {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCards } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-cards';
import { playSound } from '../sounds';

export default {
  name: 'CardSelectionHand',
  components: { Swiper, SwiperSlide },
  props: {
    cards: { type: Array, default: () => [] },
    hasAssigned: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
  },
  emits: ['assign'],
  data() {
    return {
      selectedPositive: null,
      isMobile: false,
      activeSlideIndex: 0,
      swiperInstance: null,
      mediaQuery: null,
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
  },
  mounted() {
    this.mediaQuery = window.matchMedia('(max-width: 768px)');
    this.isMobile = this.mediaQuery.matches;
    this.mediaQuery.addEventListener('change', this.onMediaChange);
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
    },
    selectAndConfirm(handId) {
      if (this.selectedPositive !== null) return;
      playSound('clickCard');
      this.selectedPositive = handId;
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
    filterStatEffects(effects) {
      if (!effects) return {};
      const result = {};
      const specialKeys = ['grant_item_id', 'draw_item', 'recover_die'];
      for (const [key, val] of Object.entries(effects)) {
        if (!specialKeys.includes(key)) {
          result[key] = val;
        }
      }
      return result;
    },
    filterNegativeEffects(effects) {
      if (!effects) return {};
      const result = {};
      const specialKeys = ['lose_die', 'discard_item'];
      for (const [key, val] of Object.entries(effects)) {
        if (!specialKeys.includes(key)) {
          result[key] = val;
        }
      }
      return result;
    },
    hasSpecialEffects(card) {
      const pos = card.positive_effects || {};
      const neg = card.negative_effects || {};
      return pos.draw_item || pos.recover_die || neg.lose_die || neg.discard_item;
    },
    isNegativeSelected(handId) {
      return this.selectedPositive !== null && this.selectedPositive !== handId;
    },
    oddsClass(odds) {
      if (odds >= 70) return 'odds-high';
      if (odds >= 40) return 'odds-medium';
      return 'odds-low';
    },
  },
  watch: {
    cards() {
      this.selectedPositive = null;
      this.activeSlideIndex = 0;
      if (this.swiperInstance) {
        this.$nextTick(() => {
          this.swiperInstance.slideTo(0, 0);
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
    padding: 40px 20px 10px;
}

/* Parchment card */
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px 20px;
  width: 320px;
  min-height: 400px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
  display: flex;
  flex-direction: column;
}

.parchment-card:hover {
  transform: translateY(-4px) scale(1.01);
  box-shadow:
    0 8px 30px rgba(0, 0, 0, 0.6),
    0 0 15px rgba(212, 168, 67, 0.15);
  border-color: var(--accent-gold);
}

.parchment-card.card-acting {
  border-color: var(--accent-gold);
  box-shadow:
    0 0 30px rgba(212, 168, 67, 0.35),
    0 0 60px rgba(212, 168, 67, 0.1),
    inset 0 1px 0 rgba(212, 168, 67, 0.15);
}

.parchment-card.card-unattended {
  opacity: 0.55;
  filter: saturate(0.6);
  transform: scale(0.97);
}

.parchment-card.card-unattended:hover {
  opacity: 0.75;
  transform: scale(0.99);
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
  font-style: italic;
  font-size: 0.9rem;
  line-height: 1.5;
  text-align: center;
  margin-bottom: 10px;
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
  margin: 0 auto 6px;
}

.odds-badge {
  display: block;
  text-align: center;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 2px 10px;
  border-radius: 4px;
  width: fit-content;
  margin: 0 auto 8px;
}

.odds-high {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.odds-medium {
  background: rgba(241, 196, 15, 0.15);
  color: #f1c40f;
}

.odds-low {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
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

.outcome-text {
  color: var(--text-secondary);
  font-size: 0.82rem;
  line-height: 1.4;
  margin-bottom: 6px;
  text-align: center;
  font-style: italic;
}

.outcome-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.stat-chip {
  padding: 3px 9px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.chip-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.chip-negative {
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
    width: 100%;
    max-width: 320px;
    min-height: auto;
    padding: 18px 16px;
  }

  .parchment-title {
    font-size: 1.05rem;
  }

  .parchment-desc {
    font-size: 0.85rem;
  }

}
</style>
