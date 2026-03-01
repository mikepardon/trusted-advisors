<template>
  <div class="setup-screen">
    <!-- Auth loading -->
    <div v-if="auth.state.loading" class="auth-loading">
      <p>Loading...</p>
    </div>

    <!-- Not logged in -->
    <LoginRegister v-else-if="!auth.state.user" />

    <!-- Logged in -->
    <template v-else>

    <!-- Daily Challenge Banner -->
    <DailyChallengeBanner />

    <!-- STEP 0: Mode selection -->
    <Transition name="fade" mode="out-in">
    <div v-if="step === 'mode'" key="mode">
      <div class="card-panel">
        <p class="user-greeting">Hail, {{ auth.state.user.name }}</p>
        <h2 class="section-title">New Game</h2>
        <p class="flavor-text">
          The kingdom stands at a crossroads. The realm needs leaders.
          How shall this campaign be waged?
        </p>

        <div class="mode-cards">
          <div
            class="mode-card"
            @click="playSound('clickCard'); gameMode = 'single'; selectMode()"
          >
            <h3 class="mode-title">Single Player</h3>
            <p class="mode-desc">Guide the realm alone with one advisor</p>
          </div>
          <div
            class="mode-card"
            @click="playSound('clickCard'); gameMode = 'pass_and_play'; selectMode()"
          >
            <h3 class="mode-title">Pass and Play</h3>
            <p class="mode-desc">Take turns on the same device (2-6 advisors)</p>
          </div>
          <div
            class="mode-card"
            @click="playSound('clickCard'); gameMode = 'online'; selectMode()"
          >
            <h3 class="mode-title">Online</h3>
            <p class="mode-desc">Play with friends over the network (2-6 advisors)</p>
          </div>
        </div>
      </div>

      <!-- Pending Game Invites -->
      <div v-if="pendingInvites.length > 0" class="card-panel invites-panel">
        <h2 class="section-title">Game Invites</h2>
        <div class="invite-list">
          <div v-for="invite in pendingInvites" :key="invite.id" class="invite-row">
            <span class="invite-from">{{ invite.sender?.name }} invites you to a game</span>
            <div class="invite-actions">
              <button class="btn-primary btn-sm" @click="acceptInvite(invite)">Join</button>
              <button class="btn-sm btn-decline" @click="declineInvite(invite)">Decline</button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- STEP: Game type selection (cooperative vs duel) -->
    <div v-else-if="step === 'gameType'" key="gameType">
      <div class="card-panel">
        <h2 class="section-title">Choose Your Challenge</h2>
        <p class="flavor-text">
          Will you work together to save the realm, or compete to build the greater kingdom?
        </p>

        <div class="mode-cards">
          <div
            class="mode-card"
            @click="playSound('clickCard'); gameType = 'cooperative'; step = 'settings'"
          >
            <h3 class="mode-title">{{ gameMode === 'single' ? 'Classic' : 'Cooperative' }}</h3>
            <p class="mode-desc">{{ gameMode === 'single' ? "You've been requested to save the land, are you up for the challenge?" : 'Work together to guide the kingdom through crisis' }}</p>
          </div>
          <div
            class="mode-card"
            @click="playSound('clickCard'); gameType = 'duel'; numPlayers = 2; step = 'settings'"
          >
            <h3 class="mode-title">Duel</h3>
            <p class="mode-desc">{{ gameMode === 'single' ? 'Challenge a bot: draft cards, build rival kingdoms' : 'Compete head-to-head: draft cards, build rival kingdoms (2 players)' }}</p>
          </div>
        </div>

        <div class="step-nav">
          <button class="back-btn" @click="playSound('clickNav'); step = 'mode'">&#8592; Back</button>
        </div>
      </div>
    </div>

    <!-- STEP 1: Game settings (players/friends + game length) -->
    <div v-else-if="step === 'settings'" key="settings">
      <div class="card-panel">
        <!-- Pass and Play: player count (hidden for duel, locked to 2) -->
        <template v-if="gameMode === 'pass_and_play' && gameType !== 'duel'">
          <h2 class="section-title">How Many Advisors?</h2>
          <p class="flavor-text">
            The realm needs leaders. How many advisors will answer the call?
          </p>

          <div class="player-select">
            <label>Number of Advisors:</label>
            <div class="player-buttons">
              <button
                v-for="n in 5"
                :key="n + 1"
                :class="{ 'btn-primary': numPlayers === n + 1 }"
                @click="playSound('clickToggle'); numPlayers = n + 1"
              >
                {{ n + 1 }}
              </button>
            </div>
          </div>
        </template>

        <!-- Duel mode info -->
        <template v-if="gameType === 'duel'">
          <h2 class="section-title">Duel Mode</h2>
          <p class="flavor-text">
            {{ gameMode === 'single' ? 'Challenge a bot to build rival kingdoms. Draft cards through bluff and choice.' : 'Two advisors compete to build rival kingdoms. Draft cards through bluff and choice. 2 players locked.' }}
          </p>

          <!-- Bot difficulty selector for single-player duel -->
          <div v-if="gameMode === 'single'" class="bot-difficulty-select">
            <label>Bot Difficulty:</label>
            <div class="player-buttons">
              <button
                v-for="d in ['easy', 'medium', 'hard']"
                :key="d"
                :class="{ 'btn-primary': botDifficulty === d }"
                @click="playSound('clickToggle'); botDifficulty = d"
              >
                {{ d.charAt(0).toUpperCase() + d.slice(1) }}
              </button>
            </div>
          </div>
        </template>

        <!-- Online: friends picker (cooperative only) -->
        <template v-if="gameMode === 'online' && gameType !== 'duel'">
          <h2 class="section-title">Invite Your Allies</h2>
          <p class="flavor-text">
            Select the friends who will join your council.
            You will be added automatically.
          </p>

          <div v-if="friendsLoading" class="friends-loading">Loading friends...</div>

          <div v-else class="friends-picker">
            <!-- Add friend inline -->
            <div class="add-friend-inline">
              <input
                v-model="addFriendUsername"
                type="text"
                placeholder="Add friend by username..."
                class="friend-input"
                @keyup.enter="addFriendInline"
              />
              <button class="btn-primary btn-sm" @click="addFriendInline" :disabled="!addFriendUsername.trim()">Add</button>
            </div>
            <p v-if="addFriendError" class="friend-error">{{ addFriendError }}</p>
            <p v-if="addFriendSuccess" class="friend-success">{{ addFriendSuccess }}</p>

            <!-- Pending received friend requests -->
            <div v-if="pendingReceivedFriends.length" class="received-requests">
              <label class="received-label">Pending Friend Requests</label>
              <div v-for="req in pendingReceivedFriends" :key="req.id" class="received-row">
                <span class="received-name">{{ req.user.name }}</span>
                <button class="btn-primary btn-sm" @click.stop="acceptFriendInline(req.id)">Accept</button>
              </div>
            </div>

            <div v-if="availableFriends.length === 0" class="no-friends">
              <p>No friends yet. Add a friend above to get started!</p>
            </div>

            <div v-else class="friend-pick-list">
              <div
                v-for="friend in availableFriends"
                :key="friend.id"
                :class="['friend-pick-row', { 'friend-selected': selectedFriendIds.includes(friend.user.id) }]"
                @click="toggleFriend(friend.user.id)"
              >
                <span class="friend-pick-check">{{ selectedFriendIds.includes(friend.user.id) ? '&#10003;' : '' }}</span>
                <span class="friend-pick-name">{{ friend.user.name }}</span>
              </div>
            </div>

            <div class="selected-count">
              {{ selectedFriendIds.length + 1 }} advisor{{ selectedFriendIds.length + 1 !== 1 ? 's' : '' }} (you + {{ selectedFriendIds.length }} friend{{ selectedFriendIds.length !== 1 ? 's' : '' }})
            </div>
          </div>
        </template>

        <!-- Single player heading -->
        <template v-if="gameMode === 'single'">
          <h2 class="section-title">Campaign Settings</h2>
          <p class="flavor-text">
            Choose how long your campaign shall last.
          </p>
        </template>

        <!-- Game length (all modes) -->
        <div class="length-select">
          <label>Campaign Length:</label>
          <div class="length-buttons">
            <button
              v-for="opt in gameLengthOptions"
              :key="opt.rounds"
              :class="['length-btn', { 'btn-primary': totalRounds === opt.rounds }]"
              @click="playSound('clickToggle'); totalRounds = opt.rounds"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <div class="step-nav">
          <button class="back-btn" @click="playSound('clickNav'); goBack()">&#8592; Back</button>
          <button
            class="btn-primary start-btn"
            @click="playSound('clickButton'); gatherAdvisors()"
            :disabled="loading || (gameMode === 'online' && gameType !== 'duel' && selectedFriendIds.length === 0)"
          >
            {{ loading ? 'Creating...' : (gameMode === 'online' && gameType === 'duel' ? 'Find Opponent' : 'Gather Advisors') }}
          </button>
        </div>
      </div>
    </div>

    <!-- STEP: Matchmaking queue (online duel) -->
    <div v-else-if="step === 'matchmaking'" key="matchmaking">
      <MatchmakingQueue
        :totalRounds="totalRounds"
        @matched="onMatchFound"
        @cancelled="step = 'settings'"
      />
    </div>

    <!-- STEP 2: Story intro -->
    <div v-else-if="step === 'story'" key="story" class="story-step">
      <StoryIntro :numPlayers="numPlayers" @continue="step = 'characters'" />
    </div>

    <!-- STEP 3: Character selection carousel -->
    <div v-else-if="step === 'characters'" key="characters">
      <!-- All players have picked: show summary -->
      <div v-if="allPlayersPicked" class="card-panel summary-panel">
        <h2 class="section-title">Your Council is Assembled</h2>
        <div class="summary-picks">
          <div v-for="(charId, playerNum) in playerSelections" :key="playerNum" class="summary-pick">
            <div class="summary-card">
              <img :src="getCharacterImage(charId)" alt="Advisor" class="summary-portrait" />
              <div class="summary-info">
                <span class="summary-player">Player {{ playerNum }}</span>
                <span class="summary-name">{{ getCharacterName(charId) }}</span>
              </div>
            </div>
          </div>
        </div>
        <button
          class="btn-primary start-btn"
          :disabled="starting"
          @click="playSound('clickButton'); startGame()"
        >
          {{ starting ? 'Beginning...' : 'Begin Campaign' }}
        </button>
      </div>

      <!-- Picking in progress -->
      <div v-else class="card-panel carousel-panel">
        <h2 class="section-title picking-header">
          Player {{ currentPickingPlayer }}, choose your advisor
        </h2>
        <p class="picking-subtitle">Swipe through the available advisors</p>

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
                  <span class="wild-badge">W = {{ char.wild_value }}</span>
                  <span class="wild-desc">{{ char.wild_ability }}: {{ char.wild_ability_description }}</span>
                </div>
              </div>
            </SwiperSlide>
          </Swiper>
        </div>

        <p class="tap-hint">Tap an advisor to select</p>

        <div v-if="lockedCharacters.length" class="locked-section">
          <p class="locked-label">Locked Advisors</p>
          <div class="locked-list">
            <div v-for="c in lockedCharacters" :key="c.id" class="locked-card">
              <img :src="c.image_url || '/images/character.png'" :alt="c.name" class="locked-portrait" />
              <div class="locked-info">
                <span class="locked-name">{{ c.name }}</span>
                <span class="locked-req">{{ c.unlock_requirement }}</span>
              </div>
            </div>
          </div>
        </div>

        <button class="back-btn back-btn-centered" @click="playSound('clickNav'); goBack()">&#8592; Back</button>
      </div>
    </div>
    </Transition>
    </template>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';
