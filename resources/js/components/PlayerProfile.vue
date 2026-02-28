<template>
  <div class="profile-overlay" @click.self="$emit('close')">
    <div class="profile-modal">
      <button class="close-btn" @click="$emit('close')">&times;</button>

      <div v-if="loading" class="modal-loading">Loading profile...</div>
      <div v-else-if="error" class="modal-error">{{ error }}</div>
      <template v-else>
        <div class="modal-header">
          <div class="modal-avatar">&#9876;</div>
          <div>
            <h2 class="modal-name">{{ profile.name }}</h2>
            <p class="modal-level">Level {{ profile.level }} Advisor</p>
          </div>
        </div>

        <!-- XP Bar -->
        <div class="modal-xp">
          <div class="xp-header">
            <span class="xp-badge">Lv. {{ profile.level }}</span>
            <span class="xp-text">{{ profile.xp }} / {{ profile.xp_for_next_level }} XP</span>
          </div>
          <div class="xp-track">
            <div class="xp-fill" :style="{ width: xpPercent + '%' }"></div>
          </div>
        </div>

        <!-- Stats row -->
        <div class="modal-stats-row">
          <div class="modal-stat">
            <span class="modal-stat-num">{{ profile.elo_rating }}</span>
            <span class="modal-stat-label">ELO</span>
          </div>
          <div class="modal-stat" v-if="profile.login_streak">
            <span class="modal-stat-num">&#128293; {{ profile.login_streak }}</span>
            <span class="modal-stat-label">Streak</span>
          </div>
        </div>

        <!-- Game stats -->
        <div class="modal-game-stats">
          <div class="mini-stat">
            <span class="mini-num">{{ profile.total_games }}</span>
            <span class="mini-label">Games</span>
          </div>
          <div class="mini-stat mini-win">
            <span class="mini-num">{{ profile.total_wins }}</span>
            <span class="mini-label">Wins</span>
          </div>
          <div class="mini-stat mini-loss">
            <span class="mini-num">{{ profile.total_losses }}</span>
            <span class="mini-label">Losses</span>
          </div>
          <div class="mini-stat">
            <span class="mini-num">{{ profile.duel_wins }}</span>
            <span class="mini-label">Duel Wins</span>
          </div>
        </div>

        <!-- Games together -->
        <div v-if="profile.games_together != null" class="games-together">
          Games together: <strong>{{ profile.games_together }}</strong>
        </div>

        <!-- Recent achievements -->
        <div v-if="profile.recent_achievements && profile.recent_achievements.length" class="modal-achievements">
          <h3 class="modal-section-title">Recent Achievements</h3>
          <div v-for="ach in profile.recent_achievements" :key="ach.id" class="ach-item">
            <span class="ach-icon">{{ ach.icon || '&#127942;' }}</span>
            <div>
              <span class="ach-name">{{ ach.name }}</span>
              <span class="ach-desc">{{ ach.description }}</span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'PlayerProfile',
  props: {
    userId: { type: [Number, String], required: true },
  },
  emits: ['close'],
  data() {
    return {
      profile: null,
      loading: true,
      error: null,
    };
  },
  computed: {
    xpPercent() {
      if (!this.profile) return 0;
      const level = this.profile.level || 1;
      const xp = this.profile.xp || 0;
      const currentLevelXp = (100 * level * (level + 1)) / 2;
      const prevLevelXp = (100 * (level - 1) * level) / 2;
      const progress = xp - prevLevelXp;
      const range = currentLevelXp - prevLevelXp;
      if (range <= 0) return 0;
      return Math.min(100, Math.round((progress / range) * 100));
    },
  },
  async mounted() {
    try {
      const res = await axios.get(`/api/users/${this.userId}/profile`);
      this.profile = res.data;
    } catch {
      this.error = 'Could not load profile.';
    }
    this.loading = false;
  },
};
</script>

<style scoped>
.profile-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.profile-modal {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 420px;
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
}

.close-btn {
  position: absolute;
  top: 10px;
  right: 14px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.8rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.close-btn:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.modal-loading, .modal-error {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.modal-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
}

.modal-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgba(212, 168, 67, 0.15);
  border: 2px solid var(--accent-gold);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  flex-shrink: 0;
}

.modal-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin: 0;
}

.modal-level {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin: 0;
}

/* XP */
.modal-xp {
  margin-bottom: 16px;
}

.xp-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
}

.xp-badge {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
}

.xp-text {
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.xp-track {
  height: 10px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 5px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  overflow: hidden;
}

.xp-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 5px;
}

/* Stats row */
.modal-stats-row {
  display: flex;
  justify-content: center;
  gap: 30px;
  margin-bottom: 16px;
}

.modal-stat {
  text-align: center;
}

.modal-stat-num {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.4rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.modal-stat-label {
  font-size: 0.7rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Game stats */
.modal-game-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 16px;
}

.mini-stat {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
  padding: 10px 6px;
  text-align: center;
}

.mini-num {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.mini-win .mini-num { color: #6abf50; }
.mini-loss .mini-num { color: #d05040; }

.mini-label {
  display: block;
  font-size: 0.65rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 2px;
}

.games-together {
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 16px;
}

.games-together strong {
  color: var(--accent-gold);
}

/* Achievements */
.modal-section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.9rem;
  margin-bottom: 10px;
  text-align: center;
}

.ach-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 0;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.ach-icon {
  font-size: 1.2rem;
  flex-shrink: 0;
}

.ach-name {
  display: block;
  color: var(--text-bright);
  font-size: 0.85rem;
  font-weight: 600;
}

.ach-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.75rem;
}

@media (max-width: 768px) {
  .modal-game-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
