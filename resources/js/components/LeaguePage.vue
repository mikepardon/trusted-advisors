<template>
    <TaSubPage
        title="League"
        :subtitle="pageSubtitle"
        :stat="rankStat"
        back="/"
    >
        <p
            v-if="league.state.loading && !league.state.loaded"
            class="league-loading"
        >
            Loading…
        </p>

        <template v-else-if="standings">
            <!-- Shield-crest header card -->
            <div class="crest-header">
                <div
                    class="crest"
                    :style="{ '--crest-color': standings.tier_color }"
                >
                    <span class="crest-inner"></span>
                    <span class="crest-star">&#9733;</span>
                </div>
                <div class="crest-info">
                    <div class="crest-name">
                        {{ standings.tier_name }} League
                    </div>
                    <div v-if="endsIn" class="crest-ends">
                        Resets in {{ endsIn }}
                    </div>
                    <div class="crest-legend">
                        Top {{ standings.promote_count }} promote · Bottom
                        {{ standings.demote_count }} demote
                    </div>
                </div>
            </div>

            <!-- Ladder -->
            <div class="ladder">
                <template v-for="(row, index) in standings.rows" :key="index">
                    <div
                        v-if="shouldShowPromotionZone(row)"
                        class="zone zone--promote"
                    >
                        <span class="zone-line zone-line--left"></span>
                        <span class="zone-label"
                            >&#9650; PROMOTION ZONE &#9650;</span
                        >
                        <span class="zone-line zone-line--right"></span>
                    </div>
                    <div
                        v-if="shouldShowDemotionZone(row)"
                        class="zone zone--demote"
                    >
                        <span class="zone-line zone-line--left"></span>
                        <span class="zone-label"
                            >&#9660; DEMOTION ZONE &#9660;</span
                        >
                        <span class="zone-line zone-line--right"></span>
                    </div>

                    <div
                        class="ta-row ladder-row"
                        :class="{
                            'ta-row--me': row.is_you,
                            promoting: isPromoting(row),
                            demoting: isDemoting(row),
                        }"
                    >
                        <span class="row-rank">{{ row.rank }}</span>
                        <div class="row-crest">
                            <LeagueCrest
                                :variant="row.crest_style"
                                :tier="row.crest_colour"
                            />
                        </div>
                        <span class="row-name">{{
                            row.is_you ? "You" : row.name || "Advisor"
                        }}</span>
                        <span class="row-score">{{ row.score }} pts</span>
                    </div>
                </template>
            </div>
        </template>
    </TaSubPage>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { DateTime } from "luxon";
import TaSubPage from "./TaSubPage.vue";
import LeagueCrest from "./LeagueCrest.vue";
import { useLeague } from "../stores/league";
import type { LeagueRow } from "../stores/league";

const league = useLeague();

const nowMs = ref(0);
const timer = ref<ReturnType<typeof setInterval>>();

const standings = computed(() => league.state.standings);

