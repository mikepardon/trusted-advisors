<template>
  <div class="lobby">
    <!-- PHASE 1: Gathering Players -->
    <Transition name="fade" mode="out-in">
    <div v-if="!allJoined" key="gathering" class="card-panel lobby-panel">
      <h2 class="section-title">Gathering Advisors</h2>
      <p class="lobby-subtitle">Waiting for players to join ({{ filledSlots }}/{{ numPlayers }})</p>

      <div class="player-slots">
        <div
          v-for="slot in numPlayers"
          :key="slot"
          :class="['player-slot', slotState(slot)]"
        >
          <div v-if="getPlayerForSlot(slot)" class="slot-filled">
            <span class="slot-number">Player {{ slot }}</span>
            <span class="slot-name">{{ getPlayerForSlot(slot).user?.name || 'Player' }}</span>
            <span class="slot-badge badge-joined">Joined</span>
          </div>
          <div v-else-if="getPendingInviteForSlot(slot)" class="slot-pending">
            <span class="slot-number">Slot {{ slot }}</span>
            <span class="slot-name">{{ getPendingInviteForSlot(slot).receiver?.name }}</span>
            <span class="slot-badge badge-pending">Invite sent</span>
          </div>
          <div v-else class="slot-empty">
            <span class="slot-number">Slot {{ slot }}</span>
            <span class="slot-status">Empty</span>
          </div>
        </div>
      </div>

      <!-- Invite Friends (host only) -->
      <div v-if="isHost && availableFriends.length > 0 && filledSlots < numPlayers" class="invite-section">
        <h3 class="invite-title">Invite Friends</h3>
        <div class="friend-invite-list">
          <div
            v-for="friend in availableFriends"
            :key="friend.user.id"
            class="friend-invite-row"
          >
            <span class="friend-name">{{ friend.user.name }}</span>
            <button class="btn-small" @click="inviteFriend(friend.user.id)" :disabled="inviting">
              Invite
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- PHASE 2: Character Selection (simultaneous) -->
    <div v-else-if="!allSelected" key="picking" class="card-panel carousel-panel">
      <!-- I still need to pick -->
      <template v-if="myPlayerNeedsPick">
        <h2 class="section-title picking-header">
          Choose your advisor
        </h2>
        <p class="picking-subtitle">Swipe through and tap to select</p>

        <div class="carousel-wrapper">
          <Swiper
            :modules="swiperModules"
            :effect="'cards'"
            :grab-cursor="true"
            :cards-effect="{ perSlideOffset: 8, perSlideRotate: 2, rotate: true, slideShadows: false }"
            :style="{ overflow: 'visible' }"
            @swiper="onSwiper"
            @slideChange="onSlideChange"
          >
            <SwiperSlide
              v-for="char in availableCharacters"
              :key="char.id"
            >
              <div class="advisor-card" @click="selectCharacterByIndex(availableCharacters.indexOf(char))">
                <div class="advisor-portrait-wrap">
                  <img :src="char.image_url || '/images/character.png'" :alt="char.name" class="advisor-portrait" />
                </div>
                <h3 class="advisor-name">{{ char.name }}</h3>
                <p class="advisor-desc">{{ char.description }}</p>
                <div class="advisor-dice">
                  <div class="dice-row" v-for="(die, di) in char.dice" :key="di">
                    <span class="dice-label">Die {{ di + 1 }}:</span>
                    <span class="dice-face" v-for="(face, fi) in die" :key="fi">{{ face }}</span>
                  </div>
                </div>
                <div class="advisor-wild">
                  <span class="wild-badge">WILD = {{ char.wild_value }}</span>
                  <span class="wild-desc">{{ char.wild_ability }}: {{ char.wild_ability_description }}</span>
                </div>
              </div>
            </SwiperSlide>
          </Swiper>
        </div>

        <p class="tap-hint">Tap an advisor to select</p>

        <!-- Show who has already picked -->
        <div v-if="pickedPlayers.length" class="already-picked">
          <div v-for="p in pickedPlayers" :key="p.player_number" class="picked-line">
            {{ p.user?.name }} chose <strong>{{ p.character?.name }}</strong>
          </div>
        </div>
      </template>

      <!-- I already picked — waiting for others -->
      <template v-else>
        <h2 class="section-title">Choosing Advisors</h2>
        <p class="waiting-text">
          You chose <strong>{{ myPlayer.character?.name }}</strong>. Waiting for others...
        </p>
        <div class="pick-progress">
          <div v-for="slot in numPlayers" :key="slot" class="pick-slot">
            <span class="pick-number">Player {{ slot }}</span>
            <span v-if="getPlayerForSlot(slot)?.character" class="pick-char">
              {{ getPlayerForSlot(slot).character.name }}
            </span>
            <span v-else class="pick-active">Choosing...</span>
          </div>
        </div>
      </template>
    </div>

    <!-- PHASE 3: All selected, starting -->
    <div v-else key="starting" class="card-panel lobby-panel">
      <h2 class="section-title">All Advisors Gathered!</h2>
      <p class="lobby-subtitle">The campaign is about to begin...</p>
      <div class="selected-advisors">
        <div v-for="p in players" :key="p.player_number" class="selected-row">
          <span class="selected-player">{{ p.user?.name }}</span>
          <span class="selected-char">{{ p.character?.name }}</span>
        </div>
      </div>
      <div class="starting-spinner">Preparing the realm...</div>
    </div>
    </Transition>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';
