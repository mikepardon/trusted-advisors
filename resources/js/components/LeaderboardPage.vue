<template>
    <div class="leaderboard-page">
        <h2 class="section-title">Leaderboards</h2>

        <!-- Current Season Banner -->
        <div v-if="activeSeason" class="season-banner">
            <div class="season-banner-top">
                <span class="season-banner-name">{{ activeSeason.name }}</span>
                <span class="season-banner-dates"
                    >{{ formatDate(activeSeason.starts_at) }} &ndash;
                    {{ formatDate(activeSeason.ends_at) }}</span
                >
            </div>
            <div class="season-banner-bar">
                <div
                    class="season-banner-fill"
                    :style="{ width: seasonPercent + '%' }"
                ></div>
            </div>
            <div class="season-banner-time">{{ seasonTimeLeft }}</div>
        </div>

        <HintBubble hint-id="leaderboard-elo">
            Play <strong>online duels</strong> to earn ELO rating and climb the
            competitive rankings!
        </HintBubble>

        <!-- Tabs -->
        <div class="tab-row">
            <button
                class="tab-btn"
                :class="{ active: tab === 'global' }"
                @click="
                    tab = 'global';
                    fetchData();
                "
            >
                Global
            </button>
            <button
                class="tab-btn"
                :class="{ active: tab === 'friends' }"
                @click="
                    tab = 'friends';
                    fetchData();
                "
            >
                Friends
            </button>
            <button
                class="tab-btn"
                :class="{ active: tab === 'rewards' }"
                @click="
                    tab = 'rewards';
                    fetchRewards();
                "
            >
                Rewards
            </button>
        </div>

        <!-- Leaderboard view -->
        <template v-if="tab !== 'rewards'">
            <!-- Filters -->
            <div class="filters-row">
                <select
                    v-model="metric"
                    class="filter-select"
                    @change="onMetricChange"
                >
                    <option value="wins">Wins</option>
                    <option value="score">Score</option>
                    <option value="xp">XP</option>
                    <option value="elo">ELO</option>
                </select>
                <select
                    v-if="metric !== 'elo' && metric !== 'xp'"
                    v-model="seasonId"
                    class="filter-select"
                    @change="fetchData()"
                >
                    <option :value="null">All Seasons</option>
                    <option v-for="s in seasons" :key="s.id" :value="s.id">
                        {{ s.name }}
                    </option>
                </select>
                <select
                    v-if="metric === 'wins'"
                    v-model="gameType"
                    class="filter-select"
                    @change="fetchData()"
                >
                    <option :value="null">All Types</option>
                    <option value="cooperative">Cooperative</option>
                    <option value="duel">Duel</option>
                </select>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="lb-loading">Loading...</div>

            <!-- Empty -->
            <div v-else-if="entries.length === 0" class="lb-empty">
                No data yet.
            </div>

            <!-- Table -->
            <div v-else class="lb-table">
                <div
                    v-for="entry in entries"
                    :key="entry.user_id"
                    :ref="entry.is_current_user ? 'currentUserRow' : undefined"
                    class="lb-row"
                    :class="{ 'lb-current': entry.is_current_user }"
                >
                    <span class="lb-rank">{{ entry.rank }}</span>
                    <span
                        class="lb-name lb-clickable"
                        @click="showProfileUserId = entry.user_id"
                    >
                        {{ entry.username }}
                        <span class="lb-level">Lv.{{ entry.level }}</span>
                    </span>
                    <span class="lb-value">{{ formatValue(entry.value) }}</span>
                </div>
            </div>

            <!-- Floating current player row (when scrolled out of view) -->
            <div
                v-if="currentUserEntry && !currentUserVisible"
                class="lb-float"
            >
                <div class="lb-row lb-current lb-float-row">
                    <span class="lb-rank">{{ currentUserEntry.rank }}</span>
                    <span class="lb-name">
                        {{ currentUserEntry.username }}
                        <span class="lb-level"
                            >Lv.{{ currentUserEntry.level }}</span
                        >
                    </span>
                    <span class="lb-value">{{
                        formatValue(currentUserEntry.value)
                    }}</span>
                </div>
            </div>
        </template>

        <!-- Ranking Rewards view -->
        <template v-if="tab === 'rewards'">
            <div v-if="loadingRewards" class="lb-loading">
                Loading rewards...
            </div>
            <div
                v-else-if="Object.keys(rewardsByMetric).length === 0"
                class="lb-empty"
            >
                No ranking rewards set for this season.
            </div>
            <div v-else>
                <!-- Metric sub-tabs -->
                <div class="metric-tabs">
                    <button
                        v-for="m in rewardMetrics"
                        :key="m"
                        class="metric-tab"
                        :class="{ active: rewardMetric === m }"
                        @click="rewardMetric = m"
                    >
                        {{ metricLabel(m) }}
                    </button>
                </div>

                <!-- Reward cards for selected metric -->
                <div v-if="rewardsByMetric[rewardMetric]" class="rewards-grid">
                    <div
                        v-for="tier in rewardsByMetric[rewardMetric]"
                        :key="rewardMetric + '-' + tier.label"
                        class="reward-card"
                        :class="tier.tierClass"
                    >
                        <div class="reward-placement">{{ tier.label }}</div>
                        <div class="reward-details">
                            <span v-if="tier.xp" class="reward-item reward-xp"
                                >+{{ tier.xp }} XP</span
                            >
                            <span
                                v-if="tier.coins"
                                class="reward-item reward-coins"
                                >+{{ tier.coins }} &#129689;</span
                            >
                            <span
                                v-if="tier.character"
                                class="reward-item reward-char"
                                >&#128081; {{ tier.character }}</span
                            >
                            <span
                                v-if="tier.title"
                                class="reward-item reward-title"
                                >&#127941; "{{ tier.title }}"</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <PlayerProfile
            v-if="showProfileUserId"
            :user-id="showProfileUserId"
            @close="showProfileUserId = null"
        />
    </div>
