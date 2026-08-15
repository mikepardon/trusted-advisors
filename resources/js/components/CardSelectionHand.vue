<template>
  <div class="hand-container">
    <div
      v-if="!loading && selectedPositive === undefined && !(hasAssigned && !singlePlayer)"
      class="choose-heading"
    >
      <span class="choose-heading-title">Choose One Matter</span>
      <span class="choose-heading-line"></span>
      <span class="choose-heading-sub">the other{{ cards.length > 2 ? 's are' : ' is' }} lost</span>
    </div>

    <div v-if="loading" class="loading">Loading hand...</div>

    <div v-else-if="hasAssigned && !singlePlayer" class="hand-assigned">
      <div class="assigned-check">&#10003;</div>
      <p class="assigned-msg">Decision recorded. Waiting for the rest of the council...</p>
    </div>

    <!-- ===== MOBILE: two cards side by side ===== -->
    <template v-else-if="isMobile">
      <div class="hand-grid" :class="{ 'grid-focused': selectedPositive !== undefined }">
        <div
          v-for="item in cards"
          v-show="!singlePlayer || selectedPositive === undefined || selectedPositive === item.hand_id"
          :key="item.hand_id"
          class="parchment-card"
          :class="{
            'card-acting': selectedPositive === item.hand_id,
            'card-unattended': selectedPositive !== undefined && selectedPositive !== item.hand_id,
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
                <div v-else-if="selectedPositive !== undefined" class="card-ribbon unattended">
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
{{ formatStatName(arrow.stat) }}<template v-if="hasForesight">: {{ arrow.value > 0 ? '+' + arrow.value : arrow.value }}</template>
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
{{ formatStatName(arrow.stat) }}<template v-if="hasForesight">: {{ arrow.value > 0 ? '+' + arrow.value : arrow.value }}</template>
                    </span>
                  </div>
                </div>

                <!-- Card redraw button -->
                <button
                  v-if="redraws > 0 && selectedPositive === undefined"
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
                  <template v-if="negativeFlavors.length > 0">
                    <p class="resolve-flavor-meanwhile">Meanwhile...</p>
                    <p v-for="(f, fi) in negativeFlavors" :key="fi" class="resolve-flavor-negative">{{ f }}</p>
                  </template>
                </div>
                <div v-if="resolveSpecialEffects.length > 0" class="resolve-specials">
                  <p v-for="(eff, ei) in resolveSpecialEffects" :key="ei" class="resolve-special">{{ eff.description }}</p>
                </div>
                <p class="resolve-tap-continue" @click="$emit('continue')">
                  {{ resolveData.gameOver ? 'Tap to view results' : 'Click to continue' }}
                </p>
              </template>
            </div>
      </div>
    </template>

    <!-- ===== DESKTOP: Side-by-side flex ===== -->
    <div v-else class="hand-cards">
      <div
        v-for="item in cards"
        v-show="!singlePlayer || selectedPositive === undefined || selectedPositive === item.hand_id"
        :key="item.hand_id"
        class="parchment-card"
        :class="{
          'card-acting': selectedPositive === item.hand_id,
          'card-unattended': selectedPositive !== undefined && selectedPositive !== item.hand_id,
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
          <div v-else-if="selectedPositive !== undefined" class="card-ribbon unattended">
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
{{ formatStatName(arrow.stat) }}<template v-if="hasForesight">: {{ arrow.value > 0 ? '+' + arrow.value : arrow.value }}</template>
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
{{ formatStatName(arrow.stat) }}<template v-if="hasForesight">: {{ arrow.value > 0 ? '+' + arrow.value : arrow.value }}</template>
              </span>
            </div>
          </div>

          <!-- Card redraw button -->
          <button
            v-if="redraws > 0 && selectedPositive === undefined"
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
            <template v-if="negativeFlavors.length > 0">
              <p class="resolve-flavor-meanwhile">Meanwhile...</p>
              <p v-for="(f, fi) in negativeFlavors" :key="fi" class="resolve-flavor-negative">{{ f }}</p>
            </template>
          </div>
          <div v-if="resolveSpecialEffects.length > 0" class="resolve-specials">
            <p v-for="(eff, ei) in resolveSpecialEffects" :key="ei" class="resolve-special">{{ eff.description }}</p>
          </div>
          <p class="resolve-tap-continue" @click="$emit('continue')">
            {{ resolveData.gameOver ? 'Tap to view results' : 'Click to continue' }}
          </p>
        </template>
      </div>
    </div>

    <!-- Redraws remaining indicator -->
    <div v-if="redraws > 0 && selectedPositive === undefined" class="redraws-remaining">
      {{ redraws }} redraw{{ redraws > 1 ? 's' : '' }} remaining
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { playSound } from "../sounds";
import dddiceService from "../dddice-service";

