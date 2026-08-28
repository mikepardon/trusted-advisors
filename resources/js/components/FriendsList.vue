<template>
    <TaSubPage title="Allies" subtitle="Duel the people you know">
        <!-- Count tiles -->
        <div class="ally-tiles">
            <div class="ally-tile">
                <div class="ally-tile-num">{{ friends.length }}</div>
                <div class="ally-tile-label">Allies</div>
            </div>
            <div class="ally-tile">
                <div class="ally-tile-num ally-tile-num--online">
                    {{ onlineCount }}
                </div>
                <div class="ally-tile-label">Online</div>
            </div>
            <div class="ally-tile">
                <div class="ally-tile-num ally-tile-num--pending">
                    {{ pendingCount }}
                </div>
                <div class="ally-tile-label">Requests</div>
            </div>
        </div>

        <!-- Add ally -->
        <form class="add-ally" @submit.prevent="sendRequest">
            <input
                v-model="username"
                type="text"
                placeholder="Enter username..."
                class="ally-input"
            />
            <button
                type="submit"
                class="ta-pill ta-pill--gold add-ally-btn"
                :disabled="sending || !username.trim()"
            >
                {{ sending ? "..." : "ADD" }}
            </button>
        </form>
        <p v-if="error" class="ally-msg ally-msg--error">{{ error }}</p>
        <p v-if="success" class="ally-msg ally-msg--success">{{ success }}</p>

        <!-- Pending received -->
        <template v-if="pendingReceived.length > 0">
            <div class="ta-divider">
                <span class="ta-divider-label">Pending Requests</span>
                <span class="ta-divider-line"></span>
            </div>
            <div class="ally-rows">
                <div
                    v-for="req in pendingReceived"
                    :key="req.id"
                    class="ta-row"
                >
                    <div class="ally-portrait"></div>
                    <div class="ta-row-body">
                        <div class="ta-row-title">{{ req.user.name }}</div>
                        <div class="ta-row-meta">Wants to be your ally</div>
                    </div>
                    <div class="ally-request-actions">
                        <button
                            class="ta-pill ta-pill--gold ally-action"
                            @click="acceptRequest(req.id)"
                        >
                            ACCEPT
                        </button>
                        <button
                            class="ta-pill ta-pill--claimed ally-action"
                            @click="removeFriendship(req.id)"
                        >
                            DECLINE
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Allies -->
        <template v-if="friends.length > 0">
            <div class="ta-divider">
                <span class="ta-divider-label">Your Allies</span>
                <span class="ta-divider-line"></span>
            </div>
            <div class="ally-rows">
                <div
                    v-for="f in friends"
                    :key="f.id"
                    class="ta-row"
                    @click="showProfileFriend = f"
                >
                    <div
                        class="ally-portrait"
                        :class="{ 'ally-portrait--online': isOnline(f) }"
                    ></div>
                    <div class="ta-row-body">
                        <div class="ta-row-title">
                            {{ f.user.name }}
                            <span
                                v-if="f.league"
                                class="league-badge"
                                :style="{
                                    borderColor: f.league.color,
                                    color: f.league.color,
                                }"
                                >{{ f.league.name }}</span
                            >
                        </div>
                        <div
                            class="ta-row-meta"
                            :class="{ 'ally-status--online': isOnline(f) }"
                        >
                            {{ statusText(f) }}
                        </div>
                    </div>
                    <span
                        class="ta-pill ta-pill--gold ally-action"
                        @click.stop="showProfileFriend = f"
                        >DUEL</span
                    >
                </div>
            </div>
        </template>

        <!-- Sent -->
        <template v-if="pendingSent.length > 0">
            <div class="ta-divider">
                <span class="ta-divider-label">Sent Requests</span>
                <span class="ta-divider-line"></span>
            </div>
            <div class="ally-rows">
                <div v-for="req in pendingSent" :key="req.id" class="ta-row">
                    <div class="ally-portrait"></div>
                    <div class="ta-row-body">
                        <div class="ta-row-title">{{ req.user.name }}</div>
                        <div class="ta-row-meta">Invited · pending</div>
                    </div>
                    <button
                        class="ta-pill ta-pill--claimed ally-action"
                        @click="removeFriendship(req.id)"
                    >
                        CANCEL
                    </button>
                </div>
            </div>
        </template>

        <p
            v-if="
                friends.length === 0 &&
                pendingReceived.length === 0 &&
                pendingSent.length === 0
            "
            class="ally-empty"
        >
            No allies yet. Add someone by username above!
        </p>

        <PlayerProfile
            v-if="showProfileFriend"
            :user-id="showProfileFriend.user.id"
            :friendship-id="showProfileFriend.id"
            @close="showProfileFriend = undefined"
            @removed="onProfileRemoved"
        />
    </TaSubPage>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import axios, { isAxiosError } from "axios";
import TaSubPage from "./TaSubPage.vue";
import PlayerProfile from "./PlayerProfile.vue";

interface FriendUser {
    id: number;
    name?: string;
    level?: number;
}

interface FriendStats {
    wins?: number;
    draws?: number;
    losses?: number;
    last_played?: string;
}

interface FriendLeague {
    tier: number;
    name: string;
    color: string;
}

interface Friendship {
    id: number;
    user: FriendUser;
    stats?: FriendStats;
    last_login_at?: string;
    league?: FriendLeague;
}

const username = ref("");
const showProfileFriend = ref<Friendship>();
const friends = ref<Friendship[]>([]);
const pendingSent = ref<Friendship[]>([]);
const pendingReceived = ref<Friendship[]>([]);
const error = ref("");
const success = ref("");
const sending = ref(false);

