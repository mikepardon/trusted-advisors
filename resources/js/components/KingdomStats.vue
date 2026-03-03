<template>
  <div class="kingdom-stats">
    <h3 class="stats-title">Kingdom Status</h3>
    <div class="stats-grid">
      <div v-for="stat in stats" :key="stat.key" class="stat-item">
        <div class="stat-header">
          <span class="stat-icon">{{ stat.icon }}</span>
          <span class="stat-name">{{ stat.label }}</span>
          <span class="stat-value" :class="[getValueClass(game[stat.key]), flashClass[stat.key]]">{{ tweenedValues[stat.key] }}</span>
        </div>
        <!-- Desktop: horizontal bar -->
        <div class="stat-bar-bg">
          <div class="danger-zone danger-low"></div>
          <div class="safe-zone"></div>
          <div
            class="stat-bar"
            :style="{ width: (game[stat.key] / 20 * 100) + '%' }"
            :class="[getBarClass(game[stat.key]), barFlashClass[stat.key]]"
          ></div>
        </div>
        <!-- Mobile: radial progress with number centered inside -->
        <div class="radial-wrap">
          <svg class="radial-svg" viewBox="0 0 48 48">
            <circle class="radial-bg" cx="24" cy="24" r="20" />
            <circle
              class="radial-fill"
              :class="[getBarClass(game[stat.key]), barFlashClass[stat.key]]"
              cx="24" cy="24" r="20"
              :style="{ strokeDashoffset: radialOffset(game[stat.key]) }"
            />
          </svg>
          <span class="radial-value" :class="[getValueClass(game[stat.key]), flashClass[stat.key]]">{{ tweenedValues[stat.key] }}</span>
        </div>
        <span class="stat-short">{{ stat.short }}</span>
      </div>
    </div>
    <div v-if="showLiveScore" class="live-score-row">
      <div v-if="game.bonus_score" class="bonus-score-indicator">
        <span class="bonus-label">Renown</span>
        <span class="bonus-value">+{{ game.bonus_score }}</span>
      </div>
      <div class="live-score-indicator">
        <span class="score-label">Score</span>
        <span class="score-value">{{ liveScore }}</span>
        <span class="score-rank" :class="'rank-' + liveRank.toLowerCase()">{{ liveRank }}</span>
      </div>
    </div>
    <div v-else-if="game.bonus_score" class="bonus-score-indicator">
      <span class="bonus-label">Renown</span>
      <span class="bonus-value">+{{ game.bonus_score }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'KingdomStats',
  props: {
    game: { type: Object, required: true },
  },
  data() {
    return {
      stats: [
        { key: 'wealth', label: 'Wealth', short: 'W', icon: '\u{1FA99}' },
        { key: 'influence', label: 'Influence', short: 'INF', icon: '\u{1F3DB}' },
        { key: 'security', label: 'Security', short: 'SEC', icon: '\u{1F6E1}' },
        { key: 'religion', label: 'Religion', short: 'REL', icon: '\u{1F54C}' },
        { key: 'food', label: 'Food', short: 'FD', icon: '\u{1F33E}' },
        { key: 'happiness', label: 'Happiness', short: 'HAP', icon: '\u{1F3AD}' },
      ],
      prevValues: {},
      tweenedValues: {},
      flashClass: {},
      barFlashClass: {},
      flashTimers: {},
      tweenTimers: {},
    };
  },
  computed: {
    showLiveScore() {
      return this.game.game_type !== 'duel' && this.game.status !== 'completed' && this.game.status !== 'cancelled';
    },
    liveScore() {
      const g = this.game;
      const base = (g.wealth || 0) + (g.influence || 0) + (g.security || 0) + (g.religion || 0) + (g.food || 0) + (g.happiness || 0);
      const years = Math.max(1, Math.floor((g.total_rounds || 12) / 12));
      const multipliers = { 1: 1.0, 2: 1.4, 3: 1.7, 4: 1.9, 5: 2.0 };
      const mult = multipliers[years] || 1.0;
      const stats = [g.wealth || 0, g.influence || 0, g.security || 0, g.religion || 0, g.food || 0, g.happiness || 0];
      const spread = Math.max(...stats) - Math.min(...stats);
      const balance = Math.max(0, 30 - spread * 3);
      const bonus = g.bonus_score || 0;
      return Math.floor(base * mult) + balance + bonus;
    },
    liveRank() {
      const s = this.liveScore;
      if (s >= 200) return 'Legendary';
      if (s >= 150) return 'Excellent';
      if (s >= 100) return 'Good';
      if (s >= 60) return 'Adequate';
      return 'Poor';
    },
  },
  watch: {
    game: {
      handler(newGame, oldGame) {
        if (!newGame) return;
        for (const stat of this.stats) {
          const oldVal = this.prevValues[stat.key];
          const newVal = newGame[stat.key];
          if (oldVal === undefined || !oldGame) {
            this.tweenedValues[stat.key] = newVal;
          } else if (newVal !== oldVal) {
            if (this.flashTimers[stat.key]) clearTimeout(this.flashTimers[stat.key]);
            const direction = newVal > oldVal ? 'up' : 'down';
            this.flashClass[stat.key] = 'flash-' + direction;
            this.barFlashClass[stat.key] = 'bar-flash-' + direction;
            this.flashTimers[stat.key] = setTimeout(() => {
              this.flashClass[stat.key] = '';
              this.barFlashClass[stat.key] = '';
            }, 1200);
            this.animateValue(stat.key, oldVal, newVal);
          }
          this.prevValues[stat.key] = newVal;
        }
      },
      deep: true,
      immediate: true,
    },
  },
  beforeUnmount() {
    Object.values(this.tweenTimers).forEach(id => cancelAnimationFrame(id));
  },
  methods: {
    animateValue(key, from, to) {
      if (this.tweenTimers[key]) cancelAnimationFrame(this.tweenTimers[key]);
      const duration = 800;
      const start = performance.now();
      const step = (now) => {
        const t = Math.min((now - start) / duration, 1);
        const ease = 1 - Math.pow(1 - t, 3); // ease-out cubic
        this.tweenedValues[key] = Math.round(from + (to - from) * ease);
        if (t < 1) {
          this.tweenTimers[key] = requestAnimationFrame(step);
        }
      };
      this.tweenTimers[key] = requestAnimationFrame(step);
    },
    getBarClass(value) {
      if (value <= 2) return 'bar-critical';
      if (value <= 5) return 'bar-danger-low';
      if (value <= 8) return 'bar-caution-low';
      return 'bar-safe';
    },
    getValueClass(value) {
      if (value <= 2) return 'val-critical';
      if (value <= 5) return 'val-danger';
      return 'val-safe';
    },
    radialOffset(value) {
      const circumference = 2 * Math.PI * 20; // ~125.66
      const pct = Math.min(value, 20) / 20;
      return circumference * (1 - pct);
    },
  },
};
</script>

