<template>
    <!-- Single root element: a multi-root component breaks the router's
         <transition mode="out-in">, leaving the next page (home) blank. -->
    <div class="game-over-root">
        <VictoryEffect ref="confetti" />
    <div v-if="loading" class="loading">Loading final results...</div>
    <div v-else-if="gameData" class="game-over">
        <!-- DUEL END SCREEN -->
        <template v-if="isDuel">
            <div class="card-panel result-panel" :class="resultAnimationClass">
                <h2
                    class="game-over-title"
                    :class="isDuelWinner ? 'title-win' : 'title-loss'"
                >
                    {{ duelEndTitle }}
                </h2>
                <p class="end-flavor">{{ duelEndFlavor }}</p>

                <div class="duel-kingdoms-final">
                    <div
                        v-for="kingdom in duelKingdoms"
                        :key="kingdom.id"
                        class="duel-kingdom-panel"
                        :class="{
                            'kingdom-winner':
                                kingdom.player?.player_number ===
                                gameData.game.winner_player_number,
                        }"
                    >
                        <h3 class="kingdom-header">
                            <span
                                :class="{
                                    'clickable-name': kingdom.player?.user_id,
                                }"
                                @click="
                                    kingdom.player?.user_id &&
                                    (showProfileUserId = kingdom.player.user_id)
                                "
                                >{{ playerDisplayName(kingdom.player) }}</span
                            >
                            <span v-if="isBothTimeout" class="draw-badge"
                                >DRAW</span
                            >
                            <span
                                v-else-if="
                                    kingdom.player?.player_number ===
                                    gameData.game.winner_player_number
                                "
                                class="winner-badge"
                                >WINNER</span
                            >
                            <span
                                v-else-if="
                                    isTimeout &&
                                    kingdom.player?.player_number ===
                                        timedOutPlayerNumber
                                "
                                class="timeout-badge"
                                >TIMED OUT</span
                            >
                        </h3>
                        <div class="kingdom-sub">
                            <span class="kingdom-character">{{
                                kingdom.player?.character?.name
                            }}</span>
                        </div>
                        <div class="stats-grid">
                            <div
                                v-for="stat in stats"
                                :key="stat.key"
                                class="final-stat"
                            >
                                <span class="stat-icon"
                                    ><AppIcon
                                        :type="stat.type"
                                        :value="stat.value"
                                /></span>
                                <span class="stat-label">{{ stat.label }}</span>
                                <span
                                    class="stat-val"
                                    :class="getValueClass(kingdom[stat.key])"
                                >
                                    {{ kingdom[stat.key] }}
                                </span>
                            </div>
                        </div>
                        <div class="kingdom-score">
                            Score: <strong>{{ kingdomTotal(kingdom) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="round-summary">
                    <p class="rounds-survived">
                        Months Played:
                        <strong>{{ gameData.game.current_round }}</strong> /
                        {{ gameData.game.total_rounds }}
                    </p>
                </div>

                <!-- Completion rewards -->
                <div v-if="completion" class="completion-rewards">
                    <div v-if="myXp" class="reward-item reward-xp">
                        +{{ myXp }} XP
                    </div>
                    <div v-if="myCoins" class="reward-item reward-coins">
                        +{{ myCoins }} Coins
                    </div>
                    <div v-if="myPassPoints" class="reward-item reward-pass">
                        +{{ myPassPoints }} Season Pass &amp; League
                    </div>
                    <div v-if="myLevelUp" class="reward-item reward-level">
                        Level Up! Lv.{{ myLevelUp }}
                    </div>
                    <div
                        v-for="ach in myAchievements"
                        :key="ach.id"
                        class="reward-item reward-achievement"
                    >
                        {{ ach.name }}
                    </div>
                    <div
                        v-if="completion.challenge_completed"
                        class="reward-item reward-challenge"
                    >
                        Challenge Complete:
                        {{ completion.challenge_completed.title }} (+{{
                            completion.challenge_completed.reward_xp
                        }}
                        XP)
                    </div>
                </div>

                <MysteryChest
                    v-if="myBonusChest"
                    :tier="myBonusChest.tier"
                    :coins="myBonusChest.coins"
                    @open="onChestOpen"
                />

                <!-- XP Progress Bar -->
                <div v-if="myXpDetails" class="xp-progress-section">
                    <div class="xp-progress-header">
                        <span class="xp-level-label"
                            >Level {{ xpBarLevel }}</span
                        >
                        <span class="xp-amount">+{{ myXp }} XP</span>
                    </div>
                    <div class="xp-progress-track">
                        <div
                            class="xp-progress-fill"
                            :style="{ width: xpBarPercent + '%' }"
                        ></div>
                    </div>
                    <div class="xp-progress-footer">
                        <span
                            >{{ xpBarDisplayXp }} /
                            {{ xpForLevel(xpBarLevel + 1) }} XP</span
                        >
                    </div>
                    <transition name="levelup-pop">
                        <div v-if="showLevelUp" class="level-up-banner">
                            <span class="level-up-star">&#11088;</span>
                            <span class="level-up-text"
                                >Level {{ myXpDetails.new_level }}!</span
                            >
                            <span class="level-up-star">&#11088;</span>
                        </div>
                    </transition>
                    <div
                        v-if="myUnlocks.length > 0 && showLevelUp"
                        class="new-unlocks"
                    >
                        <div
                            v-for="unlock in myUnlocks"
                            :key="unlock.id"
                            class="unlock-item"
                        >
                            <span class="unlock-icon">&#127381;</span>
                            <span class="unlock-name">{{ unlock.name }}</span>
                            <button
                                class="btn-unlock-claim"
                                @click="router.push('/shop')"
                            >
                                View
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Character XP -->
                <div v-if="myCharXp" class="char-xp-section">
                    <div class="char-xp-row">
                        <span class="char-xp-name">{{
                            myCharXp.character_name
                        }}</span>
                        <span class="char-xp-earned"
                            >+{{ myCharXp.xp_earned }} Advisor XP</span
                        >
                        <span v-if="myCharXp.leveled_up" class="char-xp-lvlup"
                            >Lv.{{ myCharXp.new_level }}!</span
                        >
                    </div>
                    <div
                        v-if="myCharXp.pending_upgrades > 0"
                        class="char-xp-pending"
                    >
                        <button
                            class="btn-char-upgrade"
                            @click="router.push('/collection')"
                        >
                            Level Up Available!
                        </button>
                    </div>
                </div>

                <div class="button-row">
                    <button
                        class="btn-primary play-again"
                        @click="router.push('/')"
                    >
                        Home
                    </button>
                    <button class="play-again share-btn" @click="shareReplay">
                        {{ shareCopied ? "Copied!" : "Share Replay" }}
                    </button>
                </div>
            </div>
        </template>

        <!-- COOPERATIVE END SCREEN (single / pass_and_play / online) -->
        <template v-else>
            <div class="card-panel result-panel">
                <h2
                    class="game-over-title"
                    :class="isWin ? 'title-win' : 'title-loss'"
                >
                    {{ endTitle }}
                </h2>
                <p class="end-flavor">{{ endFlavor }}</p>

                <div class="final-stats">
                    <h3>Final Kingdom Status</h3>
                    <div class="stats-grid">
                        <div
                            v-for="stat in stats"
                            :key="stat.key"
                            class="final-stat"
                        >
                            <span class="stat-icon"
                                ><AppIcon :type="stat.type" :value="stat.value"
                            /></span>
                            <span class="stat-label">{{ stat.label }}</span>
                            <span
                                class="stat-val"
                                :class="getValueClass(gameData.game[stat.key])"
                            >
                                {{ gameData.game[stat.key] }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="total-score">
                    <h3>Final Score</h3>
                    <div class="score">{{ finalScore }}</div>
                    <p class="score-rank">{{ scoreRank }}</p>
                    <div v-if="scoreBreakdown" class="score-breakdown">
                        <div class="breakdown-row">
                            <span>Kingdom Total</span>
                            <span>{{ scoreBreakdown.base_score }}</span>
                        </div>
                        <div class="breakdown-row">
                            <span>Year Multiplier</span>
                            <span
                                >&times;{{
                                    scoreBreakdown.year_multiplier
                                }}</span
                            >
                        </div>
                        <div class="breakdown-row">
                            <span>Balance Bonus</span>
                            <span>+{{ scoreBreakdown.balance_bonus }}</span>
                        </div>
                        <div
                            v-if="scoreBreakdown.year_bonus"
                            class="breakdown-row"
                        >
                            <span>Year Bonus</span>
                            <span>+{{ scoreBreakdown.year_bonus }}</span>
                        </div>
                        <div
                            v-if="scoreBreakdown.stacking_bonus"
                            class="breakdown-row"
                        >
                            <span>Stacking Bonus</span>
                            <span>+{{ scoreBreakdown.stacking_bonus }}</span>
                        </div>
                        <div
                            v-if="scoreBreakdown.bonus_score"
                            class="breakdown-row"
                        >
                            <span>Bonus Score</span>
                            <span>+{{ scoreBreakdown.bonus_score }}</span>
                        </div>
                        <div
                            v-if="scoreBreakdown.score_modifier"
                            class="breakdown-row"
                        >
                            <span>Score Modifier</span>
                            <span
                                :class="
                                    scoreBreakdown.score_modifier > 0
                                        ? 'mod-positive'
                                        : 'mod-negative'
                                "
                                >{{
                                    scoreBreakdown.score_modifier > 0
                                        ? "+"
                                        : ""
                                }}{{ scoreBreakdown.score_modifier }}%</span
                            >
                        </div>
                        <div class="breakdown-row breakdown-total">
                            <span>Final Score</span>
                            <span>{{ scoreBreakdown.final_score }}</span>
                        </div>
                    </div>
                </div>

                <div class="round-summary">
                    <p class="rounds-survived">
                        Months Survived:
                        <strong>{{ gameData.game.current_round }}</strong> /
                        {{ gameData.game.total_rounds }}
                    </p>
                </div>

                <div class="advisors-section">
                    <h3>Your Advisors</h3>
                    <div
                        v-for="player in gameData.game.players"
                        :key="player.id"
                        class="advisor"
                    >
                        <strong
                            :class="{ 'clickable-name': player.user_id }"
                            @click="
                                player.user_id &&
                                (showProfileUserId = player.user_id)
                            "
                            >{{ player.character.name }}</strong
                        >
                        <div
                            v-if="player.items && player.items.length > 0"
                            class="advisor-items"
                        >
                            <span
                                v-for="pi in player.items"
                                :key="pi.id"
                                class="advisor-item-tag"
                                :class="
                                    pi.is_cursed ? 'tag-cursed' : 'tag-normal'
                                "
                            >
                                {{ pi.is_cursed ? "\u{1F480} " : ""
                                }}{{ pi.item.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="summary">
                    <p v-if="isWin">
                        After
                        {{ monthsToYears(gameData.game.total_rounds || 24) }} of
                        counsel, your wisdom has guided the kingdom through its
                        darkest hours. The Kingdom endures thanks to your
                        leadership!
                    </p>
                    <p v-else>
                        After {{ gameData.game.current_round }} months, the
                        kingdom could no longer sustain itself. Perhaps next
                        time, balance will be maintained.
                    </p>
                </div>

                <!-- Completion rewards -->
                <div v-if="completion" class="completion-rewards">
                    <div v-if="myXp" class="reward-item reward-xp">
                        +{{ myXp }} XP
                    </div>
                    <div v-if="myCoins" class="reward-item reward-coins">
                        +{{ myCoins }} Coins
                    </div>
                    <div v-if="myPassPoints" class="reward-item reward-pass">
                        +{{ myPassPoints }} Season Pass &amp; League
                    </div>
                    <div v-if="myLevelUp" class="reward-item reward-level">
                        Level Up! Lv.{{ myLevelUp }}
                    </div>
                    <div
                        v-for="ach in myAchievements"
                        :key="ach.id"
                        class="reward-item reward-achievement"
                    >
                        {{ ach.name }}
                    </div>
                    <div
                        v-if="completion.challenge_completed"
                        class="reward-item reward-challenge"
                    >
                        Challenge Complete:
                        {{ completion.challenge_completed.title }} (+{{
                            completion.challenge_completed.reward_xp
                        }}
                        XP)
                    </div>
                </div>

                <MysteryChest
                    v-if="myBonusChest"
                    :tier="myBonusChest.tier"
                    :coins="myBonusChest.coins"
                    @open="onChestOpen"
                />

                <!-- XP Progress Bar -->
                <div v-if="myXpDetails" class="xp-progress-section">
                    <div class="xp-progress-header">
                        <span class="xp-level-label"
                            >Level {{ xpBarLevel }}</span
                        >
                        <span class="xp-amount">+{{ myXp }} XP</span>
                    </div>
                    <div class="xp-progress-track">
                        <div
                            class="xp-progress-fill"
                            :style="{ width: xpBarPercent + '%' }"
                        ></div>
                    </div>
                    <div class="xp-progress-footer">
                        <span
                            >{{ xpBarDisplayXp }} /
                            {{ xpForLevel(xpBarLevel + 1) }} XP</span
                        >
                    </div>
                    <transition name="levelup-pop">
                        <div v-if="showLevelUp" class="level-up-banner">
                            <span class="level-up-star">&#11088;</span>
                            <span class="level-up-text"
                                >Level {{ myXpDetails.new_level }}!</span
                            >
                            <span class="level-up-star">&#11088;</span>
                        </div>
                    </transition>
                    <div
                        v-if="myUnlocks.length > 0 && showLevelUp"
                        class="new-unlocks"
                    >
                        <div
                            v-for="unlock in myUnlocks"
                            :key="unlock.id"
                            class="unlock-item"
                        >
                            <span class="unlock-icon">&#127381;</span>
                            <span class="unlock-name">{{ unlock.name }}</span>
                            <button
                                class="btn-unlock-claim"
                                @click="router.push('/shop')"
                            >
                                View
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Character XP -->
                <div v-if="myCharXp" class="char-xp-section">
                    <div class="char-xp-row">
                        <span class="char-xp-name">{{
                            myCharXp.character_name
                        }}</span>
                        <span class="char-xp-earned"
                            >+{{ myCharXp.xp_earned }} Advisor XP</span
                        >
                        <span v-if="myCharXp.leveled_up" class="char-xp-lvlup"
                            >Lv.{{ myCharXp.new_level }}!</span
                        >
                    </div>
                    <div
                        v-if="myCharXp.pending_upgrades > 0"
                        class="char-xp-pending"
                    >
                        <button
                            class="btn-char-upgrade"
                            @click="router.push('/collection')"
                        >
                            Level Up Available!
                        </button>
                    </div>
                </div>

                <div class="button-row">
                    <button
                        class="btn-primary play-again"
                        @click="router.push('/')"
                    >
                        Home
                    </button>
                    <button class="play-again share-btn" @click="shareReplay">
                        {{ shareCopied ? "Copied!" : "Share Replay" }}
                    </button>
                </div>
            </div>
        </template>

        <PlayerProfile
            v-if="showProfileUserId"
            :user-id="showProfileUserId"
            @close="showProfileUserId = undefined"
        />
    </div>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { playSound } from "../sounds";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import { checkAndPromptReview } from "../services/app-review-service";
import AppIcon from "./AppIcon.vue";
import { useIcons } from "../stores/icons";
import PlayerProfile from "./PlayerProfile.vue";
import VictoryEffect from "./VictoryEffect.vue";
import MysteryChest from "./MysteryChest.vue";
import { haptic } from "../haptics";
import { xpForLevel } from "../xp";

interface StatIcon {
    key: string;
    label: string;
    short: string;
    type: string;
    value: string;
    icon: string;
}

interface GameCharacter {
    name?: string;
}

interface GamePlayerUser {
    name?: string;
}

interface GameItem {
    name?: string;
}

interface GamePlayerItem {
    id: number;
    is_cursed: boolean;
    item: GameItem;
}

interface GamePlayer {
    id: number;
    player_number: number;
    user_id?: number;
    character_id?: number;
    is_bot?: boolean;
    bot_difficulty?: string;
    character?: GameCharacter;
    user?: GamePlayerUser;
    items?: GamePlayerItem[];
}

type KingdomStatKey =
    "wealth" | "influence" | "security" | "religion" | "food" | "happiness";

interface Game {
    game_mode?: string;
    game_type?: string;
    winner_player_number?: number;
    win?: boolean;
    wealth: number;
    influence: number;
    security: number;
    religion: number;
    food: number;
    happiness: number;
    current_round: number;
    total_rounds: number;
    bonus_score?: number;
    score_modifier?: number;
    final_score?: number;
    num_players?: number;
    timed_out_player_number?: number;
    players?: GamePlayer[];
}

interface DuelKingdom {
    id: number;
    wealth: number;
    influence: number;
    security: number;
    religion: number;
    food: number;
    happiness: number;
    player?: GamePlayer;
}

interface ScoreBreakdown {
    base_score: number;
    year_multiplier: number;
    balance_bonus: number;
    year_bonus: number;
    stacking_bonus: number;
    bonus_score: number;
    score_modifier: number;
    final_score: number;
}

interface GameData {
    game: Game;
    game_type?: string;
    player_kingdoms?: DuelKingdom[];
    timed_out_player_number?: number;
    _scoreBreakdown?: ScoreBreakdown;
}

interface EloChange {
    new?: number;
    change?: number;
}

interface CoinAward {
    coins?: number;
    new_coins?: number;
}

interface XpDetails {
    old_xp: number;
    new_xp: number;
    old_level: number;
    new_level: number;
}

interface AchievementReward {
    id: number;
    name: string;
}

interface UnlockReward {
    id: number;
    name: string;
}

interface CharacterXpAward {
    character_name: string;
    xp_earned: number;
    leveled_up?: boolean;
    new_level?: number;
    pending_upgrades: number;
}

interface BonusChest {
    tier: "common" | "rare" | "epic" | "legendary";
    coins: number;
}

interface CompletionData {
    xp_awards?: Record<string, number>;
    level_ups?: Record<string, number>;
    elo_changes?: Record<string, EloChange>;
    achievements_unlocked?: Record<string, AchievementReward[]>;
    xp_details?: Record<string, XpDetails>;
    coin_awards?: Record<string, CoinAward>;
    bonus_chests?: Record<string, BonusChest>;
    season_pass_points?: Record<string, number>;
    character_xp_awards?: Record<string, CharacterXpAward>;
    new_unlocks?: Record<string, UnlockReward[]>;
    challenge_completed?: {
        title: string;
        reward_xp: number;
    };
}

const { id } = defineProps<{
    id: string | number;
}>();

const router = useRouter();
const auth = useAuth();
const toast = useToast();

const gameData = ref<GameData | undefined>(undefined);
const completion = ref<CompletionData | undefined>(undefined);
const loading = ref(true);
const shareCopied = ref(false);
const showProfileUserId = ref<number | undefined>(undefined);
const timedOutPlayerNumber = ref<number | undefined>(undefined);
const xpBarPercent = ref(0);
const xpBarLevel = ref(0);
const xpBarDisplayXp = ref(0);
const showLevelUp = ref(false);
type EffectType =
    | "gold_rain"
    | "petals"
    | "embers"
    | "confetti"
    | "fireworks"
    | "starfall"
    | "snow";

const confetti = useTemplateRef<{
    fire: (type: EffectType, count?: number) => void;
}>("confetti");

// The local player's equipped victory effect (falls back to gold rain).
const victoryEffectType = computed<EffectType>(() => {
    switch (auth.state.user?.victory_fx) {
        case "petals":
        case "embers":
        case "confetti":
        case "fireworks":
        case "starfall":
        case "snow": {
            return auth.state.user.victory_fx;
        }
        default: {
            return "gold_rain";
        }
    }
});
const stats = ref<StatIcon[]>(useIcons().getStatIcons());

const isDuel = computed<boolean>(
    () =>
        gameData.value?.game?.game_type === "duel" ||
        gameData.value?.game_type === "duel",
);

const duelKingdoms = computed<DuelKingdom[]>(
    () => gameData.value?.player_kingdoms || [],
);

const isTimeout = computed<boolean>(
    () => timedOutPlayerNumber.value !== undefined,
);

const isBothTimeout = computed<boolean>(() => timedOutPlayerNumber.value === 0);

const myPlayerNumber = computed<number | undefined>(() => {
    const userId = auth.state.user?.id;
    const myPlayer = gameData.value?.game?.players?.find(
        (player) => player.user_id === userId,
    );
    return myPlayer?.player_number || undefined;
});

const didITimeout = computed<boolean>(() => {
    if (!isTimeout.value || !myPlayerNumber.value) return false;
    if (isBothTimeout.value) return true;
    return timedOutPlayerNumber.value === myPlayerNumber.value;
});

const isDuelWinner = computed<boolean>(() => {
    if (!isDuel.value) return false;
    if (isBothTimeout.value) return false;
    if (didITimeout.value) return false;
    if (isTimeout.value && !didITimeout.value) return true;
    const winner = gameData.value?.game?.winner_player_number;
    if (!winner || !myPlayerNumber.value) return true;
    return winner === myPlayerNumber.value;
});

const duelEndTitle = computed<string>(() => {
    if (isBothTimeout.value) return "Draw — Both Timed Out";
    if (isTimeout.value) {
        if (didITimeout.value) return "You Timed Out!";
        const winner = gameData.value?.game?.winner_player_number;
        const player = gameData.value?.game?.players?.find(
            (entry) => entry.player_number === winner,
        );
        const name =
            player?.user?.name || player?.character?.name || "Player " + winner;
        return `${name} Wins!`;
    }
    const winner = gameData.value?.game?.winner_player_number;
    if (!winner) return "The Duel is Over";
    const player = gameData.value?.game?.players?.find(
        (entry) => entry.player_number === winner,
    );
    const name =
        player?.user?.name || player?.character?.name || "Player " + winner;
    return `${name} Wins!`;
});

const duelEndFlavor = computed<string>(() => {
    if (isBothTimeout.value) {
        return "Neither ruler could act in time. The kingdoms stand in uneasy stalemate.";
    }
    if (isTimeout.value) {
        if (didITimeout.value) {
            return "You ran out of time. Your opponent claims victory by forfeit.";
        }
        return "Your opponent ran out of time. Victory is yours by forfeit!";
    }
    const winner = gameData.value?.game?.winner_player_number;
    if (!winner) return "The campaign has ended.";
    const loser = winner === 1 ? 2 : 1;
    const winKingdom = duelKingdoms.value.find(
        (kingdom) => kingdom.player?.player_number === winner,
    );
    const loseKingdom = duelKingdoms.value.find(
        (kingdom) => kingdom.player?.player_number === loser,
    );

    if (loseKingdom) {
        const statKeys: KingdomStatKey[] = [
            "wealth",
            "influence",
            "security",
            "religion",
            "food",
            "happiness",
        ];
        const collapsed = statKeys.find((statKey) => loseKingdom[statKey] <= 0);
        if (collapsed) {
            return `The rival kingdom collapsed when ${collapsed} reached zero.`;
        }
        const atMax = statKeys.filter(
            (statKey) => winKingdom && winKingdom[statKey] >= 20,
        ).length;
        if (atMax >= 3) {
            return "Three pillars of the kingdom reached their zenith. A decisive victory!";
        }
    }
    return "After a long campaign, the stronger kingdom prevails.";
});

const isWin = computed<boolean>(() => gameData.value?.game?.win === true);

const totalScore = computed<number>(() => {
    const game = gameData.value?.game;
    if (!game) return 0;
    return (
        game.wealth +
        game.influence +
        game.security +
        game.religion +
        game.food +
        game.happiness
    );
});

const scoreBreakdown = computed<ScoreBreakdown | undefined>(() => {
    // Use stored breakdown from the game-over response, or compute client-side
    const stored = gameData.value?._scoreBreakdown;
    if (stored) return stored;
    // Compute from game data
    const game = gameData.value?.game;
    if (!game) return undefined;
    const base =
        game.wealth +
        game.influence +
        game.security +
        game.religion +
        game.food +
        game.happiness;
    const statValues = [
        game.wealth,
        game.influence,
        game.security,
        game.religion,
        game.food,
        game.happiness,
    ];
    const spread = Math.max(...statValues) - Math.min(...statValues);
    const balanceBonus = Math.max(0, 30 - spread * 3);
    const years = Math.floor((game.current_round || 0) / 12) + 1;
    const multipliers: Record<number, number> = {
        1: 1,
        2: 1.4,
        3: 1.7,
        4: 1.9,
        5: 2,
    };
    const yearMultiplier = multipliers[years] || 2;
    const yearsCompleted = Math.floor((game.current_round || 0) / 12);
    const yearBonus = yearsCompleted * 50;
    const bonusScore = game.bonus_score || 0;
    let stackingBonus = 0;
    for (const value of statValues) {
        if (value >= 15) stackingBonus += 10;
        if (value >= 20) stackingBonus += 20;
    }
    const scoreModifier = game.score_modifier || 0;
    const rawTotal =
        Math.floor(base * yearMultiplier) +
        balanceBonus +
        yearBonus +
        stackingBonus +
        bonusScore;
    const finalScoreValue =
        game.final_score ?? Math.floor(rawTotal * (1 + scoreModifier / 100));
    return {
        base_score: base,
        year_multiplier: yearMultiplier,
        balance_bonus: balanceBonus,
        year_bonus: yearBonus,
        stacking_bonus: stackingBonus,
        bonus_score: bonusScore,
        score_modifier: scoreModifier,
        final_score: finalScoreValue,
    };
});

const finalScore = computed<number>(
    () => scoreBreakdown.value?.final_score ?? totalScore.value,
);

const scoreRank = computed<string>(() => {
    if (!isWin.value) return "The Kingdom has fallen. Better luck next time.";
    const score = finalScore.value;
    if (score >= 200) return "Legendary - The kingdom enters a new Golden Age!";
    if (score >= 150)
        return "Excellent - Your wisdom will be remembered for centuries.";
    if (score >= 100)
        return "Good - The kingdom stands strong thanks to your guidance.";
    if (score >= 60) return "Adequate - The kingdom survives, but just barely.";
    return "Poor - The kingdom limps on, weakened by your counsel.";
});

const endTitle = computed<string>(() => {
    if (!isWin.value) return "The Kingdom Has Fallen";
    const score = finalScore.value;
    if (score >= 150) return "God Save the King!";
    if (score >= 60) return "The Kingdom Endures";
    return "A Narrow Survival";
});

const endFlavor = computed<string>(() => {
    const game = gameData.value?.game;
    if (!game) return "";

    if (!isWin.value) {
        const tooLow = stats.value.find(
            (stat) => game[stat.key as KingdomStatKey] <= 0,
        );
        if (tooLow) {
            return `The kingdom collapsed when ${tooLow.label.toLowerCase()} reached zero. The people lost faith in their advisors.`;
        }
        return "The campaign has ended in defeat.";
    }

    const years = Math.max(1, Math.floor((game.total_rounds || 24) / 12));
    const yearWord =
        years === 1
            ? "one year"
            : years === 2
              ? "two years"
              : years === 3
                ? "three years"
                : years === 4
                  ? "four years"
                  : "five years";
    return `Your advisors have guided the kingdom through ${yearWord} of crisis. The realm celebrates, and your deeds are judged.`;
});

const resultAnimationClass = computed<string>(() => {
    if (!isDuel.value) return "";
    if (isBothTimeout.value) return "result-loss";
    return isDuelWinner.value ? "result-win" : "result-loss";
});

const myXp = computed<number | undefined>(() => {
    if (!completion.value?.xp_awards) return undefined;
    const values = Object.values(completion.value.xp_awards);
    return values.length > 0 ? values[0] : undefined;
});

const myLevelUp = computed<number | undefined>(() => {
    if (!completion.value?.level_ups) return undefined;
    const values = Object.values(completion.value.level_ups);
    return values.length > 0 ? values[0] : undefined;
});

const myBonusChest = computed<BonusChest | undefined>(() => {
    const userId = auth.state.user?.id;
    if (!userId || !completion.value?.bonus_chests) return undefined;
    return completion.value.bonus_chests[String(userId)];
});

const myPassPoints = computed<number | undefined>(() => {
    const userId = auth.state.user?.id;
    if (!userId || !completion.value?.season_pass_points) return undefined;
    return completion.value.season_pass_points[String(userId)];
});

function onChestOpen(tier: BonusChest["tier"]): void {
    // Reflect the chest coins in the header balance the moment it opens.
    const chest = myBonusChest.value;
    if (chest) {
        const current = auth.state.user?.coins ?? 0;
        auth.updateUserStats({ coins: current + chest.coins });
    }
    // Bigger tiers earn a bigger celebration
    if (tier === "epic" || tier === "legendary") {
        confetti.value?.fire("gold_rain", tier === "legendary" ? 180 : 90);
    }
}

const myAchievements = computed<AchievementReward[]>(() => {
    if (!completion.value?.achievements_unlocked) return [];
    const values = Object.values(completion.value.achievements_unlocked);
    return values.length > 0 ? values[0] : [];
});

const myXpDetails = computed<XpDetails | undefined>(() => {
    if (!completion.value?.xp_details) return undefined;
    const values = Object.values(completion.value.xp_details);
    return values.length > 0 ? values[0] : undefined;
});

const myCoins = computed<number | undefined>(() => {
    if (!completion.value?.coin_awards) return undefined;
    const values = Object.values(completion.value.coin_awards);
    return values.length > 0 ? values[0]?.coins : undefined;
});

const myCharXp = computed<CharacterXpAward | undefined>(() => {
    if (!completion.value?.character_xp_awards) return undefined;
    const values = Object.values(completion.value.character_xp_awards);
    return values.length > 0 ? values[0] : undefined;
});

const myUnlocks = computed<UnlockReward[]>(() => {
    if (!completion.value?.new_unlocks) return [];
    const values = Object.values(completion.value.new_unlocks);
    return values.length > 0 ? values[0] : [];
});

function monthsToYears(months: number): string {
    const years = Math.floor(months / 12);
    if (years === 0) return `${months} months`;
    const remainder = months % 12;
    const yearWord = years === 1 ? "1 year" : `${years} years`;
    if (remainder === 0) return yearWord;
    return `${yearWord} and ${remainder} month${remainder === 1 ? "" : "s"}`;
}

function playerDisplayName(player: GamePlayer | undefined): string {
    if (player?.user?.name) return player.user.name;
    return player?.character?.name || "Player";
}

function getValueClass(value: number): string {
    if (value <= 3) return "val-critical";
    if (value <= 7) return "val-danger";
    if (value <= 12) return "val-low";
    if (value >= 18) return "val-high";
    return "val-normal";
}

function kingdomTotal(kingdom: DuelKingdom): number {
    return (
        (kingdom.wealth || 0) +
        (kingdom.influence || 0) +
        (kingdom.security || 0) +
        (kingdom.religion || 0) +
        (kingdom.food || 0) +
        (kingdom.happiness || 0)
    );
}

function animateXpCounter(from: number, to: number, duration: number): void {
    const start = performance.now();
    const step = (now: number): void => {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        // Ease-out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        xpBarDisplayXp.value = Math.round(from + (to - from) * eased);
        if (progress < 1) {
            requestAnimationFrame(step);
        }
    };
    requestAnimationFrame(step);
}

function animateXpBar(): void {
    const details = myXpDetails.value;
    if (!details) return;
    const oldXp = details.old_xp;
    const newXp = details.new_xp;
    const oldLevel = details.old_level;
    const newLevel = details.new_level;

    // Start at old position
    const oldLevelStart = xpForLevel(oldLevel);
    const oldLevelEnd = xpForLevel(oldLevel + 1);
    const oldPercent =
        oldLevelEnd > oldLevelStart
            ? ((oldXp - oldLevelStart) / (oldLevelEnd - oldLevelStart)) * 100
            : 0;

    xpBarLevel.value = oldLevel;
    xpBarPercent.value = Math.min(100, Math.max(0, oldPercent));
    xpBarDisplayXp.value = oldXp;
    showLevelUp.value = false;

    // Animate the XP counter
    animateXpCounter(oldXp, newXp, newLevel > oldLevel ? 1200 : 1600);

    // Animate after a short delay
    setTimeout(() => {
        if (newLevel > oldLevel) {
            // Fill current bar to 100%, then reset for new level
            xpBarPercent.value = 100;
            setTimeout(() => {
                xpBarLevel.value = newLevel;
                xpBarPercent.value = 0;
                showLevelUp.value = true;
                playSound("win");
                haptic("heavy");
                confetti.value?.fire(victoryEffectType.value);
                setTimeout(() => {
                    const newLevelStart = xpForLevel(newLevel);
                    const newLevelEnd = xpForLevel(newLevel + 1);
                    const newPercent =
                        newLevelEnd > newLevelStart
                            ? ((newXp - newLevelStart) /
                                  (newLevelEnd - newLevelStart)) *
                              100
                            : 0;
                    xpBarPercent.value = Math.min(100, Math.max(0, newPercent));
                }, 300);
            }, 1000);
        } else {
            // Same level, just animate to new position
            const levelStart = xpForLevel(oldLevel);
            const levelEnd = xpForLevel(oldLevel + 1);
            const newPercent =
                levelEnd > levelStart
                    ? ((newXp - levelStart) / (levelEnd - levelStart)) * 100
                    : 0;
            xpBarPercent.value = Math.min(100, Math.max(0, newPercent));
        }
    }, 600);
}

async function shareReplay(): Promise<void> {
    try {
        const response = await axios.post<{ share_url: string }>(
            `/api/games/${id}/share`,
        );
        await navigator.clipboard.writeText(response.data.share_url);
        shareCopied.value = true;
        setTimeout(() => {
            shareCopied.value = false;
        }, 2000);
    } catch {
        toast.error("Failed to generate share link");
    }
}

onMounted(async () => {
    try {
        const response = await axios.get<GameData>(`/api/games/${id}`);
        gameData.value = response.data;

        // Load completion data from sessionStorage (set by GameBoard/DuelBoard)
        const stored = sessionStorage.getItem(`game_completion_${id}`);
        if (stored) {
            completion.value = JSON.parse(stored) as CompletionData;
            sessionStorage.removeItem(`game_completion_${id}`);
        }

        // Load timeout data from sessionStorage or game API
        const storedTimeout = sessionStorage.getItem(`game_timeout_${id}`);
        if (storedTimeout) {
            timedOutPlayerNumber.value = JSON.parse(storedTimeout) as number;
            sessionStorage.removeItem(`game_timeout_${id}`);
        } else if (
            gameData.value?.timed_out_player_number !== undefined &&
            gameData.value.timed_out_player_number !== null
        ) {
            timedOutPlayerNumber.value = gameData.value.timed_out_player_number;
        } else if (
            gameData.value?.game?.timed_out_player_number !== undefined &&
            gameData.value.game.timed_out_player_number !== null
        ) {
            timedOutPlayerNumber.value =
                gameData.value.game.timed_out_player_number;
        }

        // Load score breakdown from sessionStorage
        const storedBreakdown = sessionStorage.getItem(
            `game_score_breakdown_${id}`,
        );
        if (storedBreakdown && gameData.value) {
            gameData.value._scoreBreakdown = JSON.parse(
                storedBreakdown,
            ) as ScoreBreakdown;
            sessionStorage.removeItem(`game_score_breakdown_${id}`);
        }

        await nextTick(() => {
            if (isDuel.value || isWin.value) {
                playSound("win");
                haptic("success");
            } else {
                playSound("totalLoss");
                haptic("error");
            }
            // Animate XP bar
            animateXpBar();
            // Play the winner's equipped victory effect.
            const indexWon = isDuel.value ? isDuelWinner.value : isWin.value;
            if (indexWon) {
                confetti.value?.fire(victoryEffectType.value);
            }
            // Update auth store with new stats
            if (myXpDetails.value) {
                const coinAwards = completion.value?.coin_awards
                    ? Object.values(completion.value.coin_awards)
                    : [];
                const newCoins =
                    coinAwards.length > 0
                        ? coinAwards[0]?.new_coins
                        : undefined;
                auth.updateUserStats({
                    xp: myXpDetails.value.new_xp,
                    level: myXpDetails.value.new_level,
                    coins: newCoins,
                });
            }
            // Prompt for app review after a short delay
            setTimeout(() => checkAndPromptReview(), 3000);
        });
    } catch {
        toast.error("Failed to load results");
    }
    loading.value = false;
});
</script>

<style scoped>
.loading {
    text-align: center;
    padding: 60px;
    color: var(--text-secondary);
}

.game-over {
    max-width: 700px;
    margin: 0 auto;
}

.result-panel {
    text-align: center;
}

.game-over-title {
    font-family: "Cinzel", serif;
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.title-win {
    color: var(--accent-gold);
}
.title-loss {
    color: var(--accent-red);
}

.end-flavor {
    font-style: italic;
    color: var(--text-secondary);
    margin-bottom: 25px;
    font-size: 1.1rem;
    line-height: 1.5;
}

.final-stats h3,
.total-score h3,
.advisors-section h3 {
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    margin-bottom: 12px;
    font-size: 1.1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 25px;
}

.final-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.stat-val {
    font-weight: 700;
    font-size: 1.1rem;
}

.val-critical {
    color: #e74c3c;
}
.val-danger {
    color: #e67e22;
}
.val-low {
    color: #d4a843;
}
.val-normal {
    color: var(--text-bright);
}
.val-high {
    color: #27ae60;
}

.total-score {
    margin-bottom: 25px;
}

.score {
    font-family: "Cinzel", serif;
    font-size: 3.5rem;
    color: var(--accent-gold);
    font-weight: 900;
}

.score-rank {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 1.1rem;
    margin-top: 5px;
}

.score-breakdown {
    margin-top: 12px;
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(184, 148, 46, 0.2);
    border-radius: 8px;
    padding: 12px 16px;
    text-align: left;
    display: inline-block;
    min-width: 220px;
}

.breakdown-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.breakdown-row span:last-child {
    color: var(--text-bright);
    font-weight: 600;
}

.breakdown-total {
    border-top: 1px solid rgba(184, 148, 46, 0.3);
    margin-top: 4px;
    padding-top: 6px;
    font-size: 1rem;
}

.breakdown-total span:last-child {
    color: var(--accent-gold);
    font-weight: 900;
}

.mod-positive {
    color: #4caf50;
}
.mod-negative {
    color: #e74c3c;
}

.round-summary {
    margin-bottom: 20px;
}

.rounds-survived {
    font-size: 1.1rem;
    color: var(--text-primary);
}

.rounds-survived strong {
    color: var(--accent-gold);
    font-size: 1.3rem;
}

.advisors-section {
    margin-bottom: 25px;
}

.advisor {
    color: var(--text-primary);
    margin-bottom: 5px;
}

.advisor strong {
    color: var(--accent-gold);
}

.clickable-name {
    cursor: pointer;
}

.clickable-name:hover {
    text-decoration: underline;
}

.advisor-items {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 4px;
}

.advisor-item-tag {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 4px;
}

.tag-normal {
    background: rgba(74, 138, 58, 0.15);
    color: #4a8a3a;
}

.tag-cursed {
    background: rgba(160, 48, 32, 0.2);
    color: #c0392b;
}

.summary {
    color: var(--text-secondary);
    font-style: italic;
    margin-bottom: 25px;
    line-height: 1.5;
}

.button-row {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.play-again {
    font-size: 1.1rem;
    padding: 12px 36px;
}

.share-btn {
    background: rgba(67, 160, 212, 0.15);
    color: #60b8e0;
    border: 1px solid rgba(67, 160, 212, 0.3);
}

.share-btn:hover {
    background: rgba(67, 160, 212, 0.25);
    border-color: rgba(67, 160, 212, 0.5);
}

/* Completion rewards */
.completion-rewards {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    margin-bottom: 20px;
}

.reward-item {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    animation: rewardPop 0.4s ease;
}

.reward-xp {
    background: rgba(212, 168, 67, 0.2);
    color: var(--accent-gold);
    border: 1px solid rgba(212, 168, 67, 0.4);
}

.reward-coins {
    background: rgba(255, 200, 50, 0.2);
    color: #f0c030;
    border: 1px solid rgba(255, 200, 50, 0.4);
}

.reward-level {
    background: rgba(74, 138, 58, 0.2);
    color: #6abf50;
    border: 1px solid rgba(74, 138, 58, 0.4);
}

.reward-pass {
    background: rgba(155, 114, 224, 0.2);
    color: #c79bf0;
    border: 1px solid rgba(155, 114, 224, 0.4);
}

.reward-elo-up {
    background: rgba(67, 160, 212, 0.15);
    color: #60b8e0;
    border: 1px solid rgba(67, 160, 212, 0.3);
}

.reward-elo-down {
    background: rgba(160, 48, 32, 0.15);
    color: #d05040;
    border: 1px solid rgba(160, 48, 32, 0.3);
}

.reward-achievement {
    background: rgba(180, 130, 255, 0.15);
    color: #b482ff;
    border: 1px solid rgba(180, 130, 255, 0.3);
}

.reward-challenge {
    background: rgba(74, 138, 58, 0.15);
    color: #6abf50;
    border: 1px solid rgba(74, 138, 58, 0.3);
}

@keyframes rewardPop {
    0% {
        transform: scale(0.5);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* XP Progress Bar */
.xp-progress-section {
    max-width: 400px;
    margin: 0 auto 24px;
}

.xp-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.xp-level-label {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1rem;
    font-weight: 700;
}

.xp-amount {
    font-size: 0.85rem;
    color: var(--accent-gold);
    font-weight: 600;
}

.xp-progress-track {
    height: 14px;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 7px;
    overflow: hidden;
    border: 1px solid rgba(138, 106, 46, 0.3);
}

.xp-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #8a6a2e, #d4a843, #e8c468);
    border-radius: 7px;
    transition: width 1s ease-in-out;
    box-shadow: 0 0 8px rgba(212, 168, 67, 0.4);
}

.xp-progress-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 4px;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Level-up banner */
.level-up-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 12px;
    padding: 10px;
    background: linear-gradient(
        135deg,
        rgba(212, 168, 67, 0.2),
        rgba(232, 196, 104, 0.1)
    );
    border: 2px solid var(--accent-gold);
    border-radius: 8px;
    animation: levelGlow 1.5s ease-in-out infinite alternate;
}

@keyframes levelGlow {
    from {
        box-shadow: 0 0 8px rgba(212, 168, 67, 0.3);
    }
    to {
        box-shadow: 0 0 24px rgba(212, 168, 67, 0.5);
    }
}

.level-up-star {
    font-size: 1.3rem;
    animation: starSpin 1s ease-in-out;
}

@keyframes starSpin {
    0% {
        transform: scale(0) rotate(-180deg);
    }
    60% {
        transform: scale(1.3) rotate(10deg);
    }
    100% {
        transform: scale(1) rotate(0deg);
    }
}

.level-up-text {
    font-family: "Cinzel", serif;
    font-size: 1.2rem;
    font-weight: 900;
    color: var(--accent-gold);
    letter-spacing: 2px;
}

.levelup-pop-enter-active {
    transition:
        opacity 0.4s ease,
        transform 0.4s ease;
}

.levelup-pop-enter-from {
    opacity: 0;
    transform: scale(0.5);
}

/* New unlocks */
.new-unlocks {
    margin-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.unlock-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(180, 130, 255, 0.1);
    border: 1px solid rgba(180, 130, 255, 0.3);
    border-radius: 6px;
    animation: rewardPop 0.5s ease;
}

.unlock-icon {
    font-size: 1.1rem;
}

.unlock-name {
    flex: 1;
    font-family: "Cinzel", serif;
    color: #b482ff;
    font-size: 0.9rem;
    font-weight: 600;
}

.btn-unlock-claim {
    padding: 4px 14px;
    font-size: 0.75rem;
    background: rgba(180, 130, 255, 0.2);
    border: 1px solid rgba(180, 130, 255, 0.4);
    color: #b482ff;
    border-radius: 4px;
    cursor: pointer;
    font-family: "Cinzel", serif;
}

.btn-unlock-claim:hover {
    background: rgba(180, 130, 255, 0.35);
}

/* Duel end screen */
.duel-kingdoms-final {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 25px;
}

.duel-kingdom-panel {
    background: rgba(26, 18, 9, 0.5);
    border: 2px solid var(--border-gold);
    border-radius: 10px;
    padding: 16px;
    text-align: center;
}

.duel-kingdom-panel.kingdom-winner {
    border-color: var(--accent-gold);
    box-shadow: 0 0 20px rgba(212, 168, 67, 0.25);
}

.kingdom-header {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.1rem;
    margin-bottom: 12px;
}

.kingdom-sub {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 8px;
}

.kingdom-character {
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-style: italic;
}

.kingdom-elo {
    font-family: "Cinzel", serif;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.elo-up {
    color: #4a8a3a;
    font-weight: 700;
}

.elo-down {
    color: #d05040;
    font-weight: 700;
}

.winner-badge {
    display: inline-block;
    background: linear-gradient(180deg, #b8942e, #8a6a14);
    color: #1a1209;
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 3px;
    margin-left: 8px;
    vertical-align: middle;
    letter-spacing: 1px;
}

.timeout-badge {
    display: inline-block;
    background: linear-gradient(180deg, #a03020, #7a2018);
    color: #fdd;
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 3px;
    margin-left: 8px;
    vertical-align: middle;
    letter-spacing: 1px;
}

.draw-badge {
    display: inline-block;
    background: rgba(150, 150, 150, 0.3);
    color: var(--text-secondary);
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 3px;
    margin-left: 8px;
    vertical-align: middle;
    letter-spacing: 1px;
}

.kingdom-score {
    margin-top: 12px;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.kingdom-score strong {
    color: var(--accent-gold);
    font-size: 1.3rem;
}

/* === Victory / Defeat Animations === */
.result-win {
    position: relative;
    animation: winEntrance 0.8s ease-out;
}

.result-win .game-over-title {
    animation: titleWinGlow 1s ease-out 0.3s both;
}

.result-win::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: inherit;
    padding: 2px;
    background: linear-gradient(
        135deg,
        #c9a227,
        #e8c468,
        #d4a843,
        #f0d060,
        #c9a227
    );
    background-size: 400% 400%;
    animation: goldShimmer 3s ease infinite;
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    z-index: 0;
}

.result-loss {
    position: relative;
    animation: lossEntrance 0.8s ease-out;
}

.result-loss .game-over-title {
    animation: titleLossFade 1s ease-out 0.3s both;
}

.result-loss::after {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: inherit;
    border: 2px solid rgba(192, 57, 43, 0.5);
    animation: lossPulse 2s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes winEntrance {
    0% {
        transform: scale(0.8);
        opacity: 0;
    }
    60% {
        transform: scale(1.03);
        opacity: 1;
    }
    100% {
        transform: scale(1);
    }
}

@keyframes goldShimmer {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

@keyframes titleWinGlow {
    0% {
        transform: scale(0.8);
        opacity: 0;
        text-shadow: none;
    }
    100% {
        transform: scale(1);
        opacity: 1;
        text-shadow:
            0 0 20px rgba(212, 168, 67, 0.6),
            0 0 40px rgba(212, 168, 67, 0.3);
    }
}

@keyframes lossEntrance {
    0% {
        opacity: 0;
        transform: translateY(10px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes lossPulse {
    0%,
    100% {
        border-color: rgba(192, 57, 43, 0.3);
    }
    50% {
        border-color: rgba(192, 57, 43, 0.7);
    }
}

@keyframes titleLossFade {
    0% {
        opacity: 0;
        transform: scale(0.95);
    }
    100% {
        opacity: 0.85;
        transform: scale(1);
    }
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
    .game-over-title {
        font-size: 1.8rem;
    }

    .end-flavor {
        font-size: 0.95rem;
        margin-bottom: 18px;
    }

    .score {
        font-size: 2.5rem;
    }

    .score-rank {
        font-size: 0.95rem;
    }

    .stats-grid {
        gap: 6px;
    }

    .stat-label {
        font-size: 0.8rem;
    }

    .stat-val {
        font-size: 1rem;
    }

    .result-panel {
        padding: 16px 12px;
    }

    .final-stats h3,
    .total-score h3,
    .advisors-section h3 {
        font-size: 0.95rem;
    }

    .rounds-survived {
        font-size: 0.95rem;
    }

    .duel-kingdoms-final {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}

/* Character XP Section */
.char-xp-section {
    margin-top: 12px;
    padding: 10px 14px;
    background: rgba(212, 168, 67, 0.06);
    border: 1px solid rgba(212, 168, 67, 0.15);
    border-radius: 8px;
    text-align: center;
}

.char-xp-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.char-xp-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.85rem;
    font-weight: 700;
}

.char-xp-earned {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.char-xp-lvlup {
    background: rgba(90, 184, 122, 0.15);
    border: 1px solid rgba(90, 184, 122, 0.3);
    color: #5ab87a;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
}

.char-xp-pending {
    margin-top: 6px;
}

.btn-char-upgrade {
    padding: 6px 16px;
    font-family: "Cinzel", serif;
    font-size: 0.75rem;
    font-weight: 700;
    background: linear-gradient(180deg, #2a6e3a, #1a4a26);
    border: 2px solid #5ab87a;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-char-upgrade:hover {
    box-shadow: 0 0 10px rgba(90, 184, 122, 0.3);
}
</style>