// Presentation-only counts derived from real data for the top tiles.
const onlineCount = computed(
    () => friends.value.filter((friend) => isOnline(friend)).length,
);
const pendingCount = computed(
    () => pendingReceived.value.length + pendingSent.value.length,
);

onMounted(async () => {
    await fetchFriends();
});

async function fetchFriends(): Promise<void> {
    try {
        const response = await axios.get<{
            friends: Friendship[];
            pending_sent: Friendship[];
            pending_received: Friendship[];
        }>("/api/friends");
        friends.value = response.data.friends;
        pendingSent.value = response.data.pending_sent;
        pendingReceived.value = response.data.pending_received;
    } catch {
        // silently fail
    }
}

async function sendRequest(): Promise<void> {
    if (!username.value.trim()) {
        return;
    }
    error.value = "";
    success.value = "";
    sending.value = true;
    try {
        await axios.post("/api/friends", { username: username.value.trim() });
        success.value = `Request sent to ${username.value}`;
        username.value = "";
        await fetchFriends();
    } catch (error_) {
        error.value = requestErrorMessage(error_, "Failed to send request");
    }
    sending.value = false;
}

async function acceptRequest(id: number): Promise<void> {
    try {
        await axios.post(`/api/friends/${id}/accept`);
        await fetchFriends();
    } catch (error_) {
        error.value = requestErrorMessage(error_, "Failed to accept");
    }
}

async function removeFriendship(id: number): Promise<void> {
    try {
        await axios.delete(`/api/friends/${id}`);
        await fetchFriends();
    } catch (error_) {
        error.value = requestErrorMessage(error_, "Failed to remove");
    }
}

function onProfileRemoved(): void {
    showProfileFriend.value = undefined;
    void fetchFriends();
}

function requestErrorMessage(error_: unknown, fallback: string): string {
    if (isAxiosError<{ message?: string }>(error_)) {
        return error_.response?.data?.message ?? fallback;
    }
    return fallback;
}

function isOnline(friend: Friendship): boolean {
    if (!friend.last_login_at) {
        return false;
    }
    const last = new Date(friend.last_login_at);
    return Date.now() - last.getTime() < 15 * 60 * 1000; // 15 minutes
}

function statusText(friend: Friendship): string {
    if (isOnline(friend)) {
        return "Online now";
    }
    if (!friend.last_login_at) {
        return `Lv.${friend.user.level ?? 1}`;
    }
    const diff = Date.now() - new Date(friend.last_login_at).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 60) {
        return `Last seen ${mins}m ago`;
    }
    const hours = Math.floor(mins / 60);
    if (hours < 24) {
        return `Last seen ${hours}h ago`;
    }
    const days = Math.floor(hours / 24);
    if (days === 1) {
        return "Last seen yesterday";
    }
    return `Last seen ${days}d ago`;
}
</script>

<style scoped>
/* League tier badge next to an ally's name */
.league-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 7px;
    border: 1px solid currentColor;
    border-radius: 9px;
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    vertical-align: middle;
    opacity: 0.9;
}

/* Count tiles across the top */
.ally-tiles {
    display: flex;
    gap: 9px;
}

.ally-tile {
    flex: 1;
    text-align: center;
    padding: 10px 6px;
    border-radius: 13px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.28);
}

.ally-tile-num {
    font-family: "Cinzel", serif;
    font-size: 17px;
    font-weight: 800;
    color: var(--accent-gold);
}

.ally-tile-num--online {
    color: #8ef0c8;
}

.ally-tile-num--pending {
    color: #9ad0ff;
}

.ally-tile-label {
    font-family: "Cinzel", serif;
    font-size: 8.5px;
    letter-spacing: 1.6px;
    color: #8a7a5a;
    text-transform: uppercase;
}

/* Add-ally form */
.add-ally {
    display: flex;
    gap: 9px;
    margin-top: 14px;
}

.ally-input {
    flex: 1;
    min-width: 0;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.35);
    border-radius: 11px;
    color: var(--text-primary, #f0e0c8);
    font-family: "Crimson Text", Georgia, serif;
    font-size: 15px;
    padding: 9px 12px;
    outline: none;
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}

.ally-input:focus {
    border-color: var(--accent-gold);
    box-shadow: 0 0 8px rgba(240, 192, 80, 0.25);
}

.add-ally-btn {
    cursor: pointer;
    transition: transform 0.12s;
}

.add-ally-btn:active:not(:disabled) {
    transform: translateY(2px);
}

.add-ally-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ally-msg {
    margin-top: 8px;
    font-size: 12px;
}

.ally-msg--error {
    color: #f0a8a0;
}

.ally-msg--success {
    color: #8ef0c8;
}

/* Rows */
.ally-rows {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ta-row {
    cursor: pointer;
}

.ally-portrait {
    position: relative;
    width: 42px;
    height: 42px;
    flex: none;
    border-radius: 50%;
    border: 2px solid rgba(240, 192, 80, 0.35);
    background-image: url("/images/character.png");
    background-size: cover;
    background-position: center 18%;
}

.ally-portrait--online {
    border-color: #8ef0c8;
    box-shadow: 0 0 8px rgba(142, 240, 200, 0.4);
}

.ally-status--online {
    color: #8ef0c8;
}

.ally-action {
    cursor: pointer;
    transition: transform 0.12s;
}

.ally-action:active {
    transform: translateY(2px);
}

.ally-request-actions {
    display: flex;
    flex: none;
    gap: 6px;
}

.ally-empty {
    margin-top: 20px;
    text-align: center;
    color: var(--text-secondary, #9a8a68);
    font-style: italic;
    padding: 15px 0;
}

@media (max-width: 768px) {
    .ally-request-actions {
        flex-direction: column;
    }
}
</style>
