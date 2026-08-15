<template>
    <div v-if="open" class="notif-scrim" @click.self="$emit('close')">
        <div class="notif-panel" :class="{ 'panel-visible': visible }">
            <div class="panel-header">
                <span class="panel-label">NOTIFICATIONS</span>
                <span class="panel-spacer"></span>
                <button
                    v-if="activeTab === 'active' && activeBadgeCount > 0"
                    class="panel-action"
                    @click="markAllRead"
                >
                    MARK ALL READ
                </button>
                <button class="panel-close" @click="$emit('close')">
                    &times;
                </button>
            </div>

            <div v-if="loading" class="panel-state">Loading&hellip;</div>

            <div v-else class="panel-list">
                <template v-if="activeTab === 'active'">
                    <!-- Game invites -->
                    <div
                        v-for="invite in gameInvites"
                        :key="'gi-' + invite.id"
                        class="notif-row notif-row--unread"
                    >
                        <div
                            class="notif-badge"
                            :style="badgeStyle('game_invite')"
                        >
                            <span
                                class="notif-glyph"
                                :style="glyphStyle('game_invite')"
                                >{{ badgeGlyph("game_invite") }}</span
                            >
                        </div>
                        <div class="notif-copy">
                            <div class="notif-title">Game invite</div>
                            <div class="notif-body">
                                <strong>{{
                                    invite.sender?.name || "Someone"
                                }}</strong>
                                invited you to a game
                            </div>
                            <div class="notif-inline-actions">
                                <button
                                    class="notif-btn notif-btn--accept"
                                    :disabled="invite.busy"
                                    @click="acceptGameInvite(invite)"
                                >
                                    Accept
                                </button>
                                <button
                                    class="notif-btn notif-btn--decline"
                                    :disabled="invite.busy"
                                    @click="declineGameInvite(invite)"
                                >
                                    Decline
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Friend requests -->
                    <div
                        v-for="req in friendRequests"
                        :key="'fr-' + req.id"
                        class="notif-row notif-row--unread"
                    >
                        <div
                            class="notif-badge"
                            :style="badgeStyle('friend_request')"
                        >
                            <span
                                class="notif-glyph"
                                :style="glyphStyle('friend_request')"
                                >{{ badgeGlyph("friend_request") }}</span
                            >
                        </div>
                        <div class="notif-copy">
                            <div class="notif-title">Friend request</div>
                            <div class="notif-body">
                                <strong>{{
                                    req.user?.name || "Someone"
                                }}</strong>
                                sent you a friend request
                            </div>
                            <div class="notif-inline-actions">
                                <button
                                    class="notif-btn notif-btn--accept"
                                    :disabled="req.busy"
                                    @click="acceptFriend(req)"
                                >
                                    Accept
                                </button>
                                <button
                                    class="notif-btn notif-btn--decline"
                                    :disabled="req.busy"
                                    @click="rejectFriend(req)"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- DB notifications: active (unread / actionable) in the Active
                     tab; read-and-done ones live under Archived. -->
                <div
                    v-for="notif in visibleNotifications"
                    :key="'db-' + notif.id"
                    class="notif-row notif-row--tap"
                    :class="{ 'notif-row--unread': !notif.read_at }"
                    @click="openDetail(notif)"
                >
                    <div class="notif-badge" :style="badgeStyle(notif.type)">
                        <span
                            class="notif-glyph"
                            :style="glyphStyle(notif.type)"
                            >{{ badgeGlyph(notif.type) }}</span
                        >
                    </div>
                    <div class="notif-copy">
                        <div class="notif-title">{{ notif.title }}</div>
                        <div class="notif-body">{{ notif.message }}</div>
                        <div class="notif-meta">
                            <span
                                v-if="!notif.claimed_at && hasRewards(notif)"
                                class="notif-tag notif-tag--claim"
                                >Claimable</span
                            >
                            <span
                                v-else-if="notif.claimed_at"
                                class="notif-tag notif-tag--claimed"
                                >Claimed</span
                            >
                        </div>
                    </div>
                    <span class="notif-when">{{
                        timeAgo(notif.created_at)
                    }}</span>
                    <span v-if="!notif.read_at" class="ta-dot notif-dot"></span>
                    <button
                        v-if="
                            activeTab === 'active' &&
                            (!hasRewards(notif) || notif.claimed_at)
                        "
                        class="notif-dismiss"
                        title="Dismiss"
                        @click.stop="dismissNotif(notif)"
                    >
                        &times;
                    </button>
                </div>

                <div
                    v-if="currentTabEmpty"
                    class="panel-state panel-state--tab"
                >
                    {{
                        activeTab === "active"
                            ? "You're all caught up."
                            : "Nothing archived yet."
                    }}
                </div>
            </div>

            <!-- Bottom tabs: Archived list is only rendered once its tab is opened -->
            <div v-if="!loading" class="panel-tabs">
                <button
                    class="panel-tab"
                    :class="{ active: activeTab === 'active' }"
                    @click="activeTab = 'active'"
                >
                    Active
                    <span v-if="activeBadgeCount > 0" class="panel-tab-count">{{
                        activeBadgeCount
                    }}</span>
                </button>
                <button
                    class="panel-tab"
                    :class="{ active: activeTab === 'archived' }"
                    @click="activeTab = 'archived'"
                >
                    Archived
                </button>
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