const {
  cards = [],
  hasAssigned = false,
  loading = false,
  redraws = 0,
  singlePlayer = false,
  resolveData = undefined,
} = defineProps<{
  cards?: HandItem[];
  hasAssigned?: boolean;
  loading?: boolean;
  redraws?: number;
  showPreviews?: boolean;
  singlePlayer?: boolean;
  resolveData?: ResolveData | undefined;
}>();

const emit = defineEmits<{
  assign: [payload: AssignPayload];
  preview: [payload: PreviewPayload | undefined];
  redraw: [handId: number];
  continue: [];
  "resolveShown": [];
}>();

const STAT_KEYS = new Set(["wealth", "influence", "security", "religion", "food", "happiness"]);

interface CardEffects {
  reveal_stats?: unknown;
  draw_item?: unknown;
  recover_die?: unknown;
  lose_die?: unknown;
  discard_item?: unknown;
  [key: string]: unknown;
}

interface HandCard {
  title: string;
  description: string;
  question?: string;
  difficulty: number;
  positive_effects?: CardEffects;
  negative_effects?: CardEffects;
  positive_flavor?: string;
  negative_flavor?: string;
}

interface HandItem {
  hand_id: number;
  card: HandCard;
}

interface DiceRoll {
  face: string;
  value: number;
}

interface DiceResult {
  rolls?: DiceRoll[];
}

interface PositivePhase {
  total_roll?: number;
  success?: boolean;
  dice_results?: DiceResult[];
}

interface SpecialEffect {
  description?: string;
}

interface ResolveData {
  positivePhase?: PositivePhase;
  combinedEffects?: Record<string, unknown>;
  eventEffects?: Record<string, unknown>;
  specialEffects?: SpecialEffect[];
  gameOver?: boolean;
}

interface PreviewPayload {
  positive: Record<string, number>;
  negative: Record<string, number>;
}

interface AssignPayload {
  positive_hand_id: number;
  negative_hand_ids: number[];
}

interface EffectArrow {
  stat: string;
  direction: "up" | "down";
  magnitude: number;
  value: number;
}

const selectedPositive = ref<number | undefined>(undefined);
const isMobile = ref(false);
const mediaQuery = ref<MediaQueryList | undefined>(undefined);
const resolvePhase = ref<"rolling" | "results" | undefined>(undefined);

const hasForesight = computed(() => cards.some((item) => item.card.positive_effects?.reveal_stats));

const selectedCard = computed<HandItem | undefined>(() => {
  if (selectedPositive.value === undefined) {
    return undefined;
  }
  return cards.find((item) => item.hand_id === selectedPositive.value);
});

const diceRolls = computed<DiceRoll[]>(() => {
  const diceResults = resolveData?.positivePhase?.dice_results;
  if (!diceResults) {
    return [];
  }
  const rolls: DiceRoll[] = [];
  for (const diceResult of diceResults) {
    if (diceResult.rolls) {
      rolls.push(...diceResult.rolls);
    }
  }
  return rolls;
});

const positiveFlavor = computed(() => {
  if (!selectedCard.value) {
    return "";
  }
  return resolveData?.positivePhase?.success
    ? selectedCard.value.card.positive_flavor ?? ""
    : selectedCard.value.card.negative_flavor ?? "";
});

const negativeFlavors = computed<string[]>(() => {
  if (selectedPositive.value === undefined) {
    return [];
  }
  return cards
    .filter((item) => item.hand_id !== selectedPositive.value && item.card.negative_flavor)
    .map((item) => item.card.negative_flavor ?? "");
});

const resolveSpecialEffects = computed<SpecialEffect[]>(() => {
  if (!resolveData?.specialEffects) {
    return [];
  }
  return resolveData.specialEffects.filter((effect) => effect.description);
});

function onMediaChange(event: MediaQueryListEvent): void {
  isMobile.value = event.matches;
}

function filterStatEffects(effects: CardEffects | undefined): Record<string, number> {
  if (!effects) {
    return {};
  }
  const result: Record<string, number> = {};
  for (const [key, value] of Object.entries(effects)) {
    if (typeof value === "number" && STAT_KEYS.has(key)) {
      result[key] = value;
    }
  }
  return result;
}

function emitPreview(item: HandItem | undefined): void {
  if (!item) {
    emit("preview", undefined);
    return;
  }
  const net: Record<string, number> = {};
  const positiveEffects = filterStatEffects(item.card.positive_effects);
  for (const [stat, value] of Object.entries(positiveEffects)) {
    net[stat] = (net[stat] ?? 0) + value;
  }
  const otherCards = cards.filter((other) => other.hand_id !== item.hand_id);
  for (const other of otherCards) {
    const negativeEffects = filterStatEffects(other.card.negative_effects);
    for (const [stat, value] of Object.entries(negativeEffects)) {
      net[stat] = (net[stat] ?? 0) + value;
    }
  }
  const positive: Record<string, number> = {};
  const negative: Record<string, number> = {};
  for (const [stat, value] of Object.entries(net)) {
    if (value > 0) {
      positive[stat] = value;
    } else if (value < 0) {
      negative[stat] = value;
    }
  }
  if (Object.keys(positive).length === 0 && Object.keys(negative).length === 0) {
    emit("preview", undefined);
    return;
  }
  emit("preview", { positive, negative });
}

