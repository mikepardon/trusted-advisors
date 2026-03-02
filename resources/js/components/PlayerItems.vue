<template>
  <!-- Inventory icon button (only visible when player has items and showButton is true) -->
  <div v-if="showButton && items && items.length" class="items-icon-wrapper">
    <button class="items-icon-btn" @click="open = true" title="View Inventory">
      <svg class="items-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M20 7H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1Z"/>
        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
        <path d="M12 11v4M10 13h4"/>
      </svg>
      <span class="items-badge">{{ items.length }}</span>
    </button>
  </div>

  <!-- Card viewer overlay -->
  <teleport to="body">
    <transition name="items-overlay">
      <div v-if="open" class="items-overlay" @click.self="open = false">
        <div class="items-viewer">
          <button class="items-close" @click="open = false">&times;</button>

          <p class="items-heading">Inventory</p>

          <!-- Parchment card display -->
          <div class="parchment-card" :class="{ cursed: currentItem.is_cursed || currentItem.item?.is_negative, used: currentItem.is_used }">
            <div class="card-ornament">&#9876;</div>
            <h3 class="parchment-title">{{ currentItem.item?.name || 'Unknown Item' }}</h3>

            <!-- Tags row -->
            <div class="tag-row">
              <span v-if="currentItem.is_cursed" class="cursed-tag">Cursed</span>
              <span v-if="currentItem.item?.is_consumable" class="type-tag immediate-tag">Immediate</span>
              <span v-else-if="currentItem.item?.effect_type" class="type-tag ongoing-tag">Ongoing</span>
              <span v-if="currentItem.is_used" class="type-tag used-tag">Used</span>
            </div>

            <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

            <p class="parchment-desc">{{ currentItem.item?.description || '' }}</p>

            <div class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

            <!-- Effect in stat-chip style -->
            <div v-if="effectSummary(currentItem.item)" class="outcome-chips">
              <span class="stat-chip" :class="effectChipClass(currentItem)">
                {{ effectSummary(currentItem.item) }}
              </span>
            </div>

            <div class="card-meta">
              <span v-if="currentItem.item?.effect_type" class="meta-type">{{ currentItem.item.effect_type }}</span>
            </div>
          </div>

          <!-- Navigation -->
          <div v-if="items.length > 1" class="items-nav">
            <button class="nav-btn" @click="prev">&lsaquo;</button>
            <span class="nav-counter">{{ currentIndex + 1 }} / {{ items.length }}</span>
            <button class="nav-btn" @click="next">&rsaquo;</button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
export default {
  name: 'PlayerItems',
  props: {
    items: { type: Array, default: () => [] },
    showButton: { type: Boolean, default: true },
  },
  data() {
    return {
      open: false,
      currentIndex: 0,
    };
  },
  computed: {
    currentItem() {
      return this.items[this.currentIndex] || {};
    },
  },
  watch: {
    items() {
      // Reset index when items change
      if (this.currentIndex >= this.items.length) {
        this.currentIndex = 0;
      }
    },
  },
  methods: {
    openOverlay() {
      if (this.items && this.items.length) {
        this.open = true;
      }
    },
    prev() {
      this.currentIndex = this.currentIndex <= 0 ? this.items.length - 1 : this.currentIndex - 1;
    },
    next() {
      this.currentIndex = this.currentIndex >= this.items.length - 1 ? 0 : this.currentIndex + 1;
    },
    effectSummary(item) {
      if (!item?.effect) return '';
      const type = item.effect.bonus_type || '';
      const value = item.effect.bonus_value ?? 0;
      switch (type) {
        case 'roll_bonus':
          return `+${value} to rolls`;
        case 'roll_penalty':
          return `${value} to rolls`;
        case 'difficulty_reduction':
          return `-${Math.abs(value)} difficulty`;
        case 'difficulty_increase':
          return `+${Math.abs(value)} difficulty`;
        case 'score_bonus':
          return `${value > 0 ? '+' : ''}${value} renown`;
        case 'score_per_round':
          return `+${value} renown/round`;
        case 'score_multiplier':
          return `${value}x score multiplier`;
        default:
          return item.description || 'Passive effect';
      }
    },
    effectChipClass(pi) {
      const type = pi.item?.effect?.bonus_type || '';
      if (pi.is_cursed || type === 'roll_penalty' || type === 'difficulty_increase') return 'chip-negative';
      if (type === 'roll_bonus' || type === 'difficulty_reduction') return 'chip-positive';
      return 'chip-neutral';
    },
  },
};
</script>

