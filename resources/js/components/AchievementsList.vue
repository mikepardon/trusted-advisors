<template>
    <TaSubPage title="Achievements" subtitle="Medals, titles and renown">
        <HintBubble hint-id="achievements-claim">
            Earned an achievement? Tap <strong>Claim</strong> to collect your XP
            and coin rewards!
        </HintBubble>

        <div v-if="loading" class="ach-loading">Loading...</div>

        <template v-else>
            <!-- Hero: conic progress ring built from real earned/total counts -->
            <div class="ach-hero">
                <div class="ach-ring" :style="ringStyle">
                    <div class="ach-ring-face">
                        <span class="ach-ring-count">{{ earnedCount }}</span>
                        <span class="ach-ring-total">/ {{ totalCount }}</span>
                    </div>
                </div>
                <div class="ach-hero-body">
                    <div class="ach-hero-headline">{{ heroHeadline }}</div>
                    <div class="ach-hero-sub">
                        Claimed medals grant renown and season points
                    </div>
                </div>
            </div>

            <div v-if="achievements.length === 0" class="ach-empty">
                No medals to display yet.
            </div>

            <div v-else class="ach-list">
                <div
                    v-for="ach in achievements"
                    :key="ach.id"
                    class="ta-row"
                    :class="{ 'ta-row--ready': state(ach) === 'ready' }"
                >
                    <div
                        class="ta-medallion ta-medallion--round"
                        :class="{
                            'ta-medallion--silver': state(ach) === 'earned',
                            'ta-medallion--locked': state(ach) === 'locked',
                        }"
                    >
                        <div
                            class="ta-medallion-face"
                            :class="{
                                'ta-medallion-face--locked':
                                    state(ach) === 'locked',
                            }"
                        >
                            <AppIcon
                                v-bind="resolveAchievementIcon(ach.icon)"
                            />
                        </div>
                    </div>

                    <div class="ta-row-body">
                        <div
                            class="ta-row-title"
                            :class="{
                                'ta-row-title--locked': state(ach) === 'locked',
                            }"
                        >
                            {{ ach.name }}
                        </div>
                        <div class="ta-row-meta">{{ ach.description }}</div>
                        <div
                            v-if="ach.tier_group && ach.tier > 1"
                            class="ach-tier-label"
                        >
                            Tier {{ ach.tier }}
                        </div>
                        <!-- Thin progress bar for in-progress trackable achievements -->
                        <div
                            v-if="state(ach) === 'progress'"
                            class="ta-bar ta-bar--thin ach-row-bar"
                        >
                            <div
                                class="ta-bar-fill"
                                :style="{
                                    width: progressPercent(ach.progress) + '%',
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- Claimable achievements get an interactive pill button; others a static state pill -->
                    <button
                        v-if="state(ach) === 'ready'"
                        class="ta-pill ta-pill--claim ach-claim-btn"
                        :disabled="claiming === ach.id"
                        @click="claim(ach)"
                    >
                        {{ claiming === ach.id ? "..." : "CLAIM" }}
                    </button>
                    <span
                        v-else
                        class="ta-pill"
                        :class="{
                            'ta-pill--claimed': state(ach) === 'earned',
                            'ta-pill--locked': state(ach) === 'locked',
                        }"
                    >
                        {{ pillLabel(ach) }}
                    </span>
                </div>
            </div>
        </template>

        <button
            v-if="unclaimedCount >= 2"
            class="ta-cta ach-claim-all"
            :disabled="claimingAll"
            @click="claimAll"
        >
            {{ claimingAll ? "Claiming..." : `Claim All (${unclaimedCount})` }}
        </button>

        <AchievementClaim
            v-if="claimOverlay"
            :achievement="claimOverlay.achievement"
            :result="claimOverlay.result"
            @dismiss="onClaimDismiss"
        />

        <!-- Batch claim summary overlay -->
        <div
            v-if="batchClaimOverlay"
            class="batch-overlay"
            @click="batchClaimOverlay = undefined"
        >
            <div class="batch-overlay-card" @click.stop>
                <h3 class="batch-title">Rewards Claimed!</h3>
                <p class="batch-count">
                    {{ batchClaimOverlay.count }} achievements claimed
                </p>
                <div class="batch-rewards">
                    <div
                        v-if="batchClaimOverlay.xp_awarded"
                        class="batch-reward-row"
                    >
                        +{{ batchClaimOverlay.xp_awarded }} XP
                    </div>
                    <div
                        v-if="batchClaimOverlay.coins_awarded"
                        class="batch-reward-row"
                    >
                        +{{ batchClaimOverlay.coins_awarded }} &#129689;
                    </div>
                    <div
                        v-if="batchClaimOverlay.leveled_up"
                        class="batch-levelup"
                    >
                        Level Up! Now Lv. {{ batchClaimOverlay.new_level }}
                    </div>
                </div>
                <button
                    class="ta-cta batch-dismiss"
                    @click="batchClaimOverlay = undefined"
                >
                    Continue
                </button>
            </div>
        </div>
    </TaSubPage>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import TaSubPage from "./TaSubPage.vue";
