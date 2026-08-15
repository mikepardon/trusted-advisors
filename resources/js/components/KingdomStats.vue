<template>
  <div
    class="kingdom-stats"
    :data-kingdom-style="kingdomStyleSlug"
    :data-ks-anim="kingdomAnim"
    :style="kingdomRootStyle"
  >
    <h3 class="stats-title">Kingdom Status</h3>
    <div class="stats-grid">
      <div v-for="stat in stats" :key="stat.key" class="stat-item">
        <div class="stat-header">
          <span class="stat-name">{{ stat.label }}</span>
          <span class="stat-value" :class="[getValueClass(game[stat.key]), flashClass[stat.key]]">{{ tweenedValues[stat.key] }}</span>
        </div>
        <!-- Desktop: horizontal bar -->
        <div class="stat-bar-bg">
          <div
            class="stat-bar"
            :style="{ width: (game[stat.key] / 20 * 100) + '%' }"
            :class="[getBarClass(stat.key), barFlashClass[stat.key]]"
          ></div>
        </div>
        <!-- Mobile: radial progress with the stat value centred inside -->
        <div class="radial-wrap">
          <svg class="radial-svg" viewBox="0 0 48 48">
            <circle class="radial-bg" cx="24" cy="24" r="20" />
            <circle
              class="radial-fill"
              :class="[getBarClass(stat.key), barFlashClass[stat.key]]"
              cx="24" cy="24" r="20"
              :style="{ strokeDashoffset: radialOffset(game[stat.key]), '--stat-color': statColor(stat.key) }"
            />
          </svg>
          <span class="radial-value" :class="flashClass[stat.key]" :style="{ color: statColor(stat.key) }">{{ tweenedValues[stat.key] }}</span>
        </div>
        <span class="radial-label">{{ statLabel(stat.key) }}</span>
        <span class="stat-short">{{ stat.short }}</span>
      </div>
    </div>
    <div v-if="showLiveScore" class="live-score-row">
      <div v-if="game.bonus_score" class="bonus-score-indicator">
        <span class="bonus-label">Renown</span>
        <span class="bonus-value">{{ game.bonus_score > 0 ? '+' : '' }}{{ game.bonus_score }}</span>
      </div>
      <div v-if="game.score_modifier" class="bonus-score-indicator modifier-indicator">
        <span class="bonus-label">Modifier</span>
        <span class="bonus-value" :class="game.score_modifier > 0 ? 'mod-positive' : 'mod-negative'">{{ game.score_modifier > 0 ? '+' : '' }}{{ game.score_modifier }}%</span>
      </div>
      <div class="live-score-indicator clickable-score" @click="showBreakdown = true">
        <span class="score-label">Realm Score</span>
        <span class="score-value">{{ liveScore }}</span>
        <span class="score-rank" :class="'rank-' + liveRank.toLowerCase()">{{ liveRank }}</span>
      </div>
    </div>
    <div v-else-if="game.bonus_score" class="bonus-score-indicator">
      <span class="bonus-label">Renown</span>
      <span class="bonus-value">{{ game.bonus_score > 0 ? '+' : '' }}{{ game.bonus_score }}</span>
    </div>

    <!-- Score Breakdown Modal -->
    <div v-if="showBreakdown" class="breakdown-overlay" @click.self="showBreakdown = false">
      <div class="breakdown-modal">
        <div class="breakdown-header">
          <h3 class="breakdown-title">Score Breakdown</h3>
          <button class="breakdown-close" @click="showBreakdown = false">&times;</button>
        </div>
        <div class="breakdown-body">
          <div class="breakdown-row">
            <span>Kingdom Total</span>
            <span>{{ liveBreakdown.base }}</span>
          </div>
          <div class="breakdown-row">
            <span>Year Multiplier</span>
            <span>&times;{{ liveBreakdown.yearMultiplier }}</span>
          </div>
          <div class="breakdown-row breakdown-subtotal">
            <span>Subtotal</span>
            <span>{{ liveBreakdown.multiplied }}</span>
          </div>
          <div class="breakdown-row">
            <span>Balance Bonus</span>
            <span>+{{ liveBreakdown.balance }}</span>
          </div>
          <div v-if="liveBreakdown.stacking" class="breakdown-row">
            <span>Stacking Bonus</span>
            <span>+{{ liveBreakdown.stacking }}</span>
          </div>
          <div v-if="liveBreakdown.yearBonus" class="breakdown-row">
            <span>Year Bonus</span>
            <span>+{{ liveBreakdown.yearBonus }}</span>
          </div>
          <div v-if="liveBreakdown.bonus" class="breakdown-row">
            <span>Renown</span>
            <span>+{{ liveBreakdown.bonus }}</span>
          </div>
          <div v-if="liveBreakdown.curseBonus" class="breakdown-row">
            <span>Curse Rewards</span>
            <span>+{{ liveBreakdown.curseBonus }}</span>
          </div>
          <div v-if="liveBreakdown.modifier" class="breakdown-row">
            <span>Score Modifier</span>
            <span :class="liveBreakdown.modifier > 0 ? 'mod-positive' : 'mod-negative'">{{ liveBreakdown.modifier > 0 ? '+' : '' }}{{ liveBreakdown.modifier }}%</span>
          </div>
          <div class="breakdown-row breakdown-final">
            <span>Final Score</span>
            <span>{{ liveBreakdown.final }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useIcons } from "../stores/icons";
