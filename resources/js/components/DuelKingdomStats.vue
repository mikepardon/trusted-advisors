<template>
    <div class="duel-kingdoms">
        <div
            v-for="kingdom in kingdoms"
            :key="kingdom.id"
            :ref="(el) => setColReference(kingdom.player_number, el)"
            class="kingdom-col"
            :class="{
                'kingdom-yours': kingdom.player_number === myPlayerNumber,
            }"
            :data-kingdom-style="getKingdomStyle(kingdom.player_number)"
            :data-ks-anim="getKingdomAnim(kingdom.player_number)"
            :style="getKingdomColStyle(kingdom.player_number)"
        >
            <!-- Full-column dice canvas overlay -->
            <canvas
                :ref="(el) => setCanvasReference(kingdom.player_number, el)"
                class="kingdom-dice-canvas"
            />

            <div class="kingdom-identity">
                <div class="kingdom-crest">
                    <LeagueCrest
                        :variant="kingdom.crest_style"
                        :tier="kingdom.crest_colour"
                    />
                </div>
                <h3 class="kingdom-name">{{ kingdom.username }}</h3>
            </div>
            <span
                v-if="getTitle(kingdom.player_number)"
                class="kingdom-title"
                >{{ getTitle(kingdom.player_number) }}</span
            >
            <h5
                class="kingdom-username kingdom-name-clickable"
                @click="$emit('showCharacter', kingdom.player_number)"
            >
                {{ kingdom.character_name }}
            </h5>

            <!-- Roll info (always rendered to prevent layout shift) -->
            <div class="kingdom-roll-info">
                <div
                    class="roll-info required-roll"
                    :class="{
                        'roll-info-hidden': !getDifficulty(
                            kingdom.player_number,
                        ),
                    }"
                >
                    <template v-if="getDifficulty(kingdom.player_number)"
                        >Required:
                        <strong>{{
                            getDifficulty(kingdom.player_number)
                        }}</strong></template
                    >
                    <template v-else>&nbsp;</template>
                </div>
                <div
                    class="roll-info actual-roll"
                    :class="[
                        getRollResult(kingdom.player_number)
                            ? isSuccess(kingdom.player_number)
                                ? 'roll-success'
                                : 'roll-failure'
                            : '',
                        {
                            'roll-info-hidden': !getRollResult(
                                kingdom.player_number,
                            ),
                        },
                    ]"
                >
                    <template v-if="getRollResult(kingdom.player_number)">
                        Rolled:
                        <strong>{{
                            getRollResult(kingdom.player_number).total_roll
                        }}</strong>
                        <span class="result-badge">{{
                            isSuccess(kingdom.player_number)
                                ? "SUCCESS"
                                : "FAILURE"
                        }}</span>
                    </template>
                    <template v-else>&nbsp;</template>
                </div>
            </div>

            <div class="kingdom-stats">
                <div v-for="stat in stats" :key="stat.key" class="stat-cell">
                    <div class="radial-wrap">
                        <svg class="radial-svg" viewBox="0 0 48 48">
                            <circle class="radial-bg" cx="24" cy="24" r="20" />
                            <circle
                                class="radial-fill"
                                :class="[
                                    getDuelBarClass(kingdom, stat.key),
                                    barFlashClass[kingdom.id + '-' + stat.key],
                                ]"
                                cx="24"
                                cy="24"
                                r="20"
                                :style="{
                                    strokeDashoffset: radialOffset(
                                        kingdom[stat.key],
                                    ),
                                    '--stat-color': statColor(stat.key),
                                }"
                            />
                        </svg>
                        <span
                            class="radial-value"
                            :style="{ color: statColor(stat.key) }"
                            >{{ kingdom[stat.key] }}</span
                        >
                    </div>
                    <span class="radial-label">{{ statLabel(stat.key) }}</span>
                </div>
            </div>
            <div class="kingdom-total">
                <strong>{{ kingdomTotal(kingdom) }}</strong>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    shallowRef,
    watch,
} from "vue";
import LeagueCrest from "./LeagueCrest.vue";
import { useIcons } from "../stores/icons";
import { createDddiceInstance, isDddiceAvailable } from "../dddice-service";
import "../styles/kingdom-styles.css";