<style scoped>
/* ---- Icon button ---- */
.items-icon-wrapper {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 800;
}

.items-icon-btn {
  position: relative;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3a2a1a, #2a1f14);
  border: 2px solid var(--border-gold, #6b5b3a);
  color: var(--accent-gold, #c9a84c);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
  transition: all 0.2s ease;
}

.items-icon-btn:hover {
  border-color: var(--accent-gold, #c9a84c);
  transform: scale(1.08);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6), 0 0 12px rgba(212, 168, 67, 0.2);
}

.items-svg {
  width: 24px;
  height: 24px;
}

.items-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 20px;
  height: 20px;
  border-radius: 10px;
  background: var(--accent-gold, #c9a84c);
  color: #1a1209;
  font-size: 0.72rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  line-height: 1;
}

/* ---- Overlay ---- */
.items-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.items-viewer {
  position: relative;
  text-align: center;
  max-width: 380px;
  width: 90%;
  padding: 20px;
}

.items-close {
  position: absolute;
  top: -10px;
  right: -10px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  border: 1px solid var(--border-gold, #6b5b3a);
  color: var(--text-secondary, #a09080);
  font-size: 1.4rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s;
}

.items-close:hover {
  color: var(--accent-gold, #c9a84c);
}

.items-heading {
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 3px;
  color: var(--text-secondary, #a09080);
  margin-bottom: 16px;
}

/* ---- Parchment card ---- */
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 24px 20px;
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.parchment-card.cursed {
  border-color: rgba(192, 57, 43, 0.7);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(192, 57, 43, 0.15);
}

.parchment-card.used {
  opacity: 0.5;
}

.card-ornament {
  font-size: 2rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.5;
  margin-bottom: 8px;
}

.parchment-card.cursed .card-ornament {
  color: #c0392b;
}

.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 6px;
  line-height: 1.3;
}

.parchment-card.cursed .parchment-title {
  color: #e07060;
}

.tag-row {
  display: flex;
  gap: 6px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.cursed-tag {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #c0392b;
  border: 1px solid #c0392b;
  border-radius: 3px;
  padding: 1px 8px;
}

.type-tag {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 3px;
  padding: 1px 8px;
}

.ongoing-tag {
  color: var(--accent-gold, #c9a84c);
  border: 1px solid var(--accent-gold, #c9a84c);
}

.immediate-tag {
  color: #f0c040;
  border: 1px solid #f0c040;
}

.used-tag {
  color: var(--text-secondary, #a09080);
  border: 1px solid var(--text-secondary, #a09080);
}

/* Divider (matches CardSelectionHand) */
.parchment-divider {
  position: relative;
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
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
  color: var(--accent-gold, #c9a84c);
  padding: 0 8px;
  font-size: 0.7rem;
}

.divider-ornament.small {
  font-size: 0.5rem;
  color: var(--text-secondary, #a09080);
}

.parchment-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  flex: 1;
}

/* Stat-chip effect */
.outcome-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.stat-chip {
  padding: 3px 12px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  font-family: 'Cinzel', serif;
}

.chip-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.chip-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
}

.chip-neutral {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold, #c9a84c);
}

.card-meta {
  margin-top: 10px;
}

.meta-type {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary, #a09080);
  opacity: 0.7;
}

/* ---- Navigation ---- */
.items-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin-top: 20px;
}

.nav-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.4);
  border: 1px solid var(--border-gold, #6b5b3a);
  color: var(--accent-gold, #c9a84c);
  font-size: 1.5rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.nav-btn:hover {
  background: rgba(0, 0, 0, 0.6);
  border-color: var(--accent-gold, #c9a84c);
}

.nav-counter {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary, #a09080);
  font-size: 0.9rem;
}

/* ---- Transitions ---- */
.items-overlay-enter-active {
  transition: opacity 0.25s ease;
}
.items-overlay-leave-active {
  transition: opacity 0.2s ease;
}
.items-overlay-enter-from,
.items-overlay-leave-to {
  opacity: 0;
}

/* ---- Mobile ---- */
@media (max-width: 768px) {
  .items-icon-wrapper {
    bottom: 14px;
    right: 14px;
  }

  .items-icon-btn {
    width: 46px;
    height: 46px;
  }

  .items-svg {
    width: 20px;
    height: 20px;
  }

  .parchment-card {
    padding: 18px 16px;
    min-height: 240px;
  }

  .parchment-title {
    font-size: 1.05rem;
  }

  .parchment-desc {
    font-size: 0.82rem;
  }
}
</style>
