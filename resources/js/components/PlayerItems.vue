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

          <!-- Card display -->
          <div class="item-card" :class="{ cursed: currentItem.is_cursed || currentItem.item?.is_negative, used: currentItem.is_used }">
            <div class="card-ornament">&#9876;</div>
            <h3 class="card-title">{{ currentItem.item?.name || 'Unknown Item' }}</h3>
            <span v-if="currentItem.is_cursed" class="cursed-tag">Cursed</span>
            <span v-if="currentItem.item?.is_consumable" class="type-tag immediate-tag">Immediate</span>
            <span v-else-if="currentItem.item?.effect_type" class="type-tag ongoing-tag">Ongoing</span>
            <span v-if="currentItem.is_used" class="type-tag used-tag">Used</span>
            <div class="card-divider"></div>
            <p class="card-desc">{{ currentItem.item?.description || '' }}</p>
            <div class="card-divider"></div>
            <div class="card-effect" :class="effectClass(currentItem)">
              {{ effectSummary(currentItem.item) }}
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
        default:
          return item.description || 'Passive effect';
      }
    },
    effectClass(pi) {
      const type = pi.item?.effect?.bonus_type || '';
      if (type === 'roll_bonus' || type === 'difficulty_reduction') return 'effect-positive';
      if (type === 'roll_penalty' || type === 'difficulty_increase') return 'effect-negative';
      return '';
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

/* ---- Item card ---- */
.item-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 24px 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(212, 168, 67, 0.08);
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.item-card.cursed {
  border-color: rgba(192, 57, 43, 0.7);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(192, 57, 43, 0.15);
}

.card-ornament {
  font-size: 2rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.5;
  margin-bottom: 8px;
}

.item-card.cursed .card-ornament {
  color: #c0392b;
}

.card-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 6px;
}

.item-card.cursed .card-title {
  color: #e07060;
}

.cursed-tag {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #c0392b;
  border: 1px solid #c0392b;
  border-radius: 3px;
  padding: 1px 8px;
  margin-bottom: 4px;
}

.item-card.used {
  opacity: 0.5;
}

.type-tag {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 3px;
  padding: 1px 8px;
  margin-bottom: 4px;
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

.card-divider {
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
  margin: 12px 0;
}

.card-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  flex: 1;
}

.card-effect {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 4px 14px;
  border-radius: 4px;
}

.card-effect.effect-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.card-effect.effect-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
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

  .item-card {
    padding: 18px 16px;
    min-height: 240px;
  }

  .card-title {
    font-size: 1.05rem;
  }

  .card-desc {
    font-size: 0.82rem;
  }
}
</style>
