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

    <!-- Home Top Bar (mobile header replacement) -->
    <div v-if="step === 'mode'" class="home-top-bar">
      <div class="home-top-left" @click="$router.push('/profile')">
        <div class="home-avatar-ring-wrap">
          <svg class="home-xp-ring" viewBox="0 0 44 44">
            <circle class="home-xp-ring-bg" cx="22" cy="22" r="20" />
            <circle
              class="home-xp-ring-progress"
              cx="22" cy="22" r="20"
              :stroke-dasharray="xpRingCircumference"
              :stroke-dashoffset="xpRingOffset"
            />
          </svg>
          <div class="home-avatar">{{ auth.state.user.name?.charAt(0)?.toUpperCase() || '?' }}</div>
        </div>
        <div class="home-user-info">
          <span class="home-username">{{ auth.state.user.name }}</span>
          <span class="home-level">Lv.{{ homeStats.level || 1 }}</span>
        </div>
      </div>
      <div class="home-top-right">
        <div class="home-elo" @click="$router.push('/leaderboard')">
          <span class="elo-trophy">&#127942;</span>
          <span class="elo-value">{{ auth.state.user?.elo_rating || 1000 }}</span>
        </div>
        <div class="home-coins" @click="$router.push('/shop')">
          <span>&#129689;</span>
          <span>{{ auth.state.user.coins ?? 0 }}</span>
        </div>
      </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div v-if="showMobileMenu" class="mobile-menu-overlay" @click.self="showMobileMenu = false">
      <div class="mobile-menu-panel">
        <button class="mobile-menu-item" @click="showMobileMenu = false; openRules()">Rules</button>
        <button class="mobile-menu-item" @click="showMobileMenu = false; openTutorial()">Tutorial</button>
        <router-link to="/settings" class="mobile-menu-item" @click="showMobileMenu = false">Settings</router-link>
        <router-link v-if="auth.state.user?.is_admin" to="/admin" class="mobile-menu-item" @click="showMobileMenu = false">Admin</router-link>
      </div>
    </div>

    <!-- STEP 0: Mode selection -->
    <Transition name="fade" mode="out-in">
    <div v-if="step === 'mode'" key="mode" class="home-page">

      <!-- Grid: col-8 (content) + col-4 (nav icons) -->
      <div class="home-grid">
        <div class="home-grid-main">
          <!-- Rotating Events -->
          <RotatingEventBanner />

          <!-- Announcements -->
          <AnnouncementsBanner />

          <!-- Daily Challenge Banner (enhanced) -->
          <div class="daily-enhanced">
            <DailyChallengeBanner />
            <WeeklyChallengeBanner />
          </div>

          <!-- In-Progress Games Button -->
          <div class="card-panel in-progress-card" @click="$router.push('/campaigns')">
            <div class="in-progress-row">
              <span class="in-progress-icon">&#9876;</span>
              <div class="in-progress-text">
                <span class="in-progress-title">{{ homeStats.activeGames > 0 ? 'Continue Game' : 'View Previous Games' }}</span>
                <span class="in-progress-sub">{{ homeStats.activeGames > 0 ? 'View in-progress campaigns' : 'View completed campaigns' }}</span>
              </div>
              <span class="in-progress-arrow">&#8250;</span>
            </div>
          </div>
        </div>

        <div class="home-grid-side">
          <button v-if="auth.state.user?.payments_enabled && !auth.state.user?.is_premium" class="side-icon-btn side-premium-btn" @click="$router.push('/premium')" title="Go Premium">&#9733;</button>
          <button class="side-icon-btn" @click="showMobileMenu = true" title="Menu">&#9776;</button>
          <button class="side-icon-btn" @click="openNotifications()" title="Alerts">
            &#128276;
            <span v-if="notifCount > 0" class="side-badge">{{ notifCount > 9 ? '9+' : notifCount }}</span>
          </button>
          <button class="side-icon-btn" @click="$router.push('/leaderboard')" title="Ranks">&#127942;</button>
          <button class="side-icon-btn" @click="$router.push('/achievements')" title="Achievements">
            &#127941;
            <span v-if="homeStats.unclaimed > 0" class="side-badge">{{ homeStats.unclaimed }}</span>
          </button>
        </div>
      </div>

      <!-- Mode Cards -->
      <div class="mode-cards">
        <div
          class="mode-card mode-card-half"
          @click="playSound('clickCard'); gameMode = 'online'; selectMode()"
        >
          <h3 class="mode-title">Online</h3>
        </div>
        <div
          class="mode-card mode-card-half"
          @click="playSound('clickCard'); gameMode = 'single'; selectMode()"
        >
          <h3 class="mode-title">Single Player</h3>
        </div>
        <div
          class="mode-card"
          @click="playSound('clickCard'); gameMode = 'pass_and_play'; selectMode()"
        >
          <h3 class="mode-title">Pass and Play</h3>
        </div>
        <div
          v-if="auth.state.user?.tournaments_enabled"
          class="mode-card"
          @click="playSound('clickCard'); $router.push('/tournaments')"
        >
          <h3 class="mode-title">&#127942; Tournaments</h3>
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
            @click="playSound('clickCard'); gameType = 'duel'; numPlayers = 2; totalRounds = 24; step = 'settings'"
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
            {{ gameMode === 'single' ? 'Challenge a bot to build rival kingdoms. Pick a card to keep and send the other to your rival.' : 'Two advisors compete to build rival kingdoms. Pick a card to keep and send the other to your rival. 2 players locked.' }}
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

          <!-- Speed mode selector for online duel -->
          <div v-if="gameMode === 'online'" class="speed-select">
            <label>Game Speed:</label>
            <div class="speed-cards">
              <div
                class="speed-card"
                :class="{ selected: speedMode === 'speed' }"
                @click="playSound('clickToggle'); speedMode = 'speed'"
              >
                <span class="speed-icon">&#9889;</span>
                <span class="speed-title">Speed Game</span>
                <span class="speed-desc">2 min per turn</span>
              </div>
              <div
                class="speed-card"
                :class="{ selected: speedMode === 'daily' }"
                @click="playSound('clickToggle'); speedMode = 'daily'"
              >
                <span class="speed-icon">&#9203;</span>
                <span class="speed-title">Daily Turns</span>
                <span class="speed-desc">24 hours per turn</span>
              </div>
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

        <!-- Game length (cooperative only — duels are always 24 rounds) -->
        <div v-if="gameType !== 'duel'" class="length-select">
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

        <!-- Custom Game (premium only) -->
        <div v-if="auth.state.user?.is_premium" class="custom-game-section">
          <label class="custom-toggle">
            <input type="checkbox" v-model="isCustomGame" @change="onCustomToggle" />
            <span class="custom-toggle-label">Custom Game</span>
          </label>

          <p v-if="isCustomGame" class="custom-warning">Custom games do not count towards leaderboards, achievements, or XP.</p>

          <div v-if="isCustomGame" class="custom-options">
            <div class="custom-option">
              <label>Starting Stats: {{ customStartingStats }}</label>
              <input type="range" v-model.number="customStartingStats" min="1" max="20" class="custom-slider" />
            </div>

            <div class="custom-option">
              <label class="hr-label">House Rules</label>
              <label class="hr-toggle"><input type="checkbox" v-model="houseRules.no_negative_effects" /> No Negative Effects</label>
              <label class="hr-toggle"><input type="checkbox" v-model="houseRules.double_positive_effects" /> Double Positive Effects</label>
              <label class="hr-toggle"><input type="checkbox" v-model="houseRules.random_starting_stats" /> Random Starting Stats</label>
              <label class="hr-toggle"><input type="checkbox" v-model="houseRules.hardcore_mode" /> Hardcore (lose at stat &le; 3)</label>
            </div>
          </div>
        </div>

        <!-- Private lobby (premium only, online) -->
        <div v-if="gameMode === 'online' && auth.state.user?.is_premium" class="private-section">
          <label class="custom-toggle">
            <input type="checkbox" v-model="isPrivateGame" />
            <span class="custom-toggle-label">Private Game</span>
          </label>
          <input v-if="isPrivateGame" v-model="lobbyPassword" type="text" class="lobby-password-input" placeholder="Set password..." />
        </div>

        <!-- Lobby browser (online, before creating) -->
        <div v-if="gameMode === 'online' && !showLobbyBrowser" class="lobby-browse-link">
          <button class="browse-btn" @click="fetchLobbies">Browse Open Lobbies</button>
        </div>

        <div v-if="showLobbyBrowser" class="lobby-browser">
          <h3 class="lobby-title">Open Lobbies</h3>
          <div v-if="lobbiesLoading" class="lobby-loading">Loading...</div>
          <div v-else-if="lobbies.length === 0" class="lobby-empty">No open lobbies.</div>
          <div v-else class="lobby-list">
            <div v-for="lobby in lobbies" :key="lobby.id" class="lobby-row">
              <div class="lobby-info">
                <span class="lobby-host">{{ lobby.host_name }}</span>
                <span class="lobby-meta">{{ lobby.game_type }} &bull; {{ lobby.current_players }}/{{ lobby.num_players }}</span>
              </div>
              <span v-if="lobby.is_private" class="lobby-lock">&#128274;</span>
              <button class="lobby-join-btn" @click="joinLobby(lobby)">Join</button>
            </div>
          </div>
          <button class="browse-close" @click="showLobbyBrowser = false">Close</button>
        </div>

        <!-- Lobby password modal -->
        <div v-if="lobbyPasswordModal" class="modal-overlay" @click.self="lobbyPasswordModal = null">
          <div class="modal-box">
            <h3>Enter Lobby Password</h3>
            <input v-model="lobbyJoinPassword" type="text" class="lobby-password-input" placeholder="Password..." @keyup.enter="doJoinLobby" />
            <div class="modal-actions">
              <button class="modal-cancel" @click="lobbyPasswordModal = null">Cancel</button>
              <button class="modal-confirm" @click="doJoinLobby">Join</button>
            </div>
          </div>
        </div>

        <div class="step-nav">
          <button class="back-btn" @click="playSound('clickNav'); goBack()">&#8592; Back</button>
          <button
            class="btn-primary start-btn"
            @click="playSound('clickButton'); gatherAdvisors()"
            :disabled="loading || (gameMode === 'online' && gameType !== 'duel' && selectedFriendIds.length === 0) || (isPrivateGame && !lobbyPassword.trim())"
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
        :speedMode="speedMode"
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
                    <span class="dice-face" v-for="(face, fi) in die" :key="fi">{{ face === 'WILD' ? 'W' : face }}</span>
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
import { useToast } from '../stores/toast';
import { playSound } from '../sounds';
import AnnouncementsBanner from './AnnouncementsBanner.vue';
import DailyChallengeBanner from './DailyChallengeBanner.vue';
import WeeklyChallengeBanner from './WeeklyChallengeBanner.vue';
import RotatingEventBanner from './RotatingEventBanner.vue';
import LoginRegister from './LoginRegister.vue';
import MatchmakingQueue from './MatchmakingQueue.vue';
import StoryIntro from './StoryIntro.vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCards } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-cards';

