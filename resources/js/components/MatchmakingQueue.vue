<template>
    <div class="card-panel matchmaking-panel">
        <div class="mm-header">
            <button class="ta-back" aria-label="Back" @click="back">
                &lsaquo;
            </button>
            <div class="mm-heading">
                <div class="mm-title">Duel</div>
                <div class="mm-sub">Two advisors, rival kingdoms</div>
            </div>
            <span class="mm-players-pill">2 Players</span>
        </div>

        <!-- You vs opponent -->
        <div class="vs-banner">
            <div class="vs-side">
                <div class="vs-portrait you"></div>
                <div class="vs-name you-name">{{ youName }}</div>
                <div class="vs-detail">Lv {{ youLevel }}{{ rankSuffix }}</div>
            </div>
            <div class="vs-center">
                <span class="vs-word">VS</span>
                <span class="vs-months">12 Months</span>
            </div>
            <div class="vs-side">
                <div
                    class="vs-portrait opp"
                    :class="{ found: phase === 'matched' }"
                >
                    <span v-if="phase !== 'matched'">?</span>
                </div>
                <div class="vs-name opp-name">{{ opponentTitle }}</div>
                <div class="vs-detail">{{ opponentDetail }}</div>
            </div>
        </div>

        <!-- Setup: choose turn length, see stakes, find an opponent -->
        <template v-if="phase === 'setup'">
            <div class="mm-divider">
                <span class="mm-divider-label">Turn Length</span
                ><span class="mm-divider-line"></span>
            </div>
            <div class="turn-rows">
                <div
                    v-for="opt in speedOptions"
                    :key="opt.mode"
                    class="turn-row"
                    :class="{ selected: speedMode === opt.mode }"
                    @click="selectSpeed(opt.mode)"
                >
                    <span class="turn-glyph">{{ opt.glyph }}</span>
                    <div class="turn-body">
                        <div class="turn-name">{{ opt.name }}</div>
                        <div class="turn-meta">{{ opt.meta }}</div>
                        <div class="turn-note">{{ opt.note }}</div>
                    </div>
                    <span v-if="speedMode === opt.mode" class="turn-pill"
                        >Selected</span
                    >
                </div>
            </div>

            <div class="mm-divider">
                <span class="mm-divider-label">At Stake</span
                ><span class="mm-divider-line"></span>
            </div>
            <div class="stake-tiles">
                <div class="stake-tile">
                    <div class="stake-label">League</div>
                    <div class="stake-value">+{{ WIN_POINTS }} pts</div>
                    <div class="stake-note">Earned on a win</div>
                </div>
                <div class="stake-tile">
                    <div class="stake-label">Rank</div>
                    <div class="stake-value">{{ tierName }}</div>
                    <div class="stake-note">{{ rankNote }}</div>
                </div>
            </div>

            <button class="find-btn" @click="startSearch">Find Opponent</button>
            <p class="find-hint">
                Matched with a rival near your rank &middot; usually seconds
            </p>
        </template>

        <!-- Searching (live speed duel) -->
        <template v-else-if="phase === 'searching'">
            <div class="search-spinner"></div>
            <p class="search-text">Searching for a worthy opponent…</p>
            <div class="elapsed-time">{{ formattedElapsed }}</div>
            <button class="btn-cancel" @click="cancelSearch">Cancel</button>
        </template>

        <!-- Queued (correspondence / daily duel) -->
        <template v-else-if="phase === 'queued'">
            <div class="queued-glyph">◷</div>
            <p class="search-text">You're in the queue for a daily duel.</p>
            <p class="queued-note">
                No need to wait around — we'll notify you the moment we find you a
                worthy opponent. Leave whenever you like.
            </p>
            <button class="find-btn queued-hub" @click="emit('cancelled')">
                Back to Hub
            </button>
            <button class="btn-cancel" @click="cancelSearch">Leave queue</button>
        </template>

        <!-- Matched -->
        <template v-else>
            <p class="match-text">Opponent found!</p>
            <p class="opponent-name">{{ opponentName }}</p>
        </template>
    </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useAuth } from "../stores/auth";
import { useLeague } from "../stores/league";