</template>

<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
} from "vue";
import axios from "axios";
import HintBubble from "./HintBubble.vue";
import PlayerProfile from "./PlayerProfile.vue";

interface LeaderboardEntry {
    user_id: number;
    rank: number;
    username: string;
    level: number;
    value: number;
    is_current_user: boolean;
}

interface Season {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
}

interface RewardCharacter {
    name?: string;
}

interface Reward {
    metric?: string;
    placement: number;
    reward_xp?: number;
    reward_coins?: number;
    reward_character_id?: number;
    reward_title?: string;
    reward_character?: RewardCharacter;
}

interface RewardTier {
    label: string;
    xp: number;
    coins: number;
    character: string | undefined;
    title: string | undefined;
    tierClass: string;
}

interface LeaderboardParameters {
    metric: string;
    season_id?: number;
    game_type?: string;
}

const tab = ref("global");
const showProfileUserId = ref<number | undefined>(undefined);
const metric = ref("elo");
const seasonId = ref<number | undefined>(undefined);
const gameType = ref<string | undefined>(undefined);
const entries = ref<LeaderboardEntry[]>([]);
const seasons = ref<Season[]>([]);
const loading = ref(true);
const currentUserVisible = ref(true);
const loadingRewards = ref(false);
const rewardsByMetric = ref<Record<string, RewardTier[]>>({});
const rewardMetric = ref("elo");

const currentUserRow = useTemplateRef<HTMLElement | HTMLElement[]>(
    "currentUserRow",
);

const observer = ref<IntersectionObserver | undefined>(undefined);

const currentUserEntry = computed<LeaderboardEntry | undefined>(() =>
    entries.value.find((entry) => entry.is_current_user),
);

const rewardMetrics = computed<string[]>(() =>
    Object.keys(rewardsByMetric.value),
);

const activeSeason = computed<Season | undefined>(() => {
    const now = new Date();
    return seasons.value.find(
        (season) =>
            new Date(season.starts_at) <= now &&
            new Date(season.ends_at) >= now,
    );
});

const seasonPercent = computed<number>(() => {
    if (!activeSeason.value) {
        return 0;
    }
    const start = new Date(activeSeason.value.starts_at).getTime();
    const end = new Date(activeSeason.value.ends_at).getTime();
    const now = Date.now();
    return Math.min(100, Math.max(0, ((now - start) / (end - start)) * 100));
});

const seasonTimeLeft = computed<string>(() => {
    if (!activeSeason.value) {
        return "";
    }
    const end = new Date(activeSeason.value.ends_at).getTime();
    const diff = end - Date.now();
    if (diff <= 0) {
        return "Ended";
    }
    const days = Math.floor(diff / 86_400_000);
    if (days > 1) {
        return `${days} days left`;
    }
    const hours = Math.floor(diff / 3_600_000);
    return `${hours} hours left`;
});

onMounted(async () => {
    try {
        const response = await axios.get("/api/seasons");
        seasons.value = response.data;
    } catch {
        // Ignore season fetch failures; leaderboard still loads.
    }
    fetchData();
});

onBeforeUnmount(() => {
    observer.value?.disconnect();
});