export default {
  name: 'GameSetup',
  components: { AnnouncementsBanner, DailyChallengeBanner, WeeklyChallengeBanner, RotatingEventBanner, LoginRegister, MatchmakingQueue, StoryIntro, Swiper, SwiperSlide },
  inject: {
    openNotifications: { default: () => () => {} },
    openRules: { default: () => () => {} },
    openTutorial: { default: () => () => {} },
  },
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast, playSound };
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
      // Home screen stats
      homeStats: { level: 1, elo: 1000, unclaimed: 0, activeGames: 0 },
      notifCount: 0,
      showMobileMenu: false,
      speedMode: 'speed',
      // Custom game
      isCustomGame: false,
      customStartingStats: 8,
      houseRules: {
        no_negative_effects: false,
        double_positive_effects: false,
        random_starting_stats: false,
        hardcore_mode: false,
      },
      // Private lobby
      isPrivateGame: false,
      lobbyPassword: '',
      // Lobby browser
      showLobbyBrowser: false,
      lobbies: [],
      lobbiesLoading: false,
      lobbyPasswordModal: null,
      lobbyJoinPassword: '',
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
    xpProgress() {
      const user = this.auth.state.user;
      if (!user) return 0;
      const level = user.level ?? 1;
      const xp = user.xp ?? 0;
      const currentLevelXp = 100 * (level - 1) * level / 2;
      const nextLevelXp = 100 * level * (level + 1) / 2;
      const range = nextLevelXp - currentLevelXp;
      if (range <= 0) return 1;
      return Math.min(Math.max((xp - currentLevelXp) / range, 0), 1);
    },
    xpRingCircumference() {
      return 2 * Math.PI * 20;
    },
    xpRingOffset() {
      return this.xpRingCircumference * (1 - this.xpProgress);
    },
    availableCharacters() {
      if (this.gameType === 'duel') {
        // Duel: both players can pick the same character
        return this.characters.filter(c => !c.is_locked_for_user);
      }
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
      this.fetchHomeStats();
    }
  },
  beforeUnmount() {
    if (this.auth.state.user) {
      window.Echo?.leave(`user.${this.auth.state.user.id}`);
    }
  },
  methods: {
    async fetchHomeStats() {
      try {
        const [statsRes, achRes, historyRes, unreadRes] = await Promise.allSettled([
          axios.get('/api/auth/stats'),
          axios.get('/api/achievements'),
          axios.get('/api/games/history'),
          axios.get('/api/notifications/unread-count'),
        ]);

        if (statsRes.status === 'fulfilled') {
          const s = statsRes.value.data;
          this.homeStats.level = s.level || 1;
          this.homeStats.elo = s.elo_rating || 1000;
        }

        if (achRes.status === 'fulfilled') {
          this.homeStats.unclaimed = achRes.value.data.filter(a => a.earned && !a.claimed).length;
        }

        if (historyRes.status === 'fulfilled') {
          this.homeStats.activeGames = (historyRes.value.data.active_games || []).length;
        }

        // Notification badge: pending invites + unread DB notifications
        const dbUnread = unreadRes.status === 'fulfilled' ? (unreadRes.value.data?.count || 0) : 0;
        this.notifCount = this.pendingInvites.length + dbUnread;
      } catch {}
    },
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
          this.notifCount++;
        })
        .listen('FriendRequestReceived', () => {
          this.notifCount++;
        })
        .listen('UserNotificationReceived', () => {
          this.notifCount++;
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
        this.toast.error(e.response?.data?.error || 'Failed to accept invite');
      }
    },
    async declineInvite(invite) {
      try {
        await axios.post(`/api/game-invites/${invite.id}/decline`);
        this.pendingInvites = this.pendingInvites.filter(i => i.id !== invite.id);
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to decline invite');
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
    onCustomToggle() {
      // Reset when toggling off
      if (!this.isCustomGame) {
        this.customStartingStats = 8;
        this.houseRules = { no_negative_effects: false, double_positive_effects: false, random_starting_stats: false, hardcore_mode: false };
      }
    },
    async fetchLobbies() {
      this.showLobbyBrowser = true;
      this.lobbiesLoading = true;
      try {
        const res = await axios.get('/api/games/lobbies');
        this.lobbies = res.data;
      } catch {
        this.lobbies = [];
      }
      this.lobbiesLoading = false;
    },
    joinLobby(lobby) {
      if (lobby.is_private) {
        this.lobbyPasswordModal = lobby;
        this.lobbyJoinPassword = '';
      } else {
        this.doJoinLobbyDirect(lobby.id);
      }
    },
    async doJoinLobby() {
      const id = this.lobbyPasswordModal?.id;
      if (!id) return;
      try {
        const res = await axios.post(`/api/games/${id}/join`, { password: this.lobbyJoinPassword });
        this.lobbyPasswordModal = null;
        this.$router.push(`/game/${res.data.game_id}`);
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to join lobby.');
      }
    },
    async doJoinLobbyDirect(id) {
      try {
        const res = await axios.post(`/api/games/${id}/join`);
        this.$router.push(`/game/${res.data.game_id}`);
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to join lobby.');
      }
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
          const onlinePayload = {
            game_mode: this.gameMode,
            game_type: this.gameType,
            num_players: this.numPlayers,
            total_rounds: this.totalRounds,
          };
          if (this.isCustomGame) {
            onlinePayload.is_custom = true;
            onlinePayload.starting_stats = this.customStartingStats;
            onlinePayload.house_rules = { ...this.houseRules };
          }
          if (this.isPrivateGame) {
            onlinePayload.is_private = true;
            onlinePayload.lobby_password = this.lobbyPassword;
          }
          const gameRes = await axios.post('/api/games', onlinePayload);
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
        if (this.isCustomGame) {
          gamePayload.is_custom = true;
          gamePayload.starting_stats = this.customStartingStats;
          gamePayload.house_rules = { ...this.houseRules };
        }
        const [gameRes, charsRes] = await Promise.all([
          axios.post('/api/games', gamePayload),
          axios.get('/api/characters'),
        ]);
        this.gameId = gameRes.data.id;
        this.characters = charsRes.data;
        this.step = 'story';
      } catch (e) {
        this.toast.error('Failed to create game: ' + (e.response?.data?.message || e.message));
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
      this.fetchHomeStats();
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
        this.toast.error('Failed to start: ' + (e.response?.data?.error || e.message));
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
  display: flex;
  flex-direction: column;
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

/* Home page layout — fills height, pushes mode cards to bottom */
.home-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

/* Mode cards */
.mode-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: space-between;
}

.mode-card {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid rgba(138, 106, 46, 0.3);
  border-radius: 10px;
  padding: 12px 14px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
  width: 100%;
  text-align: center;
}

.mode-card-half {
  width: 48%;
}

.mode-card:hover {
  border-color: rgba(212, 168, 67, 0.5);
}

.mode-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 0;
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

/* Speed mode selector */
.speed-select {
  text-align: center;
  margin-top: 20px;
  margin-bottom: 20px;
}

.speed-select label {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 2px;
  margin-bottom: 10px;
}

.speed-cards {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.speed-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 14px 20px;
  border: 2px solid rgba(138, 106, 46, 0.3);
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.2);
  cursor: pointer;
  transition: all 0.2s;
  min-width: 120px;
}

.speed-card:hover {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.speed-card.selected {
  border-color: var(--accent-gold);
  background: linear-gradient(180deg, rgba(184, 148, 46, 0.3), rgba(138, 106, 20, 0.2));
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.25);
}

.speed-icon {
  font-size: 1.6rem;
}

.speed-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  font-weight: 700;
}