interface MatchFoundEvent {
    game_id: number;
    opponent_name?: string;
}

interface EchoChannel {
    listen: (event: string, callback: (data: MatchFoundEvent) => void) => void;
    stopListening: (event: string) => void;
}

interface EchoInstance {
    private: (channel: string) => EchoChannel;
}

interface MatchmakingEntry {
    status?: string;
    matched_game_id?: number;
    opponent_name?: string;
}

const { totalRounds } = defineProps<{ totalRounds: number }>();

const emit = defineEmits<{ matched: [gameId: number]; cancelled: [] }>();

// League points awarded for a duel win (see GameCompletionService).
const WIN_POINTS = 150;

const auth = useAuth();
const league = useLeague();

const phase = ref<"setup" | "searching" | "queued" | "matched">("setup");
const speedMode = ref<"speed" | "daily">("speed");
const entry = ref<MatchmakingEntry | undefined>(undefined);
const opponentName = ref("");
const elapsed = ref(0);
const elapsedTimer = ref<ReturnType<typeof setInterval> | undefined>(undefined);
const pollTimer = ref<ReturnType<typeof setInterval> | undefined>(undefined);

const speedOptions = [
    {
        mode: "speed" as const,
        glyph: "⚡",
        name: "Speed",
        meta: "45 seconds per turn",
        note: "One sitting, start to finish",
    },
    {
        mode: "daily" as const,
        glyph: "◷",
        name: "Daily",
        meta: "24 hours per turn",
        note: "Play a month whenever you like",
    },
];

const youName = computed(() => auth.state.user?.name ?? "You");
const youLevel = computed(() => auth.state.user?.level ?? 1);

const rankSuffix = computed(() => {
    const rank = league.state.standings?.your_rank;
    return rank ? ` · Rank ${rank}` : "";
});

const tierName = computed(
    () => league.state.standings?.tier_name ?? "Unranked",
);

const rankNote = computed(() => {
    const standings = league.state.standings;
    if (!standings || standings.your_rank === null) {
        return "This week's ladder";
    }
    return `Rank ${standings.your_rank} of ${standings.total}`;
});

const opponentTitle = computed(() => {
    if (phase.value === "matched") {
        return opponentName.value;
    }
    if (phase.value === "searching") {
        return "Searching…";
    }
    return phase.value === "queued" ? "Queued…" : "A rival";
});

const opponentDetail = computed(() => {
    if (phase.value === "searching") {
        return formattedElapsed.value;
    }
    if (phase.value === "queued") {
        return "We'll notify you";
    }
    return "Similar rank";
});

const formattedElapsed = computed(() => {
    const mins = Math.floor(elapsed.value / 60);
    const secs = elapsed.value % 60;
    return `${mins}:${secs.toString().padStart(2, "0")}`;
});

function selectSpeed(mode: "speed" | "daily"): void {
    speedMode.value = mode;
}

function getEcho(): EchoInstance | undefined {
    return (window as unknown as { Echo?: EchoInstance }).Echo;
}

async function pollStatus(): Promise<void> {
    try {
        const response = await axios.get<MatchmakingEntry>(
            "/api/matchmaking/status",
        );
        if (response.data.status === "matched") {
            onMatchFound(
                response.data.matched_game_id,
                response.data.opponent_name,
            );
        }
    } catch {
        // ignore poll errors
    }
}

async function leaveQueue(): Promise<void> {
    try {
        await axios.post("/api/matchmaking/leave");
    } catch {
        // ignore leave errors
    }
}

function subscribeEcho(): void {
    const echo = getEcho();
    if (!echo || !auth.state.user) {
        return;
    }
    echo.private(`user.${auth.state.user.id}`).listen("MatchFound", (data) => {
        onMatchFound(data.game_id, data.opponent_name);
    });
}

function unsubscribeEcho(): void {
    const echo = getEcho();
    if (echo && auth.state.user) {
        echo.private(`user.${auth.state.user.id}`).stopListening("MatchFound");
    }
}

function clearTimers(): void {
    clearInterval(elapsedTimer.value);
    clearInterval(pollTimer.value);
    elapsedTimer.value = undefined;
    pollTimer.value = undefined;
}