function selectAndConfirm(handId: number): void {
  if (selectedPositive.value !== undefined) {
    return;
  }
  playSound("clickCard");
  selectedPositive.value = handId;
  emit("preview", undefined);
  const negativeIds = cards
    .filter((item) => item.hand_id !== handId)
    .map((item) => item.hand_id);
  if (negativeIds.length > 0) {
    emit("assign", {
      positive_hand_id: handId,
      negative_hand_ids: negativeIds,
    });
  }
}

function onCardHover(item: HandItem): void {
  if (selectedPositive.value !== undefined) {
    return;
  }
  emitPreview(item);
}

function onCardLeave(): void {
  emit("preview", undefined);
}

function hasSpecialEffects(card: HandCard): boolean {
  const positive = card.positive_effects ?? {};
  const negative = card.negative_effects ?? {};
  return Boolean(
    positive.draw_item ||
      positive.recover_die ||
      positive.reveal_stats ||
      negative.lose_die ||
      negative.discard_item,
  );
}

function isResolving(item: HandItem): boolean {
  return resolveData !== undefined && selectedPositive.value === item.hand_id;
}

function effectArrows(effects: CardEffects | undefined): EffectArrow[] {
  if (!effects) {
    return [];
  }
  const arrows: EffectArrow[] = [];
  for (const stat of STAT_KEYS) {
    const value = effects[stat];
    if (typeof value === "number" && value !== 0) {
      const direction = value > 0 ? "up" : "down";
      const magnitude = Math.abs(value) >= 3 ? 2 : 1;
      arrows.push({ stat, direction, magnitude, value });
    }
  }
  return arrows;
}

function formatStatName(stat: string): string {
  return stat.charAt(0).toUpperCase() + stat.slice(1);
}

watch(
  () => resolveData,
  async (value) => {
    if (value) {
      resolvePhase.value = "rolling";
      const use3D = dddiceService.isReady();
      if (use3D) {
        const diceSpecs = diceRolls.value.map((roll) => ({
          theme: "dddice-standard",
          value: roll.value,
        }));
        await dddiceService.roll(diceSpecs);
      } else {
        playSound("dice");
        await new Promise((resolve) => setTimeout(resolve, 1500));
      }
      if (resolveData) {
        resolvePhase.value = "results";
        emit("resolveShown");
      }
    } else {
      resolvePhase.value = undefined;
    }
  },
);

watch(
  () => cards,
  () => {
    selectedPositive.value = undefined;
  },
);

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
.choose-heading {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 18px 4px 14px;
}

.choose-heading-title {
  flex: none;
  font-family: 'Cinzel', serif;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--accent-gold, #f0c050);
}

.choose-heading-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, rgba(240, 192, 80, 0.4), transparent);
}

.choose-heading-sub {
  flex: none;
  font-family: 'Crimson Text', Georgia, serif;
  font-style: italic;
  font-size: 12px;
  color: #9a8a6a;
}

/* Two decision cards side by side (mobile) */
.hand-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  align-items: stretch;
  zoom: 0.9;
}

/* Once a card is chosen, the other is removed and this one zooms into focus —
   centred at a comfortable width (not edge to edge), with space above. */
.hand-grid.grid-focused {
  grid-template-columns: 1fr;
  max-width: 340px;
  margin-inline: auto;
  margin-top: 16px;
}

.hand-grid.grid-focused .parchment-card.card-acting {
  animation: card-zoom-focus 0.36s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes card-zoom-focus {
  from {
    transform: scale(0.9);
    opacity: 0.55;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.hand-grid .parchment-card {
  width: auto;
  height: auto;
  min-height: 300px;
  padding: 14px 11px;
}

.hand-grid .parchment-title {
  font-size: 1rem;
}

.hand-grid .parchment-desc {
  font-size: 1rem;
  line-height: 1.15;
}

.hand-grid .parchment-question {
  font-size: 0.8rem;
}

.hand-grid .parchment-difficulty {
  font-size: 3.4rem;
  line-height: 2.2rem;
  bottom: 6px;
  left: 8px;
}

.hand-grid .outcome-label {
  font-size: 0.64rem;
}

.hand-grid .arrow-chip {
  font-size: 0.68rem;
  padding: 2px 7px;
}

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
  justify-content: center;
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
  min-width: 14px;
  min-height: 14px;
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
