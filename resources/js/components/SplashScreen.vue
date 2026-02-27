<template>
  <div class="splash-overlay" :class="{ 'splash-fade-out': fadingOut }">
    <div class="splash-content">
      <img src="/images/banner.png" alt="Trusted Advisors" class="splash-logo" />
      <div class="splash-progress-bg">
        <div class="splash-progress-bar" :style="{ width: progress + '%' }"></div>
      </div>
      <p class="splash-blurb">{{ currentBlurb }}</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SplashScreen',
  emits: ['done'],
  data() {
    return {
      progress: 0,
      currentBlurb: '',
      fadingOut: false,
      blurbs: [
        'Restocking the granary...',
        'Sharpening the swords...',
        'Consulting the oracle...',
        'Rallying the troops...',
        'Forging alliances...',
        'Scouting the borders...',
        'Training the squires...',
        'Brewing the mead...',
        'Polishing the crown...',
        'Reading the scrolls...',
        'Feeding the horses...',
        'Raising the banners...',
        'Lighting the torches...',
        'Summoning the council...',
      ],
    };
  },
  mounted() {
    this.currentBlurb = this.randomBlurb();

    const duration = 3000;
    const interval = 30;
    const step = (100 / duration) * interval;
    this._progressTimer = setInterval(() => {
      this.progress = Math.min(this.progress + step, 100);
      if (this.progress >= 100) {
        clearInterval(this._progressTimer);
        this.fadeOut();
      }
    }, interval);

    this._blurbTimer = setInterval(() => {
      this.currentBlurb = this.randomBlurb();
    }, 600);
  },
  beforeUnmount() {
    clearInterval(this._progressTimer);
    clearInterval(this._blurbTimer);
  },
  methods: {
    randomBlurb() {
      return this.blurbs[Math.floor(Math.random() * this.blurbs.length)];
    },
    fadeOut() {
      clearInterval(this._blurbTimer);
      this.fadingOut = true;
      setTimeout(() => {
        this.$emit('done');
      }, 500);
    },
  },
};
</script>

<style scoped>
.splash-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: var(--bg-primary, #0d0a06);
  background-image:
    radial-gradient(ellipse at 50% 40%, rgba(212, 168, 67, 0.06) 0%, transparent 60%);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.5s ease;
}

.splash-fade-out {
  opacity: 0;
  pointer-events: none;
}

.splash-content {
  text-align: center;
  padding: 20px;
  max-width: 400px;
  width: 100%;
}

.splash-logo {
  max-width: 400px;
  width: 100%;
  height: auto;
  margin-bottom: 40px;
  filter: drop-shadow(0 4px 16px rgba(212, 168, 67, 0.4));
}

.splash-progress-bg {
  width: 100%;
  height: 6px;
  background: rgba(138, 106, 46, 0.2);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 20px;
}

.splash-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--accent-gold, #d4a843), var(--accent-gold-bright, #e8c468));
  border-radius: 3px;
  transition: width 0.03s linear;
}

.splash-blurb {
  color: var(--text-secondary, #a08a6a);
  font-family: 'Crimson Text', Georgia, serif;
  font-style: italic;
  font-size: 1rem;
  min-height: 1.5em;
}
</style>
