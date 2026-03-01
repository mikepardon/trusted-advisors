<template>
  <transition name="overlay-fade">
    <div v-if="visible" class="claim-overlay" @click.self="dismiss">
      <div class="claim-content">
        <p class="claim-intro">Achievement Unlocked!</p>

        <div class="claim-card" :class="{ 'card-show': cardVisible }">
          <div class="claim-icon">{{ iconEmoji }}</div>
          <h2 class="claim-name">{{ achievement.name }}</h2>
          <p class="claim-desc">{{ achievement.description }}</p>

          <div class="claim-divider"></div>

          <div class="claim-reward">+{{ result.xp_awarded }} XP</div>
          <div v-if="result.coins_awarded" class="claim-reward claim-coins">+{{ result.coins_awarded }} &#129689;</div>

          <div v-if="result.leveled_up" class="claim-levelup">
            Level Up! Lv.{{ result.new_level }}
          </div>

          <div v-if="result.next_tier" class="claim-next">
            Next: {{ result.next_tier.name }}
          </div>
        </div>

        <button class="btn-continue" :class="{ 'btn-show': cardVisible }" @click="dismiss">
          Continue
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
const iconMap = {
  trophy: '\u{1F3C6}',
  shield: '\u{1F6E1}',
  crown: '\u{1F451}',
  flame: '\u{1F525}',
  lightning: '\u{26A1}',
  star: '\u{2B50}',
  swords: '\u{2694}',
  crossed_swords: '\u{2694}',
  scroll: '\u{1F4DC}',
  arrow_up: '\u{2B06}',
  diamond: '\u{1F48E}',
  book: '\u{1F4D6}',
  wizard: '\u{1F9D9}',
  castle: '\u{1F3F0}',
  people: '\u{1F465}',
  calendar: '\u{1F4C5}',
  handshake: '\u{1F91D}',
  globe: '\u{1F30D}',
  muscle: '\u{1F4AA}',
  sparkles: '\u{2728}',
};

export default {
  name: 'AchievementClaim',
  props: {
    achievement: { type: Object, required: true },
    result: { type: Object, required: true },
  },
  emits: ['dismiss'],
  data() {
    return {
      visible: false,
      cardVisible: false,
    };
  },
  computed: {
    iconEmoji() {
      return iconMap[this.achievement.icon] || '\u{1F3C6}';
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
.claim-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  animation: fadeIn 0.5s ease;
}

.claim-content {
  text-align: center;
  max-width: 420px;
  width: 90%;
  padding: 30px 20px;
}

.claim-intro {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold, #c9a84c);
  letter-spacing: 2px;
  margin-bottom: 24px;
  animation: fadeInUp 0.8s ease;
}

.claim-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--accent-gold, #c9a84c);
  border-radius: 12px;
  padding: 28px 22px;
  transform: scale(0.6);
  opacity: 0;
  transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.5s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5), 0 0 30px rgba(201, 168, 76, 0.15);
}

.claim-card.card-show {
  transform: scale(1);
  opacity: 1;
}

.claim-icon {
  font-size: 3rem;
  margin-bottom: 8px;
}

.claim-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.4rem;
  text-align: center;
  margin-bottom: 6px;
}

.claim-desc {
  color: var(--text-secondary, #a09080);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  margin-bottom: 4px;
}

.claim-divider {
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--accent-gold, #c9a84c), transparent);
  margin: 12px 0;
}

.claim-reward {
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: #f0c040;
  background: rgba(240, 192, 64, 0.12);
  padding: 6px 20px;
  border-radius: 6px;
  margin-top: 4px;
}

.claim-coins {
  font-size: 0.95rem;
  margin-top: 6px;
}

.claim-levelup {
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
  color: #6abf50;
  margin-top: 10px;
  animation: pulse 1.5s ease infinite;
}

.claim-next {
  font-size: 0.8rem;
  color: var(--text-secondary, #a09080);
  margin-top: 10px;
  font-style: italic;
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

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

@media (max-width: 768px) {
  .claim-intro {
    font-size: 1rem;
    margin-bottom: 18px;
  }

  .claim-card {
    padding: 20px 16px;
  }

  .claim-icon {
    font-size: 2.4rem;
  }

  .claim-name {
    font-size: 1.15rem;
  }

  .btn-continue {
    font-size: 0.95rem;
    padding: 10px 30px;
  }
}
</style>