import "../styles/kingdom-styles.css";

interface StatIcon {
  key: string;
  label: string;
  short: string;
  type: string;
  value: string;
  icon: string;
}

interface KingdomCssVariables {
  border_anim?: string;
  border_color?: string;
  border_glow?: string;
  border_color_rgb?: string;
  bg_tint?: string;
  bg_color?: string;
  name_accent?: string;
  total_accent?: string;
  bar_caution?: string;
  stat_color?: string;
  text_color?: string;
}

interface KingdomStyleData {
  css_vars?: KingdomCssVariables;
  background_image_url?: string;
}

interface CurseEffect {
  type?: string;
  value?: number;
}

interface PlayerCurse {
  curse?: {
    positive_effect?: CurseEffect;
    positive_effect_duel?: CurseEffect;
  };
}

interface GamePlayer {
  curses?: PlayerCurse[];
}

interface GameState {
  game_type?: string;
  status?: string;
  wealth?: number;
  influence?: number;
  security?: number;
  religion?: number;
  food?: number;
  happiness?: number;
  current_round?: number;
  bonus_score?: number;
  score_modifier?: number;
  players?: GamePlayer[];
  [key: string]: unknown;
}

interface PreviewEffects {
  positive?: Record<string, number>;
  negative?: Record<string, number>;
}

interface LiveBreakdown {
  base: number;
  yearMultiplier: number;
  multiplied: number;
  balance: number;
  stacking: number;
  yearBonus: number;
  bonus: number;
  curseBonus: number;
  modifier: number;
  final: number;
}

interface StatPreview {
  pos: number;
  neg: number;
}

const {
  game,
  kingdomStyleSlug = "classic",
  kingdomStyleData = undefined,
  previewEffects = undefined,
} = defineProps<{
  game: GameState;
  kingdomStyleSlug?: string;
  kingdomStyleData?: KingdomStyleData;
  previewEffects?: PreviewEffects;
}>();

const stats: StatIcon[] = useIcons().getStatIcons();
const previousValues = reactive<Record<string, number>>({});
const tweenedValues = reactive<Record<string, number>>({});
const flashClass = reactive<Record<string, string>>({});
const barFlashClass = reactive<Record<string, string>>({});
const flashTimers: Record<string, ReturnType<typeof setTimeout>> = {};
const tweenTimers: Record<string, number> = {};
const showBreakdown = ref(false);

const kingdomAnim = computed<string>(() => kingdomStyleData?.css_vars?.border_anim || "none");

