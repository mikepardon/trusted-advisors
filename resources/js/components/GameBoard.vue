<template>
  <div v-if="loading" class="loading">Loading game state...</div>
  <div v-else-if="gameData" class="game-board">
    <!-- Connection status banner -->
    <div v-if="isOnline && connectionStatus !== 'connected'" class="connection-banner" :class="connectionStatus">
      <span v-if="connectionStatus === 'connecting'">Reconnecting...</span>
      <span v-else>Connection lost. Trying to reconnect...</span>
    </div>
    <!-- ONLINE LOBBY (setup phase) -->
    <template v-if="isOnline && gameData.game.status === 'setup'">
      <OnlineLobby
        ref="lobby"
        :gameId="id"
        :hostId="gameData.game.user_id"
        :gameType="gameData.game.game_type || 'cooperative'"
        :turnTimeLimit="gameData.game.turn_time_limit || 0"
        @start-game="startOnlineGame"
        @lobby-updated="fetchGame"
      />
    </template>

    <template v-else>
    <!-- 3D Dice Overlay (cooperative only — duel uses per-player canvases) -->
    <DiceOverlay v-if="!isDuel" ref="diceOverlay" />

    <!-- DUEL MODE -->
    <template v-if="isDuel">
    <div class="time-bar">
      <div class="time-bar-right" style="width: unset;">
        <div class="time-bar-icons">
          <button
            class="bar-icon-btn"
            title="View Inventory"
            @click="$refs.duelBoard?.$refs.playerItems?.openOverlay()"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bar-icon-svg">
              <path d="M20 7H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1Z"/>
              <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
            <span class="bar-icon-badge">{{ duelItemCount }}</span>
          </button>
          <button class="bar-icon-btn" title="View Your Dice" @click="showDuelDiceViewer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bar-icon-svg">
              <rect x="3" y="3" width="18" height="18" rx="3"/>
              <circle cx="8.5" cy="8.5" r="1" fill="currentColor"/>
              <circle cx="15.5" cy="8.5" r="1" fill="currentColor"/>
              <circle cx="12" cy="12" r="1" fill="currentColor"/>
              <circle cx="8.5" cy="15.5" r="1" fill="currentColor"/>
              <circle cx="15.5" cy="15.5" r="1" fill="currentColor"/>
            </svg>
            <span class="bar-icon-badge">{{ duelDiceCount }}</span>
          </button>
        </div>
      </div>
      <div class="time-info">
        <span class="round-label">Month {{ gameData.current_round }}/{{ gameData.total_rounds }}</span>
      </div>
      <button class="burger-btn" @click.stop="showGameMenu = !showGameMenu"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:block;min-width:15px;"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    </div>
    <DuelBoard
      ref="duelBoard"
      :gameData="gameData"
      :gameId="id"
      @refresh="fetchGame"
      @game-data-updated="onGameDataUpdated"
      @phase-updated="onPhaseUpdated"
      @game-over="$router.replace(`/game/${id}/over`)"
      @items-updated="count => duelItemCount = count"
      @dice-updated="count => duelDiceCount = count"
    />
    </template>

    <!-- COOPERATIVE MODE -->
    <template v-else>
    <!-- Round display -->
    <div class="time-bar">
      <div class="time-bar-right" style="width: unset;">
        <div class="time-bar-icons">
          <button
            class="bar-icon-btn"
            title="View Inventory"
            @click="$refs.playerItems?.openOverlay()"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bar-icon-svg">
              <path d="M20 7H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1Z"/>
              <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
            <span class="bar-icon-badge">{{ usableItems.length }}</span>
          </button>
          <button class="bar-icon-btn" title="View Your Dice" @click="showDiceViewer = true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bar-icon-svg">
              <rect x="3" y="3" width="18" height="18" rx="3"/>
              <circle cx="8.5" cy="8.5" r="1" fill="currentColor"/>
              <circle cx="15.5" cy="8.5" r="1" fill="currentColor"/>
              <circle cx="12" cy="12" r="1" fill="currentColor"/>
              <circle cx="8.5" cy="15.5" r="1" fill="currentColor"/>
              <circle cx="15.5" cy="15.5" r="1" fill="currentColor"/>
            </svg>
            <span class="bar-icon-badge">{{ diceCount }}</span>
          </button>
        </div>
      </div>
      <div class="time-info">
        <span class="round-label">Month {{ gameData.current_round }}/{{ gameData.total_rounds }}</span>
      </div>
      <button class="burger-btn" @click.stop="showGameMenu = !showGameMenu"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:block;min-width:15px;"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    </div>

    <!-- Kingdom Stats -->
    <KingdomStats :game="displayGame" :kingdomStyleSlug="myKingdomStyleSlug" :kingdomStyleData="myKingdomStyleData" :previewEffects="cardPreviewEffects" />

    <!-- Player Items (overlay only, button in top bar) -->
    <PlayerItems ref="playerItems" :items="currentPlayerItems" :showButton="false" :currentRound="gameData.current_round || 0" />

    <!-- Active Curses Indicator -->
    <div v-if="activeCurseCount > 0" class="curse-indicator" @click="showCurseDetails = !showCurseDetails">
      &#9760; {{ activeCurseCount }} curse{{ activeCurseCount > 1 ? 's' : '' }}
    </div>
    <div v-if="showCurseDetails && activeCurseCount > 0" class="curse-details-panel">
      <div v-for="(curses, pn) in playerCurses" :key="pn">
        <div v-for="c in curses" :key="c.id" class="curse-detail-row">
          <span class="curse-detail-name">{{ c.curse?.name || 'Curse' }}</span>
          <span class="curse-detail-neg">{{ c.curse?.negative_effect?.type }}</span>
          <span class="curse-detail-pos">{{ c.curse?.positive_effect?.type }}</span>
        </div>
      </div>
    </div>

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
      v-if="showItemReveal && itemRevealQueue.length"
      :item="itemRevealQueue[0]"
      @dismiss="onItemRevealDismiss"
    />

    <!-- Curse Selection Overlay -->
    <CurseSelectionOverlay
      v-if="showCurseSelection && currentPendingCurse()"
      :curses="currentPendingCurse().curse_details"
      :playerName="gameData?.game?.players?.find(p => p.player_number === activePlayerNumber)?.character?.name || 'Player'"
      :isDuel="false"
      @selected="onCurseSelected"
    />

    <!-- Item Discard Overlay (when over 2-item limit) -->
    <ItemDiscard
      v-if="itemsOverLimit.length > 0"
      :gameId="id"
      :playerData="itemsOverLimit[0]"
      @discarded="onItemDiscarded"
    />

    <!-- TURN HANDOFF OVERLAY (pass and play) -->
    <TurnHandoffOverlay
      v-if="showTurnHandoff"
      :playerNumber="turnHandoffPlayerNumber"
      :characterName="turnHandoffCharacterName"
      @ready="onHandoffReady"
    />

    <!-- SELECTING PHASE -->
    <template v-if="gameData.round_phase === 'selecting' && !showTurnHandoff">
      <!-- Online waiting overlay -->
      <WaitingOverlay
        v-if="isOnline && waitingForOthers"
        :playerStatus="gameData.player_status || []"
      />

      <template v-else>
        <CardSelectionHand
          v-if="!isSinglePlayer || !currentPlayerHasAssigned"
          :cards="currentHand"
          :hasAssigned="currentPlayerHasAssigned"
          :loading="handLoading"
          @assign="assignRoles"
          @preview="onCardPreview"
        />
      </template>

      <!-- Item Usage Phase (after all assigned, before rolling) -->
      <div v-if="gameData.all_assigned && !allItemsDecided" class="item-phase">
        <template v-if="showItemPrompt">
          <div class="item-prompt-card">
            <p class="item-prompt-title">{{ currentPlayerName }}'s Preparation</p>
            <p class="item-prompt-text" v-if="!itemDeciding">Do you want to use an item before the roll?</p>
            <div v-if="!itemDeciding" class="item-prompt-buttons">
              <button class="btn-primary" @click="itemDeciding = true">Use Item</button>
              <button class="btn-secondary" :disabled="usingItem" @click="skipItem">No, skip</button>
            </div>
            <div v-else class="item-selection">
              <p class="item-select-label">Choose an item to use:</p>
              <div v-for="pi in usableItems" :key="pi.id" class="item-use-card" @click="useItem(pi.id)">
                <span class="item-use-name">{{ pi.item?.name }}</span>
                <span class="item-use-effect">{{ itemEffectLabel(pi.item) }}</span>
              </div>
              <button class="btn-secondary" @click="itemDeciding = false">Cancel</button>
            </div>
          </div>
        </template>
        <template v-else-if="isOnline">
          <div class="resolve-prompt">
            <p>Waiting for all players to decide on items...</p>
          </div>
        </template>
      </div>

    </template>

    <!-- Roll Dice Button (multi-player only — single player auto-resolves) -->
    <div v-if="gameData.round_phase === 'selecting' && gameData.all_assigned && allItemsDecided && !isSinglePlayer" class="resolve-prompt">
      <p>The council is ready!</p>
      <button class="btn-primary" :disabled="resolving" @click="resolveRound">
        {{ resolving ? 'Rolling...' : 'Roll Dice' }}
      </button>
    </div>

    <!-- RESOLVING PHASE -->
    <template v-if="gameData.round_phase === 'resolving'">
      <RoundResults
        :round="gameData.current_round"
        :totalRounds="gameData.total_rounds"
        :positivePhase="positivePhase"
        :negativePhase="negativePhase"
        :combinedEffects="combinedEffects"
        :eventEffects="eventEffects"
        :specialEffects="specialEffects"
        :gameOver="isGameOver"
        :canAdvance="!isOnline || isHost"
        :players="gameData.game?.players"
        :resumed="resolveResumed"
        @phase-complete="onPhaseComplete"
        @next-round="advanceRound"
      />
    </template>
    </template>
    </template>

    <!-- Game Burger Menu Overlay -->
    <div v-if="showGameMenu" class="game-menu-overlay" @click.self="showGameMenu = false">
      <div class="mobile-menu-panel">
        <button class="mobile-menu-item" @click="showGameMenu = false; openRules()">Rules</button>
        <button class="mobile-menu-item" @click="showGameMenu = false; openTutorial()">Tutorial</button>
        <button class="mobile-menu-item" @click="showGameMenu = false; showSettingsModal = true">Settings</button>
        <button
          v-if="isSinglePlayer || isPassAndPlay || (isOnline && gameData.game.turn_time_limit >= 86400)"
          class="mobile-menu-item game-menu-leave"
          @click="showGameMenu = false; goHome()"
        >Go Home</button>
        <button
          v-if="isOnline && gameData.game.turn_time_limit < 86400"
          class="mobile-menu-item game-menu-quit"
          @click="showGameMenu = false; showQuitConfirm = true"
        >Quit Game</button>
      </div>
    </div>

    <!-- Settings Modal -->
    <SettingsModal :visible="showSettingsModal" @close="showSettingsModal = false" />

    <!-- Quit Game Confirmation -->
    <ConfirmModal
      :visible="showQuitConfirm"
      title="Quit Game"
      message="Are you sure? Your ELO will be affected."
      confirm-text="Quit"
      :dangerous="true"
      @confirm="forfeitGame"
      @cancel="showQuitConfirm = false"
    />

    <!-- Dice Viewer Modal -->
    <div v-if="showDiceViewer && characterDice" class="dice-viewer-overlay" @click.self="showDiceViewer = false">
      <div class="dice-viewer-modal">
        <h3 class="dice-viewer-title">Your Dice</h3>
        <div v-for="(die, i) in characterDice" :key="i" class="dice-viewer-row">
          <span class="dice-viewer-label">Die {{ i + 1 }}</span>
          <div class="dice-viewer-faces">
            <span v-for="(face, j) in die" :key="j" class="dice-viewer-face"
                  :class="{ 'dice-viewer-face-wild': face === 'WILD' }">
              {{ face === 'WILD' ? 'W' : face }}
            </span>
          </div>
        </div>
        <p v-if="characterWildValue" class="dice-viewer-wild-info">
          WILD = {{ characterWildValue }}{{ characterWildAbility ? ' + triggers ' + characterWildAbility : '' }}
        </p>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';
