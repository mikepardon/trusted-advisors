<template>
    <div v-if="loading" class="loading">Loading game state...</div>
    <div v-else-if="gameData" class="game-board">
        <!-- Connection status banner -->
        <div
            v-if="isOnline && connectionStatus !== 'connected'"
            class="connection-banner"
            :class="connectionStatus"
        >
            <span v-if="connectionStatus === 'connecting'"
                >Reconnecting...</span
            >
            <span v-else>Connection lost. Trying to reconnect...</span>
        </div>
        <!-- ONLINE LOBBY (setup phase) -->
        <template v-if="isOnline && gameData.game.status === 'setup'">
            <OnlineLobby
                ref="lobby"
                :game-id="id"
                :host-id="gameData.game.user_id"
                :game-type="gameData.game.game_type || 'cooperative'"
                :turn-time-limit="gameData.game.turn_time_limit || 0"
                @start-game="startOnlineGame"
                @lobby-updated="fetchGame"
                @leave="goHome"
            />
        </template>

        <template v-else>
            <!-- 3D Dice Overlay (cooperative only — duel uses per-player canvases) -->
            <DiceOverlay v-if="!isDuel" ref="diceOverlay" />

            <!-- DUEL MODE -->
            <template v-if="isDuel">
                <div class="time-bar">
                    <div class="time-bar-right" style="width: unset">
                        <div class="time-bar-icons">
                            <button
                                class="bar-icon-btn"
                                title="View Inventory"
                                @click="openDuelInventory()"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    class="bar-icon-svg"
                                >
                                    <path
                                        d="M20 7H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1Z"
                                    />
                                    <path
                                        d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"
                                    />
                                </svg>
                                <span class="bar-icon-badge">{{
                                    duelItemCount
                                }}</span>
                            </button>
                            <button
                                class="bar-icon-btn"
                                title="View Your Dice"
                                @click="showDuelDiceViewer"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    class="bar-icon-svg"
                                >
                                    <rect
                                        x="3"
                                        y="3"
                                        width="18"
                                        height="18"
                                        rx="3"
                                    />
                                    <circle
                                        cx="8.5"
                                        cy="8.5"
                                        r="1"
                                        fill="currentColor"
                                    />
                                    <circle
                                        cx="15.5"
                                        cy="8.5"
                                        r="1"
                                        fill="currentColor"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="1"
                                        fill="currentColor"
                                    />
                                    <circle
                                        cx="8.5"
                                        cy="15.5"
                                        r="1"
                                        fill="currentColor"
                                    />
                                    <circle
                                        cx="15.5"
                                        cy="15.5"
                                        r="1"
                                        fill="currentColor"
                                    />
                                </svg>
                                <span class="bar-icon-badge">{{
                                    duelDiceCount
                                }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="time-info">
                        <span class="round-label"
                            >Month {{ gameData.current_round }}/{{
                                gameData.total_rounds
                            }}</span
                        >
                    </div>
                    <button
                        class="burger-btn"
                        @click.stop="showGameMenu = !showGameMenu"
                    >
                        <svg
                            width="15"
                            height="15"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            style="display: block; min-width: 15px"
                        >
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                </div>
                <DuelBoard
                    ref="duelBoard"
                    :game-data="gameData"
                    :game-id="id"
                    @refresh="fetchGame"
                    @game-data-updated="onGameDataUpdated"
                    @phase-updated="onPhaseUpdated"
                    @game-over="onDuelGameOver"
                    @items-updated="(count) => (duelItemCount = count)"
                    @dice-updated="(count) => (duelDiceCount = count)"
                />
            </template>

            <!-- COOPERATIVE MODE -->
            <template v-else>
                <!-- Board header (flush, war-table style) -->
                <div class="board-header">
                    <button
                        class="board-menu-btn"
                        aria-label="Menu"
                        @click.stop="showGameMenu = !showGameMenu"
                    >
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        >
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <div class="board-heading">
                        <div class="board-title">
                            {{ isSinglePlayer ? "Solo Reign" : "Shared Reign" }}
                        </div>
                        <div class="board-sub">
                            vs the realm &middot; {{ diceCount }} dice in hand
                        </div>
                    </div>
                    <span class="board-month-pill"
                        >MONTH {{ gameData.current_round }}</span
                    >
                </div>

                <!-- Board tools (inventory / dice / curses) -->
                <div class="board-tools">
                    <button
                        class="bar-icon-btn"
                        title="View Inventory"
                        @click="openInventory()"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="bar-icon-svg"
                        >
                            <path
                                d="M20 7H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1Z"
                            />
                            <path
                                d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"
                            />
                        </svg>
                        <span class="bar-icon-badge">{{
                            usableItems.length
                        }}</span>
                    </button>
                    <button
                        class="bar-icon-btn"
                        title="View Your Dice"
                        @click="showDiceViewer = true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="bar-icon-svg"
                        >
                            <rect x="3" y="3" width="18" height="18" rx="3" />
                            <circle
                                cx="8.5"
                                cy="8.5"
                                r="1"
                                fill="currentColor"
                            />
                            <circle
                                cx="15.5"
                                cy="8.5"
                                r="1"
                                fill="currentColor"
                            />
                            <circle cx="12" cy="12" r="1" fill="currentColor" />
                            <circle
                                cx="8.5"
                                cy="15.5"
                                r="1"
                                fill="currentColor"
                            />
                            <circle
                                cx="15.5"
                                cy="15.5"
                                r="1"
                                fill="currentColor"
                            />
                        </svg>
                        <span class="bar-icon-badge">{{ diceCount }}</span>
                    </button>
                    <button
                        v-if="activeCurseCount > 0"
                        class="bar-icon-btn bar-icon-curse"
                        title="View Curses"
                        @click="showCurseDetails = true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="bar-icon-svg"
                        >
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"
                            />
                            <path d="M12 8v4M12 16h.01" />
                        </svg>
                        <span class="bar-icon-badge bar-icon-badge-curse">{{
                            activeCurseCount
                        }}</span>
                    </button>
                </div>

                <!-- Kingdom Stats -->
                <KingdomStats
                    :game="displayGame"
                    :kingdom-style-slug="myKingdomStyleSlug"
                    :kingdom-style-data="myKingdomStyleData"
                    :preview-effects="cardPreviewEffects"
                />

                <!-- Player Items (overlay only, button in top bar) -->
                <PlayerItems
                    ref="playerItems"
                    :items="currentPlayerItems"
                    :show-button="false"
                    :current-round="gameData.current_round || 0"
                />

                <!-- Active Curses Overlay -->
                <teleport to="body">
                    <transition name="curse-viewer">
                        <div
                            v-if="showCurseDetails && activeCurseCount > 0"
                            class="curse-viewer-overlay"
                            @click.self="showCurseDetails = false"
                        >
                            <div class="curse-viewer-container">
                                <button
                                    class="curse-viewer-close"
                                    @click="showCurseDetails = false"
                                >
                                    &times;
                                </button>

                                <div class="curse-viewer-header">
                                    <h2 class="curse-viewer-title">
                                        Active Curses
                                    </h2>
                                    <p class="curse-viewer-subtitle">
                                        {{ activeCurseCount }} curse{{
                                            activeCurseCount > 1 ? "s" : ""
                                        }}
                                        afflicting the kingdom
                                    </p>
                                </div>

                                <div class="curse-viewer-cards">
                                    <template
                                        v-for="(curses, pn) in playerCurses"
                                        :key="pn"
                                    >
                                        <div
                                            v-for="c in curses"
                                            :key="c.id"
                                            class="curse-viewer-parchment"
                                        >
                                            <img
                                                v-if="c.curse?.image_path"
                                                :src="`/api/storage/${c.curse.image_path}`"
                                                class="curse-viewer-image"
                                            />
                                            <div
                                                v-else
                                                class="curse-viewer-icon"
                                            >
                                                &#9760;
                                            </div>

                                            <h3 class="curse-viewer-name">
                                                {{
                                                    c.curse?.name ||
                                                    "Unknown Curse"
                                                }}
                                            </h3>
                                            <p
                                                v-if="c.curse?.description"
                                                class="curse-viewer-flavor"
                                            >
                                                {{ c.curse.description }}
                                            </p>

                                            <div class="curse-viewer-divider">
                                                <span
                                                    class="curse-viewer-divider-gem"
                                                    >&#9830;</span
                                                >
                                            </div>

                                            <div
                                                class="curse-viewer-effect curse-viewer-penalty"
                                            >
                                                <span
                                                    class="curse-viewer-effect-icon"
                                                    >&#9888;</span
                                                >
                                                <div>
                                                    <span
                                                        class="curse-viewer-effect-label"
                                                        >Penalty</span
                                                    >
                                                    <p
                                                        class="curse-viewer-effect-desc"
                                                    >
                                                        {{
                                                            describeCurseEffect(
                                                                c.curse
                                                                    ?.negative_effect,
                                                                "negative",
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                class="curse-viewer-effect curse-viewer-reward"
                                            >
                                                <span
                                                    class="curse-viewer-effect-icon"
                                                    >&#10022;</span
                                                >
                                                <div>
                                                    <span
                                                        class="curse-viewer-effect-label"
                                                        >Reward</span
                                                    >
                                                    <p
                                                        class="curse-viewer-effect-desc"
                                                    >
                                                        {{
                                                            describeCurseEffect(
                                                                c.curse
                                                                    ?.positive_effect,
                                                                "positive",
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="curse-viewer-meta">
                                                <span
                                                    class="curse-viewer-player"
                                                    >{{
                                                        getPlayerNameForCurse(
                                                            pn,
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    v-if="c.acquired_round"
                                                    class="curse-viewer-round"
                                                    >Round
                                                    {{ c.acquired_round }}</span
                                                >
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </transition>
                </teleport>

                <!-- Event Banner -->
                <EventBanner :event="gameData.current_event" />

                <!-- Event Reveal Overlay -->
                <EventReveal
                    v-if="showEventReveal && gameData.current_event"
                    :event="gameData.current_event"
                    @dismiss="showEventReveal = false"
                />

                <!-- Item Reveal Overlay -->
                <ItemReveal
                    v-if="showItemReveal && itemRevealQueue.length > 0"
                    :item="itemRevealQueue[0]"
                    @dismiss="onItemRevealDismiss"
                />

                <!-- Curse Selection Overlay -->
                <CurseSelectionOverlay
                    v-if="showCurseSelection && currentPendingCurse()"
                    :curses="currentPendingCurse().curse_details"
                    :player-name="
                        gameData?.game?.players?.find(
                            (p) => p.player_number === activePlayerNumber,
                        )?.character?.name || 'Player'
                    "
                    :is-duel="false"
                    @selected="onCurseSelected"
                />

                <!-- Item Discard Overlay (when over 2-item limit) -->
                <ItemDiscard
                    v-if="itemsOverLimit.length > 0"
                    :game-id="id"
                    :player-data="itemsOverLimit[0]"
                    @discarded="onItemDiscarded"
                />

                <!-- TURN HANDOFF OVERLAY (pass and play) -->
                <TurnHandoffOverlay
                    v-if="showTurnHandoff"
                    :player-number="turnHandoffPlayerNumber"
                    :character-name="turnHandoffCharacterName"
                    @ready="onHandoffReady"
                />

                <!-- SELECTING PHASE -->
                <template
                    v-if="
                        gameData.round_phase === 'selecting' && !showTurnHandoff
                    "
                >
                    <!-- Online waiting overlay -->
                    <WaitingOverlay
                        v-if="isOnline && waitingForOthers"
                        :player-status="gameData.player_status || []"
                    />

                    <template v-else>
                        <CardSelectionHand
                            v-if="isSinglePlayer || !currentPlayerHasAssigned"
                            :cards="currentHand"
                            :has-assigned="currentPlayerHasAssigned"
                            :loading="handLoading"
                            :redraws="cardRedraws"
                            :show-previews="showPreviews"
                            :single-player="isSinglePlayer"
                            :resolve-data="singlePlayerResolveData"
                            @assign="assignRoles"
                            @preview="onCardPreview"
                            @redraw="onCardRedraw"
                            @continue="onSinglePlayerContinue"
                            @resolve-shown="currentStatPhase = 'negative'"
                        />
                    </template>

                    <!-- Item Usage Phase (after all assigned, before rolling) -->
                    <div
                        v-if="gameData.all_assigned && !allItemsDecided"
                        class="item-phase"
                    >
                        <template v-if="showItemPrompt">
                            <div class="item-prompt-card">
                                <p class="item-prompt-title">
                                    {{ currentPlayerName }}'s Preparation
                                </p>
                                <p
                                    v-if="!itemDeciding"
                                    class="item-prompt-text"
                                >
                                    Do you want to use an item before the roll?
                                </p>
                                <div
                                    v-if="!itemDeciding"
                                    class="item-prompt-buttons"
                                >
                                    <button
                                        class="btn-primary"
                                        @click="itemDeciding = true"
                                    >
                                        Use Item
                                    </button>
                                    <button
                                        class="btn-secondary"
                                        :disabled="usingItem"
                                        @click="skipItem"
                                    >
                                        No, skip
                                    </button>
                                </div>
                                <div v-else class="item-selection">
                                    <p class="item-select-label">
                                        Choose an item to use:
                                    </p>
                                    <div
                                        v-for="pi in usableItems"
                                        :key="pi.id"
                                        class="item-use-card"
                                        @click="useItem(pi.id)"
                                    >
                                        <span class="item-use-name">{{
                                            pi.item?.name
                                        }}</span>
                                        <span class="item-use-effect">{{
                                            itemEffectLabel(pi.item)
                                        }}</span>
                                    </div>
                                    <button
                                        class="btn-secondary"
                                        @click="itemDeciding = false"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template v-else-if="isOnline">
                            <div class="resolve-prompt">
                                <p>
                                    Waiting for all players to decide on
                                    items...
                                </p>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Roll Dice Button (multi-player only — single player auto-resolves) -->
                <div
                    v-if="
                        gameData.round_phase === 'selecting' &&
                        gameData.all_assigned &&
                        allItemsDecided &&
                        !isSinglePlayer
                    "
                    class="resolve-prompt"
                >
                    <p>The council is ready!</p>
                    <button
                        class="btn-primary"
                        :disabled="resolving"
                        @click="resolveRound"
                    >
                        {{ resolving ? "Rolling..." : "Roll Dice" }}
                    </button>
                </div>

                <!-- RESOLVING PHASE -->
                <template v-if="gameData.round_phase === 'resolving'">
                    <RoundResults
                        :round="gameData.current_round"
                        :total-rounds="gameData.total_rounds"
                        :positive-phase="positivePhase"
                        :negative-phase="negativePhase"
                        :combined-effects="combinedEffects"
                        :event-effects="eventEffects"
                        :special-effects="specialEffects"
                        :game-over="isGameOver"
                        :can-advance="!isOnline || isHost"
                        :players="gameData.game?.players"
                        :resumed="resolveResumed"
                        @phase-complete="onPhaseComplete"
                        @next-round="advanceRound"
                    />
                </template>
            </template>
        </template>

        <!-- Game Burger Menu Overlay -->
        <div
            v-if="showGameMenu"
            class="game-menu-overlay"
            @click.self="showGameMenu = false"
        >
            <div class="mobile-menu-panel">
                <button
                    class="mobile-menu-item"
                    @click="
                        showGameMenu = false;
                        openRules();
                    "
                >
                    Rules
                </button>
                <button
                    class="mobile-menu-item"
                    @click="
                        showGameMenu = false;
                        openTutorial();
                    "
                >
                    Tutorial
                </button>
                <button
                    class="mobile-menu-item"
                    @click="
                        showGameMenu = false;
                        showSettingsModal = true;
                    "
                >
                    Settings
                </button>
                <button
                    v-if="isDaily"
                    class="mobile-menu-item game-menu-quit"
                    @click="
                        showGameMenu = false;
                        showQuitDailyConfirm = true;
                    "
                >
                    Quit Trial
                </button>
                <button
                    v-if="!isOnline || !isDuel"
                    class="mobile-menu-item game-menu-leave"
                    @click="
                        showGameMenu = false;
                        goHome();
                    "
                >
                    Go Home
                </button>
                <button
                    v-if="isOnline && isDuel"
                    class="mobile-menu-item game-menu-quit"
                    @click="
                        showGameMenu = false;
                        showQuitConfirm = true;
                    "
                >
                    Resign
                </button>
            </div>
        </div>

        <!-- Settings Modal -->
        <SettingsModal
            :visible="showSettingsModal"
            @close="showSettingsModal = false"
        />

        <!-- Quit Game Confirmation -->
        <ConfirmModal
            :visible="showQuitConfirm"
            title="Resign"
            message="Leaving now counts as a resignation. Your opponent wins and your ELO will be affected."
            confirm-text="Resign"
            :dangerous="true"
            @confirm="forfeitGame"
            @cancel="showQuitConfirm = false"
        />

        <!-- Quit Daily Trial Confirmation -->
        <ConfirmModal
            :visible="showQuitDailyConfirm"
            title="Quit Trial"
            message="Abandoning this trial records it as a loss for today. You won't be able to retry until the next challenge."
            confirm-text="Quit Trial"
            :dangerous="true"
            @confirm="quitDailyChallenge"
            @cancel="showQuitDailyConfirm = false"
        />

        <!-- Dice Viewer Modal -->
        <div
            v-if="showDiceViewer && characterDice"
            class="dice-viewer-overlay"
            @click.self="showDiceViewer = false"
        >
            <div class="dice-viewer-modal">
                <h3 class="dice-viewer-title">Your Dice</h3>
                <div
                    v-for="(die, i) in characterDice"
                    :key="i"
                    class="dice-viewer-row"
                >
                    <span class="dice-viewer-label">Die {{ i + 1 }}</span>
                    <div class="dice-viewer-faces">
                        <span
                            v-for="(face, j) in die"
                            :key="j"
                            class="dice-viewer-face"
                            :class="{
                                'dice-viewer-face-wild': face === 'WILD',
                            }"
                        >
                            {{ face === "WILD" ? "W" : face }}
                        </span>
                    </div>
                </div>
                <p v-if="characterWildValue" class="dice-viewer-wild-info">
                    WILD = {{ characterWildValue
                    }}{{
                        characterWildAbility
                            ? " + triggers " + characterWildAbility
                            : ""
                    }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    computed,
    inject,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
} from "vue";
import axios, { isAxiosError } from "axios";
import { useRouter } from "vue-router";
import KingdomStats from "./KingdomStats.vue";
import EventBanner from "./EventBanner.vue";
import CardSelectionHand from "./CardSelectionHand.vue";
import RoundResults from "./RoundResults.vue";
import TurnHandoffOverlay from "./TurnHandoffOverlay.vue";
import OnlineLobby from "./OnlineLobby.vue";
import WaitingOverlay from "./WaitingOverlay.vue";
import DuelBoard from "./DuelBoard.vue";
import PlayerItems from "./PlayerItems.vue";
import EventReveal from "./EventReveal.vue";
import ItemReveal from "./ItemReveal.vue";
import ItemDiscard from "./ItemDiscard.vue";
import DiceOverlay from "./DiceOverlay.vue";
import CurseSelectionOverlay from "./CurseSelectionOverlay.vue";
import ConfirmModal from "./ConfirmModal.vue";
import SettingsModal from "./SettingsModal.vue";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";

interface Character {
    name?: string;
}

interface CurseEffect {
    type?: string;
    value?: number;
    stat?: string;
    count?: number;
    rounds?: number;
}

interface Curse {
    name?: string;
    description?: string;
    image_path?: string;
    negative_effect?: CurseEffect;
    positive_effect?: CurseEffect;
}

interface PlayerCurse {
    id: number;
    acquired_round?: number;
    curse?: Curse;
}

interface KingdomStyle {
    slug?: string;
}

interface GamePlayerUser {
    id: number;
    active_kingdom_style_slug?: string;
    active_kingdom_style?: KingdomStyle;
}

interface GamePlayer {
    id: number;
    player_number: number;
    user_id?: number;
    user?: GamePlayerUser;
    character?: Character;
}

interface GameState {
    user_id?: number;
    status?: string;
    game_type?: string;
    duel_phase?: string;
    current_round?: number;
    turn_time_limit?: number;
    is_daily?: boolean;
    players?: GamePlayer[];
    [key: string]: unknown;
}

interface PlayerStatus {
    player_number: number;
    character_name?: string;
    has_assigned?: boolean;
    has_items?: boolean;
    item_decided?: boolean;
}

interface ItemEffect {
    bonus_type?: string;
    bonus_value?: number;
    stat?: string;
}

interface Item {
    name?: string;
    description?: string;
    effect?: ItemEffect;
}

interface PlayerItem {
    id: number;
    is_used?: boolean;
    used_round?: number;
    item?: Item;
}

interface HandCard {
    hand_id: number;
    card?: Record<string, unknown>;
}

interface PendingCurse {
    player_id: number;
    curse_details?: unknown;
}

interface SpecialEffect {
    type?: string;
    item?: unknown;
}

interface PhaseResult {
    cards?: unknown[];
    total_difficulty?: number;
    total_roll?: number;
    dice_results?: unknown[];
    wild_triggers?: unknown[];
    ability_effects?: unknown[];
    success?: boolean;
    effects?: Record<string, unknown>;
}

interface RoundResult {
    success?: boolean;
    stat_totals?: { total_difficulty?: number; total_roll?: number };
    dice_results?: unknown[];
    wild_triggers?: unknown[];
    effects_applied?: Record<string, unknown>;
}

interface SinglePlayerResolveData {
    positivePhase: PhaseResult;
    negativePhase: PhaseResult;
    combinedEffects: Record<string, unknown>;
    eventEffects: Record<string, unknown>;
    specialEffects: SpecialEffect[];
    gameOver: boolean;
}

interface GameEvent {
    id?: number;
}

interface GameData {
    game: GameState;
    game_mode?: string;
    game_type?: string;
    round_phase?: string;
    duel_phase?: string;
    current_round?: number;
    total_rounds?: number;
    current_event?: GameEvent;
    all_assigned?: boolean;
    all_items_decided?: boolean;
    player_status?: PlayerStatus[];
    pending_curses?: PendingCurse[];
    player_curses?: Record<number, PlayerCurse[]>;
    round_results?: RoundResult[];
    [key: string]: unknown;
}

interface ResolveResponse {
    positive_phase: PhaseResult;
    negative_phase: PhaseResult;
    combined_effects: Record<string, unknown>;
    event_effects: Record<string, unknown>;
    special_effects?: SpecialEffect[];
    game_over: boolean;
    game_after_positive?: Record<string, unknown>;
    game_after_negative?: Record<string, unknown>;
    game: GameState;
    player_items?: Record<number, PlayerItem[]>;
    items_over_limit?: unknown[];
    pending_curses?: PendingCurse[];
    player_curses?: Record<number, PlayerCurse[]>;
}

interface EchoChannel {
    listen: (event: string, callback: (data: never) => void) => EchoChannel;
}

interface PusherConnection {
    bind: (
        event: string,
        callback: (states: { current: string; previous: string }) => void,
    ) => void;
}

interface EchoInstance {
    private: (channel: string) => EchoChannel;
    leave: (channel: string) => void;
    connector?: { pusher?: { connection: PusherConnection } };
}

const { id } = defineProps<{
    id: string | number;
}>();

const router = useRouter();
const auth = useAuth();
const toast = useToast();

function noop(): void {
    // Fallback when the parent does not provide the handler.
}

const openRules = inject<() => void>("openRules", noop);
const openTutorial = inject<() => void>("openTutorial", noop);
const setActiveGameType = inject<(type: string | undefined) => void>(
    "setActiveGameType",
    noop,
);

const lobby = useTemplateRef("lobby");
const duelBoard = useTemplateRef("duelBoard");
const playerItems = useTemplateRef("playerItems");

const gameData = ref<GameData | undefined>(undefined);
const loading = ref(true);
const activePlayerNumber = ref(1);
const currentHand = ref<HandCard[]>([]);
const handLoading = ref(false);
const resolving = ref(false);
const positivePhase = ref<PhaseResult>({});
const negativePhase = ref<PhaseResult>({});
const combinedEffects = ref<Record<string, unknown>>({});
const eventEffects = ref<Record<string, unknown>>({});
const specialEffects = ref<SpecialEffect[]>([]);
const isGameOver = ref(false);
const gameAfterPositive = ref<Record<string, unknown> | undefined>(undefined);
const gameAfterNegative = ref<Record<string, unknown> | undefined>(undefined);
const preResolveGame = ref<Record<string, unknown> | undefined>(undefined); // snapshot of game stats before resolution
const currentStatPhase = ref<"positive" | "negative" | undefined>(undefined); // undefined, 'positive', or 'negative'
const showTurnHandoff = ref(false);
const turnHandoffPlayerNumber = ref<number | undefined>(undefined);
// Items & Dice
const currentPlayerItems = ref<PlayerItem[]>([]);
const diceCount = ref(3);
const duelItemCount = ref(0);
const duelDiceCount = ref(4);
const showDiceViewer = ref(false);
const characterDice = ref<string[][] | undefined>(undefined);
const cardRedraws = ref(0);
const characterWildValue = ref<number | undefined>(undefined);
const characterWildAbility = ref<string | undefined>(undefined);
// Event reveal
const showEventReveal = ref(false);
// Resolve restoration
const resolveResumed = ref(false);
// Item reveal
const itemRevealQueue = ref<unknown[]>([]);
const showItemReveal = ref(false);
// Connection monitoring
const connectionStatus = ref("connected");
// Item discard (when over 2-item limit)
const itemsOverLimit = ref<unknown[]>([]);
// Item phase
const itemDeciding = ref(false);
const usingItem = ref(false);
const allItemsDecided = ref(false);
// Online mode
const myPlayerNumber = ref<number | undefined>(undefined);
const waitingForOthers = ref(false);
const echoChannel = ref<EchoChannel | undefined>(undefined);
// Curses
const pendingCurses = ref<PendingCurse[] | undefined>(undefined);
const playerCurses = ref<Record<number, PlayerCurse[]>>({});
const showCurseDetails = ref(false);
const showCurseSelection = ref(false);
// Card effect preview
const cardPreviewEffects = ref<unknown>(undefined);
// Seer's Lens curse: show stat preview bars
const showPreviews = ref(false);
// Burger menu
const showGameMenu = ref(false);
const showSettingsModal = ref(false);
const showQuitConfirm = ref(false);
const showQuitDailyConfirm = ref(false);
// Single-player inline results
const singlePlayerResolveData = ref<SinglePlayerResolveData | undefined>(
    undefined,
);
// Concurrency guard for advanceRound (non-reactive orchestration flag)
const advancing = ref(false);

function getEcho(): EchoInstance | undefined {
    return (window as unknown as { Echo?: EchoInstance }).Echo;
}

function errorMessage(error: unknown): string {
    if (isAxiosError<{ message?: string; error?: string }>(error)) {
        return (
            error.response?.data?.error ||
            error.response?.data?.message ||
            error.message
        );
    }
    if (error instanceof Error) {
        return error.message;
    }
    return String(error);
}

const activeCurseCount = computed<number>(() => {
    let count = 0;
    for (const pn in playerCurses.value) {
        const curses = playerCurses.value[pn];
        if (Array.isArray(curses)) {
            count += curses.length;
        }
    }
    return count;
});

const currentPlayerHasAssigned = computed<boolean>(() => {
    if (!gameData.value?.player_status) {
        return false;
    }
    const ps = gameData.value.player_status.find(
        (p) => p.player_number === activePlayerNumber.value,
    );
    return ps?.has_assigned || false;
});

const currentPlayerName = computed<string>(() => {
    if (!gameData.value?.player_status) {
        return "Player";
    }
    const ps = gameData.value.player_status.find(
        (p) => p.player_number === activePlayerNumber.value,
    );
    return ps?.character_name || `Player ${activePlayerNumber.value}`;
});

const isPassAndPlay = computed<boolean>(
    () => gameData.value?.game_mode === "pass_and_play",
);
const isOnline = computed<boolean>(
    () => gameData.value?.game_mode === "online",
);
const isSinglePlayer = computed<boolean>(
    () => gameData.value?.game_mode === "single" || !gameData.value?.game_mode,
);
const isDuel = computed<boolean>(
    () =>
        (gameData.value?.game_type || gameData.value?.game?.game_type) ===
        "duel",
);

const isDaily = computed<boolean>(
    () => gameData.value?.game?.is_daily === true,
);

const isHost = computed<boolean>(
    () => gameData.value?.game?.user_id === auth.state.user?.id,
);

const turnHandoffCharacterName = computed<string>(() => {
    if (!turnHandoffPlayerNumber.value || !gameData.value?.player_status) {
        return "";
    }
    const ps = gameData.value.player_status.find(
        (p) => p.player_number === turnHandoffPlayerNumber.value,
    );
    return ps?.character_name || `Player ${turnHandoffPlayerNumber.value}`;
});

const myKingdomStyleSlug = computed<string>(() => {
    const userId = auth.state.user?.id;
    const player = gameData.value?.game?.players?.find(
        (p) => p.user_id === userId,
    );
    return player?.user?.active_kingdom_style_slug || "classic";
});

const myKingdomStyleData = computed<KingdomStyle | undefined>(() => {
    const userId = auth.state.user?.id;
    const player = gameData.value?.game?.players?.find(
        (p) => p.user_id === userId,
    );
    return player?.user?.active_kingdom_style || undefined;
});

const showItemPrompt = computed<boolean>(() => {
    if (!gameData.value?.all_assigned) {
        return false;
    }
    if (allItemsDecided.value) {
        return false;
    }
    // Check if current active player has items and hasn't decided
    const ps = gameData.value.player_status?.find(
        (p) => p.player_number === activePlayerNumber.value,
    );
    if (!ps) {
        return false;
    }
    if (ps.item_decided) {
        return false;
    }
    if (!ps.has_items) {
        return false;
    }
    // Also check client-side that there are usable items
    if (usableItems.value.length === 0) {
        return false;
    }
    return true;
});

const usableItems = computed<PlayerItem[]>(() => {
    const round = gameData.value?.current_round || 0;
    return currentPlayerItems.value.filter(
        (pi) => !pi.is_used && !(pi.used_round && pi.used_round === round),
    );
});

const displayGame = computed<Record<string, unknown>>(() => {
    // Before any phase is accepted, show pre-resolution stats
    if (currentStatPhase.value === undefined && preResolveGame.value) {
        return preResolveGame.value;
    }
    if (currentStatPhase.value === "positive" && gameAfterPositive.value) {
        return { ...gameData.value?.game, ...gameAfterPositive.value };
    }
    if (currentStatPhase.value === "negative" && gameAfterNegative.value) {
        return { ...gameData.value?.game, ...gameAfterNegative.value };
    }
    return gameData.value?.game || {};
});

function onGameDataUpdated(data: GameData): void {
    gameData.value = data;
}

function onPhaseUpdated(duelPhase: string): void {
    if (!gameData.value) {
        return;
    }

    gameData.value.duel_phase = duelPhase;
    if (gameData.value.game) {
        gameData.value.game.duel_phase = duelPhase;
    }
}

function onDuelGameOver(): void {
    router.replace(`/game/${id}/over`);
}

function openInventory(): void {
    playerItems.value?.openOverlay();
}

function openDuelInventory(): void {
    duelBoard.value?.playerItems?.openOverlay();
}

function onVisibilityChange(): void {
    if (!(document.visibilityState === "visible" && isOnline.value)) {
        return;
    }

    fetchGameWithRetry();
    // Resubscribe if channel lost
    if (!echoChannel.value) {
        subscribeToGameChannel();
    }
}

function setupConnectionMonitoring(): void {
    try {
        const pusher = getEcho()?.connector?.pusher;
        if (!pusher) {
            return;
        }
        pusher.connection.bind("state_change", (states) => {
            connectionStatus.value = states.current;
            // On reconnect, re-fetch game state
            if (
                states.current === "connected" &&
                states.previous !== "connected" &&
                isOnline.value
            ) {
                fetchGameWithRetry();
            }
        });
    } catch {
        // Pusher not available
    }
}

async function fetchGameWithRetry(attempt = 0): Promise<void> {
    const maxAttempts = 5;
    try {
        const response = await axios.get<GameData>(`/api/games/${id}`);
        gameData.value = response.data;
    } catch {
        if (attempt < maxAttempts && isOnline.value) {
            const delay = Math.pow(2, attempt) * 1000; // 1s, 2s, 4s, 8s, 16s
            setTimeout(() => fetchGameWithRetry(attempt + 1), delay);
        }
    }
}

async function fetchGame(): Promise<void> {
    // Only show loading spinner on initial load, not on refreshes
    if (!gameData.value) {
        loading.value = true;
    }
    try {
        const response = await axios.get<GameData>(`/api/games/${id}`);
        gameData.value = response.data;

        // Restore pending curses and active curses from server state
        pendingCurses.value = response.data.pending_curses || undefined;
        playerCurses.value = response.data.player_curses || {};

        if (
            gameData.value.game.status === "completed" ||
            gameData.value.game.status === "cancelled"
        ) {
            router.replace(`/game/${id}/over`);
            return;
        }

        // Game stuck in setup (non-online): redirect back to character selection
        if (gameData.value.game.status === "setup" && !isOnline.value) {
            router.replace(`/?resume=${id}`);
            return;
        }

        // Check for event reveal (round 1, 8, 15, 22, ...)
        checkEventReveal();

        // Duel mode: DuelBoard handles its own state — skip cooperative hand loading
        if (isDuel.value) {
            // Just ensure online setup
            if (isOnline.value) {
                setupOnlinePlayer();
                if (!echoChannel.value) {
                    subscribeToGameChannel();
                }
            }
            loading.value = false;
            return;
        }

        // If in selecting phase, load current player's hand
        if (gameData.value.round_phase === "selecting") {
            // Update allItemsDecided from server state
            allItemsDecided.value = gameData.value.all_items_decided ?? false;

            if (isOnline.value) {
                setupOnlinePlayer();
                // Check if I already assigned
                const myStatus = gameData.value.player_status?.find(
                    (p) => p.player_number === myPlayerNumber.value,
                );
                if (myStatus?.has_assigned) {
                    waitingForOthers.value = true;
                } else if (myPlayerNumber.value) {
                    await loadHand(myPlayerNumber.value);
                }
            } else if (isPassAndPlay.value) {
                // Show handoff overlay for first unassigned player
                const firstUnassigned = gameData.value.player_status?.find(
                    (p) => !p.has_assigned,
                );
                if (firstUnassigned) {
                    activePlayerNumber.value = firstUnassigned.player_number;
                    turnHandoffPlayerNumber.value =
                        firstUnassigned.player_number;
                    showTurnHandoff.value = true;
                    // Don't load hand yet — wait for "Ready"
                } else {
                    await loadHand(activePlayerNumber.value);
                }
            } else {
                await loadHand(activePlayerNumber.value);
            }
        }

        // If in resolving phase, restore full resolve state or reconstruct from server
        else if (gameData.value.round_phase === "resolving") {
            if (isSinglePlayer.value) {
                // Single player: skip RoundResults UI, just advance to next round
                await advanceRound();
            } else if (!didRestoreResolveState()) {
                await loadRoundResults();
                resolveResumed.value = true;
            }
        }
    } catch (error) {
        toast.error("Failed to load game: " + errorMessage(error));
    }
    loading.value = false;
}

async function loadHand(playerNumber: number): Promise<void> {
    handLoading.value = true;
    try {
        const response = await axios.get<{
            cards: HandCard[];
            items?: PlayerItem[];
            dice_count?: number;
            character_dice?: string[][];
            character_wild_value?: number;
            character_wild_ability?: string;
            card_redraws_remaining?: number;
            show_previews?: boolean;
        }>(`/api/games/${id}/hand/${playerNumber}`);
        currentHand.value = response.data.cards;
        currentPlayerItems.value = response.data.items || [];
        diceCount.value = response.data.dice_count ?? 3;
        characterDice.value = response.data.character_dice || undefined;
        characterWildValue.value =
            response.data.character_wild_value || undefined;
        characterWildAbility.value =
            response.data.character_wild_ability || undefined;
        cardRedraws.value = response.data.card_redraws_remaining ?? 0;
        showPreviews.value = response.data.show_previews || false;
    } catch {
        currentHand.value = [];
        currentPlayerItems.value = [];
    }
    handLoading.value = false;
}

async function loadRoundResults(): Promise<void> {
    // Results were stored from resolveRound, but if page refreshes
    // reconstruct from game state
    if (positivePhase.value.cards || !gameData.value?.round_results) {
        return;
    }

    const result = gameData.value.round_results[0];
    if (result) {
        positivePhase.value = {
            cards: [],
            total_difficulty: result.stat_totals?.total_difficulty || 0,
            total_roll: result.stat_totals?.total_roll || 0,
            dice_results: result.dice_results || [],
            wild_triggers: result.wild_triggers || [],
            ability_effects: [],
            success: result.success,
            effects: result.effects_applied || {},
        };
        negativePhase.value = { cards: [], effects: {} };
    }
}

function onCardPreview(effects: unknown): void {
    cardPreviewEffects.value = effects;
}

async function onCardRedraw(handId: number): Promise<void> {
    try {
        const response = await axios.post<{
            redraws_remaining?: number;
            new_card?: Record<string, unknown>;
            hand_id: number;
        }>(`/api/games/${id}/card-redraw`, { hand_id: handId });
        cardRedraws.value = response.data.redraws_remaining ?? 0;
        // Replace the redrawn card in the current hand
        const index = currentHand.value.findIndex((c) => c.hand_id === handId);
        if (index !== -1 && response.data.new_card) {
            currentHand.value[index] = {
                hand_id: response.data.hand_id,
                card: response.data.new_card,
            };
        }
        toast.success("Card redrawn!");
    } catch (error) {
        toast.error(errorMessage(error) || "Failed to redraw card");
    }
}

async function assignRoles({
    positive_hand_id,
    negative_hand_ids,
}: {
    positive_hand_id: number;
    negative_hand_ids: number[];
}): Promise<void> {
    cardPreviewEffects.value = undefined;
    try {
        const response = await axios.post<{
            auto_resolved?: boolean;
            resolve_data?: ResolveResponse;
            all_assigned?: boolean;
        }>(`/api/games/${id}/assign-roles`, {
            positive_hand_id,
            negative_hand_ids,
        });

        // Online mode: handle auto-resolve or show waiting
        if (isOnline.value) {
            if (
                response.data.auto_resolved &&
                response.data.resolve_data &&
                gameData.value
            ) {
                // Auto-resolved via server — RoundResolved broadcast handles the rest
                // But also apply locally for the player who triggered it
                const data = response.data.resolve_data;
                preResolveGame.value = { ...gameData.value.game };
                positivePhase.value = data.positive_phase;
                negativePhase.value = data.negative_phase;
                combinedEffects.value = data.combined_effects;
                eventEffects.value = data.event_effects;
                specialEffects.value = data.special_effects || [];
                isGameOver.value = data.game_over;
                gameAfterPositive.value = data.game_after_positive;
                gameAfterNegative.value = data.game_after_negative;
                currentStatPhase.value = undefined;
                gameData.value.game = data.game;
                gameData.value.round_phase = "resolving";
                waitingForOthers.value = false;
                saveResolveState();
            } else {
                // Show waiting overlay
                waitingForOthers.value = true;
                // Refresh to get latest player_status
                const gameResponse = await axios.get<GameData>(
                    `/api/games/${id}`,
                );
                gameData.value = gameResponse.data;
            }
            return;
        }

        // Refresh game state
        const gameResponse = await axios.get<GameData>(`/api/games/${id}`);
        gameData.value = gameResponse.data;

        // All assigned: start item decision phase or auto-resolve
        if (response.data.all_assigned) {
            allItemsDecided.value = gameData.value.all_items_decided ?? false;
            if (!allItemsDecided.value) {
                startItemPhase();
            } else if (isSinglePlayer.value) {
                // Single player: skip "The council is ready" prompt, auto-resolve
                await resolveRound();
            }
            return;
        }

        // Find next unassigned player
        if (!response.data.all_assigned && gameData.value.player_status) {
            const next = gameData.value.player_status.find(
                (p) => !p.has_assigned,
            );
            if (next) {
                activePlayerNumber.value = next.player_number;
                if (isPassAndPlay.value) {
                    // Show handoff overlay instead of immediately loading hand
                    turnHandoffPlayerNumber.value = next.player_number;
                    showTurnHandoff.value = true;
                } else {
                    await loadHand(next.player_number);
                }
            }
        } else {
            await loadHand(activePlayerNumber.value);
        }
    } catch (error) {
        toast.error("Failed to assign: " + errorMessage(error));
    }
}

async function resolveRound(): Promise<void> {
    resolving.value = true;
    try {
        // Snapshot current stats before resolution
        if (gameData.value) {
            preResolveGame.value = { ...gameData.value.game };
        }
        const response = await axios.post<ResolveResponse>(
            `/api/games/${id}/resolve-round`,
        );
        positivePhase.value = response.data.positive_phase;
        negativePhase.value = response.data.negative_phase;
        combinedEffects.value = response.data.combined_effects;
        eventEffects.value = response.data.event_effects;
        specialEffects.value = response.data.special_effects || [];
        isGameOver.value = response.data.game_over;
        gameAfterPositive.value = response.data.game_after_positive;
        gameAfterNegative.value = response.data.game_after_negative;
        if (gameData.value) {
            gameData.value.game = response.data.game;
        }
        // Refresh items after resolution (new items may have been granted)
        const resolvedItems =
            response.data.player_items?.[activePlayerNumber.value];
        if (resolvedItems) {
            currentPlayerItems.value = resolvedItems;
        }
        // Check for players over item limit
        itemsOverLimit.value = response.data.items_over_limit || [];
        // Queue item reveals for any draw_item special effects
        queueItemReveals(specialEffects.value);
        // Process pending curses
        pendingCurses.value = response.data.pending_curses || undefined;
        playerCurses.value = response.data.player_curses || {};

        if (isSinglePlayer.value) {
            // Single player: show inline results on the card instead of RoundResults
            singlePlayerResolveData.value = {
                positivePhase: response.data.positive_phase,
                negativePhase: response.data.negative_phase,
                combinedEffects: response.data.combined_effects,
                eventEffects: response.data.event_effects,
                specialEffects: response.data.special_effects || [],
                gameOver: response.data.game_over,
            };
            currentStatPhase.value = undefined; // bars stay at pre-resolve until results shown
            // Keep round_phase as 'selecting' so CardSelectionHand stays visible
        } else if (gameData.value) {
            currentStatPhase.value = undefined; // stats stay at pre-resolve until accepted
            gameData.value.round_phase = "resolving";
        }
        // Persist resolve state for page refresh recovery
        saveResolveState();
    } catch (error) {
        toast.error("Failed to resolve: " + errorMessage(error));
    }
    resolving.value = false;
}

function saveResolveState(): void {
    try {
        const round =
            gameData.value?.current_round || gameData.value?.game.current_round;
        sessionStorage.setItem(
            `game_${id}_resolve_${round}`,
            JSON.stringify({
                positivePhase: positivePhase.value,
                negativePhase: negativePhase.value,
                combinedEffects: combinedEffects.value,
                eventEffects: eventEffects.value,
                specialEffects: specialEffects.value,
                isGameOver: isGameOver.value,
                gameAfterPositive: gameAfterPositive.value,
                gameAfterNegative: gameAfterNegative.value,
            }),
        );
    } catch {
        /*
    sessionStorage full or unavailable
    */
    }
}

function didRestoreResolveState(): boolean {
    try {
        const round =
            gameData.value?.current_round ||
            gameData.value?.game?.current_round;
        const saved = sessionStorage.getItem(`game_${id}_resolve_${round}`);
        if (saved) {
            const data = JSON.parse(saved);
            positivePhase.value = data.positivePhase;
            negativePhase.value = data.negativePhase;
            combinedEffects.value = data.combinedEffects;
            eventEffects.value = data.eventEffects;
            specialEffects.value = data.specialEffects;
            isGameOver.value = data.isGameOver;
            gameAfterPositive.value = data.gameAfterPositive;
            gameAfterNegative.value = data.gameAfterNegative;
            resolveResumed.value = true;
            return true;
        }
    } catch {
        /*
    ignore
    */
    }
    return false;
}

function clearResolveState(round: number | undefined): void {
    try {
        sessionStorage.removeItem(`game_${id}_resolve_${round}`);
    } catch {
        /*
    ignore
    */
    }
}

function startItemPhase(): void {
    // Determine which player needs to decide on items
    if (isPassAndPlay.value) {
        const firstUndecided = gameData.value?.player_status?.find(
            (p) => p.has_items && !p.item_decided,
        );
        if (firstUndecided) {
            activePlayerNumber.value = firstUndecided.player_number;
            turnHandoffPlayerNumber.value = firstUndecided.player_number;
            showTurnHandoff.value = true;
        } else {
            // All decided or no items
            allItemsDecided.value = true;
        }
    } else if (isSinglePlayer.value) {
        // Single player: check if they have items
        const ps = gameData.value?.player_status?.find(
            (p) => p.player_number === activePlayerNumber.value,
        );
        if (!ps?.has_items) {
            allItemsDecided.value = true;
            // Auto-resolve immediately
            resolveRound();
        }
        // Otherwise, item prompt shows via showItemPrompt computed
    }
    // Online: each player sees their own prompt via showItemPrompt
}

async function useItem(gamePlayerItemId: number): Promise<void> {
    usingItem.value = true;
    try {
        const response = await axios.post<{
            all_items_decided: boolean;
            player_items?: PlayerItem[];
        }>(`/api/games/${id}/use-item`, {
            game_player_item_id: gamePlayerItemId,
            player_number: activePlayerNumber.value,
        });
        itemDeciding.value = false;
        allItemsDecided.value = response.data.all_items_decided;
        // Update items
        if (response.data.player_items) {
            currentPlayerItems.value = response.data.player_items;
        }
        // Update player_status
        if (gameData.value?.player_status) {
            const ps = gameData.value.player_status.find(
                (p) => p.player_number === activePlayerNumber.value,
            );
            if (ps) {
                ps.item_decided = true;
            }
        }
        // Pass-and-play: advance to next player or show roll
        if (isPassAndPlay.value && !allItemsDecided.value) {
            advanceItemPlayer();
        } else if (isSinglePlayer.value && allItemsDecided.value) {
            usingItem.value = false;
            await resolveRound();
            return;
        }
    } catch (error) {
        toast.error("Failed to use item: " + errorMessage(error));
    }
    usingItem.value = false;
}

async function skipItem(): Promise<void> {
    usingItem.value = true;
    try {
        const response = await axios.post<{ all_items_decided: boolean }>(
            `/api/games/${id}/skip-item`,
            {
                player_number: activePlayerNumber.value,
            },
        );
        itemDeciding.value = false;
        allItemsDecided.value = response.data.all_items_decided;
        // Update player_status
        if (gameData.value?.player_status) {
            const ps = gameData.value.player_status.find(
                (p) => p.player_number === activePlayerNumber.value,
            );
            if (ps) {
                ps.item_decided = true;
            }
        }
        // Pass-and-play: advance to next player or show roll
        if (isPassAndPlay.value && !allItemsDecided.value) {
            advanceItemPlayer();
        } else if (isSinglePlayer.value && allItemsDecided.value) {
            usingItem.value = false;
            await resolveRound();
            return;
        }
    } catch (error) {
        toast.error("Failed to skip item: " + errorMessage(error));
    }
    usingItem.value = false;
}

function advanceItemPlayer(): void {
    const nextUndecided = gameData.value?.player_status?.find(
        (p) => p.has_items && !p.item_decided,
    );
    if (nextUndecided) {
        activePlayerNumber.value = nextUndecided.player_number;
        turnHandoffPlayerNumber.value = nextUndecided.player_number;
        showTurnHandoff.value = true;
        loadHand(nextUndecided.player_number);
    } else {
        allItemsDecided.value = true;
    }
}

function itemEffectLabel(item?: Item): string {
    if (!item?.effect) {
        return "";
    }
    const type = item.effect.bonus_type || "";
    const value = item.effect.bonus_value ?? 0;
    switch (type) {
        case "roll_bonus": {
            return `+${value} to roll`;
        }
        case "roll_penalty": {
            return `${value} to roll`;
        }
        case "difficulty_reduction": {
            return `-${Math.abs(value)} difficulty`;
        }
        case "difficulty_increase": {
            return `+${Math.abs(value)} difficulty`;
        }
        case "stat_boost": {
            return `+${value} ${item.effect.stat || "stat"}`;
        }
        case "heal_die": {
            return "Recover a lost die";
        }
        case "score_bonus": {
            return `${value > 0 ? "+" : ""}${value} renown`;
        }
        default: {
            return item.description || "Use this item";
        }
    }
}

function setupOnlinePlayer(): void {
    const userId = auth.state.user?.id;
    if (!userId || !gameData.value?.game?.players) {
        return;
    }
    const myPlayer = gameData.value.game.players.find(
        (p) => p.user_id === userId,
    );
    if (myPlayer) {
        myPlayerNumber.value = myPlayer.player_number;
        activePlayerNumber.value = myPlayer.player_number;
    }
}

function subscribeToGameChannel(): void {
    const echo = getEcho();
    if (!echo) {
        return;
    }
    echoChannel.value = echo
        .private(`game.${id}`)
        .listen("PlayerJoinedGame", () => {
            if (lobby.value) {
                lobby.value.fetchLobby();
            }
        })
        .listen(
            "PlayerSelectedCharacter",
            async (data: { all_selected?: boolean }) => {
                if (!lobby.value) {
                    return;
                }
                await lobby.value.fetchLobby();
                if (data.all_selected && lobby.value) {
                    lobby.value.autoStartGame();
                }
            },
        )
        .listen(
            "PlayerAssignedCards",
            (data: { player_number: number; all_assigned?: boolean }) => {
                // Update player status
                if (!gameData.value?.player_status) {
                    return;
                }

                const ps = gameData.value.player_status.find(
                    (p) => p.player_number === data.player_number,
                );
                if (ps) {
                    ps.has_assigned = true;
                }
                gameData.value.all_assigned = data.all_assigned;
            },
        )
        .listen(
            "PlayerItemDecided",
            (data: { player_number: number; all_decided: boolean }) => {
                // Update item decided status
                if (gameData.value?.player_status) {
                    const ps = gameData.value.player_status.find(
                        (p) => p.player_number === data.player_number,
                    );
                    if (ps) {
                        ps.item_decided = true;
                    }
                }
                allItemsDecided.value = data.all_decided;
            },
        )
        .listen("RoundResolved", (data: ResolveResponse) => {
            // All players receive resolve data simultaneously
            if (gameData.value) {
                preResolveGame.value = { ...gameData.value.game };
            }
            positivePhase.value = data.positive_phase;
            negativePhase.value = data.negative_phase;
            combinedEffects.value = data.combined_effects;
            eventEffects.value = data.event_effects;
            specialEffects.value = data.special_effects || [];
            isGameOver.value = data.game_over;
            gameAfterPositive.value = data.game_after_positive;
            gameAfterNegative.value = data.game_after_negative;
            currentStatPhase.value = undefined;
            if (gameData.value) {
                gameData.value.game = data.game;
                gameData.value.round_phase = "resolving";
            }
            // Refresh items after resolution
            const resolvedItems = data.player_items?.[activePlayerNumber.value];
            if (resolvedItems) {
                currentPlayerItems.value = resolvedItems;
            }
            queueItemReveals(data.special_effects || []);
            waitingForOthers.value = false;
            resolving.value = false;
            saveResolveState();
        })
        .listen("NextRoundStarted", (data: GameData) => {
            if (isDuel.value && duelBoard.value) {
                gameData.value = data;
                duelBoard.value.handleNextRoundStarted(data);
                return;
            }
            // Clear saved resolve state for the round being advanced from
            clearResolveState(
                gameData.value?.current_round ||
                    gameData.value?.game?.current_round,
            );
            // Non-host auto-transitions to next round
            positivePhase.value = {};
            negativePhase.value = {};
            combinedEffects.value = {};
            eventEffects.value = {};
            specialEffects.value = [];
            isGameOver.value = false;
            gameAfterPositive.value = undefined;
            gameAfterNegative.value = undefined;
            preResolveGame.value = undefined;
            currentStatPhase.value = undefined;
            waitingForOthers.value = false;
            itemDeciding.value = false;
            usingItem.value = false;
            allItemsDecided.value = false;
            resolveResumed.value = false;
            singlePlayerResolveData.value = undefined;
            gameData.value = data;
            checkEventReveal();
            if (myPlayerNumber.value) {
                activePlayerNumber.value = myPlayerNumber.value;
                loadHand(myPlayerNumber.value);
            }
        })
        .listen("GameStarted", () => {
            fetchGame();
        })
        // Duel events
        .listen("DuelChoiceMade", (data: never) => {
            if (duelBoard.value) {
                duelBoard.value.handleDuelChoiceMade(data);
            }
        })
        .listen("DuelRollComplete", (data: never) => {
            if (duelBoard.value) {
                duelBoard.value.handleDuelRollComplete(data);
            }
        })
        .listen(
            "DuelGameOver",
            (data: {
                completion?: unknown;
                timed_out_player_number?: number;
            }) => {
                if (data.completion) {
                    sessionStorage.setItem(
                        `game_completion_${id}`,
                        JSON.stringify(data.completion),
                    );
                }
                if (data.timed_out_player_number != undefined) {
                    sessionStorage.setItem(
                        `game_timeout_${id}`,
                        JSON.stringify(data.timed_out_player_number),
                    );
                }
                router.replace(`/game/${id}/over`);
            },
        );
}

async function startOnlineGame(): Promise<void> {
    try {
        // Fetch lobby to get current player/character assignments
        const lobbyResponse = await axios.get<{
            players: { player_number: number; character_id: number }[];
        }>(`/api/games/${id}/lobby`);
        const players = lobbyResponse.data.players;
        const characters = players
            .toSorted((a, b) => a.player_number - b.player_number)
            .map((p) => p.character_id);

        await axios.post(`/api/games/${id}/start`, { characters });

        // Broadcast game started
        await fetchGame();
    } catch (error) {
        toast.error("Failed to start: " + errorMessage(error));
    }
}

function queueItemReveals(effects: SpecialEffect[]): void {
    const itemEffects = (effects || []).filter(
        (effect) =>
            (effect.type === "draw_item" || effect.type === "item_blocked") &&
            effect.item,
    );
    if (itemEffects.length > 0) {
        itemRevealQueue.value = [...itemEffects];
        showItemReveal.value = true;
    }
}

function onItemRevealDismiss(): void {
    itemRevealQueue.value.shift();
    if (itemRevealQueue.value.length === 0) {
        showItemReveal.value = false;
    }
}

function describeCurseEffect(
    effect: CurseEffect | undefined,
    phase: "positive" | "negative",
): string {
    if (!effect) {
        return "Unknown";
    }
    const t = effect.type;
    if (t === "lose_die") {
        return `Permanently lose ${effect.value || 1} die`;
    }
    if (t === "stat_per_round" && phase === "negative") {
        return `${effect.value} ${effect.stat} each round`;
    }
    if (t === "stat_per_round" && phase === "positive") {
        return `+${effect.value} ${effect.stat} each round`;
    }
    if (t === "difficulty_modifier") {
        return `All cards +${effect.value || 1} difficulty`;
    }
    if (t === "double_negative") {
        return "Negative card effects are doubled";
    }
    if (t === "xp_multiplier") {
        return `${effect.value}x XP at game end`;
    }
    if (t === "auto_max_stat") {
        return `${effect.count || 1} stat(s) set to max at game end`;
    }
    if (t === "score_bonus") {
        return `+${effect.value} score at game end`;
    }
    if (t === "opponent_difficulty") {
        return `Opponent's cards +${effect.value} difficulty`;
    }
    if (t === "opponent_lose_die") {
        return `Opponent loses a die for ${effect.rounds || 1} round(s)`;
    }
    if (t === "reveal_previews") {
        return "Reveals stat preview bars on card hover";
    }
    return JSON.stringify(effect);
}

function getPlayerNameForCurse(playerNumber: string | number): string {
    const pn =
        typeof playerNumber === "number" ? playerNumber : Number(playerNumber);
    const player = gameData.value?.game?.players?.find(
        (p) => p.player_number === pn,
    );
    return player?.character?.name || `Player ${pn}`;
}

function currentPendingCurse(): PendingCurse | undefined {
    if (!pendingCurses.value || pendingCurses.value.length === 0) {
        return undefined;
    }
    // Find the first pending curse for the active player
    const player = gameData.value?.game?.players?.find(
        (p) => p.player_number === activePlayerNumber.value,
    );
    if (!player) {
        return undefined;
    }
    return pendingCurses.value.find((pc) => pc.player_id === player.id);
}

async function onCurseSelected(curseId: number): Promise<void> {
    try {
        const response = await axios.post<{
            pending_curses?: PendingCurse[];
            player_curses?: PlayerCurse[];
        }>(`/api/games/${id}/choose-curse`, {
            curse_id: curseId,
            player_number: activePlayerNumber.value,
        });
        pendingCurses.value = response.data.pending_curses || undefined;
        if (response.data.player_curses) {
            playerCurses.value = {
                ...playerCurses.value,
                [activePlayerNumber.value]: response.data.player_curses,
            };
        }
        // When all curses resolved, proceed to next round
        if (!pendingCurses.value || pendingCurses.value.length === 0) {
            showCurseSelection.value = false;
            await advanceRound();
        }
    } catch (error) {
        toast.error("Failed to choose curse: " + errorMessage(error));
    }
}

function showDuelDiceViewer(): void {
    // Open the character modal for the active player's character in duel mode
    const board = duelBoard.value;
    if (board) {
        const playerNumber = board.activePlayerNumber;
        const player = gameData.value?.game?.players?.find(
            (p) => p.player_number === playerNumber,
        );
        if (player?.character) {
            board.openCharacterModal(playerNumber);
        }
    }
}

async function goHome(): Promise<void> {
    // Duel games are paused and resumable — don't cancel
    if (!isDuel.value) {
        try {
            await axios.post(`/api/games/${id}/cancel`);
        } catch {
            // ignore cancel errors
        }
    }
    router.push("/");
}

async function forfeitGame(): Promise<void> {
    showQuitConfirm.value = false;
    try {
        await axios.post(`/api/games/${id}/forfeit`);
        router.replace(`/game/${id}/over`);
    } catch (error) {
        toast.error("Failed to forfeit: " + errorMessage(error));
    }
}

async function quitDailyChallenge(): Promise<void> {
    showQuitDailyConfirm.value = false;
    try {
        await axios.post(`/api/daily-challenges/${id}/quit`);
        router.replace("/challenges");
    } catch (error) {
        toast.error("Failed to quit: " + errorMessage(error));
    }
}

function onItemDiscarded(updatedOverLimit: unknown[]): void {
    itemsOverLimit.value = updatedOverLimit || [];
}

function checkEventReveal(): void {
    const round = gameData.value?.current_round || 0;
    const event = gameData.value?.current_event;
    if (event && (round - 1) % 3 === 0) {
        const key = `game_${id}_event_${event.id}`;
        if (!sessionStorage.getItem(key)) {
            sessionStorage.setItem(key, "1");
            showEventReveal.value = true;
        }
    }
}

async function onHandoffReady(): Promise<void> {
    showTurnHandoff.value = false;
    await loadHand(activePlayerNumber.value);
}

function onPhaseComplete(phase: "positive" | "negative"): void {
    currentStatPhase.value = phase;
}

function onSinglePlayerContinue(): void {
    advanceRound();
}

async function advanceRound(): Promise<void> {
    // Prevent concurrent advance calls (e.g. double-tap)
    if (advancing.value) {
        return;
    }

    // Show curse selection first if there are pending curses
    if (pendingCurses.value && pendingCurses.value.length > 0) {
        showCurseSelection.value = true;
        return;
    }
    advancing.value = true;
    try {
        const response = await axios.post<
            GameData & {
                game_over?: boolean;
                completion?: unknown;
                score_breakdown?: unknown;
            }
        >(`/api/games/${id}/next-round`);

        if (response.data.game_over) {
            if (response.data.completion) {
                sessionStorage.setItem(
                    `game_completion_${id}`,
                    JSON.stringify(response.data.completion),
                );
            }
            if (response.data.score_breakdown) {
                sessionStorage.setItem(
                    `game_score_breakdown_${id}`,
                    JSON.stringify(response.data.score_breakdown),
                );
            }
            router.replace(`/game/${id}/over`);
            return;
        }

        // Clear saved resolve state for this round before resetting
        clearResolveState(
            gameData.value?.current_round ||
                gameData.value?.game?.current_round,
        );

        // Reset state for next round
        positivePhase.value = {};
        negativePhase.value = {};
        combinedEffects.value = {};
        eventEffects.value = {};
        specialEffects.value = [];
        itemRevealQueue.value = [];
        showItemReveal.value = false;
        itemsOverLimit.value = [];
        isGameOver.value = false;
        gameAfterPositive.value = undefined;
        gameAfterNegative.value = undefined;
        preResolveGame.value = undefined;
        currentStatPhase.value = undefined;
        showCurseSelection.value = false;
        itemDeciding.value = false;
        usingItem.value = false;
        allItemsDecided.value = false;
        resolveResumed.value = false;
        singlePlayerResolveData.value = undefined;
        gameData.value = response.data;
        activePlayerNumber.value = 1;
        checkEventReveal();
        if (isPassAndPlay.value) {
            turnHandoffPlayerNumber.value = 1;
            showTurnHandoff.value = true;
        } else {
            await loadHand(1);
        }
    } catch (error) {
        toast.error("Failed to advance: " + errorMessage(error));
        await fetchGame();
    } finally {
        advancing.value = false;
    }
}

onMounted(async () => {
    await fetchGame();
    setActiveGameType(isDuel.value ? "duel" : "cooperative");
    if (isOnline.value && !echoChannel.value) {
        setupOnlinePlayer();
        subscribeToGameChannel();
    }
    // Reconnection: visibility change
    document.addEventListener("visibilitychange", onVisibilityChange);
    // Reconnection: Pusher connection monitoring
    setupConnectionMonitoring();
});

onBeforeUnmount(() => {
    setActiveGameType(undefined);
    if (echoChannel.value) {
        getEcho()?.leave(`game.${id}`);
        echoChannel.value = undefined;
    }
    document.removeEventListener("visibilitychange", onVisibilityChange);
});
</script>

<style scoped>
/* Curse icon in header bar */
.bar-icon-curse {
    border-color: rgba(155, 89, 182, 0.5);
    color: #c890e0;
}
.bar-icon-curse:hover {
    border-color: #9b59b6;
    background: rgba(155, 89, 182, 0.1);
}
.bar-icon-curse .bar-icon-svg {
    color: #c890e0;
}
.bar-icon-curse .bar-icon-badge {
    color: #c890e0;
}

/* Curse Viewer Overlay */
.curse-viewer-overlay {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(
        ellipse at center,
        rgba(40, 0, 60, 0.92) 0%,
        rgba(10, 0, 15, 0.97) 100%
    );
}
.curse-viewer-container {
    position: relative;
    width: 95%;
    max-width: 720px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 28px 20px;
    text-align: center;
}
.curse-viewer-close {
    position: fixed;
    top: 16px;
    right: 16px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.6);
    border: 1px solid rgba(155, 89, 182, 0.5);
    color: rgba(200, 144, 224, 0.8);
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    transition:
        color 0.2s,
        border-color 0.2s;
}
.curse-viewer-close:hover {
    color: #c890e0;
    border-color: #9b59b6;
}
.curse-viewer-header {
    margin-bottom: 24px;
}
.curse-viewer-title {
    font-family: "Cinzel", serif;
    color: #9b59b6;
    font-size: 1.8rem;
    text-shadow: 0 0 20px rgba(155, 89, 182, 0.5);
    margin-bottom: 6px;
}
.curse-viewer-subtitle {
    color: rgba(200, 144, 224, 0.7);
    font-size: 0.9rem;
}
.curse-viewer-cards {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}
.curse-viewer-parchment {
    background: linear-gradient(180deg, #2a1530, #1e0e28, #140820);
    border: 2px solid rgba(128, 0, 128, 0.35);
    border-radius: 12px;
    padding: 24px 20px;
    width: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(155, 89, 182, 0.08);
}
.curse-viewer-image {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid rgba(155, 89, 182, 0.4);
    margin-bottom: 12px;
}
.curse-viewer-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #9b59b6;
    background: rgba(155, 89, 182, 0.1);
    border-radius: 50%;
    border: 2px solid rgba(155, 89, 182, 0.3);
    margin-bottom: 12px;
}
.curse-viewer-name {
    font-family: "Cinzel", serif;
    color: #c890e0;
    font-size: 1.1rem;
    text-align: center;
    margin-bottom: 6px;
    line-height: 1.3;
}
.curse-viewer-flavor {
    color: rgba(200, 144, 224, 0.5);
    font-size: 0.82rem;
    font-style: italic;
    line-height: 1.5;
    text-align: center;
    margin-bottom: 8px;
}
.curse-viewer-divider {
    position: relative;
    height: 1px;
    width: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(155, 89, 182, 0.4),
        transparent
    );
    margin: 8px 0;
}
.curse-viewer-divider-gem {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #1e0e28;
    color: #9b59b6;
    padding: 0 8px;
    font-size: 0.7rem;
}
.curse-viewer-effect {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    width: 100%;
    text-align: left;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 6px;
}
.curse-viewer-penalty {
    background: rgba(192, 57, 43, 0.1);
    border: 1px solid rgba(192, 57, 43, 0.25);
}
.curse-viewer-reward {
    background: rgba(212, 168, 67, 0.08);
    border: 1px solid rgba(212, 168, 67, 0.2);
}
.curse-viewer-effect-icon {
    font-size: 1.1rem;
    flex-shrink: 0;
    margin-top: 2px;
}
.curse-viewer-penalty .curse-viewer-effect-icon {
    color: #e74c3c;
}
.curse-viewer-reward .curse-viewer-effect-icon {
    color: #d4a843;
}
.curse-viewer-effect-label {
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.curse-viewer-penalty .curse-viewer-effect-label {
    color: #e74c3c;
}
.curse-viewer-reward .curse-viewer-effect-label {
    color: #d4a843;
}
.curse-viewer-effect-desc {
    font-size: 0.82rem;
    color: var(--text-secondary, #a09080);
    margin-top: 2px;
    line-height: 1.3;
}
.curse-viewer-meta {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 10px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(200, 144, 224, 0.45);
}
/* Transitions */
.curse-viewer-enter-active {
    transition: opacity 0.25s ease;
}
.curse-viewer-leave-active {
    transition: opacity 0.2s ease;
}
.curse-viewer-enter-from,
.curse-viewer-leave-to {
    opacity: 0;
}

@media (max-width: 768px) {
    .curse-viewer-parchment {
        width: 100%;
        max-width: 320px;
        padding: 18px 16px;
    }
    .curse-viewer-name {
        font-size: 1rem;
    }
    .curse-viewer-title {
        font-size: 1.4rem;
    }
}

.connection-banner {
    text-align: center;
    padding: 6px 12px;
    font-size: 0.8rem;
    font-family: "Cinzel", serif;
    border-radius: 6px;
    margin-bottom: 8px;
}

.connection-banner.connecting {
    background: rgba(212, 168, 67, 0.15);
    color: var(--accent-gold);
    border: 1px solid rgba(212, 168, 67, 0.3);
}

.connection-banner.unavailable,
.connection-banner.disconnected,
.connection-banner.failed {
    background: rgba(160, 48, 32, 0.15);
    color: #d05040;
    border: 1px solid rgba(160, 48, 32, 0.3);
}

.loading {
    text-align: center;
    padding: 60px;
    color: var(--text-secondary);
    font-size: 1.2rem;
}

/* Flush war-table board header (cooperative) */
.board-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 2px 0 12px;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(240, 192, 80, 0.2);
}

.board-menu-btn {
    flex: none;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Match the app-wide .ta-back icon button so the menu control reads consistently. */
    background: linear-gradient(180deg, #3a2a18, #181008);
    border: 1.5px solid rgba(240, 192, 80, 0.5);
    border-radius: 11px;
    color: var(--accent-gold, #f0c050);
    cursor: pointer;
    transition: transform 0.1s;
}

.board-menu-btn:active {
    transform: translateY(2px);
}

.board-heading {
    flex: 1;
    min-width: 0;
}

.board-title {
    font-family: "Cinzel", serif;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.5px;
    line-height: 1.1;
    text-transform: uppercase;
    color: var(--accent-gold, #f0c050);
}

.board-sub {
    font-size: 12px;
    color: #9a8a6a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.board-month-pill {
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #f0dcb0;
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid rgba(240, 192, 80, 0.3);
    border-radius: 10px;
    padding: 7px 12px;
}

.board-tools {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-bottom: 12px;
}

.time-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    padding: 6px 0;
    margin-bottom: 14px;
}

.time-info {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
}

.round-label {
    font-family: "Cinzel", serif;
    font-size: 1rem;
    color: var(--accent-gold);
    white-space: nowrap;
}

.time-bar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.time-bar-icons {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bar-icon-btn {
    position: relative;
    background: none;
    border: 1px solid var(--border-gold, #6b5b3a);
    border-radius: 6px;
    color: var(--accent-gold, #c9a84c);
    cursor: pointer;
    padding: 3px 6px;
    display: flex;
    align-items: center;
    transition: all 0.2s;
}

.bar-icon-btn:hover {
    border-color: var(--accent-gold, #c9a84c);
    background: rgba(212, 168, 67, 0.1);
}

.bar-icon-svg {
    width: 18px;
    height: 18px;
}

.bar-icon-badge {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--accent-gold, #c9a84c);
    margin-left: 3px;
}

.progress-info {
    min-width: 100px;
}

.progress-bar-bg {
    background: rgba(0, 0, 0, 0.4);
    height: 5px;
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    border-radius: 3px;
    background: var(--accent-gold);
    transition: width 0.5s ease;
}

.action-btn-top-wrap {
    text-align: center;
    margin-bottom: 10px;
}

.resolve-prompt {
    text-align: center;
    margin: 20px 0;
    padding: 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--accent-green);
    border-radius: 8px;
}

.resolve-prompt p {
    color: var(--accent-green);
    font-family: "Cinzel", serif;
    font-size: 1.1rem;
    margin-bottom: 12px;
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
    .time-bar {
        margin-bottom: 6px;
    }

    .round-label {
        font-size: 0.75rem;
    }

    .bar-icon-svg {
        width: 15px;
        height: 15px;
    }

    .resolve-prompt {
        padding: 12px;
        margin: 12px 0;
    }

    .resolve-prompt p {
        font-size: 0.95rem;
    }

    .item-prompt-card {
        padding: 14px;
        margin: 12px 0;
    }
}

/* Item Phase Styles */
.item-phase {
    position: fixed;
    inset: 0;
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.6);
}

.item-prompt-card {
    background: var(--bg-secondary, #2a1f14);
    border: 2px solid var(--border-gold, #6b5b3a);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    max-width: 340px;
    width: 90%;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
}

.item-prompt-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold, #c9a84c);
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.item-prompt-text {
    color: var(--text-primary, #e8d5b0);
    font-size: 0.95rem;
    margin-bottom: 14px;
}

.item-prompt-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.item-selection {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
}

.item-select-label {
    font-family: "Cinzel", serif;
    color: var(--text-secondary, #a09080);
    font-size: 0.85rem;
    margin-bottom: 4px;
}

.item-use-card {
    background: rgba(212, 168, 67, 0.08);
    border: 1px solid var(--border-gold, #6b5b3a);
    border-radius: 8px;
    padding: 10px 16px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 2px;
    width: 100%;
    max-width: 300px;
    text-align: center;
}

.item-use-card:hover {
    border-color: var(--accent-gold, #c9a84c);
    background: rgba(212, 168, 67, 0.15);
    box-shadow: 0 0 10px rgba(212, 168, 67, 0.15);
}

.item-use-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold, #c9a84c);
    font-size: 0.9rem;
    font-weight: 600;
}

.item-use-effect {
    color: var(--text-secondary, #a09080);
    font-size: 0.8rem;
    font-style: italic;
}

.btn-secondary {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border-gold, #6b5b3a);
    color: var(--text-secondary, #a09080);
    padding: 8px 20px;
    border-radius: 6px;
    font-family: "Cinzel", serif;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary:hover {
    border-color: var(--accent-gold, #c9a84c);
    color: var(--accent-gold, #c9a84c);
}

.btn-secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Burger Menu Button */
.burger-btn {
    background: none;
    border: 1px solid rgba(138, 106, 46, 0.4);
    border-radius: 6px;
    color: var(--text-secondary, #a09080);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    flex-shrink: 0;
    transition: all 0.2s;
    letter-spacing: 0;
    box-shadow: none;
}

.burger-btn:hover {
    color: var(--accent-gold, #c9a84c);
    border-color: var(--accent-gold, #c9a84c);
    background: rgba(212, 168, 67, 0.08);
    transform: none;
    box-shadow: none;
}

/* Game Menu Overlay */
.game-menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mobile-menu-panel {
    background: linear-gradient(
        180deg,
        var(--wood-light, #463220),
        var(--wood-dark, #1e160c)
    );
    border: 3px solid var(--border-gold, #c8952e);
    border-radius: 14px;
    padding: 10px 0;
    min-width: 220px;
    box-shadow:
        0 4px 0 rgba(0, 0, 0, 0.3),
        0 10px 40px rgba(0, 0, 0, 0.7);
}

.mobile-menu-item {
    display: block;
    width: 100%;
    padding: 14px 28px;
    background: none;
    border: none;
    border-radius: 0;
    color: var(--text-primary, #f0e0c8);
    font-family: "Cinzel", serif;
    font-size: 1.05rem;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    transition:
        background 0.2s,
        color 0.2s;
    letter-spacing: 1px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
    box-shadow: none;
}

.mobile-menu-item:hover {
    background: rgba(240, 192, 80, 0.1);
    color: var(--accent-gold, #f0c050);
    transform: none;
    box-shadow: none;
}

.game-menu-leave {
    color: var(--accent-gold, #f0c050);
}

.game-menu-quit {
    color: var(--accent-red, #d04030);
}

.game-menu-quit:hover {
    background: rgba(160, 48, 32, 0.15);
    color: #e05040;
}

/* Dice Viewer Modal */
.dice-viewer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dice-viewer-modal {
    background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
    border: 3px solid var(--border-gold, #8a6a2e);
    border-radius: 14px;
    padding: 24px 28px;
    min-width: 260px;
    max-width: 360px;
    box-shadow:
        0 8px 40px rgba(0, 0, 0, 0.7),
        0 0 20px rgba(212, 168, 67, 0.15);
}

.dice-viewer-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold, #c9a84c);
    font-size: 1.3rem;
    text-align: center;
    margin-bottom: 16px;
}

.dice-viewer-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.dice-viewer-label {
    font-family: "Cinzel", serif;
    color: var(--text-secondary, #a09080);
    font-size: 0.85rem;
    min-width: 42px;
}

.dice-viewer-faces {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.dice-viewer-face {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: rgba(212, 168, 67, 0.12);
    border: 1px solid rgba(212, 168, 67, 0.3);
    border-radius: 6px;
    color: var(--text-bright, #f0e6d2);
    font-size: 0.9rem;
    font-weight: 700;
}

.dice-viewer-face-wild {
    background: rgba(212, 168, 67, 0.25);
    border-color: var(--accent-gold, #c9a84c);
    color: var(--accent-gold, #c9a84c);
}

.dice-viewer-wild-info {
    text-align: center;
    color: var(--text-secondary, #a09080);
    font-size: 0.8rem;
    font-style: italic;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(138, 106, 46, 0.2);
}
</style>
