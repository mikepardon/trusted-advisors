<template>
    <TaSubPage title="Profile" back="/">
        <div class="profile-page">
            <div class="card-panel">
                <div class="profile-info">
                    <div class="profile-avatar" :class="frameClass">
                        <LeagueCrest
                            :variant="crestStyle"
                            :tier="crestColour"
                        />
                    </div>
                    <div class="profile-details">
                        <h3 class="profile-name">
                            {{ auth.state.user?.name }}
                            <span
                                v-if="auth.state.user?.is_premium"
                                class="premium-badge-inline"
                                title="Premium"
                                >&#9733;</span
                            >
                        </h3>
                        <p class="profile-title">
                            {{ auth.state.user?.active_title || "The Newbie" }}
                        </p>
                        <p class="profile-joined">
                            Level {{ gameStats.level || 1 }} Advisor
                        </p>
                    </div>
                </div>

                <!-- Cosmetics: each slot shows the current pick + a Change button
                     that opens the collection picker. -->
                <div class="cosmetics-slots">
                    <div class="cos-slot">
                        <span class="cos-label">Nameplate</span>
                        <span class="cos-current">{{
                            auth.state.user?.active_title || "The Newbie"
                        }}</span>
                        <button class="cos-change" @click="openPicker('title')">
                            Change
                        </button>
                    </div>
                    <div class="cos-slot">
                        <span class="cos-label">Crest</span>
                        <span class="cos-current cos-current--crest">
                            <span class="cos-crest-badge">
                                <LeagueCrest
                                    :variant="crestStyle"
                                    :tier="crestColour"
                                />
                            </span>
                            {{ crestName }}
                        </span>
                        <button class="cos-change" @click="openPicker('crest')">
                            Change
                        </button>
                    </div>
                    <div class="cos-slot">
                        <span class="cos-label">Frame</span>
                        <span class="cos-current">{{
                            activeName("frame")
                        }}</span>
                        <button class="cos-change" @click="openPicker('frame')">
                            Change
                        </button>
                    </div>
                    <div class="cos-slot">
                        <span class="cos-label">Card Back</span>
                        <span class="cos-current">{{
                            activeName("card_back")
                        }}</span>
                        <button
                            class="cos-change"
                            @click="openPicker('card_back')"
                        >
                            Change
                        </button>
                    </div>
                    <div class="cos-slot">
                        <span class="cos-label">Effect</span>
                        <span class="cos-current">{{
                            activeName("victory_fx")
                        }}</span>
                        <button
                            class="cos-change"
                            @click="openPicker('victory_fx')"
                        >
                            Change
                        </button>
                    </div>
                </div>

                <!-- Referral Section -->
                <div class="referral-section">
                    <h3 class="referral-title">Referral Program</h3>
                    <p class="referral-desc">
                        Invite friends! Earn 20 coins when they reach Level 2.
                    </p>

                    <div class="referral-code-display">
                        <span v-if="referralCode" class="referral-code">{{
                            referralCode
                        }}</span>
                        <span v-else class="referral-code dim">Loading...</span>
                    </div>
                    <div class="referral-buttons">
                        <button
                            class="btn-referral btn-copy"
                            :disabled="!referralCode"
                            @click="copyCode"
                        >
                            {{ copied ? "Copied!" : "Copy Code" }}
                        </button>
                        <button
                            v-if="canShare"
                            class="btn-referral btn-share"
                            :disabled="!referralCode"
                            @click="shareCode"
                        >
                            Share
                        </button>
                    </div>

                    <div v-if="referralStats" class="referral-stats">
                        <div class="ref-stat">
                            <span class="ref-stat-value">{{
                                referralStats.total_referred
                            }}</span>
                            <span class="ref-stat-label">Invited</span>
                        </div>
                        <div class="ref-stat">
                            <span class="ref-stat-value">{{
                                referralStats.verified_count
                            }}</span>
                            <span class="ref-stat-label">Verified (Lv.2+)</span>
                        </div>
                        <div class="ref-stat">
                            <span class="ref-stat-value">{{
                                referralStats.total_coins_earned
                            }}</span>
                            <span class="ref-stat-label">Coins Earned</span>
                        </div>
                    </div>

                    <!-- Enter a referral code -->
                    <div
                        v-if="!auth.state.user?.referred_by && !referralApplied"
                        class="referral-enter"
                    >
                        <p class="referral-enter-label">
                            Have a referral code?
                        </p>
                        <div class="referral-enter-row">
                            <input
                                v-model="referralInput"
                                type="text"
                                class="referral-input"
                                placeholder="Enter code"
                                maxlength="10"
                                @keyup.enter="applyReferralCode"
                            />
                            <button
                                class="btn-referral btn-apply"
                                :disabled="
                                    !referralInput.trim() || applyingReferral
                                "
                                @click="applyReferralCode"
                            >
                                {{ applyingReferral ? "Applying..." : "Apply" }}
                            </button>
                        </div>
                        <p v-if="referralError" class="referral-error">
                            {{ referralError }}
                        </p>
                    </div>
                    <div v-else-if="referralApplied" class="referral-applied">
                        Referral code applied!
                    </div>
                </div>
            </div>

            <!-- XP & Level -->
            <div v-if="!statsLoading" class="card-panel">
                <h2 class="section-title">Progression</h2>
                <div class="xp-section">
                    <div class="xp-header">
                        <span class="xp-level-badge"
                            >Lv. {{ gameStats.level || 1 }}</span
                        >
                        <span class="xp-text"
                            >{{ gameStats.xp || 0 }} /
                            {{ gameStats.xp_for_next_level || 300 }} XP</span
                        >
                    </div>
                    <div class="xp-bar-track">
                        <div
                            class="xp-bar-fill"
                            :style="{ width: xpPercent + '%' }"
                        ></div>
                    </div>
                    <div class="xp-elo-row">
                        <div class="elo-display">
                            <span class="elo-label">League</span>
                            <span class="elo-value"
                                >&#128737;
                                {{ auth.state.user?.league_points ?? 0 }}</span
                            >
                        </div>
                        <div class="elo-display">
                            <span class="elo-label">Login Streak</span>
                            <span class="elo-value streak-value"
                                >&#128293;
                                {{
                                    auth.state.user?.daily_reward_streak ?? 0
                                }}</span
                            >
                        </div>
                        <div class="elo-display">
                            <span class="elo-label">Best Streak</span>
                            <span class="elo-value">{{
                                gameStats.max_login_streak || 0
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger / transaction history -->
            <div class="card-panel">
                <h2 class="section-title">Ledger</h2>
                <button class="ledger-toggle" @click="toggleHistory">
                    {{ showHistory ? "Hide" : "Show" }} Transaction History
                </button>
                <div v-if="showHistory" class="ledger-list">
                    <div v-if="loadingHistory" class="ledger-state">
                        Loading&hellip;
                    </div>
                    <div
                        v-else-if="transactions.length === 0"
                        class="ledger-state"
                    >
                        No transactions yet.
                    </div>
                    <div
                        v-for="tx in transactions"
                        v-else
                        :key="tx.id"
                        class="tx-row"
                    >
                        <div class="tx-info">
                            <span class="tx-desc">{{ tx.description }}</span>
                            <span class="tx-date">{{
                                formatTxDate(tx.created_at)
                            }}</span>
                        </div>
                        <div
                            class="tx-amount"
                            :class="tx.amount > 0 ? 'earn' : 'spend'"
                        >
                            {{ tx.amount > 0 ? "+" : ""
                            }}{{ tx.amount }} &#9673;
                        </div>
                    </div>
                </div>
            </div>

            <!-- Game Stats -->
            <div class="card-panel">
                <h2 class="section-title">Campaign Record</h2>
                <div v-if="statsLoading" class="stats-loading">
                    Loading stats...
                </div>
                <div v-else class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-number">{{
                            gameStats.total_games
                        }}</span>
                        <span class="stat-label">Total Games</span>
                    </div>
                    <div class="stat-card stat-win">
                        <span class="stat-number">{{
                            gameStats.total_wins
                        }}</span>
                        <span class="stat-label">Victories</span>
                    </div>
                    <div class="stat-card stat-loss">
                        <span class="stat-number">{{
                            gameStats.total_losses
                        }}</span>
                        <span class="stat-label">Defeats</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">{{
                            gameStats.online_wins
                        }}</span>
                        <span class="stat-label">Online Wins</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">{{
                            gameStats.single_wins
                        }}</span>
                        <span class="stat-label">Solo Wins</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">{{
                            gameStats.pnp_wins
                        }}</span>
                        <span class="stat-label">Local Wins</span>
                    </div>
                </div>
            </div>

            <!-- Detailed Stats Link (hidden if no payments and not premium) -->
            <div
                v-if="auth.state.user?.is_premium"
                class="card-panel"
                style="text-align: center"
            >
                <router-link to="/stats" class="btn-primary stats-link"
                    >View Detailed Stats</router-link
                >
            </div>
            <div
                v-else-if="auth.state.user?.payments_enabled"
                class="card-panel"
                style="text-align: center"
            >
                <router-link to="/stats" class="btn-primary stats-link">
                    <span class="lock-icon">&#128274; </span>View Detailed Stats
                </router-link>
            </div>

            <!-- Subscription Management -->
            <div v-if="auth.state.user?.is_premium" class="card-panel">
                <h2 class="section-title">Subscription</h2>
                <div v-if="subLoading" class="stats-loading">
                    Loading subscription details...
                </div>
                <div v-else-if="subDetails" class="sub-panel">
                    <div class="sub-row">
                        <span class="sub-label">Platform</span>
                        <span class="sub-value sub-platform">{{
                            platformLabel
                        }}</span>
                    </div>
                    <div v-if="subDetails.interval" class="sub-row">
                        <span class="sub-label">Plan</span>
                        <span class="sub-value">{{ intervalLabel }}</span>
                    </div>
                    <div v-if="subDetails.amount_cents" class="sub-row">
                        <span class="sub-label">Price</span>
                        <span class="sub-value">{{ formattedPrice }}</span>
                    </div>
                    <div class="sub-row">
                        <span class="sub-label">Status</span>
                        <span class="sub-value" :class="subStatusClass">{{
                            subStatusLabel
                        }}</span>
                    </div>
                    <div
                        v-if="
                            subDetails.cancel_at_period_end &&
                            subDetails.current_period_end
                        "
                        class="sub-row"
                    >
                        <span class="sub-label">Cancels on</span>
                        <span class="sub-value sub-cancelling">{{
                            formatDate(subDetails.current_period_end)
                        }}</span>
                    </div>
                    <div
                        v-else-if="subDetails.current_period_end"
                        class="sub-row"
                    >
                        <span class="sub-label">Next bill date</span>
                        <span class="sub-value">{{
                            formatDate(subDetails.current_period_end)
                        }}</span>
                    </div>

                    <!-- Stripe: Cancel button -->
                    <div
                        v-if="
                            subDetails.platform === 'stripe' &&
                            !subDetails.cancel_at_period_end
                        "
                        class="sub-actions"
                    >
                        <button
                            class="btn-cancel-sub"
                            @click="showCancelConfirm = true"
                        >
                            Cancel Subscription
                        </button>
                    </div>

                    <!-- Apple/Google: external management -->
                    <p
                        v-if="subDetails.platform === 'apple'"
                        class="sub-external"
                    >
                        Manage your subscription in the App Store.
                    </p>
                    <p
                        v-if="subDetails.platform === 'google'"
                        class="sub-external"
                    >
                        Manage your subscription in Google Play.
                    </p>
                </div>
            </div>

            <!-- Cancel confirmation modal -->
            <div
                v-if="showCancelConfirm"
                class="modal-overlay"
                @click.self="showCancelConfirm = false"
            >
                <div class="modal-box">
                    <h3 class="modal-title">Cancel Subscription?</h3>
                    <p class="modal-body">
                        Your premium access will continue until the end of your
                        current billing period. You won't be charged again.
                    </p>
                    <div class="modal-actions">
                        <button @click="showCancelConfirm = false">
                            Keep Subscription
                        </button>
                        <button
                            class="btn-danger"
                            :disabled="cancelling"
                            @click="confirmCancel"
                        >
                            {{ cancelling ? "Cancelling..." : "Yes, Cancel" }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Account Management -->
            <div class="card-panel">
                <h2 class="section-title">Account</h2>
                <div class="account-actions">
                    <a
                        href="/auth/manage"
                        target="_blank"
                        rel="noopener"
                        class="btn-primary account-link"
                    >
                        Manage Account
                    </a>
                    <button class="btn-logout" @click="handleLogout">
                        Logout
                    </button>
                </div>
            </div>
        </div>

        <CosmeticPicker
            :kind="pickerKind"
            :open="pickerOpen"
            @close="pickerOpen = false"
        />
    </TaSubPage>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import axios, { isAxiosError } from "axios";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import { useCosmetics } from "../stores/cosmetics";
import { xpForLevel } from "../xp";
import TaSubPage from "./TaSubPage.vue";
import LeagueCrest from "./LeagueCrest.vue";
import CosmeticPicker from "./CosmeticPicker.vue";

interface GameStats {
    total_games?: number;
    total_wins?: number;
    total_losses?: number;
    online_wins?: number;
    single_wins?: number;
    pnp_wins?: number;
    xp?: number;
    level?: number;
    xp_for_next_level?: number;
    elo_rating?: number;
    login_streak?: number;
    max_login_streak?: number;
}

interface ReferralStats {
    total_referred: number;
    verified_count: number;
    total_coins_earned: number;
}

interface SubscriptionDetails {
    is_premium: boolean;
    platform?: string;
    status?: string;
    cancel_at_period_end?: boolean;
    current_period_end?: string;
    interval?: string;
    interval_count?: number;
    amount_cents?: number;
    currency?: string;
}

const auth = useAuth();
const toast = useToast();
const cosmetics = useCosmetics();

const frameClass = computed(
    () => `frame-${auth.state.user?.avatar_frame ?? "iron"}`,
);

// The cosmetic-slot picker modal. It stays mounted (so it can animate open and
// closed); `pickerKind` is the slot it shows, `pickerOpen` toggles visibility.
type CosmeticSlot = "title" | "crest" | "frame" | "card_back" | "victory_fx";
const pickerKind = ref<CosmeticSlot>("title");
const pickerOpen = ref(false);

function openPicker(kind: CosmeticSlot): void {
    pickerKind.value = kind;
    pickerOpen.value = true;
}

// Equipped crest = a numeric shape (1-5) + colour (0-4), resolved server-side.
const crestStyle = computed(() => auth.state.user?.crest_style ?? 1);
const crestColour = computed(() => auth.state.user?.crest_colour ?? 0);

const crestName = computed(() => {
    const shape = cosmetics.state.cosmetics.find(
        (c) => c.type === "crest_style" && Number(c.value) === crestStyle.value,
    );
    const colour = cosmetics.state.cosmetics.find(
        (c) =>
            c.type === "crest_colour" && Number(c.value) === crestColour.value,
    );
    return [colour?.name, shape?.name].filter(Boolean).join(" ") || "Crest";
});

// Name of the currently-equipped item in a single cosmetic slot.
function activeName(type: "frame" | "card_back" | "victory_fx"): string {
    const slug = cosmetics.state.active?.[type];
    if (typeof slug !== "string") {
        return "Default";
    }
    return (
        cosmetics.state.cosmetics.find((c) => c.slug === slug)?.name ??
        "Default"
    );
}

interface CoinTransaction {
    id: number;
    amount: number;
    description: string;
    created_at?: string;
}

const showHistory = ref(false);
const loadingHistory = ref(false);
const transactions = ref<CoinTransaction[]>([]);

async function toggleHistory(): Promise<void> {
    showHistory.value = !showHistory.value;
    if (showHistory.value && transactions.value.length === 0) {
        loadingHistory.value = true;
        try {
            const response = await axios.get<{
                transactions?: CoinTransaction[];
            }>("/api/coin-transactions");
            transactions.value = (response.data.transactions ?? []).filter(
                (tx) => tx.amount !== 0,
            );
        } catch {
            // History may be unavailable — leave the list empty.
        }
        loadingHistory.value = false;
    }
}

function formatTxDate(dateString?: string): string {
    if (!dateString) {
        return "";
    }
    return new Date(dateString).toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
}

const gameStats = reactive<GameStats>({});
const statsLoading = ref(true);
const referralCode = ref<string | undefined>(undefined);
const referralStats = ref<ReferralStats | undefined>(undefined);
const copied = ref(false);
const canShare = Boolean(navigator.share);
const referralInput = ref("");
const applyingReferral = ref(false);
const referralError = ref("");
const referralApplied = ref(false);
const subDetails = ref<SubscriptionDetails | undefined>(undefined);
const subLoading = ref(false);
const showCancelConfirm = ref(false);
const cancelling = ref(false);
const copyResetTimer = ref<ReturnType<typeof setTimeout>>();

const platformLabel = computed<string>(() => {
    const platform = subDetails.value?.platform;
    if (platform === "stripe") {
        return "Stripe";
    }
    if (platform === "apple") {
        return "Apple";
    }
    if (platform === "google") {
        return "Google Play";
    }
    return platform || "Unknown";
});

const subStatusLabel = computed<string>(() => {
    if (subDetails.value?.cancel_at_period_end) {
        return "Cancelling";
    }
    return subDetails.value?.status === "active"
        ? "Active"
        : subDetails.value?.status || "Active";
});

const subStatusClass = computed<string>(() => {
    if (subDetails.value?.cancel_at_period_end) {
        return "sub-cancelling";
    }
    return subDetails.value?.status === "active" ? "sub-active" : "";
});

const intervalLabel = computed<string>(() => {
    const interval = subDetails.value?.interval;
    if (!interval) {
        return "";
    }
    const count = subDetails.value?.interval_count || 1;
    const labels: Record<string, string> = {
        day: "Daily",
        week: "Weekly",
        month: "Monthly",
        year: "Yearly",
    };
    if (count === 1) {
        return labels[interval] || interval;
    }
    return `Every ${count} ${interval}s`;
});

const formattedPrice = computed<string>(() => {
    const cents = subDetails.value?.amount_cents;
    if (cents === undefined) {
        return "";
    }
    const currency = subDetails.value?.currency || "USD";
    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
});

const xpPercent = computed<number>(() => {
    const xp = gameStats.xp || 0;
    const level = gameStats.level || 1;
    const currentLevelXp = xpForLevel(level);
    const nextLevelXp = gameStats.xp_for_next_level || xpForLevel(level + 1);
    const range = nextLevelXp - currentLevelXp;
    if (range <= 0) {
        return 0;
    }
    return Math.min(100, Math.round(((xp - currentLevelXp) / range) * 100));
});

async function handleLogout(): Promise<void> {
    await auth.logout();
    window.location.reload();
}

async function fetchReferralData(): Promise<void> {
    const [codeResult, statsResult] = await Promise.allSettled([
        axios.get<{ code: string }>("/api/referral/code"),
        axios.get<ReferralStats>("/api/referral/stats"),
    ]);
    if (codeResult.status === "fulfilled") {
        referralCode.value = codeResult.value.data.code;
    }
    if (statsResult.status === "fulfilled") {
        referralStats.value = statsResult.value.data;
    }
}

async function copyCode(): Promise<void> {
    const code = referralCode.value;
    if (!code) {
        return;
    }
    try {
        await navigator.clipboard.writeText(code);
    } catch {
        // Fallback for non-HTTPS or unsupported contexts
        const textarea = document.createElement("textarea");
        textarea.value = code;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.append(textarea);
        textarea.select();
        document.execCommand("copy");
        textarea.remove();
    }
    copied.value = true;
    clearTimeout(copyResetTimer.value);
    copyResetTimer.value = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

async function shareCode(): Promise<void> {
    const code = referralCode.value;
    if (!code || !navigator.share) {
        return;
    }
    try {
        await navigator.share({
            title: "Join Trusted Advisors!",
            text: `Use my referral code: ${code}`,
        });
    } catch {
        /*
        ignore share cancellation
        */
    }
}

async function fetchSubscriptionDetails(): Promise<void> {
    subLoading.value = true;
    try {
        const response = await axios.get<SubscriptionDetails>(
            "/api/premium/details",
        );
        subDetails.value = response.data;
    } catch {
        // fallback to basic info
        subDetails.value = {
            is_premium: true,
            status: "active",
        };
    }
    subLoading.value = false;
}

async function confirmCancel(): Promise<void> {
    cancelling.value = true;
    try {
        const response = await axios.post<{ ends_at?: string }>(
            "/api/premium/cancel",
        );
        showCancelConfirm.value = false;
        if (subDetails.value) {
            subDetails.value.cancel_at_period_end = true;
            subDetails.value.current_period_end =
                response.data.ends_at ?? undefined;
        }
    } catch {
        toast.error("Failed to cancel subscription. Please try again.");
    }
    cancelling.value = false;
}

function formatDate(isoString: string | undefined): string {
    if (!isoString) {
        return "";
    }
    return new Date(isoString).toLocaleDateString(undefined, {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
}

async function applyReferralCode(): Promise<void> {
    const code = referralInput.value.trim();
    if (!code) {
        return;
    }
    applyingReferral.value = true;
    referralError.value = "";
    try {
        await axios.post("/api/referral/apply", { code });
        referralApplied.value = true;
        referralInput.value = "";
    } catch (error) {
        referralError.value = isAxiosError<{ message?: string }>(error)
            ? (error.response?.data?.message ?? "Invalid referral code.")
            : "Invalid referral code.";
    } finally {
        applyingReferral.value = false;
    }
}

onMounted(async () => {
    try {
        const response = await axios.get<GameStats>("/api/auth/stats");
        Object.assign(gameStats, response.data);
    } catch {
        /*
        ignore
        */
    }
    statsLoading.value = false;
    fetchReferralData();
    if (!cosmetics.state.loaded) {
        void cosmetics.fetchCatalogue();
    }
    if (auth.state.user?.is_premium) {
        fetchSubscriptionDetails();
    }
});
</script>

<style scoped>
.profile-page {
    max-width: 600px;
    margin: 0 auto;
}

/* Cosmetics slots — current pick + Change button */
.cosmetics-slots {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(240, 192, 80, 0.15);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.cos-slot {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(240, 192, 80, 0.18);
}

.cos-label {
    flex: none;
    width: 78px;
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.cos-current {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: "Cinzel", serif;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--accent-gold);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cos-crest-badge {
    width: 24px;
    height: 30px;
    flex: none;
}

.cos-change {
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 5px 12px;
    border-radius: 8px;
    background: rgba(240, 192, 80, 0.12);
    border: 1px solid rgba(240, 192, 80, 0.4);
    color: var(--accent-gold);
    cursor: pointer;
    box-shadow: none;
}

.cos-change:hover {
    background: rgba(240, 192, 80, 0.2);
    border-color: var(--accent-gold);
}

/* Ledger / transaction history */
.ledger-toggle {
    display: block;
    margin: 0 auto;
    font-family: "Cinzel", serif;
    font-size: 0.8rem;
    padding: 7px 18px;
    border-radius: 8px;
    border: 1px solid rgba(240, 192, 80, 0.3);
    background: rgba(0, 0, 0, 0.3);
    color: var(--accent-gold);
    cursor: pointer;
}

.ledger-list {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.ledger-state {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 12px;
}

.tx-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(240, 192, 80, 0.18);
}

.tx-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.tx-desc {
    font-size: 0.85rem;
    color: var(--text-primary);
}

.tx-date {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.tx-amount {
    flex: none;
    font-family: "Cinzel", serif;
    font-weight: 700;
    font-size: 0.85rem;
}

.tx-amount.earn {
    color: var(--accent-green);
}

.tx-amount.spend {
    color: var(--accent-red);
}

.section-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.3rem;
    margin-bottom: 15px;
    text-align: center;
}

.profile-info {
    display: flex;
    align-items: center;
    gap: 16px;
    justify-content: center;
}

.profile-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.35);
    border: 3px solid var(--accent-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    overflow: hidden;
}

/* Show the player's crest inside the avatar ring (frame). */
.profile-avatar :deep(.league-crest) {
    width: 66%;
    height: 80%;
}

/* Equipped avatar frames — ring style by cosmetic `value`. */
.profile-avatar.frame-iron {
    border-color: #8a8f98;
}

.profile-avatar.frame-bronze {
    border-color: #c17a3a;
    box-shadow: 0 0 8px rgba(193, 122, 58, 0.5);
}

.profile-avatar.frame-silver {
    border-color: #cbd5e1;
    box-shadow: 0 0 10px rgba(203, 213, 225, 0.6);
}

.profile-avatar.frame-gold {
    border-color: #f2c14e;
    box-shadow: 0 0 12px rgba(242, 193, 78, 0.7);
}

.profile-avatar.frame-royal {
    border-color: #b072e0;
    box-shadow: 0 0 14px rgba(176, 114, 224, 0.8);
}

.profile-details {
    text-align: left;
}

.profile-title {
    font-family: "Cinzel", serif;
    font-size: 0.82rem;
    font-weight: 700;
    color: #f2c14e;
    letter-spacing: 0.4px;
    margin-top: 2px;
}

.profile-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.3rem;
}

.profile-joined {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 0.9rem;
}

/* XP Section */
.xp-section {
    text-align: center;
}

.xp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.xp-level-badge {
    font-family: "Cinzel", serif;
    font-size: 1.2rem;
    color: var(--accent-gold);
    font-weight: 700;
}

.xp-text {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.xp-bar-track {
    width: 100%;
    height: 12px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 6px;
    border: 1px solid rgba(138, 106, 46, 0.3);
    overflow: hidden;
    margin-bottom: 14px;
}

.xp-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #8a6a2e, #d4a843);
    border-radius: 6px;
    transition: width 0.5s ease;
}

.xp-elo-row {
    display: flex;
    justify-content: center;
    gap: 24px;
}

.streak-value {
    font-size: 1.4rem;
}

.elo-display {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.elo-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.elo-value {
    font-family: "Cinzel", serif;
    font-size: 1.6rem;
    color: var(--accent-gold);
    font-weight: 700;
}

/* Stats grid */
.stats-loading {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.stat-card {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(138, 106, 46, 0.2);
    border-radius: 8px;
    padding: 14px 10px;
    text-align: center;
}

.stat-number {
    display: block;
    font-family: "Cinzel", serif;
    font-size: 1.6rem;
    color: var(--accent-gold);
    font-weight: 700;
}

.stat-card .stat-label {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}

.stat-win .stat-number {
    color: #6abf50;
}
.stat-loss .stat-number {
    color: #d05040;
}

/* Account actions */
.account-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.stats-link {
    display: inline-block;
    padding: 10px 28px;
    font-size: 0.95rem;
    text-decoration: none;
}

.account-link {
    display: inline-block;
    padding: 10px 28px;
    font-size: 0.95rem;
    text-decoration: none;
}

.btn-logout {
    padding: 10px 28px;
    font-size: 0.95rem;
    background: rgba(160, 48, 32, 0.15);
    border: 1px solid rgba(160, 48, 32, 0.4);
    border-radius: 6px;
    color: #d05040;
    cursor: pointer;
    font-family: "Cinzel", serif;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-logout:hover {
    background: rgba(160, 48, 32, 0.3);
    color: #e06050;
}

/* Referral Section */
.referral-section {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(138, 106, 46, 0.3);
}

.referral-title {
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-size: 1rem;
    margin-bottom: 6px;
    text-align: center;
}

.referral-desc {
    color: var(--text-secondary);
    font-size: 0.82rem;
    font-style: italic;
    margin-bottom: 12px;
    text-align: center;
}

.referral-code-display {
    background: rgba(0, 0, 0, 0.3);
    border: 2px solid var(--border-gold);
    border-radius: 6px;
    padding: 10px 14px;
    text-align: center;
    margin-bottom: 8px;
}

.referral-code {
    font-family: "Cinzel", serif;
    font-size: 1.2rem;
    color: var(--accent-gold);
    font-weight: 700;
    letter-spacing: 3px;
}

.referral-code.dim {
    color: var(--text-secondary);
    font-size: 0.9rem;
    letter-spacing: 0;
}

.referral-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
}

.btn-referral {
    flex: 1;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    text-align: center;
}

.btn-copy {
    background: rgba(212, 168, 67, 0.15);
    border: 1px solid rgba(212, 168, 67, 0.3);
    color: var(--accent-gold);
}

.btn-share {
    background: rgba(67, 160, 212, 0.15);
    border: 1px solid rgba(67, 160, 212, 0.3);
    color: #60b8e0;
}

.referral-stats {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 14px;
}

.ref-stat {
    text-align: center;
}

.ref-stat-value {
    display: block;
    font-family: "Cinzel", serif;
    font-size: 1.3rem;
    color: var(--accent-gold);
    font-weight: 700;
}

.ref-stat-label {
    display: block;
    font-size: 0.7rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.referral-enter {
    padding-top: 10px;
    border-top: 1px solid rgba(138, 106, 46, 0.15);
}

.referral-enter-label {
    color: var(--text-secondary);
    font-size: 0.82rem;
    font-style: italic;
    margin-bottom: 8px;
    text-align: center;
}

.referral-enter-row {
    display: flex;
    gap: 8px;
}

.referral-input {
    flex: 1;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(138, 106, 46, 0.3);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--text-bright);
    font-family: "Cinzel", serif;
    font-size: 0.9rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    text-align: center;
}

.referral-input::placeholder {
    color: var(--text-secondary);
    font-style: italic;
    text-transform: none;
    letter-spacing: 0;
}

.btn-apply {
    background: rgba(80, 160, 80, 0.15);
    border: 1px solid rgba(80, 160, 80, 0.3);
    color: #6abf50;
}

.referral-error {
    color: #d05040;
    font-size: 0.8rem;
    margin-top: 6px;
    text-align: center;
}

.referral-applied {
    color: #6abf50;
    font-size: 0.85rem;
    font-style: italic;
    text-align: center;
    padding-top: 10px;
    border-top: 1px solid rgba(138, 106, 46, 0.15);
}

.premium-badge-inline {
    color: var(--accent-gold);
    font-size: 1rem;
    margin-left: 4px;
}

.lock-icon {
    font-size: 0.8rem;
}

/* Subscription panel */
.sub-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sub-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.sub-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sub-value {
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-weight: 600;
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 60%;
}

.sub-platform {
    color: var(--accent-gold);
}

.sub-active {
    color: #6abf50;
}

.sub-cancelling {
    color: #e08040;
}

.sub-actions {
    margin-top: 8px;
    text-align: center;
}

.btn-cancel-sub {
    padding: 8px 24px;
    font-size: 0.9rem;
    background: rgba(160, 48, 32, 0.15);
    border: 1px solid rgba(160, 48, 32, 0.4);
    border-radius: 6px;
    color: #d05040;
    cursor: pointer;
    font-family: "Cinzel", serif;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-cancel-sub:hover {
    background: rgba(160, 48, 32, 0.3);
    color: #e06050;
}

.sub-external {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-style: italic;
    text-align: center;
    margin-top: 8px;
}

/* Cancel confirmation modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.modal-box {
    background: var(--bg-secondary);
    border: 2px solid var(--border-gold);
    border-radius: 12px;
    padding: 24px;
    max-width: 400px;
    width: 100%;
}

.modal-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.2rem;
    margin-bottom: 12px;
    text-align: center;
}

.modal-body {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 20px;
    text-align: center;
    line-height: 1.5;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modal-actions button {
    padding: 8px 20px;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
