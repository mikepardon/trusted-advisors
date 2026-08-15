<template>
    <TaSubPage
        title="Season Pass"
        :subtitle="subtitle"
        :stat="statPill"
        back="/"
    >
        <p v-if="pass.state.loading && !pass.state.loaded" class="pass-note">
            Loading…
        </p>

        <p v-else-if="data && data.season === null" class="pass-note">
            No Season Pass is running right now — check back soon.
        </p>

        <template v-else-if="data && data.season">
            <!-- Hero tier medallion + progress to next tier -->
            <div class="pass-hero">
                <div class="ta-medallion pass-hero-medallion">
                    <div class="ta-medallion-face pass-hero-face">
                        <span class="pass-hero-tier-label">TIER</span>
                        <span class="pass-hero-tier-num">{{
                            data.current_tier
                        }}</span>
                    </div>
                </div>
                <div class="pass-hero-body">
                    <div class="pass-hero-top">
                        <span class="pass-hero-name">{{
                            data.season.name
                        }}</span>
                        <span v-if="endsIn" class="pass-hero-ends"
                            >{{ endsIn }} left</span
                        >
                    </div>
                    <div class="ta-bar pass-hero-bar">
                        <div
                            class="ta-bar-fill"
                            :style="{ width: `${progressPercent}%` }"
                        ></div>
                    </div>
                    <div class="pass-hero-meta">
                        {{ data.points }} pts · {{ nextTierLabel }}
                    </div>
                </div>
            </div>

            <!-- Reward track -->
            <div class="pass-track">
                <div
                    v-for="tier in data.tiers"
                    :key="tier.tier"
                    class="ta-row pass-track-row"
                    :class="{ 'ta-row--ready': tier.claimable }"
                >
                    <div
                        class="pass-track-num"
                        :class="{ 'pass-track-num--locked': !tier.reached }"
                    >
                        {{ tier.tier }}
                    </div>

                    <div
                        class="ta-medallion"
                        :class="{ 'ta-medallion--locked': !tier.reached }"
                    >
                        <div
                            class="ta-medallion-face"
                            :class="{
                                'ta-medallion-face--locked': !tier.reached,
                            }"
                        >
                            {{ rewardIcon(tier) }}
                        </div>
                    </div>

                    <div class="ta-row-body">
                        <div
                            class="ta-row-title"
                            :class="[
                                { 'ta-row-title--locked': !tier.reached },
                                tier.reward_cosmetic
                                    ? `rarity-${tier.reward_cosmetic.rarity}`
                                    : '',
                            ]"
                        >
                            {{ tier.name || rewardLabel(tier) }}
                        </div>
                        <div class="ta-row-meta">
                            {{ tier.points_required }} pts ·
                            {{ rewardKind(tier) }}
                        </div>
                    </div>

                    <button
                        v-if="tier.claimable"
                        class="ta-pill ta-pill--claim pass-claim-btn"
                        type="button"
                        :disabled="pass.state.claiming === tier.tier"
                        @click="claim(tier)"
                    >
                        {{ pass.state.claiming === tier.tier ? "…" : "CLAIM" }}
                    </button>
                    <span
                        v-else-if="tier.claimed"
                        class="ta-pill ta-pill--claimed"
                        >CLAIMED</span
                    >
                    <span v-else class="ta-pill ta-pill--locked">LOCKED</span>
                </div>
            </div>
        </template>
    </TaSubPage>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { DateTime } from "luxon";
import TaSubPage from "./TaSubPage.vue";
import { useSeasonPass } from "../stores/season-pass";
import type { PassTier } from "../stores/season-pass";
import { useToast } from "../stores/toast";
import { playSound } from "../sounds";
import { haptic } from "../haptics";

const pass = useSeasonPass();
const toast = useToast();

const nowMs = ref(0);
const timer = ref<ReturnType<typeof setInterval>>();

const data = computed(() => pass.state.data);

// Season name feeds the sub-page subtitle; falls back to a neutral label.
const subtitle = computed(() => data.value?.season?.name ?? "Season Pass");

// Right-hand context pill mirrors the mock's "TIER N" stat.
const statPill = computed(() => {
    if (data.value?.season === null || data.value?.season === undefined) {
        return "";
    }
    return `TIER ${data.value.current_tier}`;
});

