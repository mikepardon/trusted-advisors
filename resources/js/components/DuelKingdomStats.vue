<template>
  <div class="duel-kingdoms">
    <div
      v-for="kingdom in kingdoms"
      :key="kingdom.id"
      class="kingdom-col"
      :class="{ 'kingdom-yours': kingdom.player_number === myPlayerNumber }"
      :data-kingdom-style="getKingdomStyle(kingdom.player_number)"
      :data-ks-anim="getKingdomAnim(kingdom.player_number)"
      :style="getKingdomColStyle(kingdom.player_number)"
      :ref="el => setColRef(kingdom.player_number, el)"
    >
      <!-- Full-column dice canvas overlay -->
      <canvas :ref="el => setCanvasRef(kingdom.player_number, el)" class="kingdom-dice-canvas" />

      <h3 class="kingdom-name">{{ kingdom.username }}</h3>
      <span v-if="getTitle(kingdom.player_number)" class="kingdom-title">{{ getTitle(kingdom.player_number) }}</span>
      <h5 class="kingdom-username kingdom-name-clickable" @click="$emit('show-character', kingdom.player_number)">{{ kingdom.character_name }}</h5>

      <!-- Roll info (always rendered to prevent layout shift) -->
      <div class="kingdom-roll-info">
        <div class="roll-info required-roll" :class="{ 'roll-info-hidden': !getDifficulty(kingdom.player_number) }">
          <template v-if="getDifficulty(kingdom.player_number)">Required: <strong>{{ getDifficulty(kingdom.player_number) }}</strong></template>
          <template v-else>&nbsp;</template>
        </div>
        <div
          class="roll-info actual-roll"
          :class="[
            getRollResult(kingdom.player_number) ? (isSuccess(kingdom.player_number) ? 'roll-success' : 'roll-failure') : '',
            { 'roll-info-hidden': !getRollResult(kingdom.player_number) }
          ]"
        >
          <template v-if="getRollResult(kingdom.player_number)">
            Rolled: <strong>{{ getRollResult(kingdom.player_number).total_roll }}</strong>
            <span class="result-badge">{{ isSuccess(kingdom.player_number) ? 'SUCCESS' : 'FAILURE' }}</span>
          </template>
          <template v-else>&nbsp;</template>
        </div>
      </div>

      <div class="kingdom-stats">
        <div v-for="stat in stats" :key="stat.key" class="stat-row">
          <span class="stat-icon">{{ stat.icon }}</span>
          <div class="stat-bar-wrap">
            <div class="stat-bar-bg">
              <div
                class="stat-bar"
                :style="{ width: (kingdom[stat.key] / 20 * 100) + '%' }"
                :class="[getBarClass(kingdom[stat.key]), barFlashClass[kingdom.id + '-' + stat.key], { 'bar-flat-right': getPreview(kingdom, stat.key)?.pos > 0 }]"
              ></div>
              <!-- Positive preview (green, extends after current bar) -->
              <div
                v-if="getPreview(kingdom, stat.key)?.pos > 0"
                class="stat-bar-preview preview-positive"
                :style="{
                  left: Math.min(kingdom[stat.key] / 20 * 100, 100) + '%',
                  width: Math.min(getPreview(kingdom, stat.key).pos / 20 * 100, 100 - kingdom[stat.key] / 20 * 100) + '%',
                }"
              ></div>
              <!-- Negative preview (red, overlaps end of current bar) -->
              <div
                v-if="getPreview(kingdom, stat.key)?.neg < 0"
                class="stat-bar-preview preview-negative"
                :style="{
                  left: Math.max((kingdom[stat.key] + getPreview(kingdom, stat.key).neg) / 20 * 100, 0) + '%',
                  width: Math.min(Math.abs(getPreview(kingdom, stat.key).neg) / 20 * 100, kingdom[stat.key] / 20 * 100) + '%',
                }"
              ></div>
            </div>
          </div>
          <span
            class="stat-value"
            :class="[getValueClass(kingdom[stat.key]), flashClass[kingdom.id + '-' + stat.key]]"
          >
            {{ tweenedValues[kingdom.id + '-' + stat.key] ?? kingdom[stat.key] }}
          </span>
        </div>
      </div>
      <div class="kingdom-total">
        <strong>{{ kingdomTotal(kingdom) }}</strong>
      </div>
    </div>
  </div>
