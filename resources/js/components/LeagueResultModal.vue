<template>
    <div class="lr-overlay" @click.self="close">
        <div class="lr-modal">
            <button type="button" class="lr-close" @click="close">
                &times;
            </button>
            <h2 class="lr-title">Weekly League</h2>
            <p class="lr-sub">Your season has been settled</p>

            <div class="lr-placement">
                <span class="lr-rank">#{{ rank }}</span>
                <span class="lr-of">of {{ total }}</span>
            </div>

            <div class="lr-tiers">
                <span
                    class="lr-tier-badge"
                    :style="{ borderColor: tierBefore.color, color: tierBefore.color }"
                    >{{ tierBefore.name }}</span
                >
                <template v-if="promoted || demoted">
                    <span class="lr-arrow" :class="{ up: promoted, down: demoted }">
                        {{ promoted ? "▲" : "▼" }}
                    </span>
                    <span
                        class="lr-tier-badge"
                        :style="{ borderColor: tierAfter.color, color: tierAfter.color }"
                        >{{ tierAfter.name }}</span
                    >
                </template>
            </div>

            <p class="lr-outcome" :class="{ up: promoted, down: demoted }">
                <template v-if="promoted">Promoted to {{ tierAfter.name }}!</template>
                <template v-else-if="demoted">Relegated to {{ tierAfter.name }}</template>
                <template v-else>Held your place in {{ tierAfter.name }}</template>
            </p>

            <p v-if="coinsEarned > 0" class="lr-coins">
                &#9673; +{{ coinsEarned }} placement coins
            </p>

            <button type="button" class="btn-primary lr-continue" @click="close">
                Continue
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { LeagueResultTier } from "../stores/league-result";

const { promoted, demoted } = defineProps<{
    rank: number;
    total: number;
    coinsEarned: number;
    promoted: boolean;
    demoted: boolean;
    tierBefore: LeagueResultTier;
    tierAfter: LeagueResultTier;
}>();

const emit = defineEmits<{ close: [] }>();

function close(): void {
    emit("close");
}
</script>

<style scoped>
.lr-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}

.lr-modal {
    position: relative;
    width: 100%;
    max-width: 420px;
    background: linear-gradient(
        180deg,
        rgba(30, 22, 13, 0.99),
        rgba(13, 9, 5, 0.99)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.5);
    border-radius: 18px;
    padding: 24px 20px 20px;
    text-align: center;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.7);
}

.lr-close {
    position: absolute;
    top: 10px;
    right: 14px;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.6rem;
    cursor: pointer;
    line-height: 1;
    box-shadow: none;
}

.lr-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 4px;
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
}

.lr-sub {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 18px;
}

.lr-placement {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 8px;
    margin-bottom: 16px;
}

.lr-rank {
    font-family: "Cinzel", serif;
    font-size: 2.6rem;
    font-weight: 800;
    color: var(--accent-gold);
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
}

.lr-of {
    color: var(--text-secondary);
    font-size: 1rem;
}

.lr-tiers {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 12px;
}

.lr-tier-badge {
    display: inline-block;
    padding: 4px 12px;
    border: 1.5px solid currentColor;
    border-radius: 11px;
    font-family: "Cinzel", serif;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.lr-arrow {
    font-size: 1.1rem;
}

.lr-arrow.up {
    color: var(--accent-green);
}

.lr-arrow.down {
    color: #d9534f;
}

.lr-outcome {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.lr-outcome.up {
    color: var(--accent-green);
    font-weight: 700;
}

.lr-outcome.down {
    color: #d9534f;
}

.lr-coins {
    font-family: "Cinzel", serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--accent-gold);
    margin-bottom: 18px;
}

.lr-continue {
    width: 100%;
    padding: 14px;
    font-size: 1.05rem;
}
</style>