const kingdomRootStyle = computed<Record<string, string>>(() => {
  const style: Record<string, string> = {};
  const data = kingdomStyleData;
  // Apply css_vars from DB as inline custom properties (overrides static CSS)
  if (data?.css_vars) {
    const cv = data.css_vars;
    if (cv.border_color) {
      style["--ks-border-color"] = cv.border_color;
    }
    if (cv.border_glow) {
      style["--ks-border-glow"] = cv.border_glow;
    }
    if (cv.border_color_rgb) {
      style["--ks-border-color-rgb"] = cv.border_color_rgb;
    }
    if (cv.bg_tint) {
      style["--ks-bg-tint"] = cv.bg_tint;
    }
    if (cv.bg_color) {
      style["--ks-bg-color"] = cv.bg_color;
    }
    if (cv.name_accent) {
      style["--ks-name-accent"] = cv.name_accent;
    }
    if (cv.total_accent) {
      style["--ks-total-accent"] = cv.total_accent;
    }
    if (cv.bar_caution) {
      style["--ks-bar-caution"] = cv.bar_caution;
      style["--ks-bar-caution-stroke"] = extractSolidColor(cv.bar_caution, "#d4a843");
    }
    if (cv.stat_color) {
      style["--ks-stat-color"] = cv.stat_color;
    }
    if (cv.text_color) {
      style["--ks-text-color"] = cv.text_color;
    }
  }
  // Apply background image
  if (data?.background_image_url) {
    style.backgroundImage = `linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url(${data.background_image_url})`;
    style.backgroundSize = "cover";
    style.backgroundPosition = "center";
  }
  return style;
});

const showLiveScore = computed<boolean>(() => game.game_type !== "duel" && game.status !== "completed" && game.status !== "cancelled");

const liveBreakdown = computed<LiveBreakdown>(() => {
  const statVals = [game.wealth || 0, game.influence || 0, game.security || 0, game.religion || 0, game.food || 0, game.happiness || 0];
  const base = statVals.reduce((sum, value) => sum + value, 0);
  const yearsCompleted = Math.floor((game.current_round || 0) / 12);
  const years = yearsCompleted + 1;
  const multipliers: Record<number, number> = { 1: 1, 2: 1.4, 3: 1.7, 4: 1.9, 5: 2 };
  const yearMultiplier = multipliers[years] || 2;
  const multiplied = Math.floor(base * yearMultiplier);
  const spread = Math.max(...statVals) - Math.min(...statVals);
  const balance = Math.max(0, 30 - spread * 3);
  const yearBonus = yearsCompleted * 50;
  let stacking = 0;
  for (const value of statVals) {
    if (value >= 15) {
      stacking += 10;
    }
    if (value >= 20) {
      stacking += 20;
    }
  }
  const bonus = game.bonus_score || 0;
  const modifier = game.score_modifier || 0;

  // Calculate curse end-game bonuses (preview)
  let curseBonus = 0;
  const isDuel = game.game_type === "duel";
  if (game.players) {
    for (const player of game.players) {
      const playerCurses = player.curses || [];
      for (const pc of playerCurses) {
        const pos = isDuel ? (pc.curse?.positive_effect_duel || pc.curse?.positive_effect) : pc.curse?.positive_effect;
        if (pos?.type === "score_bonus") {
          curseBonus += (pos.value || 0);
        }
      }
    }
  }

  const rawTotal = multiplied + balance + stacking + yearBonus + bonus + curseBonus;
  const final = Math.floor(rawTotal * (1 + modifier / 100));
  return { base, yearMultiplier, multiplied, balance, stacking, yearBonus, bonus, curseBonus, modifier, final };
});

const liveScore = computed<number>(() => liveBreakdown.value.final);

const liveRank = computed<string>(() => {
  const score = liveScore.value;
  if (score >= 200) {
    return "Legendary";
  }
  if (score >= 150) {
    return "Excellent";
  }
  if (score >= 100) {
    return "Good";
  }
  if (score >= 60) {
    return "Adequate";
  }
  return "Poor";
});

