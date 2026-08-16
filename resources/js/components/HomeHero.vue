<template>
    <!-- Daily-tribute ledger row. Shown only while the tribute is claimable;
       once claimed it drops out of the ledger entirely. -->
    <button
        v-if="claimable"
        class="ta-row ta-row--ready hero-row"
        @click="open"
    >
        <div class="ta-medallion hero-medallion">
            <div class="ta-medallion-face">&#10022;</div>
        </div>
        <div class="ta-row-body">
            <div class="ta-row-title">Daily Tribute &middot; Day {{ day }}</div>
            <div class="ta-row-meta">
                +{{ rewardAmount }} {{ rewardUnit }} &middot; &#128293;
                {{ streak }}-day streak
            </div>
        </div>
        <span class="ta-pill ta-pill--claim">CLAIM</span>
    </button>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useDailyReward } from "../stores/daily-reward";

const dailyReward = useDailyReward();

const claimable = computed(() => dailyReward.state.status?.available === true);
const rewardAmount = computed(() => dailyReward.state.status?.reward?.amount ?? 0);
const rewardUnit = computed(() =>
    dailyReward.state.status?.reward?.type === "xp" ? "XP" : "gold",
);
const day = computed(() => dailyReward.state.status?.day ?? 1);
const streak = computed(() => dailyReward.state.status?.streak ?? 0);

function open(): void {
    dailyReward.openModal();
}
</script>

<style scoped>
.hero-row-wrap {
    display: block;
}

.hero-row {
    width: 100%;
    margin-top: 8px;
    cursor: pointer;
    text-align: left;
    font-family: inherit;
    transition:
        border-color 0.15s,
        transform 0.12s,
        box-shadow 0.12s;
}

button.hero-row {
    box-shadow: none;
}

.hero-row:hover {
    transform: translateY(-1px);
}

button.hero-row.ta-row--ready:hover {
    box-shadow: 0 0 16px rgba(142, 240, 200, 0.25);
}

.hero-row-rested {
    cursor: default;
}

.hero-medallion {
    position: relative;
}

.hero-flame {
    background: radial-gradient(circle at 40% 28%, #5c3c0c, #241704);
    color: #ffb066;
    filter: drop-shadow(0 0 6px rgba(247, 129, 84, 0.7));
}

@media (prefers-reduced-motion: reduce) {
    .hero-row:hover {
        transform: none;
    }
}
</style>
