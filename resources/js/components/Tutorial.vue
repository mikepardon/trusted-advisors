<template>
  <div class="tutorial-overlay">
    <div class="tutorial-modal">
      <button class="tutorial-skip" @click="$emit('close')">Skip</button>

      <div class="tutorial-content">
        <div
          v-for="(line, i) in currentLines"
          :key="step + '-' + i"
          class="tutorial-line"
          :class="{ visible: visibleLines > i }"
          :style="{ transitionDelay: (i * 0.15) + 's' }"
        >
          {{ line }}
        </div>
      </div>

      <!-- Step dots -->
      <div class="step-dots">
        <span
          v-for="s in totalSteps"
          :key="s"
          class="dot"
          :class="{ active: s === step + 1 }"
        ></span>
      </div>

      <!-- Navigation -->
      <div class="tutorial-nav">
        <button v-if="step > 0" class="nav-btn" @click="prevStep">Back</button>
        <span v-else></span>
        <button v-if="step < totalSteps - 1" class="nav-btn btn-primary" @click="nextStep">Next</button>
        <button v-else class="nav-btn btn-primary" @click="$emit('close')">Begin!</button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Tutorial',
  emits: ['close'],
  data() {
    return {
      step: 0,
      visibleLines: 0,
      timer: null,
    };
  },
  computed: {
    totalSteps() {
      return this.steps.length;
    },
    steps() {
      return [
        [
          'Welcome, Advisor.',
          '',
          'The King has summoned you to guide',
          'the kingdom through troubled times.',
          '',
          'This tutorial will teach you how to play.',
        ],
        [
          'The Kingdom',
          '',
          'Your kingdom has 6 stats:',
          'Wealth, Influence, Security,',
          'Religion, Food, and Happiness.',
          '',
          'Each ranges from 0 to 20.',
          'If ANY stat hits 0, the kingdom falls!',
          'Survive all rounds to win.',
        ],
        [
          'Choosing Cards',
          '',
          'Each round, advisors draw cards.',
          'Cards have positive and negative effects.',
          '',
          'Positive effects boost stats on success.',
          'Negative effects ALWAYS apply.',
          '',
          'Choose wisely which crises to address!',
        ],
        [
          'Rolling Dice',
          '',
          'Each card has a difficulty (2-6).',
          'Roll your dice to meet or beat it.',
          '',
          'Wild dice can be assigned to any card.',
          'In multiplayer, dice are pooled together.',
          '',
          'Success triggers positive effects.',
          'Failure means only the negatives apply.',
        ],
        [
          'Items & Events',
          '',
          'Items grant passive bonuses like',
          '+1 to rolls or -1 difficulty.',
          'You can hold up to 2 at a time.',
          '',
          'Cursed items cannot be discarded.',
          'Use "Remove Curse" to cleanse them.',
          '',
          'Events trigger every few months,',
          'bringing fortune or disaster.',
        ],
        [
          'Play Modes',
          '',
          'Solo: One advisor, one kingdom.',
          'Local: Pass the device between friends.',
          'Online: Play with friends in real-time.',
          '',
          'In Duel mode, two rival kingdoms',
          'compete head-to-head. Pick a card',
          'to keep, send the other to your rival.',
          'Roll against both cards at once!',
          'Reach 20 in 3 stats to win!',
        ],
        [
          'Progression',
          '',
          'Earn XP from every game you play.',
          'Level up to unlock characters and items.',
          '',
          'Complete achievements for bonus rewards.',
          'Check daily challenges for extra XP.',
          'Climb the leaderboard to prove your worth!',
          '',
          'Good luck, Advisor. The kingdom awaits!',
        ],
      ];
    },
    currentLines() {
      return this.steps[this.step];
    },
  },
  watch: {
    step() {
      this.startLineAnimation();
    },
  },
  mounted() {
    this.startLineAnimation();
  },
  beforeUnmount() {
    if (this.timer) clearInterval(this.timer);
  },
  methods: {
    startLineAnimation() {
      if (this.timer) clearInterval(this.timer);
      this.visibleLines = 0;
      const total = this.currentLines.length;
      this.timer = setInterval(() => {
        this.visibleLines++;
        if (this.visibleLines >= total) {
          clearInterval(this.timer);
          this.timer = null;
        }
      }, 150);
    },
    nextStep() {
      if (this.step < this.totalSteps - 1) this.step++;
    },
    prevStep() {
      if (this.step > 0) this.step--;
    },
  },
};
</script>

<style scoped>
.tutorial-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
}

.tutorial-modal {
  background: linear-gradient(180deg, #1a1209, #0d0a06);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 30px 28px 24px;
  max-width: 480px;
  width: 100%;
  position: relative;
  box-shadow: 0 8px 40px rgba(212, 168, 67, 0.2);
}

.tutorial-skip {
  position: absolute;
  top: 10px;
  right: 14px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  cursor: pointer;
  padding: 4px 8px;
}

.tutorial-skip:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.tutorial-content {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.tutorial-line {
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1.05rem;
  color: var(--text-primary);
  line-height: 1.6;
  text-align: center;
  opacity: 0;
  transform: translateY(8px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}

.tutorial-line.visible {
  opacity: 1;
  transform: translateY(0);
}

.tutorial-line:first-child {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 4px;
}

/* Step dots */
.step-dots {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin: 20px 0 16px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(138, 106, 46, 0.3);
  transition: background 0.3s ease;
}

.dot.active {
  background: var(--accent-gold);
}

/* Navigation */
.tutorial-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-btn {
  padding: 8px 24px;
  font-size: 0.9rem;
}

@media (max-width: 768px) {
  .tutorial-modal {
    padding: 24px 18px 18px;
  }

  .tutorial-content {
    min-height: 200px;
  }

  .tutorial-line {
    font-size: 0.95rem;
  }

  .tutorial-line:first-child {
    font-size: 1.15rem;
  }
}
</style>