import { playSound } from '../sounds';
import DailyChallengeBanner from './DailyChallengeBanner.vue';
import LoginRegister from './LoginRegister.vue';
import MatchmakingQueue from './MatchmakingQueue.vue';
import StoryIntro from './StoryIntro.vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCards } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-cards';

export default {
  name: 'GameSetup',
  components: { DailyChallengeBanner, LoginRegister, MatchmakingQueue, StoryIntro, Swiper, SwiperSlide },
  setup() {
    const auth = useAuth();
    return { auth, playSound };
  },
  data() {
    return {
      step: 'mode',
      gameMode: 'single',
      gameType: 'cooperative',
      numPlayers: 2,
      totalRounds: 24,
      gameId: null,
      characters: [],
      loading: false,
      starting: false,
      // Carousel state
      currentPickingPlayer: 1,
      playerSelections: {},
      swiperInstance: null,
      activeSlideIndex: 0,
      pendingInvites: [],
      // Online friends picker
      availableFriends: [],
      pendingReceivedFriends: [],
      selectedFriendIds: [],
      friendsLoading: false,
      addFriendUsername: '',
      addFriendError: '',
      addFriendSuccess: '',
      botDifficulty: 'medium',
      // Game length options
      gameLengthOptions: [
        { label: '1 Year', rounds: 12 },
        { label: '2 Years', rounds: 24 },
        { label: '3 Years', rounds: 36 },
        { label: '4 Years', rounds: 48 },
        { label: '5 Years', rounds: 60 },
      ],
    };
  },
  computed: {
    swiperModules() {
      return [EffectCards];
    },
    availableCharacters() {
      const selectedIds = Object.values(this.playerSelections);
      return this.characters.filter(c => !selectedIds.includes(c.id) && !c.is_locked_for_user);
    },
    lockedCharacters() {
      return this.characters.filter(c => c.is_locked_for_user);
    },
    allPlayersPicked() {
      // Single-player duel: only 1 character needed (bot gets assigned automatically)
      const needed = (this.gameMode === 'single' && this.gameType === 'duel') ? 1 : this.numPlayers;
      return Object.keys(this.playerSelections).length >= needed;
    },
  },
  watch: {
    '$route'(to) {
      if (to.path === '/') {
        this.resetToHome();
      }
    },
  },
  async mounted() {
    if (this.auth.state.user) {
      await this.fetchPendingInvites();
      this.subscribeToInvites();
    }
  },
  beforeUnmount() {
    if (this.auth.state.user) {
      window.Echo?.leave(`user.${this.auth.state.user.id}`);
    }
  },
  methods: {
    async fetchPendingInvites() {
      try {
        const res = await axios.get('/api/game-invites/pending');
        this.pendingInvites = res.data;
      } catch {
        // silently fail
      }
    },
    subscribeToInvites() {
      if (!window.Echo || !this.auth.state.user) return;
      window.Echo.private(`user.${this.auth.state.user.id}`)
        .listen('GameInviteReceived', () => {
          this.fetchPendingInvites();
        })
        .listen('MatchFound', (data) => {
          if (this.step !== 'matchmaking') {
            this.$router.push(`/game/${data.game_id}`);
          }
        });
    },
    async acceptInvite(invite) {
      try {
        const res = await axios.post(`/api/game-invites/${invite.id}/accept`);
        this.$router.push(`/game/${res.data.game_id}`);
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to accept invite');
      }
    },
    async declineInvite(invite) {
      try {
        await axios.post(`/api/game-invites/${invite.id}/decline`);
        this.pendingInvites = this.pendingInvites.filter(i => i.id !== invite.id);
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to decline invite');
      }
    },
    selectMode() {
      if (this.gameMode === 'single') {
        this.numPlayers = 1;
        this.gameType = 'cooperative';
      } else {
        this.numPlayers = 2;
        this.gameType = 'cooperative';
        if (this.gameMode === 'online') {
          this.fetchFriendsForPicker();
        }
      }
      // All modes can choose game type (single player duel = vs bot)
      this.step = 'gameType';
    },
    async fetchFriendsForPicker() {
      this.friendsLoading = true;
      try {
        const res = await axios.get('/api/friends');
        this.availableFriends = res.data.friends;
        this.pendingReceivedFriends = res.data.pending_received || [];
      } catch {
        this.availableFriends = [];
        this.pendingReceivedFriends = [];
      }
      this.friendsLoading = false;
    },
    async acceptFriendInline(friendshipId) {
      try {
        await axios.post(`/api/friends/${friendshipId}/accept`);
        await this.fetchFriendsForPicker();
      } catch (e) {
        this.addFriendError = e.response?.data?.message || 'Failed to accept';
      }
    },
    toggleFriend(userId) {
      const idx = this.selectedFriendIds.indexOf(userId);
      if (idx >= 0) {
        this.selectedFriendIds.splice(idx, 1);
      } else {
        if (this.selectedFriendIds.length < 5) {
          this.selectedFriendIds.push(userId);
        }
      }
    },
    async addFriendInline() {
      if (!this.addFriendUsername.trim()) return;
      this.addFriendError = '';
      this.addFriendSuccess = '';
      try {
        await axios.post('/api/friends', { username: this.addFriendUsername.trim() });
        this.addFriendSuccess = `Request sent to ${this.addFriendUsername}`;
        this.addFriendUsername = '';
        await this.fetchFriendsForPicker();
      } catch (e) {
        this.addFriendError = e.response?.data?.message || 'Failed to send request';
      }
    },
    onMatchFound(gameId) {
      this.$router.push(`/game/${gameId}`);
    },
    async gatherAdvisors() {
      this.loading = true;
      try {
        if (this.gameMode === 'online' && this.gameType === 'duel') {
          // Online duel: use matchmaking
          this.loading = false;
          this.step = 'matchmaking';
          return;
        }
        if (this.gameMode === 'online') {
          // Online cooperative: numPlayers = selected friends + yourself
          this.numPlayers = this.selectedFriendIds.length + 1;
          const gameRes = await axios.post('/api/games', {
            game_mode: this.gameMode,
            game_type: this.gameType,
            num_players: this.numPlayers,
            total_rounds: this.totalRounds,
          });
          this.gameId = gameRes.data.id;
          // Auto-invite selected friends
          for (const friendUserId of this.selectedFriendIds) {
            try {
              await axios.post(`/api/games/${this.gameId}/invite`, { user_id: friendUserId });
            } catch {
              // silently skip if invite fails
            }
          }
          this.$router.push(`/game/${this.gameId}`);
          return;
        }
        const gamePayload = {
          game_mode: this.gameMode,
          game_type: this.gameType,
          num_players: this.numPlayers,
          total_rounds: this.totalRounds,
        };
        if (this.gameMode === 'single' && this.gameType === 'duel') {
          gamePayload.bot_difficulty = this.botDifficulty;
        }
        const [gameRes, charsRes] = await Promise.all([
          axios.post('/api/games', gamePayload),
          axios.get('/api/characters'),
        ]);
        this.gameId = gameRes.data.id;
        this.characters = charsRes.data;
        this.step = 'story';
      } catch (e) {
        alert('Failed to create game: ' + (e.response?.data?.message || e.message));
      }
      this.loading = false;
    },
    onSwiper(swiper) {
      this.swiperInstance = swiper;
    },
    onSlideChange(swiper) {
      this.activeSlideIndex = swiper.activeIndex;
    },
    selectCharacterByIndex(index) {
      if (index !== this.activeSlideIndex) return;
      const char = this.availableCharacters[index];
      if (!char) return;
      playSound('clickCard');
      this.playerSelections[this.currentPickingPlayer] = char.id;
      const pickCount = (this.gameMode === 'single' && this.gameType === 'duel') ? 1 : this.numPlayers;
      if (this.currentPickingPlayer < pickCount) {
        this.currentPickingPlayer++;
        this.$nextTick(() => {
          this.activeSlideIndex = 0;
          if (this.swiperInstance) {
            this.swiperInstance.slideTo(0, 0);
          }
        });
      }
    },
    getCharacterName(charId) {
      const char = this.characters.find(c => c.id === charId);
      return char ? char.name : 'Unknown';
    },
    getCharacterImage(charId) {
      const char = this.characters.find(c => c.id === charId);
      return char?.image_url || '/images/character.png';
    },
    goBack() {
      if (this.step === 'gameType') {
        this.step = 'mode';
      } else if (this.step === 'settings') {
        if (this.gameMode === 'single') {
          this.step = 'mode';
        } else {
          this.step = 'gameType';
        }
      } else if (this.step === 'matchmaking') {
        this.step = 'settings';
      } else if (this.step === 'story') {
        this.step = 'settings';
      } else if (this.step === 'characters') {
        if (this.currentPickingPlayer > 1) {
          // Go back one player pick
          this.currentPickingPlayer--;
          delete this.playerSelections[this.currentPickingPlayer];
          this.$nextTick(() => {
            this.activeSlideIndex = 0;
            if (this.swiperInstance) {
              this.swiperInstance.slideTo(0, 0);
            }
          });
        } else {
          this.playerSelections = {};
          this.step = 'story';
        }
      }
    },
    resetToHome() {
      this.step = 'mode';
      this.gameId = null;
      this.characters = [];
      this.currentPickingPlayer = 1;
      this.playerSelections = {};
      this.activeSlideIndex = 0;
    },
    async startGame() {
      this.starting = true;
      try {
        const selectedIds = [];
        const pickCount = (this.gameMode === 'single' && this.gameType === 'duel') ? 1 : this.numPlayers;
        for (let i = 1; i <= pickCount; i++) {
          selectedIds.push(this.playerSelections[i]);
        }
        const startPayload = { characters: selectedIds };
        if (this.gameMode === 'single' && this.gameType === 'duel') {
          startPayload.bot_difficulty = this.botDifficulty;
        }
        await axios.post(`/api/games/${this.gameId}/start`, startPayload);
        this.$router.push(`/game/${this.gameId}`);
      } catch (e) {
        alert('Failed to start: ' + (e.response?.data?.error || e.message));
      }
      this.starting = false;
    },
  },
};
</script>