function onMatchFound(
    gameId: number | undefined,
    name: string | undefined,
): void {
    clearTimers();
    phase.value = "matched";
    opponentName.value = name || "Opponent";

    setTimeout(() => {
        if (gameId !== undefined) {
            emit("matched", gameId);
        }
    }, 1500);
}

async function startSearch(): Promise<void> {
    const isDaily = speedMode.value === "daily";
    // Correspondence duels queue in the background ("we'll notify you"); speed duels
    // search live in one sitting.
    phase.value = isDaily ? "queued" : "searching";
    elapsed.value = 0;

    try {
        const response = await axios.post<MatchmakingEntry>(
            "/api/matchmaking/join",
            {
                total_rounds: totalRounds,
                speed_mode: speedMode.value,
            },
        );
        entry.value = response.data;

        if (entry.value.status === "matched") {
            onMatchFound(
                entry.value.matched_game_id,
                entry.value.opponent_name,
            );
            return;
        }

        if (!isDaily) {
            elapsedTimer.value = setInterval(() => {
                elapsed.value++;
            }, 1000);
        }
        pollTimer.value = setInterval(() => void pollStatus(), isDaily ? 5000 : 3000);
        subscribeEcho();
    } catch {
        phase.value = "setup";
    }
}

function cancelSearch(): void {
    clearTimers();
    void leaveQueue();
    entry.value = undefined;
    phase.value = "setup";
}

function back(): void {
    // Live search cancels on leave; a queued correspondence search keeps running so we
    // can notify the player when they're matched.
    if (phase.value === "searching") {
        cancelSearch();
        return;
    }
    emit("cancelled");
}

onMounted(() => {
    if (!league.state.loaded) {
        void league.fetchStandings();
    }
});

onBeforeUnmount(() => {
    clearTimers();
    unsubscribeEcho();
    // Only cancel a live "speed" search on leave. A queued correspondence search stays in
    // the queue so the player can be matched and notified after they've left.
    if (entry.value && phase.value === "searching") {
        void leaveQueue();
    }
});
</script>

<style scoped>
.matchmaking-panel {
    padding: 16px 16px 24px;
}

.mm-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.mm-heading {
    flex: 1;
    min-width: 0;
}

.mm-title {
    font-family: "Cinzel", serif;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--accent-gold);
}

.mm-sub {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.mm-players-pill {
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    padding: 6px 11px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.35);
    color: var(--accent-gold);
}

/* VS banner */
.vs-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 12px;
    border-radius: 18px;
    background: linear-gradient(
        90deg,
        rgba(240, 192, 80, 0.16),
        rgba(18, 12, 7, 0.9) 48%,
        rgba(58, 138, 212, 0.18)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.4);
    box-shadow: 0 5px 0 rgba(0, 0, 0, 0.5);
}

.vs-side {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.vs-portrait {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background-image: url("/images/character.png");
    background-size: cover;
    background-position: center 18%;
}

.vs-portrait.you {
    border: 2.5px solid #f0c050;
}

.vs-portrait.opp {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
    border: 2.5px dashed rgba(154, 208, 255, 0.6);
    font-size: 1.4rem;
    color: #9ad0ff;
}

.vs-portrait.opp.found {
    background-image: url("/images/character.png");
    background-size: cover;
    background-position: center 18%;
    border-style: solid;
}

.vs-name {
    font-family: "Cinzel", serif;
    font-size: 0.82rem;
    font-weight: 800;
    text-align: center;
}

.you-name {
    color: #f0c050;
}
.opp-name {
    color: #9ad0ff;
}

.vs-detail {
    font-size: 0.7rem;
    color: #8a7a5a;
}

.vs-center {
    flex: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}

.vs-word {
    font-family: "Cinzel", serif;
    font-size: 1.2rem;
    font-weight: 800;
    letter-spacing: 1px;
    color: #f0e0c8;
}

.vs-months {
    font-family: "Cinzel", serif;
    font-size: 0.52rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: #8a7a5a;
}

/* Section dividers */
.mm-divider {
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mm-divider-label {
    font-family: "Cinzel", serif;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 2.2px;
    text-transform: uppercase;
    color: var(--accent-gold);
}

.mm-divider-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(240, 192, 80, 0.35), transparent);
}

