<template>
    <Teleport to="body">
        <Transition appear name="cp">
            <div v-if="open" class="cp-overlay" @click.self="$emit('close')">
                <div class="cp-modal">
                    <div class="cp-header">
                        <span class="cp-title">{{ heading }}</span>
                        <button
                            class="cp-close"
                            aria-label="Close"
                            @click="$emit('close')"
                        >
                            &times;
                        </button>
                    </div>

                    <div class="cp-body">
                        <!-- Crest: two sub-pickers (shape + colour) with a live badge -->
                        <template v-if="kind === 'crest'">
                            <div class="cp-crest-hero">
                                <div class="cp-crest-badge">
                                    <LeagueCrest
                                        :variant="crestStyle"
                                        :tier="crestColour"
                                    />
                                </div>
                                <span class="cp-crest-name">{{
                                    crestName
                                }}</span>
                            </div>

                            <span class="cp-sub">Shape</span>
                            <div class="cp-grid cp-grid--crest">
                                <button
                                    v-for="s in crestStyles"
                                    :key="s.id"
                                    class="cp-option"
                                    :class="{
                                        active: Number(s.value) === crestStyle,
                                        locked: !s.owned,
                                    }"
                                    :disabled="!s.owned"
                                    :title="
                                        s.owned ? s.name : `${s.name} — locked`
                                    "
                                    @click="equip(s)"
                                >
                                    <div class="cp-crest-swatch">
                                        <LeagueCrest
                                            :variant="Number(s.value)"
                                            :tier="crestColour"
                                        />
                                    </div>
                                    <span v-if="!s.owned" class="cp-lock"
                                        >&#128274;</span
                                    >
                                </button>
                            </div>

                            <span class="cp-sub">Colour</span>
                            <div class="cp-grid cp-grid--crest">
                                <button
                                    v-for="c in crestColours"
                                    :key="c.id"
                                    class="cp-option"
                                    :class="{
                                        active: Number(c.value) === crestColour,
                                        locked: !c.owned,
                                    }"
                                    :disabled="!c.owned"
                                    :title="
                                        c.owned ? c.name : `${c.name} — locked`
                                    "
                                    @click="equip(c)"
                                >
                                    <div class="cp-crest-swatch">
                                        <LeagueCrest
                                            :variant="crestStyle"
                                            :tier="Number(c.value)"
                                        />
                                    </div>
                                    <span v-if="!c.owned" class="cp-lock"
                                        >&#128274;</span
                                    >
                                </button>
                            </div>
                        </template>

                        <!-- Single-slot cosmetics: title / frame / card back / effect -->
                        <template v-else>
                            <button
                                v-if="kind === 'victory_fx'"
                                class="cp-preview-btn"
                                @click="previewEffect"
                            >
                                &#9654; Preview effect
                            </button>
                            <div v-if="items.length === 0" class="cp-empty">
                                Nothing here yet.
                            </div>
                            <div class="cp-grid">
                                <button
                                    v-for="item in items"
                                    :key="item.id"
                                    class="cp-option cp-option--tall"
                                    :class="{
                                        active: item.active,
                                        locked: !item.owned,
                                    }"
                                    :disabled="!item.owned"
                                    @click="equip(item)"
                                >
                                    <!-- Title: the text itself -->
                                    <span
                                        v-if="kind === 'title'"
                                        class="cp-title-preview"
                                        >{{ item.value }}</span
                                    >
                                    <!-- Frame: an avatar ring -->
                                    <span
                                        v-else-if="kind === 'frame'"
                                        class="cp-frame"
                                        :class="`cpf-${item.value}`"
                                        >&#9876;</span
                                    >
                                    <!-- Card back: a card swatch -->
                                    <span
                                        v-else-if="kind === 'card_back'"
                                        class="cp-cardback"
                                        :class="`cpc-${item.value}`"
                                    ></span>
                                    <!-- Effect: a representative glyph -->
                                    <span v-else class="cp-fx">{{
                                        fxGlyph(item.value)
                                    }}</span>

                                    <span class="cp-option-name">{{
                                        item.name
                                    }}</span>
                                    <span class="cp-option-rarity">{{
                                        item.rarity
                                    }}</span>
                                    <span v-if="!item.owned" class="cp-lock"
                                        >&#128274;</span
                                    >
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
                <VictoryEffect ref="previewFx" />
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, useTemplateRef } from "vue";
import { useCosmetics } from "../stores/cosmetics";
import type { Cosmetic } from "../stores/cosmetics";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import { playSound } from "../sounds";
import LeagueCrest from "./LeagueCrest.vue";
import VictoryEffect from "./VictoryEffect.vue";

