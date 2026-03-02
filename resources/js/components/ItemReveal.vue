<template>
  <transition name="overlay-fade">
    <div v-if="visible" class="item-reveal-overlay" @click.self="dismiss">
      <div class="item-reveal-content" :class="{ 'card-entered': cardVisible }">
        <p class="item-intro">{{ introText }}</p>

        <div class="item-card" :class="{ 'card-show': cardVisible, cursed: item.is_cursed || item.is_negative, blocked: isBlocked }">
          <div class="card-ornament">{{ isBlocked ? '&#128683;' : '&#9876;' }}</div>
          <p class="card-recipient">{{ item.player }}</p>
          <h2 class="card-title">{{ item.item }}</h2>
          <span v-if="item.is_cursed && !isBlocked" class="cursed-tag">Cursed</span>
          <span v-if="isBlocked" class="cursed-tag">Lost</span>
          <div class="card-divider"></div>
          <p v-if="isBlocked" class="card-desc">Your inventory is full of cursed items. Remove curses to receive new items.</p>
          <p v-else-if="item.description" class="card-desc">{{ item.description }}</p>
          <div class="card-effect" :class="isBlocked || item.is_cursed || item.is_negative ? 'effect-negative' : 'effect-positive'">
            {{ isBlocked ? 'Item could not be received!' : (item.is_cursed ? 'A dark burden befalls...' : 'A boon has been found!') }}
          </div>
          <div v-if="item.is_consumable && !isBlocked" class="card-immediate">Takes effect immediately!</div>
          <div v-if="item.immediate_description && !isBlocked" class="card-immediate-detail">{{ item.immediate_description }}</div>
        </div>

        <button class="btn-continue" :class="{ 'btn-show': cardVisible }" @click="dismiss">
          Continue
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'ItemReveal',
  props: {
    item: { type: Object, required: true },
  },
  emits: ['dismiss'],
  data() {
    return {
      visible: false,
      cardVisible: false,
    };
  },
  computed: {
    isBlocked() {
      return this.item.type === 'item_blocked';
    },
    introText() {
      if (this.isBlocked) return 'Item Lost!';
      if (this.item.is_cursed) return 'A cursed relic emerges...';
      return 'An item has been discovered!';
    },
  },
  mounted() {
    this.visible = true;
    setTimeout(() => {
      this.cardVisible = true;
    }, 600);
  },
  methods: {
    dismiss() {
      this.visible = false;
      setTimeout(() => {
        this.$emit('dismiss');
      }, 300);
    },
  },
};
</script>

<style scoped>
.item-reveal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  animation: fadeIn 0.5s ease;
}

.item-reveal-content {
  text-align: center;
  max-width: 420px;
  width: 90%;
  padding: 30px 20px;
}

.item-intro {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--text-secondary, #a09080);
  letter-spacing: 2px;
  margin-bottom: 24px;
  animation: fadeInUp 0.8s ease;
}

.item-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 28px 22px;
  transform: scale(0.6);
  opacity: 0;
  transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.5s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.item-card.card-show {
  transform: scale(1);
  opacity: 1;
}

.item-card.cursed {
  border-color: rgba(192, 57, 43, 0.7);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5), 0 0 20px rgba(192, 57, 43, 0.15);
}

.item-card.blocked {
  border-color: rgba(192, 57, 43, 0.7);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5), 0 0 20px rgba(192, 57, 43, 0.15);
  opacity: 0.85;
}

.card-ornament {
  font-size: 2.2rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.5;
  margin-bottom: 6px;
}

.item-card.cursed .card-ornament {
  color: #c0392b;
}

.card-recipient {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  color: var(--text-secondary, #a09080);
  letter-spacing: 1px;
  margin-bottom: 6px;
}

.card-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.4rem;
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
  margin-bottom: 8px;
}

.card-effect {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 6px 16px;
  border-radius: 4px;
  margin-top: 4px;
}

.card-effect.effect-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.card-effect.effect-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
}

.card-immediate {
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: #f0c040;
  margin-top: 8px;
}

.card-immediate-detail {
  font-size: 0.82rem;
  color: var(--accent-green, #4caf50);
  font-style: italic;
  margin-top: 4px;
}

.btn-continue {
  margin-top: 28px;
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  padding: 12px 40px;
  background: linear-gradient(135deg, #8b4513, #6b3410);
  color: var(--accent-gold, #c9a84c);
  border: 1px solid var(--accent-gold, #c9a84c);
  border-radius: 8px;
  cursor: pointer;
  opacity: 0;
  transform: translateY(10px);
  transition: opacity 0.4s ease 0.3s, transform 0.4s ease 0.3s, background 0.2s;
}

.btn-continue.btn-show {
  opacity: 1;
  transform: translateY(0);
}

.btn-continue:hover {
  background: linear-gradient(135deg, #a05a20, #8b4513);
}

.overlay-fade-leave-active {
  transition: opacity 0.3s ease;
}

.overlay-fade-leave-to {
  opacity: 0;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(15px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .item-intro {
    font-size: 1rem;
    margin-bottom: 18px;
  }

  .item-card {
    padding: 20px 16px;
  }

  .card-title {
    font-size: 1.15rem;
  }

  .card-desc {
    font-size: 0.82rem;
  }

  .btn-continue {
    font-size: 0.95rem;
    padding: 10px 30px;
  }
}
</style>
