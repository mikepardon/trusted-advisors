<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="card-panel detail-modal">
      <button class="modal-close" @click="$emit('close')">&times;</button>

      <h3 class="modal-title">{{ notification.title }}</h3>
      <p class="modal-message">{{ notification.message }}</p>

      <!-- Season reward rank -->
      <div v-if="notification.type === 'season_reward' && notification.data?.rank" class="rank-display">
        You placed <strong>{{ ordinal(notification.data.rank) }}</strong>!
      </div>

      <!-- Reward breakdown -->
      <div v-if="hasRewards" class="reward-breakdown">
        <h4 class="reward-title">Rewards</h4>
        <div class="reward-items">
          <div v-if="notification.data?.reward_xp" class="reward-item">
            <span class="reward-icon">&#11088;</span>
            <span>{{ notification.data.reward_xp }} XP</span>
          </div>
          <div v-if="notification.data?.reward_coins" class="reward-item">
            <span class="reward-icon">&#129689;</span>
            <span>{{ notification.data.reward_coins }} Coins</span>
          </div>
          <div v-if="notification.data?.reward_title" class="reward-item">
            <span class="reward-icon">&#127941;</span>
            <span>Title: {{ notification.data.reward_title }}</span>
          </div>
          <div v-if="notification.data?.reward_character_id" class="reward-item">
            <span class="reward-icon">&#9812;</span>
            <span>Exclusive Character</span>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button
          v-if="!notification.claimed_at && hasRewards"
          class="btn-primary claim-btn"
          :disabled="claiming"
          @click="claim"
        >
          {{ claiming ? 'Claiming...' : 'Claim Reward' }}
        </button>
        <span v-else-if="notification.claimed_at" class="claimed-badge">Claimed</span>
      </div>

      <div v-if="claimError" class="claim-error">{{ claimError }}</div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';

export default {
  name: 'NotificationDetailModal',
  props: {
    notification: { type: Object, required: true },
  },
  emits: ['close', 'claimed'],
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      claiming: false,
      claimError: '',
    };
  },
  computed: {
    hasRewards() {
      const d = this.notification.data;
      return d && ((d.reward_xp ?? 0) > 0 || (d.reward_coins ?? 0) > 0 || d.reward_character_id);
    },
  },
  methods: {
    async claim() {
      this.claiming = true;
      this.claimError = '';
      try {
        const res = await axios.post(`/api/notifications/${this.notification.id}/claim`);
        // Update auth store with new user stats
        if (res.data.user) {
          this.auth.state.user.xp = res.data.user.xp;
          this.auth.state.user.level = res.data.user.level;
          this.auth.state.user.coins = res.data.user.coins;
        }
        this.$emit('claimed', this.notification.id);
      } catch (e) {
        this.claimError = e.response?.data?.error || 'Failed to claim reward';
      }
      this.claiming = false;
    },
    ordinal(n) {
      const s = ['th', 'st', 'nd', 'rd'];
      const v = n % 100;
      return n + (s[(v - 20) % 10] || s[v] || s[0]);
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.detail-modal {
  position: relative;
  max-width: 400px;
  width: 90%;
  padding: 28px;
}

.modal-close {
  position: absolute;
  top: 10px;
  right: 14px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.6rem;
  cursor: pointer;
  line-height: 1;
}

.modal-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.modal-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
  margin-bottom: 10px;
  padding-right: 24px;
}

.modal-message {
  color: var(--text-primary);
  font-size: 0.95rem;
  line-height: 1.4;
  margin-bottom: 16px;
}

.rank-display {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold-bright);
  font-size: 1.1rem;
  text-align: center;
  padding: 10px;
  margin-bottom: 16px;
  background: rgba(212, 168, 67, 0.08);
  border: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 6px;
}

.reward-breakdown {
  margin-bottom: 18px;
}

.reward-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 8px;
}

.reward-items {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.reward-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.15);
  border-radius: 4px;
  font-size: 0.95rem;
  color: var(--text-bright);
}

.reward-icon {
  font-size: 1.1rem;
}

.modal-actions {
  display: flex;
  justify-content: center;
  margin-top: 12px;
}

.claim-btn {
  padding: 8px 28px;
  font-size: 1rem;
}

.claimed-badge {
  font-family: 'Cinzel', serif;
  color: #6abf50;
  font-size: 0.9rem;
  font-weight: 600;
}

.claim-error {
  color: var(--accent-red);
  font-size: 0.85rem;
  text-align: center;
  margin-top: 8px;
}
</style>
