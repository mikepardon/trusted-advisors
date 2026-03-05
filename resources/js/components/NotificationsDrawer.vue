<template>
  <div v-if="open" class="notif-backdrop" @click.self="$emit('close')">
    <div class="notif-drawer" :class="{ 'drawer-visible': visible }">
      <div class="drawer-header">
        <h3 class="drawer-title">Notifications</h3>
        <button v-if="dbNotifications.length" class="mark-read-btn" @click="markAllRead">Mark all read</button>
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

        <!-- DB Notifications (Rewards & Announcements) -->
        <div v-if="dbNotifications.length" class="notif-section">
          <h4 class="notif-section-title">Rewards &amp; Announcements</h4>
          <div v-for="notif in dbNotifications" :key="'db-' + notif.id" class="notif-item notif-db" :class="{ unread: !notif.read_at }">
            <div class="notif-icon-col">
              <span v-if="notif.type === 'season_reward'" class="notif-type-icon">&#127942;</span>
              <span v-else-if="notif.type === 'admin_gift'" class="notif-type-icon">&#127873;</span>
              <span v-else class="notif-type-icon">&#128276;</span>
            </div>
            <div class="notif-body" @click="openDetail(notif)">
              <div class="notif-db-title">{{ notif.title }}</div>
              <div class="notif-db-message">{{ notif.message }}</div>
              <div class="notif-db-meta">
                <span v-if="!notif.claimed_at && hasRewards(notif)" class="notif-claim-tag">Claimable</span>
                <span v-else-if="notif.claimed_at" class="notif-claimed-tag">Claimed</span>
                <span class="notif-time">{{ timeAgo(notif.created_at) }}</span>
              </div>
            </div>
            <button
              v-if="!hasRewards(notif) || notif.claimed_at"
              class="notif-dismiss"
              @click.stop="dismissNotif(notif)"
              title="Dismiss"
            >&times;</button>
          </div>
        </div>
      </div>
    </div>

    <NotificationDetailModal
      v-if="selectedNotif"
      :notification="selectedNotif"
      @close="selectedNotif = null"
      @claimed="onClaimed"
    />
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../stores/toast';
import NotificationDetailModal from './NotificationDetailModal.vue';

export default {
  name: 'NotificationsDrawer',
  components: { NotificationDetailModal },
  setup() {
    return { toast: useToast() };
  },
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
      dbNotifications: [],
      selectedNotif: null,
    };
  },
  computed: {
    allEmpty() {
      return !this.gameInvites.length && !this.friendRequests.length && !this.dbNotifications.length;
    },
    totalCount() {
      const unreadDb = this.dbNotifications.filter(n => !n.read_at).length;
      return this.gameInvites.length + this.friendRequests.length + unreadDb;
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
    hasRewards(notif) {
      const d = notif.data;
      return d && ((d.reward_xp ?? 0) > 0 || (d.reward_coins ?? 0) > 0 || d.reward_character_id);
    },
    async fetchAll() {
      this.loading = true;
      try {
        const [invitesRes, friendsRes, notifsRes] = await Promise.all([
          axios.get('/api/game-invites/pending'),
          axios.get('/api/friends'),
          axios.get('/api/notifications'),
        ]);
        this.gameInvites = (invitesRes.data || []).map(i => ({ ...i, busy: false }));
        this.friendRequests = (friendsRes.data.pending_received || []).map(r => ({ ...r, busy: false }));
        this.dbNotifications = notifsRes.data?.data || [];
      } catch {
        // silently fail
      }
      this.loading = false;
    },
    openDetail(notif) {
      this.selectedNotif = notif;
      // Mark as read
      if (!notif.read_at) {
        axios.post(`/api/notifications/${notif.id}/read`).then(() => {
          notif.read_at = new Date().toISOString();
        }).catch(() => {});
      }
    },
    onClaimed(notifId) {
      const notif = this.dbNotifications.find(n => n.id === notifId);
      if (notif) {
        notif.claimed_at = new Date().toISOString();
        notif.read_at = notif.read_at || new Date().toISOString();
      }
      this.selectedNotif = null;
    },
    async dismissNotif(notif) {
      try {
        await axios.delete(`/api/notifications/${notif.id}`);
        this.dbNotifications = this.dbNotifications.filter(n => n.id !== notif.id);
      } catch {}
    },
    async markAllRead() {
      try {
        await axios.post('/api/notifications/mark-all-read');
        this.dbNotifications.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
      } catch {}
    },
    timeAgo(dateStr) {
      if (!dateStr) return '';
      const diff = Date.now() - new Date(dateStr).getTime();
      const mins = Math.floor(diff / 60000);
      if (mins < 1) return 'just now';
      if (mins < 60) return `${mins}m ago`;
      const hrs = Math.floor(mins / 60);
      if (hrs < 24) return `${hrs}h ago`;
      const days = Math.floor(hrs / 24);
      return `${days}d ago`;
    },
    async acceptGameInvite(invite) {
      invite.busy = true;
      try {
        const res = await axios.post(`/api/game-invites/${invite.id}/accept`);
        this.gameInvites = this.gameInvites.filter(i => i.id !== invite.id);
        this.$emit('close');
        this.$router.push('/game/' + res.data.game_id);
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to accept invite');
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
  justify-content: flex-end;
}

.notif-drawer {
  width: 340px;
  max-width: 85vw;
  height: 100%;
  background: var(--bg-secondary);
  border-left: 2px solid var(--border-gold);
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.3s ease;
}

.drawer-visible {
  transform: translateX(0);
}

.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px 10px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.3);
  gap: 8px;
}

.drawer-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

.mark-read-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 0.75rem;
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
}

.mark-read-btn:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
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

.notif-item.unread {
  border-left: 3px solid var(--accent-gold);
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

/* DB notifications styling */
.notif-db {
  cursor: pointer;
  padding: 8px 10px;
  align-items: flex-start;
}

.notif-db:hover {
  background: rgba(212, 168, 67, 0.06);
}

.notif-icon-col {
  flex-shrink: 0;
}

.notif-type-icon {
  font-size: 1.3rem;
}

.notif-body {
  flex: 1;
  min-width: 0;
}

.notif-db-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.85rem;
  margin-bottom: 2px;
}

.notif-db-message {
  color: var(--text-secondary);
  font-size: 0.8rem;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notif-db-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.notif-claim-tag {
  font-size: 0.6rem;
  padding: 1px 5px;
  border-radius: 3px;
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.notif-claimed-tag {
  font-size: 0.6rem;
  padding: 1px 5px;
  border-radius: 3px;
  background: rgba(74, 138, 58, 0.2);
  color: #6abf50;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.notif-time {
  font-size: 0.65rem;
  color: var(--text-secondary);
  opacity: 0.7;
}

.notif-dismiss {
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0 2px;
  line-height: 1;
  opacity: 0.5;
  flex-shrink: 0;
}

.notif-dismiss:hover {
  color: var(--accent-red);
  opacity: 1;
  transform: none;
  box-shadow: none;
}
</style>