interface StatIcon {
    key: string;
    type: string;
    value: string;
}

interface KingdomStatValues {
    wealth?: number;
    influence?: number;
    security?: number;
    religion?: number;
    food?: number;
    happiness?: number;
}

interface PlayerKingdom extends KingdomStatValues {
    id: number;
    player_number?: number;
    player?: {
        player_number?: number;
        is_bot?: boolean;
        user?: { name?: string };
        character?: { name?: string };
        crest_style?: number;
        crest_colour?: number;
    };
    [key: string]: unknown;
}

interface DisplayKingdom extends PlayerKingdom {
    player_number: number;
    character_name: string;
    username: string;
    crest_style: number;
    crest_colour: number;
}

interface RollResult {
    total_roll: number;
    rolls?: { value: number }[];
}

interface KingdomStyleData {
    css_vars?: {
        border_anim?: string;
        border_color?: string;
        border_glow?: string;
        border_color_rgb?: string;
        bg_tint?: string;
        bg_color?: string;
        name_accent?: string;
        total_accent?: string;
        bar_safe?: string;
        bar_caution?: string;
        stat_color?: string;
        text_color?: string;
    };
    background_image_url?: string;
}

interface PreviewEffects {
    positive?: Record<string, number>;
    negative?: Record<string, number>;
}

interface DiceAnimationTrigger {
    playerNumber: number;
    rollResult: RollResult;
    themes?: string[];
    timestamp: number;
}

interface DiceInstance {
    isReady: () => boolean;
    init: (
        canvas: HTMLCanvasElement,
        options: { diceSize: number },
    ) => Promise<boolean>;
    roll: (diceSpecs: { theme: string; value: number }[]) => Promise<void>;
    resize: (width: number, height: number) => void;
    clear: () => void;
    destroy: () => void;
}

const {
    playerKingdoms = [],
    myPlayerNumber = 1,
    isSinglePlayerDuel = false,
    playerDifficulties = {},
    playerRollResults = {},
    diceAnimationTrigger = undefined,
    playerKingdomStyles = {},
    playerKingdomStyleData = {},
    playerTitles = {},
    previewEffects = undefined,
} = defineProps<{
    playerKingdoms?: PlayerKingdom[];
    myPlayerNumber?: number;
    isSinglePlayerDuel?: boolean;
    playerDifficulties?: Record<number, number | undefined>;
    playerRollResults?: Record<number, RollResult | undefined>;
    playerDiceThemes?: Record<number, string[] | undefined>;
    diceAnimationTrigger?: DiceAnimationTrigger;
    playerKingdomStyles?: Record<number, string | undefined>;
    playerKingdomStyleData?: Record<number, KingdomStyleData | undefined>;
    playerTitles?: Record<number, string | undefined>;
    previewEffects?: PreviewEffects;
}>();

const emit = defineEmits<{
    showCharacter: [playerNumber: number];
    diceAnimationComplete: [
        payload: { playerNumber: number; timestamp: number },
    ];
}>();

const stats: StatIcon[] = useIcons().getStatIcons();
const previousValues = reactive<Record<string, number | undefined>>({});
const tweenedValues = reactive<Record<string, number>>({});
const flashClass = reactive<Record<string, string>>({});
const barFlashClass = reactive<Record<string, string>>({});
const flashTimers: Record<string, ReturnType<typeof setTimeout>> = {};
const tweenTimers: Record<string, number> = {};
// Dice instances per player
const diceInstances: Record<number, DiceInstance | undefined> = {};
const canvasReferences: Record<number, HTMLCanvasElement | undefined> = {};
const colReferences: Record<number, HTMLElement | undefined> = {};
const resizeObserver = shallowRef<ResizeObserver | undefined>(undefined);