interface BadgeStyle {
    glyph: string;
    rim: string;
    face: string;
    glyphColour: string;
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
const activeTab = ref<"active" | "archived">("active");

// A DB notification is "done" once it's read and needs no further action
// (no unclaimed reward). Those move to Archived; everything else stays Active.
const archivedNotifications = computed(() =>
    databaseNotifications.value.filter(
        (notif) =>
            !!notif.read_at && (!hasRewards(notif) || !!notif.claimed_at),
    ),
);

const activeNotifications = computed(() =>
    databaseNotifications.value.filter(
        (notif) => !notif.read_at || (hasRewards(notif) && !notif.claimed_at),
    ),
);

const visibleNotifications = computed(() =>
    activeTab.value === "active"
        ? activeNotifications.value
        : archivedNotifications.value,
);

const activeBadgeCount = computed(
    () =>
        gameInvites.value.length +
        friendRequests.value.length +
        activeNotifications.value.length,
);

const currentTabEmpty = computed(
    () =>
        (activeTab.value === "active"
            ? activeBadgeCount.value
            : archivedNotifications.value.length) === 0,
);

const totalCount = computed(() => {
    const unreadDatabase = databaseNotifications.value.filter(
        (notif) => !notif.read_at,
    ).length;
    return (
        gameInvites.value.length + friendRequests.value.length + unreadDatabase
    );
});

/**
 * Presentation-only mapping of a notification type to its medallion badge look
 * (glyph, gold/steel/green/blue rim + face, glyph colour). Purely cosmetic.
 */
const badgeThemes: Record<string, BadgeStyle> = {
    game_invite: {
        glyph: "⚔",
        rim: "linear-gradient(180deg,#dfe8f2,#6a7a8c)",
        face: "radial-gradient(circle at 40% 28%,#3a4a5c,#141c26)",
        glyphColour: "#dfe8f2",
    },
    friend_request: {
        glyph: "☺",
        rim: "linear-gradient(180deg,#9ad0ff,#2f5f9f)",
        face: "radial-gradient(circle at 40% 28%,#1a3550,#0a1420)",
        glyphColour: "#9ad0ff",
    },
    season_reward: {
        glyph: "★",
        rim: "linear-gradient(180deg,#a8f0a8,#2f7f20)",
        face: "radial-gradient(circle at 40% 28%,#20431a,#0c1a08)",
        glyphColour: "#a8f0a8",
    },
    admin_gift: {
        glyph: "✦",
        rim: "linear-gradient(180deg,#ffe9a8,#b8842a)",
        face: "radial-gradient(circle at 40% 28%,#5c3c0c,#241704)",
        glyphColour: "#ffd977",
    },
};

const defaultBadge: BadgeStyle = {
    glyph: "◈",
    rim: "linear-gradient(180deg,#ffe9a8,#b8842a)",
    face: "radial-gradient(circle at 40% 28%,#5c3c0c,#241704)",
    glyphColour: "#ffd977",
};

function badgeTheme(type: string): BadgeStyle {
    return badgeThemes[type] ?? defaultBadge;
}

function badgeStyle(type: string): { background: string } {
    return { background: badgeTheme(type).rim };
}

function glyphStyle(type: string): { background: string; color: string } {
    const theme = badgeTheme(type);
    return { background: theme.face, color: theme.glyphColour };
}

function badgeGlyph(type: string): string {
    return badgeTheme(type).glyph;
}

watch(
    () => open,
    (value) => {
        if (value) {
            activeTab.value = "active";
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
        const [invitesResponse, friendsResponse, notifsResponse] =
            await Promise.all([
                axios.get<GameInvite[] | undefined>(
                    "/api/game-invites/pending",
                ),
                axios.get<{ pending_received?: FriendRequest[] }>(
                    "/api/friends",
                ),
                axios.get<{ data?: DatabaseNotification[] }>(
                    "/api/notifications",
                ),
            ]);
        gameInvites.value = (invitesResponse.data ?? []).map((invite) => ({
            ...invite,
            busy: false,
        }));
        friendRequests.value = (
            friendsResponse.data.pending_received ?? []
        ).map((request) => ({ ...request, busy: false }));
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
    const notif = databaseNotifications.value.find(
        (item) => item.id === notifId,
    );
    if (notif) {
        notif.claimed_at = new Date().toISOString();
        notif.read_at ??= new Date().toISOString();
    }
    selectedNotif.value = undefined;
}

async function dismissNotif(notif: DatabaseNotification): Promise<void> {
    try {
        await axios.delete(`/api/notifications/${notif.id}`);
        databaseNotifications.value = databaseNotifications.value.filter(
            (item) => item.id !== notif.id,
        );
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

function timeAgo(dateString: string | undefined): string {
    if (!dateString) {
        return "";
    }
    const diff = Date.now() - new Date(dateString).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) {
        return "now";
    }
    if (mins < 60) {
        return `${mins}m`;
    }
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) {
        return `${hrs}h`;
    }
    const days = Math.floor(hrs / 24);
    return `${days}d`;
}

async function acceptGameInvite(invite: GameInvite): Promise<void> {
    invite.busy = true;
    try {
        const response = await axios.post<{ game_id: number }>(
            `/api/game-invites/${invite.id}/accept`,
        );
        gameInvites.value = gameInvites.value.filter(
            (item) => item.id !== invite.id,
        );
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
        gameInvites.value = gameInvites.value.filter(
            (item) => item.id !== invite.id,
        );
    } catch {
        invite.busy = false;
    }
}

async function acceptFriend(request: FriendRequest): Promise<void> {
    request.busy = true;
    try {
        await axios.post(`/api/friends/${request.id}/accept`);
        friendRequests.value = friendRequests.value.filter(
            (item) => item.id !== request.id,
        );
    } catch {
        request.busy = false;
    }
}

async function rejectFriend(request: FriendRequest): Promise<void> {
    request.busy = true;
    try {
        await axios.delete(`/api/friends/${request.id}`);
        friendRequests.value = friendRequests.value.filter(
            (item) => item.id !== request.id,
        );
    } catch {
        request.busy = false;
    }
}
</script>

<style scoped>
/* Dark scrim over the whole screen */
.notif-scrim {
    position: fixed;
    inset: 0;
    background: rgba(5, 3, 1, 0.55);
    z-index: 1000;
}

/* Rounded gold-bordered panel anchored near the top */
.notif-panel {
    position: absolute;
    top: 84px;
    left: 16px;
    right: 16px;
    max-width: 460px;
    margin: 0 auto;
    max-height: min(72vh, 520px);
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none;
    border-radius: 16px;
    background: linear-gradient(
        180deg,
        rgba(30, 22, 13, 0.99),
        rgba(13, 9, 5, 0.99)
    );
    border: 1px solid rgba(240, 192, 80, 0.45);
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.7);
    transform: translateY(-10px);
    opacity: 0;
    transition:
        transform 0.22s ease,
        opacity 0.22s ease;
}

.notif-panel::-webkit-scrollbar {
    display: none;
}

.panel-visible {
    transform: translateY(0);
    opacity: 1;
}

/* Header row */
.panel-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(240, 192, 80, 0.22);
    position: sticky;
    top: 0;
    background: linear-gradient(
        180deg,
        rgba(30, 22, 13, 0.99),
        rgba(24, 17, 10, 0.99)
    );
    z-index: 1;
}

.panel-label {
    font-family: "Cinzel", serif;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 2.2px;
    color: var(--accent-gold);
}

.panel-spacer {
    flex: 1;
}

.panel-action {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-family: "Cinzel", serif;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 1px;
    color: #9a8a68;
    box-shadow: none;
    white-space: nowrap;
}

.panel-action:hover {
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
}

.panel-action--danger {
    color: #b06050;
}

.panel-action--danger:hover {
    color: #f06050;
}

.panel-close {
    background: none;
    border: none;
    padding: 0 2px;
    margin-left: 2px;
    line-height: 1;
    cursor: pointer;
    font-size: 18px;
    color: #9a8a68;
    box-shadow: none;
}

.panel-close:hover {
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
}

/* Loading / empty states */
.panel-state {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 34px 20px;
    font-size: 0.9rem;
}

.panel-state--tab {
    padding: 28px 20px;
}

.panel-list {
    display: flex;
    flex-direction: column;
}

/* Bottom Active | Archived tabs */
.panel-tabs {
    position: sticky;
    bottom: 0;
    z-index: 1;
    display: flex;
    gap: 6px;
    padding: 8px 12px;
    border-top: 1px solid rgba(240, 192, 80, 0.22);
    background: linear-gradient(
        180deg,
        rgba(24, 17, 10, 0.99),
        rgba(16, 11, 6, 0.99)
    );
}

.panel-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 80, 0.22);
    color: var(--text-secondary);
    font-family: "Cinzel", serif;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    cursor: pointer;
    box-shadow: none;
}

