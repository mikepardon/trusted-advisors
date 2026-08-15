<template>
  <div class="duel-board">
    <!-- Turn Timer -->
    <div v-if="formattedTimeRemaining" class="turn-timer" :class="{ urgent: timerUrgent }">
      {{ formattedTimeRemaining }}
    </div>

    <!-- Kingdom Stats -->
    <DuelKingdomStats
      ref="kingdomStats"
      :player-kingdoms="playerKingdoms"
      :my-player-number="activePlayerNumber"
      :is-single-player-duel="isSinglePlayerDuel"
      :player-difficulties="playerDifficulties"
      :player-roll-results="playerRollResults"
      :player-dice-themes="playerDiceThemesComputed"
      :dice-animation-trigger="diceAnimationTrigger"
      :player-kingdom-styles="playerKingdomStylesComputed"
      :player-kingdom-style-data="playerKingdomStyleDataComputed"
      :player-titles="playerTitlesComputed"
      :preview-effects="cardPreviewEffects"
      @show-character="openCharacterModal"
      @dice-animation-complete="onDiceAnimationComplete"
    />

    <!-- Event Banner (below kingdoms) -->
    <EventBanner v-if="showEventBanner" :event="currentEvent" />

    <!-- Character Info Modal -->
    <CharacterInfoModal
      v-if="showCharacterModal && selectedCharacterData"
      :character="selectedCharacterData.character"
      :active-dice="selectedCharacterData.activeDice"
      :ability-uses="selectedCharacterData.abilityUses"
      :items="selectedCharacterData.items"
      @close="showCharacterModal = false"
    />

    <!-- Player Items (overlay only, no floating button) -->
    <PlayerItems ref="playerItems" :items="currentPlayerItems" :show-button="false" :current-round="currentRound" />

    <!-- Curse Selection Overlay -->
    <CurseSelectionOverlay
      v-if="showCurseSelection && currentDuelPendingCurse()"
      :curses="currentDuelPendingCurse().curse_details"
      :player-name="players?.find(p => p.player_number === activePlayerNumber)?.character?.name || 'Player'"
      :is-duel="true"
      @selected="onCurseSelected"
    />

    <!-- Event Reveal Overlay -->
    <EventReveal
      v-if="showEventReveal && currentEvent"
      :event="currentEvent"
      @dismiss="showEventReveal = false"
    />

    <!-- TURN HANDOFF OVERLAY (pass and play) -->
    <TurnHandoffOverlay
      v-if="showHandoff"
      :player-number="handoffPlayerNumber"
      :character-name="handoffCharacterName"
      @ready="onHandoffReady"
    />

    <!-- TIMEOUT OVERLAY -->
    <div v-if="showTimeoutOverlay" class="waiting-overlay timeout-overlay">
      <div class="waiting-content">
        <div class="waiting-ornament timeout-icon">&#9201;</div>
        <h2 class="waiting-title timeout-title">Time's Up!</h2>
        <p class="waiting-sub">{{ timeoutMessage }}</p>
      </div>
    </div>

    <!-- WAITING OVERLAY (online, not your turn) -->
    <div v-if="showWaiting && !showTimeoutOverlay" class="waiting-overlay">
      <div class="waiting-content">
        <div class="waiting-ornament">&#9876;</div>
        <h2 class="waiting-title">{{ waitingMessage }}</h2>
        <p class="waiting-sub">Your opponent is making their move...</p>
      </div>
    </div>

    <!-- === CHOOSING PHASE (both players select simultaneously) === -->
    <template v-if="duelPhase === 'choosing' && !showHandoff && !showWaiting">
      <DuelChoosePhase
        :cards="currentCards"
        @select="submitSelection"
        @preview="onCardPreview"
      />
      <div v-if="waitingForOpponentSelection" class="waiting-inline">
        <p>Waiting for opponent to choose their card...</p>
      </div>
    </template>

    <!-- === SIMULTANEOUS ROLLING === -->
    <template v-if="duelPhase === 'rolling' && !showHandoff && !showWaiting">
      <!-- Roll / Continue button (above tabs) -->
      <button
        v-if="!myRollData && itemDecided && !duelRolling"
        class="btn-roll action-btn-top"
        @click="myRollPhase?.startRolling()"
      >
        Roll!
      </button>
      <div v-else-if="showRerollAboveStats" class="continue-above-stats reroll-above">
        <button class="btn-reroll-top" :disabled="rerolling" @click="handleReroll">
          &#9733; {{ myAbility.name }}: {{ rerollLabelTop }}
          <span class="ability-uses-badge">({{ myAbilityUses }} use{{ myAbilityUses > 1 ? 's' : '' }} left)</span>
        </button>
        <button class="btn-continue-top" :disabled="rerolling" @click="handleContinueAfterRoll">
          {{ rerolling ? 'Rerolling...' : 'Skip' }}
        </button>
      </div>
      <div v-else-if="showContinueAboveStats" class="continue-above-stats">
        <button class="btn-continue-top" @click="handleContinueAfterRoll">Continue</button>
      </div>

      <!-- Roll Tabs -->
      <div class="duel-roll-tabs">
        <button class="roll-tab" :class="myTabClass" @click="rollTab = 'mine'">You</button>
        <button class="roll-tab" :class="opponentTabClass" @click="rollTab = 'opponent'">{{ opponentCharacterName }}</button>
      </div>

      <!-- Your Roll (v-show to preserve dice animation state) -->
      <DuelRollPhase
        v-show="rollTab === 'mine'"
        ref="myRollPhase"
        :cards="myCards"
        :player-name="isOfferer ? offererName : chooserName"
        :can-roll="!myRollData"
        :roll-data="myRollData"
        :dice-count="diceCount"
        :ability="myAbility"
        :ability-uses="myAbilityUses"
        :ability-activated="abilityActivated"
        :activating-ability="activatingAbility"
        :peeked-cards="peekedCards"
        :rerolling="rerolling"
        :needs-continue="pendingRerollDecision"
        :use3d-dice="dddiceAvailable"
        :player-items="currentPlayerItems"
        :item-decided="itemDecided"
        :hide-roll-button="true"
        :hide-reroll-section="true"
        :current-round="currentRound"
        @roll="submitRoll"
        @use-ability="activateAbility"
        @reroll="handleReroll"
        @continue="handleContinueAfterRoll"
        @use-item="handleUseItem"
        @skip-item="handleSkipItem"
      />

      <!-- Opponent's Roll -->
      <template v-if="rollTab === 'opponent'">
        <DuelRollPhase
          v-if="opponentRollData"
          :roll-data="opponentRollData"
          :player-name="opponentCharacterName"
          :can-roll="false"
          :dice-count="diceCount"
          :use3d-dice="dddiceAvailable"
        />
        <div v-else class="waiting-inline">
          <p>Waiting for {{ opponentCharacterName }} to roll...</p>
        </div>
      </template>

      <!-- Post-continue waiting -->
      <div v-if="rollTab === 'mine' && myRollData && !opponentRollData && !pendingRerollDecision" class="waiting-inline">
        <p>Waiting for {{ opponentCharacterName }} to roll...</p>
      </div>
    </template>

    <!-- === ROLLING OFFERER (sequential: pass-and-play / single-player) === -->
    <template v-if="duelPhase === 'rolling_offerer' && !showHandoff && !showWaiting">
      <button
        v-if="isOfferer && !offererRollData && itemDecided && !duelRolling"
        class="btn-roll action-btn-top"
        @click="offererRollPhase?.startRolling()"
      >
        Roll!
      </button>
      <div v-else-if="showRerollAboveStats" class="continue-above-stats reroll-above">
        <button class="btn-reroll-top" :disabled="rerolling" @click="handleReroll">
          &#9733; {{ myAbility.name }}: {{ rerollLabelTop }}
          <span class="ability-uses-badge">({{ myAbilityUses }} use{{ myAbilityUses > 1 ? 's' : '' }} left)</span>
        </button>
        <button class="btn-continue-top" :disabled="rerolling" @click="handleContinueAfterRoll">
          {{ rerolling ? 'Rerolling...' : 'Skip' }}
        </button>
      </div>
      <div v-else-if="showContinueAboveStats" class="continue-above-stats">
        <button class="btn-continue-top" @click="handleContinueAfterRoll">Continue</button>
      </div>
      <DuelRollPhase
        ref="offererRollPhase"
        :cards="myCards"
        :player-name="offererName"
        :can-roll="isOfferer"
        :roll-data="offererRollData"
        :dice-count="diceCount"
        :ability="isOfferer ? myAbility : undefined"
        :ability-uses="isOfferer ? myAbilityUses : 0"
        :ability-activated="abilityActivated"
        :activating-ability="activatingAbility"
        :peeked-cards="peekedCards"
        :rerolling="rerolling"
        :needs-continue="pendingRerollDecision"
        :use3d-dice="dddiceAvailable"
        :player-items="currentPlayerItems"
        :item-decided="itemDecided"
        :hide-roll-button="true"
        :hide-reroll-section="true"
        :current-round="currentRound"
        @roll="submitRoll"
        @use-ability="activateAbility"
        @reroll="handleReroll"
        @continue="handleContinueAfterRoll"
        @use-item="handleUseItem"
        @skip-item="handleSkipItem"
      />
    </template>

    <!-- === ROLLING CHOOSER (sequential: pass-and-play / single-player) === -->
    <template v-if="duelPhase === 'rolling_chooser' && !showHandoff && !showWaiting">
      <button
        v-if="isChooser && !chooserRollData && itemDecided && !duelRolling"
        class="btn-roll action-btn-top"
        @click="chooserRollPhase?.startRolling()"
      >
        Roll!
      </button>
      <div v-else-if="showRerollAboveStats" class="continue-above-stats reroll-above">
        <button class="btn-reroll-top" :disabled="rerolling" @click="handleReroll">
          &#9733; {{ myAbility.name }}: {{ rerollLabelTop }}
          <span class="ability-uses-badge">({{ myAbilityUses }} use{{ myAbilityUses > 1 ? 's' : '' }} left)</span>
        </button>
        <button class="btn-continue-top" :disabled="rerolling" @click="handleContinueAfterRoll">
          {{ rerolling ? 'Rerolling...' : 'Skip' }}
        </button>
      </div>
      <div v-else-if="showContinueAboveStats" class="continue-above-stats">
        <button class="btn-continue-top" @click="handleContinueAfterRoll">Continue</button>
      </div>
      <!-- Show offerer's completed roll -->
      <DuelRollPhase
        v-if="offererRollData"
        :roll-data="offererRollData"
        :player-name="offererName"
        :can-roll="false"
        :dice-count="diceCount"
        :use3d-dice="dddiceAvailable"
      />
      <!-- Chooser's roll -->
      <DuelRollPhase
        ref="chooserRollPhase"
        :cards="myCards"
        :player-name="chooserName"
        :can-roll="isChooser"
        :roll-data="chooserRollData"
        :dice-count="diceCount"
        :ability="isChooser ? myAbility : undefined"
        :ability-uses="isChooser ? myAbilityUses : 0"
        :ability-activated="abilityActivated"
        :activating-ability="activatingAbility"
        :peeked-cards="peekedCards"
        :rerolling="rerolling"
        :needs-continue="pendingRerollDecision"
        :use3d-dice="dddiceAvailable"
        :player-items="currentPlayerItems"
        :item-decided="itemDecided"
        :hide-roll-button="true"
        :hide-reroll-section="true"
        :current-round="currentRound"
        @roll="submitRoll"
        @use-ability="activateAbility"
        @reroll="handleReroll"
        @continue="handleContinueAfterRoll"
        @use-item="handleUseItem"
        @skip-item="handleSkipItem"
      />
    </template>

    <!-- Resolving phase skipped in duel — continue goes straight to next round -->
  </div>
