<template>
  <div v-if="open" class="notif-backdrop" @click.self="$emit('close')">
    <div class="notif-drawer" :class="{ 'drawer-visible': visible }">
      <div class="drawer-header">
        <h3 class="drawer-title">Notifications</h3>
        <div class="header-actions">
          <button v-if="databaseNotifications.length > 0" class="mark-read-btn" @click="markAllRead">Mark all read</button>
          <button v-if="hasReadNotifications" class="mark-read-btn delete-read-btn" @click="deleteAllRead">Delete all read</button>
        </div>
        <button class="drawer-close" @click="$emit('close')">&times;</button>
      </div>

      <div v-if="loading" class="drawer-loading">Loading...</div>

      <div v-else-if="allEmpty" class="drawer-empty">
        <p>No notifications at this time.</p>
      </div>

      <div v-else class="drawer-content">
        <!-- Game invites -->
        <div v-if="gameInvites.length > 0" class="notif-section">
          <h4 class="notif-section-title">Game Invites</h4>
          <div v-for="invite in gameInvites" :key="'gi-' + invite.id" class="notif-item">
            <div class="notif-text">
              <strong>{{ invite.sender?.name || 'Someone' }}</strong> invited you to a game
            </div>
            <div class="notif-actions">
              <button class="notif-btn notif-accept" :disabled="invite.busy" @click="acceptGameInvite(invite)">Accept</button>
              <button class="notif-btn notif-decline" :disabled="invite.busy" @click="declineGameInvite(invite)">Decline</button>
            </div>
          </div>
        </div>

        <!-- Friend requests -->
        <div v-if="friendRequests.length > 0" class="notif-section">
          <h4 class="notif-section-title">Friend Requests</h4>
          <div v-for="req in friendRequests" :key="'fr-' + req.id" class="notif-item">
            <div class="notif-text">
              <strong>{{ req.user?.name || 'Someone' }}</strong> sent you a friend request
            </div>
            <div class="notif-actions">
              <button class="notif-btn notif-accept" :disabled="req.busy" @click="acceptFriend(req)">Accept</button>
              <button class="notif-btn notif-decline" :disabled="req.busy" @click="rejectFriend(req)">Reject</button>
            </div>
          </div>
        </div>

        <!-- DB Notifications (Rewards & Announcements) -->
        <div v-if="databaseNotifications.length > 0" class="notif-section">
          <h4 class="notif-section-title">Rewards &amp; Announcements</h4>
          <div v-for="notif in databaseNotifications" :key="'db-' + notif.id" class="notif-item notif-db" :class="{ unread: !notif.read_at }">
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
              title="Dismiss"
              @click.stop="dismissNotif(notif)"
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

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import axios, { isAxiosError } from "axios";
import { useToast } from "../stores/toast";
import NotificationDetailModal from "./NotificationDetailModal.vue";

interface GameInvite {
  id: number;
  sender?: { name?: string };
  busy: boolean;
}

interface FriendRequest {
  id: number;
  user?: { name?: string };
  busy: boolean;
}

interface DatabaseNotificationData {
  reward_xp?: number;
  reward_coins?: number;
  reward_character_id?: number;
  reward_dice_theme_id?: number;
  reward_kingdom_style_id?: number;
}

interface DatabaseNotification {
  id: number;
  type: string;
  title: string;
  message: string;
  data?: DatabaseNotificationData;
  read_at?: string;
  claimed_at?: string;
  created_at?: string;
}

const { open = false } = defineProps<{
  open?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  "update:count": [count: number];
}>();

const toast = useToast();
const router = useRouter();

const loading = ref(false);
const visible = ref(false);
const gameInvites = ref<GameInvite[]>([]);
const friendRequests = ref<FriendRequest[]>([]);
const databaseNotifications = ref<DatabaseNotification[]>([]);
const selectedNotif = ref<DatabaseNotification>();

const allEmpty = computed(
  () => gameInvites.value.length === 0 && friendRequests.value.length === 0 && databaseNotifications.value.length === 0,
);

const totalCount = computed(() => {
  const unreadDatabase = databaseNotifications.value.filter((notif) => !notif.read_at).length;
  return gameInvites.value.length + friendRequests.value.length + unreadDatabase;
});

const hasReadNotifications = computed(() => databaseNotifications.value.some((notif) => notif.read_at));

watch(
  () => open,
  (value) => {
    if (value) {
      fetchAll();
      requestAnimationFrame(() => {
        visible.value = true;
      });
    } else {
      visible.value = false;
    }
  },
);

watch(totalCount, (value) => {
  emit("update:count", value);
});

function hasRewards(notif: DatabaseNotification): boolean {
  const data = notif.data;
  return Boolean(
    data &&
      ((data.reward_xp ?? 0) > 0 ||
        (data.reward_coins ?? 0) > 0 ||
        data.reward_character_id ||
        data.reward_dice_theme_id ||
        data.reward_kingdom_style_id),
  );
}