const kingdoms = computed<DisplayKingdom[]>(() =>
    playerKingdoms
        .map((kingdom) => {
            const playerNumber =
                kingdom.player?.player_number ?? kingdom.player_number ?? 0;
            const isRealBot =
                Boolean(kingdom.player?.is_bot) && !kingdom.player?.user;
            const characterName = kingdom.player?.character?.name ?? "Advisor";
            const displayUsername = isRealBot
                ? "Bot"
                : kingdom.player?.user?.name || "Player";
            return {
                ...kingdom,
                player_number: playerNumber,
                character_name: characterName,
                username:
                    playerNumber === myPlayerNumber && isSinglePlayerDuel
                        ? `${displayUsername} (YOU)`
                        : displayUsername,
                crest_style: kingdom.player?.crest_style ?? 1,
                crest_colour: kingdom.player?.crest_colour ?? 0,
            };
        })
        .toSorted((first, second) => {
            if (first.player_number === myPlayerNumber) {
                return -1;
            }
            if (second.player_number === myPlayerNumber) {
                return 1;
            }
            return first.player_number - second.player_number;
        }),
);

watch(
    () => playerKingdoms,
    (newKingdoms) => {
        if (!newKingdoms) {
            return;
        }
        for (const kingdom of newKingdoms) {
            const id = kingdom.id;
            for (const stat of stats) {
                const key = `${id}-${stat.key}`;
                const oldValue = previousValues[key];
                const rawValue = kingdom[stat.key];
                const newValue = typeof rawValue === "number" ? rawValue : 0;
                if (oldValue === undefined) {
                    tweenedValues[key] = newValue;
                } else if (newValue !== oldValue) {
                    if (Object.hasOwn(flashTimers, key)) {
                        clearTimeout(flashTimers[key]);
                    }
                    const direction = newValue > oldValue ? "up" : "down";
                    flashClass[key] = `flash-${direction}`;
                    barFlashClass[key] = `bar-flash-${direction}`;
                    flashTimers[key] = setTimeout(() => {
                        flashClass[key] = "";
                        barFlashClass[key] = "";
                    }, 1200);
                    animateValue(key, oldValue, newValue);
                }
                previousValues[key] = newValue;
            }
        }
    },
    { deep: true, immediate: true },
);

watch(
    () => diceAnimationTrigger,
    async (trigger) => {
        if (!trigger) {
            return;
        }
        const { playerNumber, rollResult, themes, timestamp } = trigger;
        const instance = diceInstances[playerNumber];
        if (!instance || !instance.isReady()) {
            emit("diceAnimationComplete", { playerNumber, timestamp });
            return;
        }

        const diceSpecs = (rollResult.rolls || []).map((roll, index) => ({
            theme: (themes || [])[index] || "dddice-standard",
            value: roll.value,
        }));

        if (diceSpecs.length > 0) {
            try {
                await instance.roll(diceSpecs);
            } catch (error) {
                console.warn("[dddice] Kingdom dice animation failed:", error);
            }
        }
        emit("diceAnimationComplete", { playerNumber, timestamp });
    },
    { deep: true },
);

function clearDice(playerNumber?: number): void {
    if (playerNumber) {
        diceInstances[playerNumber]?.clear();
    } else {
        for (const number of [1, 2]) {
            diceInstances[number]?.clear();
        }
    }
}

function setCanvasReference(playerNumber: number, element: unknown): void {
    if (element instanceof HTMLCanvasElement) {
        canvasReferences[playerNumber] = element;
    }
}

function setColReference(playerNumber: number, element: unknown): void {
    if (element instanceof HTMLElement) {
        colReferences[playerNumber] = element;
    }
}

async function initDiceInstance(playerNumber: number): Promise<void> {
    const canvas = canvasReferences[playerNumber];
    if (!canvas) {
        return;
    }
    const instance: DiceInstance = createDddiceInstance();
    const ok = await instance.init(canvas, { diceSize: 1.6 });
    if (ok) {
        diceInstances[playerNumber] = instance;
    }
}

