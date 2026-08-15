<template>
    <div class="round-results">
        <div
            v-if="popText"
            :key="popKey"
            class="micro-pop"
            :class="`micro-${popKind}`"
        >
            {{ popText }}
        </div>

        <!-- Action button at top for easy access -->
        <button
            v-if="
                viewPhase === 'positive' &&
                !allRolled &&
                nextToRoll &&
                !isAnyRolling
            "
            class="ta-cta ta-cta--dark action-btn-top"
            @click="startRolling(nextToRoll)"
        >
            Roll ⬢
        </button>
        <button
            v-else-if="
                viewPhase === 'positive' && allRolled && !resultsAccepted
            "
            class="ta-cta action-btn-top"
            @click="acceptAndContinue"
        >
            Accept Results
        </button>
        <button
            v-else-if="viewPhase === 'negative' && canAdvance"
            class="ta-cta action-btn-top"
            @click="$emit('nextRound')"
        >
            {{ gameOver ? "View Results" : "Next Month →" }}
        </button>

        <!-- ===================== -->
        <!-- PHASE 1: POSITIVE     -->
        <!-- ===================== -->
        <template v-if="viewPhase === 'positive'">
            <div class="phase-section phase-positive">
                <h4 class="phase-title">The Council Acts</h4>
                <p class="phase-note">
                    Your advisors have chosen to address these matters:
                </p>

                <div class="pooled-cards">
                    <div
                        v-for="(card, idx) in positivePhase.cards"
                        :key="'p-' + idx"
                        class="pooled-card"
                    >
                        <span class="pooled-player">{{
                            card.character_name
                        }}</span>
                        <span class="pooled-card-title">{{
                            card.card.title
                        }}</span>
                        <span class="pooled-diff"
                            >Diff: {{ card.card.difficulty }}</span
                        >
                    </div>
                </div>

                <div class="total-difficulty-line">
                    Combined Difficulty:
                    <strong>{{ positivePhase.total_difficulty }}</strong>
                </div>

                <!-- Dice Rolling Area -->
                <div class="dice-section">
                    <div
                        v-for="pr in positivePhase.dice_results"
                        :key="pr.player_number"
                        class="player-roll-row"
                    >
                        <span class="roll-name">{{ pr.character_name }}</span>

                        <template v-if="hasRolled(pr.player_number)">
                            <span class="roll-faces">
                                <span
                                    v-for="(roll, ri) in pr.rolls"
                                    :key="ri"
                                    class="face-badge"
                                    :class="
                                        roll.face === 'WILD'
                                            ? 'face-wild'
                                            : 'face-num'
                                    "
                                >
                                    {{
                                        roll.face === "WILD"
                                            ? "W " + roll.value
                                            : roll.face
                                    }}
                                </span>
                            </span>
                            <span class="roll-subtotal"
                                >= {{ playerSubtotal(pr) }}</span
                            >
                        </template>

                        <template v-else-if="isRolling(pr.player_number)">
                            <span class="roll-faces rolling-anim">
                                <span
                                    v-for="(roll, ri) in pr.rolls"
                                    :key="'r-' + ri"
                                    class="face-badge face-rolling"
                                >
                                    {{
                                        rollingFaces[pr.player_number]?.[ri] ??
                                        "?"
                                    }}
                                </span>
                            </span>
                        </template>

                        <template v-else>
                            <span class="roll-waiting">Awaiting roll...</span>
                        </template>
                    </div>
                </div>

                <!-- Running total -->
                <div v-if="rolledCount > 0 && !allRolled" class="running-total">
                    Roll so far: <strong>{{ runningTotal }}</strong> &mdash;
                    {{ remainingCount }} advisor{{
                        remainingCount !== 1 ? "s" : ""
                    }}
                    to roll
                </div>

                <!-- After all rolled: Wild Triggers, Summary -->
                <template v-if="allRolled">
                    <div
                        v-if="
                            positivePhase.ability_effects &&
                            positivePhase.ability_effects.length > 0
                        "
                        class="wild-section"
                    >
                        <div
                            v-for="(desc, i) in positivePhase.ability_effects"
                            :key="i"
                            class="wild-trigger"
                        >
                            {{ desc }}
                        </div>
                    </div>

                    <div class="roll-summary">
                        <div class="roll-tally">
                            <span
                                class="roll-total"
                                :class="
                                    positivePhase.success
                                        ? 'roll-pass'
                                        : 'roll-fail'
                                "
                            >
                                Roll {{ positivePhase.total_roll }}
                            </span>
                            <span class="roll-vs">vs</span>
                            <span class="roll-difficulty"
                                >Difficulty
                                {{ positivePhase.total_difficulty }}</span
                            >
                        </div>
                        <div
                            class="verdict"
                            :class="
                                positivePhase.success
                                    ? 'verdict-pass'
                                    : 'verdict-fail'
                            "
                        >
                            {{ positivePhase.success ? "SUCCESS" : "FAILURE" }}
                        </div>
                    </div>

                    <div
                        v-if="
                            positivePhase.item_modifiers &&
                            positivePhase.item_modifiers.length > 0
                        "
                        class="item-modifiers-section"
                    >
                        <span
                            v-for="(
                                modifier, i
                            ) in positivePhase.item_modifiers"
                            :key="'im-' + i"
                            class="item-mod-tag"
                            :class="modifierTagClass(modifier)"
                        >
                            {{ modifier.item_name }} ({{
                                modifierLabel(modifier)
                            }})
                        </span>
                    </div>

                    <!-- Flavor text reveal -->
                    <div
                        v-if="positivePhase.success && positiveFlavorText"
                        class="flavor-reveal flavor-positive"
                    >
                        {{ positiveFlavorText }}
                    </div>
                    <div
                        v-else-if="
                            !positivePhase.success && negativeFlavorForActed
                        "
                        class="flavor-reveal flavor-negative"
                    >
                        {{ negativeFlavorForActed }}
                    </div>

                    <!-- Show effects preview (but stats haven't moved yet) -->
                    <div
                        v-if="
                            positivePhase.success &&
                            Object.keys(
                                filterStatEffects(positivePhase.effects || {}),
                            ).length > 0
                        "
                        class="effects-row"
                    >
                        <span
                            v-for="(val, stat) in filterStatEffects(
                                positivePhase.effects || {},
                            )"
                            :key="stat"
                            class="ta-chip"
                            :class="val > 0 ? 'ta-chip--good' : 'ta-chip--bad'"
                        >
                            {{ statIcon(stat) }} {{ stat }}
                            {{ val > 0 ? "+" : "" }}{{ val }}
                        </span>
                    </div>
                    <div v-if="!positivePhase.success" class="no-effects">
                        The council's efforts fell short. No positive effects
                        this month.
                    </div>

                    <!-- Special effects for positive phase -->
                    <div
                        v-if="positiveSpecialEffects.length > 0"
                        class="special-effects-section"
                    >
                        <div
                            v-for="(se, i) in positiveSpecialEffects"
                            :key="'se-' + i"
                            class="special-effect-line"
                        >
                            <span class="special-icon">{{
                                specialIcon(se.type)
                            }}</span>
                            {{ se.description }}
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- ===================== -->
        <!-- PHASE 2: NEGATIVE     -->
        <!-- ===================== -->
        <template v-if="viewPhase === 'negative'">
            <div class="phase-section phase-negative">
                <h4 class="phase-title">Meanwhile...</h4>
                <p class="phase-note">
                    These matters were left unattended. Their consequences are
                    unavoidable.
                </p>

                <div class="pooled-cards">
                    <div
                        v-for="(card, idx) in negativePhase.cards"
                        :key="'n-' + idx"
                        class="pooled-card pooled-neg"
                    >
                        <span class="pooled-player">{{
                            card.character_name
                        }}</span>
                        <span class="pooled-card-title">{{
                            card.card.title
                        }}</span>
                    </div>
                </div>

                <div class="neg-card-details">
                    <div
                        v-for="(card, idx) in negativePhase.cards"
                        :key="'nd-' + idx"
                        class="neg-card-detail"
                    >
                        <h5 class="neg-card-name">{{ card.card.title }}</h5>
                        <p class="neg-card-desc">{{ card.card.description }}</p>
                        <p
                            v-if="card.card.negative_flavor"
                            class="neg-card-flavor"
                        >
                            {{ card.card.negative_flavor }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="Object.keys(negativePhase.effects || {}).length > 0"
                    class="effects-row"
                >
                    <span
                        v-for="(val, stat) in negativePhase.effects"
                        :key="stat"
                        class="ta-chip"
                        :class="val > 0 ? 'ta-chip--good' : 'ta-chip--bad'"
                    >
                        {{ statIcon(stat) }} {{ stat }} {{ val > 0 ? "+" : ""
                        }}{{ val }}
                    </span>
                </div>

                <!-- Special effects for negative phase -->
                <div
                    v-if="negativeSpecialEffects.length > 0"
                    class="special-effects-section special-negative"
                >
                    <div
                        v-for="(se, i) in negativeSpecialEffects"
                        :key="'nse-' + i"
                        class="special-effect-line"
                    >
                        <span class="special-icon">{{
                            specialIcon(se.type)
                        }}</span>
                        {{ se.description }}
                    </div>
                </div>
            </div>

            <!-- Combined Summary -->
            <div class="total-summary">
                <div class="ta-divider">
                    <span class="ta-divider-line"></span>
                    <span class="ta-divider-label">END OF MONTH SUMMARY</span>
                    <span class="ta-divider-line"></span>
                </div>
                <div class="total-effects">
                    <span
                        v-for="(val, stat) in combinedEffects"
                        :key="stat"
                        class="ta-chip"
                        :class="val > 0 ? 'ta-chip--good' : 'ta-chip--bad'"
                    >
                        {{ statIcon(stat) }} {{ stat }} {{ val > 0 ? "+" : ""
                        }}{{ val }}
                    </span>
                </div>
                <div
                    v-if="Object.keys(eventEffects || {}).length > 0"
                    class="event-row"
                >
                    <span class="event-label">Event:</span>
                    <span
                        v-for="(val, stat) in eventEffects"
                        :key="stat"
                        class="ta-chip"
                        :class="val > 0 ? 'ta-chip--good' : 'ta-chip--bad'"
                    >
                        {{ statIcon(stat) }} {{ stat }} {{ val > 0 ? "+" : ""
                        }}{{ val }}
                    </span>
                </div>
            </div>

            <p v-if="!canAdvance" class="waiting-host-text">
                Waiting for host to advance...
            </p>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { playSound } from "../sounds";
import dddiceService from "../dddice-service";
import { useIcons } from "../stores/icons";
import { haptic, type HapticStyle } from "../haptics";

interface DiceRoll {
    face: string;
    value: number;
}

interface DiceResult {
    player_number: number;
    character_name: string;
    rolls: DiceRoll[];
}

interface PhaseCard {
    character_name: string;
    card: {
        title: string;
        difficulty?: number;
        description?: string;
        positive_flavor?: string;
        negative_flavor?: string;
    };
}

interface ItemModifier {
    item_name: string;
    type: string;
    value: number;
}

interface PositivePhase {
    cards?: PhaseCard[];
    total_difficulty?: number;
    dice_results?: DiceResult[];
    ability_effects?: string[];
    success?: boolean;
    total_roll?: number;
    item_modifiers?: ItemModifier[];
    effects?: Record<string, number>;
}

interface NegativePhase {
    cards?: PhaseCard[];
    effects?: Record<string, number>;
}

interface SpecialEffect {
    phase: string;
    type: string;
    description: string;
}

interface RoundPlayer {
    player_number: number;
    user?: {
        active_dice_theme_slug?: string;
    };
}

const {
    round,
    positivePhase = {},
    negativePhase = {},
    specialEffects = [],
    players = [],
} = defineProps<{
    round: number;
    totalRounds?: number;
    positivePhase?: PositivePhase;
    negativePhase?: NegativePhase;
    combinedEffects?: Record<string, number>;
    eventEffects?: Record<string, number>;
    specialEffects?: SpecialEffect[];
    gameOver?: boolean;
    canAdvance?: boolean;
    players?: RoundPlayer[];
    resumed?: boolean;
}>();

const emit = defineEmits<{
    nextRound: [];
    phaseComplete: [phase: string];
}>();

const rolledPlayerNumbers = ref<number[]>([]);
const rollingPlayerNumbers = ref<number[]>([]);
const rollingFaces = ref<Record<number, string[]>>({});
const viewPhase = ref("positive");
const resultsAccepted = ref(false);

// Per-round micro-win feedback: a combo that builds across consecutive
// successful rounds and resets on a failure.
const winCombo = ref(0);
const popText = ref("");
const popKey = ref(0);
const popKind = ref<"success" | "fail">("success");

function playerSubtotal(diceResult: DiceResult): number {
    return (diceResult.rolls || []).reduce(
        (sum, roll) => sum + (roll.value || 0),
        0,
    );
}

const rolledCount = computed<number>(() => rolledPlayerNumbers.value.length);

const totalPlayers = computed<number>(
    () => (positivePhase.dice_results || []).length,
);

const remainingCount = computed<number>(
    () => totalPlayers.value - rolledCount.value,
);

const allRolled = computed<boolean>(
    () => totalPlayers.value > 0 && rolledCount.value >= totalPlayers.value,
);

const runningTotal = computed<number>(() => {
    let sum = 0;
    const diceResults = positivePhase.dice_results || [];
    for (const diceResult of diceResults) {
        if (rolledPlayerNumbers.value.includes(diceResult.player_number)) {
            sum += playerSubtotal(diceResult);
        }
    }
    return sum;
});

const nextToRoll = computed<DiceResult | undefined>(() =>
    (positivePhase.dice_results || []).find(
        (diceResult) =>
            !rolledPlayerNumbers.value.includes(diceResult.player_number) &&
            !rollingPlayerNumbers.value.includes(diceResult.player_number),
    ),
);

const isAnyRolling = computed<boolean>(
    () => rollingPlayerNumbers.value.length > 0,
);

const positiveSpecialEffects = computed<SpecialEffect[]>(() =>
    (specialEffects || []).filter((effect) => effect.phase === "positive"),
);

const negativeSpecialEffects = computed<SpecialEffect[]>(() =>
    (specialEffects || []).filter((effect) => effect.phase === "negative"),
);

const positiveFlavorText = computed<string>(() => {
    const cards = positivePhase.cards || [];
    const flavors = cards
        .map((card) => card.card?.positive_flavor)
        .filter(Boolean);
    return flavors.join(" ");
});

const negativeFlavorForActed = computed<string>(() => {
    // When the council fails the acted-on cards, show their negative flavor
    const cards = positivePhase.cards || [];
    const flavors = cards
        .map((card) => card.card?.negative_flavor)
        .filter(Boolean);
    return flavors.join(" ");
});

watch(
    () => round,
    () => {
        rolledPlayerNumbers.value = [];
        rollingPlayerNumbers.value = [];
        rollingFaces.value = {};
        viewPhase.value = "positive";
        resultsAccepted.value = false;
    },
);

// Fire micro-win feedback the moment a round's outcome is revealed.
watch(allRolled, (rolled) => {
    if (!rolled) {
        return;
    }
    if (positivePhase.success) {
        winCombo.value++;
        const statCount = Object.keys(
            filterStatEffects(positivePhase.effects || {}),
        ).length;
        popKind.value = "success";
        popText.value =
            winCombo.value >= 2
                ? `Combo ×${winCombo.value}!`
                : `Success! +${statCount}`;
        const styles: HapticStyle[] = ["success", "medium", "heavy"];
        haptic(styles[Math.min(winCombo.value - 1, 2)]);
    } else {
        winCombo.value = 0;
        popKind.value = "fail";
        popText.value = "Failure";
        haptic("warning");
    }
    popKey.value++;
});

function hasRolled(playerNumber: number): boolean {
    return rolledPlayerNumbers.value.includes(playerNumber);
}

function isRolling(playerNumber: number): boolean {
    return rollingPlayerNumbers.value.includes(playerNumber);
}

function getThemesForPlayer(playerNumber: number): string[] {
    const player = (players || []).find(
        (entry) => entry.player_number === playerNumber,
    );
    const slug = player?.user?.active_dice_theme_slug || "dddice-standard";
    return [slug, slug, slug];
}

async function startRolling(diceResult: DiceResult): Promise<void> {
    const playerNumber = diceResult.player_number;
    if (
        rollingPlayerNumbers.value.includes(playerNumber) ||
        rolledPlayerNumbers.value.includes(playerNumber)
    )
        return;

    const use3D = dddiceService.isReady();

    if (!use3D) {
        playSound("dice");
    }

    rollingPlayerNumbers.value.push(playerNumber);
    rollingFaces.value[playerNumber] = diceResult.rolls.map(() => "?");

    if (use3D) {
        // 3D dice path: animate via dddice, then show final results immediately
        const themes = getThemesForPlayer(playerNumber);
        const diceSpecs = diceResult.rolls.map((roll, index) => ({
            theme: themes[index] || "dddice-standard",
            value: roll.value,
        }));
        await dddiceService.roll(diceSpecs);

        rollingPlayerNumbers.value = rollingPlayerNumbers.value.filter(
            (number) => number !== playerNumber,
        );
        rolledPlayerNumbers.value.push(playerNumber);

        if (allRolled.value) {
            if (positivePhase.success) {
                playSound("win");
            } else {
                playSound("fail");
            }
        }
    } else {
        // Fallback: text animation
        const possibleFaces = ["1", "2", "3", "4", "5", "W"];
        let ticks = 0;
        const maxTicks = 12;
        const interval = setInterval(() => {
            ticks++;
            rollingFaces.value[playerNumber] = diceResult.rolls.map(
                () =>
                    possibleFaces[
                        Math.floor(Math.random() * possibleFaces.length)
                    ],
            );
            rollingFaces.value = { ...rollingFaces.value };

            if (ticks >= maxTicks) {
                clearInterval(interval);
                rollingPlayerNumbers.value = rollingPlayerNumbers.value.filter(
                    (number) => number !== playerNumber,
                );
                rolledPlayerNumbers.value.push(playerNumber);

                if (allRolled.value) {
                    if (positivePhase.success) {
                        playSound("win");
                    } else {
                        playSound("fail");
                    }
                }
            }
        }, 70);
    }
}

async function acceptAndContinue(): Promise<void> {
    resultsAccepted.value = true;
    emit("phaseComplete", "positive");
    await nextTick();
    viewPhase.value = "negative";
    await nextTick();
    emit("phaseComplete", "negative");
}

function statIcon(stat: string): string {
    const statIcons = useIcons().getStatIcons();
    const match = statIcons.find((icon) => icon.key === stat);
    return match ? match.icon : "";
}

function filterStatEffects(
    effects: Record<string, number> | undefined,
): Record<string, number> {
    if (!effects) return {};
    const result: Record<string, number> = {};
    for (const [key, value] of Object.entries(effects)) {
        if (
            ![
                "grant_item_id",
                "draw_item",
                "recover_die",
                "remove_curse",
            ].includes(key)
        ) {
            result[key] = value;
        }
    }
    return result;
}

function modifierLabel(modifier: ItemModifier): string {
    const labels: Record<string, string> = {
        roll_bonus: "+" + modifier.value + " to roll",
        roll_penalty: modifier.value + " to roll",
        difficulty_reduction: "-" + modifier.value + " difficulty",
        difficulty_increase: "+" + modifier.value + " difficulty",
    };
    return labels[modifier.type] || modifier.type;
}

function modifierTagClass(modifier: ItemModifier): string {
    if (
        modifier.type === "roll_bonus" ||
        modifier.type === "difficulty_reduction"
    )
        return "mod-helpful";
    return "mod-harmful";
}

function specialIcon(type: string): string {
    const icons: Record<string, string> = {
        draw_item: "\u{1F3C6}",
        recover_die: "\u{1FA79}",
        lose_die: "\u{1F4A5}",
        discard_item: "\u{1F4A8}",
        remove_curse: "\u{2728}",
    };
    return icons[type] || "\u{2728}";
}
</script>

<style scoped>
.round-results {
    margin-bottom: 20px;
}

/* -------------------------------------------------- Micro win/fail pop */
.micro-pop {
    position: fixed;
    top: 30%;
    left: 50%;
    transform: translateX(-50%);
    z-index: 500;
    pointer-events: none;
    font-family: "Cinzel", serif;
    font-weight: 800;
    font-size: 1.8rem;
    letter-spacing: 1.5px;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
    animation: micro-pop-float 1.3s ease-out forwards;
}

.micro-success {
    color: var(--ta-good);
}

.micro-fail {
    color: var(--ta-bad);
}

@keyframes micro-pop-float {
    0% {
        opacity: 0;
        transform: translateX(-50%) translateY(10px) scale(0.6);
    }
    20% {
        opacity: 1;
        transform: translateX(-50%) translateY(0) scale(1.1);
    }
    35% {
        transform: translateX(-50%) translateY(-4px) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateX(-50%) translateY(-46px) scale(1);
    }
}

/* -------------------------------------------------- Top action button */
.action-btn-top {
    max-width: 320px;
    margin: 0 auto 14px;
}

/* -------------------------------------------------- Phase panels */
.phase-section {
    padding: 16px 14px;
    border-radius: 17px;
    margin-bottom: 15px;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.96),
        rgba(16, 11, 6, 0.98)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.4);
    box-shadow: 0 6px 22px rgba(0, 0, 0, 0.5);
}

