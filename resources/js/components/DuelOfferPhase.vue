<template>
    <div class="duel-offer">
        <div class="choose-divider">
            <span class="choose-divider-label">Choose a Card to Reveal</span>
            <span class="choose-divider-line"></span>
            <span class="choose-divider-note"
                >they pick this or the hidden one</span
            >
        </div>

        <!-- MOBILE: Swiper carousel -->
        <template v-if="isMobile">
            <div class="swiper-hand">
                <Swiper
                    :modules="swiperModules"
                    effect="cards"
                    :grab-cursor="true"
                    :cards-effect="{
                        perSlideOffset: 8,
                        perSlideRotate: 2,
                        rotate: true,
                        slideShadows: false,
                    }"
                    :style="{ overflow: 'visible' }"
                    @swiper="onSwiper"
                    @slide-change="onSlideChange"
                >
                    <SwiperSlide v-for="item in cards" :key="item.hand_id">
                        <div
                            class="parchment-card"
                            :class="{
                                'card-acting': selectedId === item.hand_id,
                                'card-unattended':
                                    selectedId !== null &&
                                    selectedId !== item.hand_id,
                            }"
                            @click="selectAndConfirm(item.hand_id)"
                        >
                            <div
                                v-if="selectedId === item.hand_id"
                                class="card-ribbon acting"
                            >
                                Revealing this
                            </div>
                            <div
                                v-else-if="selectedId !== null"
                                class="card-ribbon unattended"
                            >
                                Keeping this
                            </div>

                            <h3 class="parchment-title">
                                {{ item.card.title }}
                            </h3>
                            <p class="parchment-desc">
                                {{ item.card.description }}
                            </p>
                            <span class="parchment-difficulty"
                                >Difficulty {{ item.card.difficulty }}</span
                            >

                            <div class="parchment-divider">
                                <span class="divider-ornament">&#9830;</span>
                            </div>

                            <div class="outcome-section">
                                <p class="outcome-label">On Success:</p>
                                <div class="outcome-chips">
                                    <span
                                        v-for="(val, stat) in filterStatEffects(
                                            item.card.positive_effects,
                                        )"
                                        :key="'p-' + stat"
                                        class="stat-chip chip-positive"
                                        >{{ stat }}</span
                                    >
                                </div>
                            </div>

                            <div class="outcome-section">
                                <p class="outcome-label">On Failure:</p>
                                <div class="outcome-chips">
                                    <span
                                        v-for="(val, stat) in filterStatEffects(
                                            item.card.negative_effects,
                                        )"
                                        :key="'n-' + stat"
                                        class="stat-chip chip-negative"
                                        >{{ stat }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </SwiperSlide>
                </Swiper>
            </div>
        </template>

        <!-- DESKTOP: Side-by-side -->
        <div v-else class="offer-cards">
            <div
                v-for="item in cards"
                :key="item.hand_id"
                class="parchment-card"
                :class="{
                    'card-acting': selectedId === item.hand_id,
                    'card-unattended':
                        selectedId !== null && selectedId !== item.hand_id,
                }"
                @click="selectAndConfirm(item.hand_id)"
            >
                <div
                    v-if="selectedId === item.hand_id"
                    class="card-ribbon acting"
                >
                    Revealing this
                </div>
                <div
                    v-else-if="selectedId !== null"
                    class="card-ribbon unattended"
                >
                    Keeping this
                </div>

                <h3 class="parchment-title">{{ item.card.title }}</h3>
                <p class="parchment-desc">{{ item.card.description }}</p>
                <span class="parchment-difficulty"
                    >Difficulty {{ item.card.difficulty }}</span
                >

                <div class="parchment-divider">
                    <span class="divider-ornament">&#9830;</span>
                </div>

                <div class="outcome-section">
                    <p class="outcome-label">On Success:</p>
                    <div class="outcome-chips">
                        <span
                            v-for="(val, stat) in filterStatEffects(
                                item.card.positive_effects,
                            )"
                            :key="'p-' + stat"
                            class="stat-chip chip-positive"
                        ></span>
                    </div>
                </div>

                <div class="outcome-section">
                    <p class="outcome-label">On Failure:</p>
                    <div class="outcome-chips">
                        <span
                            v-for="(val, stat) in filterStatEffects(
                                item.card.negative_effects,
                            )"
                            :key="'n-' + stat"
                            class="stat-chip chip-negative"
                        ></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import { EffectCards } from "swiper/modules";