<style scoped>
/* Fade transition between steps */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.35s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.setup-screen {
  max-width: 800px;
  margin: 0 auto;
  height: 100%;
}

.story-step {
  height: 100%;
}

.auth-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 60px 0;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 1.1rem;
}

.user-greeting {
  text-align: center;
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  letter-spacing: 1px;
  margin-bottom: 4px;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  margin-bottom: 15px;
  text-align: center;
}

.flavor-text {
  text-align: center;
  font-style: italic;
  color: var(--text-secondary);
  margin-bottom: 25px;
  line-height: 1.6;
  font-size: 1.1rem;
}

/* Mode cards */
.mode-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.mode-card {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid rgba(138, 106, 46, 0.3);
  border-radius: 10px;
  padding: 18px 20px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.mode-card:hover {
  border-color: rgba(212, 168, 67, 0.5);
}

.mode-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 4px;
}

.mode-desc {
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-style: italic;
}

/* Pending invites */
.invites-panel {
  margin-top: 20px;
}

.invite-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.invite-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: rgba(26, 18, 9, 0.6);
  border: 1px solid rgba(67, 160, 212, 0.3);
  border-radius: 8px;
}

.invite-from {
  color: var(--text-bright, #f0e6d2);
}

.invite-actions {
  display: flex;
  gap: 8px;
}

.btn-sm {
  padding: 4px 14px;
  font-size: 0.85rem;
  border-radius: 4px;
}

.btn-decline {
  background: rgba(160, 48, 32, 0.2);
  color: #d05040;
  border: 1px solid rgba(160, 48, 32, 0.4);
  cursor: pointer;
}

.player-select {
  text-align: center;
  margin-bottom: 20px;
}

.player-select label {
  display: block;
  margin-bottom: 10px;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
}

.player-buttons {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.player-buttons button {
  width: 50px;
  height: 50px;
  font-size: 1.2rem;
  border-radius: 6px;
}

.bot-difficulty-select .player-buttons button {
  width: auto;
  min-width: 80px;
  padding: 8px 16px;
  font-size: 1rem;
}

/* Game length selector */
.length-select {
  text-align: center;
  margin-bottom: 20px;
  margin-top: 20px;
}

.length-select label {
  display: block;
  margin-bottom: 10px;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
}

.length-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
}

.length-btn {
  padding: 8px 16px;
  font-size: 0.9rem;
  border-radius: 6px;
  white-space: nowrap;
  background: rgba(26, 18, 9, 0.6);
  border: 2px solid rgba(138, 106, 46, 0.3);
  color: var(--text-secondary);
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: border-color 0.2s, background 0.2s, color 0.2s;
}

.length-btn:hover {
  border-color: rgba(212, 168, 67, 0.5);
  color: var(--text-bright);
}

.length-btn.btn-primary {
  background: linear-gradient(180deg, #4a3a24, var(--wood-light, #3a2a1a));
  border-color: var(--accent-gold);
  color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.25);
}

/* Friends picker */
.friends-loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px;
}

