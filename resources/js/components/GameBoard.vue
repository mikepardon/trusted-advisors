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
      <div class="time-info">
        <span class="round-label">{{ monthLabel }}</span>
        <span class="phase-label">{{ duelPhaseLabel }}</span>
      </div>
      <div class="time-bar-right">
        <div class="progress-info">
          <div class="progress-bar-bg">
            <div
              class="progress-bar"
              :style="{ width: (gameData.current_round / gameData.total_rounds * 100) + '%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>
    <DuelBoard
      ref="duelBoard"
      :gameData="gameData"
      :gameId="id"
      @refresh="fetchGame"
      @game-data-updated="onGameDataUpdated"
      @phase-updated="onPhaseUpdated"
      @game-over="$router.replace(`/game/${id}/over`)"
    />
    </template>

    <!-- COOPERATIVE MODE -->
    <template v-else>
    <!-- Round display -->
    <div class="time-bar">
      <div class="time-info">
        <span class="round-label">{{ monthLabel }}</span>
        <span class="phase-label">{{ phaseLabel }}</span>
      </div>
      <div class="time-bar-right">
        <div class="time-bar-icons">
          <button
            v-if="usableItems.length"
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
          <span class="bar-dice-count" title="Active Dice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bar-icon-svg">
              <rect x="3" y="3" width="18" height="18" rx="3"/>
              <circle cx="8.5" cy="8.5" r="1" fill="currentColor"/>
              <circle cx="15.5" cy="8.5" r="1" fill="currentColor"/>
              <circle cx="12" cy="12" r="1" fill="currentColor"/>
              <circle cx="8.5" cy="15.5" r="1" fill="currentColor"/>
              <circle cx="15.5" cy="15.5" r="1" fill="currentColor"/>
            </svg>
            <span class="dice-num">{{ diceCount }}</span>
          </span>
        </div>
        <div class="progress-info">
          <div class="progress-bar-bg">
            <div
              class="progress-bar"
              :style="{ width: (gameData.current_round / gameData.total_rounds * 100) + '%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick action button above stats for easy access (multi-player only) -->
    <div v-if="gameData.round_phase === 'selecting' && gameData.all_assigned && allItemsDecided && !isSinglePlayer" class="action-btn-top-wrap">
      <button class="btn-primary" :disabled="resolving" @click="resolveRound">
        {{ resolving ? 'Rolling...' : 'Roll Dice' }}
      </button>
    </div>

    <!-- Kingdom Stats -->
    <KingdomStats :game="displayGame" :kingdomStyleSlug="myKingdomStyleSlug" :kingdomStyleData="myKingdomStyleData" />

    <!-- Player Items (overlay only, button in top bar) -->
    <PlayerItems ref="playerItems" :items="currentPlayerItems" :showButton="false" />

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
        />
      </template>

      <!-- Item Usage Phase (after all assigned, before rolling) -->
      <div v-if="gameData.all_assigned && !allItemsDecided" class="item-phase">
        <template v-if="showItemPrompt">
          <div class="item-prompt-card">
            <p class="item-prompt-title">{{ currentPlayerName }}'s Preparation</p>
            <p class="item-prompt-text" v-if="!itemDeciding">Do you want to use an item before the roll?</p>
            <div v-if="!itemDeciding" class="item-prompt-buttons">
              <button class="btn-primary" @click="itemDeciding = true">Yes, choose an item</button>
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

      <!-- Roll Dice Button (multi-player only — single player auto-resolves) -->
      <div v-if="gameData.all_assigned && allItemsDecided && !isSinglePlayer" class="resolve-prompt">
        <p>The council is ready!</p>
        <button class="btn-primary" :disabled="resolving" @click="resolveRound">
          {{ resolving ? 'Rolling...' : 'Roll Dice' }}
        </button>
      </div>
    </template>

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
        @phase-complete="onPhaseComplete"
        @next-round="advanceRound"
      />
    </template>
    </template>
    </template>

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
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';

export default {
  name: 'GameBoard',
  components: { KingdomStats, EventBanner, CardSelectionHand, RoundResults, TurnHandoffOverlay, OnlineLobby, WaitingOverlay, DuelBoard, PlayerItems, EventReveal, ItemReveal, ItemDiscard, DiceOverlay },
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast };
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
      // Event reveal
      showEventReveal: false,
      lastRevealedEventId: null,
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
      // In-game alerts
    };
  },
  computed: {
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
      return true;
    },
    usableItems() {
      return this.currentPlayerItems.filter(pi => !pi.is_used);
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
      this.loading = true;
      try {
        const res = await axios.get(`/api/games/${this.id}`);
        this.gameData = res.data;

        if (this.gameData.game.status === 'completed' || this.gameData.game.status === 'cancelled') {
          this.$router.replace(`/game/${this.id}/over`);
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

        // If in resolving phase, load round results
        if (this.gameData.round_phase === 'resolving') {
          await this.loadRoundResults();
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
    async assignRoles({ positive_hand_id, negative_hand_ids }) {
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
      } catch (e) {
        this.toast.error('Failed to resolve: ' + (e.response?.data?.error || e.message));
      }
      this.resolving = false;
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
        })
        .listen('NextRoundStarted', (data) => {
          if (this.isDuel && this.$refs.duelBoard) {
            this.gameData = data;
            this.$refs.duelBoard.handleNextRoundStarted(data);
            return;
          }
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
    onItemDiscarded(updatedOverLimit) {
      this.itemsOverLimit = updatedOverLimit || [];
    },
    checkEventReveal() {
      const round = this.gameData?.current_round || 0;
      const event = this.gameData?.current_event;
      if (event && (round - 1) % 3 === 0) {
        // Only show if we haven't already revealed this event
        const eventId = event.id;
        if (this.lastRevealedEventId !== eventId) {
          this.lastRevealedEventId = eventId;
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
        this.itemDeciding = false;
        this.usingItem = false;
        this.allItemsDecided = false;
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
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 6px 14px;
  margin-bottom: 14px;
}

.round-label {
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  color: var(--accent-gold);
}

.phase-label {
  margin-left: 10px;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
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

.bar-dice-count {
  display: flex;
  align-items: center;
  gap: 3px;
  color: var(--text-secondary, #a09080);
  font-size: 0.85rem;
}

.dice-num {
  font-family: 'Cinzel', serif;
  font-weight: 700;
  color: var(--text-bright, #f0e6d2);
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
    flex-direction: column;
    gap: 4px;
    padding: 5px 10px;
    margin-bottom: 8px;
  }

  .time-bar-right {
    width: 100%;
    justify-content: space-between;
  }

  .round-label {
    font-size: 0.7rem;
  }

  .phase-label {
    margin-left: 6px;
    font-size: 0.75rem;
  }

  .bar-icon-svg {
    width: 15px;
    height: 15px;
  }

  .progress-info {
    min-width: 80px;
  }

  .progress-info {
    min-width: unset;
    width: 100%;
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
</style>
