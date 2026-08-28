<template>
    <section v-if="!loading && !complete && steps.length > 0" class="oj">
        <div class="oj-head" @click="collapsed = !collapsed">
            <div class="oj-head-info">
                <span class="oj-eyebrow">Getting Started</span>
                <span class="oj-progress">{{ claimedCount }}/{{ steps.length }} claimed</span>
                <span v-if="readyCount > 0" class="oj-ready">{{ readyCount }} to claim</span>
            </div>
            <span class="oj-toggle">{{ collapsed ? "▾" : "▴" }}</span>
        </div>

        <div v-if="!collapsed" class="oj-body">
            <div class="oj-bar">
                <div class="oj-bar-fill" :style="{ width: barWidth }"></div>
            </div>

            <ul class="oj-list">
                <li
                    v-for="step in steps"
                    :key="step.key"
                    class="oj-step"
                    :class="{ 'oj-step--claimed': step.claimed, 'oj-step--ready': step.done && !step.claimed }"
                >
                    <span class="oj-check">{{ step.claimed ? "✓" : step.done ? "★" : "○" }}</span>
                    <span class="oj-step-info">
                        <span class="oj-step-title">{{ step.title }}</span>
                        <span class="oj-step-desc">{{ step.description }}</span>
                    </span>
                    <span class="oj-reward">
                        {{ step.reward.type === "coins" ? "🪙" : "✦" }}
                        {{ step.reward.amount }}
                    </span>
                    <button
                        v-if="step.done && !step.claimed"
                        class="oj-claim"
                        :disabled="claiming === step.key"
                        @click="claim(step.key)"
                    >
                        {{ claiming === step.key ? "…" : "Claim" }}
                    </button>
                    <span v-else-if="step.claimed" class="oj-done">Claimed</span>
                    <span v-else class="oj-locked">Locked</span>
                </li>
            </ul>
        </div>
    </section>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import { useToast } from "../stores/toast";

interface Reward {
    type: string;
    amount: number;
}

interface Step {
    key: string;
    title: string;
    description: string;
    reward: Reward;
    done: boolean;
    claimed: boolean;
}

const toast = useToast();

const loading = ref(true);
const complete = ref(false);
// Start collapsed so it doesn't crowd out the hub; the "N to claim" nudge draws the eye.
const collapsed = ref(true);
const steps = ref<Step[]>([]);
const claiming = ref<string | undefined>(undefined);

const claimedCount = computed<number>(() => steps.value.filter((step) => step.claimed).length);
const readyCount = computed<number>(
    () => steps.value.filter((step) => step.done && !step.claimed).length,
);
const barWidth = computed<string>(() =>
    steps.value.length === 0 ? "0%" : `${(claimedCount.value / steps.value.length) * 100}%`,
);

function apply(data: { steps: Step[]; complete: boolean }): void {
    steps.value = data.steps;
    complete.value = data.complete;
}

async function claim(key: string): Promise<void> {
    claiming.value = key;
    try {
        const { data } = await axios.post<{ steps: Step[]; complete: boolean }>(
            `/api/onboarding/${key}/claim`,
        );
        apply(data);
        toast.success("Reward claimed!");
    } catch {
        toast.error("Could not claim that reward.");
    } finally {
        claiming.value = undefined;
    }
}

onMounted(async () => {
    try {
        const { data } = await axios.get<{ steps: Step[]; complete: boolean }>("/api/onboarding");
        apply(data);
    } catch {
        // Silent — the journey is a bonus surface; never block the hub.
        complete.value = true;
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.oj {
    background: linear-gradient(180deg, #2f2417, #241b12);
    border: 2px solid #c9a24b;
    border-radius: 12px;
    margin: 0 auto 1rem;
    max-width: 640px;
    color: #f3e9d2;
    overflow: hidden;
}

.oj-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 0.9rem;
    cursor: pointer;
}

.oj-head-info {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
    min-width: 0;
}

.oj-eyebrow {
    font-family: "Cinzel", serif;
    color: #e8c667;
    font-weight: 700;
    font-size: 0.95rem;
}

.oj-progress {
    font-size: 0.72rem;
    opacity: 0.75;
}

.oj-ready {
    font-size: 0.66rem;
    font-weight: 700;
    color: #241b12;
    background: linear-gradient(180deg, #ffd873, #c9a24b);
    border-radius: 999px;
    padding: 0.1rem 0.5rem;
    white-space: nowrap;
}

.oj-toggle {
    color: #c9a24b;
    font-size: 0.9rem;
}

.oj-body {
    padding: 0 0.9rem 0.9rem;
}

.oj-bar {
    height: 6px;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.7rem;
}

.oj-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #c9a24b, #ffd873);
    transition: width 0.3s;
}

.oj-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.oj-step {
    display: grid;
    grid-template-columns: 22px 1fr auto auto;
    align-items: center;
    gap: 0.6rem;
    background: rgba(0, 0, 0, 0.28);
    border: 1px solid rgba(201, 162, 75, 0.18);
    border-radius: 8px;
    padding: 0.5rem 0.6rem;
}

.oj-step--ready {
    border-color: #ffd873;
    background: linear-gradient(90deg, rgba(255, 216, 115, 0.15), rgba(0, 0, 0, 0.28));
}

.oj-step--claimed {
    opacity: 0.65;
}

.oj-check {
    text-align: center;
    color: #c9a24b;
    font-weight: 800;
}

.oj-step--ready .oj-check {
    color: #ffd873;
}

.oj-step-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.oj-step-title {
    font-weight: 700;
    font-size: 0.82rem;
    font-family: "Cinzel", serif;
}

.oj-step-desc {
    font-size: 0.7rem;
    opacity: 0.7;
}

.oj-reward {
    font-size: 0.76rem;
    font-weight: 700;
    color: #e8c667;
    white-space: nowrap;
}

.oj-claim {
    background: #c9a24b;
    color: #241b12;
    border: none;
    border-radius: 6px;
    padding: 0.32rem 0.7rem;
    font-weight: 700;
    font-family: "Cinzel", serif;
    font-size: 0.74rem;
    cursor: pointer;
}

.oj-claim:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.oj-done,
.oj-locked {
    font-size: 0.7rem;
    opacity: 0.6;
    white-space: nowrap;
}
</style>