import { playSound } from '../sounds';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCards } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-cards';

export default {
  name: 'OnlineLobby',
  components: { Swiper, SwiperSlide },
  props: {
    gameId: { type: [String, Number], required: true },
    hostId: { type: Number, required: true },
  },
  emits: ['start-game', 'lobby-updated'],
  setup() {
    const auth = useAuth();
    return { auth, playSound };
  },
  data() {
    return {
      players: [],
      invites: [],
      characters: [],
      friends: [],
      numPlayers: 2,
      allJoined: false,
      allSelected: false,
      inviting: false,
      selectingCharacter: false,
      autoStarting: false,
      swiperInstance: null,
      activeSlideIndex: 0,
    };
  },
  computed: {
    isHost() {
      return this.auth.state.user?.id === this.hostId;
    },
    myPlayer() {
      return this.players.find(p => p.user_id === this.auth.state.user?.id);
    },
    filledSlots() {
      return this.players.length;
    },
    pendingInvites() {
      return this.invites.filter(i => i.status === 'pending');
    },
    availableFriends() {
      const playerUserIds = this.players.map(p => p.user_id);
      const invitedUserIds = this.invites.map(i => i.receiver_id);
      return this.friends.filter(f =>
        !playerUserIds.includes(f.user.id) && !invitedUserIds.includes(f.user.id)
      );
    },
    takenCharacterIds() {
      return this.players.filter(p => p.character_id).map(p => p.character_id);
    },
    availableCharacters() {
      return this.characters.filter(c => !this.takenCharacterIds.includes(c.id) && !c.is_locked_for_user);
    },
    swiperModules() {
      return [EffectCards];
    },
    myPlayerNeedsPick() {
      if (!this.myPlayer) return false;
      return !this.myPlayer.character_id;
    },
    pickedPlayers() {
      return this.players.filter(p => p.character_id);
    },
  },
  async mounted() {
    await this.fetchLobby();
    await this.fetchFriends();
  },
  methods: {
    async fetchLobby() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}/lobby`);
        this.players = res.data.players;
        this.invites = res.data.invites;
        this.characters = res.data.characters;
        this.numPlayers = res.data.num_players;
        this.allJoined = res.data.all_joined;
        this.allSelected = res.data.all_selected;
      } catch (e) {
        console.error('Failed to fetch lobby', e);
      }
    },
    async fetchFriends() {
      try {
        const res = await axios.get('/api/friends');
        this.friends = res.data.friends || [];
      } catch (e) {
        console.error('Failed to fetch friends', e);
      }
    },
    async inviteFriend(userId) {
      this.inviting = true;
      try {
        await axios.post(`/api/games/${this.gameId}/invite`, { user_id: userId });
        await this.fetchLobby();
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to invite');
      }
      this.inviting = false;
    },
    onSwiper(swiper) {
      this.swiperInstance = swiper;
    },
    onSlideChange(swiper) {
      this.activeSlideIndex = swiper.activeIndex;
    },
    async selectCharacterByIndex(index) {
      if (this.selectingCharacter) return;
      if (index !== this.activeSlideIndex) return;

      const char = this.availableCharacters[index];
      if (!char) return;

      playSound('clickCard');
      this.selectingCharacter = true;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/select-character`, {
          character_id: char.id,
        });
        await this.fetchLobby();

        // If I'm host and all selected, auto-start
        if (res.data.all_selected && this.isHost) {
          await this.autoStartGame();
        }
      } catch (e) {
        const msg = e.response?.data?.error || 'Failed to select character';
        if (msg.toLowerCase().includes('already taken') || msg.toLowerCase().includes('already selected')) {
          await this.fetchLobby();
        } else {
          alert(msg);
        }
      }
      this.selectingCharacter = false;
    },
    async autoStartGame() {
      if (this.autoStarting) return;
      if (!this.isHost) return;
      this.autoStarting = true;
      try {
        const lobbyRes = await axios.get(`/api/games/${this.gameId}/lobby`);
        const players = lobbyRes.data.players;
        const characters = players
          .sort((a, b) => a.player_number - b.player_number)
          .map(p => p.character_id);

        await axios.post(`/api/games/${this.gameId}/start`, { characters });
        // GameStarted broadcast will trigger lobby-updated
      } catch (e) {
        console.error('Auto-start failed', e);
        // Fallback: let host click manually
        this.$emit('start-game');
      }
      this.autoStarting = false;
    },
    getPlayerForSlot(slot) {
      return this.players.find(p => p.player_number === slot);
    },
    getPendingInviteForSlot(slot) {
      const filledSlots = this.players.map(p => p.player_number);
      const emptySlotIndex = slot - filledSlots.filter(s => s <= slot).length;
      const pending = this.pendingInvites;
      if (emptySlotIndex >= 0 && emptySlotIndex < pending.length) {
        return pending[emptySlotIndex];
      }
      return null;
    },
    slotState(slot) {
      if (this.getPlayerForSlot(slot)) return 'slot-joined';
      if (this.getPendingInviteForSlot(slot)) return 'slot-invited';
      return 'slot-vacant';
    },
  },
};
</script>

