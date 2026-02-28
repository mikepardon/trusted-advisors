<template>
  <div class="duel-board">
    <!-- Kingdom Stats -->
    <DuelKingdomStats
      :playerKingdoms="playerKingdoms"
      :myPlayerNumber="activePlayerNumber"
    />

    <!-- Player Items -->
    <PlayerItems ref="playerItems" :items="currentPlayerItems" :showButton="false" />

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

    <!-- === OFFERING PHASE === -->
    <template v-if="duelPhase === 'offering' && !showHandoff && !showWaiting">
      <DuelOfferPhase
        v-if="isMyTurnToOffer"
        :cards="currentCards"
        @offer="submitOffer"
      />
      <div v-else class="waiting-inline">
        <p>The offerer is selecting cards to reveal...</p>
      </div>
    </template>

    <!-- === CHOOSING PHASE === -->
    <template v-if="duelPhase === 'choosing' && !showHandoff && !showWaiting">
      <DuelChoosePhase
        v-if="isMyTurnToChoose"
        :cards="currentCards"
        @choose="submitChoice"
      />
      <div v-else class="waiting-inline">
        <p>Your opponent is choosing a card...</p>
      </div>
    </template>

    <!-- === ROLLING OFFERER === -->
    <template v-if="duelPhase === 'rolling_offerer' && !showHandoff && !showWaiting">
      <DuelRollPhase
        :card="myCard"
        :playerName="offererName"
        :canRoll="isOfferer"
        :rollData="offererRollData"
        @roll="submitRoll"
      />
    </template>

    <!-- === ROLLING CHOOSER === -->
    <template v-if="duelPhase === 'rolling_chooser' && !showHandoff && !showWaiting">
      <!-- Show offerer's completed roll -->
      <DuelRollPhase
        v-if="offererRollData"
        :card="offererRollData.card"
        :playerName="offererName"
        :canRoll="false"
        :rollData="offererRollData"
      />
      <!-- Chooser's roll -->
      <DuelRollPhase
        :card="myCard"
        :playerName="chooserName"
        :canRoll="isChooser"
        :rollData="chooserRollData"
        @roll="submitRoll"
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
import DuelOfferPhase from './DuelOfferPhase.vue';
import DuelChoosePhase from './DuelChoosePhase.vue';
import DuelRollPhase from './DuelRollPhase.vue';
import DuelResolvePhase from './DuelResolvePhase.vue';
import TurnHandoffOverlay from './TurnHandoffOverlay.vue';
import PlayerItems from './PlayerItems.vue';
import EventReveal from './EventReveal.vue';
import { useAuth } from '../stores/auth';