import type { Swiper as SwiperInstance } from "swiper";
import "swiper/css";
import "swiper/css/effect-cards";
import { playSound } from "../sounds";

interface DuelCard {
    title: string;
    description: string;
    difficulty: number;
    positive_effects: Record<string, unknown> | undefined;
    negative_effects: Record<string, unknown> | undefined;
}

interface DuelHandItem {
    hand_id: number;
    card: DuelCard;
}

const { cards = [] } = defineProps<{
    cards?: DuelHandItem[];
}>();

const emit = defineEmits<{
    offer: [handId: number];
}>();

const swiperModules = [EffectCards];

const selectedId = ref<number | undefined>(undefined);
const isMobile = ref(false);
const swiperInstance = ref<SwiperInstance | undefined>(undefined);
const mediaQuery = ref<MediaQueryList | undefined>(undefined);

function onMediaChange(event: MediaQueryListEvent): void {
    isMobile.value = event.matches;
}

function onSwiper(swiper: SwiperInstance): void {
    swiperInstance.value = swiper;
}

function onSlideChange(): void {
    // No-op: slide changes require no handling in the offer phase.
}

function selectAndConfirm(handId: number): void {
    if (selectedId.value !== undefined) {
        return;
    }
    playSound("clickCard");
    selectedId.value = handId;
    emit("offer", handId);
}

function filterStatEffects(
    effects: Record<string, unknown> | undefined,
): Record<string, unknown> {
    if (!effects) {
        return {};
    }
    const result: Record<string, unknown> = {};
    const specialKeys = new Set([
        "grant_item_id",
        "draw_item",
        "recover_die",
        "lose_die",
        "discard_item",
        "remove_curse",
    ]);
    for (const [key, value] of Object.entries(effects)) {
        if (!specialKeys.has(key)) {
            result[key] = value;
        }
    }
    return result;
}

watch(
    () => cards,
    () => {
        selectedId.value = undefined;
        if (swiperInstance.value) {
            nextTick(() => {
                swiperInstance.value?.slideTo(0, 0);
            });
        }
    },
);

onMounted(() => {
    mediaQuery.value = window.matchMedia("(max-width: 768px)");
    isMobile.value = mediaQuery.value.matches;
    mediaQuery.value.addEventListener("change", onMediaChange);
});

onBeforeUnmount(() => {
    mediaQuery.value?.removeEventListener("change", onMediaChange);
});
</script>

<style scoped>
.duel-offer {
    margin-bottom: 20px;
}

/* Section divider — "Choose a Card to Reveal" */
.choose-divider {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 12px 0 10px;
}

.choose-divider-label {
    font-family: "Cinzel", serif;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 2.2px;
    text-transform: uppercase;
    color: var(--accent-gold);
    white-space: nowrap;
}

.choose-divider-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(240, 192, 80, 0.4), transparent);
}

.choose-divider-note {
    font-size: 11px;
    font-style: italic;
    color: #8a7a5a;
    white-space: nowrap;
}

/* Desktop side-by-side */
.offer-cards {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Mobile swiper */
.swiper-hand {
    max-width: 340px;
    margin: 0 auto;
    padding: 10px 0;
}

.swiper-hand :deep(.swiper-slide) {
    padding: 40px 20px 10px;
}

/* Parchment card */
.parchment-card {
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.96),
        rgba(16, 11, 6, 0.98)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.6);
    border-radius: 16px;
    padding: 16px 14px;
    width: 300px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
    display: flex;
    flex-direction: column;
}

