<template>
  <div class="duel-board">
    <!-- Kingdom Stats -->
    <DuelKingdomStats
      :playerKingdoms="playerKingdoms"
      :myPlayerNumber="activePlayerNumber"
      :isSinglePlayerDuel="isSinglePlayerDuel"
      @show-character="openCharacterModal"
    />

    <!-- Character Info Modal -->
    <CharacterInfoModal
      v-if="showCharacterModal && selectedCharacterData"
      :character="selectedCharacterData.character"
      :activeDice="selectedCharacterData.activeDice"
      :abilityUses="selectedCharacterData.abilityUses"
      :items="selectedCharacterData.items"
      @close="showCharacterModal = false"
    />

    <!-- Player Items -->
    <PlayerItems ref="playerItems" :items="currentPlayerItems" :showButton="true" />

    <!-- Compact item/dice icons -->
    <div class="duel-bar-icons">
      <button
        v-if="currentPlayerItems.length"
        class="bar-icon-btn"
        title="View Inventory"
        @click="$refs.playerItems?.openOverlay()"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bar-icon-svg">
          <path d="M20 7H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1Z"/>
          <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
        </svg>
        <span class="bar-icon-badge">{{ currentPlayerItems.length }}</span>
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

    <!-- Event Reveal Overlay -->
    <EventReveal
      v-if="showEventReveal && currentEvent"
      :event="currentEvent"
      @dismiss="showEventReveal = false"
    />

    <!-- TURN HANDOFF OVERLAY (pass and play) -->
    <TurnHandoffOverlay
      v-if="showHandoff"
      :playerNumber="handoffPlayerNumber"
      :characterName="handoffCharacterName"
      @ready="onHandoffReady"
    />

    <!-- WAITING OVERLAY (online, not your turn) -->
    <div v-if="showWaiting" class="waiting-overlay">
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
      />
      <div v-if="waitingForOpponentSelection" class="waiting-inline">
        <p>Waiting for opponent to choose their card...</p>
      </div>
    </template>

    <!-- === SIMULTANEOUS ROLLING === -->
    <template v-if="duelPhase === 'rolling' && !showHandoff && !showWaiting">
      <!-- Roll Tabs -->
      <div class="duel-roll-tabs">
        <button class="roll-tab" :class="myTabClass" @click="rollTab = 'mine'">You</button>
        <button class="roll-tab" :class="opponentTabClass" @click="rollTab = 'opponent'">{{ opponentCharacterName }}</button>
      </div>

      <!-- Your Roll (v-show to preserve dice animation state) -->
      <DuelRollPhase
        v-show="rollTab === 'mine'"
        :cards="myCards"
        :playerName="isOfferer ? offererName : chooserName"
        :canRoll="!myRollData"
        :rollData="myRollData"
        :ability="myAbility"
        :abilityUses="myAbilityUses"
        :abilityActivated="abilityActivated"
        :activatingAbility="activatingAbility"
        :peekedCards="peekedCards"
        :rerolling="rerolling"
        :needsContinue="pendingRerollDecision"
        @roll="submitRoll"
        @use-ability="activateAbility"
        @reroll="handleReroll"
        @continue="handleContinueAfterRoll"
      />

      <!-- Opponent's Roll -->
      <template v-if="rollTab === 'opponent'">
        <DuelRollPhase
          v-if="opponentRollData"
          :rollData="opponentRollData"
          :playerName="opponentCharacterName"
          :canRoll="false"
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
      <DuelRollPhase
        :cards="myCards"
        :playerName="offererName"
        :canRoll="isOfferer"
        :rollData="offererRollData"
        :ability="isOfferer ? myAbility : null"
        :abilityUses="isOfferer ? myAbilityUses : 0"
        :abilityActivated="abilityActivated"
        :activatingAbility="activatingAbility"
        :peekedCards="peekedCards"
        :rerolling="rerolling"
        :needsContinue="pendingRerollDecision"
        @roll="submitRoll"
        @use-ability="activateAbility"
        @reroll="handleReroll"
        @continue="handleContinueAfterRoll"
      />
    </template>

    <!-- === ROLLING CHOOSER (sequential: pass-and-play / single-player) === -->
    <template v-if="duelPhase === 'rolling_chooser' && !showHandoff && !showWaiting">
      <!-- Show offerer's completed roll -->
      <DuelRollPhase
        v-if="offererRollData"
        :rollData="offererRollData"
        :playerName="offererName"
        :canRoll="false"
      />
      <!-- Chooser's roll -->
      <DuelRollPhase
        :cards="myCards"
        :playerName="chooserName"
        :canRoll="isChooser"
        :rollData="chooserRollData"
        :ability="isChooser ? myAbility : null"
        :abilityUses="isChooser ? myAbilityUses : 0"
        :abilityActivated="abilityActivated"
        :activatingAbility="activatingAbility"
        :peekedCards="peekedCards"
        :rerolling="rerolling"
        :needsContinue="pendingRerollDecision"
        @roll="submitRoll"
        @use-ability="activateAbility"
        @reroll="handleReroll"
        @continue="handleContinueAfterRoll"
      />
    </template>

    <!-- === RESOLVING PHASE === -->
    <template v-if="duelPhase === 'resolving' && !showHandoff">
      <DuelResolvePhase
        :offererResult="offererRollData"
        :chooserResult="chooserRollData"
        :canAdvance="canAdvance"
        :gameOver="isGameOver"
        @next-round="advanceRound"
      />
    </template>
  </div>
