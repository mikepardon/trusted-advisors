<template>
  <div class="round-results">
    <!-- Action button at top for easy access -->
    <button
      v-if="viewPhase === 'positive' && !allRolled && nextToRoll && !isAnyRolling"
      class="btn-roll action-btn-top"
      @click="startRolling(nextToRoll)"
    >
      Roll!
    </button>
    <button
      v-else-if="viewPhase === 'positive' && allRolled && !resultsAccepted"
      class="btn-primary action-btn-top"
      @click="acceptAndContinue"
    >
      Accept Results
    </button>
    <button
      v-else-if="viewPhase === 'negative' && canAdvance"
      class="btn-primary action-btn-top"
      @click="$emit('nextRound')"
    >
      {{ gameOver ? 'View Results' : 'Next Month' }}
    </button>

    <!-- ===================== -->
    <!-- PHASE 1: POSITIVE     -->
    <!-- ===================== -->
    <template v-if="viewPhase === 'positive'">
      <div class="phase-section phase-positive">
        <h4 class="phase-title">The Council Acts</h4>
        <p class="phase-note">Your advisors have chosen to address these matters:</p>

        <div class="pooled-cards">
          <div v-for="(card, idx) in positivePhase.cards" :key="'p-' + idx" class="pooled-card">
            <span class="pooled-player">{{ card.character_name }}</span>
            <span class="pooled-card-title">{{ card.card.title }}</span>
            <span class="pooled-diff">Diff: {{ card.card.difficulty }}</span>
          </div>
        </div>

        <div class="total-difficulty-line">
          Combined Difficulty: <strong>{{ positivePhase.total_difficulty }}</strong>
        </div>

        <!-- Dice Rolling Area -->
        <div class="dice-section">
          <div v-for="pr in positivePhase.dice_results" :key="pr.player_number" class="player-roll-row">
            <span class="roll-name">{{ pr.character_name }}</span>

            <template v-if="hasRolled(pr.player_number)">
              <span class="roll-faces">
                <span
                  v-for="(roll, ri) in pr.rolls"
                  :key="ri"
                  class="face-badge"
                  :class="roll.face === 'WILD' ? 'face-wild' : 'face-num'"
                >
                  {{ roll.face === 'WILD' ? 'W ' + roll.value : roll.face }}
                </span>
              </span>
              <span class="roll-subtotal">= {{ playerSubtotal(pr) }}</span>
            </template>

            <template v-else-if="isRolling(pr.player_number)">
              <span class="roll-faces rolling-anim">
                <span
                  v-for="(roll, ri) in pr.rolls"
                  :key="'r-' + ri"
                  class="face-badge face-rolling"
                >
                  {{ rollingFaces[pr.player_number]?.[ri] ?? '?' }}
                </span>
              </span>
            </template>

            <template v-else>
              <span class="roll-waiting">Awaiting roll...</span>
            </template>
          </div>
        </div>

        <!-- Running total -->
        <div v-if="rolledCount > 0 && !allRolled" class="running-total">
          Roll so far: <strong>{{ runningTotal }}</strong>
          &mdash; {{ remainingCount }} advisor{{ remainingCount !== 1 ? 's' : '' }} to roll
        </div>

        <!-- After all rolled: Wild Triggers, Summary -->
        <template v-if="allRolled">
          <div v-if="positivePhase.ability_effects && positivePhase.ability_effects.length > 0" class="wild-section">
            <div v-for="(desc, i) in positivePhase.ability_effects" :key="i" class="wild-trigger">
              {{ desc }}
            </div>
          </div>

          <div class="roll-summary">
            <span class="roll-total" :class="positivePhase.success ? 'roll-pass' : 'roll-fail'">
              Total Roll: {{ positivePhase.total_roll }}
            </span>
            <span class="roll-vs">vs</span>
            <span class="roll-difficulty">Difficulty: {{ positivePhase.total_difficulty }}</span>
            <span class="result-badge" :class="positivePhase.success ? 'badge-success' : 'badge-failure'">
              {{ positivePhase.success ? 'SUCCESS' : 'FAILURE' }}
            </span>
          </div>

          <div v-if="positivePhase.item_modifiers && positivePhase.item_modifiers.length > 0" class="item-modifiers-section">
            <span
              v-for="(modifier, i) in positivePhase.item_modifiers"
              :key="'im-' + i"
              class="item-mod-tag"
              :class="modifierTagClass(modifier)"
            >
              {{ modifier.item_name }} ({{ modifierLabel(modifier) }})
            </span>
          </div>

          <!-- Flavor text reveal -->
          <div v-if="positivePhase.success && positiveFlavorText" class="flavor-reveal flavor-positive">
            {{ positiveFlavorText }}
          </div>
          <div v-else-if="!positivePhase.success && negativeFlavorForActed" class="flavor-reveal flavor-negative">
            {{ negativeFlavorForActed }}
          </div>

          <!-- Show effects preview (but stats haven't moved yet) -->
          <div v-if="positivePhase.success && Object.keys(filterStatEffects(positivePhase.effects || {})).length > 0" class="effects-row">
            <span
              v-for="(val, stat) in filterStatEffects(positivePhase.effects || {})"
              :key="stat"
              class="effect-badge effect-positive"
            >
              {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
            </span>
          </div>
          <div v-if="!positivePhase.success" class="no-effects">
            The council's efforts fell short. No positive effects this month.
          </div>

          <!-- Special effects for positive phase -->
          <div v-if="positiveSpecialEffects.length > 0" class="special-effects-section">
            <div v-for="(se, i) in positiveSpecialEffects" :key="'se-' + i" class="special-effect-line">
              <span class="special-icon">{{ specialIcon(se.type) }}</span>
              {{ se.description }}
            </div>
          </div>

        </template>
      </div>
    </template>

    <!-- ===================== -->
    <!-- PHASE 2: NEGATIVE     -->
    <!-- ===================== -->
    <template v-if="viewPhase === 'negative'">
      <div class="phase-section phase-negative">
        <h4 class="phase-title">Meanwhile...</h4>
        <p class="phase-note">These matters were left unattended. Their consequences are unavoidable.</p>

        <div class="pooled-cards">
          <div v-for="(card, idx) in negativePhase.cards" :key="'n-' + idx" class="pooled-card pooled-neg">
            <span class="pooled-player">{{ card.character_name }}</span>
            <span class="pooled-card-title">{{ card.card.title }}</span>
          </div>
        </div>

        <div class="neg-card-details">
          <div v-for="(card, idx) in negativePhase.cards" :key="'nd-' + idx" class="neg-card-detail">
            <h5 class="neg-card-name">{{ card.card.title }}</h5>
            <p class="neg-card-desc">{{ card.card.description }}</p>
            <p v-if="card.card.negative_flavor" class="neg-card-flavor">{{ card.card.negative_flavor }}</p>
          </div>
        </div>

        <div v-if="Object.keys(negativePhase.effects || {}).length > 0" class="effects-row">
          <span
            v-for="(val, stat) in negativePhase.effects"
            :key="stat"
            class="effect-badge effect-negative"
          >
            {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>

        <!-- Special effects for negative phase -->
        <div v-if="negativeSpecialEffects.length > 0" class="special-effects-section special-negative">
          <div v-for="(se, i) in negativeSpecialEffects" :key="'nse-' + i" class="special-effect-line">
            <span class="special-icon">{{ specialIcon(se.type) }}</span>
            {{ se.description }}
          </div>
        </div>
      </div>

      <!-- Combined Summary -->
      <div class="total-summary">
        <h4>End of Month Summary</h4>
        <div class="total-effects">
          <span
            v-for="(val, stat) in combinedEffects"
            :key="stat"
            class="effect-badge"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
        <div v-if="Object.keys(eventEffects || {}).length > 0" class="event-row">
          <span class="event-label">Event:</span>
          <span
            v-for="(val, stat) in eventEffects"
            :key="stat"
            class="effect-badge"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
      </div>

      <p v-if="!canAdvance" class="waiting-host-text">Waiting for host to advance...</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { playSound } from "../sounds";
import dddiceService from "../dddice-service";
import { useIcons } from "../stores/icons";

interface DiceRoll {
  face: string;
  value: number;
}

interface DiceResult {
  player_number: number;
  character_name: string;
  rolls: DiceRoll[];
}

interface PhaseCard {
  character_name: string;
  card: {
    title: string;
    difficulty?: number;
    description?: string;
    positive_flavor?: string;
    negative_flavor?: string;
  };
}

interface ItemModifier {
  item_name: string;
  type: string;
  value: number;
}

interface PositivePhase {
  cards?: PhaseCard[];
  total_difficulty?: number;
  dice_results?: DiceResult[];
  ability_effects?: string[];
  success?: boolean;
  total_roll?: number;
  item_modifiers?: ItemModifier[];
  effects?: Record<string, number>;
}

interface NegativePhase {
  cards?: PhaseCard[];
  effects?: Record<string, number>;
}

interface SpecialEffect {
  phase: string;
  type: string;
  description: string;
}

interface RoundPlayer {
  player_number: number;
  user?: {
    active_dice_theme_slug?: string;
  };
}

const {
  round,
  positivePhase = {},
  negativePhase = {},
  specialEffects = [],
  players = [],
} = defineProps<{
  round: number;
  totalRounds?: number;
  positivePhase?: PositivePhase;
  negativePhase?: NegativePhase;
  combinedEffects?: Record<string, number>;
  eventEffects?: Record<string, number>;
  specialEffects?: SpecialEffect[];
  gameOver?: boolean;
  canAdvance?: boolean;
  players?: RoundPlayer[];
  resumed?: boolean;
}>();

const emit = defineEmits<{
  "nextRound": [];
  "phaseComplete": [phase: string];
}>();

const rolledPlayerNumbers = ref<number[]>([]);
const rollingPlayerNumbers = ref<number[]>([]);
const rollingFaces = ref<Record<number, string[]>>({});
const viewPhase = ref("positive");
const resultsAccepted = ref(false);

function playerSubtotal(diceResult: DiceResult): number {
  return (diceResult.rolls || []).reduce((sum, roll) => sum + (roll.value || 0), 0);
}

const rolledCount = computed<number>(() => rolledPlayerNumbers.value.length);

const totalPlayers = computed<number>(() => (positivePhase.dice_results || []).length);

const remainingCount = computed<number>(() => totalPlayers.value - rolledCount.value);

const allRolled = computed<boolean>(() => totalPlayers.value > 0 && rolledCount.value >= totalPlayers.value);

const runningTotal = computed<number>(() => {
  let sum = 0;
  const diceResults = positivePhase.dice_results || [];
  for (const diceResult of diceResults) {
    if (rolledPlayerNumbers.value.includes(diceResult.player_number)) {
      sum += playerSubtotal(diceResult);
    }
  }
  return sum;
});

const nextToRoll = computed<DiceResult | undefined>(() => (positivePhase.dice_results || []).find(
  (diceResult) => !rolledPlayerNumbers.value.includes(diceResult.player_number) && !rollingPlayerNumbers.value.includes(diceResult.player_number),
));

const isAnyRolling = computed<boolean>(() => rollingPlayerNumbers.value.length > 0);

const positiveSpecialEffects = computed<SpecialEffect[]>(() => (specialEffects || []).filter((effect) => effect.phase === "positive"));

const negativeSpecialEffects = computed<SpecialEffect[]>(() => (specialEffects || []).filter((effect) => effect.phase === "negative"));

const positiveFlavorText = computed<string>(() => {
  const cards = positivePhase.cards || [];
  const flavors = cards.map((card) => card.card?.positive_flavor).filter(Boolean);
  return flavors.join(" ");
});

const negativeFlavorForActed = computed<string>(() => {
  // When the council fails the acted-on cards, show their negative flavor
  const cards = positivePhase.cards || [];
  const flavors = cards.map((card) => card.card?.negative_flavor).filter(Boolean);
  return flavors.join(" ");
});

watch(() => round, () => {
  rolledPlayerNumbers.value = [];
  rollingPlayerNumbers.value = [];
  rollingFaces.value = {};
  viewPhase.value = "positive";
  resultsAccepted.value = false;
});

function hasRolled(playerNumber: number): boolean {
  return rolledPlayerNumbers.value.includes(playerNumber);
}

function isRolling(playerNumber: number): boolean {
  return rollingPlayerNumbers.value.includes(playerNumber);
}

function getThemesForPlayer(playerNumber: number): string[] {
  const player = (players || []).find((entry) => entry.player_number === playerNumber);
  const slug = player?.user?.active_dice_theme_slug || "dddice-standard";
  return [slug, slug, slug];
}

async function startRolling(diceResult: DiceResult): Promise<void> {
  const playerNumber = diceResult.player_number;
  if (rollingPlayerNumbers.value.includes(playerNumber) || rolledPlayerNumbers.value.includes(playerNumber)) return;

  const use3D = dddiceService.isReady();

  if (!use3D) {
    playSound("dice");
  }

  rollingPlayerNumbers.value.push(playerNumber);
  rollingFaces.value[playerNumber] = diceResult.rolls.map(() => "?");

  if (use3D) {
    // 3D dice path: animate via dddice, then show final results immediately
    const themes = getThemesForPlayer(playerNumber);
    const diceSpecs = diceResult.rolls.map((roll, index) => ({
      theme: themes[index] || "dddice-standard",
      value: roll.value,
    }));
    await dddiceService.roll(diceSpecs);

    rollingPlayerNumbers.value = rollingPlayerNumbers.value.filter((number) => number !== playerNumber);
    rolledPlayerNumbers.value.push(playerNumber);

    if (allRolled.value) {
      if (positivePhase.success) {
        playSound("win");
      } else {
        playSound("fail");
      }
    }
  } else {
    // Fallback: text animation
    const possibleFaces = ["1", "2", "3", "4", "5", "W"];
    let ticks = 0;
    const maxTicks = 12;
    const interval = setInterval(() => {
      ticks++;
      rollingFaces.value[playerNumber] = diceResult.rolls.map(() =>
        possibleFaces[Math.floor(Math.random() * possibleFaces.length)],
      );
      rollingFaces.value = { ...rollingFaces.value };

      if (ticks >= maxTicks) {
        clearInterval(interval);
        rollingPlayerNumbers.value = rollingPlayerNumbers.value.filter((number) => number !== playerNumber);
        rolledPlayerNumbers.value.push(playerNumber);

        if (allRolled.value) {
          if (positivePhase.success) {
            playSound("win");
          } else {
            playSound("fail");
          }
        }
      }
    }, 70);
  }
}

async function acceptAndContinue(): Promise<void> {
  resultsAccepted.value = true;
  emit("phaseComplete", "positive");
  await nextTick();
  viewPhase.value = "negative";
  await nextTick();
  emit("phaseComplete", "negative");
}

function statIcon(stat: string): string {
  const statIcons = useIcons().getStatIcons();
  const match = statIcons.find((icon) => icon.key === stat);
  return match ? match.icon : "";
}

function filterStatEffects(effects: Record<string, number> | undefined): Record<string, number> {
  if (!effects) return {};
  const result: Record<string, number> = {};
  for (const [key, value] of Object.entries(effects)) {
    if (!["grant_item_id", "draw_item", "recover_die", "remove_curse"].includes(key)) {
      result[key] = value;
    }
  }
  return result;
}

function modifierLabel(modifier: ItemModifier): string {
  const labels: Record<string, string> = {
    roll_bonus: "+" + modifier.value + " to roll",
    roll_penalty: modifier.value + " to roll",
    difficulty_reduction: "-" + modifier.value + " difficulty",
    difficulty_increase: "+" + modifier.value + " difficulty",
  };
  return labels[modifier.type] || modifier.type;
}

function modifierTagClass(modifier: ItemModifier): string {
  if (modifier.type === "roll_bonus" || modifier.type === "difficulty_reduction") return "mod-helpful";
  return "mod-harmful";
}

function specialIcon(type: string): string {
  const icons: Record<string, string> = {
    draw_item: "\u{1F3C6}",
    recover_die: "\u{1FA79}",
    lose_die: "\u{1F4A5}",
    discard_item: "\u{1F4A8}",
    remove_curse: "\u{2728}",
  };
  return icons[type] || "\u{2728}";
}
</script>

<style scoped>
.round-results {
  margin-bottom: 20px;
}

.action-btn-top {
  display: block;
  margin: 0 auto 12px;
  font-size: 1rem;
  padding: 10px 36px;
}

.phase-section {
  background: var(--bg-secondary);
  border-radius: 10px;
  padding: 18px;
  margin-bottom: 15px;
  border-left: 4px solid;
}

.phase-positive { border-left-color: var(--accent-green); }
.phase-negative { border-left-color: var(--accent-red); }

.phase-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1.1rem;
  margin-bottom: 6px;
}

.phase-note {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin-bottom: 12px;
}

.pooled-cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.pooled-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px;
  background: rgba(74, 138, 58, 0.08);
  border-radius: 6px;
  font-size: 0.85rem;
}

.pooled-card.pooled-neg {
  background: rgba(160, 48, 32, 0.08);
}

.pooled-player {
  color: var(--text-secondary);
  min-width: 100px;
  font-size: 0.8rem;
}

.pooled-card-title {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  flex: 1;
}

.pooled-diff {
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-weight: 600;
}

.total-difficulty-line {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 14px;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
}

.total-difficulty-line strong {
  color: var(--text-bright);
  font-size: 1.05rem;
}

.dice-section { margin-bottom: 10px; }

.player-roll-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.1);
  border-radius: 6px;
  min-height: 44px;
}