function onMetricChange(): void {
    if (metric.value === "score") {
        if (activeSeason.value) {
            seasonId.value = activeSeason.value.id;
        }
        gameType.value = "cooperative";
    } else if (metric.value === "elo" || metric.value === "xp") {
        seasonId.value = undefined;
        gameType.value = undefined;
    }
    fetchData();
}

async function fetchData(): Promise<void> {
    loading.value = true;
    try {
        const parameters: LeaderboardParameters = { metric: metric.value };
        if (seasonId.value) {
            parameters.season_id = seasonId.value;
        }
        if (gameType.value) {
            parameters.game_type = gameType.value;
        }

        const url =
            tab.value === "friends"
                ? "/api/leaderboards/friends"
                : "/api/leaderboards/global";
        const response = await axios.get(url, { params: parameters });
        entries.value = response.data;
    } catch {
        // Ignore fetch failures; empty state handles it.
    }
    loading.value = false;
    nextTick(() => setupVisibilityObserver());
}

async function fetchRewards(): Promise<void> {
    if (!activeSeason.value) {
        return;
    }
    loadingRewards.value = true;
    try {
        const response = await axios.get(
            `/api/seasons/${activeSeason.value.id}`,
        );
        const rewards = response.data.season?.rewards || [];
        rewardsByMetric.value = buildGroupedTiers(rewards);
        const metrics = Object.keys(rewardsByMetric.value);
        if (metrics.length > 0 && !metrics.includes(rewardMetric.value)) {
            rewardMetric.value = metrics[0];
        }
    } catch {
        // Ignore reward fetch failures; empty state handles it.
    }
    loadingRewards.value = false;
}

function buildGroupedTiers(rewards: Reward[]): Record<string, RewardTier[]> {
    if (rewards.length === 0) {
        return {};
    }

    // Group by metric first
    const byMetric: Record<string, Reward[]> = {};
    for (const reward of rewards) {
        const key = reward.metric || "elo";
        if (!Object.hasOwn(byMetric, key)) {
            byMetric[key] = [];
        }
        byMetric[key].push(reward);
    }

    // Build tiers per metric
    const result: Record<string, RewardTier[]> = {};
    for (const [key, items] of Object.entries(byMetric)) {
        const sorted = items.toSorted((a, b) => a.placement - b.placement);
        result[key] = buildTiers(sorted);
    }
    return result;
}

function buildTiers(rewards: Reward[]): RewardTier[] {
    const tiers: RewardTier[] = [];
    let index = 0;
    while (index < rewards.length) {
        const current = rewards[index];
        let end = index;
        while (
            end + 1 < rewards.length &&
            rewards[end + 1].reward_xp === current.reward_xp &&
            rewards[end + 1].reward_coins === current.reward_coins &&
            rewards[end + 1].reward_character_id ===
                current.reward_character_id &&
            rewards[end + 1].reward_title === current.reward_title
        ) {
            end++;
        }

        const startPlace = current.placement;
        const endPlace = rewards[end].placement;
        const label =
            startPlace === endPlace
                ? ordinal(startPlace)
                : `${ordinal(startPlace)} - ${ordinal(endPlace)}`;

        let tierClass = "";
        if (startPlace === 1) {
            tierClass = "reward-gold";
        } else if (startPlace <= 3) {
            tierClass = "reward-silver";
        } else if (startPlace <= 10) {
            tierClass = "reward-bronze";
        }

        tiers.push({
            label,
            xp: current.reward_xp || 0,
            coins: current.reward_coins || 0,
            character: current.reward_character?.name ?? undefined,
            title: current.reward_title ?? undefined,
            tierClass,
        });

        index = end + 1;
    }
    return tiers;
}

function metricLabel(key: string): string {
    const labels: Record<string, string> = {
        elo: "ELO Rating",
        score: "Highest Score",
        wins: "Most Wins",
    };
    return labels[key] || key;
}

function ordinal(value: number): string {
    const suffixes = ["th", "st", "nd", "rd"];
    const remainder = value % 100;
    return (
        value +
        (suffixes[(remainder - 20) % 10] || suffixes[remainder] || suffixes[0])
    );
}

function setupVisibilityObserver(): void {
    observer.value?.disconnect();
    const rowReference = currentUserRow.value;
    const row = Array.isArray(rowReference) ? rowReference[0] : rowReference;
    if (!row) {
        currentUserVisible.value = !currentUserEntry.value;
        return;
    }
    observer.value = new IntersectionObserver(
        ([entry]) => {
            currentUserVisible.value = entry.isIntersecting;
        },
        { threshold: 0.5 },
    );
    observer.value.observe(row);
}

function formatValue(value: number): number | string {
    if (value >= 1000) {
        return `${(value / 1000).toFixed(1)}k`;
    }
    return value;
}

