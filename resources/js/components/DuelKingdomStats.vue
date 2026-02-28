<template>
  <div class="duel-kingdoms">
    <div
      v-for="(kingdom, idx) in kingdoms"
      :key="kingdom.id"
      class="kingdom-panel"
      :class="{ 'kingdom-yours': kingdom.player_number === myPlayerNumber }"
    >
      <h3 class="kingdom-title">
        {{ kingdom.player_number === myPlayerNumber ? 'Your Kingdom' : "Rival's Kingdom" }}
        <span class="kingdom-player">{{ kingdom.character_name }}</span>
      </h3>
      <div class="kingdom-stats-grid">
        <div v-for="stat in stats" :key="stat.key" class="kingdom-stat">
          <span class="stat-icon">{{ stat.icon }}</span>
          <span class="stat-name">{{ stat.label }}</span>
          <span
            class="stat-value"
            :class="[getValueClass(kingdom[stat.key]), flashClass[kingdom.id + '-' + stat.key]]"
          >
            {{ kingdom[stat.key] }}
          </span>
          <div class="stat-bar-bg">
            <div
              class="stat-bar"
              :style="{ width: (kingdom[stat.key] / 20 * 100) + '%' }"
              :class="getBarClass(kingdom[stat.key])"
            ></div>
          </div>
        </div>
      </div>
      <div class="kingdom-total">
        Score: <strong>{{ kingdomTotal(kingdom) }}</strong>
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
      flashClass: {},
      flashTimers: {},
    };
  },
  computed: {
    kingdoms() {
      return this.playerKingdoms.map(k => ({
        ...k,
        player_number: k.player?.player_number ?? k.player_number,
        character_name: k.player?.character?.name ?? 'Player',
      })).sort((a, b) => {
        // Show "yours" first
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
            if (oldVal !== undefined && newVal !== oldVal) {
              if (this.flashTimers[key]) clearTimeout(this.flashTimers[key]);
              this.flashClass[key] = newVal > oldVal ? 'flash-up' : 'flash-down';
              this.flashTimers[key] = setTimeout(() => {
                this.flashClass[key] = '';
              }, 1200);
            }
            this.prevValues[key] = newVal;
          }
        }
      },
      deep: true,
      immediate: true,
    },
  },
  methods: {
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
  gap: 12px;
  margin-bottom: 20px;
}

.kingdom-panel {
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 12px;
}

.kingdom-panel.kingdom-yours {
  border-color: var(--accent-gold);
  box-shadow: 0 0 8px rgba(212, 168, 67, 0.15);
}

.kingdom-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  text-align: center;
  margin-bottom: 8px;
}

.kingdom-player {
  display: block;
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-style: italic;
}

.kingdom-stats-grid {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kingdom-stat {
  display: grid;
  grid-template-columns: 20px 1fr auto 60px;
  align-items: center;
  gap: 4px;
  font-size: 0.8rem;
}

.stat-icon { font-size: 0.85rem; }
.stat-name {
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
}

.stat-value {
  font-weight: 700;
  text-align: right;
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

.stat-bar-bg {
  background: rgba(0, 0, 0, 0.4);
  height: 6px;
  border-radius: 3px;
  overflow: hidden;
}

.stat-bar {
  height: 100%;
  border-radius: 3px;
  transition: width 0.5s ease;
}

.bar-critical { background: #e74c3c; }
.bar-danger { background: #e67e22; }
.bar-caution { background: #d4a843; }
.bar-safe { background: #27ae60; }
.bar-max { background: linear-gradient(90deg, #d4a843, #f0d060); }

.kingdom-total {
  text-align: center;
  margin-top: 8px;
  font-size: 0.8rem;
  color: var(--text-secondary);
}

.kingdom-total strong {
  color: var(--accent-gold);
  font-size: 1rem;
}

@media (max-width: 768px) {
  .duel-kingdoms {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .kingdom-panel {
    padding: 8px;
  }

  .kingdom-stat {
    grid-template-columns: 18px 1fr auto 50px;
    font-size: 0.75rem;
  }
}
</style>