.parchment-card:hover {
    transform: translateY(-4px) scale(1.01);
    border-color: var(--accent-gold);
    box-shadow:
        0 8px 30px rgba(0, 0, 0, 0.6),
        0 0 15px rgba(212, 168, 67, 0.15);
}

.parchment-card.card-acting {
    border-color: var(--accent-gold);
    box-shadow:
        0 0 30px rgba(212, 168, 67, 0.35),
        0 0 60px rgba(212, 168, 67, 0.1),
        inset 0 1px 0 rgba(212, 168, 67, 0.15);
}

.parchment-card.card-unattended {
    opacity: 0.55;
    filter: saturate(0.6);
    transform: scale(0.97);
}

/* Ribbon */
.card-ribbon {
    position: absolute;
    top: -1px;
    left: 0;
    right: 0;
    padding: 4px 18px;
    font-family: "Cinzel", serif;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    border-radius: 0 0 6px 6px;
    text-align: center;
    z-index: 1;
}

.card-ribbon.acting {
    background: linear-gradient(180deg, #b8942e, #8a6a14);
    color: #1a1209;
}

.card-ribbon.unattended {
    background: rgba(100, 80, 60, 0.6);
    color: var(--text-secondary);
}

.parchment-title {
    font-family: "Cinzel", serif;
    font-weight: 700;
    color: #fff5e0;
    font-size: 0.95rem;
    line-height: 1.3;
    text-align: center;
    margin-bottom: 6px;
    margin-top: 4px;
}

.parchment-desc {
    color: var(--accent-gold);
    font-style: italic;
    font-size: 0.82rem;
    text-align: center;
    margin-bottom: 8px;
    line-height: 1.35;
}

.parchment-difficulty {
    display: block;
    text-align: center;
    font-family: "Cinzel", serif;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 3px 12px;
    background: rgba(240, 192, 80, 0.15);
    color: var(--accent-gold);
    border: 1px solid rgba(240, 192, 80, 0.3);
    border-radius: 8px;
    width: fit-content;
    margin: 0 auto 6px;
}

.parchment-divider {
    position: relative;
    display: flex;
    align-items: center;
    gap: 7px;
    height: auto;
    background: none;
    margin: 11px 0;
}

.parchment-divider::before,
.parchment-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: rgba(240, 192, 80, 0.28);
}

.divider-ornament {
    position: static;
    transform: none;
    background: none;
    color: #c8952e;
    padding: 0;
    font-size: 0.55rem;
}

.outcome-section {
    margin-bottom: 8px;
}
.outcome-label {
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 1.3px;
    text-transform: uppercase;
    text-align: left;
    margin-bottom: 6px;
}

.outcome-section:first-of-type .outcome-label {
    color: var(--ta-good, #8ef0c8);
}

.outcome-section:last-of-type .outcome-label {
    color: var(--ta-bad, #f0a8a0);
}

.outcome-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: flex-start;
}

.stat-chip {
    font-size: 11px;
    padding: 3px 6px;
    border-radius: 7px;
    border: 1px solid transparent;
    font-weight: 600;
    text-transform: capitalize;
    white-space: nowrap;
}

.chip-positive {
    color: var(--ta-good, #8ef0c8);
    background: rgba(92, 184, 92, 0.15);
    border-color: rgba(142, 240, 200, 0.4);
}

.chip-negative {
    color: var(--ta-bad, #f0a8a0);
    background: rgba(208, 64, 48, 0.15);
    border-color: rgba(240, 168, 160, 0.4);
}

@media (max-width: 768px) {
    .parchment-card {
        width: 100%;
        max-width: 320px;
        min-height: auto;
        padding: 13px 12px;
    }

    .parchment-title {
        font-size: 0.95rem;
    }
}
</style>
