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
  background: linear-gradient(90deg, rgba(212, 168, 67, 0.08), rgba(212, 168, 67, 0.02));
  border: 1px solid rgba(212, 168, 67, 0.25);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 12px;
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
  letter-spacing: 1px;
  color: rgba(212, 168, 67, 0.8);
  background: rgba(212, 168, 67, 0.12);
  padding: 1px 6px;
  border-radius: 3px;
  font-weight: 600;
  flex-shrink: 0;
  margin-top: 2px;
}

.banner-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.banner-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
}

.banner-desc {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.8rem;
  line-height: 1.3;
}

.banner-reward {
  display: flex;
  justify-content: center;
  font-size: 0.75rem;
  color: var(--text-secondary);
  background: rgba(212, 168, 67, 0.15);
  padding: 1px 6px;
  border-radius: 3px;
  margin-top: 6px;
}
</style>