export default {
  name: 'DuelBoard',
  components: { DuelKingdomStats, DuelOfferPhase, DuelChoosePhase, DuelRollPhase, DuelResolvePhase, TurnHandoffOverlay, PlayerItems, EventReveal },
  setup() {
    const auth = useAuth();
    return { auth };
  },
  props: {
    gameData: { type: Object, required: true },
    gameId: { type: [String, Number], required: true },
  },
  emits: ['refresh', 'game-over'],
  data() {
    return {
      duelPhase: null,
      offererPlayerNumber: null,
      playerKingdoms: [],
      currentCards: [],
      myCard: null,
      offererRollData: null,
      chooserRollData: null,
      isGameOver: false,
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
    isMyTurnToOffer() {
      return this.activePlayerNumber === this.offererNumber;
    },
    isMyTurnToChoose() {
      return this.activePlayerNumber === this.chooserNumber;
    },
    offererName() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.offererNumber);
      return player?.character?.name || `Player ${this.offererNumber}`;
    },
    chooserName() {
      const player = this.gameData?.game?.players?.find(p => p.player_number === this.chooserNumber);
      return player?.character?.name || `Player ${this.chooserNumber}`;
    },
    handoffCharacterName() {
      if (!this.handoffPlayerNumber || !this.gameData?.game?.players) return '';
      const player = this.gameData.game.players.find(p => p.player_number === this.handoffPlayerNumber);
      return player?.character?.name || `Player ${this.handoffPlayerNumber}`;
    },
    currentEvent() {
      return this.gameData?.current_event || null;
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
  },
  methods: {
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

      // Online: determine my player number
      if (this.isOnline) {
        const userId = this.auth.state.user?.id;
        const myPlayer = data.game?.players?.find(p => p.user_id === userId);
        if (myPlayer) {
          this.activePlayerNumber = myPlayer.player_number;
        }
        this.updateOnlineWaiting();
      }

      // If entering offering or choosing phase, load cards
      if (this.duelPhase === 'offering' || this.duelPhase === 'choosing') {
        if (!this.showHandoff) {
          this.loadDuelHand();
        }
      }

      this.checkEventReveal();
    },

    updateOnlineWaiting() {
      if (!this.isOnline) {
        this.showWaiting = false;
        return;
      }

      const phase = this.duelPhase;
      const myNum = this.activePlayerNumber;
      const offNum = this.offererNumber;
      const choNum = this.chooserNumber;

      if (phase === 'offering' && myNum !== offNum) {
        this.showWaiting = true;
        this.waitingMessage = 'Waiting for Offer';
      } else if (phase === 'choosing' && myNum !== choNum) {
        this.showWaiting = true;
        this.waitingMessage = 'Waiting for Choice';
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

    async submitOffer(revealedHandId) {
      try {
        await axios.post(`/api/games/${this.gameId}/duel-offer`, {
          revealed_hand_id: revealedHandId,
        });

        this.duelPhase = 'choosing';

        if (this.isPassAndPlay) {
          // Handoff to chooser
          this.initiatePassAndPlayHandoff(this.chooserNumber);
        } else {
          this.updateOnlineWaiting();
        }
      } catch (e) {
        alert('Failed to offer: ' + (e.response?.data?.error || e.message));
      }
    },

    async submitChoice(chosenHandId) {
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-choose`, {
          chosen_hand_id: chosenHandId,
        });

        this.duelPhase = 'rolling_offerer';

        // Store the cards for the rolling phase
        if (this.isPassAndPlay) {
          // Handoff to offerer to roll first
          this.myCard = res.data.offerer_card?.card || null;
          this.initiatePassAndPlayHandoff(this.offererNumber);
        } else {
          // Online: load my card
          await this.loadMyRollCard();
          this.updateOnlineWaiting();
        }
      } catch (e) {
        alert('Failed to choose: ' + (e.response?.data?.error || e.message));
      }
    },

    async loadMyRollCard() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}/duel-hand/${this.activePlayerNumber}`);
        const cards = res.data.cards || [];
        this.myCard = cards[0]?.card || null;
      } catch {
        this.myCard = null;
      }
    },

    async submitRoll() {
      try {
        const res = await axios.post(`/api/games/${this.gameId}/duel-roll`);

        const rollResult = res.data;

        if (this.duelPhase === 'rolling_offerer' || this.gameData?.game?.duel_phase === 'rolling_offerer') {
          this.offererRollData = rollResult;
          this.duelPhase = 'rolling_chooser';

          // Refresh kingdoms
          await this.refreshKingdoms();

          if (this.isPassAndPlay) {
            // Load chooser's card and handoff
            this.myCard = null;
            this.initiatePassAndPlayHandoff(this.chooserNumber);
          } else {
            this.updateOnlineWaiting();
          }
        } else {
          this.chooserRollData = rollResult;
          this.duelPhase = 'resolving';

          // Refresh kingdoms
          await this.refreshKingdoms();

          // Check for instant win/loss
          if (rollResult.duel_result) {
            this.isGameOver = true;
          }

          if (this.isPassAndPlay && this.offererRollData?.duel_result) {
            this.isGameOver = true;
          }
        }
      } catch (e) {
        alert('Failed to roll: ' + (e.response?.data?.error || e.message));
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
        this.myCard = null;
        this.currentCards = [];
        this.isGameOver = false;

        // Update from response
        this.syncFromGameData(res.data);

        if (this.isPassAndPlay) {
          // Handoff to the new offerer
          this.initiatePassAndPlayHandoff(this.offererNumber);
        }
      } catch (e) {
        alert('Failed to advance: ' + (e.response?.data?.error || e.message));
        this.$emit('refresh');
      }
    },

    // Called from GameBoard when duel broadcasts are received
    handleDuelOfferMade(data) {
      this.duelPhase = data.duel_phase;
      this.loadDuelHand();
      this.showWaiting = false;
    },

    handleDuelChoiceMade(data) {
      this.duelPhase = data.duel_phase;
      this.loadMyRollCard();
      this.showWaiting = false;
      this.updateOnlineWaiting();
    },

    handleDuelRollComplete(data) {
      this.duelPhase = data.duel_phase;
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
      this.showWaiting = false;

      // If it's now my turn to roll
      if (this.duelPhase === 'rolling_chooser' && this.activePlayerNumber === this.chooserNumber) {
        this.loadMyRollCard();
      }

      this.updateOnlineWaiting();
    },

    handleNextRoundStarted(data) {
      this.offererRollData = null;
      this.chooserRollData = null;
      this.myCard = null;
      this.currentCards = [];
      this.isGameOver = false;
      this.syncFromGameData(data);
    },
  },

  async mounted() {
    // Initial card load
    if (this.duelPhase === 'offering' || this.duelPhase === 'choosing') {
      if (this.isPassAndPlay) {
        // Show handoff for the active player
        const activeNum = this.duelPhase === 'offering' ? this.offererNumber : this.chooserNumber;
        this.initiatePassAndPlayHandoff(activeNum);
      } else {
        await this.loadDuelHand();
      }
    } else if (this.duelPhase === 'rolling_offerer' || this.duelPhase === 'rolling_chooser') {
      if (this.isPassAndPlay) {
        const activeNum = this.duelPhase === 'rolling_offerer' ? this.offererNumber : this.chooserNumber;
        this.initiatePassAndPlayHandoff(activeNum);
      } else {
        await this.loadMyRollCard();
        this.updateOnlineWaiting();
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
</style>
