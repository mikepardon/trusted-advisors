<template>
  <div class="advisor-mgmt">
    <div v-if="loading" class="loading-text">Loading advisors...</div>
    <div v-else-if="!advisors.length" class="loading-text">No advisors unlocked yet.</div>
    <div v-else class="advisor-grid">
      <div
        v-for="a in advisors"
        :key="a.id"
        class="advisor-card"
        :class="{ 'has-pending': a.pending_upgrades > 0, 'can-immortalise': a.can_immortalise }"
        @click="$emit('select', a)"
      >
        <div class="advisor-img-wrap">
          <img :src="a.character.image_url || '/images/character.png'" :alt="a.display_name" class="advisor-img" />
          <span class="advisor-level-badge">{{ a.level }}</span>
          <span v-if="a.pending_upgrades > 0" class="advisor-pending-badge">!</span>
        </div>
        <span class="advisor-name">{{ a.display_name }}</span>
        <div class="advisor-xp-bar-wrap">
          <div class="advisor-xp-bar" :style="{ width: xpPercent(a) + '%' }"></div>
        </div>
        <span class="advisor-xp-text" v-if="a.level < (a.max_level || 8)">{{ a.xp - a.xp_for_current_level }} / {{ a.xp_for_next_level - a.xp_for_current_level }} XP</span>
        <span class="advisor-xp-text advisor-xp-max" v-else>MAX</span>
        <button v-if="a.pending_upgrades > 0" class="advisor-levelup-btn" @click.stop="$emit('level-up', a)">Level Up!</button>
        <span v-else-if="a.can_immortalise" class="advisor-immortalise-tag">Immortalise</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdvisorManagement',
  props: {
    advisors: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
  },
  emits: ['select', 'level-up'],
  methods: {
    xpPercent(a) {
      if (a.level >= (a.max_level || 8)) return 100;
      if (!a.xp_for_next_level) return 0;
      const range = a.xp_for_next_level - a.xp_for_current_level;
      if (range <= 0) return 100;
      return Math.min(100, ((a.xp - a.xp_for_current_level) / range) * 100);
    },
  },
};
</script>

<style scoped>
.advisor-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 12px;
}

.advisor-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 12px 8px 10px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.advisor-card:hover {
  border-color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.advisor-card.has-pending {
  border-color: #5ab87a;
  box-shadow: 0 0 8px rgba(90, 184, 122, 0.25);
}

.advisor-card.can-immortalise {
  border-color: #b06adf;
  box-shadow: 0 0 8px rgba(176, 106, 223, 0.25);
}

.advisor-img-wrap {
  position: relative;
  width: 64px;
  height: 64px;
}

.advisor-img {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-gold);
}

.advisor-level-badge {
  position: absolute;
  bottom: -4px;
  right: -4px;
  background: linear-gradient(135deg, var(--accent-gold), #b08830);
  color: #1a0f05;
  font-size: 0.7rem;
  font-weight: 800;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--bg-primary);
  font-family: 'Cinzel', serif;
}

.advisor-pending-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #5ab87a;
  color: white;
  font-size: 0.65rem;
  font-weight: 800;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--bg-primary);
  animation: pulse-badge 1.5s ease-in-out infinite;
}

@keyframes pulse-badge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.2); }
}

.advisor-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.75rem;
  text-align: center;
  font-weight: 700;
  line-height: 1.2;
}

.advisor-xp-bar-wrap {
  width: 80%;
  height: 4px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 2px;
  overflow: hidden;
}

.advisor-xp-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--accent-gold), #f0d060);
  border-radius: 2px;
  transition: width 0.3s;
}

.advisor-xp-text {
  font-size: 0.6rem;
  color: var(--text-secondary);
}

.advisor-xp-max {
  color: var(--accent-gold);
  font-weight: 700;
}

.advisor-levelup-btn {
  font-size: 0.6rem;
  color: white;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: linear-gradient(180deg, #2a6e3a, #1a4a26);
  border: 1.5px solid #5ab87a;
  border-radius: 4px;
  padding: 3px 10px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: box-shadow 0.15s;
}

.advisor-levelup-btn:hover {
  box-shadow: 0 0 8px rgba(90, 184, 122, 0.4);
}

.advisor-immortalise-tag {
  font-size: 0.6rem;
  color: #b06adf;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.loading-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 40px;
}

@media (max-width: 768px) {
  .advisor-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }

  .advisor-img-wrap {
    width: 56px;
    height: 56px;
  }

  .advisor-img {
    width: 56px;
    height: 56px;
  }

  .advisor-name {
    font-size: 0.68rem;
  }
}
</style>
