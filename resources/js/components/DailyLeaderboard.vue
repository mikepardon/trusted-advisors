<template>
    <div class="dl-overlay" @click.self="emit('close')">
        <div class="dl-modal">
            <div class="dl-head">
                <div class="dl-heading">
                    <span class="dl-eyebrow">Leaderboard</span>
                    <span class="dl-title">{{ title }}</span>
                </div>
                <button class="dl-close" aria-label="Close" @click="emit('close')">
                    &times;
                </button>
            </div>

            <div class="dl-tabs">
                <button
                    class="dl-tab"
                    :class="{ active: tab === 'global' }"
                    @click="tab = 'global'"
                >
                    Global
                </button>
                <button
                    class="dl-tab"
                    :class="{ active: tab === 'friends' }"
                    @click="tab = 'friends'"
                >
                    Friends
                </button>
            </div>

            <div v-if="loading" class="dl-loading">Loading…</div>

            <template v-else>
                <p class="dl-note">Ranked by fewest months to win, then highest score.</p>

                <ol v-if="rows.length > 0" class="dl-list">
                    <li
                        v-for="row in rows"
                        :key="row.user_id"
                        class="dl-row"
                        :class="{ 'dl-row--you': row.is_you }"
                    >
                        <span class="dl-rank" :class="medal(row.rank)">{{ row.rank }}</span>
                        <span class="dl-player">{{ row.player }}{{ row.is_you ? " (you)" : "" }}</span>
                        <span class="dl-months">{{ row.months }} mo</span>
                        <span class="dl-score">{{ row.score ?? "—" }}</span>
                    </li>
                </ol>

                <p v-else class="dl-empty">
                    {{ tab === "friends" ? "No friends have won this one yet." : "No winners yet — be the first!" }}
                </p>

                <!-- Your standing, when you're not already in the visible list -->
                <div v-if="youOutsideList" class="dl-you">
                    <span class="dl-rank">{{ you?.rank ?? "—" }}</span>
                    <span class="dl-player">You</span>
                    <span class="dl-months">{{ youSummary }}</span>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onMounted, ref } from "vue";

const { challengeId, title } = defineProps<{
    challengeId: number;
    title: string;
}>();

const emit = defineEmits<{ close: [] }>();

interface Row {
    rank: number;
    user_id: number;
    player: string;
    months: number | undefined;
    score: number | undefined;
    is_you: boolean;
}

interface You {
    rank: number | undefined;
    player: string;
    months: number | undefined;
    score: number | undefined;
    status?: string;
    is_you: boolean;
}

const loading = ref(true);
const tab = ref<"global" | "friends">("global");
const global = ref<Row[]>([]);
const friends = ref<Row[]>([]);
const you = ref<You | undefined>(undefined);

const rows = computed<Row[]>(() => (tab.value === "friends" ? friends.value : global.value));

const youOutsideList = computed<boolean>(
    () => you.value !== undefined && rows.value.every((row) => !row.is_you),
);

const youSummary = computed<string>(() => {
    if (you.value === undefined) {
        return "";
    }
    if (you.value.rank !== undefined && you.value.months !== undefined) {
        return `${you.value.months} mo`;
    }
    const labels: Record<string, string> = {
        lost: "Lost",
        quit: "Quit",
        in_progress: "Playing",
        pending: "Not played",
    };
    return you.value.status ? (labels[you.value.status] ?? you.value.status) : "—";
});

function medal(rank: number): string {
    if (rank === 1) {
        return "dl-rank--gold";
    }
    if (rank === 2) {
        return "dl-rank--silver";
    }
    if (rank === 3) {
        return "dl-rank--bronze";
    }
    return "";
}

onMounted(async () => {
    try {
        const { data } = await axios.get<{
            global: Row[];
            friends: Row[];
            you: You | undefined;
        }>(`/api/daily-challenges/${challengeId}/leaderboard`);
        global.value = data.global;
        friends.value = data.friends;
        you.value = data.you ?? undefined;
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.dl-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.72);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 300;
    padding: 18px;
}

.dl-modal {
    width: 100%;
    max-width: 440px;
    max-height: 86vh;
    overflow-y: auto;
    background: linear-gradient(180deg, #2f2417, #1c150d);
    border: 2px solid #c9a24b;
    border-radius: 14px;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.7);
    padding: 16px 16px 20px;
    color: #f3e9d2;
}

.dl-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.dl-heading {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.dl-eyebrow {
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #c9a24b;
}

.dl-title {
    font-family: "Cinzel", serif;
    font-weight: 700;
    color: #e8c667;
    font-size: 1.05rem;
}

.dl-close {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(201, 162, 75, 0.4);
    border-radius: 8px;
    color: #e8c667;
    font-size: 1.3rem;
    line-height: 1;
    cursor: pointer;
}

.dl-tabs {
    display: flex;
    gap: 6px;
    margin: 14px 0 4px;
}

.dl-tab {
    flex: 1;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(201, 162, 75, 0.25);
    border-radius: 8px;
    color: #a89574;
    font-family: "Cinzel", serif;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 7px;
    cursor: pointer;
}

.dl-tab.active {
    background: linear-gradient(180deg, #e8c667, #c9a24b);
    color: #241b12;
    border-color: #e8c667;
}

.dl-loading,
.dl-empty {
    text-align: center;
    opacity: 0.75;
    padding: 1.5rem;
    font-style: italic;
    font-size: 0.85rem;
}

.dl-note {
    margin: 6px 2px 10px;
    font-size: 0.7rem;
    opacity: 0.6;
}

.dl-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dl-row {
    display: grid;
    grid-template-columns: 34px 1fr auto auto;
    align-items: center;
    gap: 10px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(201, 162, 75, 0.18);
    border-radius: 8px;
    padding: 8px 10px;
}

.dl-row--you {
    background: linear-gradient(90deg, rgba(201, 162, 75, 0.22), rgba(0, 0, 0, 0.3));
    border-color: #c9a24b;
}

.dl-rank {
    font-family: "Cinzel", serif;
    font-weight: 800;
    text-align: center;
    color: #a89574;
}

.dl-rank--gold {
    color: #ffd873;
}

.dl-rank--silver {
    color: #d6dde6;
}

.dl-rank--bronze {
    color: #d69a6a;
}

.dl-player {
    font-weight: 600;
    font-size: 0.86rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dl-months {
    font-family: "Cinzel", serif;
    font-weight: 700;
    color: #e8c667;
    font-size: 0.82rem;
}

.dl-score {
    font-size: 0.78rem;
    opacity: 0.75;
    min-width: 36px;
    text-align: right;
}

.dl-you {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    padding: 8px 10px;
    border-top: 1px solid rgba(201, 162, 75, 0.3);
    background: linear-gradient(90deg, rgba(201, 162, 75, 0.18), rgba(0, 0, 0, 0.25));
    border-radius: 8px;
    font-weight: 600;
}
</style>
