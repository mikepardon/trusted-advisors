<template>
    <TaSubPage title="Challenges" back="/">
        <div class="challenges-page">
            <div v-if="loading" class="cp-loading">Loading…</div>

            <template v-else>
                <!-- Today -->
                <section class="cp-today">
                    <span class="cp-label">Today's Trial</span>

                    <div v-if="today" class="cp-today-card">
                        <h2 class="cp-today-title">{{ today.title }}</h2>
                        <p class="cp-today-goal">{{ today.description }}</p>
                        <p v-if="today.reward_xp" class="cp-reward">
                            +{{ today.reward_xp }} XP
                        </p>

                        <div v-if="today.character" class="cp-advisor">
                            <img
                                v-if="today.character.image_url"
                                :src="today.character.image_url"
                                :alt="today.character.name"
                                class="cp-advisor-img"
                            />
                            <div class="cp-advisor-info">
                                <span class="cp-advisor-name">{{
                                    today.character.name
                                }}</span>
                                <p
                                    v-if="today.character.wild_ability_description"
                                    class="cp-advisor-wild"
                                >
                                    ✦
                                    {{ today.character.wild_ability_description }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="today.loadout.length > 0"
                            class="cp-loadout"
                        >
                            <div
                                v-for="(item, index) in today.loadout"
                                :key="index"
                                class="cp-loadout-item"
                            >
                                <span class="cp-loadout-glyph">{{
                                    glyph(item.type)
                                }}</span>
                                <span class="cp-loadout-name">
                                    {{ item.name }}
                                    <span class="cp-loadout-cadence">{{
                                        cadenceLabel(item.cadence)
                                    }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="cp-today-action">
                            <button
                                v-if="today.status === 'pending'"
                                class="cp-play"
                                :disabled="startingToday"
                                @click="startToday"
                            >
                                {{ startingToday ? "Entering…" : "Play" }}
                            </button>
                            <button
                                v-else-if="today.status === 'in_progress'"
                                class="cp-resume"
                                @click="resume(today.game_id)"
                            >
                                Resume
                            </button>
                            <span
                                v-else-if="today.status === 'won'"
                                class="cp-badge cp-badge--won"
                            >
                                ✓ Completed in
                                {{ today.rounds_taken ?? "—" }} months
                            </span>
                            <span
                                v-else
                                class="cp-badge cp-badge--attempted"
                            >
                                Attempted
                            </span>

                            <button
                                class="cp-leaderboard"
                                @click="openLeaderboard(today.id, today.title)"
                            >
                                🏆 Leaderboard
                            </button>
                        </div>

                        <p v-if="today.plays > 0" class="cp-stats">
                            {{ platformStats(today) }}
                        </p>
                    </div>

                    <p v-else class="cp-empty">
                        No challenge available today — check back tomorrow.
                    </p>
                </section>

                <!-- History -->
                <section class="cp-history">
                    <h3 class="cp-history-heading">Past Trials</h3>
                    <p v-if="!isPremium" class="cp-upsell">
                        🔒 Replaying past challenges is a premium feature.
                    </p>

                    <p v-if="history.length === 0" class="cp-empty">
                        No past challenges yet.
                    </p>

                    <div v-else class="cp-history-list">
                        <div
                            v-for="challenge in history"
                            :key="challenge.id"
                            class="cp-history-item"
                        >
                            <div class="cp-history-main">
                                <span class="cp-history-date">{{
                                    challenge.date
                                }}</span>
                                <span class="cp-history-title">{{
                                    challenge.title
                                }}</span>
                                <span class="cp-history-goal">{{
                                    challenge.description
                                }}</span>
                                <span class="cp-history-reward"
                                    >+{{ challenge.reward_xp }} XP</span
                                >
                                <span
                                    v-if="challenge.plays > 0"
                                    class="cp-stats cp-stats--history"
                                >
                                    {{ platformStats(challenge) }}
                                </span>
                            </div>

                            <div class="cp-history-action">
                                <button
                                    class="cp-leaderboard cp-leaderboard--icon"
                                    aria-label="Leaderboard"
                                    @click="openLeaderboard(challenge.id, challenge.title)"
                                >
                                    🏆
                                </button>
                                <span
                                    v-if="challenge.status === 'won'"
                                    class="cp-badge cp-badge--won"
                                >
                                    ✓ {{ challenge.rounds_taken ?? "—" }} mo
                                </span>
                                <span
                                    v-else-if="challenge.status === 'lost'"
                                    class="cp-badge cp-badge--lost"
                                >
                                    ✗
                                </span>
                                <button
                                    v-else-if="challenge.status === 'in_progress'"
                                    class="cp-resume"
                                    @click="resume(challenge.game_id)"
                                >
                                    Resume
                                </button>
                                <button
                                    v-else-if="isPremium"
                                    class="cp-play"
                                    :disabled="startingId === challenge.id"
                                    @click="startPast(challenge)"
                                >
                                    {{
                                        startingId === challenge.id
                                            ? "…"
                                            : "Play"
                                    }}
                                </button>
                                <button
                                    v-else
                                    class="cp-lock"
                                    aria-label="Upgrade to premium"
                                    @click="goPremium"
                                >
                                    🔒
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </template>

            <DailyLeaderboard
                v-if="leaderboard"
                :challenge-id="leaderboard.id"
                :title="leaderboard.title"
                @close="leaderboard = undefined"
            />
        </div>
    </TaSubPage>
</template>

<script setup lang="ts">
import axios, { isAxiosError } from "axios";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "../stores/toast";
import TaSubPage from "./TaSubPage.vue";
import DailyLeaderboard from "./DailyLeaderboard.vue";

type ChallengeStatus = "pending" | "in_progress" | "won" | "lost";

interface ChallengeGoal {
    type: string;
    stat?: string;
    value?: number;
    targets?: Record<string, number>;
}

interface ChallengeCharacter {
    name: string;
    image_url?: string;
    description?: string;
    wild_ability_description?: string;
}

interface LoadoutItem {
    name: string;
    type?: string;
    cadence?: string;
    description?: string;
}

interface PlatformStats {
    avg_rounds: number | undefined;
    success_rate: number | undefined;
    plays: number;
}

interface TodayChallenge extends PlatformStats {
    id: number;
    date: string;
    title: string;
    description: string;
    goal: ChallengeGoal;
    reward_xp: number;
    status: ChallengeStatus;
    game_id: number | undefined;
    rounds_taken: number | undefined;
    character: ChallengeCharacter | undefined;
    loadout: LoadoutItem[];
}

interface HistoryChallenge extends PlatformStats {
    id: number;
    date: string;
    title: string;
    description: string;
    goal: ChallengeGoal;
    reward_xp: number;
    status: ChallengeStatus;
    game_id: number | undefined;
    rounds_taken: number | undefined;
}

const TYPE_GLYPHS: Record<string, string> = {
    weapon: "⚔️",
    armour: "🛡️",
    potion: "🧪",
    scroll: "📜",
    relic: "✨",
    coin: "💰",
    hex: "🔮",
};

const CADENCE_LABELS: Record<string, string> = {
    passive: "Always active",
    per_round: "Once per round",
    per_game: "Once per game",
};

function glyph(type?: string): string {
    return (type && TYPE_GLYPHS[type]) || "⚔";
}

function cadenceLabel(cadence?: string): string {
    return (cadence && CADENCE_LABELS[cadence]) || "";
}

function platformStats(stats: PlatformStats): string {
    const avg = stats.avg_rounds == undefined ? "—" : `${stats.avg_rounds} mo`;
    return `Avg ${avg} · ${stats.success_rate ?? 0}% success · ${stats.plays} plays`;
}

const router = useRouter();
const toast = useToast();

const loading = ref(true);
const isPremium = ref(false);
const today = ref<TodayChallenge | undefined>(undefined);
const history = ref<HistoryChallenge[]>([]);
const startingToday = ref(false);
const startingId = ref<number | undefined>(undefined);
const leaderboard = ref<{ id: number; title: string } | undefined>(undefined);

function openLeaderboard(id: number, title: string): void {
    leaderboard.value = { id, title };
}

function resume(gameId: number | undefined): void {
    if (gameId != undefined) {
        void router.push(`/game/${gameId}`);
    }
}

function goPremium(): void {
    void router.push("/premium");
}

async function startToday(): Promise<void> {
    startingToday.value = true;
    try {
        const { data } = await axios.post<{ game_id: number }>(
            "/api/daily-challenge/start",
        );
        void router.push(`/game/${data.game_id}`);
    } catch {
        toast.error("Could not start today's challenge.");
        startingToday.value = false;
    }
}

async function startPast(challenge: HistoryChallenge): Promise<void> {
    startingId.value = challenge.id;
    try {
        const { data } = await axios.post<{ game_id: number }>(
            `/api/daily-challenges/${challenge.id}/start`,
        );
        void router.push(`/game/${data.game_id}`);
    } catch (error) {
        if (isAxiosError(error) && error.response?.status === 409) {
            toast.error("You have already played that challenge.");
        } else {
            toast.error("Could not start that challenge.");
        }
        startingId.value = undefined;
    }
}

onMounted(async () => {
    try {
        const { data } = await axios.get<{
            is_premium: boolean;
            today: TodayChallenge | undefined;
            history: HistoryChallenge[];
        }>("/api/challenges");
        isPremium.value = data.is_premium;
        today.value = data.today ?? undefined;
        history.value = data.history;
    } catch {
        toast.error("Could not load challenges.");
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.challenges-page {
    max-width: 640px;
    margin: 0 auto;
    color: #f3e9d2;
}

.cp-loading {
    text-align: center;
    opacity: 0.7;
    padding: 3rem 1rem;
    font-style: italic;
}

.cp-label {
    font-size: 0.65rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #e8c667;
    font-family: "Cinzel", serif;
}

/* Today */
.cp-today {
    margin-bottom: 1.75rem;
}

.cp-today-card {
    margin-top: 0.5rem;
    background: linear-gradient(180deg, #2f2417, #241b12);
    border: 2px solid #c9a24b;
    border-radius: 12px;
    padding: 1.1rem;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.45);
}

.cp-today-title {
    font-family: "Cinzel", serif;
    color: #e8c667;
    font-size: 1.35rem;
    margin: 0 0 0.4rem;
}

.cp-today-goal {
    margin: 0;
    font-size: 0.92rem;
    line-height: 1.4;
}

.cp-reward {
    margin: 0.4rem 0 0;
    color: #c9a24b;
    font-size: 0.85rem;
    font-weight: 700;
}

.cp-advisor {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    margin-top: 0.9rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(201, 162, 75, 0.25);
}

.cp-advisor-img {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid rgba(201, 162, 75, 0.4);
    flex-shrink: 0;
}

.cp-advisor-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.cp-advisor-name {
    font-weight: 700;
    font-family: "Cinzel", serif;
    color: #f3e9d2;
    font-size: 0.9rem;
}

.cp-advisor-wild {
    margin: 0;
    font-size: 0.75rem;
    color: #c9a24b;
    line-height: 1.3;
}

.cp-loadout {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.75rem;
}

.cp-loadout-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: #2f2417;
    border: 1px solid rgba(201, 162, 75, 0.25);
    border-radius: 6px;
    padding: 0.3rem 0.55rem;
}

.cp-loadout-glyph {
    font-size: 1rem;
}

.cp-loadout-name {
    font-size: 0.78rem;
    font-weight: 600;
}

.cp-loadout-cadence {
    font-size: 0.66rem;
    color: #c9a24b;
    margin-left: 0.3rem;
}

.cp-today-action {
    margin-top: 1rem;
}

/* History */
.cp-history-heading {
    font-family: "Cinzel", serif;
    color: #e8c667;
    font-size: 1.15rem;
    margin: 0 0 0.5rem;
}

.cp-upsell {
    margin: 0 0 0.75rem;
    font-size: 0.82rem;
    color: #c9a24b;
}

.cp-history-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.cp-history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: #2f2417;
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
}

.cp-history-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.1rem;
}

.cp-history-date {
    font-size: 0.62rem;
    color: #c9a24b;
    letter-spacing: 1px;
}

.cp-history-title {
    font-family: "Cinzel", serif;
    font-weight: 700;
    font-size: 0.88rem;
}

.cp-history-goal {
    font-size: 0.74rem;
    opacity: 0.75;
    line-height: 1.3;
}

.cp-history-reward {
    font-size: 0.7rem;
    color: #c9a24b;
    font-weight: 700;
}

.cp-history-action {
    flex-shrink: 0;
}

/* Shared stats */
.cp-stats {
    display: block;
    margin: 0.6rem 0 0;
    font-size: 0.72rem;
    opacity: 0.7;
}

.cp-stats--history {
    margin-top: 0.2rem;
}

/* Buttons and badges */
.cp-play,
.cp-resume {
    background: #c9a24b;
    color: #241b12;
    border: none;
    border-radius: 6px;
    padding: 0.45rem 1rem;
    font-weight: 700;
    font-family: "Cinzel", serif;
    cursor: pointer;
}

.cp-resume {
    background: transparent;
    color: #e8c667;
    border: 1px solid #c9a24b;
}

.cp-play:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.cp-lock {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0.8;
    padding: 0.2rem;
}

.cp-leaderboard {
    margin-left: 0.6rem;
    background: transparent;
    color: #e8c667;
    border: 1px solid #c9a24b;
    border-radius: 6px;
    padding: 0.4rem 0.75rem;
    font-family: "Cinzel", serif;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
}

.cp-leaderboard--icon {
    margin-left: 0;
    margin-right: 0.4rem;
    padding: 0.3rem 0.5rem;
    font-size: 0.95rem;
}

.cp-badge {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 700;
    font-family: "Cinzel", serif;
    white-space: nowrap;
}

.cp-badge--won {
    color: #6abf50;
}

.cp-badge--lost {
    color: #c98a7a;
}

.cp-badge--attempted {
    color: #a89574;
    opacity: 0.8;
}

.cp-empty {
    padding: 1.25rem;
    text-align: center;
    opacity: 0.7;
    font-size: 0.85rem;
}
</style>