.phase-positive {
    border-color: rgba(142, 240, 200, 0.45);
}
.phase-negative {
    border-color: rgba(240, 168, 160, 0.45);
}

.phase-title {
    font-family: "Cinzel", serif;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: var(--ta-gold);
    font-size: 1.05rem;
    margin: 0 0 6px;
}

.phase-note {
    color: var(--ta-mute);
    font-style: italic;
    font-size: 0.85rem;
    margin-bottom: 12px;
    line-height: 1.4;
}

/* -------------------------------------------------- Pooled cards */
.pooled-cards {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.pooled-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 11px;
    border-radius: 11px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 80, 0.24);
    font-size: 0.85rem;
}

.pooled-card.pooled-neg {
    background: linear-gradient(
        90deg,
        rgba(208, 64, 48, 0.12),
        rgba(0, 0, 0, 0.35)
    );
    border-color: rgba(240, 168, 160, 0.3);
}

.pooled-player {
    color: var(--ta-mute);
    min-width: 96px;
    font-size: 0.8rem;
}

.pooled-card-title {
    color: var(--ta-text);
    font-family: "Cinzel", serif;
    font-weight: 700;
    font-size: 0.85rem;
    flex: 1;
}

.pooled-diff {
    color: var(--ta-mute);
    font-size: 0.78rem;
    font-family: "Cinzel", serif;
    font-weight: 700;
}