/* Turn length rows */
.turn-rows {
    margin-top: 9px;
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.turn-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 15px;
    background: rgba(0, 0, 0, 0.3);
    border: 1.5px solid rgba(240, 192, 80, 0.18);
    cursor: pointer;
    transition:
        border-color 0.2s,
        background 0.2s;
}

.turn-row.selected {
    background: rgba(240, 192, 80, 0.1);
    border-color: rgba(240, 192, 80, 0.55);
}

.turn-glyph {
    width: 40px;
    height: 40px;
    flex: none;
    border-radius: 13px;
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid rgba(240, 192, 80, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--accent-gold);
}

.turn-body {
    flex: 1;
    min-width: 0;
}

.turn-name {
    font-family: "Cinzel", serif;
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-bright, #f0e0c8);
}

.turn-meta {
    font-size: 0.75rem;
    color: #bcac8c;
}

.turn-note {
    font-size: 0.72rem;
    color: #8a7a5a;
}

.turn-pill {
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    background: linear-gradient(180deg, #ffe897, #f0c050 55%, #b8842a);
    color: #241703;
    border-radius: 9px;
    padding: 5px 9px;
}

/* Stake tiles */
.stake-tiles {
    margin-top: 9px;
    display: flex;
    gap: 8px;
}

.stake-tile {
    flex: 1;
    min-width: 0;
    padding: 11px 9px;
    border-radius: 14px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 80, 0.2);
    text-align: center;
}

.stake-label {
    font-family: "Cinzel", serif;
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: #8a7a5a;
}

.stake-value {
    margin-top: 5px;
    font-family: "Cinzel", serif;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--accent-gold);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stake-note {
    margin-top: 3px;
    font-size: 0.66rem;
    line-height: 1.3;
    color: #8a7a5a;
}

/* Find opponent */
.find-btn {
    margin-top: 16px;
    width: 100%;
    padding: 16px;
    border: 2px solid #fff0b0;
    border-radius: 16px;
    background: linear-gradient(180deg, #ffe897, #f0c050 55%, #b8842a);
    font-family: "Cinzel", serif;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: #241703;
    cursor: pointer;
    transition: transform 0.1s;
}

.find-btn:active {
    transform: translateY(2px);
}

.find-hint {
    margin-top: 9px;
    text-align: center;
    font-size: 0.72rem;
    color: #8a7a5a;
}

/* Searching */
.search-spinner {
    width: 60px;
    height: 60px;
    border: 3px solid rgba(138, 106, 46, 0.2);
    border-top: 3px solid var(--accent-gold);
    border-radius: 50%;
    margin: 26px auto 16px;
    animation: spin 1.2s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.search-text {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    font-size: 1rem;
}

.elapsed-time {
    margin-top: 12px;
    text-align: center;
    font-family: "Cinzel", serif;
    font-size: 2rem;
    color: var(--text-bright);
}

.btn-cancel {
    display: block;
    margin: 22px auto 0;
    background: rgba(160, 48, 32, 0.2);
    color: #d05040;
    border: 1px solid rgba(160, 48, 32, 0.4);
    padding: 10px 30px;
    font-size: 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: rgba(160, 48, 32, 0.35);
    border-color: rgba(160, 48, 32, 0.6);
}

/* Queued (correspondence) */
.queued-glyph {
    text-align: center;
    font-size: 3rem;
    color: var(--accent-gold);
    margin: 20px 0 8px;
}

.queued-note {
    margin: 8px auto 4px;
    max-width: 320px;
    text-align: center;
    font-size: 0.8rem;
    line-height: 1.5;
    color: var(--text-secondary);
}

.queued-hub {
    margin-top: 18px;
}

/* Matched */
.match-text {
    margin-top: 26px;
    text-align: center;
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.4rem;
}

.opponent-name {
    text-align: center;
    color: var(--text-bright);
    font-size: 1.2rem;
    font-weight: 600;
}
</style>
