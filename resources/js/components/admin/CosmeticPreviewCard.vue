<template>
    <!-- Single cosmetic rendered in the in-game dark card style, mirroring the
         parchment card from ItemPreviewCard.vue. The hero shows the cosmetic exactly
         as players see it in CosmeticPicker.vue: a LeagueCrest for crest parts, an
         avatar ring for frames, a card swatch for card backs, a glyph for victory
         effects, and the literal text for titles. An uploaded meta image, when
         present, takes precedence over the generated swatch. -->
    <div class="cosmetic-card">
        <div class="cosmetic-hero">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                class="cosmetic-image"
                :alt="cosmetic.name"
            />
            <div
                v-else-if="
                    cosmetic.type === 'crest_style' ||
                    cosmetic.type === 'crest_colour'
                "
                class="cosmetic-crest"
            >
                <LeagueCrest :variant="crestVariant" :tier="crestTier" />
            </div>
            <span
                v-else-if="cosmetic.type === 'title'"
                class="cosmetic-title-preview"
                >{{ cosmetic.value || cosmetic.name }}</span
            >
            <span
                v-else-if="cosmetic.type === 'frame'"
                class="cosmetic-frame"
                :class="`cpf-${cosmetic.value}`"
                >&#9876;</span
            >
            <span
                v-else-if="cosmetic.type === 'card_back'"
                class="cosmetic-cardback"
                :class="`cpc-${cosmetic.value}`"
            ></span>
            <span v-else class="cosmetic-fx">{{ fxGlyph }}</span>
        </div>

        <h3 class="cosmetic-name">{{ cosmetic.name || "Unnamed" }}</h3>

        <div class="tag-row">
            <span class="type-tag">{{ typeLabel }}</span>
            <span class="rarity-tag" :class="`rarity-${cosmetic.rarity}`">{{
                cosmetic.rarity
            }}</span>
        </div>

        <div class="cosmetic-divider">
            <span class="divider-ornament">&#9830;</span>
        </div>

        <p class="cosmetic-desc">{{ valueSummary }}</p>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import LeagueCrest from "../LeagueCrest.vue";

interface PreviewCosmetic {
    type:
        | "title"
        | "frame"
        | "card_back"
        | "victory_fx"
        | "crest_style"
        | "crest_colour";
    name: string;
    slug: string;
    rarity: string;
    value: string;
    imageUrl?: string;
}

const { cosmetic } = defineProps<{
    cosmetic: PreviewCosmetic;
}>();

const TYPE_LABELS: Record<string, string> = {
    title: "Title",
    frame: "Frame",
    card_back: "Card Back",
    victory_fx: "Victory Effect",
    crest_style: "Crest Style",
    crest_colour: "Crest Colour",
};

// Emoji glyph per victory effect render key (mirrors CosmeticPicker.vue's fxGlyph).
const FX_GLYPHS: Record<string, string> = {
    gold_rain: "\u{1FA99}",
    petals: "\u{1F338}",
    embers: "\u{1F525}",
    confetti: "\u{1F389}",
    fireworks: "\u{1F386}",
    starfall: "\u{2B50}",
    snow: "\u{2744}",
};

const imageUrl = computed<string | undefined>(() => cosmetic.imageUrl);

const typeLabel = computed<string>(
    () => TYPE_LABELS[cosmetic.type] ?? cosmetic.type,
);

const fxGlyph = computed<string>(() => FX_GLYPHS[cosmetic.value] ?? "\u{2728}");

// Parse the numeric crest render value; blank/invalid falls back to a sane default.
const crestValue = computed<number | undefined>(() => {
    if (cosmetic.value.trim() === "") {
        return undefined;
    }
    const parsed = Number(cosmetic.value);
    return Number.isSafeInteger(parsed) ? parsed : undefined;
});

// A crest_style cosmetic drives the shield shape (variant) on a neutral colour;
// a crest_colour cosmetic drives the palette (tier) on a neutral shape.
const crestVariant = computed<number>(() =>
    cosmetic.type === "crest_style" ? (crestValue.value ?? 1) : 1,
);
const crestTier = computed<number>(() =>
    cosmetic.type === "crest_colour" ? (crestValue.value ?? 0) : 0,
);

// A short human summary of the saved value, standing in for the missing description.
const valueSummary = computed<string>(() => {
    if (cosmetic.type === "title") {
        return `Nameplate text: "${cosmetic.value || cosmetic.name}"`;
    }
    if (cosmetic.type === "crest_style") {
        return `Shield shape #${cosmetic.value}`;
    }
    if (cosmetic.type === "crest_colour") {
        return `Colour palette #${cosmetic.value}`;
    }
    return `Render key: ${cosmetic.value}`;
});
</script>

<style scoped>
.cosmetic-card {
    background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
    border: 2px solid var(--border-gold, #6b5b3a);
    border-radius: 12px;
    padding: 24px 20px;
    min-height: 260px;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.cosmetic-hero {
    height: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.cosmetic-image {
    max-width: 88px;
    max-height: 88px;
    border-radius: 8px;
    border: 1px solid rgba(212, 168, 67, 0.3);
}

.cosmetic-crest {
    width: 66px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cosmetic-title-preview {
    font-family: "Cinzel", serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #f2c14e;
    text-align: center;
}

/* Frame preview — an avatar ring by value (mirrors CosmeticPicker.vue) */
.cosmetic-frame {
    width: 66px;
    height: 66px;
    border-radius: 50%;
    border: 3px solid #8a8f98;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: var(--accent-gold, #c9a84c);
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

/* Card back preview — a card swatch by value (mirrors CosmeticPicker.vue) */
.cosmetic-cardback {
    width: 58px;
    height: 80px;
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

.cosmetic-fx {
    font-size: 2.6rem;
}

.cosmetic-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold, #c9a84c);
    font-size: 1.15rem;
    text-align: center;
    margin-bottom: 8px;
    line-height: 1.3;
}

.tag-row {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
}

.type-tag {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-radius: 3px;
    padding: 1px 8px;
    color: var(--text-secondary, #a09080);
    border: 1px solid var(--text-secondary, #a09080);
}

.rarity-tag {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-radius: 3px;
    padding: 1px 8px;
}

.rarity-common {
    background: rgba(150, 150, 150, 0.2);
    color: #b0b0b0;
}
.rarity-rare {
    background: rgba(52, 152, 219, 0.2);
    color: #5dade2;
}
.rarity-epic {
    background: rgba(155, 89, 182, 0.2);
    color: #bb8fce;
}
.rarity-legendary {
    background: rgba(212, 168, 67, 0.2);
    color: #d4a843;
}

.cosmetic-divider {
    position: relative;
    width: 80%;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        var(--border-gold, #6b5b3a),
        transparent
    );
    margin: 14px 0;
}

.divider-ornament {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #2a1f14;
    color: var(--accent-gold, #c9a84c);
    padding: 0 8px;
    font-size: 0.7rem;
}

.cosmetic-desc {
    color: var(--text-primary, #e8d5b0);
    font-style: italic;
    font-size: 0.85rem;
    line-height: 1.5;
    text-align: center;
}
</style>
