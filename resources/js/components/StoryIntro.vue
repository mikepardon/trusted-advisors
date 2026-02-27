<template>
  <div class="story-screen" @click="handleClick">
    <div class="story-vignette">
      <img src="/images/cover-art.png" alt="" class="story-bg" />
      <div class="story-overlay"></div>

      <div class="story-text-wrap">
        <div
          v-for="(line, i) in lines"
          :key="i"
          class="story-line"
          :class="{ visible: visibleLines > i }"
          :style="{ transitionDelay: (i * 0.15) + 's' }"
        >
          {{ line }}
        </div>
      </div>

      <p v-if="allVisible" class="click-continue">Click anywhere to continue</p>
    </div>
  </div>
</template>

<script>
import { playSound } from '../sounds';

export default {
  name: 'StoryIntro',
  props: {
    numPlayers: { type: Number, default: 2 },
  },
  emits: ['continue'],
  data() {
    return {
      visibleLines: 0,
      timer: null,
    };
  },
  computed: {
    councilWord() {
      if (this.numPlayers === 1) return 'most trusted advisor';
      return this.numPlayers + ' most trusted advisors';
    },
    lines() {
      return [
        'England, 1280 AD.',
        'The kingdom teeters on the brink of chaos.',
        'Bandits roam the roads. Rival lords plot rebellion.',
        'The people grow restless. Famine threatens the land.',
        `The King has summoned his ${this.councilWord}`,
        '\u2014 you \u2014',
        'to guide the kingdom through these troubled times.',
        'For the next two years, the fate of the realm rests in your hands.',
        'Each month brings new crises and impossible choices.',
        'You cannot fix everything. Choose which fires to fight,',
        'and which to let burn.',
        'Keep the kingdom standing. That is all that matters.',
      ];
    },
    allVisible() {
      return this.visibleLines >= this.lines.length;
    },
  },
  mounted() {
    this.timer = setInterval(() => {
      if (this.visibleLines < this.lines.length) {
        this.visibleLines++;
      } else {
        clearInterval(this.timer);
      }
    }, 800);
  },
  beforeUnmount() {
    clearInterval(this.timer);
  },
  methods: {
    handleClick() {
      if (!this.allVisible) {
        clearInterval(this.timer);
        this.visibleLines = this.lines.length;
      } else {
        playSound('clickButton');
        this.$emit('continue');
      }
    },
  },
};
</script>

<style scoped>
.story-screen {
  max-width: 800px;
  margin: 0 auto;
  height: 100%;
  cursor: pointer;
}

.story-vignette {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(138, 106, 46, 0.3);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.7);
}

.story-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.25;
  filter: blur(2px);
}

.story-overlay {
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at center, rgba(13, 10, 6, 0.3) 0%, rgba(13, 10, 6, 0.85) 100%);
}

.story-text-wrap {
  position: relative;
  z-index: 1;
  text-align: center;
  padding: 20px 16px 10px;
  max-width: 600px;
}

.story-line {
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1rem;
  line-height: 1.25;
  color: var(--text-bright);
  opacity: 0;
  transform: translateY(10px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}

.story-line.visible {
  opacity: 1;
  transform: translateY(0);
}

.story-line:nth-child(1) {
  font-family: 'Cinzel', serif;
  font-size: 1.3rem;
  color: var(--accent-gold);
  margin-bottom: 8px;
}

.story-line:nth-child(6) {
  font-family: 'Cinzel', serif;
  font-size: 1.15rem;
  color: var(--accent-gold);
  font-weight: 700;
  margin: 3px 0;
}

.story-line:last-child {
  font-style: italic;
  color: var(--accent-gold);
  margin-top: 8px;
}

.click-continue {
  position: relative;
  z-index: 1;
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
  margin-top: 16px;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .story-text-wrap {
    padding: 12px 12px 8px;
  }

  .story-line {
    font-size: 0.88rem;
    line-height: 1.2;
  }

  .story-line:nth-child(1) {
    font-size: 1.1rem;
    margin-bottom: 6px;
  }

  .story-line:nth-child(6) {
    font-size: 1rem;
  }

  .click-continue {
    font-size: 0.78rem;
    margin-top: 10px;
  }
}
</style>