const endsIn = computed(() => {
    const endsAt = data.value?.season?.ends_at;
    if (endsAt === null || endsAt === undefined) {
        return "";
    }
    const diff = DateTime.fromISO(endsAt).toMillis() - nowMs.value;
    if (diff <= 0) {
        return "";
    }
    const totalMinutes = Math.floor(diff / 60_000);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    if (days > 0) {
        return `${days}d ${hours}h`;
    }
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes}m`;
});

// Label under the hero bar: the next unreached tier and its threshold.
const nextTierLabel = computed(() => {
    const state = data.value;
    if (state === undefined || state.season === null) {
        return "";
    }
    const next = state.tiers.find((tier) => !tier.reached);
    if (next === undefined) {
        return "All tiers reached";
    }
    return `Tier ${next.tier} at ${next.points_required} pts`;
});

const progressPercent = computed(() => {
    const state = data.value;
    if (state === undefined || state.season === null) {
        return 0;
    }
    const next = state.tiers.find((tier) => !tier.reached);
    if (next === undefined) {
        return 100;
    }
    const current = state.tiers.find(
        (tier) => tier.tier === state.current_tier,
    );
    const floor = current?.points_required ?? 0;
    const span = next.points_required - floor;
    if (span <= 0) {
        return 100;
    }
    return Math.min(100, Math.max(0, ((state.points - floor) / span) * 100));
});

function rewardLabel(tier: PassTier): string {
    if (tier.reward_cosmetic !== null) {
        return tier.reward_cosmetic.name;
    }
    return `${tier.reward_coins} Gold`;
}

// The "Kind" descriptor shown after the points in the row meta.
function rewardKind(tier: PassTier): string {
    if (tier.reward_cosmetic === null) {
        return "Currency";
    }
    const kinds: Record<string, string> = {
        title: "Title",
        frame: "Frame",
        card_back: "Card Back",
        victory_fx: "Effect",
    };
    return kinds[tier.reward_cosmetic.type] ?? "Cosmetic";
}

function rewardIcon(tier: PassTier): string {
    if (tier.reward_cosmetic === null) {
        return "\u{1F4B0}"; // money bag
    }
    const icons: Record<string, string> = {
        title: "\u{1F4DC}", // scroll
        frame: "\u{1F48D}", // ring
        card_back: "\u{1F3B4}", // playing cards
        victory_fx: "✨", // sparkles
    };
    return icons[tier.reward_cosmetic.type] ?? "\u{1F381}"; // gift
}

async function claim(tier: PassTier): Promise<void> {
    const granted = await pass.claim(tier.tier);
    if (granted === undefined) {
        toast.error("Couldn't claim that tier.");
        return;
    }
    playSound("win");
    haptic("success");
    const reward = granted.cosmetic ?? `${granted.coins} gold`;
    toast.success(`Claimed: ${reward}!`);
}

function tick(): void {
    nowMs.value = DateTime.now().toMillis();
}

onMounted(() => {
    void pass.fetchState();
    tick();
    timer.value = setInterval(tick, 1000);
});

onBeforeUnmount(() => {
    if (timer.value !== undefined) {
        clearInterval(timer.value);
    }
});
</script>

<style scoped>
.pass-note {
    color: var(--ta-mute);
    text-align: center;
    padding: 24px 0;
}

/* ---- Hero tier medallion + progress to next tier ---- */
.pass-hero {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 15px;
    background: linear-gradient(
        90deg,
        rgba(240, 192, 80, 0.16),
        rgba(0, 0, 0, 0.4)
    );
    border: 1px solid rgba(240, 192, 80, 0.4);
}

/* Larger, softer-cornered variant of the shared medallion for the hero. */
.pass-hero-medallion {
    width: 54px;
    height: 54px;
    padding: 3px;
    border-radius: 16px;
    box-shadow: 0 4px 0 rgba(0, 0, 0, 0.5);
}

.pass-hero-face {
    flex-direction: column;
    border-radius: 13px;
    color: var(--ta-gold-bright);
}

.pass-hero-tier-label {
    font-family: "Cinzel", serif;
    font-size: 8px;
    letter-spacing: 1px;
}

.pass-hero-tier-num {
    font-family: "Cinzel", serif;
    font-size: 17px;
    font-weight: 800;
    line-height: 1;
}

.pass-hero-body {
    flex: 1;
    min-width: 0;
}

.pass-hero-top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}

.pass-hero-name {
    font-family: "Cinzel", serif;
    font-size: 14px;
    font-weight: 700;
    color: var(--ta-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pass-hero-ends {
    flex: none;
    font-size: 11.5px;
    color: var(--ta-mute);
}

.pass-hero-bar {
    margin-top: 7px;
    transition: width 0.5s ease;
}

.pass-hero-meta {
    margin-top: 5px;
    font-size: 11.5px;
    color: var(--ta-mute);
}

/* ---- Reward track ---- */
.pass-track {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Rows sit in the track's own gap, so drop the shared row's top margin. */
.pass-track-row + .pass-track-row {
    margin-top: 0;
}

.pass-track-num {
    width: 30px;
    flex: none;
    text-align: center;
    font-family: "Cinzel", serif;
    font-size: 13px;
    font-weight: 800;
    color: var(--ta-gold);
}

.pass-track-num--locked {
    color: #6a5a3a;
}

.pass-claim-btn {
    cursor: pointer;
    animation: track-pulse 1.5s ease-in-out infinite;
}

.pass-claim-btn:disabled {
    cursor: not-allowed;
    opacity: 0.7;
    animation: none;
}

.rarity-rare {
    color: var(--ta-blue);
}

.rarity-epic {
    color: #b072e0;
}

.rarity-legendary {
    color: var(--ta-gold);
}

@keyframes track-pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.06);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pass-claim-btn {
        animation: none;
    }
}
</style>
