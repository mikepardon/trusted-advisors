<template>
  <div class="lobby">
    <div class="card-panel lobby-panel">
      <h2 class="section-title">Online Lobby</h2>
      <p class="lobby-subtitle">Waiting for advisors to gather ({{ filledSlots }}/{{ numPlayers }})</p>

      <!-- Player Slots -->
      <div class="player-slots">
        <div
          v-for="slot in numPlayers"
          :key="slot"
          :class="['player-slot', slotState(slot)]"
        >
          <div v-if="getPlayerForSlot(slot)" class="slot-filled">
            <span class="slot-number">Player {{ slot }}</span>
            <span class="slot-name">{{ getPlayerForSlot(slot).user?.name || 'Player' }}</span>
            <span v-if="getPlayerForSlot(slot).character" class="slot-character">
              {{ getPlayerForSlot(slot).character.name }}
            </span>
            <span v-else class="slot-picking">Choosing advisor...</span>
          </div>
          <div v-else-if="getPendingInviteForSlot(slot)" class="slot-pending">
            <span class="slot-number">Slot {{ slot }}</span>
            <span class="slot-name">{{ getPendingInviteForSlot(slot).receiver?.name }}</span>
            <span class="slot-status">Invite pending...</span>
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

      <!-- Character Selection (for current user if in game and no character yet) -->
      <div v-if="myPlayer && !myPlayer.character_id" class="character-select-section">
        <h3 class="invite-title">Choose Your Advisor</h3>
        <div class="character-grid">
          <div
            v-for="char in availableCharacters"
            :key="char.id"
            :class="['char-option', { 'char-selected': selectedCharacterId === char.id }]"
            @click="selectedCharacterId = char.id"
          >
            <span class="char-name">{{ char.name }}</span>
            <span class="char-desc">{{ char.description }}</span>
          </div>
        </div>
        <button
          v-if="selectedCharacterId"
          class="btn-primary select-char-btn"
          @click="confirmCharacter"
          :disabled="selectingCharacter"
        >
          {{ selectingCharacter ? 'Selecting...' : 'Confirm Advisor' }}
        </button>
      </div>

      <div v-if="myPlayer && myPlayer.character_id" class="my-selection">
        <p>Your advisor: <strong>{{ myPlayer.character?.name }}</strong></p>
      </div>

      <!-- Host Start Button -->
      <button
        v-if="isHost && canStart"
        class="btn-primary start-btn"
        @click="$emit('start-game')"
      >
        Begin Campaign
      </button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';

export default {
  name: 'OnlineLobby',
  props: {
    gameId: { type: [String, Number], required: true },
    hostId: { type: Number, required: true },
  },
  emits: ['start-game', 'lobby-updated'],
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      players: [],
      invites: [],
      characters: [],
      friends: [],
      numPlayers: 2,
      inviting: false,
      selectedCharacterId: null,
      selectingCharacter: false,
      echoChannel: null,
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
      return this.characters.filter(c => !this.takenCharacterIds.includes(c.id));
    },
    canStart() {
      return this.players.length >= this.numPlayers
        && this.players.every(p => p.character_id);
    },
  },
  async mounted() {
    await this.fetchLobby();
    await this.fetchFriends();
    this.subscribeToChannel();
  },
  beforeUnmount() {
    if (this.echoChannel) {
      window.Echo.leave(`game.${this.gameId}`);
      this.echoChannel = null;
    }
  },
  methods: {
    async fetchLobby() {
      try {
        const res = await axios.get(`/api/games/${this.gameId}/lobby`);
        this.players = res.data.players;
        this.invites = res.data.invites;
        this.characters = res.data.characters;
        this.numPlayers = res.data.num_players;
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
    subscribeToChannel() {
      this.echoChannel = window.Echo.private(`game.${this.gameId}`)
        .listen('PlayerJoinedGame', () => {
          this.fetchLobby();
        })
        .listen('PlayerSelectedCharacter', () => {
          this.fetchLobby();
        })
        .listen('GameStarted', () => {
          this.$emit('lobby-updated');
        });
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
    async confirmCharacter() {
      this.selectingCharacter = true;
      try {
        await axios.post(`/api/games/${this.gameId}/select-character`, {
          character_id: this.selectedCharacterId,
        });
        await this.fetchLobby();
        this.selectedCharacterId = null;
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to select character');
      }
      this.selectingCharacter = false;
    },
    getPlayerForSlot(slot) {
      return this.players.find(p => p.player_number === slot);
    },
    getPendingInviteForSlot(slot) {
      // Map pending invites to unfilled slots
      const filledSlots = this.players.map(p => p.player_number);
      const emptySlotIndex = slot - filledSlots.filter(s => s <= slot).length;
      const pending = this.pendingInvites;
      if (emptySlotIndex >= 0 && emptySlotIndex < pending.length) {
        return pending[emptySlotIndex];
      }
      return null;
    },
    slotState(slot) {
      if (this.getPlayerForSlot(slot)) {
        const player = this.getPlayerForSlot(slot);
        return player.character_id ? 'slot-ready' : 'slot-joined';
      }
      if (this.getPendingInviteForSlot(slot)) return 'slot-invited';
      return 'slot-vacant';
    },
  },
};
</script>

<style scoped>
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
}

.slot-ready {
  border-color: var(--accent-green, #4a8a3a);
  background: rgba(74, 138, 58, 0.08);
}

.slot-joined {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.slot-invited {
  border-color: rgba(138, 106, 46, 0.4);
}

.slot-vacant {
  opacity: 0.5;
}

.slot-number {
  display: block;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  margin-bottom: 2px;
}

.slot-name {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--text-bright, #f0e6d2);
  font-size: 1.1rem;
}

.slot-character {
  display: block;
  color: var(--accent-gold);
  font-style: italic;
  font-size: 0.9rem;
}

.slot-picking,
.slot-status {
  display: block;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
}

.invite-section,
.character-select-section {
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

.character-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.char-option {
  padding: 12px 16px;
  border: 2px solid rgba(138, 106, 46, 0.3);
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.2s;
  background: rgba(26, 18, 9, 0.6);
}

.char-option:hover {
  border-color: rgba(212, 168, 67, 0.5);
}

.char-selected {
  border-color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.char-name {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  margin-bottom: 2px;
}

.char-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
}

.select-char-btn {
  display: block;
  margin: 0 auto;
}

.my-selection {
  text-align: center;
  padding: 16px;
  color: var(--text-bright, #f0e6d2);
  margin-bottom: 16px;
}

.my-selection strong {
  color: var(--accent-gold);
}

.start-btn {
  display: block;
  margin: 20px auto 0;
  font-size: 1.2rem;
  padding: 12px 40px;
}
</style>
