<template>
  <div v-if="challenge && challenge.title && !challenge.completed" class="daily-banner">
    <div class="banner-row">
      <div class="banner-text">
        <span class="banner-label">Daily</span>
        <div class="banner-info">
          <span class="banner-title">{{ challenge.title }}</span>
          <p class="banner-desc">{{ challenge.description }}</p>
        </div>
      </div>
    </div>
    <span class="banner-reward">+{{ challenge.reward_xp }} XP</span>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'DailyChallengeBanner',
  data() {
    return {
      challenge: null,
    };
  },
  async mounted() {
    try {
      const res = await axios.get('/api/daily-challenge');
      if (res.data) {
        this.challenge = res.data;
      }
    } catch {}
  },
};
</script>

<style scoped>
.daily-banner {
  background: rgba(13, 10, 6, 0.65);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(212, 168, 67, 0.3);
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
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.18);
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

.banner-reward {
  display: flex;
  justify-content: center;
  font-size: 0.75rem;
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.1);
  padding: 2px 8px;
  border-radius: 3px;
  margin-top: 8px;
  font-family: 'Cinzel', serif;
  letter-spacing: 0.5px;
}
</style>
