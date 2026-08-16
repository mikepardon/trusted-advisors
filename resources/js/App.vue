<template>
    <div id="game-app" :class="{ 'is-admin': isAdmin }">
        <!-- Open in App banner (iOS Safari only) -->
        <div v-if="showAppBanner" class="app-banner">
            <img
                src="/images/logo.png"
                alt="Trusted Advisors"
                class="app-banner-icon"
            />
            <div class="app-banner-text">
                <strong>Trusted Advisors</strong>
                <span>Open in the app for a better experience</span>
            </div>
            <a :href="appDeepLink" class="app-banner-open">OPEN</a>
            <button class="app-banner-close" @click="dismissAppBanner">
                &times;
            </button>
        </div>

        <!-- Impersonation banner -->
        <div
            v-if="auth.state.user?.is_impersonating"
            class="impersonation-banner"
            @click="stopImpersonating"
        >
            Impersonating {{ auth.state.user.name }} &mdash; Click to stop
        </div>

        <SplashScreen v-if="showSplash" @done="splashDone" />

        <!-- Boot gate: hold the shell until auth resolves so the nav/header don't
         pop in after first paint and reflow every page (jarring resize). -->
        <div v-if="auth.state.loading" class="app-boot">
            <div class="app-boot-spinner"></div>
        </div>

        <!-- War-table header for non-game, non-admin pages -->
        <header
            v-if="
                !auth.state.loading &&
                !isAdmin &&
                !isGamePage &&
                auth.state.user
            "
            class="game-header"
        >
            <div class="ta-header">
                <div
                    class="ta-header-player"
                    @click="
                        navSound();
                        $router.push('/profile');
                    "
                >
                    <div class="ta-avatar" :style="{ background: xpRingConic }">
                        <div class="ta-avatar-face">{{ playerLevel }}</div>
                    </div>
                </div>
                <div
                    class="ta-header-id"
                    @click="
                        navSound();
                        $router.push('/profile');
                    "
                >
                    <div class="ta-header-name">{{ auth.state.user.name }}</div>
                    <div class="ta-header-xp">
                        {{ auth.state.user.active_title || "The Newbie" }}
                    </div>
                </div>
                <div class="ta-header-right">
                    <div class="ta-header-top-row">
                        <span
                            class="ta-streak"
                            :class="{ 'ta-streak--cold': streakDays === 0 }"
                            @click="openStreak"
                        >
                            <span class="ta-streak-flame">&#128293;</span
                            >{{ streakDays }}
                        </span>
                        <button
                            class="ta-bell"
                            :class="{ 'ta-bell--open': showNotifications }"
                            aria-label="Notifications"
                            @click="showNotifications = true"
                        >
                            <span>&#9993;</span>
                            <span
                                v-if="notifCount > 0"
                                class="ta-dot ta-bell-dot"
                            ></span>
                        </button>
                        <div class="ta-menu-wrap">
                            <button
                                class="ta-bell ta-menu-btn"
                                :class="{ 'ta-bell--open': showMenuPopup }"
                                aria-label="Menu"
                                @click.stop="showMenuPopup = !showMenuPopup"
                            >
                                &#9776;
                            </button>
                            <div
                                v-if="showMenuPopup"
                                class="menu-popup ta-menu-popup"
                            >
                                <router-link
                                    to="/collection"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Collection</router-link
                                >
                                <router-link
                                    to="/season-pass"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Season Pass</router-link
                                >
                                <router-link
                                    to="/league"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >League</router-link
                                >
                                <router-link
                                    to="/stats"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Stats</router-link
                                >
                                <router-link
                                    v-if="auth.state.user?.tournaments_enabled"
                                    to="/tournaments"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Tournaments</router-link
                                >
                                <router-link
                                    v-if="
                                        auth.state.user?.payments_enabled &&
                                        !auth.state.user?.is_premium
                                    "
                                    to="/premium"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Go Premium</router-link
                                >
                                <div class="menu-popup-divider"></div>
                                <button
                                    class="menu-popup-item"
                                    @click="
                                        showMenuPopup = false;
                                        showHowToPlay = true;
                                    "
                                >
                                    Rules
                                </button>
                                <button
                                    class="menu-popup-item"
                                    @click="
                                        showMenuPopup = false;
                                        showTutorial = true;
                                    "
                                >
                                    Tutorial
                                </button>
                                <router-link
                                    to="/settings"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Settings</router-link
                                >
                                <router-link
                                    v-if="auth.state.user?.is_admin"
                                    to="/admin"
                                    class="menu-popup-item"
                                    @click="showMenuPopup = false"
                                    >Admin</router-link
                                >
                                <div class="menu-popup-divider"></div>
                                <button
                                    class="menu-popup-item menu-popup-logout"
                                    @click="
                                        showMenuPopup = false;
                                        showLogoutConfirm = true;
                                    "
                                >
                                    Logout
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="ta-header-currencies">
                        <span
                            class="ta-currency"
                            @click="
                                navSound();
                                $router.push('/shop');
                            "
                            >&#9673; {{ auth.state.user.coins ?? 0 }}</span
                        >
                        <span
                            class="ta-currency ta-currency--league"
                            @click="
                                navSound();
                                $router.push('/league');
                            "
                            >&#128737;
                            {{ auth.state.user.league_points ?? 0 }}</span
                        >
                    </div>
                </div>
            </div>
        </header>

        <main v-if="!auth.state.loading" :style="mainBgStyle">
            <div v-if="gameBgUrl && isGamePage" class="main-bg-overlay"></div>
            <router-view v-slot="{ Component }">
                <transition name="page" mode="out-in">
                    <component :is="Component" :key="$route.path" />
                </transition>
            </router-view>
        </main>

        <HowToPlay v-if="showHowToPlay" @close="showHowToPlay = false" />
        <NotificationsDrawer
            :open="showNotifications"
            @close="showNotifications = false"
            @update:count="notifCount = $event"
        />

        <!-- Streak toast -->
        <transition name="toast-fade">
            <div v-if="streakToast" class="streak-toast">
                <span class="streak-fire">&#128293;</span>
                <span class="streak-text"
                    >{{ streakToast.streak }}-day streak! +{{
                        streakToast.xp
                    }}
                    XP</span
                >
            </div>
        </transition>

        <TutorialOverlay v-if="showTutorial" @close="showTutorial = false" />
        <ToastContainer />

        <DailyRewardModal
            v-if="dailyReward.state.modalOpen && dailyReward.state.status"
            :day="dailyReward.state.status.day"
            :streak="dailyReward.state.status.streak"
            :ladder="dailyReward.state.status.ladder"
            :reward="dailyReward.state.status.reward"
            :available="dailyReward.state.status.available"
            @close="dailyReward.closeModal()"
            @claimed="onDailyRewardClaimed"
        />

        <LeagueResultModal
            v-if="leagueResult.state.status"
            :rank="leagueResult.state.status.rank"
            :total="leagueResult.state.status.total"
            :coins-earned="leagueResult.state.status.coins_earned"
            :promoted="leagueResult.state.status.promoted"
            :demoted="leagueResult.state.status.demoted"
            :tier-before="leagueResult.state.status.tier_before"
            :tier-after="leagueResult.state.status.tier_after"
            @close="leagueResult.markSeen()"
        />

        <RewardReveal />

        <ConfirmModal
            :visible="showLogoutConfirm"
            title="Logout"
            message="Are you sure you want to log out?"
            confirm-text="Logout"
            :dangerous="true"
            @confirm="handleLogout"
            @cancel="showLogoutConfirm = false"
        />
    </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import ConfirmModal from "./components/ConfirmModal.vue";