function getKingdomStyle(playerNumber: number): string {
    return playerKingdomStyles[playerNumber] || "classic";
}

function getKingdomAnim(playerNumber: number): string {
    return (
        playerKingdomStyleData[playerNumber]?.css_vars?.border_anim || "none"
    );
}

function getKingdomColStyle(playerNumber: number): Record<string, string> {
    const data = playerKingdomStyleData[playerNumber];
    const style: Record<string, string> = {};
    // Apply css_vars from DB as inline custom properties (overrides static CSS)
    if (data?.css_vars) {
        const cssVariables = data.css_vars;
        if (cssVariables.border_color) {
            style["--ks-border-color"] = cssVariables.border_color;
        }
        if (cssVariables.border_glow) {
            style["--ks-border-glow"] = cssVariables.border_glow;
        }
        if (cssVariables.border_color_rgb) {
            style["--ks-border-color-rgb"] = cssVariables.border_color_rgb;
        }
        if (cssVariables.bg_tint) {
            style["--ks-bg-tint"] = cssVariables.bg_tint;
        }
        if (cssVariables.bg_color) {
            style["--ks-bg-color"] = cssVariables.bg_color;
        }
        if (cssVariables.name_accent) {
            style["--ks-name-accent"] = cssVariables.name_accent;
        }
        if (cssVariables.total_accent) {
            style["--ks-total-accent"] = cssVariables.total_accent;
        }
        if (cssVariables.bar_safe) {
            style["--ks-bar-safe"] = cssVariables.bar_safe;
        }
        if (cssVariables.bar_caution) {
            style["--ks-bar-caution"] = cssVariables.bar_caution;
        }
        if (cssVariables.stat_color) {
            style["--ks-stat-color"] = cssVariables.stat_color;
        }
        if (cssVariables.text_color) {
            style["--ks-text-color"] = cssVariables.text_color;
        }
    }
    // Apply background image
    if (data?.background_image_url) {
        style.backgroundImage = `linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url(${data.background_image_url})`;
        style.backgroundSize = "cover";
        style.backgroundPosition = "center";
    }
    return style;
}

function getTitle(playerNumber: number): string | undefined {
    return playerTitles[playerNumber] || undefined;
}

function getDifficulty(playerNumber: number): number | undefined {
    return playerDifficulties[playerNumber] || undefined;
}

function getRollResult(playerNumber: number): RollResult | undefined {
    return playerRollResults[playerNumber] || undefined;
}

function isSuccess(playerNumber: number): boolean {
    const result = getRollResult(playerNumber);
    const difficulty = getDifficulty(playerNumber);
    if (!result || !difficulty) {
        return false;
    }
    return result.total_roll >= difficulty;
}