.friends-picker {
  margin-bottom: 20px;
}

.add-friend-inline {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.friend-input {
  flex: 1;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1rem;
  padding: 8px 12px;
  outline: none;
}

.friend-input:focus {
  border-color: var(--accent-gold);
}

.friend-error {
  color: var(--accent-red);
  font-size: 0.85rem;
  margin-bottom: 6px;
}

.friend-success {
  color: var(--accent-green);
  font-size: 0.85rem;
  margin-bottom: 6px;
}

/* Received friend requests */
.received-requests {
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
}

.received-label {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
  margin-bottom: 8px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.received-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: rgba(67, 160, 212, 0.06);
  border: 1px solid rgba(67, 160, 212, 0.2);
  border-radius: 6px;
  margin-bottom: 6px;
}

.received-name {
  color: var(--text-bright, #f0e6d2);
  font-size: 0.95rem;
}

.no-friends {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px;
}

.friend-pick-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.friend-pick-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  background: rgba(26, 18, 9, 0.6);
  border: 2px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.friend-pick-row:hover {
  border-color: rgba(212, 168, 67, 0.4);
}

.friend-selected {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.friend-pick-check {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(138, 106, 46, 0.4);
  border-radius: 4px;
  color: var(--accent-gold);
  font-size: 0.85rem;
  font-weight: 700;
}

.friend-selected .friend-pick-check {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
}

.friend-pick-name {
  color: var(--text-bright, #f0e6d2);
  font-family: 'Cinzel', serif;
  font-size: 1rem;
}

.selected-count {
  text-align: center;
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  padding: 8px;
  background: rgba(212, 168, 67, 0.06);
  border-radius: 6px;
}

.step-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 25px;
}

.back-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 0.9rem;
  padding: 10px 20px;
  cursor: pointer;
  letter-spacing: 0;
}

.back-btn:hover {
  color: var(--text-bright);
  border-color: var(--border-gold);
  background: none;
  box-shadow: none;
  transform: none;
}

.back-btn-centered {
  display: block;
  margin: 12px auto 0;
}

.start-btn {
  font-size: 1.2rem;
  padding: 12px 40px;
}

/* Carousel panel */
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
  margin-bottom: 30px;
  font-size: 0.95rem;
}

.carousel-wrapper {
  max-width: 340px;
  margin: 0 auto 30px;
  padding: 20px 0;
}

/* Advisor card inside swiper */
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
  border-top: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 0 0 8px 8px;
  padding: 10px;
  margin-top: auto;
  text-align: center;
}