</template>

<script>
import { createDddiceInstance, isDddiceAvailable } from '../dddiceService';
import '../styles/kingdom-styles.css';

export default {
  name: 'DuelKingdomStats',
  props: {
    playerKingdoms: { type: Array, default: () => [] },
    myPlayerNumber: { type: Number, default: 1 },
    isSinglePlayerDuel: { type: Boolean, default: false },
    playerDifficulties: { type: Object, default: () => ({}) },
    playerRollResults: { type: Object, default: () => ({}) },
    playerDiceThemes: { type: Object, default: () => ({}) },
    diceAnimationTrigger: { type: Object, default: null },
    playerKingdomStyles: { type: Object, default: () => ({}) },
    playerKingdomStyleData: { type: Object, default: () => ({}) },
    playerTitles: { type: Object, default: () => ({}) },
    previewEffects: { type: Object, default: null },
  },
  emits: ['show-character', 'dice-animation-complete'],
  data() {
    return {
      stats: [
        { key: 'wealth', label: 'Wealth', icon: '\u{1FA99}' },
        { key: 'influence', label: 'Influence', icon: '\u{1F3DB}' },
        { key: 'security', label: 'Security', icon: '\u{1F6E1}' },
        { key: 'religion', label: 'Religion', icon: '\u{1F54C}' },
        { key: 'food', label: 'Food', icon: '\u{1F33E}' },
        { key: 'happiness', label: 'Happiness', icon: '\u{1F3AD}' },
      ],
      prevValues: {},
      tweenedValues: {},
      flashClass: {},
      barFlashClass: {},
      flashTimers: {},
      tweenTimers: {},
      // Dice instances per player
      diceInstances: {},
      canvasRefs: {},
      colRefs: {},
      resizeObserver: null,
    };
  },
  computed: {
    kingdoms() {
      return this.playerKingdoms.map(k => {
        const pn = k.player?.player_number ?? k.player_number;
        const isRealBot = k.player?.is_bot && !k.player?.user;
        const characterName = k.player?.character?.name ?? 'Advisor';
        const displayUsername = isRealBot ? 'Bot' : (k.player?.user?.name || 'Player');
        return {
          ...k,
          player_number: pn,
          character_name: characterName,
          username: pn === this.myPlayerNumber && this.isSinglePlayerDuel ? `${displayUsername} (YOU)` : displayUsername,
        };
      }).sort((a, b) => {
        if (a.player_number === this.myPlayerNumber) return -1;
        if (b.player_number === this.myPlayerNumber) return 1;
        return a.player_number - b.player_number;
      });
    },
  },
  watch: {
    playerKingdoms: {
      handler(newKingdoms) {
        if (!newKingdoms) return;
        for (const k of newKingdoms) {
          const id = k.id;
          for (const stat of this.stats) {
            const key = id + '-' + stat.key;
            const oldVal = this.prevValues[key];
            const newVal = k[stat.key];
            if (oldVal === undefined) {
              this.tweenedValues[key] = newVal;
            } else if (newVal !== oldVal) {
              if (this.flashTimers[key]) clearTimeout(this.flashTimers[key]);
              const direction = newVal > oldVal ? 'up' : 'down';
              this.flashClass[key] = 'flash-' + direction;
              this.barFlashClass[key] = 'bar-flash-' + direction;
              this.flashTimers[key] = setTimeout(() => {
                this.flashClass[key] = '';
                this.barFlashClass[key] = '';
              }, 1200);
              this.animateValue(key, oldVal, newVal);
            }
            this.prevValues[key] = newVal;
          }
        }
      },
      deep: true,
      immediate: true,
    },
    diceAnimationTrigger: {
      async handler(trigger) {
        if (!trigger) return;
        const { playerNumber, rollResult, themes, timestamp } = trigger;
        const instance = this.diceInstances[playerNumber];
        if (!instance || !instance.isReady()) {
          this.$emit('dice-animation-complete', { playerNumber, timestamp });
          return;
        }

        const diceSpecs = (rollResult.rolls || []).map((roll, i) => ({
          theme: (themes || [])[i] || 'dddice-standard',
          value: roll.value,
        }));

        if (diceSpecs.length) {
          try {
            await instance.roll(diceSpecs);
          } catch (err) {
            console.warn('[dddice] Kingdom dice animation failed:', err);
          }
        }
        this.$emit('dice-animation-complete', { playerNumber, timestamp });
      },
      deep: true,
    },
  },
  async mounted() {
    if (isDddiceAvailable()) {
      // Initialize dice instances after DOM is ready
      await this.$nextTick();
      for (const pn of [1, 2]) {
        await this.initDiceInstance(pn);
      }

      // Set up ResizeObserver for canvas resizing
      this.resizeObserver = new ResizeObserver(entries => {
        for (const entry of entries) {
          const el = entry.target;
          for (const pn of [1, 2]) {
            if (this.colRefs[pn] === el && this.diceInstances[pn]) {
              const w = entry.contentRect.width;
              const h = entry.contentRect.height;
              if (w > 0 && h > 0) {
                this.diceInstances[pn].resize(w, h);
              }
            }
          }
        }
      });

      for (const pn of [1, 2]) {
        const col = this.colRefs[pn];
        if (col) {
          this.resizeObserver.observe(col);
        }
      }
    }
  },
  beforeUnmount() {
    Object.values(this.tweenTimers).forEach(id => cancelAnimationFrame(id));
    if (this.resizeObserver) {
      this.resizeObserver.disconnect();
      this.resizeObserver = null;
    }
    for (const pn of [1, 2]) {
      if (this.diceInstances[pn]) {
        this.diceInstances[pn].destroy();
      }
    }
    this.diceInstances = {};
  },
  methods: {
    clearDice(playerNumber) {
      if (playerNumber) {
        if (this.diceInstances[playerNumber]) {
          this.diceInstances[playerNumber].clear();
        }
      } else {
        for (const pn of [1, 2]) {
          if (this.diceInstances[pn]) {
            this.diceInstances[pn].clear();
          }
        }
      }
    },
    setCanvasRef(pn, el) {
      if (el) this.canvasRefs[pn] = el;
    },
    setColRef(pn, el) {
      if (el) this.colRefs[pn] = el;
    },
    async initDiceInstance(pn) {
      const canvas = this.canvasRefs[pn];
      if (!canvas) return;
      const instance = createDddiceInstance();
      const ok = await instance.init(canvas, { diceSize: 1.6 });
      if (ok) {
        this.diceInstances[pn] = instance;
      }
    },
    getKingdomStyle(pn) {
      return this.playerKingdomStyles[pn] || 'classic';
    },
    getKingdomAnim(pn) {
      return this.playerKingdomStyleData[pn]?.css_vars?.border_anim || 'none';
    },
    getKingdomColStyle(pn) {
      const data = this.playerKingdomStyleData[pn];
      const style = {};
      // Apply css_vars from DB as inline custom properties (overrides static CSS)
      if (data?.css_vars) {
        const cv = data.css_vars;
        if (cv.border_color) style['--ks-border-color'] = cv.border_color;
        if (cv.border_glow) style['--ks-border-glow'] = cv.border_glow;
        if (cv.border_color_rgb) style['--ks-border-color-rgb'] = cv.border_color_rgb;
        if (cv.bg_tint) style['--ks-bg-tint'] = cv.bg_tint;
        if (cv.bg_color) style['--ks-bg-color'] = cv.bg_color;
        if (cv.name_accent) style['--ks-name-accent'] = cv.name_accent;
        if (cv.total_accent) style['--ks-total-accent'] = cv.total_accent;
        if (cv.bar_safe) style['--ks-bar-safe'] = cv.bar_safe;
        if (cv.bar_caution) style['--ks-bar-caution'] = cv.bar_caution;
        if (cv.stat_color) style['--ks-stat-color'] = cv.stat_color;
        if (cv.text_color) style['--ks-text-color'] = cv.text_color;
      }
      // Apply background image
      if (data?.background_image_url) {
        style.backgroundImage = `linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url(${data.background_image_url})`;
        style.backgroundSize = 'cover';
        style.backgroundPosition = 'center';
      }
      return style;
    },
    getTitle(pn) {
      return this.playerTitles[pn] || null;
    },
    getDifficulty(pn) {
      return this.playerDifficulties[pn] || null;
    },
    getRollResult(pn) {
      return this.playerRollResults[pn] || null;
    },
    isSuccess(pn) {
      const result = this.getRollResult(pn);
      const difficulty = this.getDifficulty(pn);
      if (!result || !difficulty) return false;
      return result.total_roll >= difficulty;
    },
    animateValue(key, from, to) {
      if (this.tweenTimers[key]) cancelAnimationFrame(this.tweenTimers[key]);
      const duration = 800;
      const start = performance.now();
      const step = (now) => {
        const t = Math.min((now - start) / duration, 1);
        const ease = 1 - Math.pow(1 - t, 3);
        this.tweenedValues[key] = Math.round(from + (to - from) * ease);
        if (t < 1) {
          this.tweenTimers[key] = requestAnimationFrame(step);
        }
      };
      this.tweenTimers[key] = requestAnimationFrame(step);
    },
    kingdomTotal(k) {
      return (k.wealth || 0) + (k.influence || 0) + (k.security || 0)
        + (k.religion || 0) + (k.food || 0) + (k.happiness || 0);
    },
    getBarClass(value) {
      if (value <= 2) return 'bar-critical';
      if (value <= 5) return 'bar-danger';
      if (value <= 8) return 'bar-caution';
      if (value >= 20) return 'bar-max';
      return 'bar-safe';
    },
    getPreview(kingdom, statKey) {
      if (!this.previewEffects || kingdom.player_number !== this.myPlayerNumber) return null;
      const pos = this.previewEffects.positive?.[statKey] || 0;
      const neg = this.previewEffects.negative?.[statKey] || 0;
      if (!pos && !neg) return null;
      const current = kingdom[statKey] || 0;
      return { pos, neg, current };
    },
    getValueClass(value) {
      if (value <= 2) return 'val-critical';
      if (value <= 5) return 'val-danger';
      if (value >= 20) return 'val-max';
      return 'val-safe';
    },
  },
};
</script>