import HowToPlay from "./components/HowToPlay.vue";
import NotificationsDrawer from "./components/NotificationsDrawer.vue";
import SplashScreen from "./components/SplashScreen.vue";
import ToastContainer from "./components/ToastContainer.vue";
import TutorialOverlay from "./components/TutorialOverlay.vue";
import DailyRewardModal from "./components/DailyRewardModal.vue";
import LeagueResultModal from "./components/LeagueResultModal.vue";
import RewardReveal from "./components/RewardReveal.vue";
import { useAuth } from "./stores/auth";
import type { StreakNotification } from "./stores/auth";
import { useToast } from "./stores/toast";
import { useDailyReward } from "./stores/daily-reward";
import { useLeagueResult } from "./stores/league-result";
import { useReward } from "./stores/reward";
import { playSound } from "./sounds";
import { initOneSignal, promptPushPermission } from "./onesignal";
import { xpForLevel } from "./xp";

interface SiteSettings {
    homepage_background_url?: string;
    classic_game_background_url?: string;
    duel_game_background_url?: string;
    hub_center_image_url?: string;
}

interface GameInvitesResponse {
    length: number;
}

interface FriendsResponse {
    pending_received?: unknown[];
}

interface UnreadCountResponse {
    count?: number;
}

interface PremiumStatusChangedEvent {
    is_premium: boolean;
}

interface EchoChannel {
    listen: (
        event: string,
        callback: (data: PremiumStatusChangedEvent) => void,
    ) => EchoChannel;
    stopListening: (event: string) => void;
}

interface EchoInstance {
    private: (channel: string) => EchoChannel;
}