.wild-badge {
  display: inline-block;
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 2px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 4px;
}

.wild-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-style: italic;
  line-height: 1.4;
}

.tap-hint {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin-top: 4px;
  animation: hintPulse 2s ease-in-out infinite;
}

@keyframes hintPulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

/* Locked characters */
.locked-section {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(138, 106, 46, 0.2);
}

.locked-label {
  text-align: center;
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.85rem;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-bottom: 10px;
  opacity: 0.6;
}

.locked-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
}

.locked-card {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(138, 106, 46, 0.15);
  border-radius: 8px;
  padding: 6px 12px;
  opacity: 0.5;
}

.locked-portrait {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid rgba(138, 106, 46, 0.3);
  filter: grayscale(1);
}

.locked-name {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-weight: 600;
}

.locked-req {
  display: block;
  color: var(--accent-gold);
  font-size: 0.7rem;
  opacity: 0.8;
}

/* Summary panel */
.summary-panel {
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.summary-picks {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  justify-content: center;
  margin-bottom: 10px;
}

.summary-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14);
  border: 2px solid var(--accent-gold);
  border-radius: 10px;
  padding: 16px 24px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.15);
}

.summary-portrait {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 2px solid var(--accent-gold);
  object-fit: cover;
}

.summary-info {
  display: flex;
  flex-direction: column;
}

