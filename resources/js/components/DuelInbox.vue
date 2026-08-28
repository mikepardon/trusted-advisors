<template>
    <section v-if="!loading && duels.length > 0" class="di">
        <div class="di-head">
            <span class="di-eyebrow">Your Duels</span>
            <span v-if="yourTurnCount > 0" class="di-badge">{{ yourTurnCount }} to play</span>
        </div>

        <ul class="di-list">
            <li v-for="duel in duels" :key="duel.id" class="di-row" :class="{ 'di-row--turn': duel.is_my_turn }">
                <span class="di-vs">
                    vs {{ duel.opponent }}
                    <span class="di-round">Month {{ duel.round }} / {{ duel.total_rounds }}</span>
                </span>
                <span v-if="duel.is_my_turn" class="di-turn-tag">Your turn</span>
                <span v-else class="di-wait-tag">{{ waitLabel(duel) }}</span>
                <button class="di-play" @click="resume(duel.id)">
                    {{ duel.is_my_turn ? "Play" : "View" }}
                </button>
            </li>
        </ul>
    </section>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

interface Duel {
    id: number;
    opponent: string;
    round: number;
    total_rounds: number;
    is_my_turn: boolean;
    is_correspondence: boolean;
    time_remaining: number | undefined;
}

const router = useRouter();

const loading = ref(true);
const duels = ref<Duel[]>([]);
const yourTurnCount = computed<number>(() => duels.value.filter((duel) => duel.is_my_turn).length);

function resume(id: number): void {
    void router.push(`/game/${id}`);
}

function waitLabel(duel: Duel): string {
    if (!duel.is_correspondence || duel.time_remaining === undefined) {
        return "Waiting";
    }
    const hours = Math.floor(duel.time_remaining / 3600);
    return hours > 0 ? `Waiting · ${hours}h left` : "Waiting";
}

onMounted(async () => {
    try {
        const { data } = await axios.get<{ duels: Duel[]; your_turn_count: number }>(
            "/api/duels/active",
        );
        duels.value = data.duels;
    } catch {
        // Silent — the inbox is a convenience surface, never block the hub.
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.di {
    background: linear-gradient(180deg, #2a2233, #1e1826);
    border: 2px solid #7d6ab0;
    border-radius: 12px;
    margin: 0 auto 1rem;
    max-width: 640px;
    color: #ece6f5;
    padding: 0.7rem 0.9rem;
}

.di-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.55rem;
}

.di-eyebrow {
    font-family: "Cinzel", serif;
    color: #b9a6e6;
    font-weight: 700;
    font-size: 0.95rem;
}

.di-badge {
    background: #7d6ab0;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
}

.di-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.di-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 0.6rem;
    background: rgba(0, 0, 0, 0.28);
    border: 1px solid rgba(125, 106, 176, 0.25);
    border-radius: 8px;
    padding: 0.5rem 0.6rem;
}

.di-row--turn {
    border-color: #b9a6e6;
    background: linear-gradient(90deg, rgba(125, 106, 176, 0.25), rgba(0, 0, 0, 0.28));
}

.di-vs {
    display: flex;
    flex-direction: column;
    min-width: 0;
    font-weight: 700;
    font-size: 0.85rem;
}

.di-round {
    font-size: 0.68rem;
    opacity: 0.65;
    font-weight: 400;
}

.di-turn-tag {
    font-size: 0.7rem;
    font-weight: 700;
    color: #b9a6e6;
    white-space: nowrap;
}

.di-wait-tag {
    font-size: 0.68rem;
    opacity: 0.6;
    white-space: nowrap;
}

.di-play {
    background: #7d6ab0;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 0.32rem 0.75rem;
    font-weight: 700;
    font-family: "Cinzel", serif;
    font-size: 0.74rem;
    cursor: pointer;
}
</style>