<style scoped>
.duel-kingdoms {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 16px;
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 10px;
}

.kingdom-col {
  display: flex;
  flex-direction: column;
  position: relative;
  border: 2px solid var(--ks-border-color, transparent);
  border-radius: 6px;
  box-shadow: var(--ks-border-glow, none);
  background-color: var(--ks-bg-color, var(--ks-bg-tint, transparent));
  padding: 4px;
  transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
}

.kingdom-col.kingdom-yours .kingdom-name {
  color: var(--ks-name-accent, var(--accent-gold));
}

.kingdom-name {
  font-family: 'Cinzel', serif;
  color: var(--ks-name-accent, var(--text-secondary));
  font-size: 0.8rem;
  text-align: center;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kingdom-title {
  display: block;
  font-family: 'Cinzel', serif;
  font-style: italic;
  color: var(--ks-name-accent, var(--accent-gold));
  font-size: 0.55rem;
  text-align: center;
  margin-bottom: 2px;
  opacity: 0.8;
}

.kingdom-name-clickable {
  cursor: pointer;
  transition: color 0.2s;
}

.kingdom-name-clickable:hover {
  color: var(--accent-gold-bright);
  text-decoration: underline;
}

.kingdom-username {
  font-family: inherit;
  color: var(--ks-text-color, var(--text-secondary));
  font-size: 0.55rem;
  text-align: center;
  margin: -4px 0 4px;
  opacity: 0.7;
  font-weight: 400;
}

/* Full-column dice canvas overlay */
.kingdom-dice-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 5;
  border-radius: 6px;
}

