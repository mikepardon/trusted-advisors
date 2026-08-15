<template>
    <svg
        viewBox="0 0 100 120"
        class="league-crest"
        role="img"
        :aria-label="`${tierName} league crest`"
    >
        <defs>
            <clipPath :id="clipId">
                <path :d="shield" />
            </clipPath>
            <linearGradient :id="fieldId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" :stop-color="pal.fieldTop" />
                <stop offset="1" :stop-color="pal.field" />
            </linearGradient>
            <linearGradient :id="metalId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" :stop-color="pal.metalHi" />
                <stop offset="1" :stop-color="pal.metal" />
            </linearGradient>
        </defs>

        <!-- Field -->
        <path :d="shield" :fill="`url(#${fieldId})`" />

        <!-- Charges + ordinaries, clipped to the shield outline -->
        <g :clip-path="`url(#${clipId})`" :fill="`url(#${metalId})`">
            <!-- 1 · Sovereign: embattled chief + crown line + single mullet -->
            <template v-if="variant === 1">
                <path
                    d="M14,13 H86 V29 H78 V25 H70 V29 H62 V25 H54 V29 H46 V25 H38 V29 H30 V25 H22 V29 H14 Z"
                />
                <path :d="star" transform="translate(50,60) scale(1.7)" />
                <circle cx="50" cy="84" r="2.4" />
            </template>

            <!-- 2 · Quartered: gold cross, two mullets above, two die faces below -->
            <template v-else-if="variant === 2">
                <rect x="46" y="13" width="8" height="102" />
                <rect x="14" y="58" width="72" height="8" />
                <path :d="star" transform="translate(31,40) scale(1.05)" />
                <path :d="star" transform="translate(69,40) scale(1.05)" />
                <g
                    :fill="pal.field"
                    :stroke="`url(#${metalId})`"
                    stroke-width="2"
                >
                    <rect x="23" y="78" width="16" height="16" rx="3" />
                    <rect x="61" y="78" width="16" height="16" rx="3" />
                </g>
                <g :fill="`url(#${metalId})`">
                    <circle cx="27.5" cy="82.5" r="1.7" />
                    <circle cx="31" cy="86" r="1.7" />
                    <circle cx="34.5" cy="89.5" r="1.7" />
                    <circle cx="65.5" cy="82.5" r="1.7" />
                    <circle cx="72.5" cy="89.5" r="1.7" />
                </g>
            </template>

            <!-- 3 · Chevron: bold chevron, mullet above, two below -->
            <template v-else-if="variant === 3">
                <path d="M18,76 L50,50 L82,76 L82,85 L50,59 L18,85 Z" />
                <path :d="star" transform="translate(50,34) scale(1.2)" />
                <path
                    :d="star"
                    transform="translate(34,94) scale(0.95)"
                    :fill="pal.accent"
                />
                <path
                    :d="star"
                    transform="translate(66,94) scale(0.95)"
                    :fill="pal.accent"
                />
            </template>

            <!-- 4 · Council: domed chief + crossed swords over a boss -->
            <template v-else-if="variant === 4">
                <path d="M14,13 H86 V25 Q50,39 14,25 Z" />
                <circle cx="50" cy="70" r="17" />
                <circle
                    cx="50"
                    cy="70"
                    r="17"
                    fill="none"
                    :stroke="pal.field"
                    stroke-width="2"
                />
                <g
                    :stroke="pal.field"
                    stroke-width="3.4"
                    stroke-linecap="round"
                >
                    <line x1="39" y1="59" x2="61" y2="81" />
                    <line x1="61" y1="59" x2="39" y2="81" />
                </g>
                <circle cx="50" cy="70" r="3" :fill="pal.field" />
            </template>

            <!-- 5 · Pennon: three pales + a die face on the centre boss -->
            <template v-else-if="variant === 5">
                <rect x="25" y="13" width="7" height="102" />
                <rect x="46.5" y="13" width="7" height="102" />
                <rect x="68" y="13" width="7" height="102" />
                <circle cx="50" cy="50" r="15" :fill="pal.field" />
                <g
                    :fill="pal.field"
                    :stroke="`url(#${metalId})`"
                    stroke-width="2"
                >
                    <rect x="42" y="42" width="16" height="16" rx="3" />
                </g>
                <g>
                    <circle cx="46" cy="46" r="1.8" />
                    <circle cx="54" cy="46" r="1.8" />
                    <circle cx="46" cy="54" r="1.8" />
                    <circle cx="54" cy="54" r="1.8" />
                </g>
            </template>

            <!-- 6 · Crossed Swords: two blades in an X, crossguards + pommels -->
            <template v-else-if="variant === 6">
                <g stroke-linejoin="round">
                    <!-- blade running top-left to bottom-right -->
                    <path d="M27,40 L33,34 L64,71 L64,79 L56,79 L27,44 Z" />
                    <!-- blade running top-right to bottom-left -->
                    <path d="M73,40 L67,34 L36,71 L36,79 L44,79 L73,44 Z" />
                </g>
                <!-- crossguards -->
                <rect
                    x="30"
                    y="44"
                    width="16"
                    height="4"
                    rx="1.5"
                    transform="rotate(45 38 46)"
                />
                <rect
                    x="54"
                    y="44"
                    width="16"
                    height="4"
                    rx="1.5"
                    transform="rotate(-45 62 46)"
                />
                <!-- pommels -->
                <circle cx="30" cy="37" r="3.4" />
                <circle cx="70" cy="37" r="3.4" />
            </template>

            <!-- 7 · Crown: banded crown with five jewelled points -->
            <template v-else-if="variant === 7">
                <path
                    d="M26,52 L26,74 L74,74 L74,52 L64,62 L57,48 L50,60 L43,48 L36,62 Z"
                    stroke-linejoin="round"
                />
                <rect x="26" y="76" width="48" height="8" rx="2" />
                <circle cx="26" cy="50" r="3" />
                <circle cx="50" cy="46" r="3" />
                <circle cx="74" cy="50" r="3" />
                <circle cx="38" cy="80" r="1.8" :fill="pal.field" />
                <circle cx="50" cy="80" r="1.8" :fill="pal.field" />
                <circle cx="62" cy="80" r="1.8" :fill="pal.field" />
            </template>

            <!-- 8 · Tower: crenellated keep with door and window -->
            <template v-else-if="variant === 8">
                <path
                    d="M30,44 H36 V49 H44 V44 H50 V49 H58 V44 H64 V49 H70 V88 H30 Z"
                    stroke-linejoin="round"
                />
                <!-- window -->
                <rect
                    x="45"
                    y="56"
                    width="10"
                    height="12"
                    rx="5"
                    :fill="pal.field"
                />
                <!-- door -->
                <path d="M42,88 V78 Q50,70 58,78 V88 Z" :fill="pal.field" />
            </template>

            <!-- 9 · Fleur-de-lis -->
            <template v-else-if="variant === 9">
                <path
                    d="M50,38
                       C47,44 47,49 50,53
                       C46,48 40,49 40,57
                       C40,63 46,65 50,60
                       C48,66 44,68 40,68
                       C36,68 34,64 35,60
                       C31,64 33,73 41,75
                       L34,75 L34,80 L66,80 L66,75 L59,75
                       C67,73 69,64 65,60
                       C66,64 64,68 60,68
                       C56,68 52,66 50,60
                       C54,65 60,63 60,57
                       C60,49 54,48 50,53
                       C53,49 53,44 50,38 Z"
                    stroke-linejoin="round"
                />
                <rect x="38" y="66" width="24" height="5" rx="2" />
            </template>

            <!-- 10 · Saltire: bold diagonal X cross + central mullet -->
            <template v-else-if="variant === 10">
                <path
                    d="M18,20 L30,20 L82,90 L82,102 L70,102 L18,32 Z"
                    stroke-linejoin="round"
                />
                <path
                    d="M82,20 L70,20 L18,90 L18,102 L30,102 L82,32 Z"
                    stroke-linejoin="round"
                />
                <path
                    :d="star"
                    transform="translate(50,60) scale(1.2)"
                    :fill="pal.field"
                />
            </template>

            <!-- 11 · Sunburst: central disc with twelve radiating rays -->
            <template v-else-if="variant === 11">
                <g stroke-linejoin="round">
                    <path
                        v-for="ray in 12"
                        :key="ray"
                        d="M50,26 L46,44 L54,44 Z"
                        :transform="`rotate(${(ray - 1) * 30} 50 64)`"
                    />
                </g>
                <circle cx="50" cy="64" r="17" />
                <circle
                    cx="50"
                    cy="64"
                    r="17"
                    fill="none"
                    :stroke="pal.field"
                    stroke-width="2"
                />
            </template>

            <!-- 12 · Anchor: ring, shank, stock, curved flukes -->
            <template v-else-if="variant === 12">
                <circle
                    cx="50"
                    cy="40"
                    r="7"
                    fill="none"
                    :stroke="`url(#${metalId})`"
                    stroke-width="4"
                />
                <rect x="47" y="44" width="6" height="42" rx="2" />
                <rect x="36" y="52" width="28" height="5" rx="2.5" />
                <path
                    d="M50,86
                       C36,86 28,76 28,64
                       L34,64
                       C34,72 40,79 50,79
                       C60,79 66,72 66,64
                       L72,64
                       C72,76 64,86 50,86 Z"
                    stroke-linejoin="round"
                />
            </template>

            <!-- 13 · Lion: simplified maned head facing forward -->
            <template v-else-if="variant === 13">
                <!-- mane: ring of points -->
                <g stroke-linejoin="round">
                    <path
                        v-for="point in 12"
                        :key="point"
                        d="M50,30 L45,44 L55,44 Z"
                        :transform="`rotate(${(point - 1) * 30} 50 62)`"
                    />
                </g>
                <!-- face -->
                <circle cx="50" cy="62" r="17" />
                <!-- ears -->
                <circle cx="37" cy="52" r="4" />
                <circle cx="63" cy="52" r="4" />
                <!-- eyes -->
                <circle cx="43" cy="59" r="2.4" :fill="pal.field" />
                <circle cx="57" cy="59" r="2.4" :fill="pal.field" />
                <!-- nose + muzzle -->
                <path d="M46,68 L54,68 L50,73 Z" :fill="pal.field" />
                <path
                    d="M50,73 Q44,76 42,72 M50,73 Q56,76 58,72"
                    fill="none"
                    :stroke="pal.field"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />
            </template>

            <!-- 14 · Dragon: S-curved serpentine body with head + wing -->
            <template v-else-if="variant === 14">
                <path
                    d="M40,36
                       C54,34 58,44 52,52
                       C46,60 52,68 60,66
                       C54,74 44,74 40,66
                       C36,58 42,50 48,48
                       C52,46 52,40 46,40
                       C48,44 44,46 42,44
                       C40,42 40,38 40,36 Z"
                    stroke-linejoin="round"
                />
                <!-- head -->
                <path
                    d="M38,34 L48,36 L44,42 L36,40 Z"
                    stroke-linejoin="round"
                />
                <circle cx="42" cy="37" r="1.6" :fill="pal.field" />
                <!-- wing suggestion -->
                <path
                    d="M56,50 C68,44 74,50 72,62 C66,56 62,56 58,60 Z"
                    stroke-linejoin="round"
                />
                <!-- tail -->
                <path
                    d="M40,66 L34,78 L40,76 L42,82 L46,72 Z"
                    stroke-linejoin="round"
                />
            </template>

            <!-- 15 · Eagle: displayed spread-wing silhouette -->
            <template v-else-if="variant === 15">
                <!-- head -->
                <circle cx="50" cy="40" r="6" />
                <path d="M50,40 L58,42 L50,46 Z" />
                <!-- body -->
                <path
                    d="M45,44 L55,44 L53,74 L47,74 Z"
                    stroke-linejoin="round"
                />
                <!-- wings -->
                <path
                    d="M46,48
                       C34,42 22,42 16,50
                       C24,50 28,54 26,60
                       C34,54 40,56 44,62
                       C42,54 44,50 46,48 Z"
                    stroke-linejoin="round"
                />
                <path
                    d="M54,48
                       C66,42 78,42 84,50
                       C76,50 72,54 74,60
                       C66,54 60,56 56,62
                       C58,54 56,50 54,48 Z"
                    stroke-linejoin="round"
                />
                <!-- tail feathers -->
                <path
                    d="M44,74 L56,74 L54,88 L50,82 L46,88 Z"
                    stroke-linejoin="round"
                />
            </template>

            <!-- Fallback: Sovereign chief for unknown variants -->
            <template v-else>
                <path
                    d="M14,13 H86 V29 H78 V25 H70 V29 H62 V25 H54 V29 H46 V25 H38 V29 H30 V25 H22 V29 H14 Z"
                />
                <path :d="star" transform="translate(50,60) scale(1.7)" />
                <circle cx="50" cy="84" r="2.4" />
            </template>
        </g>

        <!-- Shield outline on top -->
        <path
            :d="shield"
            fill="none"
            :stroke="`url(#${metalId})`"
            stroke-width="3.5"
            stroke-linejoin="round"
        />
    </svg>