type EffectType =
    | "gold_rain"
    | "petals"
    | "embers"
    | "confetti"
    | "fireworks"
    | "starfall"
    | "snow";

const { kind, open = false } = defineProps<{
    /**
    Which cosmetic slot this picker edits.
    */
    kind: "title" | "crest" | "frame" | "card_back" | "victory_fx";
    open?: boolean;
}>();

defineEmits<{ close: [] }>();

// Narrow an arbitrary cosmetic value to a known effect (unknown => gold rain).
function toEffect(value: string | null | undefined): EffectType {
    switch (value) {
        case "petals":
        case "embers":
        case "confetti":
        case "fireworks":
        case "starfall":
        case "snow": {
            return value;
        }
        default: {
            return "gold_rain";
        }
    }
}

const cosmetics = useCosmetics();
const auth = useAuth();
const toast = useToast();

const HEADINGS: Record<string, string> = {
    title: "Nameplate",
    crest: "Crest",
    frame: "Avatar Frame",
    card_back: "Card Back",
    victory_fx: "Victory Effect",
};

const heading = computed(() => HEADINGS[kind] ?? "Cosmetics");

const items = computed(() =>
    cosmetics.state.cosmetics.filter((c) => c.type === kind),
);

const crestStyles = computed(() =>
    cosmetics.state.cosmetics.filter((c) => c.type === "crest_style"),
);
const crestColours = computed(() =>
    cosmetics.state.cosmetics.filter((c) => c.type === "crest_colour"),
);
const crestStyle = computed(() => auth.state.user?.crest_style ?? 1);
const crestColour = computed(() => auth.state.user?.crest_colour ?? 0);
const crestName = computed(() => {
    const shape = crestStyles.value.find(
        (c) => Number(c.value) === crestStyle.value,
    );
    const colour = crestColours.value.find(
        (c) => Number(c.value) === crestColour.value,
    );
    return [colour?.name, shape?.name].filter(Boolean).join(" ") || "Crest";
});

function fxGlyph(value: string | null): string {
    const glyphs: Record<string, string> = {
        gold_rain: "\u{1FA99}",
        petals: "\u{1F338}",
        embers: "\u{1F525}",
        confetti: "\u{1F389}",
        fireworks: "\u{1F386}",
        starfall: "\u{2B50}",
        snow: "\u{2744}",
    };
    return glyphs[value ?? ""] ?? "✨";
}

const previewFx = useTemplateRef<{
    fire: (type: EffectType, count?: number) => void;
}>("previewFx");

// The equipped victory effect's render key (falls back to gold rain).
const activeEffect = computed<EffectType>(() => {
    const slug = cosmetics.state.active?.victory_fx;
    const value =
        cosmetics.state.cosmetics.find((c) => c.slug === slug)?.value ??
        auth.state.user?.victory_fx;
    return toEffect(value);
});

// Play the equipped victory effect so the player can see what it looks like.
function previewEffect(): void {
    playSound("win");
    previewFx.value?.fire(activeEffect.value);
}

// Equip on tap. The display updates itself, so there's no success toast.
async function equip(part: Cosmetic): Promise<void> {
    if (!part.owned || part.active) {
        return;
    }
    try {
        await cosmetics.equip(part);
        playSound("clickCard");
        // Keep the live crest badge in sync (the store only mirrors title/frame).
        if (auth.state.user) {
            switch (part.type) {
                case "crest_style": {
                    auth.state.user.crest_style = Number(part.value);

                    break;
                }
                case "crest_colour": {
                    auth.state.user.crest_colour = Number(part.value);

                    break;
                }
                case "victory_fx": {
                    auth.state.user.victory_fx = part.value ?? undefined;

                    break;
                }
                // No default
            }
        }
    } catch {
        toast.error("Couldn't equip that.");
    }
}
</script>

<style scoped>
.cp-overlay {
    position: fixed;
    inset: 0;
    background: rgba(5, 3, 1, 0.7);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    z-index: 2000;
}

.cp-modal {
    width: 100%;
    max-width: 460px;
    height: 70vh;
    min-height: 320px;
    display: flex;
    flex-direction: column;
    background: linear-gradient(
        180deg,
        rgba(30, 22, 13, 0.99),
        rgba(13, 9, 5, 0.99)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.5);
    border-radius: 18px 18px 0 0;
    box-shadow: 0 -16px 40px rgba(0, 0, 0, 0.7);
}

