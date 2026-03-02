<template>
  <transition name="overlay-fade">
    <div v-if="visible" class="item-discard-overlay">
      <div class="item-discard-content">
        <h3 class="discard-title">Too Many Items!</h3>
        <p class="discard-subtitle">
          {{ playerData.character_name }} is carrying too many items. Choose one to discard:
        </p>

        <!-- ===== MOBILE: Swiper carousel ===== -->
        <template v-if="isMobile">
          <div class="swiper-discard">
            <Swiper
              :modules="swiperModules"
              :effect="'cards'"
              :grab-cursor="true"
              :cards-effect="{ perSlideOffset: 8, perSlideRotate: 2, rotate: true, slideShadows: false }"
              :style="{ overflow: 'visible' }"
              @slideChange="onSlideChange"
            >
              <SwiperSlide v-for="item in playerData.items" :key="item.id">
                <div
                  class="parchment-card"
                  :class="{
                    cursed: item.is_cursed,
                    'card-selected': selectedId === item.id,
                    disabled: item.is_cursed,
                  }"
                  @click="!item.is_cursed && selectItem(item.id)"
                >
                  <!-- Cursed ribbon -->
                  <div v-if="item.is_cursed" class="card-ribbon cursed-ribbon">
                    Cursed &mdash; Cannot Discard
                  </div>
                  <!-- Selected ribbon -->
                  <div v-else-if="selectedId === item.id" class="card-ribbon selected-ribbon">
                    Discard This
                  </div>

                  <div class="card-ornament">&#9876;</div>
                  <h3 class="parchment-title">{{ item.item_name }}</h3>

                  <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

                  <p v-if="item.description" class="parchment-desc">{{ item.description }}</p>

                  <div v-if="effectSummary(item)" class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

                  <div v-if="effectSummary(item)" class="outcome-chips">
                    <span class="stat-chip" :class="effectChipClass(item)">
                      {{ effectSummary(item) }}
                    </span>
                  </div>
                </div>
              </SwiperSlide>
            </Swiper>
          </div>
        </template>

        <!-- ===== DESKTOP: Side-by-side flex ===== -->
        <div v-else class="discard-cards">
          <div
            v-for="item in playerData.items"
            :key="item.id"
            class="parchment-card"
            :class="{
              cursed: item.is_cursed,
              'card-selected': selectedId === item.id,
              disabled: item.is_cursed,
            }"
            @click="!item.is_cursed && selectItem(item.id)"
          >
            <!-- Cursed ribbon -->
            <div v-if="item.is_cursed" class="card-ribbon cursed-ribbon">
              Cursed &mdash; Cannot Discard
            </div>
            <!-- Selected ribbon -->
            <div v-else-if="selectedId === item.id" class="card-ribbon selected-ribbon">
              Discard This
            </div>

            <div class="card-ornament">&#9876;</div>
            <h3 class="parchment-title">{{ item.item_name }}</h3>

            <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

            <p v-if="item.description" class="parchment-desc">{{ item.description }}</p>

            <div v-if="effectSummary(item)" class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

            <div v-if="effectSummary(item)" class="outcome-chips">
              <span class="stat-chip" :class="effectChipClass(item)">
                {{ effectSummary(item) }}
              </span>
            </div>
          </div>
        </div>

        <button
          class="btn-primary discard-btn"
          :disabled="!selectedId || discarding"
          @click="confirmDiscard"
        >
          {{ discarding ? 'Discarding...' : 'Confirm Discard' }}
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
import axios from 'axios';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCards } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-cards';