const auth = useAuth();
const toast = useToast();
const dailyReward = useDailyReward();
const leagueResult = useLeagueResult();
const reward = useReward();
const router = useRouter();
const route = useRoute();

const isIosSafari =
    /iPhone|iPad/.test(navigator.userAgent) &&
    /Safari/.test(navigator.userAgent) &&
    !navigator.userAgent.includes("wtn") &&
    !navigator.userAgent.includes("WebToNative");

const showAppBanner = ref(
    isIosSafari && !localStorage.getItem("app_banner_dismissed"),
);
const showSplash = ref(false);
const showHowToPlay = ref(false);
const showNotifications = ref(false);
const showMenuPopup = ref(false);
const showLogoutConfirm = ref(false);
const notifCount = ref(0);
const streakToast = ref<StreakNotification | undefined>(undefined);
const showTutorial = ref(false);

const homepageBgUrl = ref<string | undefined>(undefined);
const classicGameBgUrl = ref<string | undefined>(undefined);
const duelGameBgUrl = ref<string | undefined>(undefined);
const hubCenterImageUrl = ref<string | undefined>(undefined);
const activeGameType = ref<string | undefined>(undefined);

const notifChannel = ref<EchoChannel | undefined>(undefined);
const streakToastTimer = ref<ReturnType<typeof setTimeout> | undefined>(
    undefined,
);
const closeMenuOnClick = ref<(() => void) | undefined>(undefined);

provide("openNotifications", () => {
    showNotifications.value = true;
});
provide("openRules", () => {
    showHowToPlay.value = true;
});
provide("openTutorial", () => {
    showTutorial.value = true;
});
provide("setActiveGameType", (type: string | undefined) => {
    activeGameType.value = type;
});
// The home hub's war-table centre art, admin-configurable via Site Appearance.
provide("hubCenterImageUrl", hubCenterImageUrl);

function getEcho(): EchoInstance | undefined {
    return (window as unknown as { Echo?: EchoInstance }).Echo;
}

const appDeepLink = computed(() => "ta://open" + route.fullPath);

const isAdmin = computed(() => route.path.startsWith("/admin"));

const xpProgress = computed(() => {
    const user = auth.state.user;
    if (!user) {
        return 0;
    }
    const level = user.level ?? 1;
    const xp = user.xp ?? 0;
    const currentLevelXp = xpForLevel(level);
    const nextLevelXp = xpForLevel(level + 1);
    const range = nextLevelXp - currentLevelXp;
    if (range <= 0) {
        return 1;
    }
    return Math.min(Math.max((xp - currentLevelXp) / range, 0), 1);
});

const playerLevel = computed(() => auth.state.user?.level ?? 1);

// Gold arc of the avatar ring fills clockwise with XP progress.
const xpRingConic = computed(() => {
    const pct = Math.round(xpProgress.value * 100);
    return `conic-gradient(#f0c050 0 ${pct}%, rgba(255,255,255,.12) ${pct}% 100%)`;
});

const streakDays = computed(() => auth.state.user?.daily_reward_streak ?? 0);

function openStreak(): void {
    playSound("clickNav");
    dailyReward.openModal();
}

const isGamePage = computed(() => /^\/game\/\d+(\/.*)?$/.test(route.path));

const gameBgUrl = computed(() => {
    // The redesigned board is flush with the dark war-table ground by default;
    // the per-game background image is opt-in via Settings.
    if (
        !isGamePage.value ||
        localStorage.getItem("game_bg_enabled") !== "true"
    ) {
        return undefined;
    }
    return activeGameType.value === "duel"
        ? duelGameBgUrl.value
        : classicGameBgUrl.value;
});

const mainBgStyle = computed(() => {
    // Home is flush dark (continuous with the header); only in-game pages carry
    // a background image.
    const bgUrl = isGamePage.value ? gameBgUrl.value : undefined;
    if (!bgUrl) {
        return {};
    }
    return {
        backgroundImage: `url(${bgUrl})`,
        backgroundSize: "cover",
        backgroundPosition: "center",
        backgroundAttachment: "scroll",
        position: "relative",
    };
});

watch(
    () => auth.state.streakNotification,
    (value) => {
        if (!value) {
            return;
        }

        streakToast.value = value;
        auth.state.streakNotification = undefined;
        streakToastTimer.value = setTimeout(() => {
            streakToast.value = undefined;
        }, 4000);
    },
);

watch(
    () => route.path,
    () => {
        if (!isGamePage.value) {
            activeGameType.value = undefined;
        }
    },
);

function dismissAppBanner(): void {
    showAppBanner.value = false;
    localStorage.setItem("app_banner_dismissed", "1");
}