function animateValue(key: string, from: number, to: number): void {
    if (Object.hasOwn(tweenTimers, key)) {
        cancelAnimationFrame(tweenTimers[key]);
    }
    const duration = 800;
    const start = performance.now();
    const step = (now: number): void => {
        const progress = Math.min((now - start) / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        tweenedValues[key] = Math.round(from + (to - from) * ease);
        if (progress < 1) {
            tweenTimers[key] = requestAnimationFrame(step);
        }
    };
    tweenTimers[key] = requestAnimationFrame(step);
}

function kingdomTotal(kingdom: KingdomStatValues): number {
    return (
        (kingdom.wealth || 0) +
        (kingdom.influence || 0) +
        (kingdom.security || 0) +
        (kingdom.religion || 0) +
        (kingdom.food || 0) +
        (kingdom.happiness || 0)
    );
}

function getDuelBarClass(kingdom: DisplayKingdom, statKey: string): string {
    if (previewEffects && kingdom.player_number === myPlayerNumber) {
        const positive = previewEffects.positive?.[statKey] || 0;
        const negative = previewEffects.negative?.[statKey] || 0;
        if (positive > 0 && negative < 0) {
            return "bar-preview-mixed";
        }
        if (positive > 0) {
            return "bar-preview-positive";
        }
        if (negative < 0) {
            return "bar-preview-negative";
        }
    }
    return "bar-default";
}

function radialOffset(value: number | undefined): number {
    const circumference = 2 * Math.PI * 20;
    const percentage = Math.min(value ?? 0, 20) / 20;
    return circumference * (1 - percentage);
}

// Thematic label + signature colour per stat, matching the solo KingdomStats board.
function statLabel(statKey: string): string {
    const labels: Record<string, string> = {
        wealth: "Wealth",
        influence: "Influence",
        security: "Security",
        religion: "Religion",
        food: "Food",
        happiness: "Happiness",
    };
    return labels[statKey] ?? statKey;
}

function statColor(statKey: string): string {
    const colours: Record<string, string> = {
        wealth: "#f0c050",
        influence: "#b072e0",
        security: "#4d9de0",
        religion: "#cdd3dc",
        food: "#5fad56",
        happiness: "#e0685a",
    };
    return colours[statKey] ?? "#f0c050";
}

onMounted(async () => {
    if (!isDddiceAvailable()) {
        return;
    }

    // Initialize dice instances after DOM is ready
    await nextTick();
    for (const playerNumber of [1, 2]) {
        await initDiceInstance(playerNumber);
    }

    // Set up ResizeObserver for canvas resizing
    const observer = new ResizeObserver((entries) => {
        for (const entry of entries) {
            const element = entry.target;
            for (const playerNumber of [1, 2]) {
                const instance = diceInstances[playerNumber];
                if (instance && colReferences[playerNumber] === element) {
                    const width = entry.contentRect.width;
                    const height = entry.contentRect.height;
                    if (width > 0 && height > 0) {
                        instance.resize(width, height);
                    }
                }
            }
        }
    });

    for (const playerNumber of [1, 2]) {
        const col = colReferences[playerNumber];
        if (col) {
            observer.observe(col);
        }
    }
    resizeObserver.value = observer;
});

onBeforeUnmount(() => {
    for (const id of Object.values(tweenTimers)) {
        cancelAnimationFrame(id);
    }
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
        resizeObserver.value = undefined;
    }
    for (const playerNumber of [1, 2]) {
        diceInstances[playerNumber]?.destroy();
        diceInstances[playerNumber] = undefined;
    }
});

defineExpose({ clearDice });
</script>

<style scoped>
.duel-kingdoms {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 16px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.3);
    border-radius: 15px;
    padding: 11px 8px;
}

.kingdom-col {
    display: flex;
    flex-direction: column;
    position: relative;
    border: 2px solid var(--ks-border-color, transparent);
    border-radius: 6px;
    box-shadow: var(--ks-border-glow, none);
    background-color: var(--ks-bg-color, var(--ks-bg-tint, transparent));
    padding: 4px;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease,
        background-color 0.3s ease;
}

.kingdom-col.kingdom-yours .kingdom-name {
    color: var(--ks-name-accent, var(--accent-gold));
}

.kingdom-identity {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-bottom: 2px;
    min-width: 0;
}

.kingdom-crest {
    width: 34px;
    height: 40px;
    flex: 0 0 auto;
}

.kingdom-name {
    font-family: "Cinzel", serif;
    font-weight: 700;
    letter-spacing: 0.4px;
    color: var(--ks-name-accent, var(--text-secondary));
    font-size: 0.8rem;
    text-align: center;
    margin-bottom: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.kingdom-title {
    display: block;
    font-family: "Cinzel", serif;
    font-style: italic;
    color: var(--ks-name-accent, var(--accent-gold));
    font-size: 0.55rem;
    text-align: center;
    margin-bottom: 2px;
    opacity: 0.8;
}

.kingdom-name-clickable {
    cursor: pointer;
    transition: color 0.2s;
}

.kingdom-name-clickable:hover {
    color: var(--accent-gold-bright);
    text-decoration: underline;
}

.kingdom-username {
    font-family: inherit;
    color: var(--ks-text-color, var(--text-secondary));
    font-size: 0.55rem;
    text-align: center;
    margin: -4px 0 4px;
    opacity: 0.7;
    font-weight: 400;
}

/* Full-column dice canvas overlay */
.kingdom-dice-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 5;
    border-radius: 6px;
}