<style scoped>
.lobby { height: 100%; }

.lobby-panel {
  padding: 30px 20px;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  margin-bottom: 8px;
  text-align: center;
}

.lobby-subtitle {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  margin-bottom: 24px;
}

/* Fade transition */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.35s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* Player slots */
.player-slots {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 24px;
}

.player-slot {
  padding: 14px 18px;
  border-radius: 8px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: rgba(26, 18, 9, 0.6);
  display: flex;
  align-items: center;
  gap: 10px;
}

.slot-joined {
  border-color: var(--accent-green, #4a8a3a);
  background: rgba(74, 138, 58, 0.08);
}

.slot-invited {
  border-color: rgba(138, 106, 46, 0.4);
}

.slot-vacant {
  opacity: 0.5;
}

.slot-number {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  min-width: 60px;
}

.slot-name {
  font-family: 'Cinzel', serif;
  color: var(--text-bright, #f0e6d2);
  font-size: 1rem;
  flex: 1;
}

.slot-badge {
  font-size: 0.7rem;
  padding: 2px 10px;
  border-radius: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.badge-joined {
  background: rgba(74, 138, 58, 0.2);
  color: #4a8a3a;
}

.badge-pending {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
}

.slot-status {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
}

/* Invite section */
.invite-section {
  margin-bottom: 24px;
}

.invite-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 12px;
}

.friend-invite-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.friend-invite-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: rgba(26, 18, 9, 0.6);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
}

.friend-name {
  color: var(--text-bright, #f0e6d2);
}

.btn-small {
  padding: 4px 14px;
  font-size: 0.85rem;
  border-radius: 4px;
  background: var(--accent-gold);
  color: #1a1209;
  border: none;
  cursor: pointer;
  font-weight: 600;
}

.btn-small:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Character selection carousel */
.carousel-panel {
  padding: 30px 20px;
}

.picking-header {
  font-size: 1.4rem;
  margin-bottom: 5px;
}

.picking-subtitle {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  margin-bottom: 20px;
  font-size: 0.95rem;
}

.carousel-wrapper {
  max-width: 340px;
  margin: 0 auto 20px;
  padding: 20px 0;
}

.tap-hint {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
  animation: pulse 2s ease-in-out infinite;
  margin-bottom: 16px;
}

@keyframes pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

.already-picked {
  padding: 12px 16px;
  background: rgba(74, 138, 58, 0.06);
  border: 1px solid rgba(74, 138, 58, 0.2);
  border-radius: 8px;
  margin-top: 10px;
}

.picked-line {
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 4px;
}

.picked-line strong {
  color: var(--accent-gold);
}

/* Waiting for other player to pick */
.waiting-text {
  text-align: center;
  color: var(--text-secondary);
  font-size: 1.1rem;
  margin-bottom: 24px;
  font-style: italic;
}

.waiting-text strong {
  color: var(--accent-gold);
}

.pick-progress {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pick-slot {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid rgba(138, 106, 46, 0.2);
  background: rgba(26, 18, 9, 0.6);
}

.pick-number {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  min-width: 60px;
}

.pick-char {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
}

.pick-active {
  color: var(--accent-green, #4a8a3a);
  font-style: italic;
  animation: pulse 1.5s ease-in-out infinite;
}

.pick-waiting {
  color: var(--text-secondary);
  font-style: italic;
  opacity: 0.5;
}

/* All selected / starting */
.selected-advisors {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 24px;
}

.selected-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid rgba(74, 138, 58, 0.3);
  background: rgba(74, 138, 58, 0.06);
}

.selected-player {
  color: var(--text-bright);
  font-size: 0.95rem;
}

.selected-char {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
}

.starting-spinner {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  animation: pulse 1.5s ease-in-out infinite;
}

/* Advisor card inside swiper (matches GameSetup) */
.advisor-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 20px;
  width: 320px;
  min-height: 440px;
  box-shadow:
    0 8px 32px rgba(0, 0, 0, 0.6),
    inset 0 1px 0 rgba(212, 168, 67, 0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.advisor-portrait-wrap {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid var(--accent-gold);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
  margin-bottom: 15px;
}

.advisor-portrait {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.advisor-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 8px;
  text-align: center;
}

.advisor-desc {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.5;
  text-align: center;
  margin-bottom: 12px;
  font-style: italic;
}

.advisor-dice {
  width: 100%;
  margin-bottom: 12px;
}

.dice-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  justify-content: center;
}

.dice-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
  min-width: 42px;
}

.dice-face {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.8rem;
  font-weight: 600;
}

.advisor-wild {
  width: 100%;
  background: rgba(212, 168, 67, 0.08);
  border: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 6px;
  padding: 8px 10px;
  text-align: center;
}

.wild-badge {
  display: block;
  color: var(--accent-gold);
  font-weight: 700;
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  margin-bottom: 2px;
}

.wild-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-style: italic;
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .lobby-panel, .carousel-panel {
    padding: 16px 12px;
  }

  .section-title {
    font-size: 1.2rem;
  }

  .carousel-wrapper {
    max-width: 300px;
  }

  .advisor-card {
    width: 280px;
    min-height: 380px;
    padding: 16px;
  }

  .advisor-portrait-wrap {
    width: 90px;
    height: 90px;
  }

  .advisor-name {
    font-size: 1.1rem;
  }

  .advisor-desc {
    font-size: 0.8rem;
  }
}
</style>