function splashDone(): void {
    showSplash.value = false;
    // Client-only dismissal flag (matches app_banner_dismissed above); nothing
    // reads splash_seen server-side, so localStorage replaces the old cookie.
    localStorage.setItem("splash_seen", "1");
}

function navSound(): void {
    playSound("clickNav");
}

async function stopImpersonating(): Promise<void> {
    try {
        await axios.post("/api/impersonate/stop");
        window.location.assign("/admin");
    } catch {
        toast.error("Failed to stop impersonating");
    }
}

function stopListeningNotifChannel(): void {
    if (!notifChannel.value) {
        return;
    }
    notifChannel.value.stopListening("GameInviteReceived");
    notifChannel.value.stopListening("FriendRequestReceived");
    notifChannel.value.stopListening("UserNotificationReceived");
    notifChannel.value.stopListening("PremiumStatusChanged");
}

async function handleLogout(): Promise<void> {
    showLogoutConfirm.value = false;
    showMenuPopup.value = false;
    if (notifChannel.value) {
        stopListeningNotifChannel();
        notifChannel.value = undefined;
    }
    await auth.logout();
    notifCount.value = 0;
    if (route.path === "/") {
        window.location.reload();
    } else {
        void router.replace("/");
    }
}

async function fetchDailyReward(): Promise<void> {
    if (!auth.state.user) {
        return;
    }
    // No auto-pop: the home hero surfaces the claimable reward as a persistent CTA.
    await dailyReward.fetchStatus();
}

function onDailyRewardClaimed(coins: number): void {
    dailyReward.markClaimed(dailyReward.state.status?.streak ?? 0);
    dailyReward.closeModal();
    reward.reveal({ amount: coins, title: "Daily Reward", icon: "🎁" });
}

async function fetchNotifCount(): Promise<void> {
    if (!auth.state.user) {
        return;
    }
    try {
        const [invitesResponse, friendsResponse, unreadResponse] =
            await Promise.all([
                axios.get<GameInvitesResponse>("/api/game-invites/pending"),
                axios.get<FriendsResponse>("/api/friends"),
                axios.get<UnreadCountResponse>(
                    "/api/notifications/unread-count",
                ),
            ]);
        const invites = invitesResponse.data?.length || 0;
        const friends = friendsResponse.data?.pending_received?.length || 0;
        const databaseUnread = unreadResponse.data?.count || 0;
        notifCount.value = invites + friends + databaseUnread;
    } catch {
        // ignore
    }
}

function subscribeNotifChannel(): void {
    const userId = auth.state.user?.id;
    const echo = getEcho();
    if (!userId || !echo) {
        return;
    }
    notifChannel.value = echo
        .private(`user.${userId}`)
        .listen("GameInviteReceived", () => {
            notifCount.value++;
        })
        .listen("FriendRequestReceived", () => {
            notifCount.value++;
        })
        .listen("UserNotificationReceived", () => {
            notifCount.value++;
        })
        .listen("PremiumStatusChanged", (data) => {
            auth.updateUserStats({ is_premium: data.is_premium });
        });
}

async function fetchSiteSettings(): Promise<void> {
    // Fetch site settings (homepage background)
    try {
        const response = await axios.get<SiteSettings>("/api/site-settings");
        homepageBgUrl.value =
            response.data?.homepage_background_url || undefined;
        classicGameBgUrl.value =
            response.data?.classic_game_background_url || undefined;
        duelGameBgUrl.value =
            response.data?.duel_game_background_url || undefined;
        hubCenterImageUrl.value =
            response.data?.hub_center_image_url || undefined;
    } catch {
        // ignore
    }
}

onMounted(() => {
    void fetchSiteSettings();
    // fetchUser() is already called in app.js router guard; just wait for it
    let checkAttempts = 0;
    const check = (): void => {
        if (!auth.state.loading && auth.state.user) {
            // Auto-show tutorial for first-time visitors (after login)
            if (!localStorage.getItem("has_seen_tutorial")) {
                showTutorial.value = true;
                localStorage.setItem("has_seen_tutorial", "1");
            }
            void fetchNotifCount();
            void fetchDailyReward();
            void leagueResult.fetchStatus();
            subscribeNotifChannel();
            void initOneSignal().then(() => promptPushPermission());
        } else if (auth.state.loading && checkAttempts < 300) {
            checkAttempts++;
            setTimeout(check, 50);
        }
    };
    check();
    closeMenuOnClick.value = (): void => {
        showMenuPopup.value = false;
    };
    document.addEventListener("click", closeMenuOnClick.value);
});