/* Roll info */
.kingdom-roll-info {
  margin-bottom: 4px;
  min-height: 36px;
}

.roll-info-hidden {
  opacity: 0;
  visibility: hidden;
}

.roll-info {
  text-align: center;
  font-family: 'Cinzel', serif;
  font-size: 0.72rem;
  padding: 2px 0;
  color: var(--text-secondary);
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

.required-roll strong {
  color: var(--accent-gold);
}

.actual-roll {
  font-weight: 600;
}

.roll-success {
  color: #4a8a3a;
}

.roll-success strong {
  color: #5ea84a;
}

.roll-failure {
  color: #c0392b;
}

.roll-failure strong {
  color: #e74c3c;
}

.result-badge {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 1px 6px;
  border-radius: 3px;
  font-weight: 700;
  margin-left: 4px;
}

.roll-success .result-badge {
  background: rgba(74, 138, 58, 0.2);
}

.roll-failure .result-badge {
  background: rgba(160, 48, 32, 0.2);
}

.kingdom-stats {
  display: flex;
  flex-direction: column;
  gap: 0;
  flex: 1;
}

.stat-row {
  display: flex;
  align-items: center;
  gap: 4px;
}

.stat-icon {
  font-size: 0.85rem;
  flex-shrink: 0;
  width: 18px;
  text-align: center;
}

.stat-bar-wrap {
  flex: 1;
  min-width: 0;
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
}

.bar-critical { background: #e74c3c; }
.bar-danger { background: #e67e22; }
.bar-caution { background: var(--ks-bar-caution, #d4a843); }
.bar-safe { background: var(--ks-bar-safe, #27ae60); }
.bar-max { background: linear-gradient(90deg, #d4a843, #f0d060); }

.bar-flat-right {
  border-top-right-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}

.stat-bar-preview {
  position: absolute;
  top: 0;
  height: 100%;
  border-radius: 0 4px 4px 0;
  transition: left 0.25s ease, width 0.25s ease, opacity 0.25s ease;
  animation: preview-pulse 1.5s ease-in-out infinite;
}

.preview-positive {
  background: rgba(6, 120, 10, 1);
  box-shadow: 0 0 4px rgba(6, 120, 10, 0.9);
}

.preview-negative {
  background: rgba(249, 23, 0, 0.9);
  box-shadow: 0 0 4px rgba(249, 23, 0, 0.8);
}

@keyframes preview-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

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

.stat-value {
  font-weight: 700;
  font-size: 0.8rem;
  width: 20px;
  text-align: right;
  flex-shrink: 0;
  transition: color 0.5s ease, text-shadow 0.5s ease;
}

.val-safe { color: var(--ks-stat-color, var(--text-bright)); }
.val-danger { color: #e67e22; }
.val-critical { color: #e74c3c; }
.val-max { color: #d4a843; text-shadow: 0 0 6px rgba(212, 168, 67, 0.4); }

.flash-up {
  color: #4caf50 !important;
  text-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
}

.flash-down {
  color: #e74c3c !important;
  text-shadow: 0 0 8px rgba(231, 76, 60, 0.6);
}

.kingdom-total {
  text-align: center;
  margin-top: 0;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.kingdom-total strong {
  color: var(--ks-total-accent, var(--accent-gold));
  font-size: 0.95rem;
}

@media (max-width: 768px) {
  .duel-kingdoms {
    gap: 6px;
    padding: 6px;
  }

  .kingdom-name {
    font-size: 0.7rem;
  }

  .stat-icon {
    font-size: 0.75rem;
    width: 16px;
  }

  .stat-bar-bg {
    height: 6px;
  }

  .stat-value {
    font-size: 0.7rem;
    width: 18px;
  }

  .roll-info {
    font-size: 0.65rem;
  }

  .result-badge {
    font-size: 0.55rem;
  }
}
</style>