function formatDate(dateString: string): string {
    if (!dateString) {
        return "";
    }
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
    });
}
</script>

<style scoped>
.leaderboard-page {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
}

.section-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.3rem;
    margin-bottom: 16px;
    text-align: center;
}

/* Season Banner */
.season-banner {
    background: linear-gradient(180deg, #2a1f14, #1a1209);
    border: 1px solid rgba(138, 106, 46, 0.3);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 14px;
}

.season-banner-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.season-banner-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.95rem;
    font-weight: 700;
}

.season-banner-dates {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.season-banner-bar {
    width: 100%;
    height: 5px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 4px;
}

.season-banner-fill {
    height: 100%;
    background: linear-gradient(90deg, #8a6a2e, #d4a843);
    border-radius: 3px;
    transition: width 0.5s ease;
}

.season-banner-time {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-align: right;
}

.tab-row {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-bottom: 12px;
}

.tab-btn {
    padding: 6px 16px;
    font-size: 0.8rem;
}

.tab-btn.active {
    background: var(--accent-gold);
    border-color: var(--accent-gold);
    color: black;
    box-shadow:
        0 4px 0 #7a5a14,
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.filters-row {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.filter-select {
    background: var(--bg-primary);
    border: 1px solid rgba(138, 106, 46, 0.3);
    color: var(--text-bright);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-family: inherit;
}

.lb-loading,
.lb-empty {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 30px;
}

.lb-table {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lb-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
    border: 1px solid rgba(138, 106, 46, 0.2);
    border-radius: 6px;
}

.lb-row.lb-current {
    border-color: var(--accent-gold);
    background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
}

.lb-rank {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.1rem;
    font-weight: 700;
    min-width: 30px;
    text-align: center;
}

.lb-name {
    flex: 1;
    color: var(--text-bright);
    font-size: 0.95rem;
}

.lb-clickable {
    cursor: pointer;
}

.lb-clickable:hover {
    color: var(--accent-gold);
    text-decoration: underline;
}

.lb-level {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin-left: 6px;
}

.lb-value {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1rem;
    font-weight: 700;
}

/* Floating current user row */
.lb-float {
    position: sticky;
    bottom: 0;
    padding: 8px 0 0;
    background: linear-gradient(180deg, transparent, var(--bg-primary) 30%);
}

.lb-float-row {
    box-shadow: 0 -2px 12px rgba(212, 168, 67, 0.3);
    border-color: var(--accent-gold);
    animation: floatGlow 2s ease-in-out infinite;
}

@keyframes floatGlow {
    0%,
    100% {
        box-shadow: 0 -2px 12px rgba(212, 168, 67, 0.2);
    }
    50% {
        box-shadow: 0 -2px 20px rgba(212, 168, 67, 0.4);
    }
}

/* Ranking Rewards */
.metric-tabs {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-bottom: 14px;
}

.metric-tab {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(138, 106, 46, 0.25);
    color: var(--text-secondary);
    padding: 5px 16px;
    border-radius: 5px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}

.metric-tab.active {
    background: rgba(212, 168, 67, 0.12);
    border-color: var(--accent-gold);
    color: var(--accent-gold);
}

.rewards-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
}

.reward-card {
    background: linear-gradient(180deg, #2a1f14, #1a1209);
    border: 2px solid rgba(138, 106, 46, 0.25);
    border-radius: 10px;
    padding: 16px 20px;
    min-width: 140px;
    flex: 1;
    max-width: 200px;
    text-align: center;
}

.reward-card.reward-gold {
    border-color: #d4a843;
    background: linear-gradient(180deg, #3a2a10, #1a1209);
    box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.reward-card.reward-silver {
    border-color: #a0a0a0;
    background: linear-gradient(180deg, #2a2a2a, #1a1209);
}

.reward-card.reward-bronze {
    border-color: #8a5c2e;
}

.reward-placement {
    font-family: "Cinzel", serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--accent-gold);
    margin-bottom: 10px;
}

.reward-gold .reward-placement {
    font-size: 1.3rem;
}

.reward-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.reward-item {
    font-size: 0.85rem;
    color: var(--text-bright);
}

.reward-xp {
    color: #7ec8e3;
}

.reward-coins {
    color: #d4a843;
}

.reward-char {
    color: #e0b0ff;
}

.reward-title {
    color: #90ee90;
    font-style: italic;
}

@media (max-width: 768px) {
    .filters-row {
        flex-direction: column;
        align-items: stretch;
    }

    .reward-card {
        min-width: 120px;
        padding: 12px 14px;
    }

    .season-banner-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
}
</style>