.speed-desc {
  color: var(--text-secondary);
  font-size: 0.8rem;
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
  align-items: stretch;
  gap: 14px;
  margin-top: 25px;
}

.step-nav > button {
  flex: 1;
}

.back-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 1rem;
  padding: 12px 20px;
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
  padding: 12px 20px;
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

/* Home Top Bar */
.home-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  margin-bottom: 8px;
}

.home-top-left {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.home-avatar-ring-wrap {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.home-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent-gold), #8a6a14);
  color: var(--wood-dark);
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--border-gold);
  box-shadow: 0 2px 8px rgba(212, 168, 67, 0.3);
  flex-shrink: 0;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.home-xp-ring {
  width: 44px;
  height: 44px;
  transform: rotate(-90deg);
}

.home-xp-ring-bg {
  fill: none;
  stroke: rgba(138, 106, 46, 0.25);
  stroke-width: 2.5;
}

.home-xp-ring-progress {
  fill: none;
  stroke: var(--accent-gold);
  stroke-width: 2.5;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s ease;
}

.home-user-info {
  display: flex;
  flex-direction: column;
}

.home-username {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.2;
}

.home-level {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.7rem;
  letter-spacing: 1px;
}

.home-top-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.home-elo {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.2s;
}

.home-elo:hover {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
}