import AchievementClaim from "./AchievementClaim.vue";
import AppIcon from "./AppIcon.vue";
import HintBubble from "./HintBubble.vue";
import { useAuth } from "../stores/auth";
import { resolveAchievementIcon } from "../utils/achievement-icons";

interface AchievementProgress {
    current: number;
    target: number;
}

interface Achievement {
    id: number;
    name: string;
    description: string;
    icon: string;
    earned: boolean;
    claimed: boolean;
    reward_xp?: number;
    reward_coins?: number;
    tier_group?: string;
    tier: number;
    progress?: AchievementProgress;
}

interface ClaimResult {
    new_xp: number;
    new_level: number;
    new_coins: number;
}

interface ClaimOverlay {
    achievement: Achievement;
    result: ClaimResult;
}

interface BatchClaimResult {
    count: number;
    new_xp: number;
    new_level: number;
    new_coins: number;
    xp_awarded?: number;
    coins_awarded?: number;
    leveled_up?: boolean;
}

/**
Presentational states matching the war-table medal styling.
*/
type AchievementState = "ready" | "earned" | "progress" | "locked";

const { updateUserStats } = useAuth();

const achievements = ref<Achievement[]>([]);
const loading = ref(true);
const claiming = ref<number>();
const claimingAll = ref(false);
const claimOverlay = ref<ClaimOverlay>();
const batchClaimOverlay = ref<BatchClaimResult>();

const unclaimedCount = computed(
    () =>
        achievements.value.filter(
            (achievement) => achievement.earned && !achievement.claimed,
        ).length,
);

const totalCount = computed(() => achievements.value.length);
const earnedCount = computed(
    () => achievements.value.filter((achievement) => achievement.earned).length,
);

// Conic-gradient progress ring: gold arc for the earned share, faint track for the rest.
const ringStyle = computed(() => {
    const total = totalCount.value;
    const fraction =
        total > 0 ? Math.round((earnedCount.value / total) * 100) : 0;
    return {
        background: `conic-gradient(#f0c050 0 ${fraction}%, rgba(255, 255, 255, 0.1) ${fraction}% 100%)`,
    };
});

const heroHeadline = computed(() => {
    const ready = unclaimedCount.value;
    if (ready > 0) {
        return `${ready} ${ready === 1 ? "medal" : "medals"} ready to claim`;
    }
    return `${earnedCount.value} of ${totalCount.value} medals earned`;
});

onMounted(async () => {
    await fetchAchievements();
});

/**
Map a real achievement's flags to a presentational medal state.
*/
function state(achievement: Achievement): AchievementState {
    if (achievement.earned && !achievement.claimed) {
        return "ready";
    }
    if (achievement.earned) {
        return "earned";
    }
    if (achievement.progress && achievement.progress.current > 0) {
        return "progress";
    }
    return "locked";
}

function pillLabel(achievement: Achievement): string {
    const current = state(achievement);
    if (current === "earned") {
        return "EARNED";
    }
    if (current === "progress") {
        return `${progressPercent(achievement.progress)}%`;
    }
    return "LOCKED";
}

async function fetchAchievements(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get<Achievement[]>("/api/achievements");
        achievements.value = response.data;
    } catch {
        // silently fail
    }
    loading.value = false;
}

function progressPercent(progress: AchievementProgress | undefined): number {
    if (!progress || !progress.target) {
        return 0;
    }
    return Math.min(
        100,
        Math.round((progress.current / progress.target) * 100),
    );
}

