<template>
  <div class="duel-choose">
    <h4 class="phase-title">Choose a Card to Keep</h4>
    <p class="phase-note">
      The other card will be sent to your opponent.
    </p>

    <!-- MOBILE: Swiper carousel -->
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
          <SwiperSlide v-for="item in cards" :key="item.hand_id">
            <div
              class="parchment-card"
              :class="{
                'card-acting': selectedId === item.hand_id,
                'card-unattended': selectedId !== null && selectedId !== item.hand_id,
              }"
              @click="selectAndConfirm(item.hand_id)"
            >
              <div v-if="selectedId === item.hand_id" class="card-ribbon acting">
                Keeping this
              </div>
              <div v-else-if="selectedId !== null" class="card-ribbon unattended">
                Sending this
              </div>

              <h3 class="parchment-title">{{ item.card.title }}</h3>
              <p class="parchment-desc">{{ item.card.description }}</p>
              <span class="parchment-difficulty">Difficulty {{ item.card.difficulty }}</span>

              <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

              <div class="outcome-section">
                <p class="outcome-label">On Success:</p>
                <div class="outcome-chips">
                  <span
                    v-for="(val, stat) in filterStatEffects(getDuelPositive(item.card))"
                    :key="'p-' + stat"
                    class="stat-chip chip-positive"
                  >{{ stat }}</span>
                </div>
              </div>

              <div class="outcome-section">
                <p class="outcome-label">On Failure:</p>
                <div class="outcome-chips">
                  <span
                    v-for="(val, stat) in filterStatEffects(getDuelNegative(item.card))"
                    :key="'n-' + stat"
                    class="stat-chip chip-negative"
                  >{{ stat }}</span>
                </div>
              </div>
            </div>
          </SwiperSlide>
        </Swiper>
      </div>
    </template>

    <!-- DESKTOP: Side-by-side -->
    <div v-else class="choose-cards">
      <div
        v-for="item in cards"
        :key="item.hand_id"
        class="parchment-card"
        :class="{
          'card-acting': selectedId === item.hand_id,
          'card-unattended': selectedId !== null && selectedId !== item.hand_id,
          'card-previewing': hoveredId === item.hand_id && selectedId === null,
        }"
        @click="selectAndConfirm(item.hand_id)"
        @mouseenter="onCardHover(item)"
        @mouseleave="onCardLeave"
      >
        <div v-if="selectedId === item.hand_id" class="card-ribbon acting">
          Keeping this
        </div>
        <div v-else-if="selectedId !== null" class="card-ribbon unattended">
          Sending this
        </div>

        <h3 class="parchment-title">{{ item.card.title }}</h3>
        <p class="parchment-desc">{{ item.card.description }}</p>
        <span class="parchment-difficulty">Difficulty {{ item.card.difficulty }}</span>

        <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

        <div class="outcome-section">
          <p class="outcome-label">On Success:</p>
          <div class="outcome-chips">
            <span
              v-for="(val, stat) in filterStatEffects(getDuelPositive(item.card))"
              :key="'p-' + stat"
              class="stat-chip chip-positive"
            >{{ stat }}</span>
          </div>
        </div>

        <div class="outcome-section">
          <p class="outcome-label">On Failure:</p>
          <div class="outcome-chips">
            <span
              v-for="(val, stat) in filterStatEffects(getDuelNegative(item.card))"
              :key="'n-' + stat"
              class="stat-chip chip-negative"
            >{{ stat }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import { EffectCards } from "swiper/modules";
import type { Swiper as SwiperInstance } from "swiper";
import "swiper/css";
import "swiper/css/effect-cards";
import { playSound } from "../sounds";

interface DuelChooseCard {
  title: string;
  description: string;
  difficulty: number;
  positive_effects: Record<string, unknown> | undefined;
  negative_effects: Record<string, unknown> | undefined;
  positive_effects_duel: Record<string, unknown> | undefined;
  negative_effects_duel: Record<string, unknown> | undefined;
}

interface DuelChooseItem {
  hand_id: number;
  card: DuelChooseCard;
}

interface PreviewPayload {
  positive: Record<string, unknown>;
  negative: Record<string, unknown>;
}

const { cards = [] } = defineProps<{
  cards?: DuelChooseItem[];
}>();

const emit = defineEmits<{
  select: [handId: number];
  preview: [payload: PreviewPayload | undefined];
}>();

const swiperModules = [EffectCards];

const selectedId = ref<number | undefined>(undefined);
const isMobile = ref(false);
const swiperInstance = ref<SwiperInstance | undefined>(undefined);
const mediaQuery = ref<MediaQueryList | undefined>(undefined);
const hoveredId = ref<number | undefined>(undefined);

function onMediaChange(event: MediaQueryListEvent): void {
  isMobile.value = event.matches;
}

function onSwiper(swiper: SwiperInstance): void {
  swiperInstance.value = swiper;
}

function getDuelPositive(card: DuelChooseCard): Record<string, unknown> | undefined {
  return card.positive_effects_duel ?? card.positive_effects;
}

function getDuelNegative(card: DuelChooseCard): Record<string, unknown> | undefined {
  return card.negative_effects_duel ?? card.negative_effects;
}

function filterStatEffects(effects: Record<string, unknown> | undefined): Record<string, unknown> {
  if (!effects) {
    return {};
  }
  const result: Record<string, unknown> = {};
  const specialKeys = new Set(["grant_item_id", "draw_item", "recover_die", "lose_die", "discard_item", "remove_curse"]);
  for (const [key, value] of Object.entries(effects)) {
    if (!specialKeys.has(key)) {
      result[key] = value;
    }
  }
  return result;
}

function emitPreview(item: DuelChooseItem | undefined): void {
  if (!item) {
    emit("preview", undefined);
    return;
  }
  // Duel: only show positive effects (what you gain on success)
  emit("preview", {
    positive: filterStatEffects(getDuelPositive(item.card)),
    negative: {},
  });
}

function onSlideChange(): void {
  if (!swiperInstance.value || selectedId.value !== undefined) {
    return;
  }
  const index = swiperInstance.value.activeIndex;
  const item = cards[index];
  if (item) {
    emitPreview(item);
  }
}

function selectAndConfirm(handId: number): void {
  if (selectedId.value !== undefined) {
    return;
  }
  playSound("clickCard");
  selectedId.value = handId;
  emit("preview", undefined);
  emit("select", handId);
}

function onCardHover(item: DuelChooseItem): void {
  if (selectedId.value !== undefined) {
    return;
  }
  hoveredId.value = item.hand_id;
  emitPreview(item);
}

function onCardLeave(): void {
  hoveredId.value = undefined;
  emit("preview", undefined);
}

watch(
  () => cards,
  (newCards) => {
    selectedId.value = undefined;
    if (swiperInstance.value) {
      nextTick(() => {
        swiperInstance.value?.slideTo(0, 0);
      });
    }
    // Auto-emit preview for first card on mobile
    if (isMobile.value && newCards?.length) {
      nextTick(() => emitPreview(newCards[0]));
    }
  },
);

onMounted(() => {
  mediaQuery.value = window.matchMedia("(max-width: 768px)");
  isMobile.value = mediaQuery.value.matches;
  mediaQuery.value.addEventListener("change", onMediaChange);
  // Emit initial preview for the first card on mobile
  if (isMobile.value && cards.length > 0) {
    nextTick(() => emitPreview(cards[0]));
  }
});

onBeforeUnmount(() => {
  mediaQuery.value?.removeEventListener("change", onMediaChange);
});
</script>

<style scoped>
.duel-choose {
  margin-bottom: 0;
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
  margin-bottom: 0;
}

/* Desktop side-by-side */
.choose-cards {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

/* Mobile swiper */
.swiper-hand {
  max-width: 340px;
  margin: 0 auto;
  padding: 0;
}

.swiper-hand :deep(.swiper-slide) {
  padding: 20px 20px 10px;
}

/* Parchment card */
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px 20px;
  width: 300px;
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
  border-color: var(--accent-gold);
  box-shadow:
    0 8px 30px rgba(0, 0, 0, 0.6),
    0 0 15px rgba(212, 168, 67, 0.15);
}

.parchment-card.card-previewing {
  border-color: rgba(212, 168, 67, 0.6);
  box-shadow:
    0 8px 30px rgba(0, 0, 0, 0.6),
    0 0 12px rgba(212, 168, 67, 0.12);
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
  text-align: center;
  z-index: 1;
}

.card-ribbon.acting {
  background: linear-gradient(180deg, #b8942e, #8a6a14);
  color: #1a1209;
}

.card-ribbon.unattended {
  background: rgba(100, 80, 60, 0.6);
  color: var(--text-secondary);
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
  min-width: 14px;
  min-height: 14px;
}

.chip-positive { background: rgba(39, 174, 96, 0.15); color: #4caf50; }
.chip-negative { background: rgba(192, 57, 43, 0.15); color: #e57373; }

@media (max-width: 768px) {
  .parchment-card {
    width: 100%;
    max-width: 320px;
    min-height: auto;
    padding: 6px 10px;
  }

  .parchment-title {
    font-size: 1.05rem;
  }
}
</style>