.elo-trophy {
  font-size: 0.9rem;
}

.elo-value {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 0.85rem;
}

.home-coins {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 16px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  color: var(--accent-gold);
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.home-coins:hover {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
}

/* Home Grid Layout */
.home-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  margin-bottom: 12px;
}

.home-grid-main {
  min-width: 0;
}

.home-grid-side {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 4px;
}

.side-icon-btn {
  position: relative;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: rgba(26, 18, 9, 0.9);
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  padding: 0;
  letter-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
  flex-shrink: 0;
  overflow: visible;
}

.side-icon-btn:hover {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: rgba(42, 31, 20, 0.95);
  transform: none;
  box-shadow: 0 2px 12px rgba(212, 168, 67, 0.2);
}

.side-premium-btn {
  background: linear-gradient(135deg, rgba(212, 168, 67, 0.3), rgba(180, 120, 30, 0.2));
  border-color: var(--accent-gold);
  color: var(--accent-gold);
  box-shadow: 0 0 10px rgba(212, 168, 67, 0.25), 0 2px 8px rgba(0, 0, 0, 0.4);
}

.side-premium-btn:hover {
  background: linear-gradient(135deg, rgba(212, 168, 67, 0.45), rgba(180, 120, 30, 0.3));
  box-shadow: 0 0 16px rgba(212, 168, 67, 0.4), 0 2px 12px rgba(212, 168, 67, 0.2);
}

