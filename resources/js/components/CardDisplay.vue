<template>
    <div v-if="card" class="card-display">
        <div class="card-header">
            <h3 class="card-title">{{ card.title }}</h3>
            <span v-if="card.difficulty" class="diff-badge">
                Difficulty: {{ card.difficulty }}
            </span>
        </div>
        <p class="card-description">{{ card.description }}</p>
        <div class="card-effects">
            <div v-if="card.positive_effects" class="effect-row">
                <span class="effect-label">Positive:</span>
                <span
                    v-for="(val, stat) in filterStatEffects(
                        card.positive_effects,
                    )"
                    :key="'p-' + stat"
                    class="effect-chip"
                    :class="val > 0 ? 'effect-positive' : 'effect-negative'"
                >
                    {{ stat }}: {{ val > 0 ? "+" : "" }}{{ val }}
                </span>
            </div>
            <div v-if="card.negative_effects" class="effect-row">
                <span class="effect-label">Negative:</span>
                <span
                    v-for="(val, stat) in card.negative_effects"
                    :key="'n-' + stat"
                    class="effect-chip"
                    :class="val > 0 ? 'effect-positive' : 'effect-negative'"
                >
                    {{ stat }}: {{ val > 0 ? "+" : "" }}{{ val }}
                </span>
            </div>
        </div>
    </div>
    <div v-else class="card-display no-card">
        <p>No card to display.</p>
    </div>
</template>

<script setup lang="ts">
type CardEffects = Record<string, number>;

interface Card {
    title?: string;
    description?: string;
    difficulty?: number | string;
    positive_effects?: CardEffects;
    negative_effects?: CardEffects;
}

const { card = undefined } = defineProps<{
    card?: Card;
}>();

function filterStatEffects(effects: CardEffects | undefined): CardEffects {
    if (!effects) {
        return {};
    }
    const result: CardEffects = {};
    for (const [key, value] of Object.entries(effects)) {
        if (key !== "grant_item_id") {
            result[key] = value;
        }
    }
    return result;
}
</script>

<style scoped>
.card-display {
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.96),
        rgba(16, 11, 6, 0.98)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.6);
    border-radius: 16px;
    padding: 14px 16px;
    margin-bottom: 14px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.card-title {
    font-family: "Cinzel", serif;
    font-weight: 700;
    color: #fff5e0;
    font-size: 1rem;
    line-height: 1.3;
}

.diff-badge {
    font-family: "Cinzel", serif;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 1px;
    padding: 3px 10px;
    border-radius: 8px;
    background: rgba(240, 192, 80, 0.15);
    border: 1px solid rgba(240, 192, 80, 0.3);
    color: var(--accent-gold);
}

.card-description {
    text-align: center;
    color: var(--accent-gold);
    font-style: italic;
    line-height: 1.4;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.card-effects {
    font-size: 0.85rem;
}

.effect-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 8px;
}

.effect-label {
    font-family: "Cinzel", serif;
    color: var(--ta-good, #8ef0c8);
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.3px;
    margin-right: 4px;
}

.effect-row:last-of-type .effect-label {
    color: var(--ta-bad, #f0a8a0);
}

.effect-chip {
    padding: 3px 6px;
    border-radius: 7px;
    border: 1px solid transparent;
    text-transform: capitalize;
    font-size: 0.72rem;
    white-space: nowrap;
}

.effect-positive {
    color: var(--ta-good, #8ef0c8);
    background: rgba(92, 184, 92, 0.15);
    border-color: rgba(142, 240, 200, 0.4);
}

.effect-negative {
    color: var(--ta-bad, #f0a8a0);
    background: rgba(208, 64, 48, 0.15);
    border-color: rgba(240, 168, 160, 0.4);
}

.no-card {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
}
</style>