export default {
  name: 'ItemDiscard',
  components: { Swiper, SwiperSlide },
  props: {
    gameId: { type: [String, Number], required: true },
    playerData: { type: Object, required: true },
  },
  emits: ['discarded'],
  data() {
    return {
      visible: false,
      selectedId: null,
      discarding: false,
      isMobile: false,
      mediaQuery: null,
    };
  },
  computed: {
    swiperModules() {
      return [EffectCards];
    },
  },
  mounted() {
    this.visible = true;
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
    onSlideChange() {
      // Clear selection when swiping
    },
    effectSummary(item) {
      if (!item?.effect) return '';
      const type = item.effect.bonus_type || '';
      const value = item.effect.bonus_value ?? 0;
      switch (type) {
        case 'roll_bonus': return `+${value} to rolls`;
        case 'roll_penalty': return `${value} to rolls`;
        case 'difficulty_reduction': return `-${Math.abs(value)} difficulty`;
        case 'difficulty_increase': return `+${Math.abs(value)} difficulty`;
        case 'score_bonus': return `${value > 0 ? '+' : ''}${value} renown`;
        case 'score_per_round': return `+${value} renown/round`;
        case 'score_multiplier': return `${value}x score multiplier`;
        default: return '';
      }
    },
    effectChipClass(item) {
      if (item.is_cursed) return 'chip-negative';
      const type = item?.effect?.bonus_type || '';
      if (type === 'roll_bonus' || type === 'difficulty_reduction') return 'chip-positive';
      if (type === 'roll_penalty' || type === 'difficulty_increase') return 'chip-negative';
      return 'chip-neutral';
    },
    selectItem(id) {
      this.selectedId = this.selectedId === id ? null : id;
    },
    async confirmDiscard() {
      if (!this.selectedId) return;
      this.discarding = true;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/discard-item`, {
          game_player_item_id: this.selectedId,
        });
        this.$emit('discarded', res.data.items_over_limit);
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to discard item');
      }
      this.discarding = false;
    },
  },
};
</script>

<style scoped>
.item-discard-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  animation: fadeIn 0.4s ease;
}

.item-discard-content {
  text-align: center;
  max-width: 720px;
  width: 95%;
  padding: 28px 22px;
}

.discard-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.3rem;
  margin: 0 0 8px;
}

.discard-subtitle {
  color: var(--text-secondary, #a09080);
  font-size: 0.9rem;
  margin: 0 0 20px;
  line-height: 1.4;
}

/* Desktop: side-by-side layout */
.discard-cards {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

/* Mobile: Swiper carousel */
.swiper-discard {
  max-width: 320px;
  margin: 0 auto 20px;
  padding: 10px 0;
}

.swiper-discard .swiper-slide {
  padding: 40px 10px 10px;
}

/* Parchment card (matches CardSelectionHand/PlayerItems) */
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 24px 20px;
  width: 220px;
  min-height: 260px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.parchment-card:not(.disabled):hover {
  transform: translateY(-4px) scale(1.01);
  box-shadow:
    0 8px 30px rgba(0, 0, 0, 0.6),
    0 0 15px rgba(212, 168, 67, 0.15);
  border-color: var(--accent-gold, #c9a84c);
}

.parchment-card.cursed {
  border-color: rgba(192, 57, 43, 0.5);
  opacity: 0.55;
  cursor: not-allowed;
}

.parchment-card.cursed .card-ornament {
  color: #c0392b;
}

.parchment-card.cursed .parchment-title {
  color: #e07060;
}

.parchment-card.card-selected {
  border-color: #c0392b;
  box-shadow:
    0 0 20px rgba(192, 57, 43, 0.3),
    0 0 40px rgba(192, 57, 43, 0.1),
    inset 0 1px 0 rgba(192, 57, 43, 0.15);
}

.parchment-card.disabled {
  pointer-events: none;
}

/* Ribbons */
.card-ribbon {
  position: absolute;
  top: -1px;
  left: 0;
  right: 0;
  padding: 4px 18px;
  font-family: 'Cinzel', serif;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 0 0 6px 6px;
  z-index: 1;
  text-align: center;
}

.cursed-ribbon {
  background: linear-gradient(180deg, #8b2020, #601515);
  color: #f0c0c0;
}

.selected-ribbon {
  background: linear-gradient(180deg, #8b2020, #601515);
  color: #ffa0a0;
}

.card-ornament {
  font-size: 1.8rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.5;
  margin-bottom: 6px;
}

.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1rem;
  text-align: center;
  margin-bottom: 4px;
  line-height: 1.3;
}

/* Dividers */
.parchment-divider {
  position: relative;
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
  margin: 10px 0;
}

.parchment-divider.divider-thin {
  background: linear-gradient(90deg, transparent, rgba(138, 106, 46, 0.4), transparent);
  margin: 6px 0;
}

.divider-ornament {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #2a1f14;
  color: var(--accent-gold, #c9a84c);
  padding: 0 8px;
  font-size: 0.7rem;
}

.divider-ornament.small {
  font-size: 0.5rem;
  color: var(--text-secondary, #a09080);
}

.parchment-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.82rem;
  line-height: 1.4;
  text-align: center;
  flex: 1;
}

/* Stat-chip effect */
.outcome-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.stat-chip {
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.chip-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.chip-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
}

.chip-neutral {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold, #c9a84c);
}

.discard-btn {
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  padding: 10px 30px;
  margin-top: 10px;
}

.discard-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.overlay-fade-leave-active {
  transition: opacity 0.3s ease;
}

.overlay-fade-leave-to {
  opacity: 0;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* ---- Mobile ---- */
@media (max-width: 768px) {
  .parchment-card {
    width: 100%;
    max-width: 300px;
    min-height: auto;
    padding: 18px 16px;
  }

  .parchment-title {
    font-size: 0.95rem;
  }

  .parchment-desc {
    font-size: 0.78rem;
  }
}
</style>