/* Roll info */
.kingdom-roll-info {
    margin-bottom: 4px;
    min-height: 36px;
}

.roll-info-hidden {
    opacity: 0;
    visibility: hidden;
}

.roll-info {
    text-align: center;
    font-family: "Cinzel", serif;
    font-size: 0.72rem;
    padding: 2px 0;
    color: var(--text-secondary);
    transition:
        opacity 0.3s ease,
        visibility 0.3s ease;
}

.required-roll strong {
    color: var(--accent-gold);
}

.actual-roll {
    font-weight: 600;
}

.roll-success {
    color: #4a8a3a;
}

.roll-success strong {
    color: #5ea84a;
}

.roll-failure {
    color: #c0392b;
}

.roll-failure strong {
    color: #e74c3c;
}

.result-badge {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 1px 6px;
    border-radius: 3px;
    font-weight: 700;
    margin-left: 4px;
}

.roll-success .result-badge {
    background: rgba(74, 138, 58, 0.2);
}

.roll-failure .result-badge {
    background: rgba(160, 48, 32, 0.2);
}

.kingdom-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px 4px;
    flex: 1;
    justify-items: center;
}

.stat-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}

/* Radial progress */
.radial-wrap {
    position: relative;
    width: 44px;
    height: 44px;
}

.radial-svg {
    width: 44px;
    height: 44px;
    transform: rotate(-90deg);
}

.radial-value {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-family: "Cinzel", serif;
    font-weight: 800;
    font-size: 0.85rem;
    line-height: 1;
}

.radial-label {
    font-family: "Cinzel", serif;
    font-size: 0.48rem;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: #8a7a5a;
}

.radial-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.09);
    stroke-width: 4;
}

.radial-fill {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    stroke-dasharray: 125.66;
    transition: stroke-dashoffset 0.5s ease;
}

.radial-fill.bar-default {
    stroke: var(--stat-color, #d4a843);
    transition: stroke 0.25s ease;
}
.radial-fill.bar-preview-positive {
    stroke: #4caf50;
    transition: stroke 0.25s ease;
}
.radial-fill.bar-preview-negative {
    stroke: #e74c3c;
    transition: stroke 0.25s ease;
}
.radial-fill.bar-preview-mixed {
    stroke: #e67e22;
    transition: stroke 0.25s ease;
}

.radial-fill.bar-flash-up {
    stroke: #4caf50 !important;
}
.radial-fill.bar-flash-down {
    stroke: #e74c3c !important;
}

.flash-up {
    color: #4caf50 !important;
    text-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
}

.flash-down {
    color: #e74c3c !important;
    text-shadow: 0 0 8px rgba(231, 76, 60, 0.6);
}

.kingdom-total {
    text-align: center;
    margin-top: 0;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.kingdom-total strong {
    color: var(--ks-total-accent, var(--accent-gold));
    font-size: 0.95rem;
}

@media (max-width: 768px) {
    .duel-kingdoms {
        gap: 6px;
        padding: 6px;
    }

    .kingdom-name {
        font-size: 0.7rem;
    }

    .radial-wrap {
        width: 38px;
        height: 38px;
    }

    .radial-svg {
        width: 38px;
        height: 38px;
    }

    .radial-value {
        font-size: 0.75rem;
    }

    .radial-label {
        font-size: 0.44rem;
    }

    .roll-info {
        font-size: 0.65rem;
    }

    .result-badge {
        font-size: 0.55rem;
    }
}
</style>