watch(
  () => game,
  (newGame, oldGame) => {
    if (!newGame) {
      return;
    }
    for (const stat of stats) {
      const oldValue = previousValues[stat.key];
      const newValue = newGame[stat.key] as number;
      if (oldValue === undefined || !oldGame) {
        tweenedValues[stat.key] = newValue;
      } else if (newValue !== oldValue) {
        if (Object.hasOwn(flashTimers, stat.key)) {
          clearTimeout(flashTimers[stat.key]);
        }
        const direction = newValue > oldValue ? "up" : "down";
        flashClass[stat.key] = `flash-${direction}`;
        barFlashClass[stat.key] = `bar-flash-${direction}`;
        flashTimers[stat.key] = setTimeout(() => {
          flashClass[stat.key] = "";
          barFlashClass[stat.key] = "";
        }, 1200);
        animateValue(stat.key, oldValue, newValue);
      }
      previousValues[stat.key] = newValue;
    }
  },
  { deep: true, immediate: true },
);

onBeforeUnmount(() => {
  for (const id of Object.values(tweenTimers)) {
    cancelAnimationFrame(id);
  }
});

function animateValue(key: string, from: number, to: number): void {
  if (Object.hasOwn(tweenTimers, key)) {
    cancelAnimationFrame(tweenTimers[key]);
  }
  const duration = 800;
  const start = performance.now();
  const step = (now: number): void => {
    const t = Math.min((now - start) / duration, 1);
    const ease = 1 - (1 - t) ** 3; // ease-out cubic
    tweenedValues[key] = Math.round(from + (to - from) * ease);
    if (t < 1) {
      tweenTimers[key] = requestAnimationFrame(step);
    }
  };
  tweenTimers[key] = requestAnimationFrame(step);
}

function getPreview(statKey: string): StatPreview | undefined {
  if (!previewEffects) {
    return undefined;
  }
  const pos = previewEffects.positive?.[statKey] || 0;
  const neg = previewEffects.negative?.[statKey] || 0;
  if (!pos && !neg) {
    return undefined;
  }
  return { pos, neg };
}

function getBarClass(statKey: string): string {
  const preview = getPreview(statKey);
  if (preview) {
    if (preview.pos > 0 && preview.neg < 0) {
      return "bar-preview-mixed";
    }
    if (preview.pos > 0) {
      return "bar-preview-positive";
    }
    if (preview.neg < 0) {
      return "bar-preview-negative";
    }
  }
  return "bar-default";
}

function getValueClass(value: number): string {
  if (value <= 2) {
    return "val-critical";
  }
  if (value <= 5) {
    return "val-danger";
  }
  return "val-safe";
}

// Short thematic label shown under each radial ring (fits the compact ring and
// matches the board mockup). The underlying stat keys are unchanged.
function statLabel(statKey: string): string {
  const labels: Record<string, string> = {
    wealth: "Wealth",
    influence: "People",
    security: "Military",
    religion: "Church",
    food: "Food",
    happiness: "Mood",
  };
  return labels[statKey] ?? statKey;
}

// Signature ring colour per stat (used as the radial's default arc colour;
// card-effect previews still override it via the bar-preview-* classes).
function statColor(statKey: string): string {
  const colours: Record<string, string> = {
    wealth: "#f0c050",
    influence: "#b072e0",
    security: "#4d9de0",
    religion: "#cdd3dc",
    food: "#5fad56",
    happiness: "#e0685a",
  };
  return colours[statKey] ?? "#f0c050";
}

function radialOffset(value: number): number {
  const circumference = 2 * Math.PI * 20; // ~125.66
  const pct = Math.min(value, 20) / 20;
  return circumference * (1 - pct);
}

function extractSolidColor(value: string | undefined, fallback: string): string {
  if (!value || typeof value !== "string") {
    return fallback;
  }
  // If it's not a gradient, return as-is (it's already a solid color)
  if (!value.includes("gradient")) {
    return value;
  }
  // Extract the first color from a gradient string
  const hexMatch = value.match(/#[0-9a-fA-F]{3,8}/);
  if (hexMatch) {
    return hexMatch[0];
  }
  const rgbMatch = value.match(/rgba?\([^)]+\)/);
  if (rgbMatch) {
    return rgbMatch[0];
  }
  return fallback;
}
</script>

