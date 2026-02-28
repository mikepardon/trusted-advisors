<template>
  <div v-if="loading" class="loading">Loading game state...</div>
  <div v-else-if="gameData" class="game-board">
    <!-- ONLINE LOBBY (setup phase) -->
    <template v-if="isOnline && gameData.game.status === 'setup'">
      <OnlineLobby
        ref="lobby"
        :gameId="id"
        :hostId="gameData.game.user_id"
        @start-game="startOnlineGame"
        @lobby-updated="fetchGame"
      />
    </template>

    <template v-else>
    <!-- DUEL MODE -->
    <template v-if="isDuel">
    <div class="time-bar">
      <div class="time-info">
        <span class="round-label">{{ monthLabel }}</span>
        <span class="phase-label">{{ duelPhaseLabel }}</span>
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
    <DuelBoard
      ref="duelBoard"
      :gameData="gameData"
      :gameId="id"
      @refresh="fetchGame"
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
      <div class="progress-info">
        <div class="progress-bar-bg">
          <div
            class="progress-bar"
            :style="{ width: (gameData.current_round / gameData.total_rounds * 100) + '%' }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Kingdom Stats -->
    <KingdomStats :game="displayGame" />

    <!-- Event Banner -->
    <EventBanner :event="gameData.current_event" />

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
          :cards="currentHand"
          :hasAssigned="currentPlayerHasAssigned"
          :loading="handLoading"
          @assign="assignRoles"
        />
      </template>

      <div v-if="gameData.all_assigned && isOnline" class="resolve-prompt">
        <p>The council has made their decisions!</p>
        <button class="btn-primary" :disabled="resolving" @click="resolveRound">
          {{ resolving ? 'Resolving...' : 'See What Happens' }}
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
import { useAuth } from '../stores/auth';

export default {
  name: 'GameBoard',
  components: { KingdomStats, EventBanner, CardSelectionHand, RoundResults, TurnHandoffOverlay, OnlineLobby, WaitingOverlay, DuelBoard },
  setup() {
    const auth = useAuth();
    return { auth };
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
      // Online mode
      myPlayerNumber: null,
      waitingForOthers: false,
      echoChannel: null,
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
        offering: 'Offering Cards',
        choosing: 'Choosing Card',
        rolling_offerer: 'Offerer Rolling',
        rolling_chooser: 'Chooser Rolling',
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
  },
  beforeUnmount() {
    if (this.echoChannel) {
      window.Echo.leave(`game.${this.id}`);
      this.echoChannel = null;
    }
  },
  methods: {
    async fetchGame() {
      this.loading = true;
      try {
        const res = await axios.get(`/api/games/${this.id}`);
        this.gameData = res.data;

        if (this.gameData.game.status === 'completed') {
          this.$router.replace(`/game/${this.id}/over`);
          return;
        }

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
        alert('Failed to load game: ' + (e.response?.data?.message || e.message));
      }
      this.loading = false;
    },
    async loadHand(playerNumber) {
      this.handLoading = true;
      try {
        const res = await axios.get(`/api/games/${this.id}/hand/${playerNumber}`);
        this.currentHand = res.data.cards;
      } catch (e) {
        this.currentHand = [];
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

        // Single player: skip the "See What Happens" prompt, auto-trigger resolve
        if (res.data.all_assigned && this.isSinglePlayer) {
          await this.resolveRound();
          return;
        }

        // Pass and play: auto-resolve once all players have assigned
        if (res.data.all_assigned && this.isPassAndPlay) {
          await this.resolveRound();
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
        alert('Failed to assign: ' + (e.response?.data?.error || e.message));
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
      } catch (e) {
        alert('Failed to resolve: ' + (e.response?.data?.error || e.message));
      }
      this.resolving = false;
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
          this.gameData = data;
          if (this.myPlayerNumber) {
            this.activePlayerNumber = this.myPlayerNumber;
            this.loadHand(this.myPlayerNumber);
          }
        })
        .listen('GameStarted', () => {
          this.fetchGame();
        })
        // Duel events
        .listen('DuelOfferMade', (data) => {
          if (this.$refs.duelBoard) {
            this.$refs.duelBoard.handleDuelOfferMade(data);
          }
        })
        .listen('DuelChoiceMade', (data) => {
          if (this.$refs.duelBoard) {
            this.$refs.duelBoard.handleDuelChoiceMade(data);
          }
        })
        .listen('DuelRollComplete', (data) => {
          if (this.$refs.duelBoard) {
            this.$refs.duelBoard.handleDuelRollComplete(data);
          }
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
        alert('Failed to start: ' + (e.response?.data?.error || e.message));
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
          this.$router.replace(`/game/${this.id}/over`);
          return;
        }

        // Reset state for next round
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
        this.gameData = res.data;
        this.activePlayerNumber = 1;
        if (this.isPassAndPlay) {
          this.turnHandoffPlayerNumber = 1;
          this.showTurnHandoff = true;
        } else {
          await this.loadHand(1);
        }
      } catch (e) {
        alert('Failed to advance: ' + (e.response?.data?.error || e.message));
        await this.fetchGame();
      }
    },
  },
};
</script>

<style scoped>
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
  padding: 12px 20px;
  margin-bottom: 20px;
}

.round-label {
  font-family: 'Cinzel', serif;
  font-size: 1.3rem;
  color: var(--accent-gold);
}

.phase-label {
  margin-left: 15px;
  color: var(--text-secondary);
  font-style: italic;
}

.progress-info {
  min-width: 120px;
}

.progress-bar-bg {
  background: rgba(0, 0, 0, 0.4);
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  border-radius: 4px;
  background: var(--accent-gold);
  transition: width 0.5s ease;
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
    gap: 6px;
    padding: 8px 12px;
    margin-bottom: 10px;
  }

  .round-label {
    font-size: 0.7rem;
  }

  .phase-label {
    margin-left: 8px;
    font-size: 0.85rem;
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
}
</style>
