<template>
  <div class="duel-board">
    <!-- Turn Timer -->
    <div v-if="formattedTimeRemaining" class="turn-timer" :class="{ urgent: timerUrgent }">
      {{ formattedTimeRemaining }}
    </div>



    <!-- Event Banner -->
    <EventBanner :event="currentEvent" />

    <!-- Kingdom Stats -->
    <DuelKingdomStats
      ref="kingdomStats"
      :playerKingdoms="playerKingdoms"
      :myPlayerNumber="activePlayerNumber"
      :isSinglePlayerDuel="isSinglePlayerDuel"
      :playerDifficulties="playerDifficulties"
      :playerRollResults="playerRollResults"
      :playerDiceThemes="playerDiceThemesComputed"
      :diceAnimationTrigger="diceAnimationTrigger"
      :playerKingdomStyles="playerKingdomStylesComputed"
      :playerKingdomStyleData="playerKingdomStyleDataComputed"
      :playerTitles="playerTitlesComputed"
      @show-character="openCharacterModal"
      @dice-animation-complete="onDiceAnimationComplete"
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

    <!-- Player Items (overlay only, no floating button) -->
    <PlayerItems ref="playerItems" :items="currentPlayerItems" :showButton="false" />


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
      />
      <div v-if="waitingForOpponentSelection" class="waiting-inline">
        <p>Waiting for opponent to choose their card...</p>
      </div>
    </template>

    <!-- === SIMULTANEOUS ROLLING === -->
    <template v-if="duelPhase === 'rolling' && !showHandoff && !showWaiting">
      <!-- Continue button (between kingdom stats and roll tabs) -->
      <div v-if="showContinueAboveStats" class="continue-above-stats">
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
        :cards="myCards"
        :playerName="isOfferer ? offererName : chooserName"
        :canRoll="!myRollData"
        :rollData="myRollData"
        :diceCount="diceCount"
        :ability="myAbility"
        :abilityUses="myAbilityUses"
        :abilityActivated="abilityActivated"
        :activatingAbility="activatingAbility"
        :peekedCards="peekedCards"
        :rerolling="rerolling"
        :needsContinue="pendingRerollDecision"
        :use3dDice="dddiceAvailable"
        :playerItems="currentPlayerItems"
        :itemDecided="itemDecided"
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
          :rollData="opponentRollData"
          :playerName="opponentCharacterName"
          :canRoll="false"
          :diceCount="diceCount"
          :use3dDice="dddiceAvailable"
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
      <div v-if="showContinueAboveStats" class="continue-above-stats">
        <button class="btn-continue-top" @click="handleContinueAfterRoll">Continue</button>
      </div>
      <DuelRollPhase
        :cards="myCards"
        :playerName="offererName"
        :canRoll="isOfferer"
        :rollData="offererRollData"
        :diceCount="diceCount"
        :ability="isOfferer ? myAbility : null"
        :abilityUses="isOfferer ? myAbilityUses : 0"
        :abilityActivated="abilityActivated"
        :activatingAbility="activatingAbility"
        :peekedCards="peekedCards"
        :rerolling="rerolling"
        :needsContinue="pendingRerollDecision"
        :use3dDice="dddiceAvailable"
        :playerItems="currentPlayerItems"
        :itemDecided="itemDecided"
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
      <div v-if="showContinueAboveStats" class="continue-above-stats">
        <button class="btn-continue-top" @click="handleContinueAfterRoll">Continue</button>
      </div>
      <!-- Show offerer's completed roll -->
      <DuelRollPhase
        v-if="offererRollData"
        :rollData="offererRollData"
        :playerName="offererName"
        :canRoll="false"
        :diceCount="diceCount"
        :use3dDice="dddiceAvailable"
      />
      <!-- Chooser's roll -->
      <DuelRollPhase
        :cards="myCards"
        :playerName="chooserName"
        :canRoll="isChooser"
        :rollData="chooserRollData"
        :diceCount="diceCount"
        :ability="isChooser ? myAbility : null"
        :abilityUses="isChooser ? myAbilityUses : 0"
        :abilityActivated="abilityActivated"
        :activatingAbility="activatingAbility"
        :peekedCards="peekedCards"
        :rerolling="rerolling"
        :needsContinue="pendingRerollDecision"
        :use3dDice="dddiceAvailable"
        :playerItems="currentPlayerItems"
        :itemDecided="itemDecided"
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