.side-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #e74c3c;
  color: #fff;
  font-size: 0.55rem;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  min-width: 16px;
  height: 16px;
  line-height: 16px;
  text-align: center;
  border-radius: 8px;
  padding: 0 3px;
}

/* Mobile Menu Overlay */
.mobile-menu-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.mobile-menu-panel {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 8px 0;
  min-width: 200px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
}

.mobile-menu-item {
  display: block;
  width: 100%;
  padding: 12px 24px;
  background: none;
  border: none;
  color: var(--text-primary);
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  text-align: center;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
  letter-spacing: 0;
}

.mobile-menu-item:hover {
  background: rgba(212, 168, 67, 0.1);
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

/* Enhanced Daily Challenge */
.daily-enhanced {
  margin-bottom: 10px;
}

.daily-enhanced :deep(.daily-banner) {
  padding: 14px 16px;
  border-width: 2px;
  border-radius: 10px;
  margin-bottom: 8px;
}

.daily-enhanced :deep(.banner-icon) {
  font-size: 1.6rem;
}

.daily-enhanced :deep(.banner-title) {
  font-size: 1rem;
}

.daily-enhanced :deep(.banner-reward) {
  font-size: 0.85rem;
  padding: 2px 8px;
}

/* In-Progress Games in grid */
.home-grid-main .in-progress-card {
  margin-bottom: 0;
}

/* In-Progress Games Card */
.in-progress-card {
  cursor: pointer;
  transition: border-color 0.2s;
  padding: 10px 14px;
}

.in-progress-card:hover {
  border-color: var(--accent-gold);
}

.in-progress-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.in-progress-icon {
  font-size: 1.5rem;
  color: var(--accent-gold);
  flex-shrink: 0;
}

.in-progress-text {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.in-progress-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  font-weight: 700;
}

.in-progress-sub {
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-style: italic;
}

.in-progress-arrow {
  font-size: 1.5rem;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.hide-mobile {
  display: block;
}

/* Desktop: hide mobile top bar (use App.vue header instead) */
@media (min-width: 769px) {
  .home-top-bar {
    display: none;
  }
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .hide-mobile {
    display: none;
  }

  .section-title {
    font-size: 1.3rem;
    margin-bottom: 10px;
  }

  .flavor-text {
    font-size: 0.95rem;
    margin-bottom: 18px;
  }

  .mode-card {
    padding: 10px 14px;
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

  .custom-options {
    padding: 10px;
  }

  .home-avatar-ring-wrap {
    width: 40px;
    height: 40px;
  }

  .home-avatar {
    width: 34px;
    height: 34px;
    font-size: 0.95rem;
  }

  .home-xp-ring {
    width: 40px;
    height: 40px;
  }

  .home-username {
    font-size: 0.85rem;
  }
}

/* Custom Game Section */
.custom-game-section {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(138, 106, 46, 0.2);
}

.custom-warning {
  margin: 8px 0 0;
  padding: 8px 12px;
  background: rgba(200, 160, 40, 0.12);
  border: 1px solid rgba(200, 160, 40, 0.3);
  border-radius: 6px;
  color: #c0a030;
  font-size: 0.8rem;
  text-align: center;
}

.custom-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  margin-bottom: 10px;
}

.custom-toggle input[type="checkbox"] {
  accent-color: var(--accent-gold);
  width: 18px;
  height: 18px;
}

.custom-toggle-label {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  font-weight: 600;
}

.custom-options {
  padding: 12px 16px;
  background: rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.15);
  border-radius: 8px;
}

.custom-option {
  margin-bottom: 14px;
}

.custom-option label {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.8rem;
  margin-bottom: 6px;
}

.custom-slider {
  width: 100%;
  accent-color: var(--accent-gold);
}

.hr-label {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.hr-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-secondary);
  font-size: 0.82rem;
  margin-bottom: 6px;
  cursor: pointer;
}