import KingdomStats from './KingdomStats.vue';
import EventBanner from './EventBanner.vue';
import CardSelectionHand from './CardSelectionHand.vue';
import RoundResults from './RoundResults.vue';
import TurnHandoffOverlay from './TurnHandoffOverlay.vue';
import OnlineLobby from './OnlineLobby.vue';
import WaitingOverlay from './WaitingOverlay.vue';
import DuelBoard from './DuelBoard.vue';
import PlayerItems from './PlayerItems.vue';
import EventReveal from './EventReveal.vue';
import ItemReveal from './ItemReveal.vue';
import ItemDiscard from './ItemDiscard.vue';
import DiceOverlay from './DiceOverlay.vue';
import CurseSelectionOverlay from './CurseSelectionOverlay.vue';
import ConfirmModal from './ConfirmModal.vue';
import SettingsModal from './SettingsModal.vue';
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';
import { playSound } from '../sounds';

export default {
  name: 'GameBoard',
  components: { KingdomStats, EventBanner, CardSelectionHand, RoundResults, TurnHandoffOverlay, OnlineLobby, WaitingOverlay, DuelBoard, PlayerItems, EventReveal, ItemReveal, ItemDiscard, DiceOverlay, CurseSelectionOverlay, ConfirmModal, SettingsModal },
  inject: {
    openRules: { default: () => () => {} },
    openTutorial: { default: () => () => {} },
    setActiveGameType: { default: () => () => {} },
  },
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast, playSound };
  },
  props: {
    id: { type: [String, Number], required: true },
  },
  data() {
    return {
      gameData: null,
      loading: true,
      activePlayerNumber: 1,
      currentHand: [],
      handLoading: false,
      resolving: false,
      positivePhase: {},
      negativePhase: {},
      combinedEffects: {},
      eventEffects: {},
      specialEffects: [],
      isGameOver: false,
      gameAfterPositive: null,
      gameAfterNegative: null,
      preResolveGame: null, // snapshot of game stats before resolution
      currentStatPhase: null, // null, 'positive', or 'negative'
      showTurnHandoff: false,
      turnHandoffPlayerNumber: null,
      // Items & Dice
      currentPlayerItems: [],
      diceCount: 3,
      duelItemCount: 0,
      duelDiceCount: 4,
      showDiceViewer: false,
      characterDice: null,
      characterWildValue: null,
      characterWildAbility: null,
      // Event reveal
      showEventReveal: false,
      // Resolve restoration
      resolveResumed: false,
      // Item reveal
      itemRevealQueue: [],
      showItemReveal: false,
      // Connection monitoring
      connectionStatus: 'connected',
      // Item discard (when over 2-item limit)
      itemsOverLimit: [],
      // Item phase
      itemDeciding: false,
      usingItem: false,
      currentItemPlayer: null,
      allItemsDecided: false,
      // Online mode
      myPlayerNumber: null,
      waitingForOthers: false,
      echoChannel: null,
      // Curses
      pendingCurses: null,
      playerCurses: {},
      showCurseDetails: false,
      showCurseSelection: false,
      // Card effect preview
      cardPreviewEffects: null,
      // Burger menu
      showGameMenu: false,
      showSettingsModal: false,
      showQuitConfirm: false,
    };
  },
  computed: {
    activeCurseCount() {
      let count = 0;
      for (const pn in this.playerCurses) {
        const curses = this.playerCurses[pn];
        if (Array.isArray(curses)) count += curses.length;
      }
      return count;
    },
    monthLabel() {
      const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
      ];
      const round = this.gameData?.current_round || 1;
      const monthIndex = (round - 1) % 12;
      const yearOffset = Math.floor((round - 1) / 12);
      const total = this.gameData?.total_rounds || 24;
      return `${months[monthIndex]}, ${1280 + yearOffset} AD (${round}/${total})`;
    },
    phaseLabel() {
      const phases = {
        selecting: "The Lord's Council",
        resolving: 'Month Resolution',
        dealing: 'Dealing...',
        complete: 'Game Over',
      };
      return phases[this.gameData?.round_phase] || '';
    },
    currentPlayerHasAssigned() {
      if (!this.gameData?.player_status) return false;
      const ps = this.gameData.player_status.find(
        p => p.player_number === this.activePlayerNumber
      );
      return ps?.has_assigned || false;
    },
    currentPlayerName() {
      if (!this.gameData?.player_status) return 'Player';
      const ps = this.gameData.player_status.find(
        p => p.player_number === this.activePlayerNumber
      );
      return ps?.character_name || `Player ${this.activePlayerNumber}`;
    },
    isPassAndPlay() {
      return this.gameData?.game_mode === 'pass_and_play';
    },
    isOnline() {
      return this.gameData?.game_mode === 'online';
    },
    isSinglePlayer() {
      return this.gameData?.game_mode === 'single' || !this.gameData?.game_mode;
    },
    isDuel() {
      return (this.gameData?.game_type || this.gameData?.game?.game_type) === 'duel';
    },
    duelPhaseLabel() {
      const phases = {
        choosing: 'Selecting Cards',
        rolling: 'Rolling Dice',
        rolling_offerer: 'Player 1 Rolling',
        rolling_chooser: 'Player 2 Rolling',
        resolving: 'Month Resolution',
      };
      const dp = this.gameData?.duel_phase || this.gameData?.game?.duel_phase;
      return phases[dp] || 'Duel';
    },
    isHost() {
      return this.gameData?.game?.user_id === this.auth.state.user?.id;
    },
    turnHandoffCharacterName() {
      if (!this.turnHandoffPlayerNumber || !this.gameData?.player_status) return '';
      const ps = this.gameData.player_status.find(
        p => p.player_number === this.turnHandoffPlayerNumber
      );
      return ps?.character_name || `Player ${this.turnHandoffPlayerNumber}`;
    },
    myKingdomStyleSlug() {
      const userId = this.auth.state.user?.id;
      const player = this.gameData?.game?.players?.find(p => p.user_id === userId);
      return player?.user?.active_kingdom_style_slug || 'classic';
    },
    myKingdomStyleData() {
      const userId = this.auth.state.user?.id;
      const player = this.gameData?.game?.players?.find(p => p.user_id === userId);
      return player?.user?.active_kingdom_style || null;
    },
    showItemPrompt() {
      if (!this.gameData?.all_assigned) return false;
      if (this.allItemsDecided) return false;
      // Check if current active player has items and hasn't decided
      const ps = this.gameData.player_status?.find(p => p.player_number === this.activePlayerNumber);
      if (!ps) return false;
      if (ps.item_decided) return false;
      if (!ps.has_items) return false;
      // Also check client-side that there are usable items
      if (this.usableItems.length === 0) return false;
      return true;
    },
    usableItems() {
      const round = this.gameData?.current_round || 0;
      return this.currentPlayerItems.filter(pi => !pi.is_used && !(pi.used_round && pi.used_round === round));
    },
    displayGame() {
      // Before any phase is accepted, show pre-resolution stats
      if (this.currentStatPhase === null && this.preResolveGame) {
        return this.preResolveGame;
      }
      if (this.currentStatPhase === 'positive' && this.gameAfterPositive) {
        return { ...this.gameData.game, ...this.gameAfterPositive };
      }
      if (this.currentStatPhase === 'negative' && this.gameAfterNegative) {
        return { ...this.gameData.game, ...this.gameAfterNegative };
      }
      return this.gameData?.game || {};
    },
  },
  async mounted() {
    await this.fetchGame();
    this.setActiveGameType(this.isDuel ? 'duel' : 'cooperative');
    if (this.isOnline && !this.echoChannel) {
      this.setupOnlinePlayer();
      this.subscribeToGameChannel();
    }
    // Reconnection: visibility change
    document.addEventListener('visibilitychange', this.onVisibilityChange);
    // Reconnection: Pusher connection monitoring
    this.setupConnectionMonitoring();
  },
  beforeUnmount() {
    this.setActiveGameType(null);
    if (this.echoChannel) {
      window.Echo.leave(`game.${this.id}`);
      this.echoChannel = null;
    }
    document.removeEventListener('visibilitychange', this.onVisibilityChange);
  },
  methods: {
    onGameDataUpdated(data) {
      this.gameData = data;
    },
    onPhaseUpdated(duelPhase) {
      if (this.gameData) {
        this.gameData.duel_phase = duelPhase;
        if (this.gameData.game) {
          this.gameData.game.duel_phase = duelPhase;
        }
      }
    },
    onVisibilityChange() {
      if (document.visibilityState === 'visible' && this.isOnline) {
        this.fetchGameWithRetry();
        // Resubscribe if channel lost
        if (!this.echoChannel) {
          this.subscribeToGameChannel();
        }
      }
    },
    setupConnectionMonitoring() {
      try {
        const pusher = window.Echo?.connector?.pusher;
        if (!pusher) return;
        pusher.connection.bind('state_change', (states) => {
          this.connectionStatus = states.current;
          // On reconnect, re-fetch game state
          if (states.current === 'connected' && states.previous !== 'connected' && this.isOnline) {
            this.fetchGameWithRetry();
          }
        });
      } catch {
        // Pusher not available
      }
    },
    async fetchGameWithRetry(attempt = 0) {
      const maxAttempts = 5;
      try {
        const res = await axios.get(`/api/games/${this.id}`);
        this.gameData = res.data;
      } catch {
        if (attempt < maxAttempts && this.isOnline) {
          const delay = Math.pow(2, attempt) * 1000; // 1s, 2s, 4s, 8s, 16s
          setTimeout(() => this.fetchGameWithRetry(attempt + 1), delay);
        }
      }
    },
    async fetchGame() {
      // Only show loading spinner on initial load, not on refreshes
      if (!this.gameData) {
        this.loading = true;
      }
      try {
        const res = await axios.get(`/api/games/${this.id}`);
        this.gameData = res.data;

        // Restore pending curses and active curses from server state
        this.pendingCurses = res.data.pending_curses || null;
        this.playerCurses = res.data.player_curses || {};

        if (this.gameData.game.status === 'completed' || this.gameData.game.status === 'cancelled') {
          this.$router.replace(`/game/${this.id}/over`);
          return;
        }

        // Game stuck in setup (non-online): redirect back to character selection
        if (this.gameData.game.status === 'setup' && !this.isOnline) {
          this.$router.replace(`/?resume=${this.id}`);
          return;
        }

        // Check for event reveal (round 1, 8, 15, 22, ...)
        this.checkEventReveal();

        // Duel mode: DuelBoard handles its own state — skip cooperative hand loading
        if (this.isDuel) {
          // Just ensure online setup
          if (this.isOnline) {
            this.setupOnlinePlayer();
            if (!this.echoChannel) {
              this.subscribeToGameChannel();
            }
          }
          this.loading = false;
          return;
        }

        // If in selecting phase, load current player's hand
        if (this.gameData.round_phase === 'selecting') {
          // Update allItemsDecided from server state
          this.allItemsDecided = this.gameData.all_items_decided ?? false;

          // If all assigned and all items decided, show Roll button (don't auto-resolve)
          if (this.gameData.all_assigned && this.allItemsDecided && (this.isSinglePlayer || this.isPassAndPlay)) {
            // Don't auto-resolve — player must click "Roll Dice"
          }

          if (this.isOnline) {
            this.setupOnlinePlayer();
            // Check if I already assigned
            const myStatus = this.gameData.player_status?.find(
              p => p.player_number === this.myPlayerNumber
            );
            if (myStatus?.has_assigned) {
              this.waitingForOthers = true;
            } else if (this.myPlayerNumber) {
              await this.loadHand(this.myPlayerNumber);
            }
          } else if (this.isPassAndPlay) {
            // Show handoff overlay for first unassigned player
            const firstUnassigned = this.gameData.player_status?.find(p => !p.has_assigned);
            if (firstUnassigned) {
              this.activePlayerNumber = firstUnassigned.player_number;
              this.turnHandoffPlayerNumber = firstUnassigned.player_number;
              this.showTurnHandoff = true;
              // Don't load hand yet — wait for "Ready"
            } else {
              await this.loadHand(this.activePlayerNumber);
            }
          } else {
            await this.loadHand(this.activePlayerNumber);
          }
        }

        // If in resolving phase, restore full resolve state or reconstruct from server
        if (this.gameData.round_phase === 'resolving') {
          if (!this.restoreResolveState()) {
            await this.loadRoundResults();
            this.resolveResumed = true;
          }
        }
      } catch (e) {
        this.toast.error('Failed to load game: ' + (e.response?.data?.message || e.message));
      }
      this.loading = false;
    },
    async loadHand(playerNumber) {
      this.handLoading = true;
      try {
        const res = await axios.get(`/api/games/${this.id}/hand/${playerNumber}`);
        this.currentHand = res.data.cards;
        this.currentPlayerItems = res.data.items || [];
        this.diceCount = res.data.dice_count ?? 3;
        this.characterDice = res.data.character_dice || null;
        this.characterWildValue = res.data.character_wild_value || null;
        this.characterWildAbility = res.data.character_wild_ability || null;
      } catch (e) {
        this.currentHand = [];
        this.currentPlayerItems = [];
      }
      this.handLoading = false;
    },
    async loadRoundResults() {
      // Results were stored from resolveRound, but if page refreshes
      // reconstruct from game state
      if (!this.positivePhase.cards && this.gameData.round_results) {
        const result = this.gameData.round_results[0];
        if (result) {
          this.positivePhase = {
            cards: [],
            total_difficulty: result.stat_totals?.total_difficulty || 0,
            total_roll: result.stat_totals?.total_roll || 0,
            dice_results: result.dice_results || [],
            wild_triggers: result.wild_triggers || [],
            ability_effects: [],
            success: result.success,
            effects: result.effects_applied || {},
          };
          this.negativePhase = { cards: [], effects: {} };
        }
      }
    },
    onCardPreview(effects) {
      this.cardPreviewEffects = effects;
    },
    async assignRoles({ positive_hand_id, negative_hand_ids }) {
      this.cardPreviewEffects = null;
      try {
        const res = await axios.post(`/api/games/${this.id}/assign-roles`, {
          positive_hand_id,
          negative_hand_ids,
        });

        // Online mode: handle auto-resolve or show waiting
        if (this.isOnline) {
          if (res.data.auto_resolved) {
            // Auto-resolved via server — RoundResolved broadcast handles the rest
            // But also apply locally for the player who triggered it
            const data = res.data.resolve_data;
            this.preResolveGame = { ...this.gameData.game };
            this.positivePhase = data.positive_phase;
            this.negativePhase = data.negative_phase;
            this.combinedEffects = data.combined_effects;
            this.eventEffects = data.event_effects;
            this.specialEffects = data.special_effects || [];
            this.isGameOver = data.game_over;
            this.gameAfterPositive = data.game_after_positive;
            this.gameAfterNegative = data.game_after_negative;
            this.currentStatPhase = null;
            this.gameData.game = data.game;
            this.gameData.round_phase = 'resolving';
            this.waitingForOthers = false;
            this.saveResolveState();
          } else {
            // Show waiting overlay
            this.waitingForOthers = true;
            // Refresh to get latest player_status
            const gameRes = await axios.get(`/api/games/${this.id}`);
            this.gameData = gameRes.data;
          }
          return;
        }

        // Refresh game state
        const gameRes = await axios.get(`/api/games/${this.id}`);
        this.gameData = gameRes.data;

        // All assigned: start item decision phase or auto-resolve
        if (res.data.all_assigned) {
          this.allItemsDecided = this.gameData.all_items_decided ?? false;
          if (!this.allItemsDecided) {
            this.startItemPhase();
          } else if (this.isSinglePlayer) {
            // Single player: skip "The council is ready" prompt, auto-resolve
            await this.resolveRound();
          }
          return;
        }

        // Find next unassigned player
        if (!res.data.all_assigned && this.gameData.player_status) {
          const next = this.gameData.player_status.find(p => !p.has_assigned);
          if (next) {
            this.activePlayerNumber = next.player_number;
            if (this.isPassAndPlay) {
              // Show handoff overlay instead of immediately loading hand
              this.turnHandoffPlayerNumber = next.player_number;
              this.showTurnHandoff = true;
            } else {
              await this.loadHand(next.player_number);
            }
          }
        } else {
          await this.loadHand(this.activePlayerNumber);
        }
      } catch (e) {
        this.toast.error('Failed to assign: ' + (e.response?.data?.error || e.message));
      }
    },
    async resolveRound() {
      this.resolving = true;
      try {
        // Snapshot current stats before resolution
        this.preResolveGame = { ...this.gameData.game };
        const res = await axios.post(`/api/games/${this.id}/resolve-round`);
        this.positivePhase = res.data.positive_phase;
        this.negativePhase = res.data.negative_phase;
        this.combinedEffects = res.data.combined_effects;
        this.eventEffects = res.data.event_effects;
        this.specialEffects = res.data.special_effects || [];
        this.isGameOver = res.data.game_over;
        this.gameAfterPositive = res.data.game_after_positive;
        this.gameAfterNegative = res.data.game_after_negative;
        this.currentStatPhase = null; // stats stay at pre-resolve until accepted
        this.gameData.game = res.data.game;
        this.gameData.round_phase = 'resolving';
        // Refresh items after resolution (new items may have been granted)
        if (res.data.player_items && res.data.player_items[this.activePlayerNumber]) {
          this.currentPlayerItems = res.data.player_items[this.activePlayerNumber];
        }
        // Check for players over item limit
        this.itemsOverLimit = res.data.items_over_limit || [];
        // Queue item reveals for any draw_item special effects
        this.queueItemReveals(this.specialEffects);
        // Process pending curses
        this.pendingCurses = res.data.pending_curses || null;
        this.playerCurses = res.data.player_curses || {};
        // Persist resolve state for page refresh recovery
        this.saveResolveState();
      } catch (e) {
        this.toast.error('Failed to resolve: ' + (e.response?.data?.error || e.message));
      }
      this.resolving = false;
    },
    saveResolveState() {
      try {
        const round = this.gameData.current_round || this.gameData.game.current_round;
        sessionStorage.setItem(`game_${this.id}_resolve_${round}`, JSON.stringify({
          positivePhase: this.positivePhase,
          negativePhase: this.negativePhase,
          combinedEffects: this.combinedEffects,
          eventEffects: this.eventEffects,
          specialEffects: this.specialEffects,
          isGameOver: this.isGameOver,
          gameAfterPositive: this.gameAfterPositive,
          gameAfterNegative: this.gameAfterNegative,
        }));
      } catch { /* sessionStorage full or unavailable */ }
    },
    restoreResolveState() {
      try {
        const round = this.gameData.current_round || this.gameData.game?.current_round;
        const saved = sessionStorage.getItem(`game_${this.id}_resolve_${round}`);
        if (saved) {
          const data = JSON.parse(saved);
          this.positivePhase = data.positivePhase;
          this.negativePhase = data.negativePhase;
          this.combinedEffects = data.combinedEffects;
          this.eventEffects = data.eventEffects;
          this.specialEffects = data.specialEffects;
          this.isGameOver = data.isGameOver;
          this.gameAfterPositive = data.gameAfterPositive;
          this.gameAfterNegative = data.gameAfterNegative;
          this.resolveResumed = true;
          return true;
        }
      } catch { /* ignore */ }
      return false;
    },
    clearResolveState(round) {
      try {
        sessionStorage.removeItem(`game_${this.id}_resolve_${round}`);
      } catch { /* ignore */ }
    },
    startItemPhase() {
      // Determine which player needs to decide on items
      if (this.isPassAndPlay) {
        const firstUndecided = this.gameData.player_status?.find(
          p => p.has_items && !p.item_decided
        );
        if (firstUndecided) {
          this.activePlayerNumber = firstUndecided.player_number;
          this.turnHandoffPlayerNumber = firstUndecided.player_number;
          this.showTurnHandoff = true;
        } else {
          // All decided or no items
          this.allItemsDecided = true;
        }
      } else if (this.isSinglePlayer) {
        // Single player: check if they have items
        const ps = this.gameData.player_status?.find(p => p.player_number === this.activePlayerNumber);
        if (!ps?.has_items) {
          this.allItemsDecided = true;
          // Auto-resolve immediately
          this.resolveRound();
        }
        // Otherwise, item prompt shows via showItemPrompt computed
      }
      // Online: each player sees their own prompt via showItemPrompt
    },
    async useItem(gamePlayerItemId) {
      this.usingItem = true;
      try {
        const res = await axios.post(`/api/games/${this.id}/use-item`, {
          game_player_item_id: gamePlayerItemId,
          player_number: this.activePlayerNumber,
        });
        this.itemDeciding = false;
        this.allItemsDecided = res.data.all_items_decided;
        // Update items
        if (res.data.player_items) {
          this.currentPlayerItems = res.data.player_items;
        }
        // Update player_status
        if (this.gameData.player_status) {
          const ps = this.gameData.player_status.find(p => p.player_number === this.activePlayerNumber);
          if (ps) ps.item_decided = true;
        }
        // Pass-and-play: advance to next player or show roll
        if (this.isPassAndPlay && !this.allItemsDecided) {
          this.advanceItemPlayer();
        } else if (this.isSinglePlayer && this.allItemsDecided) {
          this.usingItem = false;
          await this.resolveRound();
          return;
        }
      } catch (e) {
        this.toast.error('Failed to use item: ' + (e.response?.data?.error || e.message));
      }
      this.usingItem = false;
    },
    async skipItem() {
      this.usingItem = true;
      try {
        const res = await axios.post(`/api/games/${this.id}/skip-item`, {
          player_number: this.activePlayerNumber,
        });
        this.itemDeciding = false;
        this.allItemsDecided = res.data.all_items_decided;
        // Update player_status
        if (this.gameData.player_status) {
          const ps = this.gameData.player_status.find(p => p.player_number === this.activePlayerNumber);
          if (ps) ps.item_decided = true;
        }
        // Pass-and-play: advance to next player or show roll
        if (this.isPassAndPlay && !this.allItemsDecided) {
          this.advanceItemPlayer();
        } else if (this.isSinglePlayer && this.allItemsDecided) {
          this.usingItem = false;
          await this.resolveRound();
          return;
        }
      } catch (e) {
        this.toast.error('Failed to skip item: ' + (e.response?.data?.error || e.message));
      }
      this.usingItem = false;
    },
    advanceItemPlayer() {
      const nextUndecided = this.gameData.player_status?.find(
        p => p.has_items && !p.item_decided
      );
      if (nextUndecided) {
        this.activePlayerNumber = nextUndecided.player_number;
        this.turnHandoffPlayerNumber = nextUndecided.player_number;
        this.showTurnHandoff = true;
        this.loadHand(nextUndecided.player_number);
      } else {
        this.allItemsDecided = true;
      }
    },
    itemEffectLabel(item) {
      if (!item?.effect) return '';
      const type = item.effect.bonus_type || '';
      const value = item.effect.bonus_value ?? 0;
      switch (type) {
        case 'roll_bonus': return `+${value} to roll`;
        case 'roll_penalty': return `${value} to roll`;
        case 'difficulty_reduction': return `-${Math.abs(value)} difficulty`;
        case 'difficulty_increase': return `+${Math.abs(value)} difficulty`;
        case 'stat_boost': return `+${value} ${item.effect.stat || 'stat'}`;
        case 'heal_die': return 'Recover a lost die';
        case 'score_bonus': return `${value > 0 ? '+' : ''}${value} renown`;
        default: return item.description || 'Use this item';
      }
    },
    setupOnlinePlayer() {
      const userId = this.auth.state.user?.id;
      if (!userId || !this.gameData?.game?.players) return;
      const myPlayer = this.gameData.game.players.find(p => p.user_id === userId);
      if (myPlayer) {
        this.myPlayerNumber = myPlayer.player_number;
        this.activePlayerNumber = myPlayer.player_number;
      }
    },
    subscribeToGameChannel() {
      if (!window.Echo) return;
      this.echoChannel = window.Echo.private(`game.${this.id}`)
        .listen('PlayerJoinedGame', () => {
          if (this.$refs.lobby) {
            this.$refs.lobby.fetchLobby();
          }
        })
        .listen('PlayerSelectedCharacter', (data) => {
          if (this.$refs.lobby) {
            this.$refs.lobby.fetchLobby().then(() => {
              if (data.all_selected && this.$refs.lobby) {
                this.$refs.lobby.autoStartGame();
              }
            });
          }
        })
        .listen('PlayerAssignedCards', (data) => {
          // Update player status
          if (this.gameData?.player_status) {
            const ps = this.gameData.player_status.find(p => p.player_number === data.player_number);
            if (ps) ps.has_assigned = true;
            this.gameData.all_assigned = data.all_assigned;
          }
        })
        .listen('PlayerItemDecided', (data) => {
          // Update item decided status
          if (this.gameData?.player_status) {
            const ps = this.gameData.player_status.find(p => p.player_number === data.player_number);
            if (ps) ps.item_decided = true;
          }
          this.allItemsDecided = data.all_decided;
        })
        .listen('RoundResolved', (data) => {
          // All players receive resolve data simultaneously
          this.preResolveGame = { ...this.gameData.game };
          this.positivePhase = data.positive_phase;
          this.negativePhase = data.negative_phase;
          this.combinedEffects = data.combined_effects;
          this.eventEffects = data.event_effects;
          this.specialEffects = data.special_effects || [];
          this.isGameOver = data.game_over;
          this.gameAfterPositive = data.game_after_positive;
          this.gameAfterNegative = data.game_after_negative;
          this.currentStatPhase = null;
          this.gameData.game = data.game;
          this.gameData.round_phase = 'resolving';
          // Refresh items after resolution
          if (data.player_items && data.player_items[this.activePlayerNumber]) {
            this.currentPlayerItems = data.player_items[this.activePlayerNumber];
          }
          this.queueItemReveals(data.special_effects || []);
          this.waitingForOthers = false;
          this.resolving = false;
          this.saveResolveState();
        })
        .listen('NextRoundStarted', (data) => {
          if (this.isDuel && this.$refs.duelBoard) {
            this.gameData = data;
            this.$refs.duelBoard.handleNextRoundStarted(data);
            return;
          }
          // Clear saved resolve state for the round being advanced from
          this.clearResolveState(this.gameData.current_round || this.gameData.game?.current_round);
          // Non-host auto-transitions to next round
          this.positivePhase = {};
          this.negativePhase = {};
          this.combinedEffects = {};
          this.eventEffects = {};
          this.specialEffects = [];
          this.isGameOver = false;
          this.gameAfterPositive = null;
          this.gameAfterNegative = null;
          this.preResolveGame = null;
          this.currentStatPhase = null;
          this.waitingForOthers = false;
          this.itemDeciding = false;
          this.usingItem = false;
          this.allItemsDecided = false;
          this.resolveResumed = false;
          this.gameData = data;
          this.checkEventReveal();
          if (this.myPlayerNumber) {
            this.activePlayerNumber = this.myPlayerNumber;
            this.loadHand(this.myPlayerNumber);
          }
        })
        .listen('GameStarted', () => {
          this.fetchGame();
        })
        // Duel events
        .listen('DuelChoiceMade', (data) => {
          if (this.$refs.duelBoard) {
            this.$refs.duelBoard.handleDuelChoiceMade(data);
          }
        })
        .listen('DuelRollComplete', (data) => {
          if (this.$refs.duelBoard) {
            this.$refs.duelBoard.handleDuelRollComplete(data);
          }
        })
        .listen('DuelGameOver', (data) => {
          if (data.completion) {
            sessionStorage.setItem(`game_completion_${this.id}`, JSON.stringify(data.completion));
          }
          if (data.timed_out_player_number != null) {
            sessionStorage.setItem(`game_timeout_${this.id}`, JSON.stringify(data.timed_out_player_number));
          }
          this.$router.replace(`/game/${this.id}/over`);
        });
    },
    async startOnlineGame() {
      try {
        // Fetch lobby to get current player/character assignments
        const lobbyRes = await axios.get(`/api/games/${this.id}/lobby`);
        const players = lobbyRes.data.players;
        const characters = players
          .sort((a, b) => a.player_number - b.player_number)
          .map(p => p.character_id);

        await axios.post(`/api/games/${this.id}/start`, { characters });

        // Broadcast game started
        await this.fetchGame();
      } catch (e) {
        this.toast.error('Failed to start: ' + (e.response?.data?.error || e.message));
      }
    },
    queueItemReveals(specialEffects) {
      const itemEffects = (specialEffects || []).filter(e => (e.type === 'draw_item' || e.type === 'item_blocked') && e.item);
      if (itemEffects.length) {
        this.itemRevealQueue = [...itemEffects];
        this.showItemReveal = true;
      }
    },
    onItemRevealDismiss() {
      this.itemRevealQueue.shift();
      if (this.itemRevealQueue.length === 0) {
        this.showItemReveal = false;
      }
    },
    currentPendingCurse() {
      if (!this.pendingCurses || !this.pendingCurses.length) return null;
      // Find the first pending curse for the active player
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.activePlayerNumber);
      if (!player) return null;
      return this.pendingCurses.find(pc => pc.player_id === player.id);
    },
    async onCurseSelected(curseId) {
      try {
        const res = await axios.post(`/api/games/${this.id}/choose-curse`, {
          curse_id: curseId,
          player_number: this.activePlayerNumber,
        });
        this.pendingCurses = res.data.pending_curses || null;
        if (res.data.player_curses) {
          this.playerCurses = {
            ...this.playerCurses,
            [this.activePlayerNumber]: res.data.player_curses,
          };
        }
        // When all curses resolved, proceed to next round
        if (!this.pendingCurses || this.pendingCurses.length === 0) {
          this.showCurseSelection = false;
          await this.advanceRound();
        }
      } catch (e) {
        this.toast.error('Failed to choose curse: ' + (e.response?.data?.error || e.message));
      }
    },
    showDuelDiceViewer() {
      // Open the character modal for the active player's character in duel mode
      const duelBoard = this.$refs.duelBoard;
      if (duelBoard) {
        const playerNum = duelBoard.activePlayerNumber;
        const player = this.gameData?.game?.players?.find(p => p.player_number === playerNum);
        if (player?.character) {
          duelBoard.openCharacterModal(playerNum);
        }
      }
    },
    async goHome() {
      try {
        await axios.post(`/api/games/${this.id}/cancel`);
      } catch {
        // ignore cancel errors
      }
      this.$router.push('/');
    },
    async forfeitGame() {
      this.showQuitConfirm = false;
      try {
        await axios.post(`/api/games/${this.id}/forfeit`);
        this.$router.replace(`/game/${this.id}/over`);
      } catch (e) {
        this.toast.error('Failed to forfeit: ' + (e.response?.data?.error || e.message));
      }
    },
    onItemDiscarded(updatedOverLimit) {
      this.itemsOverLimit = updatedOverLimit || [];
    },
    checkEventReveal() {
      const round = this.gameData?.current_round || 0;
      const event = this.gameData?.current_event;
      if (event && (round - 1) % 3 === 0) {
        const key = `game_${this.id}_event_${event.id}`;
        if (!sessionStorage.getItem(key)) {
          sessionStorage.setItem(key, '1');
          this.showEventReveal = true;
        }
      }
    },
    async onHandoffReady() {
      this.showTurnHandoff = false;
      await this.loadHand(this.activePlayerNumber);
    },
    onPhaseComplete(phase) {
      this.currentStatPhase = phase;
    },
    async advanceRound() {
      // Show curse selection first if there are pending curses
      if (this.pendingCurses && this.pendingCurses.length > 0) {
        this.showCurseSelection = true;
        return;
      }
      try {
        const res = await axios.post(`/api/games/${this.id}/next-round`);

        if (res.data.game_over) {
          if (res.data.completion) {
            sessionStorage.setItem(`game_completion_${this.id}`, JSON.stringify(res.data.completion));
          }
          if (res.data.score_breakdown) {
            sessionStorage.setItem(`game_score_breakdown_${this.id}`, JSON.stringify(res.data.score_breakdown));
          }
          this.$router.replace(`/game/${this.id}/over`);
          return;
        }

        // Clear saved resolve state for this round before resetting
        this.clearResolveState(this.gameData.current_round || this.gameData.game?.current_round);

        // Reset state for next round
        this.positivePhase = {};
        this.negativePhase = {};
        this.combinedEffects = {};
        this.eventEffects = {};
        this.specialEffects = [];
        this.itemRevealQueue = [];
        this.showItemReveal = false;
        this.itemsOverLimit = [];
        this.isGameOver = false;
        this.gameAfterPositive = null;
        this.gameAfterNegative = null;
        this.preResolveGame = null;
        this.currentStatPhase = null;
        this.showCurseSelection = false;
        this.itemDeciding = false;
        this.usingItem = false;
        this.allItemsDecided = false;
        this.resolveResumed = false;
        this.gameData = res.data;
        this.activePlayerNumber = 1;
        this.checkEventReveal();
        if (this.isPassAndPlay) {
          this.turnHandoffPlayerNumber = 1;
          this.showTurnHandoff = true;
        } else {
          await this.loadHand(1);
        }
      } catch (e) {
        this.toast.error('Failed to advance: ' + (e.response?.data?.error || e.message));
        await this.fetchGame();
      }
    },
  },
};
</script>