<script>
import axios from 'axios';
import DuelKingdomStats from './DuelKingdomStats.vue';
import DuelChoosePhase from './DuelChoosePhase.vue';
import DuelRollPhase from './DuelRollPhase.vue';
import TurnHandoffOverlay from './TurnHandoffOverlay.vue';
import PlayerItems from './PlayerItems.vue';
import EventReveal from './EventReveal.vue';
import EventBanner from './EventBanner.vue';
import CharacterInfoModal from './CharacterInfoModal.vue';
import { isDddiceAvailable } from '../dddiceService';
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';

export default {
  name: 'DuelBoard',
  components: { DuelKingdomStats, DuelChoosePhase, DuelRollPhase, TurnHandoffOverlay, PlayerItems, EventReveal, EventBanner, CharacterInfoModal },
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast };
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
      diceCount: 4,
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
      // Per-player dice in kingdom stats
      playerDifficulties: {},
      playerRollResults: {},
      diceAnimationTrigger: null,
      _diceAnimationResolve: null,
      // Item decision tracking
      itemDecided: false,
      // Character info modal
      showCharacterModal: false,
      selectedCharacterData: null,
      // Turn timer
      turnTimeLimit: null,
      turnTimeRemaining: null,
      turnTimerInterval: null,
      // Timeout overlay
      showTimeoutOverlay: false,
      timeoutMessage: '',
      reportingTimeout: false,
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
      const isBot = player?.is_bot && !player?.user;
      const name = isBot ? (player?.character?.name || 'Bot') : (player?.user?.name || `Player ${this.offererNumber}`);
      if (this.isSinglePlayerDuel && this.offererNumber === this.activePlayerNumber) return `${name} (YOU)`;
      return name;
    },
    chooserName() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.chooserNumber);
      const isBot = player?.is_bot && !player?.user;
      const name = isBot ? (player?.character?.name || 'Bot') : (player?.user?.name || `Player ${this.chooserNumber}`);
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
      const isBot = player?.is_bot && !player?.user;
      return isBot ? (player?.character?.name || 'Bot') : (player?.user?.name || `Player ${opponentNum}`);
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
    dddiceAvailable() {
      return isDddiceAvailable();
    },
    showContinueAboveStats() {
      if (!this.pendingRerollDecision) return false;
      if (!this.myRollData) return false;
      // In simultaneous rolling, don't show until BOTH players have rolled
      if (this.duelPhase === 'rolling' && (!this.offererRollData || !this.chooserRollData)) return false;
      // If reroll option is showing, continue is paired with it in DuelRollPhase
      const ability = this.myAbility?.name;
      const isRerollAbility = ['rally', 'gamble'].includes(ability);
      const hasReroll = isRerollAbility && this.myAbilityUses > 0 && !this.abilityActivated && !this.myRollData?.rerolled;
      return !hasReroll;
    },
    playerDiceThemesComputed() {
      return {
        1: this.getThemesForPlayer(1),
        2: this.getThemesForPlayer(2),
      };
    },
    playerKingdomStylesComputed() {
      return {
        1: this.getKingdomStyleForPlayer(1),
        2: this.getKingdomStyleForPlayer(2),
      };
    },
    playerKingdomStyleDataComputed() {
      return {
        1: this.getKingdomStyleDataForPlayer(1),
        2: this.getKingdomStyleDataForPlayer(2),
      };
    },
    playerTitlesComputed() {
      return {
        1: this.getTitleForPlayer(1),
        2: this.getTitleForPlayer(2),
      };
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
    formattedTimeRemaining() {
      if (this.turnTimeRemaining == null) return null;
      const total = this.turnTimeRemaining;
      if (total <= 0) return '0:00';
      const hours = Math.floor(total / 3600);
      const mins = Math.floor((total % 3600) / 60);
      const secs = total % 60;
      if (hours > 0) {
        return `${hours}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
      }
      return `${mins}:${secs.toString().padStart(2, '0')}`;
    },
    timerUrgent() {
      return this.turnTimeRemaining != null && this.turnTimeRemaining <= 30;
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
      // For simultaneous rolling, bot rolls when human rolls — don't pre-trigger
      if (isBotTurn && this.duelPhase !== 'rolling') {
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
        activeDice: Math.max(1, 4 - (player.lost_dice || 0)),
        abilityUses: player.ability_uses ?? 0,
        items: player.items || [],
      };
      this.showCharacterModal = true;
    },

    startTurnTimer() {
      if (this.turnTimerInterval) clearInterval(this.turnTimerInterval);
      this.turnTimerInterval = setInterval(() => {
        if (this.turnTimeRemaining != null && this.turnTimeRemaining > 0) {
          this.turnTimeRemaining--;
          if (this.turnTimeRemaining <= 0) {
            clearInterval(this.turnTimerInterval);
            this.turnTimerInterval = null;
            this.handleTimerExpired();
          }
        } else {
          clearInterval(this.turnTimerInterval);
          this.turnTimerInterval = null;
        }
      }, 1000);
    },

    async handleTimerExpired() {
      if (this.reportingTimeout || this.showTimeoutOverlay) return;
      this.reportingTimeout = true;

      // Random delay 3-10 seconds before auto-playing the timed-out player's turn
      const delay = 3000 + Math.floor(Math.random() * 7000);
      await new Promise(resolve => setTimeout(resolve, delay));

      try {
        const res = await axios.post(`/api/games/${this.gameId}/report-timeout`);
        const data = res.data;

        if (data.game_over) {
          // Fallback: game ended (shouldn't normally happen now)
          const myNum = this.activePlayerNumber;
          const timedOutNum = data.timed_out_player_number;

          if (timedOutNum === 0) {
            this.timeoutMessage = 'Both players ran out of time. The game ends in a draw.';
          } else if (timedOutNum === myNum) {
            this.timeoutMessage = 'You ran out of time. Your opponent wins by forfeit.';
          } else {
            this.timeoutMessage = 'Your opponent ran out of time. You win by forfeit!';
          }

          this.showTimeoutOverlay = true;

          if (data.completion) {
            sessionStorage.setItem(`game_completion_${this.gameId}`, JSON.stringify(data.completion));
          }
          if (data.timed_out_player_number != null) {
            sessionStorage.setItem(`game_timeout_${this.gameId}`, JSON.stringify(data.timed_out_player_number));
          }

          setTimeout(() => {
            this.$router.replace(`/game/${this.gameId}/over`);
          }, 3000);
        } else if (data.auto_played) {
          // Turn was auto-played — refresh game state
          // The broadcast events (DuelRollComplete, DuelChoiceMade) will update the UI
          this.reportingTimeout = false;
          this.turnTimeRemaining = this.turnTimeLimit;
          this.startTurnTimer();
          // Refresh full game state to sync
          this.$emit('refresh');
        }
      } catch (e) {
        console.error('Timeout report failed:', e);
        try {
          const res = await axios.get(`/api/games/${this.gameId}`);
          if (res.data.game?.status === 'completed' || res.data.game?.status === 'cancelled') {
            this.timeoutMessage = 'Time expired. The game has ended.';
            this.showTimeoutOverlay = true;
            setTimeout(() => {
              this.$router.replace(`/game/${this.gameId}/over`);
            }, 2000);
          } else {
            // Game still active — just refresh
            this.reportingTimeout = false;
            this.$emit('refresh');
          }
        } catch {
          // Last resort: redirect anyway
          this.$router.replace(`/game/${this.gameId}/over`);
        }
      }

      this.reportingTimeout = false;
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

      // Sync turn timer (don't restart if timeout already reported)
      if (data.turn_time_limit && !this.showTimeoutOverlay && !this.reportingTimeout) {
        this.turnTimeLimit = data.turn_time_limit;
        if (data.turn_time_remaining != null) {
          this.turnTimeRemaining = data.turn_time_remaining;
          this.startTurnTimer();
        }
      }

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
        // For sequential rolling, trigger bot with delay; for simultaneous, bot rolls when human rolls
        if (this.duelPhase !== 'rolling' && this.hasBotPlayer && this.isCurrentTurnBot && !this._opponentTurnPending) {
          this.triggerBotTurn();
        }
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
        this.diceCount = res.data.dice_count ?? 4;
        // Auto-decide if player has no usable items
        const usable = this.currentPlayerItems.filter(pi => !pi.is_used);
        if (usable.length === 0) {
          this.itemDecided = true;
        }
      } catch {
        this.currentCards = [];
        this.currentPlayerItems = [];
        this.itemDecided = true;
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
          this._opponentTurnPending = false;
          await this.loadMyRollCards();
          // For simultaneous rolling, bot rolls when human rolls (triggerBotRollImmediate)
          // For sequential, trigger bot turn with delay
          if (phase !== 'rolling' && this.isCurrentTurnBot) {
            this.triggerBotTurn();
          }
        } else {
          await this.loadMyRollCards();
          this.updateOnlineWaiting();
        }
      } catch (e) {
        this.toast.error('Failed to select: ' + (e.response?.data?.error || e.message));
      }
    },

    async loadMyRollCards() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}/duel-hand/${this.activePlayerNumber}`);
        const cards = res.data.cards || [];
        this.myCards = cards;
        // Compute difficulty for this player's cards
        const totalDifficulty = cards.reduce((sum, c) => sum + ((c.card || c).difficulty || 0), 0);
        if (totalDifficulty > 0) {
          this.playerDifficulties = { ...this.playerDifficulties, [this.activePlayerNumber]: totalDifficulty };
        }
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
        this.toast.error('Failed to use ability: ' + (e.response?.data?.error || e.message));
      }
      this.activatingAbility = false;
    },

    getKingdomStyleForPlayer(playerNumber) {
      const player = this.gameData?.game?.players?.find(p => p.player_number === playerNumber);
      return player?.user?.active_kingdom_style_slug || 'classic';
    },
    getKingdomStyleDataForPlayer(playerNumber) {
      const player = this.gameData?.game?.players?.find(p => p.player_number === playerNumber);
      const style = player?.user?.active_kingdom_style;
      if (!style) return null;
      return {
        slug: style.slug,
        background_image_url: style.background_image_url,
        css_vars: style.css_vars,
      };
    },
    getTitleForPlayer(playerNumber) {
      const player = this.gameData?.game?.players?.find(p => p.player_number === playerNumber);
      return player?.user?.active_title || null;
    },
    getThemesForPlayer(playerNumber) {
      const player = this.gameData?.game?.players?.find(p => p.player_number === playerNumber);
      const slug = player?.user?.active_dice_theme_slug || 'dddice-standard';
      return [slug, slug, slug, slug];
    },

    async triggerDiceAnimation(rollResult, playerNumber) {
      if (!this.dddiceAvailable) return;
      const themes = this.getThemesForPlayer(playerNumber);
      const timestamp = Date.now();

      return new Promise((resolve) => {
        let resolved = false;
        const doResolve = () => {
          if (resolved) return;
          resolved = true;
          if (this._diceAnimationResolve?.timestamp === timestamp) {
            this._diceAnimationResolve = null;
          }
          resolve();
        };

        this._diceAnimationResolve = { resolve: doResolve, timestamp };

        // Safety timeout — always fires even if animation hangs or events are lost
        setTimeout(doResolve, 8000);

        this.diceAnimationTrigger = {
          playerNumber,
          rollResult,
          themes,
          timestamp,
        };
      });
    },

    onDiceAnimationComplete({ playerNumber, timestamp }) {
      if (this._diceAnimationResolve?.timestamp === timestamp) {
        this._diceAnimationResolve.resolve();
      }
    },

    applyRollResult(rollResult) {
      // Clear 3D dice immediately so they don't linger after outcome is shown
      const pn = rollResult.player_number || this.activePlayerNumber;
      this.$refs.kingdomStats?.clearDice(pn);

      if (this.duelPhase === 'rolling') {
        if (rollResult.player_number === this.offererNumber) {
          this.offererRollData = rollResult;
        } else {
          this.chooserRollData = rollResult;
        }
        this.refreshKingdoms();
        if (rollResult.duel_result) this.isGameOver = true;

        if (this.hasBotPlayer && !this.opponentRollData) {
          this.triggerBotRollImmediate();
        }
      } else if (this.duelPhase === 'rolling_offerer' || this.gameData?.game?.duel_phase === 'rolling_offerer') {
        this.offererRollData = rollResult;
        this.refreshKingdoms();
      } else {
        this.chooserRollData = rollResult;
        this.refreshKingdoms();
      }

      // Update per-player roll results for kingdom stats display
      this.playerRollResults = { ...this.playerRollResults, [pn]: rollResult };

      // Extract difficulty for opponent from roll data if we don't have it yet
      if (rollResult.cards?.length && !this.playerDifficulties[pn]) {
        const diff = rollResult.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
        if (diff > 0) {
          this.playerDifficulties = { ...this.playerDifficulties, [pn]: diff };
        }
      }

      this.pendingRerollDecision = true;
    },

    async recoverRollState() {
      // Fetch current game state to recover roll data that the backend already has
      try {
        const res = await axios.get(`/api/games/${this.gameId}`);
        const data = res.data;
        this.syncFromGameData(data);
        this.$emit('game-data-updated', data);

        // If the backend has moved to resolving, set that
        const phase = data.duel_phase || data.game?.duel_phase;
        if (phase) this.duelPhase = phase;

        // Recover roll results from round_results if available
        const roundResults = data.round_results || data.game?.round_results;
        if (roundResults) {
          if (roundResults.offerer && !this.offererRollData) {
            this.offererRollData = roundResults.offerer;
          }
          if (roundResults.chooser && !this.chooserRollData) {
            this.chooserRollData = roundResults.chooser;
          }
        }
      } catch (err) {
        console.error('Failed to recover roll state:', err);
        this.$emit('refresh');
      }
    },

    async handleUseItem(gamePlayerItemId) {
      try {
        const res = await axios.post(`/api/games/${this.gameId}/use-item`, {
          game_player_item_id: gamePlayerItemId,
          player_number: this.activePlayerNumber,
        });
        this.itemDecided = true;
        if (res.data.player_items) {
          this.currentPlayerItems = res.data.player_items;
        }
      } catch (e) {
        this.toast.error('Failed to use item: ' + (e.response?.data?.error || e.message));
      }
    },
    async handleSkipItem() {
      try {
        await axios.post(`/api/games/${this.gameId}/skip-item`, {
          player_number: this.activePlayerNumber,
        });
        this.itemDecided = true;
      } catch (e) {
        this.toast.error('Failed to skip item: ' + (e.response?.data?.error || e.message));
      }
    },
    async submitRoll() {
      let rollResult;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-roll`);
        rollResult = res.data;
      } catch (e) {
        if (e.response?.status === 422 && e.response?.data?.error?.includes('already rolled')) {
          // Player already rolled (e.g. page reload) — fetch existing state
          await this.recoverRollState();
          return;
        }
        this.toast.error('Failed to roll: ' + (e.response?.data?.error || e.message));
        return;
      }

      // Trigger 3D dice animation FIRST — results shown after dice stop
      try {
        await this.triggerDiceAnimation(rollResult, rollResult.player_number || this.activePlayerNumber);
      } catch (err) {
        console.warn('[dddice] Animation failed, continuing:', err);
      }

      // Now show results, play sound, and adjust stats
      this.applyRollResult(rollResult);
    },

    advanceAfterOffererRoll() {
      this.duelPhase = 'rolling_chooser';
      this.itemDecided = false; // Reset for chooser's item decision
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
      let rollResult;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-reroll`, {
          player_number: this.activePlayerNumber,
        });
        rollResult = res.data;
      } catch (e) {
        this.toast.error('Reroll failed: ' + (e.response?.data?.error || e.message));
        this.rerolling = false;
        return;
      }

      // Trigger 3D dice animation FIRST — results shown after dice stop
      try {
        await this.triggerDiceAnimation(rollResult, this.activePlayerNumber);
      } catch (err) {
        console.warn('[dddice] Reroll animation failed, continuing:', err);
      }

      // Now update roll data with rerolled results
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

      this.playerRollResults = { ...this.playerRollResults, [this.activePlayerNumber]: rollResult };

      const player = this.gameData?.game?.players?.find(p => p.player_number === this.activePlayerNumber);
      if (player && rollResult.remaining_uses !== undefined) {
        player.ability_uses = rollResult.remaining_uses;
      }
      this.abilityActivated = true;

      await this.refreshKingdoms();
      if (rollResult.duel_result) this.isGameOver = true;

      this.rerolling = false;
    },

    handleContinueAfterRoll() {
      this.pendingRerollDecision = false;

      if (this.duelPhase === 'rolling') {
        if (this.offererRollData && this.chooserRollData) {
          // Both rolled — skip resolving, advance directly to next round
          if (this.offererRollData.duel_result || this.chooserRollData.duel_result) this.isGameOver = true;
          this.advanceRound();
        } else if (this.hasBotPlayer && !this.opponentRollData) {
          // Bot hasn't rolled yet — trigger immediately
          this._opponentTurnPending = false;
          this.triggerBotRollImmediate();
        }
        // If opponent hasn't rolled yet, stay in rolling phase
      } else if (this.duelPhase === 'rolling_offerer' || this.gameData?.game?.duel_phase === 'rolling_offerer') {
        // Reset ability state for chooser's turn
        this.abilityActivated = false;
        this.peekedCards = null;
        this.advanceAfterOffererRoll();
      } else {
        // Chooser done — skip resolving, advance directly to next round
        if (this.chooserRollData?.duel_result || this.offererRollData?.duel_result) this.isGameOver = true;
        this.advanceRound();
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

        // Clear any lingering 3D dice
        this.$refs.kingdomStats?.clearDice();

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
        this.playerDifficulties = {};
        this.playerRollResults = {};
        this.diceAnimationTrigger = null;
        this._opponentTurnPending = false;

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
        this.toast.error('Failed to advance: ' + (e.response?.data?.error || e.message));
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

    async handleDuelRollComplete(data) {
      const rollData = data.roll_data;
      const pn = rollData.player_number;

      // Ignore stale broadcasts from previous rounds (we're already in card selection)
      if (this.duelPhase === 'choosing' || this.duelPhase === 'offering') return;

      // Skip if this is our own roll — already handled by submitRoll/applyRollResult
      if (pn === this.activePlayerNumber) {
        // Still update phase from broadcast
        const newPhase = data.duel_phase;
        if (newPhase && newPhase !== this.duelPhase) {
          this.duelPhase = newPhase;
        }
        this.showWaiting = false;
        this.updateOnlineWaiting();
        return;
      }

      // Set difficulty BEFORE animation so "Required: X" shows during roll
      if (rollData.cards?.length && !this.playerDifficulties[pn]) {
        const diff = rollData.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
        if (diff > 0) {
          this.playerDifficulties = { ...this.playerDifficulties, [pn]: diff };
        }
      }

      // Trigger 3D dice animation FIRST — results shown after dice stop
      try {
        await this.triggerDiceAnimation(rollData, pn);
      } catch (err) {
        console.warn('[dddice] Online opponent dice animation failed, continuing:', err);
      }
      this.$refs.kingdomStats?.clearDice(pn);

      // Now show results and adjust stats
      if (pn === this.offererNumber) {
        this.offererRollData = rollData;
      } else {
        this.chooserRollData = rollData;
      }
      this.playerRollResults = { ...this.playerRollResults, [pn]: rollData };

      if (rollData.duel_result) {
        this.isGameOver = true;
      }

      this.refreshKingdoms();

      if (this.duelPhase === 'rolling' && this.offererRollData && this.chooserRollData && !this.pendingRerollDecision) {
        // Skip resolving — advance directly to next round
        this.advanceRound();
      } else if (this.duelPhase !== 'rolling' || !this.pendingRerollDecision) {
        const newPhase = data.duel_phase;
        if (newPhase === 'resolving') {
          this.advanceRound();
        } else {
          this.duelPhase = newPhase;
        }
      }

      this.showWaiting = false;

      // Sequential: if it's now my turn to roll
      if (this.duelPhase === 'rolling_chooser' && this.activePlayerNumber === this.chooserNumber) {
        this.loadMyRollCards();
      }

      this.updateOnlineWaiting();
    },

    async triggerBotRollImmediate() {
      if (!this.hasBotPlayer || this._opponentTurnPending) return;
      this._opponentTurnPending = true;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/opponent-turn`);
        const data = res.data;

        // Guard: if the round advanced while we were waiting, discard stale result
        if (this.duelPhase === 'choosing' || this.duelPhase === 'offering') {
          this._opponentTurnPending = false;
          return;
        }

        if (data.player_number !== undefined) {
          // Set difficulty BEFORE animation so "Required: X" is visible during roll
          if (data.cards?.length) {
            const diff = data.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
            if (diff > 0) {
              this.playerDifficulties = { ...this.playerDifficulties, [data.player_number]: diff };
            }
          }

          // Trigger 3D dice animation FIRST — results shown after dice stop
          try {
            await this.triggerDiceAnimation(data, data.player_number);
          } catch (err) {
            console.warn('[dddice] Bot roll animation failed, continuing:', err);
          }
          this.$refs.kingdomStats?.clearDice(data.player_number);

          // Now show results and adjust stats
          if (data.player_number === this.offererNumber) {
            this.offererRollData = data;
          } else {
            this.chooserRollData = data;
          }
          this.playerRollResults = { ...this.playerRollResults, [data.player_number]: data };

          if (data.duel_result) this.isGameOver = true;
          await this.refreshKingdoms();
        }
      } catch (e) {
        console.error('Bot roll failed:', e);
      }
      this._opponentTurnPending = false;
    },

    async triggerBotTurn() {
      if (!this.hasBotPlayer || this._opponentTurnPending) return;
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

            // Bot may need to roll next (sequential only)
            // In simultaneous mode, bot rolls when human rolls (via triggerBotRollImmediate)
            if (this.isCurrentTurnBot && phase !== 'rolling') {
              this._opponentTurnPending = false;
              this.triggerBotTurn();
              return;
            }
          }
        } else if (data.player_number !== undefined && this.duelPhase !== 'choosing' && this.duelPhase !== 'offering') {
          // Set difficulty BEFORE animation so "Required: X" is visible during roll
          if (data.cards?.length) {
            const diff = data.cards.reduce((sum, cr) => sum + (cr.difficulty || 0), 0);
            if (diff > 0) {
              this.playerDifficulties = { ...this.playerDifficulties, [data.player_number]: diff };
            }
          }

          // Trigger 3D dice animation FIRST — results shown after dice stop
          try {
            await this.triggerDiceAnimation(data, data.player_number);
          } catch (err) {
            console.warn('[dddice] Bot turn animation failed, continuing:', err);
          }
          this.$refs.kingdomStats?.clearDice(data.player_number);

          // Now show results and adjust stats
          if (data.player_number === this.offererNumber) {
            this.offererRollData = data;
          } else {
            this.chooserRollData = data;
          }
          this.playerRollResults = { ...this.playerRollResults, [data.player_number]: data };

          if (data.duel_result) {
            this.isGameOver = true;
          }
          await this.refreshKingdoms();

          const game = await axios.get(`/api/games/${this.gameId}`);
          const newPhase = game.data.duel_phase || game.data.game?.duel_phase;
          this.$emit('game-data-updated', game.data);

          // Don't auto-advance if human still reviewing their roll
          if (newPhase === 'resolving' && this.pendingRerollDecision) {
            // Stay in rolling — will advance when human clicks Continue
          } else if (newPhase === 'resolving') {
            // Skip resolving — advance directly to next round
            this.advanceRound();
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
      this.playerDifficulties = {};
      this.playerRollResults = {};
      this.diceAnimationTrigger = null;
      this.itemDecided = false;
      this._opponentTurnPending = false;
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
      // Bot rolls simultaneously when human rolls (triggerBotRollImmediate in submitRoll)
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
    } else if (this.duelPhase === 'resolving') {
      // Page refreshed into resolving — skip it, advance directly
      this.advanceRound();
    }
  },
  beforeUnmount() {
    if (this.turnTimerInterval) {
      clearInterval(this.turnTimerInterval);
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

.btn-continue-top:hover {
  background: rgba(212, 168, 67, 0.25);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.3);
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
  padding: 5px 12px;
  border-radius: 6px;
  font-family: 'Crimson Text',serif;
  font-size: 0.7rem;
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
  border: 1px solid var(--border-gold);
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