.panel-tab.active {
    color: #241703;
    background: linear-gradient(180deg, #ffe897, #c8952e);
    border-color: #fff0b0;
}

.panel-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: rgba(208, 64, 48, 0.85);
    color: #fff;
    font-size: 0.6rem;
    letter-spacing: 0;
}

.panel-tab.active .panel-tab-count {
    background: rgba(36, 23, 3, 0.55);
}

/* Notification row */
.notif-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(240, 192, 80, 0.1);
    background: transparent;
}

.notif-row:last-child {
    border-bottom: none;
}

.notif-row--unread {
    background: rgba(242, 112, 60, 0.09);
}

.notif-row--tap {
    cursor: pointer;
}

.notif-row--tap:hover {
    background: rgba(240, 192, 80, 0.06);
}

.notif-row--tap.notif-row--unread:hover {
    background: rgba(242, 112, 60, 0.14);
}

/* Coloured rim + face medallion badge */
.notif-badge {
    width: 34px;
    height: 34px;
    flex: none;
    box-sizing: border-box;
    padding: 2px;
    border-radius: 11px;
}

/* The face gradient + glyph colour are painted inline per notification type. */
.notif-glyph {
    width: 100%;
    height: 100%;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}

.notif-copy {
    flex: 1;
    min-width: 0;
}

