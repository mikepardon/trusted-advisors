<template>
  <div class="duel-kingdoms">
    <div
      v-for="kingdom in kingdoms"
      :key="kingdom.id"
      class="kingdom-col"
      :class="{ 'kingdom-yours': kingdom.player_number === myPlayerNumber }"
    >
      <h3 class="kingdom-name">{{ kingdom.character_name }}</h3>
      <div class="kingdom-stats">
        <div v-for="stat in stats" :key="stat.key" class="stat-row">
          <span class="stat-icon">{{ stat.icon }}</span>
          <div class="stat-bar-wrap">
            <div class="stat-bar-bg">
              <div
                class="stat-bar"
                :style="{ width: (kingdom[stat.key] / 20 * 100) + '%' }"
                :class="[getBarClass(kingdom[stat.key]), barFlashClass[kingdom.id + '-' + stat.key]]"
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
export default {
  name: 'DuelKingdomStats',
  props: {
    playerKingdoms: { type: Array, default: () => [] },
    myPlayerNumber: { type: Number, default: 1 },
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
      tweenedValues: {},
      flashClass: {},
      barFlashClass: {},
      flashTimers: {},
      tweenTimers: {},
    };
  },
  computed: {
    kingdoms() {
      return this.playerKingdoms.map(k => ({
        ...k,
        player_number: k.player?.player_number ?? k.player_number,
        character_name: k.player?.character?.name ?? 'Player',
      })).sort((a, b) => {
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
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 10px;
}

.kingdom-col {
  display: flex;
  flex-direction: column;
}

.kingdom-col.kingdom-yours .kingdom-name {
  color: var(--accent-gold);
}

.kingdom-name {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-align: center;
  margin-bottom: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kingdom-stats {
  display: flex;
  flex-direction: column;
  gap: 4px;
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
}

.stat-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s ease;
}

.bar-critical { background: #e74c3c; }
.bar-danger { background: #e67e22; }
.bar-caution { background: #d4a843; }
.bar-safe { background: #27ae60; }
.bar-max { background: linear-gradient(90deg, #d4a843, #f0d060); }

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

.val-safe { color: var(--text-bright); }
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
  margin-top: 6px;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.kingdom-total strong {
  color: var(--accent-gold);
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
}
</style>