.total-difficulty-line {
    color: var(--ta-mute);
    font-size: 0.88rem;
    margin-bottom: 14px;
    padding: 7px 11px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.28);
    text-align: center;
    font-family: "Cinzel", serif;
    letter-spacing: 0.4px;
}

.total-difficulty-line strong {
    color: var(--ta-gold);
    font-size: 1.05rem;
}

/* -------------------------------------------------- Per-player roll rows */
.dice-section {
    margin-bottom: 10px;
}

.player-roll-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    padding: 9px 11px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 80, 0.2);
    min-height: 44px;
}

.roll-name {
    color: var(--ta-text);
    min-width: 108px;
    font-size: 0.85rem;
    font-family: "Cinzel", serif;
    font-weight: 700;
}

.roll-faces {
    display: flex;
    gap: 6px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Cast-dice tile: white face, slight tilt (mock spec). */
.face-badge {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    border-radius: 9px;
    font-size: 0.85rem;
    font-weight: 800;
    font-family: "Cinzel", serif;
    text-align: center;
}

.face-num {
    background: linear-gradient(180deg, #fffbe8, #e8d090);
    border: 2px solid #fff0b0;
    color: #2a1f14;
    box-shadow: 0 3px 0 rgba(0, 0, 0, 0.45);
}

.face-wild {
    background: linear-gradient(180deg, #ffe897, #c8952e);
    border: 2px solid #fff0b0;
    color: #241703;
    box-shadow: 0 3px 0 rgba(0, 0, 0, 0.45);
}

.face-rolling {
    background: rgba(0, 0, 0, 0.4);
    color: var(--ta-gold);
    border: 1px solid var(--ta-gold);
    box-shadow: none;
    animation: diceShake 0.1s infinite alternate;
}

.rolling-anim {
    animation: none;
}

@keyframes diceShake {
    0% {
        transform: translateY(-1px) rotate(-3deg);
    }
    100% {
        transform: translateY(1px) rotate(3deg);
    }
}

.roll-subtotal {
    color: var(--ta-gold);
    font-family: "Cinzel", serif;
    font-weight: 800;
    font-size: 0.95rem;
    margin-left: 6px;
}

.roll-waiting {
    color: var(--ta-mute);
    font-style: italic;
    font-size: 0.8rem;
}

.running-total {
    color: var(--ta-mute);
    font-size: 0.88rem;
    margin-bottom: 12px;
    padding: 8px 12px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.24);
    text-align: center;
    font-family: "Cinzel", serif;
    letter-spacing: 0.4px;
}

.running-total strong {
    color: var(--ta-gold);
    font-size: 1.05rem;
}

/* -------------------------------------------------- Wild triggers */
.wild-section {
    margin-bottom: 10px;
    padding: 9px 11px;
    border-radius: 12px;
    background: rgba(240, 192, 80, 0.1);
    border: 1px solid rgba(240, 192, 80, 0.4);
    animation: fadeIn 0.3s ease;
}

.wild-trigger {
    color: var(--ta-gold);
    font-size: 0.85rem;
    font-style: italic;
    margin-bottom: 3px;
}

/* -------------------------------------------------- Verdict / roll summary */
.roll-summary {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 14px 12px;
    border-radius: 14px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 80, 0.3);
    text-align: center;
    animation: fadeIn 0.3s ease;
}

.roll-tally {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    justify-content: center;
    font-family: "Cinzel", serif;
}

.roll-total {
    font-weight: 800;
    font-size: 1rem;
    letter-spacing: 0.5px;
}

.roll-pass {
    color: var(--ta-good);
}
.roll-fail {
    color: var(--ta-bad);
}
.roll-vs {
    color: var(--ta-mute);
    font-style: italic;
}
.roll-difficulty {
    color: var(--ta-text);
    font-weight: 700;
}

.verdict {
    font-family: "Cinzel", serif;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: 2.2px;
}

.verdict-pass {
    color: var(--ta-good);
}
.verdict-fail {
    color: #f05a4a;
}

/* -------------------------------------------------- Item modifiers */
.item-modifiers-section {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 10px;
    animation: fadeIn 0.3s ease;
}

.item-mod-tag {
    font-family: "Cinzel", serif;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 8px;
    font-style: italic;
    border: 1px solid transparent;
}

.mod-helpful {
    color: var(--ta-good);
    background: rgba(92, 184, 92, 0.15);
    border-color: rgba(142, 240, 200, 0.4);
}

.mod-harmful {
    color: var(--ta-bad);
    background: rgba(208, 64, 48, 0.15);
    border-color: rgba(240, 168, 160, 0.4);
}

/* -------------------------------------------------- Flavour reveal */
.flavor-reveal {
    font-style: italic;
    font-size: 0.88rem;
    line-height: 1.5;
    margin-bottom: 10px;
    padding: 9px 12px;
    border-radius: 12px;
    color: var(--ta-text);
    animation: fadeIn 0.3s ease;
}

.flavor-positive {
    background: rgba(92, 184, 92, 0.1);
    border: 1px solid rgba(142, 240, 200, 0.35);
}

.flavor-negative {
    background: rgba(208, 64, 48, 0.1);
    border: 1px solid rgba(240, 168, 160, 0.35);
}

.no-effects {
    color: var(--ta-mute);
    font-style: italic;
    font-size: 0.85rem;
    margin-bottom: 10px;
    text-align: center;
}

/* -------------------------------------------------- Effect chip rows */
.effects-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 10px;
}

