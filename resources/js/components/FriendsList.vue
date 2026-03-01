<template>
  <div class="card-panel friends-panel">
    <h2 class="section-title">Friends</h2>

    <!-- Add Friend -->
    <div class="add-friend">
      <input
        v-model="username"
        type="text"
        placeholder="Enter username..."
        class="friend-input"
        @keyup.enter="sendRequest"
      />
      <button class="btn-primary send-btn" @click="sendRequest" :disabled="sending || !username.trim()">
        {{ sending ? '...' : 'Send' }}
      </button>
    </div>
    <p v-if="error" class="friend-error">{{ error }}</p>
    <p v-if="success" class="friend-success">{{ success }}</p>

    <!-- Pending Received -->
    <div v-if="pendingReceived.length" class="friend-section">
      <h3 class="section-subtitle">Pending Requests</h3>
      <div v-for="req in pendingReceived" :key="req.id" class="friend-item">
        <span class="friend-name">{{ req.user.name }}</span>
        <div class="friend-actions">
          <button class="btn-success action-btn" @click="acceptRequest(req.id)">Accept</button>
          <button class="btn-danger action-btn" @click="removeFriendship(req.id)">Decline</button>
        </div>
      </div>
    </div>

    <!-- Friends -->
    <div v-if="friends.length" class="friend-section">
      <h3 class="section-subtitle">Your Allies</h3>
      <div
        v-for="f in friends"
        :key="f.id"
        class="friend-card"
        @click="showProfileFriend = f"
      >
        <div class="friend-card-left">
          <div class="friend-avatar">{{ f.user.name?.charAt(0)?.toUpperCase() || '?' }}</div>
          <div class="friend-info">
            <span class="friend-card-name">{{ f.user.name }}</span>
            <span class="friend-card-level">Lv.{{ f.user.level ?? 1 }}</span>
          </div>
        </div>
        <div class="friend-card-stats">
          <div class="friend-stat">
            <span class="friend-stat-val stat-win">{{ f.stats?.wins ?? 0 }}</span>
            <span class="friend-stat-label">W</span>
          </div>
          <span class="stat-slash">/</span>
          <div class="friend-stat">
            <span class="friend-stat-val stat-draw">{{ f.stats?.draws ?? 0 }}</span>
            <span class="friend-stat-label">D</span>
          </div>
          <span class="stat-slash">/</span>
          <div class="friend-stat">
            <span class="friend-stat-val stat-loss">{{ f.stats?.losses ?? 0 }}</span>
            <span class="friend-stat-label">L</span>
          </div>
        </div>
        <div v-if="f.stats?.last_played" class="friend-last-played">{{ f.stats.last_played }}</div>
      </div>
    </div>

    <!-- Sent -->
    <div v-if="pendingSent.length" class="friend-section">
      <h3 class="section-subtitle">Sent Requests</h3>
      <div v-for="req in pendingSent" :key="req.id" class="friend-item">
        <span class="friend-name">{{ req.user.name }}</span>
        <button class="btn-danger action-btn" @click="removeFriendship(req.id)">Cancel</button>
      </div>
    </div>

    <p v-if="!friends.length && !pendingReceived.length && !pendingSent.length" class="friends-empty">
      No allies yet. Send a friend request above!
    </p>

    <PlayerProfile
      v-if="showProfileFriend"
      :userId="showProfileFriend.user.id"
      :friendshipId="showProfileFriend.id"
      @close="showProfileFriend = null"
      @removed="showProfileFriend = null; fetchFriends()"
    />
  </div>
</template>

<script>
import axios from 'axios';
import PlayerProfile from './PlayerProfile.vue';

export default {
  name: 'FriendsList',
  components: { PlayerProfile },
  data() {
    return {
      username: '',
      showProfileFriend: null,
      friends: [],
      pendingSent: [],
      pendingReceived: [],
      error: '',
      success: '',
      sending: false,
    };
  },
  async mounted() {
    await this.fetchFriends();
  },
  methods: {
    async fetchFriends() {
      try {
        const res = await axios.get('/api/friends');
        this.friends = res.data.friends;
        this.pendingSent = res.data.pending_sent;
        this.pendingReceived = res.data.pending_received;
      } catch {
        // silently fail
      }
    },
    async sendRequest() {
      if (!this.username.trim()) return;
      this.error = '';
      this.success = '';
      this.sending = true;
      try {
        await axios.post('/api/friends', { username: this.username.trim() });
        this.success = `Request sent to ${this.username}`;
        this.username = '';
        await this.fetchFriends();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to send request';
      }
      this.sending = false;
    },
    async acceptRequest(id) {
      try {
        await axios.post(`/api/friends/${id}/accept`);
        await this.fetchFriends();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to accept';
      }
    },
    async removeFriendship(id) {
      try {
        await axios.delete(`/api/friends/${id}`);
        await this.fetchFriends();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to remove';
      }
    },
  },
};
</script>

<style scoped>
.friends-panel {
  margin-top: 20px;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 15px;
  text-align: center;
}

.add-friend {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.friend-input {
  flex: 1;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1rem;
  padding: 8px 12px;
  outline: none;
  transition: border-color 0.2s;
}

.friend-input:focus {
  border-color: var(--accent-gold);
  box-shadow: 0 0 8px rgba(212, 168, 67, 0.2);
}

.send-btn {
  padding: 8px 20px;
  font-size: 0.9rem;
}

.friend-error {
  color: var(--accent-red);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.friend-success {
  color: var(--accent-green);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.friend-section {
  margin-top: 16px;
}

.section-subtitle {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.9rem;
  margin-bottom: 8px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.friend-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.friend-name {
  color: var(--text-primary);
  font-size: 1rem;
}

.friend-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  padding: 4px 12px;
  font-size: 0.75rem;
}

/* Friend cards */
.friend-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  margin-bottom: 8px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.friend-card:hover {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.06);
}

.friend-card-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.friend-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent-gold), #8a6a14);
  color: var(--wood-dark);
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid var(--border-gold);
  flex-shrink: 0;
}

.friend-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.friend-card-name {
  color: var(--text-bright);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.friend-card-level {
  color: var(--text-secondary);
  font-size: 0.7rem;
}

.friend-card-stats {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}

.friend-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 20px;
}

.friend-stat-val {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 700;
}

.stat-win { color: #6abf50; }
.stat-draw { color: var(--text-secondary); }
.stat-loss { color: #d05040; }

.stat-slash {
  color: var(--text-secondary);
  font-size: 0.7rem;
  opacity: 0.5;
}

.friend-stat-label {
  font-size: 0.55rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.friend-last-played {
  font-size: 0.65rem;
  color: var(--text-secondary);
  white-space: nowrap;
  flex-shrink: 0;
}

.friends-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 15px 0;
}

@media (max-width: 768px) {
  .section-title {
    font-size: 1.1rem;
  }

  .action-btn {
    padding: 4px 10px;
    font-size: 0.7rem;
  }

  .friend-last-played {
    display: none;
  }
}
</style>