</template>

<script setup lang="ts">
import { computed } from "vue";

interface TierPalette {
    name: string;
    metal: string;
    metalHi: string;
    field: string;
    fieldTop: string;
    accent: string;
}

const { variant = 1, tier = 0 } = defineProps<{
    /**
  Crest style 1–15: Sovereign, Quartered, Chevron, Council, Pennon, Crossed
  Swords, Crown, Tower, Fleur-de-lis, Saltire, Sunburst, Anchor, Lion, Dragon,
  Eagle.
  */
    variant?: number;
    /**
  Colour palette 0–4: Wooden, Bronze, Silver, Gold, Obsidian.
  */
    tier?: number;
}>();

// Tier palettes — the metal recolours the whole badge (Image #32's tier row).
const TIERS: TierPalette[] = [
    {
        name: "Wooden",
        metal: "#9a7a4e",
        metalHi: "#c2a06e",
        field: "#241a10",
        fieldTop: "#3a2a18",
        accent: "#c8a878",
    },
    {
        name: "Bronze",
        metal: "#c07a4a",
        metalHi: "#e6a878",
        field: "#26160e",
        fieldTop: "#3c2416",
        accent: "#e6b088",
    },
    {
        name: "Silver",
        metal: "#c2ccd8",
        metalHi: "#ffffff",
        field: "#1a222e",
        fieldTop: "#2a3644",
        accent: "#ffffff",
    },
    {
        name: "Gold",
        metal: "#e8b84a",
        metalHi: "#ffe897",
        field: "#281d0b",
        fieldTop: "#3e2d12",
        accent: "#ffe897",
    },
    {
        name: "Obsidian",
        metal: "#a892d8",
        metalHi: "#e0d4fb",
        field: "#150f20",
        fieldTop: "#241a36",
        accent: "#e0d4fb",
    },
];

const pal = computed(() => TIERS[Math.min(Math.max(tier, 0), 4)] ?? TIERS[0]);
const tierName = computed(() => pal.value.name);

// Pennon (style 5) uses a pointed banner shield; the rest use a heater.
const shield = computed(() =>
    variant === 5
        ? "M18,13 H82 V64 L50,115 L18,64 Z"
        : "M14,13 H86 V53 C86,88 60,108 50,115 C40,108 14,88 14,53 Z",
);

// A five-pointed mullet (star) centred on the origin, scaled/placed via transform.
const star =
    "M0,-10 L2.9,-4.05 L9.51,-3.09 L4.76,1.55 L5.88,8.09 L0,4.5 L-5.88,8.09 L-4.76,1.55 L-9.51,-3.09 L-2.9,-4.05 Z";

// Unique gradient/clip ids so multiple crests can coexist on one page.
crestUid += 1;
const clipId = `lc-clip-${crestUid}`;
const fieldId = `lc-field-${crestUid}`;
const metalId = `lc-metal-${crestUid}`;
</script>

<script lang="ts">
let crestUid = 0;
</script>

<style scoped>
.league-crest {
    display: block;
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
}
</style>