</template>

<script>
import axios from 'axios';
import DuelKingdomStats from './DuelKingdomStats.vue';
import DuelChoosePhase from './DuelChoosePhase.vue';
import DuelRollPhase from './DuelRollPhase.vue';
import DuelResolvePhase from './DuelResolvePhase.vue';
import TurnHandoffOverlay from './TurnHandoffOverlay.vue';
import PlayerItems from './PlayerItems.vue';
import EventReveal from './EventReveal.vue';
import CharacterInfoModal from './CharacterInfoModal.vue';
import { useAuth } from '../stores/auth';

export default {
  name: 'DuelBoard',
  components: { DuelKingdomStats, DuelChoosePhase, DuelRollPhase, DuelResolvePhase, TurnHandoffOverlay, PlayerItems, EventReveal, CharacterInfoModal },
  setup() {
    const auth = useAuth();
    return { auth };
  },
  props: {
    gameData: { type: Object, required: true },
    gameId: { type: [String, Number], required: true },
  },
  emits: ['refresh', 'game-over', 'game-data-updated', 'phase-updated'],
  data() {
    return {
      duelPhase: null,
      offererPlayerNumber: null,
      playerKingdoms: [],
      currentCards: [],
      myCards: [],
      offererRollData: null,
      chooserRollData: null,
      isGameOver: false,
      waitingForOpponentSelection: false,
      // Pass-and-play handoff
      showHandoff: false,
      handoffPlayerNumber: null,
      // Active player tracking (for pass-and-play)
      activePlayerNumber: 1,
      // Items & Dice
      currentPlayerItems: [],
      diceCount: 3,
      // Event reveal
      showEventReveal: false,
      lastRevealedEventId: null,
      // Online waiting
      showWaiting: false,
      waitingMessage: '',
      // Ability
      abilityActivated: false,
      activatingAbility: false,
      peekedCards: null,
      // Reroll
      rerolling: false,
      pendingRerollDecision: false,
      // Roll tab nav
      rollTab: 'mine',
      // Character info modal
      showCharacterModal: false,
      selectedCharacterData: null,
    };
  },
  computed: {
    isOnline() {
      return this.gameData?.game_mode === 'online';
    },
    isPassAndPlay() {
      return this.gameData?.game_mode === 'pass_and_play';
    },
    isHost() {
      return this.gameData?.game?.user_id === this.auth.state.user?.id;
    },
    canAdvance() {
      if (!this.isOnline) return true;
      return this.isHost;
    },
    offererNumber() {
      return this.offererPlayerNumber || this.gameData?.offerer_player_number || 1;
    },
    chooserNumber() {
      return this.offererNumber === 1 ? 2 : 1;
    },
    isOfferer() {
      return this.activePlayerNumber === this.offererNumber;
    },
    isChooser() {
      return this.activePlayerNumber === this.chooserNumber;
    },
    offererName() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.offererNumber);
      const name = player?.character?.name || `Player ${this.offererNumber}`;
      if (this.isSinglePlayerDuel && this.offererNumber === this.activePlayerNumber) return `${name} (YOU)`;
      return name;
    },
    chooserName() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.chooserNumber);
      const name = player?.character?.name || `Player ${this.chooserNumber}`;
      if (this.isSinglePlayerDuel && this.chooserNumber === this.activePlayerNumber) return `${name} (YOU)`;
      return name;
    },
    handoffCharacterName() {
      if (!this.handoffPlayerNumber || !this.gameData?.game?.players) return '';
      const player = this.gameData.game.players.find(p => p.player_number === this.handoffPlayerNumber);
      return player?.character?.name || `Player ${this.handoffPlayerNumber}`;
    },
    myAbility() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.activePlayerNumber);
      if (!player?.character) return null;
      return {
        name: player.character.wild_ability,
        description: player.character.wild_ability_description,
      };
    },
    myAbilityUses() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.activePlayerNumber);
      return player?.ability_uses ?? 0;
    },
    hasRerollAbility() {
      const ability = this.myAbility?.name;
      return ['rally', 'gamble'].includes(ability) && this.myAbilityUses > 0;
    },
    currentEvent() {
      return this.gameData?.current_event || null;
    },
    isSinglePlayerDuel() {
      return this.gameData?.game_mode === 'single' && !this.isOnline && !this.isPassAndPlay;
    },
    botPlayer() {
      return this.gameData?.game?.players?.find(p => p.is_bot);
    },
    hasBotPlayer() {
      return !!this.botPlayer;
    },
    myRollData() {
      if (this.isOfferer) return this.offererRollData;
      return this.chooserRollData;
    },
    opponentRollData() {
      if (this.isOfferer) return this.chooserRollData;
      return this.offererRollData;
    },
    opponentCharacterName() {
      const opponentNum = this.activePlayerNumber === 1 ? 2 : 1;
      const player = this.gameData?.game?.players?.find(p => p.player_number === opponentNum);
      return player?.character?.name || `Player ${opponentNum}`;
    },
    myTabClass() {
      const cls = { active: this.rollTab === 'mine' };
      if (this.myRollData?.cards?.length) {
        cls['tab-success'] = this.myRollData.cards[0].success === true;
        cls['tab-failure'] = this.myRollData.cards[0].success === false;
      }
      return cls;
    },
    opponentTabClass() {
      const cls = { active: this.rollTab === 'opponent' };
      if (this.opponentRollData?.cards?.length) {
        cls['tab-success'] = this.opponentRollData.cards[0].success === true;
        cls['tab-failure'] = this.opponentRollData.cards[0].success === false;
      } else {
        cls['tab-waiting'] = true;
      }
      return cls;
    },
    isCurrentTurnBot() {
      if (!this.hasBotPlayer) return false;
      const botNum = this.botPlayer.player_number;
      const phase = this.duelPhase;
      if (phase === 'choosing') return true; // Bot always needs to select in choosing phase
      if (phase === 'rolling') return true; // Bot needs to roll in simultaneous mode
      if (phase === 'rolling_offerer' && this.offererNumber === botNum) return true;
      if (phase === 'rolling_chooser' && this.chooserNumber === botNum) return true;
      return false;
    },
  },
  watch: {
    gameData: {
      handler(newData) {
        if (newData) {
          this.syncFromGameData(newData);
        }
      },
      immediate: true,
    },
    isCurrentTurnBot(isBotTurn) {
      if (isBotTurn) {
        this.triggerBotTurn();
      }
    },
  },
  methods: {
    openCharacterModal(playerNumber) {
      const player = this.gameData?.game?.players?.find(p => p.player_number === playerNumber);
      if (!player?.character) return;
      this.selectedCharacterData = {
        character: player.character,
        activeDice: Math.max(1, 3 - (player.lost_dice || 0)),
        abilityUses: player.ability_uses ?? 0,
        items: player.items || [],
      };
      this.showCharacterModal = true;
    },

    checkEventReveal() {
      const round = this.gameData?.current_round || this.gameData?.game?.current_round || 0;
      const event = this.currentEvent;
      if (event && (round - 1) % 3 === 0) {
        const eventId = event.id;
        if (this.lastRevealedEventId !== eventId) {
          this.lastRevealedEventId = eventId;
          this.showEventReveal = true;
        }
      }
    },

    syncFromGameData(data) {
      this.duelPhase = data.duel_phase || data.game?.duel_phase;
      this.offererPlayerNumber = data.offerer_player_number || data.game?.offerer_player_number;
      this.playerKingdoms = data.player_kingdoms || [];

      // Determine my player number
      if (this.isOnline || this.isSinglePlayerDuel) {
        const userId = this.auth.state.user?.id;
        const myPlayer = data.game?.players?.find(p => p.user_id === userId);
        if (myPlayer) {
          this.activePlayerNumber = myPlayer.player_number;
        } else if (this.isSinglePlayerDuel) {
          this.activePlayerNumber = 1;
        }
        if (this.isOnline && !this.hasBotPlayer) {
          this.updateOnlineWaiting();
        }
      }

      // If entering choosing phase, load cards
      if (this.duelPhase === 'choosing') {
        if (!this.showHandoff) {
          this.loadDuelHand();
        }
      }

      // If entering rolling phase, load my cards
      if (this.duelPhase === 'rolling' || this.duelPhase === 'rolling_offerer' || this.duelPhase === 'rolling_chooser') {
        this.loadMyRollCards();
      }

      this.checkEventReveal();
    },

    updateOnlineWaiting() {
      if (!this.isOnline || this.hasBotPlayer) {
        this.showWaiting = false;
        return;
      }

      const phase = this.duelPhase;
      const myNum = this.activePlayerNumber;
      const offNum = this.offererNumber;
      const choNum = this.chooserNumber;

      if (phase === 'rolling' ) {
        // Simultaneous rolling — no full-screen waiting overlay
        this.showWaiting = false;
      } else if (phase === 'rolling_offerer' && myNum !== offNum) {
        this.showWaiting = true;
        this.waitingMessage = 'Opponent is Rolling';
      } else if (phase === 'rolling_chooser' && myNum !== choNum) {
        this.showWaiting = true;
        this.waitingMessage = 'Opponent is Rolling';
      } else {
        this.showWaiting = false;
      }
    },

    async loadDuelHand() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}/duel-hand/${this.activePlayerNumber}`);
        this.currentCards = res.data.cards || [];
        this.currentPlayerItems = res.data.items || [];
        this.diceCount = res.data.dice_count ?? 3;
      } catch {
        this.currentCards = [];
        this.currentPlayerItems = [];
      }
    },

    initiatePassAndPlayHandoff(playerNumber) {
      this.handoffPlayerNumber = playerNumber;
      this.showHandoff = true;
    },

    async onHandoffReady() {
      this.showHandoff = false;
      this.activePlayerNumber = this.handoffPlayerNumber;
      await this.loadDuelHand();
    },

    async submitSelection(keptHandId) {
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-select`, {
          kept_hand_id: keptHandId,
        });

        if (res.data.waiting) {
          // Waiting for opponent to select
          this.waitingForOpponentSelection = true;

          if (this.isPassAndPlay) {
            // Pass-and-play: hand off to other player for their selection
            this.waitingForOpponentSelection = false;
            const otherPlayer = this.activePlayerNumber === 1 ? 2 : 1;
            this.initiatePassAndPlayHandoff(otherPlayer);
          } else if (this.hasBotPlayer) {
            // Bot needs to select — trigger bot turn
            this.triggerBotTurn();
          }
          return;
        }

        // Both selected — transition to rolling
        const phase = res.data.duel_phase || 'rolling_offerer';
        this.duelPhase = phase;
        this.waitingForOpponentSelection = false;
        this.$emit('phase-updated', phase);

        if (this.isPassAndPlay) {
          await this.loadMyRollCards();
          this.initiatePassAndPlayHandoff(this.offererNumber);
        } else if (this.hasBotPlayer) {
          await this.loadMyRollCards();
          // Bot needs to roll — watcher won't fire since isCurrentTurnBot stayed true (choosing → rolling)
          if (this.isCurrentTurnBot) {
            this._opponentTurnPending = false;
            this.triggerBotTurn();
          }
        } else {
          await this.loadMyRollCards();
          this.updateOnlineWaiting();
        }
      } catch (e) {
        alert('Failed to select: ' + (e.response?.data?.error || e.message));
      }
    },

    async loadMyRollCards() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}/duel-hand/${this.activePlayerNumber}`);
        const cards = res.data.cards || [];
        this.myCards = cards;
      } catch {
        this.myCards = [];
      }
    },

    async activateAbility() {
      if (this.activatingAbility || this.abilityActivated) return;
      this.activatingAbility = true;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/use-ability`, {
          player_number: this.activePlayerNumber,
        });
        this.abilityActivated = true;
        if (res.data.peeked_cards) {
          this.peekedCards = res.data.peeked_cards;
        }
        const player = this.gameData?.game?.players?.find(p => p.player_number === this.activePlayerNumber);
        if (player) {
          player.ability_uses = res.data.remaining_uses;
        }
      } catch (e) {
        alert('Failed to use ability: ' + (e.response?.data?.error || e.message));
      }
      this.activatingAbility = false;
    },

    async submitRoll() {
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-roll`);
        const rollResult = res.data;

        // Store roll data
        if (this.duelPhase === 'rolling') {
          if (rollResult.player_number === this.offererNumber) {
            this.offererRollData = rollResult;
          } else {
            this.chooserRollData = rollResult;
          }
          await this.refreshKingdoms();
          if (rollResult.duel_result) this.isGameOver = true;

          // Always pause for player to review results and optionally use abilities
          this.pendingRerollDecision = true;
          return;
        } else if (this.duelPhase === 'rolling_offerer' || this.gameData?.game?.duel_phase === 'rolling_offerer') {
          this.offererRollData = rollResult;
          await this.refreshKingdoms();

          // Always pause for player to review results
          this.pendingRerollDecision = true;
          return;
        } else {
          this.chooserRollData = rollResult;
          await this.refreshKingdoms();

          // Always pause for player to review results
          this.pendingRerollDecision = true;
          return;
        }
      } catch (e) {
        alert('Failed to roll: ' + (e.response?.data?.error || e.message));
      }
    },

    advanceAfterOffererRoll() {
      this.duelPhase = 'rolling_chooser';
      this.$emit('phase-updated', 'rolling_chooser');
      if (this.isPassAndPlay) {
        this.myCards = [];
        this.initiatePassAndPlayHandoff(this.chooserNumber);
      } else if (this.hasBotPlayer) {
        this.myCards = [];
      } else {
        this.updateOnlineWaiting();
      }
    },

    advanceAfterChooserRoll(rollResult) {
      this.duelPhase = 'resolving';
      this.$emit('phase-updated', 'resolving');
      if (rollResult?.duel_result) this.isGameOver = true;
      if (this.isPassAndPlay && this.offererRollData?.duel_result) this.isGameOver = true;
    },

    async handleReroll() {
      this.rerolling = true;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-reroll`, {
          player_number: this.activePlayerNumber,
        });
        const rollResult = res.data;

        // Update the roll data with rerolled results
        if (this.duelPhase === 'rolling') {
          if (rollResult.player_number === this.offererNumber) {
            this.offererRollData = rollResult;
          } else {
            this.chooserRollData = rollResult;
          }
        } else if (this.duelPhase === 'rolling_offerer' || this.gameData?.game?.duel_phase === 'rolling_offerer') {
          this.offererRollData = rollResult;
        } else {
          this.chooserRollData = rollResult;
        }

        // Update ability uses on the player
        const player = this.gameData?.game?.players?.find(p => p.player_number === this.activePlayerNumber);
        if (player && rollResult.remaining_uses !== undefined) {
          player.ability_uses = rollResult.remaining_uses;
        }
        this.abilityActivated = true;

        await this.refreshKingdoms();

        if (rollResult.duel_result) this.isGameOver = true;

        // Keep pendingRerollDecision true so "Continue" button shows
      } catch (e) {
        alert('Reroll failed: ' + (e.response?.data?.error || e.message));
      }
      this.rerolling = false;
    },

    handleContinueAfterRoll() {
      this.pendingRerollDecision = false;

      // Now advance the phase as normal
      if (this.duelPhase === 'rolling') {
        if (this.offererRollData && this.chooserRollData) {
          this.duelPhase = 'resolving';
          this.$emit('phase-updated', 'resolving');
        }
        // If opponent hasn't rolled yet, stay in rolling phase
        // Bot should already be triggered via isCurrentTurnBot watcher
      } else if (this.duelPhase === 'rolling_offerer' || this.gameData?.game?.duel_phase === 'rolling_offerer') {
        // Reset ability state for chooser's turn
        this.abilityActivated = false;
        this.peekedCards = null;
        this.advanceAfterOffererRoll();
      } else {
        this.advanceAfterChooserRoll(this.chooserRollData);
      }
    },

    async refreshKingdoms() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}`);
        this.playerKingdoms = res.data.player_kingdoms || [];
      } catch {
        // silent
      }
    },

    async advanceRound() {
      try {
        const res = await axios.post(`/api/games/${this.gameId}/next-round`);

        if (res.data.game_over) {
          if (res.data.completion) {
            sessionStorage.setItem(`game_completion_${this.gameId}`, JSON.stringify(res.data.completion));
          }
          this.$emit('game-over');
          return;
        }

        // Reset state for next round
        this.offererRollData = null;
        this.chooserRollData = null;
        this.myCards = [];
        this.currentCards = [];
        this.isGameOver = false;
        this.abilityActivated = false;
        this.peekedCards = null;
        this.pendingRerollDecision = false;
        this.rerolling = false;
        this.waitingForOpponentSelection = false;
        this.rollTab = 'mine';

        // Update from response
        this.syncFromGameData(res.data);
        this.$emit('game-data-updated', res.data);

        if (this.isPassAndPlay) {
          // In pass-and-play, player 1 always picks first
          this.initiatePassAndPlayHandoff(1);
        } else if (this.hasBotPlayer) {
          await this.loadDuelHand();
        }
      } catch (e) {
        alert('Failed to advance: ' + (e.response?.data?.error || e.message));
        this.$emit('refresh');
      }
    },

    // Called from GameBoard when duel broadcasts are received
    handleDuelChoiceMade(data) {
      this.duelPhase = data.duel_phase;
      this.waitingForOpponentSelection = false;
      this.loadMyRollCards();
      this.showWaiting = false;
      this.updateOnlineWaiting();
    },

    handleDuelRollComplete(data) {
      const rollData = data.roll_data;

      if (rollData.player_number === this.offererNumber) {
        this.offererRollData = rollData;
      } else {
        this.chooserRollData = rollData;
      }

      if (rollData.duel_result) {
        this.isGameOver = true;
      }

      this.refreshKingdoms();

      if (this.duelPhase === 'rolling' && this.offererRollData && this.chooserRollData && !this.pendingRerollDecision) {
        this.duelPhase = 'resolving';
        this.$emit('phase-updated', 'resolving');
      } else if (this.duelPhase !== 'rolling' || !this.pendingRerollDecision) {
        this.duelPhase = data.duel_phase;
      }

      this.showWaiting = false;

      // Sequential: if it's now my turn to roll
      if (this.duelPhase === 'rolling_chooser' && this.activePlayerNumber === this.chooserNumber) {
        this.loadMyRollCards();
      }

      this.updateOnlineWaiting();
    },

    async triggerBotTurn() {
      if (this._opponentTurnPending) return;
      this._opponentTurnPending = true;

      const delay = this.isOnline
        ? 3000 + Math.random() * 4000
        : 1000 + Math.random() * 1000;
      await new Promise(r => setTimeout(r, delay));

      try {
        const res = await axios.post(`/api/games/${this.gameId}/opponent-turn`);
        const data = res.data;

        if (this.duelPhase === 'choosing') {
          if (data.waiting === false) {
            // Both selected — transition to rolling
            const phase = data.duel_phase || 'rolling_offerer';
            this.duelPhase = phase;
            this.waitingForOpponentSelection = false;
            this.$emit('phase-updated', phase);
            await this.loadMyRollCards();

            // Bot may need to roll next — watcher won't fire since isCurrentTurnBot was already true
            if (this.isCurrentTurnBot) {
              this._opponentTurnPending = false;
              this.triggerBotTurn();
              return;
            }
          }
        } else if (data.player_number !== undefined) {
          // Bot rolled
          if (data.player_number === this.offererNumber) {
            this.offererRollData = data;
          } else {
            this.chooserRollData = data;
          }
          if (data.duel_result) {
            this.isGameOver = true;
          }
          await this.refreshKingdoms();

          const game = await axios.get(`/api/games/${this.gameId}`);
          const newPhase = game.data.duel_phase || game.data.game?.duel_phase;
          this.$emit('game-data-updated', game.data);

          // Don't auto-advance to resolving if human still reviewing their roll
          if (newPhase === 'resolving' && this.pendingRerollDecision) {
            // Stay in rolling — will advance when human clicks Continue
          } else {
            this.duelPhase = newPhase;
          }

          if (this.duelPhase === 'rolling_chooser' && this.activePlayerNumber === this.chooserNumber) {
            await this.loadMyRollCards();
          } else if (this.duelPhase === 'rolling_offerer' && this.activePlayerNumber === this.offererNumber) {
            await this.loadMyRollCards();
          }
        }
      } catch (e) {
        console.error('Bot turn failed:', e);
      }

      this._opponentTurnPending = false;
    },

    handleNextRoundStarted(data) {
      this.offererRollData = null;
      this.chooserRollData = null;
      this.myCards = [];
      this.currentCards = [];
      this.isGameOver = false;
      this.abilityActivated = false;
      this.peekedCards = null;
      this.pendingRerollDecision = false;
      this.rerolling = false;
      this.waitingForOpponentSelection = false;
      this.rollTab = 'mine';
      this.syncFromGameData(data);
      this.$emit('game-data-updated', data);
    },
  },

  async mounted() {
    // Ensure kingdoms are loaded
    if (!this.playerKingdoms.length) {
      await this.refreshKingdoms();
    }

    // Initial card load
    if (this.duelPhase === 'choosing') {
      if (this.isPassAndPlay) {
        this.initiatePassAndPlayHandoff(1);
      } else {
        await this.loadDuelHand();
        if (this.isCurrentTurnBot) {
          this.triggerBotTurn();
        }
      }
    } else if (this.duelPhase === 'rolling') {
      await this.loadMyRollCards();
      this.updateOnlineWaiting();
    } else if (this.duelPhase === 'rolling_offerer' || this.duelPhase === 'rolling_chooser') {
      if (this.isPassAndPlay) {
        const activeNum = this.duelPhase === 'rolling_offerer' ? this.offererNumber : this.chooserNumber;
        this.initiatePassAndPlayHandoff(activeNum);
      } else {
        await this.loadMyRollCards();
        this.updateOnlineWaiting();
        if (this.isCurrentTurnBot) {
          this.triggerBotTurn();
        }
      }
    }
  },
};
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

.duel-bar-icons {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
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

/* Roll Tab Nav */
.duel-roll-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 12px;
  justify-content: center;
}

.roll-tab {
  flex: 1;
  max-width: 180px;
  padding: 8px 16px;
  border-radius: 6px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  background: rgba(0, 0, 0, 0.2);
  border: 2px solid rgba(138, 106, 46, 0.3);
  color: var(--text-secondary);
  text-align: center;
}

.roll-tab.active {
  background: rgba(212, 168, 67, 0.12);
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.roll-tab.tab-success {
  border-color: #4a8a3a;
  color: #5ea84a;
}

.roll-tab.tab-success.active {
  background: rgba(74, 138, 58, 0.12);
}

.roll-tab.tab-failure {
  border-color: #a03020;
  color: #c0392b;
}

.roll-tab.tab-failure.active {
  background: rgba(160, 48, 32, 0.12);
}

.roll-tab.tab-waiting {
  opacity: 0.6;
  animation: tabPulse 2s ease-in-out infinite;
}

@keyframes tabPulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.8; }
}
</style>