/* -------------------------------------------------- Negative card details */
.neg-card-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.neg-card-detail {
    padding: 10px 13px;
    border-radius: 12px;
    background: linear-gradient(
        90deg,
        rgba(208, 64, 48, 0.1),
        rgba(0, 0, 0, 0.35)
    );
    border: 1px solid rgba(240, 168, 160, 0.28);
}

.neg-card-name {
    font-family: "Cinzel", serif;
    color: var(--ta-gold);
    font-size: 0.9rem;
    font-weight: 700;
    margin: 0 0 4px;
}

.neg-card-desc {
    color: var(--ta-mute);
    font-style: italic;
    font-size: 0.82rem;
    line-height: 1.4;
    margin: 0;
}

.neg-card-flavor {
    color: var(--ta-bad);
    font-style: italic;
    font-size: 0.8rem;
    line-height: 1.4;
    margin-top: 4px;
}

/* -------------------------------------------------- Total summary */
.total-summary {
    padding: 16px 14px;
    border-radius: 17px;
    text-align: center;
    margin-bottom: 20px;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.96),
        rgba(16, 11, 6, 0.98)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.6);
    box-shadow: 0 6px 22px rgba(0, 0, 0, 0.6);
}

.total-summary .ta-divider {
    margin-top: 0;
}

