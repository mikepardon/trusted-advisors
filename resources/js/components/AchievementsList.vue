<template>
  <div class="achievements-page">
    <h2 class="section-title">Achievements</h2>

    <div v-if="loading" class="ach-loading">Loading...</div>

    <div v-else class="ach-grid">
      <div
        v-for="ach in achievements"
        :key="ach.id"
        class="ach-card"
        :class="{
          earned: ach.earned && ach.claimed,
          unclaimed: ach.earned && !ach.claimed,
          locked: !ach.earned,
        }"
      >
        <div class="ach-icon">{{ iconMap[ach.icon] || '?' }}</div>
        <div class="ach-info">
          <div class="ach-header">
            <h3 class="ach-name">{{ ach.name }}</h3>
            <div v-if="ach.earned && ach.claimed" class="ach-badge claimed-badge">&#10003;</div>
            <button
              v-else-if="ach.earned && !ach.claimed"
              class="ach-claim-btn"
              :disabled="claiming === ach.id"
              @click="claim(ach)"
            >
              {{ claiming === ach.id ? '...' : 'Claim' }}
            </button>
            <div v-else-if="ach.reward_xp || ach.reward_coins" class="ach-xp-preview">
              <span v-if="ach.reward_xp">+{{ ach.reward_xp }} XP</span>
              <span v-if="ach.reward_coins"> +{{ ach.reward_coins }} &#129689;</span>
            </div>
          </div>
          <p class="ach-desc">{{ ach.description }}</p>
          <div v-if="ach.tier_group && ach.tier > 1" class="ach-tier-label">Tier {{ ach.tier }}</div>
          <!-- Progress bar for unearned trackable achievements -->
          <div v-if="!ach.earned && ach.progress" class="ach-progress">
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: progressPercent(ach.progress) + '%' }"></div>
            </div>
            <span class="progress-text">{{ ach.progress.current }} / {{ ach.progress.target }}</span>
          </div>
        </div>
      </div>
    </div>

    <AchievementClaim
      v-if="claimOverlay"
      :achievement="claimOverlay.achievement"
      :result="claimOverlay.result"
      @dismiss="onClaimDismiss"
    />
  </div>
</template>

<script>
import axios from 'axios';
import AchievementClaim from './AchievementClaim.vue';
import { useAuth } from '../stores/auth';

export default {
  name: 'AchievementsList',
  components: { AchievementClaim },
  data() {
    return {
      achievements: [],
      loading: true,
      claiming: null,
      claimOverlay: null,
      iconMap: {
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
      },
    };
  },
  async mounted() {
    await this.fetchAchievements();
  },
  methods: {
    async fetchAchievements() {
      this.loading = true;
      try {
        const res = await axios.get('/api/achievements');
        this.achievements = res.data;
      } catch {}
      this.loading = false;
    },
    progressPercent(progress) {
      if (!progress || !progress.target) return 0;
      return Math.min(100, Math.round((progress.current / progress.target) * 100));
    },
    async claim(ach) {
      this.claiming = ach.id;
      try {
        const res = await axios.post(`/api/achievements/${ach.id}/claim`);
        const result = res.data;

        // Update auth store
        const { updateUserStats } = useAuth();
        updateUserStats({ xp: result.new_xp, level: result.new_level, coins: result.new_coins });

        // Show overlay
        this.claimOverlay = { achievement: ach, result };

        // Mark as claimed locally
        ach.claimed = true;
      } catch {}
      this.claiming = null;
    },
    onClaimDismiss() {
      this.claimOverlay = null;
    },
  },
};
</script>

<style scoped>
.achievements-page {
  max-width: 700px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
  text-align: center;
}

.ach-loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.ach-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ach-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  transition: all 0.2s;
}

.ach-card.earned {
  border-color: rgba(74, 138, 58, 0.4);
  background: rgba(74, 138, 58, 0.06);
}

.ach-card.unclaimed {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.ach-card.locked {
  opacity: 0.6;
}

.ach-icon {
  font-size: 1.8rem;
  width: 40px;
  text-align: center;
  flex-shrink: 0;
  padding-top: 2px;
}

.ach-info {
  flex: 1;
  min-width: 0;
}

.ach-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ach-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  margin: 0;
}

.ach-desc {
  color: var(--text-secondary);
  font-size: 0.8rem;
  margin: 2px 0 0;
}

.ach-tier-label {
  font-size: 0.65rem;
  color: var(--text-secondary);
  opacity: 0.7;
  margin-top: 2px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.claimed-badge {
  font-size: 0.85rem;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgba(74, 138, 58, 0.25);
  color: #6abf50;
  font-weight: 700;
  flex-shrink: 0;
}

.ach-claim-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  padding: 3px 12px;
  border-radius: 4px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
  animation: claimPulse 2s ease-in-out infinite;
  transition: background 0.2s;
}

.ach-claim-btn:hover {
  background: rgba(212, 168, 67, 0.3);
}

.ach-claim-btn:disabled {
  opacity: 0.5;
  animation: none;
  cursor: default;
}

.ach-xp-preview {
  font-size: 0.65rem;
  color: var(--text-secondary);
  opacity: 0.6;
  white-space: nowrap;
  flex-shrink: 0;
}

@keyframes claimPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(212, 168, 67, 0.4); }
  50% { box-shadow: 0 0 8px 2px rgba(212, 168, 67, 0.3); }
}

/* Progress bar */
.ach-progress {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.progress-track {
  flex: 1;
  height: 6px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 3px;
  overflow: hidden;
  border: 1px solid rgba(138, 106, 46, 0.15);
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 3px;
  transition: width 0.4s ease;
  min-width: 0;
}

.progress-text {
  font-size: 0.7rem;
  color: var(--text-secondary);
  white-space: nowrap;
  flex-shrink: 0;
  min-width: 40px;
  text-align: right;
}

@media (max-width: 768px) {
  .ach-icon {
    font-size: 1.4rem;
    width: 32px;
  }
}
</style>