/* Slide the sheet up from the bottom + fade the scrim. */
.cp-enter-active,
.cp-leave-active {
    transition: opacity 0.22s ease;
}

.cp-enter-from,
.cp-leave-to {
    opacity: 0;
}

.cp-enter-active .cp-modal,
.cp-leave-active .cp-modal {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.cp-enter-from .cp-modal,
.cp-leave-to .cp-modal {
    transform: translateY(100%);
}

.cp-header {
    flex: none;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px;
    border-bottom: 1px solid rgba(240, 192, 80, 0.22);
}

.cp-title {
    flex: 1;
    font-family: "Cinzel", serif;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: var(--accent-gold);
}

.cp-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    padding: 0 4px;
    box-shadow: none;
}

.cp-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    scrollbar-width: none;
    padding: 14px 16px calc(16px + env(safe-area-inset-bottom));
}

.cp-body::-webkit-scrollbar {
    display: none;
}

.cp-sub {
    display: block;
    margin: 12px 0 6px;
    font-family: "Cinzel", serif;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 1.6px;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.cp-preview-btn {
    display: block;
    margin: 0 auto 12px;
    font-family: "Cinzel", serif;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 8px 18px;
    border-radius: 10px;
    background: rgba(240, 192, 80, 0.12);
    border: 1px solid rgba(240, 192, 80, 0.4);
    color: var(--accent-gold);
    cursor: pointer;
    box-shadow: none;
}

.cp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 8px;
}

.cp-grid--crest {
    grid-template-columns: repeat(5, 1fr);
}

.cp-option {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 8px 6px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 80, 0.22);
    cursor: pointer;
    box-shadow: none;
}

.cp-option--tall {
    min-height: 92px;
    justify-content: center;
}

.cp-option.active {
    border-color: var(--accent-gold);
    background: rgba(240, 192, 80, 0.12);
    box-shadow: 0 0 10px rgba(240, 192, 80, 0.3);
}

.cp-option.locked {
    cursor: default;
    opacity: 0.5;
}

.cp-lock {
    position: absolute;
    top: 4px;
    right: 6px;
    font-size: 0.8rem;
}

.cp-option-name {
    font-family: "Cinzel", serif;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-primary);
    text-align: center;
    line-height: 1.1;
}

.cp-option-rarity {
    font-size: 0.58rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
}

/* Crest hero + swatches */
.cp-crest-hero {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 6px;
}

.cp-crest-badge {
    width: 46px;
    height: 56px;
    flex: none;
}

.cp-crest-name {
    font-family: "Cinzel", serif;
    font-weight: 700;
    color: var(--accent-gold);
}

.cp-crest-swatch {
    width: 100%;
    height: 46px;
    display: flex;
    justify-content: center;
}

.cp-option.locked :deep(.league-crest) {
    filter: grayscale(0.8) brightness(0.7);
}

/* Title preview */
.cp-title-preview {
    font-family: "Cinzel", serif;
    font-size: 0.85rem;
    font-weight: 700;
    color: #f2c14e;
    text-align: center;
}

/* Frame preview — an avatar ring by value */
.cp-frame {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 3px solid #8a8f98;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: var(--accent-gold);
    background: linear-gradient(135deg, #3a2a1a, #1a1209);
}

.cpf-bronze {
    border-color: #c17a3a;
    box-shadow: 0 0 8px rgba(193, 122, 58, 0.5);
}
.cpf-silver {
    border-color: #cbd5e1;
    box-shadow: 0 0 10px rgba(203, 213, 225, 0.6);
}
.cpf-gold {
    border-color: #f2c14e;
    box-shadow: 0 0 12px rgba(242, 193, 78, 0.7);
}
.cpf-royal {
    border-color: #b072e0;
    box-shadow: 0 0 14px rgba(176, 114, 224, 0.8);
}

/* Card back preview — a card swatch by value */
.cp-cardback {
    width: 38px;
    height: 52px;
    border-radius: 6px;
    border: 1px solid rgba(240, 192, 80, 0.5);
    background: linear-gradient(135deg, #d8c9a0, #a8895a);
}

.cpc-midnight {
    background: linear-gradient(135deg, #2a3a5c, #0d1326);
}
.cpc-royal_crest {
    background: linear-gradient(135deg, #4a2c6b, #241033);
}

/* Effect preview */
.cp-fx {
    font-size: 1.6rem;
}

.cp-empty {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 24px;
}
</style>
