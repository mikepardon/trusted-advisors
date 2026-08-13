<template>
  <div class="curse-overlay">
    <div class="curse-backdrop" />
    <div class="curse-container">
      <div class="curse-header">
        <h2 class="curse-title">A Curse Descends</h2>
        <p class="curse-subtitle">{{ playerName }} must choose a curse to bear</p>
      </div>

      <!-- Mobile: Swiper carousel -->
      <template v-if="isMobile">
        <div class="swiper-hand">
          <Swiper
            :modules="swiperModules"
            effect="cards"
            :grab-cursor="true"
            :cards-effect="{ perSlideOffset: 8, perSlideRotate: 2, rotate: true, slideShadows: false }"
            :style="{ overflow: 'visible' }"
            @swiper="onSwiper"
            @slide-change="onSlideChange"
          >
            <SwiperSlide v-for="curse in curses" :key="curse.id">
              <div
                class="curse-parchment"
                :class="{ 'curse-selected': selectedId === curse.id }"
                @click="selectedId = curse.id"
              >
                <img v-if="curse.image_path" :src="`/api/storage/${curse.image_path}`" class="curse-image" />
                <div v-else class="curse-icon">&#9760;</div>

                <h3 class="curse-name">{{ curse.name }}</h3>
                <p class="curse-flavor">{{ curse.description }}</p>

                <div class="curse-divider"><span class="divider-ornament">&#9830;</span></div>

                <div class="effect-row effect-penalty">
                  <span class="effect-icon">&#9888;</span>
                  <div>
                    <span class="effect-label">Penalty</span>
                    <p class="effect-desc">{{ describeEffect(getActiveNegative(curse), 'negative') }}</p>
                  </div>
                </div>

                <div class="effect-row effect-reward">
                  <span class="effect-icon">&#10022;</span>
                  <div>
                    <span class="effect-label">Reward</span>
                    <p class="effect-desc">{{ describeEffect(getActivePositive(curse), 'positive') }}</p>
                  </div>
                </div>

                <button
                  class="curse-btn"
                  :disabled="choosing"
                  @click.stop="choose(curse.id)"
                >
                  {{ choosing && selectedId === curse.id ? 'Choosing...' : 'Bear this Curse' }}
                </button>
              </div>
            </SwiperSlide>
          </Swiper>
        </div>
        <p class="swipe-hint">Swipe to see curses</p>
      </template>

      <!-- Desktop: Side-by-side -->
      <div v-else class="curse-cards">
        <div
          v-for="curse in curses"
          :key="curse.id"
          class="curse-parchment"
          :class="{ 'curse-selected': selectedId === curse.id }"
          @click="selectedId = curse.id"
        >
          <img v-if="curse.image_path" :src="`/api/storage/${curse.image_path}`" class="curse-image" />
          <div v-else class="curse-icon">&#9760;</div>

          <h3 class="curse-name">{{ curse.name }}</h3>
          <p class="curse-flavor">{{ curse.description }}</p>

          <div class="curse-divider"><span class="divider-ornament">&#9830;</span></div>

          <div class="effect-row effect-penalty">
            <span class="effect-icon">&#9888;</span>
            <div>
              <span class="effect-label">Penalty</span>
              <p class="effect-desc">{{ describeEffect(getActiveNegative(curse), 'negative') }}</p>
            </div>
          </div>

          <div class="effect-row effect-reward">
            <span class="effect-icon">&#10022;</span>
            <div>
              <span class="effect-label">Reward</span>
              <p class="effect-desc">{{ describeEffect(getActivePositive(curse), 'positive') }}</p>
            </div>
          </div>

          <button
            class="curse-btn"
            :disabled="choosing"
            @click.stop="choose(curse.id)"
          >
            {{ choosing && selectedId === curse.id ? 'Choosing...' : 'Bear this Curse' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import { EffectCards } from "swiper/modules";
import type { Swiper as SwiperInstance } from "swiper";
import "swiper/css";
import "swiper/css/effect-cards";

interface CurseEffect {
  type: string;
  value?: number;
  stat?: string;
  count?: number;
  rounds?: number;
}

interface Curse {
  id: number;
  name: string;
  description: string;
  image_path: string | undefined;
  negative_effect: CurseEffect | undefined;
  positive_effect: CurseEffect | undefined;
  negative_effect_duel: CurseEffect | undefined;
  positive_effect_duel: CurseEffect | undefined;
}

const { curses, playerName = "", isDuel = false } = defineProps<{
  curses: Curse[];
  playerName?: string;
  isDuel?: boolean;
}>();

const emit = defineEmits<{
  selected: [curseId: number];
}>();

const swiperModules = [EffectCards];

const selectedId = ref<number | undefined>(undefined);
const choosing = ref(false);
const isMobile = ref(false);
const mediaQuery = ref<MediaQueryList | undefined>(undefined);
const swiperInstance = ref<SwiperInstance | undefined>(undefined);

function onMediaChange(event: MediaQueryListEvent): void {
  isMobile.value = event.matches;
}

function onSwiper(swiper: SwiperInstance): void {
  swiperInstance.value = swiper;
}

function onSlideChange(): void {
  // No-op: slide changes require no handling in curse selection.
}

function getActiveNegative(curse: Curse): CurseEffect | undefined {
  if (isDuel && curse.negative_effect_duel) {
    return curse.negative_effect_duel;
  }
  return curse.negative_effect;
}

function getActivePositive(curse: Curse): CurseEffect | undefined {
  if (isDuel && curse.positive_effect_duel) {
    return curse.positive_effect_duel;
  }
  return curse.positive_effect;
}

function choose(curseId: number): void {
  selectedId.value = curseId;
  choosing.value = true;
  emit("selected", curseId);
}

function describeEffect(effect: CurseEffect | undefined, phase: string): string {
  if (!effect) {
    return "Unknown";
  }
  const type = effect.type;
  if (type === "lose_die") {
    return `Permanently lose ${effect.value || 1} die`;
  }
  if (type === "stat_per_round" && phase === "negative") {
    return `${effect.value} ${effect.stat} each round`;
  }
  if (type === "stat_per_round" && phase === "positive") {
    return `+${effect.value} ${effect.stat} each round`;
  }
  if (type === "difficulty_modifier") {
    return `All cards +${effect.value || 1} difficulty`;
  }
  if (type === "double_negative") {
    return "Negative card effects are doubled";
  }
  if (type === "xp_multiplier") {
    return `${effect.value}x XP at game end`;
  }
  if (type === "auto_max_stat") {
    return `${effect.count || 1} stat(s) set to max at game end`;
  }
  if (type === "score_bonus") {
    return `+${effect.value} score at game end`;
  }
  if (type === "opponent_difficulty") {
    return `Opponent's cards +${effect.value} difficulty`;
  }
  if (type === "opponent_lose_die") {
    return `Opponent loses a die for ${effect.rounds || 1} round(s)`;
  }
  return JSON.stringify(effect);
}

onMounted(() => {
  mediaQuery.value = window.matchMedia("(max-width: 768px)");
  isMobile.value = mediaQuery.value.matches;
  mediaQuery.value.addEventListener("change", onMediaChange);
});

onBeforeUnmount(() => {
  mediaQuery.value?.removeEventListener("change", onMediaChange);
});
</script>

<style scoped>
.curse-overlay {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
}

.curse-backdrop {
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at center, rgba(40, 0, 60, 0.92) 0%, rgba(10, 0, 15, 0.97) 100%);
}

.curse-container {
  position: relative;
  z-index: 1;
  width: 95%;
  max-width: 720px;
  padding: 28px 20px;
  text-align: center;
}

.curse-header {
  margin-bottom: 24px;
}

.curse-title {
  font-family: 'Cinzel', serif;
  color: #9b59b6;
  font-size: 1.8rem;
  text-shadow: 0 0 20px rgba(155, 89, 182, 0.5);
  margin-bottom: 6px;
}

.curse-subtitle {
  color: rgba(200, 144, 224, 0.8);
  font-size: 0.95rem;
}

/* Desktop: side-by-side */
.curse-cards {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

/* Mobile: swiper */
.swiper-hand {
  max-width: 340px;
  margin: 0 auto;
  padding: 10px 0;
}

.swiper-hand .swiper-slide {
  padding: 10px 20px 10px;
}

.swipe-hint {
  color: rgba(200, 144, 224, 0.5);
  font-size: 0.75rem;
  margin-top: 12px;
  font-style: italic;
}

/* Parchment-style curse card */
.curse-parchment {
  background: linear-gradient(180deg, #2a1530, #1e0e28, #140820);
  border: 2px solid rgba(128, 0, 128, 0.35);
  border-radius: 12px;
  padding: 24px 20px;
  width: 320px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(155, 89, 182, 0.08);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.curse-parchment:hover {
  transform: translateY(-4px) scale(1.01);
  box-shadow:
    0 8px 30px rgba(0, 0, 0, 0.6),
    0 0 15px rgba(155, 89, 182, 0.2);
  border-color: rgba(155, 89, 182, 0.6);
}

.curse-parchment.curse-selected {
  border-color: #9b59b6;
  box-shadow:
    0 0 30px rgba(155, 89, 182, 0.35),
    0 0 60px rgba(155, 89, 182, 0.1),
    inset 0 1px 0 rgba(155, 89, 182, 0.15);
}

/* Card content */
.curse-image {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid rgba(155, 89, 182, 0.4);
  margin-bottom: 12px;
}

.curse-icon {
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: #9b59b6;
  background: rgba(155, 89, 182, 0.1);
  border-radius: 50%;
  border: 2px solid rgba(155, 89, 182, 0.3);
  margin-bottom: 12px;
}

.curse-name {
  font-family: 'Cinzel', serif;
  color: #c890e0;
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 8px;
  line-height: 1.3;
}

.curse-flavor {
  color: rgba(200, 144, 224, 0.55);
  font-size: 0.85rem;
  font-style: italic;
  line-height: 1.5;
  text-align: center;
  margin-bottom: 10px;
}

/* Divider */
.curse-divider {
  position: relative;
  height: 1px;
  width: 100%;
  background: linear-gradient(90deg, transparent, rgba(155, 89, 182, 0.4), transparent);
  margin: 10px 0;
}

.divider-ornament {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #1e0e28;
  color: #9b59b6;
  padding: 0 8px;
  font-size: 0.7rem;
}

/* Effect rows */
.effect-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  border-radius: 6px;
  margin-bottom: 6px;
}

.effect-penalty {
  background: rgba(192, 57, 43, 0.1);
  border: 1px solid rgba(192, 57, 43, 0.25);
}

.effect-reward {
  background: rgba(212, 168, 67, 0.08);
  border: 1px solid rgba(212, 168, 67, 0.2);
}

.effect-icon {
  font-size: 1.2rem;
  flex-shrink: 0;
  margin-top: 2px;
}

.effect-penalty .effect-icon { color: #e74c3c; }
.effect-reward .effect-icon { color: #d4a843; }

.effect-label {
  font-weight: 700;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.effect-penalty .effect-label { color: #e74c3c; }
.effect-reward .effect-label { color: #d4a843; }

.effect-desc {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-top: 2px;
  line-height: 1.3;
}

/* Choose button */
.curse-btn {
  margin-top: 14px;
  background: linear-gradient(135deg, rgba(128, 0, 128, 0.3) 0%, rgba(75, 0, 100, 0.5) 100%);
  border: 1px solid rgba(155, 89, 182, 0.5);
  color: #c890e0;
  padding: 10px 24px;
  border-radius: 8px;
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
}

.curse-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, rgba(128, 0, 128, 0.5) 0%, rgba(75, 0, 100, 0.7) 100%);
  border-color: #9b59b6;
  color: #e0c0f0;
  transform: none;
  box-shadow: 0 0 15px rgba(155, 89, 182, 0.3);
}

.curse-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .curse-parchment {
    width: 100%;
    max-width: 320px;
    padding: 18px 16px;
  }

  .curse-name {
    font-size: 1.05rem;
  }

  .curse-flavor {
    font-size: 0.8rem;
  }

  .curse-title {
    font-size: 1.4rem;
  }

  .curse-cards {
    flex-direction: column;
    align-items: center;
  }
}
</style>
