<template>
  <transition name="overlay-fade">
    <div v-if="visible" class="event-reveal-overlay" @click.self="dismiss">
      <div class="event-reveal-content" :class="{ 'card-entered': cardVisible }">
        <p class="event-intro">{{ introText }}</p>

        <div class="event-card" :class="{ 'card-show': cardVisible }">
          <div class="card-ornament">&#9876;</div>
          <h2 class="card-title">{{ event.title }}</h2>
          <p class="card-effect">{{ event.effect }}</p>

          <div v-if="statModifiers.length" class="card-modifiers">
            <div
              v-for="mod in statModifiers"
              :key="mod.stat"
              class="modifier"
              :class="mod.value > 0 ? 'positive' : 'negative'"
            >
              {{ mod.label }} {{ mod.value > 0 ? '+' : '' }}{{ mod.value }}
            </div>
          </div>

          <div v-if="mechanicLabel" class="card-mechanic">{{ mechanicLabel }}</div>
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
  name: 'EventReveal',
  props: {
    event: { type: Object, required: true },
  },
  emits: ['dismiss'],
  data() {
    return {
      visible: false,
      cardVisible: false,
    };
  },
  computed: {
    introText() {
      return 'A new event unfolds...';
    },
    statModifiers() {
      if (!this.event?.stat_modifiers) return [];
      return Object.entries(this.event.stat_modifiers).map(([stat, value]) => ({
        stat,
        label: stat.charAt(0).toUpperCase() + stat.slice(1),
        value,
      }));
    },
    mechanicLabel() {
      if (!this.event?.mechanic) return '';
      if (this.event.mechanic === 'reduce_dice') {
        const amount = this.event.mechanic_data?.amount || 1;
        return `Each advisor loses ${amount} die`;
      }
      if (this.event.mechanic === 'altered_deal') {
        const pos = this.event.mechanic_data?.positive_cards || 1;
        const neg = this.event.mechanic_data?.negative_cards || 1;
        return `Deal ${pos} positive, ${neg} negative cards`;
      }
      if (this.event.mechanic === 'grant_items') {
        return 'All advisors receive an item';
      }
      return '';
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
.event-reveal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.5s ease;
}

.event-reveal-content {
  text-align: center;
  max-width: 460px;
  width: 90%;
  padding: 30px 20px;
}

.event-intro {
  font-family: 'Cinzel', serif;
  font-size: 1.3rem;
  color: var(--text-secondary, #a09080);
  letter-spacing: 2px;
  margin-bottom: 30px;
  animation: fadeInUp 0.8s ease;
}

.event-card {
  background: linear-gradient(135deg, #2c1810, #4a2020);
  border: 2px solid #8b4513;
  border-radius: 12px;
  padding: 30px 24px;
  transform: scale(0.6);
  opacity: 0;
  transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.5s ease;
}

.event-card.card-show {
  transform: scale(1);
  opacity: 1;
}

.card-ornament {
  font-size: 2.5rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.6;
  margin-bottom: 12px;
}

.card-title {
  font-family: 'Cinzel', serif;
  color: #e8a040;
  font-size: 1.5rem;
  margin-bottom: 12px;
}

.card-effect {
  color: var(--text-secondary, #a09080);
  font-style: italic;
  font-size: 0.95rem;
  line-height: 1.5;
  margin-bottom: 16px;
}

.card-modifiers {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  margin-bottom: 12px;
}

.modifier {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  padding: 4px 12px;
  border-radius: 4px;
  border: 1px solid;
}

.modifier.positive {
  color: #4caf50;
  border-color: rgba(76, 175, 80, 0.4);
  background: rgba(76, 175, 80, 0.1);
}

.modifier.negative {
  color: #c0392b;
  border-color: rgba(192, 57, 43, 0.4);
  background: rgba(192, 57, 43, 0.1);
}

.card-mechanic {
  color: var(--accent-gold, #c9a84c);
  font-size: 0.85rem;
  font-weight: 600;
  padding-top: 10px;
  border-top: 1px solid rgba(139, 69, 19, 0.3);
}

.btn-continue {
  margin-top: 30px;
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

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .event-intro {
    font-size: 1rem;
    margin-bottom: 20px;
  }

  .event-card {
    padding: 20px 16px;
  }

  .card-title {
    font-size: 1.2rem;
  }

  .card-effect {
    font-size: 0.85rem;
  }

  .btn-continue {
    font-size: 0.95rem;
    padding: 10px 30px;
  }
}
</style>