onBeforeUnmount(() => {
    stopListeningNotifChannel();
    if (closeMenuOnClick.value) {
        document.removeEventListener("click", closeMenuOnClick.value);
    }
    if (streakToastTimer.value !== undefined) {
        clearTimeout(streakToastTimer.value);
    }
});
</script>

<style>
:root {
    --bg-primary: #0d0a06;
    --bg-secondary: #1e160c;
    --bg-card: #2a1f14;
    --accent-gold: #f0c050;
    --accent-gold-bright: #ffe070;
    --accent-red: #d04030;
    --accent-green: #5cb85c;
    --accent-green-dark: #3a8a2a;
    --accent-blue: #3a8ad4;
    --text-primary: #f0e0c8;
    --text-secondary: #b8a07a;
    --text-bright: #fff5e0;
    --border-gold: #c8952e;
    --wood-light: #463220;
    --wood-medium: #342618;
    --wood-dark: #1e160c;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Slightly smaller base type across the whole app (scales all rem sizing). */
html {
    font-size: 15px;
}

body {
    background: radial-gradient(
        ellipse at 50% 34%,
        #3a2a18 0%,
        #150f08 62%,
        #0b0805 100%
    );
    color: var(--text-primary);
    font-family: "Crimson Text", Georgia, serif;
    height: 100dvh;
    overflow: hidden;
}

#game-app {
    display: flex;
    flex-direction: column;
    height: 100dvh;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0;
}

#game-app > main {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    /* Clip horizontal overflow so a wide child (e.g. the effect-cards advisor
     * carousel, which renders with overflow:visible) can never widen the app
     * past the viewport and push the header/content off-screen. */
    overflow-x: clip;
    /* Reserve the scrollbar gutter so content width doesn't jump when a
     * scrollbar appears as async content loads. */
    scrollbar-gutter: stable;
    padding: 12px;
    -webkit-overflow-scrolling: touch;
}