</template>


<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from "vue";
import axios, { isAxiosError } from "axios";
import DuelKingdomStats from "./DuelKingdomStats.vue";
import DuelChoosePhase from "./DuelChoosePhase.vue";
import DuelRollPhase from "./DuelRollPhase.vue";
import TurnHandoffOverlay from "./TurnHandoffOverlay.vue";
import PlayerItems from "./PlayerItems.vue";
import EventReveal from "./EventReveal.vue";
import EventBanner from "./EventBanner.vue";
import CharacterInfoModal from "./CharacterInfoModal.vue";
import CurseSelectionOverlay from "./CurseSelectionOverlay.vue";
import { useRouter } from "vue-router";
import { isDddiceAvailable } from "../dddice-service";
import { useAuth } from "../stores/auth";
import { haptic } from "../haptics";
import { useToast } from "../stores/toast";

interface Character {
  name?: string;
  wild_ability: string;
  wild_ability_description: string;
}

interface KingdomStyleData {
  slug: string;
  background_image_url?: string;
  css_vars?: Record<string, string>;
}

interface KingdomStyle {
  slug?: string;
  background_image_url?: string;
  css_vars?: Record<string, string>;
}

interface GamePlayerUser {
  id: number;
  name?: string;
  active_kingdom_style_slug?: string;
  active_kingdom_style?: KingdomStyle;
  active_title?: string;
  active_dice_theme_slug?: string;
}

interface GamePlayer {
  id: number;
  player_number: number;
  user_id?: number;
  is_bot?: boolean;
  user?: GamePlayerUser;
  character?: Character;
  ability_uses?: number;
  lost_dice?: number;
  items?: PlayerItemData[];
}

interface PlayerItemData {
  is_used?: boolean;
  used_round?: number;
  item?: Record<string, unknown>;
}

interface RollCard {
  card?: { difficulty?: number };
  difficulty?: number;
  success?: boolean;
}

interface RollResult {
  player_number?: number;
  total_roll?: number;
  cards?: RollCard[];
  success?: boolean;
  duel_result?: unknown;
  rerolled?: boolean;
  remaining_uses?: number;
  player_items?: PlayerItemData[];
  pending_curses?: PendingCurse[];
  player_curses?: unknown;
}

interface PendingCurse {
  player_id: number;
  curse_details?: unknown;
}

interface DuelCard {
  difficulty?: number;
}