.roll-name {
  color: var(--text-secondary);
  min-width: 110px;
  font-size: 0.85rem;
  font-weight: 600;
}

.roll-faces {
  display: flex;
  gap: 5px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.face-badge {
  padding: 4px 10px;
  border-radius: 5px;
  font-size: 0.8rem;
  font-weight: 700;
  font-family: 'Cinzel', serif;
  min-width: 28px;
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

.rolling-anim {
  animation: none;
}

@keyframes diceShake {
  0% { transform: translateY(-1px) rotate(-2deg); }
  100% { transform: translateY(1px) rotate(2deg); }
}

.roll-subtotal {
  color: var(--text-bright);
  font-weight: 700;
  font-size: 0.9rem;
  margin-left: 6px;
}

.btn-roll {
  background: linear-gradient(135deg, #8b2020, #b83030);
  color: #f5e6cc;
  border: 2px solid #d4a843;
  padding: 8px 28px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
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

.btn-roll.action-btn-top {
  display: block;
  margin: 0 auto 12px;
  padding: 10px 36px;
  font-size: 1rem;
}

.roll-waiting {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.8rem;
}

.running-total {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 12px;
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
  text-align: center;
}

.running-total strong {
  color: var(--accent-gold);
  font-size: 1.05rem;
}

.wild-section {
  margin-bottom: 10px;
  padding: 8px;
  background: rgba(212, 168, 67, 0.1);
  border-radius: 6px;
  animation: fadeIn 0.3s ease;
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
  animation: fadeIn 0.3s ease;
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
  margin-left: auto;
}

.badge-success { background: rgba(74, 138, 58, 0.2); color: #4a8a3a; }
.badge-failure { background: rgba(160, 48, 32, 0.2); color: #c0392b; }

.item-modifiers-section {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  animation: fadeIn 0.3s ease;
}

.item-mod-tag {
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 4px;
  font-style: italic;
}

.mod-helpful {
  background: rgba(74, 138, 58, 0.12);
  color: #6abf5e;
}

.mod-harmful {
  background: rgba(160, 48, 32, 0.12);
  color: #e57373;
}

.flavor-reveal {
  font-style: italic;
  font-size: 0.85rem;
  line-height: 1.5;
  margin-bottom: 10px;
  padding: 8px 12px;
  border-radius: 6px;
  animation: fadeIn 0.3s ease;
}

.flavor-positive {
  color: #6abf5e;
  background: rgba(74, 138, 58, 0.08);
  border-left: 3px solid rgba(74, 138, 58, 0.4);
}

.flavor-negative {
  color: #e57373;
  background: rgba(160, 48, 32, 0.08);
  border-left: 3px solid rgba(160, 48, 32, 0.4);
}

.neg-card-flavor {
  color: #e57373;
  font-style: italic;
  font-size: 0.8rem;
  line-height: 1.4;
  margin-top: 4px;
}

.no-effects {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin-bottom: 10px;
}

.effects-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.effect-badge {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  text-transform: capitalize;
}

.effect-positive { background: rgba(74, 138, 58, 0.15); color: #4a8a3a; }
.effect-negative { background: rgba(160, 48, 32, 0.15); color: #c0392b; }

.accept-btn {
  display: block;
  margin: 15px auto 0;
  font-size: 1rem;
  padding: 10px 36px;
}

/* Negative card details */
.neg-card-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.neg-card-detail {
  padding: 10px 14px;
  background: rgba(160, 48, 32, 0.06);
  border: 1px solid rgba(160, 48, 32, 0.15);
  border-radius: 6px;
}

.neg-card-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  margin-bottom: 4px;
}

.neg-card-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.82rem;
  line-height: 1.4;
}

.total-summary {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 15px;
  text-align: center;
  margin-bottom: 20px;
}

.total-summary h4 {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  margin-bottom: 10px;
}

.total-effects {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.event-row {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
}

.event-label {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
}

.special-effects-section {
  margin: 10px 0;
  padding: 8px 12px;
  background: rgba(212, 168, 67, 0.08);
  border-radius: 6px;
  border-left: 3px solid var(--accent-gold);
}

.special-effects-section.special-negative {
  background: rgba(160, 48, 32, 0.08);
  border-left-color: var(--accent-red);
}

.special-effect-line {
  font-size: 0.85rem;
  color: var(--text-bright);
  margin-bottom: 4px;
  animation: fadeIn 0.3s ease;
}

.special-icon {
  margin-right: 6px;
}

.waiting-host-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 16px;
}

.next-btn {
  display: block;
  margin: 0 auto;
  font-size: 1.1rem;
  padding: 12px 40px;
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .phase-section {
    padding: 12px;
    margin-bottom: 10px;
  }

  .phase-title {
    font-size: 0.95rem;
  }

  .phase-note {
    font-size: 0.8rem;
    margin-bottom: 8px;
  }

  .pooled-card {
    padding: 5px 8px;
    font-size: 0.8rem;
  }

  .pooled-player {
    min-width: 70px;
    font-size: 0.75rem;
  }

  .pooled-card-title {
    font-size: 0.8rem;
  }

  .player-roll-row {
    flex-wrap: wrap;
    gap: 6px;
    padding: 6px 8px;
  }

  .roll-name {
    min-width: 80px;
    font-size: 0.8rem;
  }

  .face-badge {
    padding: 3px 7px;
    font-size: 0.7rem;
    min-width: 24px;
  }

  .roll-summary {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    padding: 8px;
  }

  .roll-total {
    font-size: 0.95rem;
  }

  .result-badge {
    margin-left: 0;
  }

  .accept-btn {
    font-size: 0.9rem;
    padding: 8px 28px;
  }

  .total-summary {
    padding: 10px;
    margin-bottom: 12px;
  }

  .action-btn-top {
    font-size: 0.9rem;
    padding: 8px 28px;
  }

  .next-btn {
    font-size: 0.95rem;
    padding: 10px 30px;
  }

  .btn-roll {
    padding: 6px 20px;
    font-size: 0.85rem;
  }

  .neg-card-detail {
    padding: 8px 10px;
  }

  .neg-card-name {
    font-size: 0.85rem;
  }

  .neg-card-desc {
    font-size: 0.78rem;
  }
}
</style>
