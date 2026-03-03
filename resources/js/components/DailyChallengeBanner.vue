<template>
  <div v-if="challenge && challenge.title" class="daily-banner" :class="{ completed: challenge.completed }" @click="showDetail = !showDetail">
    <div class="banner-row">
      <div class="banner-text">
        <span class="banner-title">{{ challenge.title }}</span>
        <span v-if="challenge.completed" class="banner-status">Completed!</span>
        <span v-else class="banner-reward">+{{ challenge.reward_xp }} XP</span>
      </div>
    </div>
    <div v-if="showDetail" class="banner-detail">
      <p>{{ challenge.description }}</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'DailyChallengeBanner',
  data() {
    return {
      challenge: null,
      showDetail: false,
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
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 12px;
}

.daily-banner.completed {
  border-color: rgba(74, 138, 58, 0.4);
  background: linear-gradient(90deg, rgba(74, 138, 58, 0.08), rgba(74, 138, 58, 0.02));
}

.banner-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.banner-text {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex: 1;
}

.banner-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
}

.banner-status {
  font-size: 0.75rem;
  color: #6abf50;
  font-weight: 600;
}

.banner-reward {
  font-size: 0.75rem;
  color: var(--text-secondary);
  background: rgba(212, 168, 67, 0.15);
  padding: 1px 6px;
  border-radius: 3px;
}

.banner-detail {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(138, 106, 46, 0.15);
}

.banner-detail p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.85rem;
  line-height: 1.4;
}
</style>