.hr-toggle input[type="checkbox"] {
  accent-color: var(--accent-gold);
}

/* Private Game */
.private-section {
  margin-top: 14px;
}

.lobby-password-input {
  width: 100%;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1rem;
  padding: 8px 12px;
  outline: none;
  margin-top: 6px;
  box-sizing: border-box;
}

.lobby-password-input:focus {
  border-color: var(--accent-gold);
}

/* Lobby Browser */
.lobby-browse-link {
  text-align: center;
  margin-top: 14px;
}

.browse-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  padding: 6px 18px;
  border-radius: 6px;
  border: 1px solid rgba(67, 160, 212, 0.4);
  background: rgba(67, 160, 212, 0.1);
  color: #60b8e0;
  cursor: pointer;
}

.browse-btn:hover {
  background: rgba(67, 160, 212, 0.25);
}

.lobby-browser {
  margin-top: 14px;
  padding: 14px;
  background: rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
}

.lobby-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  margin-bottom: 10px;
  text-align: center;
}

.lobby-loading, .lobby-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 12px 0;
  font-size: 0.85rem;
}

.lobby-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.lobby-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.12);
  border-radius: 6px;
}

.lobby-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.lobby-host {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
}

.lobby-meta {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.lobby-lock {
  font-size: 0.8rem;
}

.lobby-join-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  padding: 4px 12px;
  border-radius: 4px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  cursor: pointer;
}

.lobby-join-btn:hover {
  background: rgba(212, 168, 67, 0.3);
}

.browse-close {
  display: block;
  margin: 10px auto 0;
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  padding: 4px 14px;
  border-radius: 4px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
}

/* Lobby password modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: var(--bg-secondary);
  border: 1px solid var(--accent-gold);
  border-radius: 12px;
  padding: 20px 24px;
  max-width: 320px;
  width: 90%;
  text-align: center;
}

.modal-box h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin: 0 0 10px;
  font-size: 1.1rem;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  margin-top: 14px;
}

.modal-cancel,
.modal-confirm {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  padding: 6px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}

.modal-cancel {
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent;
  color: var(--text-secondary);
}

.modal-confirm {
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
}

.modal-confirm:hover {
  background: rgba(212, 168, 67, 0.35);
}
</style>