async function fetchAll(): Promise<void> {
  loading.value = true;
  try {
    const [invitesResponse, friendsResponse, notifsResponse] = await Promise.all([
      axios.get<GameInvite[] | undefined>("/api/game-invites/pending"),
      axios.get<{ pending_received?: FriendRequest[] }>("/api/friends"),
      axios.get<{ data?: DatabaseNotification[] }>("/api/notifications"),
    ]);
    gameInvites.value = (invitesResponse.data ?? []).map((invite) => ({ ...invite, busy: false }));
    friendRequests.value = (friendsResponse.data.pending_received ?? []).map((request) => ({ ...request, busy: false }));
    databaseNotifications.value = notifsResponse.data?.data ?? [];
  } catch {
    // silently fail
  }
  loading.value = false;
}

async function openDetail(notif: DatabaseNotification): Promise<void> {
  selectedNotif.value = notif;
  // Mark as read
  if (!notif.read_at) {
    try {
      await axios.post(`/api/notifications/${notif.id}/read`);
      notif.read_at = new Date().toISOString();
    } catch {
      // silently fail
    }
  }
}

function onClaimed(notifId: number): void {
  const notif = databaseNotifications.value.find((item) => item.id === notifId);
  if (notif) {
    notif.claimed_at = new Date().toISOString();
    notif.read_at ??= new Date().toISOString();
  }
  selectedNotif.value = undefined;
}

async function dismissNotif(notif: DatabaseNotification): Promise<void> {
  try {
    await axios.delete(`/api/notifications/${notif.id}`);
    databaseNotifications.value = databaseNotifications.value.filter((item) => item.id !== notif.id);
  } catch {
    // silently fail
  }
}

async function markAllRead(): Promise<void> {
  try {
    await axios.post("/api/notifications/mark-all-read");
    for (const notif of databaseNotifications.value) {
      notif.read_at ??= new Date().toISOString();
    }
  } catch {
    // silently fail
  }
}

async function deleteAllRead(): Promise<void> {
  try {
    await axios.delete("/api/notifications/read");
    databaseNotifications.value = databaseNotifications.value.filter((notif) => !notif.read_at);
  } catch {
    // silently fail
  }
}

function timeAgo(dateString: string | undefined): string {
  if (!dateString) {
    return "";
  }
  const diff = Date.now() - new Date(dateString).getTime();
  const mins = Math.floor(diff / 60_000);
  if (mins < 1) {
    return "just now";
  }
  if (mins < 60) {
    return `${mins}m ago`;
  }
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) {
    return `${hrs}h ago`;
  }
  const days = Math.floor(hrs / 24);
  return `${days}d ago`;
}

async function acceptGameInvite(invite: GameInvite): Promise<void> {
  invite.busy = true;
  try {
    const response = await axios.post<{ game_id: number }>(`/api/game-invites/${invite.id}/accept`);
    gameInvites.value = gameInvites.value.filter((item) => item.id !== invite.id);
    emit("close");
    router.push(`/game/${response.data.game_id}`);
  } catch (error) {
    toast.error(acceptInviteErrorMessage(error));
    invite.busy = false;
  }
}

function acceptInviteErrorMessage(error: unknown): string {
  if (isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error ?? "Failed to accept invite";
  }
  return "Failed to accept invite";
}

async function declineGameInvite(invite: GameInvite): Promise<void> {
  invite.busy = true;
  try {
    await axios.post(`/api/game-invites/${invite.id}/decline`);
    gameInvites.value = gameInvites.value.filter((item) => item.id !== invite.id);
  } catch {
    invite.busy = false;
  }
}

async function acceptFriend(request: FriendRequest): Promise<void> {
  request.busy = true;
  try {
    await axios.post(`/api/friends/${request.id}/accept`);
    friendRequests.value = friendRequests.value.filter((item) => item.id !== request.id);
  } catch {
    request.busy = false;
  }
}

async function rejectFriend(request: FriendRequest): Promise<void> {
  request.busy = true;
  try {
    await axios.delete(`/api/friends/${request.id}`);
    friendRequests.value = friendRequests.value.filter((item) => item.id !== request.id);
  } catch {
    request.busy = false;
  }
}
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
  justify-content: space-between;
  padding: 14px 18px 10px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.3);
  gap: 8px;
    position: relative;
    flex-direction: column;
}

.drawer-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.mark-read-btn {
  background: none !important;
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  color: var(--text-secondary);
  font-size: 0.75rem !important;
  cursor: pointer;
  text-decoration: underline;
  padding: 0 !important;
    background: unset !important;
}

.mark-read-btn:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none !important;
}

.delete-read-btn {
  color: var(--accent-red);
}

.delete-read-btn:hover {
  color: #f06050;
}

.drawer-close {
  background: none !important;
  border: none !important;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
    box-shadow: none !important;
    position: absolute;
    top: 0;
    right: 0;
    font-size: 2rem;
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
