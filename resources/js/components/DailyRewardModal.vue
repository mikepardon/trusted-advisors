<template>
    <div class="daily-reward-overlay" @click.self="close">
        <div class="daily-reward-modal">
            <button type="button" class="dr-close" @click="close">
                &times;
            </button>
            <h2 class="dr-title">Daily Reward</h2>
            <p class="dr-streak">
                <span class="dr-flame">&#128293;</span> {{ streak }} day streak
            </p>

            <div class="dr-ladder">
                <div
                    v-for="(entry, index) in ladder"
                    :key="index"
                    class="dr-day"
                    :class="{
                        claimed:
                            index + 1 < day ||
                            (!available && index + 1 === day),
                        today: available && index + 1 === day,
                        big: index + 1 === 7,
                    }"
                >
                    <span class="dr-day-label">Day {{ index + 1 }}</span>
                    <span
                        class="dr-day-coins"
                        :class="{ 'dr-day-xp': entry.type === 'xp' }"
                    >
                        {{ entry.type === "xp" ? "★" : "◉" }} {{ entry.amount
                        }}{{ entry.type === "xp" ? " XP" : "" }}
                    </span>
                    <span
                        v-if="
                            index + 1 < day || (!available && index + 1 === day)
                        "
                        class="dr-check"
                        >&#10004;</span
                    >
                </div>
            </div>

            <button
                v-if="available"
                class="btn-primary dr-claim"
                :disabled="claiming"
                @click="claim"
            >
                {{
                    claiming
                        ? "Claiming…"
                        : `Claim ${reward.amount} ${reward.type === "xp" ? "XP" : "coins"}!`
                }}
            </button>
            <button v-else type="button" class="dr-claim dr-claimed" disabled>
                Claimed today &middot; back tomorrow
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import axios, { isAxiosError } from "axios";
import { useAuth } from "../stores/auth";
import type { DailyRewardEntry } from "../stores/daily-reward";

const { day, reward, available } = defineProps<{
    day: number;
    streak: number;
    ladder: DailyRewardEntry[];
    reward: DailyRewardEntry;
    available: boolean;
}>();

const emit = defineEmits<{ close: []; claimed: [reward: DailyRewardEntry] }>();

const auth = useAuth();
const claiming = ref(false);

interface ClaimResponse {
    type: "coins" | "xp";
    amount: number;
    new_coins: number;
    new_xp: number;
    new_level: number;
}

function close(): void {
    emit("close");
}

async function claim(): Promise<void> {
    if (claiming.value) {
        return;
    }
    claiming.value = true;

    try {
        const response = await axios.post<ClaimResponse>("/api/daily-reward/claim");
        const data = response.data;

        // The reveal overlay (triggered by the parent) plays its own sound + haptics.
        if (data.type === "xp") {
            auth.updateUserStats({ xp: data.new_xp, level: data.new_level });
        } else {
            auth.updateUserStats({ coins: data.new_coins });
        }

        emit("claimed", { type: data.type, amount: data.amount });
        close();
    } catch (error) {
        // Already claimed (e.g. a race across two tabs) — just close quietly.
        if (isAxiosError(error) && error.response?.status === 422) {
            close();
            return;
        }
        claiming.value = false;
    }
}
</script>

<style scoped>
.daily-reward-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}

.daily-reward-modal {
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

.dr-close {
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

.dr-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 4px;
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
}

.dr-streak {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 18px;
}

.dr-flame {
    filter: drop-shadow(0 0 6px #f78154);
}

.dr-ladder {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 20px;
}

.dr-day {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 10px 4px;
    border-radius: 11px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.18);
    opacity: 0.72;
}

.dr-day.claimed {
    opacity: 0.5;
    background: linear-gradient(
        180deg,
        rgba(92, 184, 92, 0.12),
        rgba(0, 0, 0, 0.4)
    );
    border-color: rgba(142, 240, 200, 0.3);
}

.dr-day.today {
    opacity: 1;
    border-color: var(--accent-gold);
    box-shadow: 0 0 14px rgba(240, 192, 80, 0.35);
    transform: scale(1.05);
}

.dr-day.big {
    grid-column: span 4;
    background: linear-gradient(
        135deg,
        rgba(240, 192, 80, 0.2),
        rgba(120, 80, 20, 0.15)
    );
    border-color: rgba(240, 192, 80, 0.4);
}

.dr-day-label {
    font-family: "Cinzel", serif;
    font-size: 0.72rem;
    color: var(--text-secondary);
}

.dr-day-coins {
    font-family: "Cinzel", serif;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--accent-gold);
    white-space: nowrap;
}

/* XP reward days read cyan to set them apart from the gold coin days. */
.dr-day-xp {
    color: #7fd4ff;
}

.dr-check {
    position: absolute;
    top: 3px;
    right: 5px;
    color: var(--accent-green);
    font-size: 0.7rem;
}

.dr-claim {
    width: 100%;
    padding: 14px;
    font-size: 1.05rem;
}

.dr-claimed {
    width: 100%;
    padding: 14px;
    border: 1px solid rgba(142, 240, 200, 0.35);
    border-radius: 12px;
    background: rgba(92, 184, 92, 0.12);
    color: var(--accent-green);
    font-family: "Cinzel", serif;
    font-weight: 700;
    cursor: default;
}
</style>