.total-effects {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.event-row {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

.event-label {
    color: var(--ta-mute);
    font-style: italic;
    font-size: 0.82rem;
    font-family: "Cinzel", serif;
}

/* -------------------------------------------------- Special effects */
.special-effects-section {
    margin: 10px 0;
    padding: 9px 12px;
    border-radius: 12px;
    background: rgba(240, 192, 80, 0.1);
    border: 1px solid rgba(240, 192, 80, 0.4);
}

.special-effects-section.special-negative {
    background: rgba(208, 64, 48, 0.1);
    border-color: rgba(240, 168, 160, 0.4);
}

.special-effect-line {
    font-size: 0.85rem;
    color: var(--ta-text);
    margin-bottom: 4px;
    animation: fadeIn 0.3s ease;
}

.special-icon {
    margin-right: 6px;
}

.waiting-host-text {
    text-align: center;
    color: var(--ta-mute);
    font-style: italic;
    padding: 16px;
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
    .phase-section {
        padding: 13px 11px;
        margin-bottom: 10px;
    }

    .phase-title {
        font-size: 0.95rem;
    }

    .phase-note {
        font-size: 0.8rem;
        margin-bottom: 8px;
    }

    .pooled-card {
        padding: 6px 9px;
        font-size: 0.8rem;
    }

    .pooled-player {
        min-width: 70px;
        font-size: 0.75rem;
    }

    .pooled-card-title {
        font-size: 0.8rem;
    }

    .player-roll-row {
        flex-wrap: wrap;
        gap: 6px;
        padding: 7px 9px;
    }

    .roll-name {
        min-width: 78px;
        font-size: 0.8rem;
    }

    .face-badge {
        min-width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }

    .verdict {
        font-size: 1.3rem;
    }

    .total-summary {
        padding: 12px;
        margin-bottom: 12px;
    }

    .action-btn-top {
        margin-bottom: 10px;
    }
}
</style>