<style scoped>
.kingdom-stats {
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 20px;
}

.stats-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
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
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
}

.stat-value {
  font-weight: 700;
}

.val-safe { color: var(--text-bright); }
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

/* Danger zone indicator at low end only */
.danger-zone {
  position: absolute;
  top: 0;
  height: 100%;
  opacity: 0.3;
}

.danger-low {
  left: 0;
  width: 10%;
  background: linear-gradient(to right, #e74c3c, transparent);
}

.safe-zone {
  position: absolute;
  left: 25%;
  right: 0;
  top: 0;
  height: 100%;
  opacity: 0.1;
  background: #27ae60;
}

.stat-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s ease;
  position: relative;
  z-index: 1;
}

.bar-critical { background: #e74c3c; }
.bar-danger-low { background: #e67e22; }
.bar-caution-low { background: #d4a843; }
.bar-safe { background: #27ae60; }

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

/* Short stat labels — hidden on desktop */
.stat-short {
  display: none;
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
  font-weight: 700;
  font-size: 1rem;
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

.radial-fill.bar-critical { stroke: #e74c3c; }
.radial-fill.bar-danger-low { stroke: #e67e22; }
.radial-fill.bar-caution-low { stroke: #d4a843; }
.radial-fill.bar-safe { stroke: #27ae60; }

.radial-fill.bar-flash-up { stroke: #4caf50 !important; }
.radial-fill.bar-flash-down { stroke: #e74c3c !important; }

.live-score-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 8px;
  flex-wrap: wrap;
}

.live-score-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  background: rgba(39, 174, 96, 0.1);
  border: 1px solid rgba(39, 174, 96, 0.3);
  border-radius: 6px;
  font-size: 0.85rem;
}

.score-label {
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
}

.score-value {
  color: var(--text-bright);
  font-weight: 700;
  font-size: 1rem;
}

.score-rank {
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.rank-legendary { color: #f0c040; background: rgba(240, 192, 64, 0.15); }
.rank-excellent { color: #4caf50; background: rgba(76, 175, 80, 0.15); }
.rank-good { color: #60b8e0; background: rgba(96, 184, 224, 0.15); }
.rank-adequate { color: var(--text-secondary); background: rgba(160, 144, 128, 0.15); }
.rank-poor { color: #e57373; background: rgba(229, 115, 115, 0.15); }

.bonus-score-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 8px;
  padding: 4px 10px;
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

/* ---- Mobile compact mode ---- */
@media (max-width: 768px) {
  .kingdom-stats {
    padding: 8px;
    margin-bottom: 10px;
  }

  .stats-title {
    display: none;
  }

  .stats-grid {
    grid-template-columns: repeat(6, 1fr);
    gap: 4px;
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
    display: block;
    font-size: 0.55rem;
    text-align: center;
    color: var(--text-secondary);
    font-family: 'Cinzel', serif;
    letter-spacing: 0.5px;
    margin-top: 2px;
  }

  .stat-value {
    display: none;
  }

  .stat-bar-bg {
    display: none;
  }

  .radial-wrap {
    display: block;
  }

  .radial-value {
    font-size: 1.2rem;
  }
}
</style>
