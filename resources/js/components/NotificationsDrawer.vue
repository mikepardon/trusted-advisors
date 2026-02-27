<template>
  <div v-if="open" class="notif-backdrop" @click.self="$emit('close')">
    <div class="notif-drawer" :class="{ 'drawer-visible': visible }">
      <div class="drawer-header">
        <h3 class="drawer-title">Notifications</h3>
        <button class="drawer-close" @click="$emit('close')">&times;</button>
      </div>

      <div v-if="loading" class="drawer-loading">Loading...</div>

      <div v-else-if="allEmpty" class="drawer-empty">
        <p>No notifications at this time.</p>
      </div>

      <div v-else class="drawer-content">
        <!-- Game invites -->
        <div v-if="gameInvites.length" class="notif-section">
          <h4 class="notif-section-title">Game Invites</h4>
          <div v-for="invite in gameInvites" :key="'gi-' + invite.id" class="notif-item">
            <div class="notif-text">
              <strong>{{ invite.sender?.name || 'Someone' }}</strong> invited you to a game
            </div>
            <div class="notif-actions">
              <button class="notif-btn notif-accept" @click="acceptGameInvite(invite)" :disabled="invite.busy">Accept</button>
              <button class="notif-btn notif-decline" @click="declineGameInvite(invite)" :disabled="invite.busy">Decline</button>
            </div>
          </div>
        </div>

        <!-- Friend requests -->
        <div v-if="friendRequests.length" class="notif-section">
          <h4 class="notif-section-title">Friend Requests</h4>
          <div v-for="req in friendRequests" :key="'fr-' + req.id" class="notif-item">
            <div class="notif-text">
              <strong>{{ req.user?.name || 'Someone' }}</strong> sent you a friend request
            </div>
            <div class="notif-actions">
              <button class="notif-btn notif-accept" @click="acceptFriend(req)" :disabled="req.busy">Accept</button>
              <button class="notif-btn notif-decline" @click="rejectFriend(req)" :disabled="req.busy">Reject</button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'NotificationsDrawer',
  props: {
    open: { type: Boolean, default: false },
  },
  emits: ['close', 'update:count'],
  data() {
    return {
      loading: false,
      visible: false,
      gameInvites: [],
      friendRequests: [],
    };
  },
  computed: {
    allEmpty() {
      return !this.gameInvites.length && !this.friendRequests.length;
    },
    totalCount() {
      return this.gameInvites.length + this.friendRequests.length;
    },
  },
  watch: {
    open(val) {
      if (val) {
        this.fetchAll();
        requestAnimationFrame(() => { this.visible = true; });
      } else {
        this.visible = false;
      }
    },
    totalCount(val) {
      this.$emit('update:count', val);
    },
  },
  methods: {
    async fetchAll() {
      this.loading = true;
      try {
        const [invitesRes, friendsRes] = await Promise.all([
          axios.get('/api/game-invites/pending'),
          axios.get('/api/friends'),
        ]);
        this.gameInvites = (invitesRes.data || []).map(i => ({ ...i, busy: false }));
        this.friendRequests = (friendsRes.data.pending_received || []).map(r => ({ ...r, busy: false }));
      } catch {
        // silently fail
      }
      this.loading = false;
    },
    async acceptGameInvite(invite) {
      invite.busy = true;
      try {
        const res = await axios.post(`/api/game-invites/${invite.id}/accept`);
        this.gameInvites = this.gameInvites.filter(i => i.id !== invite.id);
        this.$emit('close');
        this.$router.push('/game/' + res.data.game_id);
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to accept invite');
        invite.busy = false;
      }
    },
    async declineGameInvite(invite) {
      invite.busy = true;
      try {
        await axios.post(`/api/game-invites/${invite.id}/decline`);
        this.gameInvites = this.gameInvites.filter(i => i.id !== invite.id);
      } catch {
        invite.busy = false;
      }
    },
    async acceptFriend(req) {
      req.busy = true;
      try {
        await axios.post(`/api/friends/${req.id}/accept`);
        this.friendRequests = this.friendRequests.filter(r => r.id !== req.id);
      } catch {
        req.busy = false;
      }
    },
    async rejectFriend(req) {
      req.busy = true;
      try {
        await axios.delete(`/api/friends/${req.id}`);
        this.friendRequests = this.friendRequests.filter(r => r.id !== req.id);
      } catch {
        req.busy = false;
      }
    },
  },
};
</script>

<style scoped>
.notif-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.notif-drawer {
  width: 100%;
  max-width: 1000px;
  max-height: 70vh;
  background: var(--bg-secondary);
  border-top: 2px solid var(--border-gold);
  border-radius: 12px 12px 0 0;
  display: flex;
  flex-direction: column;
  transform: translateY(100%);
  transition: transform 0.3s ease;
}

.drawer-visible {
  transform: translateY(0);
}

.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px 10px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.3);
}

.drawer-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

.drawer-close {
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.6rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

.drawer-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.drawer-loading,
.drawer-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px 20px;
}

.drawer-content {
  overflow-y: auto;
  padding: 10px 18px 20px;
  padding-bottom: calc(20px + env(safe-area-inset-bottom));
}

.notif-section {
  margin-bottom: 16px;
}

.notif-section:last-child {
  margin-bottom: 0;
}

.notif-section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 8px;
}

.notif-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
  margin-bottom: 6px;
}

.notif-item:last-child {
  margin-bottom: 0;
}

.notif-text {
  flex: 1;
  color: var(--text-primary);
  font-size: 0.95rem;
}

.notif-text strong {
  color: var(--accent-gold);
}

.notif-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.notif-btn {
  padding: 4px 12px;
  font-size: 0.8rem;
  font-family: 'Cinzel', serif;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  letter-spacing: 0;
}

.notif-accept {
  background: rgba(74, 138, 58, 0.2);
  border: 1px solid rgba(74, 138, 58, 0.5);
  color: #6abf50;
}

.notif-accept:hover:not(:disabled) {
  background: rgba(74, 138, 58, 0.35);
  transform: none;
  box-shadow: none;
}

.notif-decline {
  background: rgba(160, 48, 32, 0.2);
  border: 1px solid rgba(160, 48, 32, 0.5);
  color: #d05040;
}

.notif-decline:hover:not(:disabled) {
  background: rgba(160, 48, 32, 0.35);
  transform: none;
  box-shadow: none;
}

.notif-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

</style>