interface DuelHandItem {
  card?: DuelCard;
  difficulty?: number;
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

interface DiceAnimationResolve {
  resolve: () => void;
  timestamp: number;
}

interface GameData {
  current_round?: number;
  game_mode?: string;
  duel_phase?: string;
  offerer_player_number?: number;
  player_kingdoms?: unknown[];
  current_event?: unknown;
  pending_curses?: PendingCurse[];
  player_curses?: Record<number, unknown>;
  turn_time_limit?: number;
  turn_time_remaining?: number;
  round_results?: { offerer?: RollResult; chooser?: RollResult };
  game?: {
    user_id?: number;
    current_round?: number;
    duel_phase?: string;
    offerer_player_number?: number;
    players?: GamePlayer[];
    status?: string;
    turn_time_remaining?: number;
    round_results?: { offerer?: RollResult; chooser?: RollResult };
  };
}

interface CharacterModalData {
  character: Character;
  activeDice: number;
  abilityUses: number;
  items: PlayerItemData[];
}

interface DuelChoiceMadeData {
  duel_phase: string;
  player1_cards?: RollCard[];
  player2_cards?: RollCard[];
}

interface DuelRollCompleteData {
  roll_data: RollResult;
  duel_phase?: string;
}

const { gameData, gameId, showEventBanner = true } = defineProps<{
  gameData: GameData;
  gameId: string | number;
  showEventBanner?: boolean;
}>();

const emit = defineEmits<{
  refresh: [];
  "gameOver": [];
  "gameDataUpdated": [data: GameData];
  "phaseUpdated": [phase: string];
  "itemsUpdated": [count: number];
  "diceUpdated": [count: number];
}>();

const router = useRouter();
const auth = useAuth();
const toast = useToast();

const kingdomStats = useTemplateRef("kingdomStats");
const playerItems = useTemplateRef("playerItems");
const myRollPhase = useTemplateRef("myRollPhase");
const offererRollPhase = useTemplateRef("offererRollPhase");
const chooserRollPhase = useTemplateRef("chooserRollPhase");

const duelPhase = ref<string | undefined>(undefined);
const offererPlayerNumber = ref<number | undefined>(undefined);
const playerKingdoms = ref<unknown[]>([]);
const currentCards = ref<DuelHandItem[]>([]);
const myCards = ref<DuelHandItem[]>([]);
const offererRollData = ref<RollResult | undefined>(undefined);
const chooserRollData = ref<RollResult | undefined>(undefined);
const isGameOver = ref(false);
const waitingForOpponentSelection = ref(false);
// Pass-and-play handoff
const showHandoff = ref(false);
const handoffPlayerNumber = ref<number | undefined>(undefined);
// Active player tracking (for pass-and-play)
const activePlayerNumber = ref(1);
// Items & Dice
const currentPlayerItems = ref<PlayerItemData[]>([]);
const diceCount = ref(4);
// Event reveal
const showEventReveal = ref(false);
// Online waiting
const showWaiting = ref(false);
const waitingMessage = ref("");
// Ability
const abilityActivated = ref(false);
const activatingAbility = ref(false);
const peekedCards = ref<unknown>(undefined);
// Reroll
const rerolling = ref(false);
const pendingRerollDecision = ref(false);
// Roll tab nav
const rollTab = ref<"mine" | "opponent">("mine");
// Per-player dice in kingdom stats
const playerDifficulties = ref<Record<number, number | undefined>>({});
const playerRollResults = ref<Record<number, RollResult | undefined>>({});
const diceAnimationTrigger = ref<DiceAnimationTrigger | undefined>(undefined);
const diceAnimationResolveReference = ref<DiceAnimationResolve | undefined>(undefined);
// Item decision tracking
const itemDecided = ref(false);
const duelRolling = ref(false);
// Character info modal
const showCharacterModal = ref(false);
const selectedCharacterData = ref<CharacterModalData | undefined>(undefined);
// Turn timer
const turnTimeLimit = ref<number | undefined>(undefined);
const turnTimeRemaining = ref<number | undefined>(undefined);
const turnTimerInterval = ref<ReturnType<typeof setInterval> | undefined>(undefined);
// Timeout overlay
const showTimeoutOverlay = ref(false);
const timeoutMessage = ref("");
const reportingTimeout = ref(false);
const timeoutSafetyTimer = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
// Curses
const pendingCurses = ref<PendingCurse[] | undefined>(undefined);
const showCurseSelection = ref(false);
const playerCurses = ref<Record<number, unknown>>({});
// Card effect preview
const cardPreviewEffects = ref<PreviewEffects | undefined>(undefined);
// Non-reactive orchestration flags
const opponentTurnPending = ref(false);
const advancing = ref(false);

function computeDiff(cards: RollCard[] | undefined): number {
  return cards?.reduce((sum, c) => sum + ((c.card || c).difficulty || 0), 0) || 0;
}

function errorMessage(error: unknown): string {
  if (isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error || error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return String(error);
}

const players = computed<GamePlayer[] | undefined>(() => gameData?.game?.players);

const currentRound = computed<number>(() => gameData?.current_round || gameData?.game?.current_round || 0);
const isOnline = computed<boolean>(() => gameData?.game_mode === "online");
const isPassAndPlay = computed<boolean>(() => gameData?.game_mode === "pass_and_play");
const offererNumber = computed<number>(() => offererPlayerNumber.value || gameData?.offerer_player_number || 1);
const chooserNumber = computed<number>(() => (offererNumber.value === 1 ? 2 : 1));
const isOfferer = computed<boolean>(() => activePlayerNumber.value === offererNumber.value);
const isChooser = computed<boolean>(() => activePlayerNumber.value === chooserNumber.value);

const isSinglePlayerDuel = computed<boolean>(
  () => gameData?.game_mode === "single" && !isOnline.value && !isPassAndPlay.value,
);

const offererName = computed<string>(() => {
  const player = gameData?.game?.players?.find((p) => p.player_number === offererNumber.value);
  const isBot = player?.is_bot && !player?.user;
  const name = isBot ? (player?.character?.name || "Bot") : (player?.user?.name || `Player ${offererNumber.value}`);
  if (isSinglePlayerDuel.value && offererNumber.value === activePlayerNumber.value) {
    return `${name} (YOU)`;
  }
  return name;
});

const chooserName = computed<string>(() => {
  const player = gameData?.game?.players?.find((p) => p.player_number === chooserNumber.value);
  const isBot = player?.is_bot && !player?.user;
  const name = isBot ? (player?.character?.name || "Bot") : (player?.user?.name || `Player ${chooserNumber.value}`);
  if (isSinglePlayerDuel.value && chooserNumber.value === activePlayerNumber.value) {
    return `${name} (YOU)`;
  }
  return name;
});

const handoffCharacterName = computed<string>(() => {
  if (!handoffPlayerNumber.value || !gameData?.game?.players) {
    return "";
  }
  const player = gameData.game.players.find((p) => p.player_number === handoffPlayerNumber.value);
  return player?.character?.name || `Player ${handoffPlayerNumber.value}`;
});

const myAbility = computed<{ name: string; description: string } | undefined>(() => {
  const player = gameData?.game?.players?.find((p) => p.player_number === activePlayerNumber.value);
  if (!player?.character) {
    return undefined;
  }
  return {
    name: player.character.wild_ability,
    description: player.character.wild_ability_description,
  };
});

const myAbilityUses = computed<number>(() => {
  const player = gameData?.game?.players?.find((p) => p.player_number === activePlayerNumber.value);
  return player?.ability_uses ?? 0;
});

const currentEvent = computed<unknown>(() => gameData?.current_event || undefined);

const botPlayer = computed<GamePlayer | undefined>(() => gameData?.game?.players?.find((p) => p.is_bot));
const hasBotPlayer = computed<boolean>(() => !!botPlayer.value);

const myRollData = computed<RollResult | undefined>(() => {
  if (isOfferer.value) {
    return offererRollData.value;
  }
  return chooserRollData.value;
});

const opponentRollData = computed<RollResult | undefined>(() => {
  if (isOfferer.value) {
    return chooserRollData.value;
  }
  return offererRollData.value;
});

const opponentCharacterName = computed<string>(() => {
  const opponentNumber = activePlayerNumber.value === 1 ? 2 : 1;
  const player = gameData?.game?.players?.find((p) => p.player_number === opponentNumber);
  const isBot = player?.is_bot && !player?.user;
  return isBot ? (player?.character?.name || "Bot") : (player?.user?.name || `Player ${opponentNumber}`);
});

const myTabClass = computed<Record<string, boolean>>(() => {
  const cls: Record<string, boolean> = { active: rollTab.value === "mine" };
  if (myRollData.value?.cards?.length) {
    cls["tab-success"] = myRollData.value.cards[0].success === true;
    cls["tab-failure"] = myRollData.value.cards[0].success === false;
  }
  return cls;
});

const opponentTabClass = computed<Record<string, boolean>>(() => {
  const cls: Record<string, boolean> = { active: rollTab.value === "opponent" };
  if (opponentRollData.value?.cards?.length) {
    cls["tab-success"] = opponentRollData.value.cards[0].success === true;
    cls["tab-failure"] = opponentRollData.value.cards[0].success === false;
  } else {
    cls["tab-waiting"] = true;
  }
  return cls;
});

const dddiceAvailable = computed<boolean>(() => isDddiceAvailable());

const showRerollAboveStats = computed<boolean>(() => {
  if (!pendingRerollDecision.value) {
    return false;
  }
  if (!myRollData.value) {
    return false;
  }
  if (duelPhase.value === "rolling" && (!offererRollData.value || !chooserRollData.value)) {
    return false;
  }
  const ability = myAbility.value?.name;
  const isRerollAbility = ["rally", "gamble"].includes(ability ?? "");
  return isRerollAbility && myAbilityUses.value > 0 && !abilityActivated.value && !myRollData.value?.rerolled;
});

const rerollLabelTop = computed<string>(() => {
  if (!myAbility.value) {
    return "";
  }
  if (myAbility.value.name === "rally") {
    return "Reroll lowest die?";
  }
  if (myAbility.value.name === "gamble") {
    return "Reroll all dice?";
  }
  return myAbility.value.description;
});

const showContinueAboveStats = computed<boolean>(() => {
  if (!pendingRerollDecision.value) {
    return false;
  }
  if (!myRollData.value) {
    return false;
  }
  if (duelPhase.value === "rolling" && (!offererRollData.value || !chooserRollData.value)) {
    return false;
  }
  return !showRerollAboveStats.value;
});

const playerDiceThemesComputed = computed<Record<number, string[]>>(() => ({
  1: getThemesForPlayer(1),
  2: getThemesForPlayer(2),
}));

const playerKingdomStylesComputed = computed<Record<number, string>>(() => ({
  1: getKingdomStyleForPlayer(1),
  2: getKingdomStyleForPlayer(2),
}));

const playerKingdomStyleDataComputed = computed<Record<number, KingdomStyleData | undefined>>(() => ({
  1: getKingdomStyleDataForPlayer(1),
  2: getKingdomStyleDataForPlayer(2),
}));

const playerTitlesComputed = computed<Record<number, string | undefined>>(() => ({
  1: getTitleForPlayer(1),
  2: getTitleForPlayer(2),
}));

const isCurrentTurnBot = computed<boolean>(() => {
  if (isOnline.value || !hasBotPlayer.value) {
    return false;
  }
  const botNumber = botPlayer.value?.player_number;
  const phase = duelPhase.value;
  if (phase === "choosing") {
    return true; // Bot always needs to select in choosing phase
  }
  if (phase === "rolling") {
    return true; // Bot needs to roll in simultaneous mode
  }
  if (phase === "rolling_offerer" && offererNumber.value === botNumber) {
    return true;
  }
  if (phase === "rolling_chooser" && chooserNumber.value === botNumber) {
    return true;
  }
  return false;
});

const formattedTimeRemaining = computed<string | undefined>(() => {
  if (turnTimeRemaining.value == undefined) {
    return undefined;
  }
  const total = turnTimeRemaining.value;
  if (total <= 0) {
    return "0:00";
  }
  const hours = Math.floor(total / 3600);
  const mins = Math.floor((total % 3600) / 60);
  const secs = total % 60;
  if (hours > 0) {
    return `${hours}:${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
  }
  return `${mins}:${secs.toString().padStart(2, "0")}`;
});

const timerUrgent = computed<boolean>(() => turnTimeRemaining.value != undefined && turnTimeRemaining.value <= 30);

watch(
  () => gameData,
  (newData) => {
    if (newData) {
      syncFromGameData(newData);
    }
  },
  { immediate: true },
);

watch(duelPhase, (newPhase) => {
  if (newPhase !== "choosing") {
    cardPreviewEffects.value = undefined;
  }
});

watch(
  currentPlayerItems,
  (items) => {
    const round = currentRound.value;
    emit(
      "itemsUpdated",
      (items || []).filter((pi) => !pi.is_used && !(pi.used_round && pi.used_round === round)).length,
    );
  },
  { deep: true, immediate: true },
);

watch(diceCount, (value) => {
  emit("diceUpdated", value);
});

watch(isCurrentTurnBot, (isBotTurn) => {
  // For simultaneous rolling, bot rolls when human rolls — don't pre-trigger
  if (isBotTurn && duelPhase.value !== "rolling") {
    triggerBotTurn();
  }
});

function openCharacterModal(playerNumber: number): void {
  const player = gameData?.game?.players?.find((p) => p.player_number === playerNumber);
  if (!player?.character) {
    return;
  }
  selectedCharacterData.value = {
    character: player.character,
    activeDice: Math.max(1, 4 - (player.lost_dice || 0)),
    abilityUses: player.ability_uses ?? 0,
    items: player.items || [],
  };
  showCharacterModal.value = true;
}

function startTurnTimer(): void {
  if (turnTimerInterval.value) {
    clearInterval(turnTimerInterval.value);
  }
  // If already expired, trigger immediately
  if (turnTimeRemaining.value != undefined && turnTimeRemaining.value <= 0) {
    turnTimeRemaining.value = 0;
    handleTimerExpired();
    return;
  }
  turnTimerInterval.value = setInterval(() => {
    if (turnTimeRemaining.value != undefined && turnTimeRemaining.value > 0) {
      turnTimeRemaining.value--;
      if (turnTimeRemaining.value <= 0) {
        clearInterval(turnTimerInterval.value);
        turnTimerInterval.value = undefined;
        handleTimerExpired();
      }
    } else {
      clearInterval(turnTimerInterval.value);
      turnTimerInterval.value = undefined;
      handleTimerExpired();
    }
  }, 1000);
}

async function handleTimerExpired(): Promise<void> {
  if (reportingTimeout.value || showTimeoutOverlay.value) {
    return;
  }
  reportingTimeout.value = true;

  // Show overlay immediately
  timeoutMessage.value = "Waiting for server...";
  showTimeoutOverlay.value = true;

  // Nudge the server to forfeit if expired
  try {
    await axios.post(`/api/games/${gameId}/check-timeout`);
  } catch (error) {
    console.error("Timeout check failed:", error);
  }

  // The server will broadcast DuelGameOver, which GameBoard.vue handles
  // and redirects to the game-over screen. Safety poll after 5s in case
  // the broadcast doesn't arrive (e.g. WebSocket disconnect).
  timeoutSafetyTimer.value = setTimeout(async () => {
    try {
      const response = await axios.get<GameData>(`/api/games/${gameId}`);
      if (response.data.game?.status === "completed" || response.data.game?.status === "cancelled") {
        router.replace(`/game/${gameId}/over`);
      } else {
        // Game still active (opponent acted just in time) — re-sync
        showTimeoutOverlay.value = false;
        reportingTimeout.value = false;
        const remaining = response.data.turn_time_remaining ?? response.data.game?.turn_time_remaining;
        if (remaining != undefined && remaining > 0) {
          turnTimeRemaining.value = remaining;
          startTurnTimer();
        }
        emit("refresh");
      }
    } catch {
      router.replace(`/game/${gameId}/over`);
    }
  }, 5000);
}

function checkEventReveal(): void {
  const round = gameData?.current_round || gameData?.game?.current_round || 0;
  const event = currentEvent.value as { id?: number } | undefined;
  if (event && (round - 1) % 3 === 0) {
    const key = `game_${gameId}_event_${event.id}`;
    if (!sessionStorage.getItem(key)) {
      sessionStorage.setItem(key, "1");
      showEventReveal.value = true;
    }
  }
}

function syncFromGameData(data: GameData): void {
  duelPhase.value = data.duel_phase || data.game?.duel_phase;
  offererPlayerNumber.value = data.offerer_player_number || data.game?.offerer_player_number;
  playerKingdoms.value = data.player_kingdoms || [];

  // Sync curses
  if (data.pending_curses) {
    pendingCurses.value = data.pending_curses;
  }
  if (data.player_curses) {
    playerCurses.value = data.player_curses;
  }

  // Sync turn timer (don't restart if game-over timeout overlay is showing)
  if (data.turn_time_limit && !showTimeoutOverlay.value) {
    turnTimeLimit.value = data.turn_time_limit;
    if (data.turn_time_remaining != undefined) {
      turnTimeRemaining.value = data.turn_time_remaining;
      reportingTimeout.value = false; // Release guard — fresh timer from server
      startTurnTimer();
    }
  }

  // Determine my player number
  if (isOnline.value || isSinglePlayerDuel.value) {
    const userId = auth.state.user?.id;
    const myPlayer = data.game?.players?.find((p) => p.user_id === userId);
    if (myPlayer) {
      activePlayerNumber.value = myPlayer.player_number;
    } else if (isSinglePlayerDuel.value) {
      activePlayerNumber.value = 1;
    }
    if (isOnline.value && !hasBotPlayer.value) {
      updateOnlineWaiting();
    }
  }

  // If entering choosing phase, load cards
  if (duelPhase.value === "choosing" && !showHandoff.value) {
      loadDuelHand();
    }

  // If entering rolling phase, load my cards
  if (["rolling", "rolling_offerer", "rolling_chooser"].includes(duelPhase.value ?? "")) {
    loadMyRollCards();
    // For sequential rolling, trigger bot with delay; for simultaneous, bot rolls when human rolls
    if (duelPhase.value !== "rolling" && hasBotPlayer.value && isCurrentTurnBot.value && !opponentTurnPending.value) {
      triggerBotTurn();
    }
  }

  checkEventReveal();
}

function updateOnlineWaiting(): void {
  if (!isOnline.value || hasBotPlayer.value) {
    showWaiting.value = false;
    return;
  }

  const phase = duelPhase.value;
  const myNumber = activePlayerNumber.value;
  const offNumber = offererNumber.value;
  const choNumber = chooserNumber.value;

  const isOpponentRolling =
    (phase === "rolling_offerer" && myNumber !== offNumber) ||
    (phase === "rolling_chooser" && myNumber !== choNumber);

  if (phase === "rolling") {
    // Simultaneous rolling — no full-screen waiting overlay
    showWaiting.value = false;
  } else if (isOpponentRolling) {
    showWaiting.value = true;
    waitingMessage.value = "Opponent is Rolling";
  } else {
    showWaiting.value = false;
  }
}

async function loadDuelHand(): Promise<void> {
  try {
    const response = await axios.get<{ cards?: DuelHandItem[]; items?: PlayerItemData[]; dice_count?: number }>(
      `/api/games/${gameId}/duel-hand/${activePlayerNumber.value}`,
    );
    currentCards.value = response.data.cards || [];
    currentPlayerItems.value = response.data.items || [];
    diceCount.value = response.data.dice_count ?? 4;
    // Auto-decide if player has no usable items (exclude items on cooldown this round)
    const round = currentRound.value;
    const usable = currentPlayerItems.value.filter((pi) => !pi.is_used && !(pi.used_round && pi.used_round === round));
    itemDecided.value = usable.length === 0 ? true : false;
  } catch {
    currentCards.value = [];
    currentPlayerItems.value = [];
    itemDecided.value = true;
  }
}

function initiatePassAndPlayHandoff(playerNumber: number): void {
  handoffPlayerNumber.value = playerNumber;
  showHandoff.value = true;
}

async function onHandoffReady(): Promise<void> {
  showHandoff.value = false;
  if (handoffPlayerNumber.value !== undefined) {
    activePlayerNumber.value = handoffPlayerNumber.value;
  }
  await loadDuelHand();
}

function onCardPreview(effects: PreviewEffects | undefined): void {
  cardPreviewEffects.value = effects;
}

async function submitSelection(keptHandId: number): Promise<void> {
  try {
    const response = await axios.post<{
      waiting?: boolean;
      duel_phase?: string;
      player1_cards?: RollCard[];
      player2_cards?: RollCard[];
    }>(`/api/games/${gameId}/duel-select`, {
      kept_hand_id: keptHandId,
    });

    if (response.data.waiting) {
      // Waiting for opponent to select
      waitingForOpponentSelection.value = true;

      if (isPassAndPlay.value) {
        // Pass-and-play: hand off to other player for their selection
        waitingForOpponentSelection.value = false;
        const otherPlayer = activePlayerNumber.value === 1 ? 2 : 1;
        initiatePassAndPlayHandoff(otherPlayer);
      } else if (hasBotPlayer.value) {
        // Bot needs to select — trigger bot turn
        triggerBotTurn();
      }
      return;
    }

    // Both selected — transition to rolling
    const phase = response.data.duel_phase || "rolling_offerer";
    duelPhase.value = phase;
    waitingForOpponentSelection.value = false;
    emit("phaseUpdated", phase);

    // Compute difficulties for both players from swapped cards
    const d1 = computeDiff(response.data.player1_cards);
    const d2 = computeDiff(response.data.player2_cards);
    if (d1 > 0) {
      playerDifficulties.value = { ...playerDifficulties.value, 1: d1 };
    }
    if (d2 > 0) {
      playerDifficulties.value = { ...playerDifficulties.value, 2: d2 };
    }

    if (isPassAndPlay.value) {
      await loadMyRollCards();
      initiatePassAndPlayHandoff(offererNumber.value);
    } else if (hasBotPlayer.value) {
      opponentTurnPending.value = false;
      await loadMyRollCards();
      // For simultaneous rolling, bot rolls when human rolls (triggerBotRollImmediate)
      // For sequential, trigger bot turn with delay
      if (phase !== "rolling" && isCurrentTurnBot.value) {
        triggerBotTurn();
      }
    } else {
      await loadMyRollCards();
      updateOnlineWaiting();
    }
  } catch (error) {
    toast.error("Failed to select: " + errorMessage(error));
  }
}

async function loadMyRollCards(): Promise<void> {
  try {
    const response = await axios.get<{ cards?: DuelHandItem[]; items?: PlayerItemData[] }>(
      `/api/games/${gameId}/duel-hand/${activePlayerNumber.value}`,
    );
    const cards = response.data.cards || [];
    myCards.value = cards;
    // Compute difficulty for this player's cards
    const totalDifficulty = cards.reduce((sum, c) => sum + ((c.card || c).difficulty || 0), 0);
    if (totalDifficulty > 0) {
      playerDifficulties.value = { ...playerDifficulties.value, [activePlayerNumber.value]: totalDifficulty };
    }
    // Update items so the roll button / item prompt shows correctly
    currentPlayerItems.value = response.data.items || [];
    const round = currentRound.value;
    const usable = currentPlayerItems.value.filter((pi) => !pi.is_used && !(pi.used_round && pi.used_round === round));
    itemDecided.value = usable.length === 0 ? true : false;
  } catch {
    myCards.value = [];
    currentPlayerItems.value = [];
    itemDecided.value = true;
  }
}

async function activateAbility(): Promise<void> {
  if (activatingAbility.value || abilityActivated.value) {
    return;
  }
  activatingAbility.value = true;
  try {
    const response = await axios.post<{ peeked_cards?: unknown; remaining_uses?: number }>(
      `/api/games/${gameId}/use-ability`,
      {
        player_number: activePlayerNumber.value,
      },
    );
    abilityActivated.value = true;
    if (response.data.peeked_cards) {
      peekedCards.value = response.data.peeked_cards;
    }
    const player = gameData?.game?.players?.find((p) => p.player_number === activePlayerNumber.value);
    if (player) {
      player.ability_uses = response.data.remaining_uses;
    }
  } catch (error) {
    toast.error("Failed to use ability: " + errorMessage(error));
  }
  activatingAbility.value = false;
}

function getKingdomStyleForPlayer(playerNumber: number): string {
  const player = gameData?.game?.players?.find((p) => p.player_number === playerNumber);
  return player?.user?.active_kingdom_style_slug || "classic";
}

function getKingdomStyleDataForPlayer(playerNumber: number): KingdomStyleData | undefined {
  const player = gameData?.game?.players?.find((p) => p.player_number === playerNumber);
  const style = player?.user?.active_kingdom_style;
  if (!style || style.slug === undefined) {
    return undefined;
  }
  return {
    slug: style.slug,
    background_image_url: style.background_image_url,
    css_vars: style.css_vars,
  };
}

function getTitleForPlayer(playerNumber: number): string | undefined {
  const player = gameData?.game?.players?.find((p) => p.player_number === playerNumber);
  return player?.user?.active_title || undefined;
}

function getThemesForPlayer(playerNumber: number): string[] {
  const player = gameData?.game?.players?.find((p) => p.player_number === playerNumber);
  const slug = player?.user?.active_dice_theme_slug || "dddice-standard";
  return [slug, slug, slug, slug];
}

async function triggerDiceAnimation(rollResult: RollResult, playerNumber: number): Promise<void> {
  if (!dddiceAvailable.value) {
    return;
  }
  const themes = getThemesForPlayer(playerNumber);
  const timestamp = Date.now();

  return new Promise((resolve) => {
    let isResolved = false;
    const doResolve = (): void => {
      if (isResolved) {
        return;
      }
      isResolved = true;
      if (diceAnimationResolveReference.value?.timestamp === timestamp) {
        diceAnimationResolveReference.value = undefined;
      }
      resolve();
    };

    diceAnimationResolveReference.value = { resolve: doResolve, timestamp };

    // Safety timeout — always fires even if animation hangs or events are lost
    setTimeout(doResolve, 8000);

    diceAnimationTrigger.value = {
      playerNumber,
      rollResult,
      themes,
      timestamp,
    };
  });
}

function onDiceAnimationComplete({ timestamp }: { playerNumber: number; timestamp: number }): void {
  if (diceAnimationResolveReference.value?.timestamp === timestamp) {
    diceAnimationResolveReference.value.resolve();
  }
}

function applyRollResult(rollResult: RollResult): void {
  // Clear 3D dice immediately so they don't linger after outcome is shown
  const pn = rollResult.player_number || activePlayerNumber.value;
  kingdomStats.value?.clearDice(pn);

  // Per-roll micro-win feedback for the local player when the outcome reveals
  if (pn === activePlayerNumber.value && rollResult.cards?.length) {
    haptic(rollResult.cards[0].success ? "success" : "warning");
  }

  if (duelPhase.value === "rolling") {
    if (rollResult.player_number === offererNumber.value) {
      offererRollData.value = rollResult;
    } else {
      chooserRollData.value = rollResult;
    }
    refreshKingdoms();
    if (rollResult.duel_result) {
      isGameOver.value = true;
    }

    if (hasBotPlayer.value && !opponentRollData.value) {
      triggerBotRollImmediate();
    }
  } else {
    if (duelPhase.value === "rolling_offerer" || gameData?.game?.duel_phase === "rolling_offerer") {
      offererRollData.value = rollResult;
    } else {
      chooserRollData.value = rollResult;
    }
    refreshKingdoms();
  }

  // Update per-player roll results for kingdom stats display
  playerRollResults.value = { ...playerRollResults.value, [pn]: rollResult };

  // Extract difficulty for opponent from roll data if we don't have it yet
  if (rollResult.cards?.length && playerDifficulties.value[pn] === undefined) {
    const diff = rollResult.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
    if (diff > 0) {
      playerDifficulties.value = { ...playerDifficulties.value, [pn]: diff };
    }
  }

  pendingRerollDecision.value = true;

  // Update player items if included in roll result
  if (rollResult.player_items && pn === activePlayerNumber.value) {
    currentPlayerItems.value = rollResult.player_items;
  }

  // Process pending curses from roll result
  if (rollResult.pending_curses) {
    pendingCurses.value = rollResult.pending_curses;
  }
  if (rollResult.player_curses) {
    playerCurses.value = { ...playerCurses.value, [pn]: rollResult.player_curses };
  }
}

function currentDuelPendingCurse(): PendingCurse | undefined {
  if (!pendingCurses.value || pendingCurses.value.length === 0) {
    return undefined;
  }
  const player = gameData?.game?.players?.find((p) => p.player_number === activePlayerNumber.value);
  if (!player) {
    return undefined;
  }
  return pendingCurses.value.find((pc) => pc.player_id === player.id);
}

async function onCurseSelected(curseId: number): Promise<void> {
  try {
    const response = await axios.post<{ pending_curses?: PendingCurse[]; player_curses?: unknown }>(
      `/api/games/${gameId}/choose-curse`,
      {
        curse_id: curseId,
        player_number: activePlayerNumber.value,
      },
    );
    pendingCurses.value = response.data.pending_curses || undefined;
    if (response.data.player_curses) {
      playerCurses.value = {
        ...playerCurses.value,
        [activePlayerNumber.value]: response.data.player_curses,
      };
    }
    // When all curses resolved, continue the flow
    if (!pendingCurses.value || pendingCurses.value.length === 0) {
      showCurseSelection.value = false;
      continueAfterCurses();
    }
  } catch (error) {
    toast.error("Failed to choose curse: " + errorMessage(error));
  }
}

async function recoverRollState(): Promise<void> {
  // Fetch current game state to recover roll data that the backend already has
  try {
    const response = await axios.get<GameData>(`/api/games/${gameId}`);
    const data = response.data;
    syncFromGameData(data);
    emit("gameDataUpdated", data);

    // If the backend has moved to resolving, set that
    const phase = data.duel_phase || data.game?.duel_phase;
    if (phase) {
      duelPhase.value = phase;
    }

    // Recover roll results from round_results if available
    const roundResults = data.round_results || data.game?.round_results;
    if (roundResults) {
      if (roundResults.offerer && !offererRollData.value) {
        offererRollData.value = roundResults.offerer;
      }
      if (roundResults.chooser && !chooserRollData.value) {
        chooserRollData.value = roundResults.chooser;
      }
    }
  } catch (error) {
    console.error("Failed to recover roll state:", error);
    emit("refresh");
  }
}

async function handleUseItem(gamePlayerItemId: number): Promise<void> {
  try {
    const response = await axios.post<{ player_items?: PlayerItemData[] }>(`/api/games/${gameId}/use-item`, {
      game_player_item_id: gamePlayerItemId,
      player_number: activePlayerNumber.value,
    });
    itemDecided.value = true;
    if (response.data.player_items) {
      currentPlayerItems.value = response.data.player_items;
    }
  } catch (error) {
    toast.error("Failed to use item: " + errorMessage(error));
  }
}

async function handleSkipItem(): Promise<void> {
  try {
    await axios.post(`/api/games/${gameId}/skip-item`, {
      player_number: activePlayerNumber.value,
    });
    itemDecided.value = true;
  } catch (error) {
    toast.error("Failed to skip item: " + errorMessage(error));
  }
}

async function submitRoll(): Promise<void> {
  duelRolling.value = true;
  let rollResult: RollResult;
  try {
    const response = await axios.post<RollResult>(`/api/games/${gameId}/duel-roll`);
    rollResult = response.data;
  } catch (error) {
    if (
      isAxiosError<{ error?: string }>(error) &&
      error.response?.status === 422 &&
      error.response?.data?.error?.includes("already rolled")
    ) {
      // Player already rolled (e.g. page reload) — fetch existing state
      await recoverRollState();
      duelRolling.value = false;
      return;
    }
    toast.error("Failed to roll: " + errorMessage(error));
    duelRolling.value = false;
    return;
  }

  // Trigger 3D dice animation FIRST — results shown after dice stop
  try {
    await triggerDiceAnimation(rollResult, rollResult.player_number || activePlayerNumber.value);
  } catch (error) {
    console.warn("[dddice] Animation failed, continuing:", error);
  }

  // Now show results, play sound, and adjust stats
  applyRollResult(rollResult);
  duelRolling.value = false;
}

function advanceAfterOffererRoll(): void {
  duelPhase.value = "rolling_chooser";
  itemDecided.value = false; // Reset for chooser's item decision
  emit("phaseUpdated", "rolling_chooser");
  if (isPassAndPlay.value) {
    myCards.value = [];
    initiatePassAndPlayHandoff(chooserNumber.value);
  } else if (hasBotPlayer.value) {
    myCards.value = [];
  } else {
    updateOnlineWaiting();
  }
}

async function handleReroll(): Promise<void> {
  rerolling.value = true;
  let rollResult: RollResult;
  try {
    const response = await axios.post<RollResult>(`/api/games/${gameId}/duel-reroll`, {
      player_number: activePlayerNumber.value,
    });
    rollResult = response.data;
  } catch (error) {
    toast.error("Reroll failed: " + errorMessage(error));
    rerolling.value = false;
    return;
  }

  // Trigger 3D dice animation FIRST — results shown after dice stop
  try {
    await triggerDiceAnimation(rollResult, activePlayerNumber.value);
  } catch (error) {
    console.warn("[dddice] Reroll animation failed, continuing:", error);
  }

  // Now update roll data with rerolled results
  if (duelPhase.value === "rolling") {
    if (rollResult.player_number === offererNumber.value) {
      offererRollData.value = rollResult;
    } else {
      chooserRollData.value = rollResult;
    }
  } else if (duelPhase.value === "rolling_offerer" || gameData?.game?.duel_phase === "rolling_offerer") {
    offererRollData.value = rollResult;
  } else {
    chooserRollData.value = rollResult;
  }

  playerRollResults.value = { ...playerRollResults.value, [activePlayerNumber.value]: rollResult };

  const player = gameData?.game?.players?.find((p) => p.player_number === activePlayerNumber.value);
  if (player && rollResult.remaining_uses !== undefined) {
    player.ability_uses = rollResult.remaining_uses;
  }
  abilityActivated.value = true;

  await refreshKingdoms();
  if (rollResult.duel_result) {
    isGameOver.value = true;
  }

  rerolling.value = false;
}

function handleContinueAfterRoll(): void {
  pendingRerollDecision.value = false;

  // Show curse selection if there are pending curses before continuing
  if (pendingCurses.value && pendingCurses.value.length > 0) {
    showCurseSelection.value = true;
    return;
  }

  continueAfterCurses();
}

function continueAfterCurses(): void {
  if (duelPhase.value === "rolling") {
    if (offererRollData.value && chooserRollData.value) {
      // Both rolled — skip resolving, advance directly to next round
      if (offererRollData.value.duel_result || chooserRollData.value.duel_result) {
        isGameOver.value = true;
      }
      advanceRound();
    } else if (hasBotPlayer.value && !opponentRollData.value) {
      // Bot hasn't rolled yet — trigger immediately
      opponentTurnPending.value = false;
      triggerBotRollImmediate();
    }
    // If opponent hasn't rolled yet, stay in rolling phase
  } else if (duelPhase.value === "rolling_offerer" || gameData?.game?.duel_phase === "rolling_offerer") {
    // Reset ability state for chooser's turn
    abilityActivated.value = false;
    peekedCards.value = undefined;
    advanceAfterOffererRoll();
  } else if (duelPhase.value === "rolling_chooser" || duelPhase.value === "resolving") {
    // Chooser done — advance to next round
    if (chooserRollData.value?.duel_result || offererRollData.value?.duel_result) {
      isGameOver.value = true;
    }
    advanceRound();
  }
  // Ignore if already in 'choosing' or other non-rolling phase (stale callback)
}

async function refreshKingdoms(): Promise<void> {
  try {
    const response = await axios.get<{ player_kingdoms?: unknown[] }>(`/api/games/${gameId}`);
    playerKingdoms.value = response.data.player_kingdoms || [];
  } catch {
    // silent
  }
}

async function advanceRound(): Promise<void> {
  // Prevent concurrent advance calls
  if (advancing.value) {
    return;
  }
  advancing.value = true;
  try {
    const response = await axios.post<GameData & { game_over?: boolean; completion?: unknown }>(
      `/api/games/${gameId}/next-round`,
    );

    if (response.data.game_over) {
      if (response.data.completion) {
        sessionStorage.setItem(`game_completion_${gameId}`, JSON.stringify(response.data.completion));
      }
      emit("gameOver");
      return;
    }

    // Clear any lingering 3D dice
    kingdomStats.value?.clearDice();

    // Reset state for next round
    offererRollData.value = undefined;
    chooserRollData.value = undefined;
    myCards.value = [];
    currentCards.value = [];
    isGameOver.value = false;
    abilityActivated.value = false;
    peekedCards.value = undefined;
    pendingRerollDecision.value = false;
    rerolling.value = false;
    waitingForOpponentSelection.value = false;
    rollTab.value = "mine";
    playerDifficulties.value = {};
    playerRollResults.value = {};
    diceAnimationTrigger.value = undefined;
    opponentTurnPending.value = false;

    // Update from response
    syncFromGameData(response.data);
    emit("gameDataUpdated", response.data);

    if (isPassAndPlay.value) {
      // In pass-and-play, player 1 always picks first
      initiatePassAndPlayHandoff(1);
    } else if (hasBotPlayer.value) {
      await loadDuelHand();
    }
  } catch (error) {
    // In online duel, both players may try to advance simultaneously —
    // silently refresh state instead of showing error
    if (isOnline.value && isAxiosError(error) && error.response?.status === 422) {
      emit("refresh");
    } else {
      toast.error("Failed to advance: " + errorMessage(error));
      emit("refresh");
    }
  } finally {
    advancing.value = false;
  }
}

// Called from GameBoard when duel broadcasts are received
function handleDuelChoiceMade(data: DuelChoiceMadeData): void {
  duelPhase.value = data.duel_phase;
  waitingForOpponentSelection.value = false;
  loadMyRollCards();
  showWaiting.value = false;
  updateOnlineWaiting();

  // Compute difficulties for both players from swapped cards
  const d1 = computeDiff(data.player1_cards);
  const d2 = computeDiff(data.player2_cards);
  if (d1 > 0) {
    playerDifficulties.value = { ...playerDifficulties.value, 1: d1 };
  }
  if (d2 > 0) {
    playerDifficulties.value = { ...playerDifficulties.value, 2: d2 };
  }

  // Phase changed — re-sync timer from server
  if (isOnline.value && turnTimeLimit.value) {
    emit("refresh");
  }
}

async function handleDuelRollComplete(data: DuelRollCompleteData): Promise<void> {
  // Ignore stale broadcasts from previous rounds (we're already in card selection)
  if (duelPhase.value === "choosing" || duelPhase.value === "offering") {
    return;
  }

  const rollData = data.roll_data;
  const pn = rollData.player_number;

  // Skip if this is our own roll — already handled by submitRoll/applyRollResult
  if (pn === activePlayerNumber.value) {
    // Still update phase from broadcast
    const newPhase = data.duel_phase;
    if (newPhase && newPhase !== duelPhase.value) {
      duelPhase.value = newPhase;
    }
    showWaiting.value = false;
    updateOnlineWaiting();
    return;
  }

  // Set difficulty BEFORE animation so "Required: X" shows during roll
  if (pn !== undefined && rollData.cards?.length && playerDifficulties.value[pn] === undefined) {
    const diff = rollData.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
    if (diff > 0) {
      playerDifficulties.value = { ...playerDifficulties.value, [pn]: diff };
    }
  }

  // Trigger 3D dice animation FIRST — results shown after dice stop
  try {
    await triggerDiceAnimation(rollData, pn ?? activePlayerNumber.value);
  } catch (error) {
    console.warn("[dddice] Online opponent dice animation failed, continuing:", error);
  }
  kingdomStats.value?.clearDice(pn);

  // Now show results and adjust stats
  if (pn === offererNumber.value) {
    offererRollData.value = rollData;
  } else {
    chooserRollData.value = rollData;
  }
  if (pn !== undefined) {
    playerRollResults.value = { ...playerRollResults.value, [pn]: rollData };
  }

  if (rollData.duel_result) {
    isGameOver.value = true;
  }

  refreshKingdoms();

  if (duelPhase.value === "rolling" && offererRollData.value && chooserRollData.value && !pendingRerollDecision.value) {
    // Skip resolving — advance directly to next round
    advanceRound();
  } else if (duelPhase.value !== "rolling" || !pendingRerollDecision.value) {
    const newPhase = data.duel_phase;
    if (newPhase === "resolving") {
      advanceRound();
    } else {
      duelPhase.value = newPhase;
    }
  }

  showWaiting.value = false;

  // Sequential: if it's now my turn to roll
  if (duelPhase.value === "rolling_chooser" && activePlayerNumber.value === chooserNumber.value) {
    loadMyRollCards();
  }

  updateOnlineWaiting();
}

async function triggerBotRollImmediate(): Promise<void> {
  if (isOnline.value || !hasBotPlayer.value || opponentTurnPending.value) {
    return;
  }
  opponentTurnPending.value = true;
  try {
    const { data } = await axios.post<RollResult>(`/api/games/${gameId}/opponent-turn`);

    // Guard: if the round advanced while we were waiting, discard stale result
    if (duelPhase.value === "choosing" || duelPhase.value === "offering") {
      opponentTurnPending.value = false;
      return;
    }

    if (data.player_number !== undefined) {
      // Set difficulty BEFORE animation so "Required: X" is visible during roll
      if (data.cards?.length) {
        const diff = data.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
        if (diff > 0) {
          playerDifficulties.value = { ...playerDifficulties.value, [data.player_number]: diff };
        }
      }

      // Trigger 3D dice animation FIRST — results shown after dice stop
      try {
        await triggerDiceAnimation(data, data.player_number);
      } catch (error) {
        console.warn("[dddice] Bot roll animation failed, continuing:", error);
      }
      kingdomStats.value?.clearDice(data.player_number);

      // Now show results and adjust stats
      if (data.player_number === offererNumber.value) {
        offererRollData.value = data;
      } else {
        chooserRollData.value = data;
      }
      playerRollResults.value = { ...playerRollResults.value, [data.player_number]: data };

      if (data.duel_result) {
        isGameOver.value = true;
      }
      await refreshKingdoms();
    }
  } catch (error) {
    console.error("Bot roll failed:", error);
  }
  opponentTurnPending.value = false;
}

async function triggerBotTurn(): Promise<void> {
  if (isOnline.value || !hasBotPlayer.value || opponentTurnPending.value) {
    return;
  }
  opponentTurnPending.value = true;

  const delay = isOnline.value ? 3000 + Math.random() * 4000 : 1000 + Math.random() * 1000;
  await new Promise((r) => setTimeout(r, delay));

  try {
    const response = await axios.post<RollResult & { waiting?: boolean; duel_phase?: string }>(
      `/api/games/${gameId}/opponent-turn`,
    );
    const data = response.data;

    if (duelPhase.value === "choosing") {
      if (data.waiting === false) {
        // Both selected — transition to rolling
        const phase = data.duel_phase || "rolling_offerer";
        duelPhase.value = phase;
        waitingForOpponentSelection.value = false;
        emit("phaseUpdated", phase);
        await loadMyRollCards();

        // Bot may need to roll next (sequential only)
        // In simultaneous mode, bot rolls when human rolls (via triggerBotRollImmediate)
        if (phase !== "rolling" && isCurrentTurnBot.value) {
          opponentTurnPending.value = false;
          triggerBotTurn();
          return;
        }
      }
    } else if (data.player_number !== undefined && duelPhase.value !== "choosing" && duelPhase.value !== "offering") {
      // Set difficulty BEFORE animation so "Required: X" is visible during roll
      if (data.cards?.length) {
        const diff = data.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
        if (diff > 0) {
          playerDifficulties.value = { ...playerDifficulties.value, [data.player_number]: diff };
        }
      }

      // Trigger 3D dice animation FIRST — results shown after dice stop
      try {
        await triggerDiceAnimation(data, data.player_number);
      } catch (error) {
        console.warn("[dddice] Bot turn animation failed, continuing:", error);
      }
      kingdomStats.value?.clearDice(data.player_number);

      // Now show results and adjust stats
      if (data.player_number === offererNumber.value) {
        offererRollData.value = data;
      } else {
        chooserRollData.value = data;
      }
      playerRollResults.value = { ...playerRollResults.value, [data.player_number]: data };

      if (data.duel_result) {
        isGameOver.value = true;
      }
      await refreshKingdoms();

      const game = await axios.get<GameData>(`/api/games/${gameId}`);
      const newPhase = game.data.duel_phase || game.data.game?.duel_phase;
      emit("gameDataUpdated", game.data);

      // Don't auto-advance if human still reviewing their roll
      if (newPhase === "resolving" && pendingRerollDecision.value) {
        // Stay in rolling — will advance when human clicks Continue
      } else if (newPhase === "resolving") {
        // Skip resolving — advance directly to next round
        advanceRound();
      } else {
        duelPhase.value = newPhase;
      }

      const isMyChooserTurn = duelPhase.value === "rolling_chooser" && activePlayerNumber.value === chooserNumber.value;
      const isMyOffererTurn = duelPhase.value === "rolling_offerer" && activePlayerNumber.value === offererNumber.value;
      if (isMyChooserTurn || isMyOffererTurn) {
        await loadMyRollCards();
      }
    }
  } catch (error) {
    console.error("Bot turn failed:", error);
  }

  opponentTurnPending.value = false;
}

function handleNextRoundStarted(data: GameData): void {
  offererRollData.value = undefined;
  chooserRollData.value = undefined;
  myCards.value = [];
  currentCards.value = [];
  isGameOver.value = false;
  abilityActivated.value = false;
  peekedCards.value = undefined;
  pendingRerollDecision.value = false;
  rerolling.value = false;
  waitingForOpponentSelection.value = false;
  rollTab.value = "mine";
  playerDifficulties.value = {};
  playerRollResults.value = {};
  diceAnimationTrigger.value = undefined;
  itemDecided.value = false;
  showCurseSelection.value = false;
  opponentTurnPending.value = false;
  syncFromGameData(data);
  emit("gameDataUpdated", data);
}

onMounted(async () => {
  // Ensure kingdoms are loaded
  if (playerKingdoms.value.length === 0) {
    await refreshKingdoms();
  }

  // Initial card load
  switch (duelPhase.value) {
  case "choosing": {
    if (isPassAndPlay.value) {
      initiatePassAndPlayHandoff(1);
    } else {
      await loadDuelHand();
      if (isCurrentTurnBot.value) {
        triggerBotTurn();
      }
    }
  
  break;
  }
  case "rolling": {
    await loadMyRollCards();
    updateOnlineWaiting();
    // Bot rolls simultaneously when human rolls (triggerBotRollImmediate in submitRoll)
  
  break;
  }
  case "rolling_offerer": 
  case "rolling_chooser": {
    if (isPassAndPlay.value) {
      const activeNumber = duelPhase.value === "rolling_offerer" ? offererNumber.value : chooserNumber.value;
      initiatePassAndPlayHandoff(activeNumber);
    } else {
      await loadMyRollCards();
      updateOnlineWaiting();
      if (isCurrentTurnBot.value) {
        triggerBotTurn();
      }
    }
  
  break;
  }
  case "resolving": {
    // Page refreshed into resolving — check for pending curses first
    if (pendingCurses.value && pendingCurses.value.length > 0) {
      showCurseSelection.value = true;
    } else {
      advanceRound();
    }
  
  break;
  }
  // No default
  }
});

onBeforeUnmount(() => {
  if (turnTimerInterval.value) {
    clearInterval(turnTimerInterval.value);
  }
  if (timeoutSafetyTimer.value) {
    clearTimeout(timeoutSafetyTimer.value);
  }
});

defineExpose({
  activePlayerNumber,
  playerItems,
  openCharacterModal,
  handleDuelChoiceMade,
  handleDuelRollComplete,
  handleNextRoundStarted,
});
</script>


<style scoped>
.duel-board {
  position: relative;
}

.waiting-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 900;
}

.waiting-content {
  text-align: center;
  padding: 40px 30px;
}

.waiting-ornament {
  font-size: 3rem;
  color: var(--accent-gold);
  opacity: 0.5;
  margin-bottom: 16px;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 0.7; }
}

.waiting-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
  margin-bottom: 8px;
}

.waiting-sub {
  color: var(--text-secondary);
  font-style: italic;
}

.waiting-inline {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 1.1rem;
}

/* Timeout overlay */
.timeout-overlay {
  z-index: 950;
}

.timeout-icon {
  color: #e74c3c;
  font-size: 4rem;
  opacity: 1;
  animation: none;
}

.timeout-title {
  color: #e74c3c;
  font-size: 1.8rem;
}

/* Continue button above kingdom stats */
.continue-above-stats {
  text-align: center;
  margin-bottom: 10px;
}

.btn-continue-top {
  background: rgba(212, 168, 67, 0.15);
  border: 2px solid var(--accent-gold);
  color: var(--accent-gold);
  padding: 10px 40px;
  border-radius: 8px;
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-continue-top:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.25);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.3);
}

.btn-continue-top:disabled { opacity: 0.5; cursor: not-allowed; }

/* Reroll option above stats */
.reroll-above {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.btn-reroll-top {
  background: linear-gradient(135deg, #2a1a40, #4a2a6a);
  color: #d4a0ff;
  border: 2px solid #8a60c0;
  padding: 10px 20px;
  border-radius: 8px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
  line-height: 1.4;
  width: 100%;
  max-width: 340px;
  text-align: center;
}

.btn-reroll-top:hover:not(:disabled) {
  background: linear-gradient(135deg, #3a2a50, #5a3a7a);
  box-shadow: 0 0 15px rgba(138, 96, 192, 0.4);
  border-color: #b080e0;
}

.btn-reroll-top:disabled { opacity: 0.5; cursor: not-allowed; }

.reroll-above .ability-uses-badge {
  font-size: 0.7rem;
  opacity: 0.7;
}


.btn-roll.action-btn-top {
  display: block;
  margin: 0 auto 12px;
  padding: 10px 36px;
  font-size: 1rem;
  color: #f5e6cc;
  border: 2px solid #d4a843;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: 1px;
  text-transform: uppercase;
  transition: all 0.2s ease;
}

.btn-roll.action-btn-top:hover {
  background: linear-gradient(135deg, #b83030, #8b2020);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.4);
}

/* Roll Tab Nav */
.duel-roll-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 8px;
  justify-content: center;
}

.roll-tab {
  flex: 1;
  max-width: 180px;
  padding: 5px 12px !important;
  border-radius: 6px !important;
  font-family: 'Crimson Text',serif;
  font-size: 0.7rem !important;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  background: rgba(0, 0, 0, 0.2);
  border: 2px solid rgba(138, 106, 46, 0.3);
  color: var(--text-secondary);
  text-align: center;
}

.roll-tab.active {
    background: var(--accent-gold);
    color: #FFF;
}

.roll-tab.tab-success {
  border-color: #4a8a3a;
  color: #5ea84a;
}

.roll-tab.tab-success.active {
    background: #4a8a3a;
    color: #FFF;
}

.roll-tab.tab-failure {
  border-color: #a03020;
  color: #c0392b;
}

.roll-tab.tab-failure.active {
    background: #a03020;
    color: #FFF;
}

.roll-tab.tab-waiting:not(.active) {
  opacity: 0.6;
  animation: tabPulse 2s ease-in-out infinite;
}

@keyframes tabPulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.8; }
}

/* Turn timer */
.turn-timer {
  position: absolute;
  top: 8px;
  right: 8px;
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  font-weight: 700;
  color: var(--accent-gold);
  background: rgba(0, 0, 0, 0.6);
  border: 2px solid var(--border-gold);
  border-radius: 6px;
  padding: 4px 10px;
  z-index: 10;
}

.turn-timer.urgent {
  color: #e74c3c;
  border-color: #e74c3c;
  animation: timerPulse 1s ease-in-out infinite;
}

@keyframes timerPulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>
