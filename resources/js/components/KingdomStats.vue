<template>
  <div class="kingdom-stats">
    <h3 class="stats-title">Kingdom Status</h3>
    <div class="stats-grid">
      <div v-for="stat in stats" :key="stat.key" class="stat-item">
        <div class="stat-header">
          <span class="stat-icon">{{ stat.icon }}</span>
          <span class="stat-name">{{ stat.label }}</span>
          <span class="stat-value" :class="[getValueClass(game[stat.key]), flashClass[stat.key]]">{{ game[stat.key] }}</span>
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
          <span class="radial-value" :class="[getValueClass(game[stat.key]), flashClass[stat.key]]">{{ game[stat.key] }}</span>
        </div>
      </div>
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
        { key: 'wealth', label: 'Wealth', icon: '\u{1FA99}' },
        { key: 'influence', label: 'Influence', icon: '\u{1F3DB}' },
        { key: 'security', label: 'Security', icon: '\u{1F6E1}' },
        { key: 'religion', label: 'Religion', icon: '\u{1F54C}' },
        { key: 'food', label: 'Food', icon: '\u{1F33E}' },
        { key: 'happiness', label: 'Happiness', icon: '\u{1F3AD}' },
      ],
      prevValues: {},
      flashClass: {},
      barFlashClass: {},
      flashTimers: {},
    };
  },
  watch: {
    game: {
      handler(newGame, oldGame) {
        if (!oldGame || !newGame) return;
        for (const stat of this.stats) {
          const oldVal = this.prevValues[stat.key];
          const newVal = newGame[stat.key];
          if (oldVal !== undefined && newVal !== oldVal) {
            if (this.flashTimers[stat.key]) clearTimeout(this.flashTimers[stat.key]);
            const direction = newVal > oldVal ? 'up' : 'down';
            this.flashClass[stat.key] = 'flash-' + direction;
            this.barFlashClass[stat.key] = 'bar-flash-' + direction;
            this.flashTimers[stat.key] = setTimeout(() => {
              this.flashClass[stat.key] = '';
              this.barFlashClass[stat.key] = '';
            }, 1200);
          }
          this.prevValues[stat.key] = newVal;
        }
      },
      deep: true,
      immediate: true,
    },
  },
  methods: {
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
