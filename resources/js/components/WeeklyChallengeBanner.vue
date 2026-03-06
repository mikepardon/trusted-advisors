<template>
  <div v-if="challenge && challenge.title && !challenge.completed" class="weekly-banner">
    <div class="banner-row">
      <div class="banner-text">
        <span class="banner-label">Weekly</span>
        <div class="banner-info">
          <span class="banner-title">{{ challenge.title }}</span>
          <p class="banner-desc">{{ challenge.description }}</p>
        </div>
      </div>
    </div>
    <div class="progress-row">
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
      <span class="progress-text">{{ challenge.progress }}/{{ challenge.target }}</span>
    </div>
    <span class="banner-reward">+{{ challenge.reward_xp }} XP, +{{ challenge.reward_coins }} &#129689;</span>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'WeeklyChallengeBanner',
  data() {
    return {
      challenge: null,
    };
  },
  computed: {
    progressPercent() {
      if (!this.challenge || !this.challenge.target) return 0;
      return Math.min(100, (this.challenge.progress / this.challenge.target) * 100);
    },
  },
  async mounted() {
    try {
      const res = await axios.get('/api/weekly-challenge');
      if (res.data) {
        this.challenge = res.data;
      }
    } catch {}
  },
};
</script>

<style scoped>
.weekly-banner {
  background: rgba(13, 10, 6, 0.65);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(100, 140, 212, 0.3);
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 10px;
}

.banner-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.banner-text {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex: 1;
}

.banner-label {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: rgba(130, 165, 230, 0.9);
  background: rgba(100, 140, 212, 0.18);
  padding: 2px 8px;
  border-radius: 3px;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 2px;
  font-family: 'Cinzel', serif;
}

.banner-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.banner-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  font-weight: 700;
}

.banner-desc {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.8rem;
  line-height: 1.4;
}

.progress-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
}

.progress-bar-bg {
  flex: 1;
  height: 6px;
  background: rgba(100, 140, 212, 0.12);
  border-radius: 3px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, rgba(100, 140, 212, 0.7), rgba(212, 168, 67, 0.7));
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 0.7rem;
  color: var(--text-secondary);
  white-space: nowrap;
  font-family: 'Cinzel', serif;
}

.banner-reward {
  display: flex;
  justify-content: center;
  font-size: 0.75rem;
  color: var(--accent-gold);
  background: rgba(100, 140, 212, 0.1);
  padding: 2px 8px;
  border-radius: 3px;
  white-space: nowrap;
  margin-top: 8px;
  font-family: 'Cinzel', serif;
  letter-spacing: 0.5px;
}
</style>