.summary-player {
  font-size: 0.8rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.summary-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .section-title {
    font-size: 1.3rem;
    margin-bottom: 10px;
  }

  .flavor-text {
    font-size: 0.95rem;
    margin-bottom: 18px;
  }

  .player-buttons button {
    width: 42px;
    height: 42px;
    font-size: 1rem;
  }

  .start-btn {
    font-size: 1rem;
    padding: 10px 30px;
  }

  .back-btn {
    font-size: 0.8rem;
    padding: 8px 16px;
  }

  .step-nav {
    margin-top: 18px;
    gap: 10px;
  }

  .carousel-panel {
    padding: 20px 12px;
    overflow: hidden;
  }

  .picking-header {
    font-size: 1.15rem;
  }

  .picking-subtitle {
    margin-bottom: 20px;
    font-size: 0.85rem;
  }

  .carousel-wrapper {
    max-width: 300px;
    margin-bottom: 0;
    padding: 10px 0;
  }

  .advisor-card {
    width: 280px;
    min-height: 380px;
    padding: 16px;
  }

  .advisor-portrait-wrap {
    width: 90px;
    height: 90px;
    margin-bottom: 10px;
  }

  .advisor-name {
    font-size: 1.1rem;
  }

  .advisor-desc {
    font-size: 0.82rem;
    margin-bottom: 8px;
  }

  .tap-hint {
    font-size: 0.78rem;
  }

  .summary-panel {
    padding: 20px 12px;
  }

  .summary-card {
    padding: 10px 14px;
    gap: 10px;
  }

  .summary-portrait {
    width: 44px;
    height: 44px;
  }

  .length-btn {
    padding: 6px 12px;
    font-size: 0.8rem;
  }

  .friend-pick-row {
    padding: 8px 12px;
  }

  .friend-pick-name {
    font-size: 0.9rem;
  }

  .selected-count {
    font-size: 0.8rem;
  }
}
</style>