// The cohort week runs Monday→Monday; reset is the Monday after week_start.
const endsIn = computed(() => {
    const weekStart = standings.value?.week_start;
    if (weekStart === null || weekStart === undefined) {
        return "";
    }
    const resetAt = DateTime.fromISO(weekStart).plus({ days: 7 });
    const diff = resetAt.toMillis() - nowMs.value;
    if (diff <= 0) {
        return "";
    }
    const totalMinutes = Math.floor(diff / 60_000);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    if (days > 0) {
        return `${days}d ${hours}h`;
    }
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes}m`;
});

// Header subtitle: tier name plus the live reset countdown when available.
const pageSubtitle = computed(() => {
    const data = standings.value;
    if (data === undefined) {
        return "";
    }
    if (endsIn.value) {
        return `${data.tier_name} League · Resets in ${endsIn.value}`;
    }
    return `${data.tier_name} League`;
});

// Right-hand context pill: the player's current rank, when known.
const rankStat = computed(() => {
    const yourRank = standings.value?.your_rank;
    if (yourRank === null || yourRank === undefined) {
        return "";
    }
    return `RANK ${yourRank}`;
});

function isPromoting(row: LeagueRow): boolean {
    const data = standings.value;
    if (data === undefined || data.is_top_tier) {
        return false;
    }
    return row.rank <= data.promote_count;
}

function isDemoting(row: LeagueRow): boolean {
    const data = standings.value;
    if (data === undefined || data.is_bottom_tier) {
        return false;
    }
    return row.rank > data.total - data.demote_count;
}

function shouldShowPromotionZone(row: LeagueRow): boolean {
    const data = standings.value;
    if (data === undefined || data.is_top_tier) {
        return false;
    }
    // Divider sits just below the last promoting row.
    return (
        row.rank === data.promote_count + 1 && data.total > data.promote_count
    );
}

function shouldShowDemotionZone(row: LeagueRow): boolean {
    const data = standings.value;
    if (data === undefined || data.is_bottom_tier) {
        return false;
    }
    const firstDemotionRank = data.total - data.demote_count + 1;
    // Only show when it sits below the promotion boundary to avoid overlap.
    return (
        row.rank === firstDemotionRank &&
        firstDemotionRank > data.promote_count + 1
    );
}

function tick(): void {
    nowMs.value = DateTime.now().toMillis();
}

onMounted(() => {
    void league.fetchStandings();
    tick();
    timer.value = setInterval(tick, 1000);
});

onBeforeUnmount(() => {
    if (timer.value !== undefined) {
        clearInterval(timer.value);
    }
});
</script>

<style scoped>
.league-loading {
    color: var(--ta-mute);
    text-align: center;
    padding: 24px 0;
}

/* ---------------------------------------------------------------- Crest header */
.crest-header {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px;
    border-radius: 16px;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.9),
        rgba(16, 11, 6, 0.95)
    );
    border: 1px solid rgba(240, 192, 80, 0.4);
}

.crest {
    position: relative;
    width: 62px;
    height: 70px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, #7a6242, #3a2c18);
    clip-path: polygon(0 0, 100% 0, 100% 62%, 50% 100%, 0 62%);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.6);
}

/* Inner shield face; tinted by the tier's colour when supplied. */
.crest-inner {
    position: absolute;
    inset: 3px;
    background: linear-gradient(180deg, var(--crest-color, #c8a468), #4a3720);
    clip-path: polygon(0 0, 100% 0, 100% 62%, 50% 100%, 0 62%);
}

.crest-star {
    position: relative;
    font-size: 24px;
    color: #fff8e0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
    margin-bottom: 8px;
}

.crest-info {
    flex: 1;
    min-width: 0;
}

.crest-name {
    font-family: "Cinzel", serif;
    font-size: 19px;
    font-weight: 800;
    letter-spacing: 0.4px;
    color: var(--ta-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.crest-ends {
    margin-top: 3px;
    font-family: "Cinzel", serif;
    font-size: 12px;
    font-weight: 700;
    color: var(--ta-gold);
}

.crest-legend {
    margin-top: 2px;
    font-size: 12px;
    color: var(--ta-mute);
}

/* ---------------------------------------------------------------- Ladder */
.ladder {
    margin-top: 13px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Zone divider: gradient rule + centred Cinzel label. */
.zone {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 5px 0;
}

.zone-line {
    flex: 1;
    height: 1px;
}

.zone--promote .zone-line--left {
    background: linear-gradient(90deg, transparent, var(--ta-good));
}

.zone--promote .zone-line--right {
    background: linear-gradient(90deg, var(--ta-good), transparent);
}

.zone--demote .zone-line--left {
    background: linear-gradient(90deg, transparent, var(--ta-bad));
}

.zone--demote .zone-line--right {
    background: linear-gradient(90deg, var(--ta-bad), transparent);
}

.zone-label {
    font-family: "Cinzel", serif;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.8px;
    white-space: nowrap;
}

.zone--promote .zone-label {
    color: var(--ta-good);
}

.zone--demote .zone-label {
    color: var(--ta-bad);
}

.row-crest {
    width: 26px;
    height: 32px;
    flex: none;
}

/* Ladder rows lean on the shared .ta-row / .ta-row--me chrome. */
.ladder-row {
    gap: 10px;
    padding: 9px 14px;
    background: linear-gradient(
        180deg,
        rgba(44, 33, 20, 0.9),
        rgba(22, 16, 9, 0.92)
    );
    border-color: rgba(240, 192, 80, 0.14);
}

/* .ta-row--me already applies the gold wash + gold border. */

.row-rank {
    width: 26px;
    flex: none;
    text-align: center;
    font-family: "Cinzel", serif;
    font-size: 16px;
    font-weight: 800;
    color: var(--ta-gold);
}

.ladder-row.promoting .row-rank {
    color: var(--ta-good);
}

.ladder-row.demoting .row-rank {
    color: var(--ta-bad);
}

.row-name {
    flex: 1;
    min-width: 0;
    font-family: "Cinzel", serif;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.3px;
    color: var(--ta-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ta-row--me .row-name {
    color: var(--ta-gold);
}

.row-score {
    flex: none;
    font-size: 13px;
    color: var(--ta-mute);
    white-space: nowrap;
}

.ta-row--me .row-score {
    color: #f0dcb0;
}
</style>
