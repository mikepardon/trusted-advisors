<template>
    <div class="duel-resolve">
        <div class="ta-divider">
            <span class="ta-divider-label">MONTH SUMMARY</span>
            <span class="ta-divider-line"></span>
        </div>

        <div class="results-side-by-side">
            <div
                v-for="result in playerResults"
                :key="result.player_number"
                class="player-result"
            >
                <h5 class="result-player">{{ result.character_name }}</h5>

                <div class="result-roll-total">
                    <span class="roll-label">ROLL</span>
                    <strong>{{ result.total_roll }}</strong>
                </div>

                <!-- Per-card outcomes -->
                <div
                    v-for="(cr, idx) in getCardResults(result)"
                    :key="idx"
                    class="card-outcome"
                >
                    <span class="card-outcome-name">{{
                        cr.card?.title || "Card " + (idx + 1)
                    }}</span>
                    <span class="card-outcome-diff"
                        >Diff {{ cr.difficulty }}</span
                    >
                    <span
                        class="result-badge"
                        :class="cr.success ? 'badge-success' : 'badge-failure'"
                    >
                        {{ cr.success ? "SUCCESS" : "FAILURE" }}
                    </span>
                </div>

                <!-- Combined effects -->
                <div
                    v-if="Object.keys(getCombinedEffects(result)).length > 0"
                    class="effects-row"
                >
                    <span
                        v-for="(val, stat) in getCombinedEffects(result)"
                        :key="stat"
                        class="ta-chip"
                        :class="val > 0 ? 'ta-chip--good' : 'ta-chip--bad'"
                    >
                        {{ stat }} {{ val > 0 ? "+" : "" }}{{ val }}
                    </span>
                </div>
            </div>
        </div>

        <button
            v-if="canAdvance"
            class="ta-cta next-btn"
            @click="$emit('nextRound')"
        >
            {{ gameOver ? "View Results" : "Next Month →" }}
        </button>
        <p v-else class="waiting-host-text">Waiting for host to advance...</p>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

interface CardResult {
    card?: { title?: string };
    difficulty: number;
    success: boolean;
    effects: Record<string, number>;
}

interface PlayerResult {
    player_number: number;
    character_name: string;
    total_roll: number;
    cards?: CardResult[];
    card?: { title?: string };
    difficulty?: number;
    success?: boolean;
    effects?: Record<string, number>;
    combined_effects?: Record<string, number>;
}

const {
    offererResult = undefined,
    chooserResult = undefined,
    canAdvance = true,
    gameOver = false,
} = defineProps<{
    offererResult?: PlayerResult;
    chooserResult?: PlayerResult;
    canAdvance?: boolean;
    gameOver?: boolean;
}>();

defineEmits<{ nextRound: [] }>();

const playerResults = computed<PlayerResult[]>(() => {
    const results: PlayerResult[] = [];
    if (offererResult) {
        results.push(offererResult);
    }
    if (chooserResult) {
        results.push(chooserResult);
    }
    return results;
});

function getCardResults(result: PlayerResult): CardResult[] {
    // New 2-card format
    if (result.cards) {
        return result.cards;
    }
    // Legacy single-card format
    if (result.card) {
        return [
            {
                card: result.card,
                difficulty: result.difficulty ?? 0,
                success: result.success ?? false,
                effects: result.effects ?? {},
            },
        ];
    }
    return [];
}

function getCombinedEffects(result: PlayerResult): Record<string, number> {
    if (result.combined_effects) {
        return result.combined_effects;
    }
    if (result.effects) {
        return result.effects;
    }
    return {};
}
</script>

<style scoped>
.duel-resolve {
    margin-bottom: 20px;
}

.results-side-by-side {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 11px;
    margin-bottom: 16px;
}

.player-result {
    padding: 14px 12px;
    border-radius: 16px;
    text-align: center;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.96),
        rgba(16, 11, 6, 0.98)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.5);
    box-shadow: 0 5px 0 rgba(0, 0, 0, 0.4);
}

.result-player {
    font-family: "Cinzel", serif;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: 0.4px;
    color: var(--ta-gold);
    margin: 0 0 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-roll-total {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.3);
}

.roll-label {
    font-family: "Cinzel", serif;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 1.8px;
    color: var(--ta-mute-dim);
}

.result-roll-total strong {
    font-family: "Cinzel", serif;
    font-size: 17px;
    font-weight: 800;
    color: var(--ta-gold);
}

.card-outcome {
    display: flex;
    align-items: center;
    gap: 7px;
    justify-content: center;
    margin-bottom: 6px;
    flex-wrap: wrap;
}

.card-outcome-name {
    font-family: "Cinzel", serif;
    color: var(--ta-text);
    font-size: 12px;
    font-weight: 700;
}

.card-outcome-diff {
    color: var(--ta-mute);
    font-size: 11px;
}

.result-badge {
    display: inline-block;
    font-family: "Cinzel", serif;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 3px 8px;
    border-radius: 8px;
}

.badge-success {
    color: var(--ta-good);
    background: rgba(92, 184, 92, 0.15);
    border: 1px solid rgba(142, 240, 200, 0.45);
}

.badge-failure {
    color: var(--ta-bad);
    background: rgba(208, 64, 48, 0.15);
    border: 1px solid rgba(240, 168, 160, 0.45);
}

.effects-row {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 9px;
}

.next-btn {
    max-width: 320px;
    margin: 0 auto;
}

.waiting-host-text {
    text-align: center;
    color: var(--ta-mute);
    font-style: italic;
    padding: 16px;
}

@media (max-width: 768px) {
    .results-side-by-side {
        grid-template-columns: 1fr;
    }
}
</style>