<style scoped>
/* Curse indicator */
.curse-indicator {
  position: fixed;
  bottom: 80px;
  right: 16px;
  background: rgba(40, 0, 60, 0.9);
  border: 1px solid rgba(155, 89, 182, 0.5);
  color: #c890e0;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  cursor: pointer;
  z-index: 50;
  transition: all 0.2s;
}
.curse-indicator:hover {
  border-color: #9b59b6;
  background: rgba(60, 0, 90, 0.95);
}
.curse-details-panel {
  position: fixed;
  bottom: 120px;
  right: 16px;
  background: rgba(20, 5, 30, 0.95);
  border: 1px solid rgba(155, 89, 182, 0.4);
  border-radius: 8px;
  padding: 12px;
  z-index: 50;
  max-width: 280px;
  font-size: 0.8rem;
}
.curse-detail-row {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 4px 0;
  border-bottom: 1px solid rgba(155, 89, 182, 0.15);
}
.curse-detail-name { color: #c890e0; font-weight: 700; flex: 1; }
.curse-detail-neg { color: #c0392b; font-size: 0.7rem; }
.curse-detail-pos { color: #d4a843; font-size: 0.7rem; }

.connection-banner {
  text-align: center;
  padding: 6px 12px;
  font-size: 0.8rem;
  font-family: 'Cinzel', serif;
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
  font-family: 'Cinzel', serif;
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
  font-family: 'Cinzel', serif;
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
  margin: 12px 0;
}

.item-prompt-card {
  background: var(--bg-secondary, #2a1f14);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 10px;
  padding: 20px;
  text-align: center;
}

.item-prompt-title {
  font-family: 'Cinzel', serif;
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
  font-family: 'Cinzel', serif;
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
  font-family: 'Cinzel', serif;
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
  font-family: 'Cinzel', serif;
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
  background: linear-gradient(180deg, var(--wood-light, #463220), var(--wood-dark, #1e160c));
  border: 3px solid var(--border-gold, #c8952e);
  border-radius: 14px;
  padding: 10px 0;
  min-width: 220px;
  box-shadow: 0 4px 0 rgba(0,0,0,0.3), 0 10px 40px rgba(0,0,0,0.7);
}

.mobile-menu-item {
  display: block;
  width: 100%;
  padding: 14px 28px;
  background: none;
  border: none;
  border-radius: 0;
  color: var(--text-primary, #f0e0c8);
  font-family: 'Cinzel', serif;
  font-size: 1.05rem;
  font-weight: 700;
  text-align: center;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
  letter-spacing: 1px;
  text-shadow: 0 1px 2px rgba(0,0,0,0.4);
  box-shadow: none;
}

.mobile-menu-item:hover {
  background: rgba(240,192,80,0.1);
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
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.7), 0 0 20px rgba(212, 168, 67, 0.15);
}

.dice-viewer-title {
  font-family: 'Cinzel', serif;
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
  font-family: 'Cinzel', serif;
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