<style scoped>
.kingdom-stats {
  border: 2px solid var(--ks-border-color, var(--border-gold));
  border-radius: 8px;
  box-shadow: var(--ks-border-glow, none);
  background-color: var(--ks-bg-color, var(--ks-bg-tint, var(--bg-secondary)));
  padding: 15px;
  margin-bottom: 20px;
  transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
}

.stats-title {
  font-family: 'Cinzel', serif;
  color: var(--ks-name-accent, var(--accent-gold));
  text-align: center;
  margin-bottom: 12px;
  font-size: 1.1rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-item {
  padding: 5px;
}

.stat-header {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 4px;
  font-size: 0.9rem;
}

.stat-icon {
  font-size: 1rem;
}

.stat-name {
  flex: 1;
  color: var(--ks-text-color, var(--text-secondary));
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
}

.stat-value {
  font-weight: 700;
}

.val-safe { color: var(--ks-stat-color, var(--text-bright)); }
.val-danger { color: #e67e22; }
.val-critical { color: #e74c3c; font-size: 1.1em; }

.flash-up {
  color: #4caf50 !important;
  text-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
  transition: color 0.3s ease, text-shadow 0.3s ease;
}

.flash-down {
  color: #e74c3c !important;
  text-shadow: 0 0 8px rgba(231, 76, 60, 0.6);
  transition: color 0.3s ease, text-shadow 0.3s ease;
}

.stat-value {
  transition: color 0.5s ease, text-shadow 0.5s ease;
}

.stat-bar-bg {
  background: rgba(0, 0, 0, 0.4);
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
  position: relative;
}

.stat-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s ease;
  position: relative;
  z-index: 1;
}

.bar-default { background: var(--ks-bar-caution, #d4a843); transition: background 0.25s ease; }
.bar-preview-positive { background: #4caf50; transition: background 0.25s ease; }
.bar-preview-negative { background: #e74c3c; transition: background 0.25s ease; }
.bar-preview-mixed { background: #e67e22; transition: background 0.25s ease; }

.bar-flash-up {
  background: #4caf50 !important;
  box-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
  transition: background 0.3s ease, box-shadow 0.3s ease;
}

.bar-flash-down {
  background: #e74c3c !important;
  box-shadow: 0 0 8px rgba(231, 76, 60, 0.6);
  transition: background 0.3s ease, box-shadow 0.3s ease;
}

/* Short stat labels */
.stat-short {
  display: block;
  font-size: 1.2rem;
  text-align: center;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  letter-spacing: 0.5px;
  margin-top: 2px;
}

/* Radial progress — hidden on desktop */
.radial-wrap {
  display: none;
  position: relative;
  width: 48px;
  height: 48px;
}

.radial-svg {
  width: 48px;
  height: 48px;
  transform: rotate(-90deg);
}

.radial-value {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-family: 'Cinzel', serif;
  font-weight: 800;
  font-size: 1rem;
  line-height: 1;
}

/* Label under each radial (mobile only; desktop uses the bar + name row) */
.radial-label {
  display: none;
  font-family: 'Cinzel', serif;
  font-size: 0.56rem;
  font-weight: 700;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  color: #b09a6c;
  text-align: center;
  margin-top: 4px;
  line-height: 1;
}

.radial-bg {
  fill: none;
  stroke: rgba(0, 0, 0, 0.4);
  stroke-width: 4;
}

.radial-fill {
  fill: none;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-dasharray: 125.66;
  transition: stroke-dashoffset 0.5s ease;
}

.radial-fill.bar-default { stroke: var(--stat-color, var(--ks-bar-caution-stroke, var(--ks-bar-caution, #d4a843))); transition: stroke 0.25s ease; }
.radial-fill.bar-preview-positive { stroke: #4caf50; transition: stroke 0.25s ease; }
.radial-fill.bar-preview-negative { stroke: #e74c3c; transition: stroke 0.25s ease; }
.radial-fill.bar-preview-mixed { stroke: #e67e22; transition: stroke 0.25s ease; }

.radial-fill.bar-flash-up { stroke: #4caf50 !important; }
.radial-fill.bar-flash-down { stroke: #e74c3c !important; }

.live-score-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 12px;
  flex-wrap: wrap;
  background: rgba(0, 0, 0, 0.28);
  border: 1px solid rgba(240, 192, 80, 0.28);
  border-radius: 18px;
  padding: 11px 16px;
}

.live-score-indicator {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0;
  background: none;
  border: none;
  font-size: 0.85rem;
}

.score-label {
  color: #c9a24a;
  font-family: 'Cinzel', serif;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.score-value {
  color: var(--accent-gold, #f0c050);
  font-family: 'Cinzel', serif;
  font-weight: 800;
  font-size: 1.15rem;
}

.score-rank {
  font-family: 'Cinzel', serif;
  font-size: 0.68rem;
  padding: 2px 8px;
  border-radius: 6px;
  border: 1px solid currentColor;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.rank-legendary { color: #f0c040; }
.rank-excellent { color: #6cc070; }
.rank-good { color: #60b8e0; }
.rank-adequate { color: #b7a486; }
.rank-poor { color: #e57373; }

.bonus-score-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 4px 12px;
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 6px;
  font-size: 0.85rem;
}

.bonus-label {
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
}

.bonus-value {
  color: var(--accent-gold);
  font-weight: 700;
}

.modifier-indicator {
  background: rgba(138, 58, 185, 0.1);
  border-color: rgba(138, 58, 185, 0.3);
}

.mod-positive { color: #4caf50; }
.mod-negative { color: #e74c3c; }

.clickable-score {
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease;
}

.clickable-score:hover {
  opacity: 0.85;
}

/* Score Breakdown Modal */
.breakdown-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.breakdown-modal {
  background: var(--bg-secondary, #1a1209);
  border: 2px solid var(--accent-gold, #d4a843);
  border-radius: 10px;
  padding: 0;
  min-width: 280px;
  max-width: 360px;
  box-shadow: 0 0 30px rgba(212, 168, 67, 0.2);
}

.breakdown-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px 10px;
  border-bottom: 1px solid rgba(184, 148, 46, 0.2);
}

.breakdown-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #d4a843);
  font-size: 1rem;
  margin: 0;
}

.breakdown-close {
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.4rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

.breakdown-close:hover {
  color: var(--text-bright);
}

.breakdown-body {
  padding: 12px 18px 16px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.breakdown-row span:last-child {
  color: var(--text-bright);
  font-weight: 600;
}

.breakdown-subtotal {
  border-bottom: 1px solid rgba(184, 148, 46, 0.15);
  padding-bottom: 6px;
  margin-bottom: 4px;
}

.breakdown-final {
  border-top: 1px solid rgba(184, 148, 46, 0.3);
  margin-top: 6px;
  padding-top: 8px;
  font-size: 1rem;
}

.breakdown-final span:last-child {
  color: var(--accent-gold, #d4a843);
  font-weight: 900;
  font-family: 'Cinzel', serif;
}

/* ---- Mobile compact mode ---- */
@media (max-width: 768px) {
  .kingdom-stats {
    padding: 0 8px;
    margin-bottom: 10px;
  }

  .stats-title {
    display: none;
  }

  .stats-grid {
    grid-template-columns: repeat(6, 1fr);
    gap: 2px;
    background: rgba(0, 0, 0, 0.24);
    border: 1px solid rgba(240, 192, 80, 0.24);
    border-radius: 18px;
    padding: 12px 6px 8px;
  }

  .stat-item {
    padding: 4px 2px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .stat-header {
    flex-direction: column;
    gap: 1px;
    margin-bottom: 0;
    font-size: 0.8rem;
  }

  .stat-icon {
    font-size: 1.1rem;
  }

  .stat-name {
    display: none;
  }

  .stat-short {
    display: none;
  }

  .stat-value {
    display: none;
  }

  .stat-bar-bg {
    display: none;
  }

  .radial-wrap {
    display: block;
    width: 46px;
    height: 46px;
  }

  .radial-svg {
    width: 46px;
    height: 46px;
  }

  .radial-value {
    font-size: 1.15rem;
  }

  .radial-label {
    display: block;
  }
}
</style>