async function claim(achievement: Achievement): Promise<void> {
    claiming.value = achievement.id;
    try {
        const response = await axios.post<ClaimResult>(
            `/api/achievements/${achievement.id}/claim`,
        );
        const result = response.data;

        // Update auth store
        updateUserStats({
            xp: result.new_xp,
            level: result.new_level,
            coins: result.new_coins,
        });

        // Show overlay
        claimOverlay.value = { achievement, result };

        // Mark as claimed locally
        achievement.claimed = true;
    } catch {
        // silently fail
    }
    claiming.value = undefined;
}

function onClaimDismiss(): void {
    claimOverlay.value = undefined;
}

async function claimAll(): Promise<void> {
    claimingAll.value = true;
    try {
        const response = await axios.post<BatchClaimResult>(
            "/api/achievements/claim-all",
        );
        const result = response.data;

        updateUserStats({
            xp: result.new_xp,
            level: result.new_level,
            coins: result.new_coins,
        });

        // Mark all unclaimed as claimed locally
        for (const achievement of achievements.value) {
            if (achievement.earned && !achievement.claimed) {
                achievement.claimed = true;
            }
        }

        batchClaimOverlay.value = result;
    } catch {
        // silently fail
    }
    claimingAll.value = false;
}
</script>

<style scoped>
.ach-loading,
.ach-empty {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 30px;
}

/* ---- Hero ring ---- */
.ach-hero {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 15px;
    background: linear-gradient(
        90deg,
        rgba(240, 192, 80, 0.14),
        rgba(0, 0, 0, 0.4)
    );
    border: 1px solid rgba(240, 192, 80, 0.35);
}

.ach-ring {
    position: relative;
    width: 56px;
    height: 56px;
    flex: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ach-ring-face {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #100b06;
    border: 1px solid rgba(240, 192, 80, 0.4);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
}

.ach-ring-count {
    font-size: 15px;
    font-weight: 800;
    line-height: 1;
}

.ach-ring-total {
    font-size: 8px;
    letter-spacing: 0.6px;
    color: #8a7a5a;
}

.ach-hero-body {
    flex: 1;
    min-width: 0;
}

.ach-hero-headline {
    font-family: "Cinzel", serif;
    font-size: 14px;
    font-weight: 800;
    color: #f0e0c8;
}

.ach-hero-sub {
    font-size: 11.5px;
    color: #9a8a68;
}

/* ---- Medal rows ---- */
.ach-list {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ach-list .ta-row + .ta-row {
    margin-top: 0;
}

.ach-row-bar {
    margin-top: 5px;
}

.ach-tier-label {
    font-size: 0.65rem;
    color: var(--text-secondary);
    opacity: 0.7;
    margin-top: 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Claim pill rendered as a button: keep it pill-sized and add the ready-glow pulse. */
.ach-claim-btn {
    cursor: pointer;
    animation: claimPulse 2s ease-in-out infinite;
}

.ach-claim-btn:disabled {
    opacity: 0.6;
    animation: none;
    cursor: default;
}

@keyframes claimPulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(168, 240, 168, 0.5);
    }
    50% {
        box-shadow: 0 0 10px 2px rgba(168, 240, 168, 0.4);
    }
}

/* ---- Claim-all CTA ---- */
.ach-claim-all {
    position: sticky;
    bottom: 16px;
    margin-top: 16px;
    z-index: 10;
}

/* ---- Batch claim overlay ---- */
.batch-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 200;
}

.batch-overlay-card {
    background: linear-gradient(180deg, #3a2a1a, #2a1f14);
    border: 2px solid var(--accent-gold);
    border-radius: 16px;
    padding: 32px 28px;
    text-align: center;
    max-width: 360px;
    width: 90%;
}

.batch-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.4rem;
    margin-bottom: 8px;
}

.batch-count {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 16px;
}

.batch-rewards {
    margin-bottom: 20px;
}

.batch-reward-row {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.3rem;
    margin-bottom: 4px;
}

.batch-levelup {
    color: #6abf50;
    font-family: "Cinzel", serif;
    font-size: 1.1rem;
    margin-top: 8px;
}

.batch-dismiss {
    margin-top: 4px;
}
</style>