.notif-title {
    font-family: "Cinzel", serif;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-body {
    font-size: 11px;
    color: #9a8a68;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-body strong {
    color: var(--accent-gold);
    font-weight: 700;
}

.notif-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 3px;
}

.notif-meta:empty {
    display: none;
}

.notif-tag {
    font-family: "Cinzel", serif;
    font-size: 8px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.notif-tag--claim {
    background: rgba(240, 192, 80, 0.18);
    color: var(--accent-gold);
    border: 1px solid rgba(240, 192, 80, 0.4);
}

.notif-tag--claimed {
    background: rgba(74, 138, 58, 0.2);
    color: #6abf50;
    border: 1px solid rgba(74, 138, 58, 0.4);
}

.notif-when {
    flex: none;
    font-size: 10px;
    color: #7a6a4a;
    align-self: flex-start;
    padding-top: 1px;
}

.notif-dot {
    flex: none;
    align-self: center;
}

.notif-dismiss {
    background: none;
    border: none;
    padding: 0 2px;
    line-height: 1;
    flex: none;
    cursor: pointer;
    font-size: 1.1rem;
    color: #7a6a4a;
    opacity: 0.6;
    box-shadow: none;
}

.notif-dismiss:hover {
    color: var(--accent-red);
    opacity: 1;
    transform: none;
    box-shadow: none;
}

/* Inline accept/decline for invites and friend requests */
.notif-inline-actions {
    display: flex;
    gap: 6px;
    margin-top: 6px;
}

.notif-btn {
    padding: 3px 12px;
    font-size: 0.72rem;
    font-family: "Cinzel", serif;
    font-weight: 700;
    letter-spacing: 0.4px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.notif-btn--accept {
    background: rgba(74, 138, 58, 0.2);
    border: 1px solid rgba(74, 138, 58, 0.5);
    color: #6abf50;
}

.notif-btn--accept:hover:not(:disabled) {
    background: rgba(74, 138, 58, 0.35);
    transform: none;
    box-shadow: none;
}

.notif-btn--decline {
    background: rgba(160, 48, 32, 0.2);
    border: 1px solid rgba(160, 48, 32, 0.5);
    color: #d05040;
}

.notif-btn--decline:hover:not(:disabled) {
    background: rgba(160, 48, 32, 0.35);
    transform: none;
    box-shadow: none;
}

.notif-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