/* Boot gate loader — fills the shell while auth resolves */
.app-boot {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.app-boot-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(212, 168, 67, 0.25);
    border-top-color: var(--accent-gold, #d4a843);
    border-radius: 50%;
    animation: app-boot-spin 0.8s linear infinite;
}

@keyframes app-boot-spin {
    to {
        transform: rotate(360deg);
    }
}

/* ---- War-table header (non-game pages) ---- */
.game-header {
    position: relative;
    padding: 10px 16px;
    flex-shrink: 0;
    /* Above the hub/page content (z-index:1) so the ☰ menu drops over it. */
    z-index: 60;
}

.ta-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ta-header-player {
    flex: none;
    cursor: pointer;
}

.ta-avatar {
    position: relative;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}

.ta-header-player:hover .ta-avatar {
    transform: scale(1.05);
}

.ta-avatar-face {
    width: 41px;
    height: 41px;
    border-radius: 50%;
    background: #100b06;
    border: 1px solid rgba(240, 192, 80, 0.5);
    color: var(--accent-gold);
    font-family: "Cinzel", serif;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ta-header-id {
    flex: 1;
    min-width: 0;
    cursor: pointer;
}

.ta-header-name {
    font-family: "Cinzel", serif;
    font-size: 13.5px;
    font-weight: 700;
    letter-spacing: 0.6px;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ta-header-xp {
    font-size: 11.5px;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ta-header-right {
    flex: none;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}

.ta-header-top-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ta-streak {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    height: 33px;
    box-sizing: border-box;
    font-family: "Cinzel", serif;
    font-size: 12.5px;
    font-weight: 800;
    color: #ffcf9a;
    background: linear-gradient(
        180deg,
        rgba(208, 64, 48, 0.5),
        rgba(0, 0, 0, 0.5)
    );
    border: 1px solid rgba(255, 140, 80, 0.55);
    border-radius: 999px;
    padding: 4px 9px;
    box-shadow: 0 0 14px rgba(226, 84, 44, 0.3);
    cursor: pointer;
}

.ta-streak--cold {
    color: var(--text-secondary);
    background: rgba(0, 0, 0, 0.45);
    border-color: rgba(138, 106, 46, 0.4);
    box-shadow: none;
}

.ta-streak-flame {
    font-size: 14px;
    line-height: 1;
    filter: drop-shadow(0 0 5px rgba(255, 140, 60, 0.85));
}

.ta-streak--cold .ta-streak-flame {
    filter: grayscale(0.7) opacity(0.6);
}

.ta-bell {
    position: relative;
    width: 33px;
    height: 33px;
    flex: none;
    border-radius: 11px;
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid rgba(240, 192, 80, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    color: #f0dcb0;
    cursor: pointer;
    padding: 0;
    transition:
        background 0.2s,
        border-color 0.2s;
}

.ta-bell:hover,
.ta-bell--open {
    background: rgba(240, 192, 80, 0.22);
    border-color: var(--accent-gold);
}

.ta-bell-dot {
    position: absolute;
    top: -3px;
    right: -3px;
}

.ta-header-currencies {
    display: flex;
    gap: 6px;
}

.ta-currency {
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: "Cinzel", serif;
    font-size: 12px;
    font-weight: 700;
    color: var(--accent-gold);
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid rgba(240, 192, 80, 0.25);
    border-radius: 999px;
    padding: 4px 9px;
    white-space: nowrap;
    cursor: pointer;
    transition: border-color 0.2s;
}

.ta-currency:hover {
    border-color: var(--accent-gold);
}

.ta-currency--league {
    color: #e8dcc0;
}

.subtitle {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 1.1rem;
    margin-top: 8px;
}

.header-user {
    display: flex;
    gap: 10px;
    justify-content: center;
    align-items: center;
    margin-top: 10px;
}

.user-greeting {
    color: var(--accent-gold);
    font-family: "Cinzel", serif;
    font-size: 0.9rem;
    letter-spacing: 1px;
}

.header-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    align-items: center;
    margin-top: 10px;
}

.htp-btn {
    background: none;
    border: 1px solid rgba(138, 106, 46, 0.4);
    color: var(--text-secondary);
    font-family: "Crimson Text", Georgia, serif;
    font-size: 0.85rem;
    padding: 4px 14px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 0;
}

.htp-btn:hover {
    color: var(--accent-gold);
    border-color: var(--accent-gold);
    background: rgba(212, 168, 67, 0.08);
    box-shadow: none;
    transform: none;
}

/* Stop buttons and hub art selecting/highlighting on tap or drag. */
button {
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}

/* The app's default chunky "wood" button look. Wrapped in :where() so its
 * specificity is zero — any component or ta-* utility style overrides it,
 * while unstyled legacy buttons still pick it up. */
:where(button:not(.nav-item):not(.mobile-menu-item):not(.hub-btn)) {
    font-family: "Cinzel", serif;
    background: linear-gradient(
        180deg,
        var(--wood-light),
        var(--wood-medium),
        var(--wood-dark)
    );
    color: var(--accent-gold);
    border: 2px solid var(--border-gold);
    padding: 10px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.15s;
    letter-spacing: 1px;
    border-radius: 10px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

@media (hover: hover) {
    :where(
        button:not(.nav-item):not(.mobile-menu-item):not(.hub-btn):hover:not(
                :disabled
            ):not(.active)
    ) {
        background: linear-gradient(
            180deg,
            #4a3a24,
            var(--wood-light),
            var(--wood-medium)
        );
        box-shadow:
            0 4px 0 #1a1006,
            0 0 15px rgba(240, 192, 80, 0.25),
            inset 0 1px 0 rgba(255, 220, 140, 0.2);
        border-color: var(--accent-gold);
    }
}

:where(
    button:not(.nav-item):not(.mobile-menu-item):not(.hub-btn):active:not(
            :disabled
        )
) {
    transform: translateY(3px);
    box-shadow:
        0 1px 0 #1a1006,
        inset 0 1px 0 rgba(255, 220, 140, 0.15);
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    background: linear-gradient(
        180deg,
        var(--accent-gold-bright),
        var(--accent-gold),
        #b8842a
    );
    color: #1e160c;
    font-weight: 700;
    border-color: var(--accent-gold);
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.15);
    box-shadow:
        0 4px 0 #7a5a14,
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
}

.btn-primary:hover:not(:disabled) {
    background: linear-gradient(
        180deg,
        #fff0a0,
        var(--accent-gold-bright),
        var(--accent-gold)
    );
    box-shadow:
        0 4px 0 #7a5a14,
        0 0 20px rgba(240, 192, 80, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    color: #444;
}

.btn-primary:active:not(:disabled) {
    transform: translateY(3px);
    box-shadow:
        0 1px 0 #7a5a14,
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.btn-danger {
    background: linear-gradient(180deg, #e04838, #d04030, #a03020);
    color: #fff;
    border-color: #b03828;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    box-shadow:
        0 4px 0 #701810,
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.btn-danger:hover:not(:disabled) {
    background: linear-gradient(180deg, #f05848, #e04838, #b03828);
    box-shadow:
        0 4px 0 #701810,
        0 0 15px rgba(208, 64, 48, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.btn-danger:active:not(:disabled) {
    transform: translateY(3px);
    box-shadow:
        0 1px 0 #701810,
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.btn-success {
    border-color: var(--accent-green);
    color: var(--accent-green);
}

.card-panel {
    background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
    border: 2px solid var(--border-gold);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow:
        0 4px 0 rgba(0, 0, 0, 0.3),
        0 6px 24px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(255, 220, 140, 0.08);
}

.admin-link {
    display: inline-block;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.85rem;
    padding: 4px 12px;
    border: 1px solid rgba(138, 106, 46, 0.3);
    border-radius: 4px;
    transition: all 0.2s;
}

.admin-link:hover {
    color: var(--accent-gold);
    border-color: var(--accent-gold);
}

#game-app.is-admin {
    max-width: none;
    padding: 0;
}

/* ---- Compact in-game toolbar ---- */
.game-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 12px;
    border-bottom: 1px solid var(--border-gold);
    background: linear-gradient(
        180deg,
        rgba(42, 31, 20, 0.6) 0%,
        var(--bg-primary) 100%
    );
    flex-shrink: 0;
}

.toolbar-logo {
    height: 28px;
    width: auto;
    cursor: pointer;
    filter: drop-shadow(0 1px 4px rgba(212, 168, 67, 0.3));
    transition: transform 0.2s;
}

.toolbar-logo:hover {
    transform: scale(1.05);
}

.toolbar-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    font-size: 1rem;
    background: none;
    border: 1px solid rgba(138, 106, 46, 0.4);
    border-radius: 6px;
    color: var(--text-secondary);
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    letter-spacing: 0;
}

.toolbar-btn:hover {
    color: var(--accent-gold);
    border-color: var(--accent-gold);
    background: rgba(212, 168, 67, 0.08);
    transform: none;
    box-shadow: none;
}

/* ---- Bottom navigation ---- */
.bottom-nav {
    display: flex;
    justify-content: space-around;
    align-items: center;
    border-top: 1px solid var(--border-gold);
    background: linear-gradient(
        180deg,
        var(--wood-light),
        var(--wood-medium),
        var(--wood-dark)
    );
    padding: 8px 0;
    padding-bottom: calc(8px + env(safe-area-inset-bottom));
    flex-shrink: 0;
    box-shadow:
        0 -3px 12px rgba(0, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 220, 140, 0.08);
    position: relative;
    z-index: 1;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 4px 0;
    background: none;
    border: none;
    border-radius: 0;
    color: var(--text-secondary);
    text-decoration: none;
    cursor: pointer;
    transition: color 0.2s;
    font-family: "Cinzel", serif;
    letter-spacing: 0;
    box-shadow: none;
    text-shadow: none;
}

.nav-item:hover {
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
    background: none;
}

.nav-item:hover .nav-icon {
    background: rgba(240, 192, 80, 0.08);
    border-color: rgba(200, 149, 46, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(240, 192, 80, 0.15);
}

.nav-item.active {
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
    background: none;
}

.nav-icon {
    position: relative;
    font-size: 1.15rem;
    line-height: 1;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.2);
    border: 2px solid transparent;
    transition: all 0.2s;
}

.nav-item.active .nav-icon {
    border-color: var(--accent-gold);
    background: rgba(240, 192, 80, 0.1);
    box-shadow: 0 0 10px rgba(240, 192, 80, 0.25);
}

.nav-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: linear-gradient(180deg, #e74c3c, #c0392b);
    color: #fff;
    font-size: 0.55rem;
    font-family: "Cinzel", serif;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    line-height: 16px;
    text-align: center;
    border-radius: 8px;
    padding: 0 3px;
    border: 2px solid var(--wood-dark, #1a1209);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
}

.nav-badge-dot {
    min-width: 12px;
    width: 12px;
    height: 12px;
    padding: 0;
    border-radius: 50%;
}

.nav-label {
    font-size: 0.6rem;
    font-weight: 00;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: none;
}

/* ---- Notification badge ---- */
.notif-icon-wrap {
    position: relative;
    display: inline-block;
}

.notif-badge {
    position: absolute;
    top: -6px;
    right: -10px;
    background: #e74c3c;
    color: #fff;
    font-size: 0.55rem;
    font-family: "Cinzel", serif;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    line-height: 16px;
    text-align: center;
    border-radius: 8px;
    padding: 0 4px;
}

/* ---- Coin display ---- */
.nav-coins {
    display: flex;
    align-items: center;
    gap: 4px;
    font-family: "Cinzel", serif;
    font-size: 0.8rem;
    color: var(--accent-gold);
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 12px;
    background: rgba(212, 168, 67, 0.1);
    border: 1px solid rgba(138, 106, 46, 0.3);
    transition: all 0.2s;
    white-space: nowrap;
}

.nav-coins:hover {
    background: rgba(212, 168, 67, 0.2);
    border-color: var(--accent-gold);
}

/* ---- Menu popup ---- */
.nav-menu-wrap {
    position: relative;
}

.menu-popup {
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 8px;
    background: var(--bg-secondary);
    border: 2px solid var(--border-gold);
    border-radius: 8px;
    padding: 6px 0;
    z-index: 100;
    min-width: 140px;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
}

.menu-popup-item {
    display: block;
    width: 100%;
    padding: 10px 18px;
    background: none;
    border: none;
    color: var(--text-primary);
    font-family: "Cinzel", serif;
    font-size: 0.9rem;
    text-align: left;
    cursor: pointer;
    text-decoration: none;
    transition:
        background 0.2s,
        color 0.2s;
    letter-spacing: 0;
}

.menu-popup-item:hover {
    background: rgba(212, 168, 67, 0.1);
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
}

.menu-popup-divider {
    height: 1px;
    background: rgba(138, 106, 46, 0.3);
    margin: 4px 0;
}

.menu-popup-logout {
    color: var(--accent-red);
}

.menu-popup-logout:hover {
    background: rgba(160, 48, 32, 0.15);
    color: #d05040;
}

/* Header menu: anchor the popup below the ☰ button (not above like the old nav) */
.ta-menu-wrap {
    position: relative;
}

.ta-menu-btn {
    font-size: 15px;
    line-height: 1;
}

.ta-menu-popup {
    bottom: auto;
    top: 100%;
    margin-bottom: 0;
    margin-top: 8px;
    min-width: 170px;
    max-height: min(70vh, 460px);
    overflow-y: auto;
    scrollbar-width: none;
    background: linear-gradient(
        180deg,
        rgba(30, 22, 13, 0.99),
        rgba(13, 9, 5, 0.99)
    );
    border-color: rgba(240, 192, 80, 0.45);
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.7);
}

.ta-menu-popup::-webkit-scrollbar {
    display: none;
}

.ta-menu-popup .menu-popup-item {
    padding: 9px 16px;
    font-size: 0.85rem;
}

/* ---- Streak toast ---- */
.streak-toast {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
    border: 3px solid var(--accent-gold);
    border-radius: 12px;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 9999;
    box-shadow:
        0 4px 0 rgba(0, 0, 0, 0.3),
        0 6px 24px rgba(240, 192, 80, 0.4);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
}

.streak-fire {
    font-size: 1.8rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.4));
}

.streak-text {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.05rem;
    font-weight: 700;
    white-space: nowrap;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
}

.toast-fade-enter-active {
    transition:
        opacity 0.4s ease,
        transform 0.4s ease;
}

.toast-fade-leave-active {
    transition:
        opacity 0.6s ease,
        transform 0.6s ease;
}

.toast-fade-enter-from {
    opacity: 0;
    transform: translateX(-50%) translateY(-20px);
}

.toast-fade-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-10px);
}

/* Open in App banner */
.app-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
    border-bottom: 1px solid var(--border-gold);
    flex-shrink: 0;
    z-index: 100;
}

.app-banner-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    object-fit: contain;
}

.app-banner-text {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.app-banner-text strong {
    font-family: "Cinzel", serif;
    font-size: 0.85rem;
    color: var(--text-primary);
}

.app-banner-text span {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.app-banner-open {
    font-family: "Cinzel", serif;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--accent-gold);
    background: none;
    border: 1px solid var(--accent-gold);
    border-radius: 14px;
    padding: 5px 14px;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s;
}

.app-banner-open:hover {
    background: rgba(240, 192, 80, 0.1);
}

.app-banner-close {
    background: none !important;
    border: none !important;
    color: var(--text-secondary);
    font-size: 1.3rem;
    padding: 0 4px !important;
    cursor: pointer;
    line-height: 1;
    box-shadow: none !important;
}

/* Impersonation banner */
.impersonation-banner {
    background: #c0392b;
    color: #fff;
    text-align: center;
    padding: 8px 16px;
    font-family: "Cinzel", serif;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 1px;
    cursor: pointer;
    flex-shrink: 0;
    z-index: 9999;
}

.impersonation-banner:hover {
    background: #e74c3c;
}

/* Homepage background overlay */
.main-bg-overlay {
    position: fixed;
    inset: 0;
    background: rgba(13, 10, 6, 0.65);
    pointer-events: none;
    z-index: 0;
}

/* The victory canvas is a fixed full-screen overlay; exclude it so this rule's
   position:relative / z-index:1 doesn't trap it in the main stacking context. */
#game-app > main > :not(.main-bg-overlay):not(.victory-canvas) {
    position: relative;
    z-index: 1;
}

/* Page transitions */
.page-enter-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.page-leave-active {
    transition: opacity 0.15s ease;
}
.page-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.page-leave-to {
    opacity: 0;
}
</style>
