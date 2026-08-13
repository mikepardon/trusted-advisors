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
          <button v-if="auth.state.user?.payments_enabled && !auth.state.user?.is_premium" class="side-icon-btn side-premium-btn" title="Go Premium" @click="$router.push('/premium')">&#9733;</button>
          <button class="side-icon-btn" title="Menu" @click="showMobileMenu = true">&#9776;</button>
          <button class="side-icon-btn" title="Alerts" @click="openNotifications()">
            &#128276;
            <span v-if="notifCount > 0" class="side-badge">{{ notifCount > 9 ? '9+' : notifCount }}</span>
          </button>
          <button class="side-icon-btn" title="Ranks" @click="$router.push('/leaderboard')">&#127942;</button>
          <button class="side-icon-btn" title="Achievements" @click="$router.push('/achievements')">
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
          <span class="mode-subtitle">Battle others live</span>
        </div>
        <div
          class="mode-card mode-card-half"
          @click="playSound('clickCard'); gameMode = 'single'; selectMode()"
        >
          <h3 class="mode-title">Single Player</h3>
          <span class="mode-subtitle">Solo campaign</span>
        </div>
        <div
          v-if="auth.state.user?.tournaments_enabled"
          class="mode-card"
          @click="playSound('clickCard'); $router.push('/tournaments')"
        >
          <h3 class="mode-title">Tournaments</h3>
          <span class="mode-subtitle">Compete for glory</span>
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
            @click="playSound('clickCard'); gameType = 'duel'; numberPlayers = 2; totalRounds = 24; step = 'settings'"
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
                :class="{ 'btn-primary': numberPlayers === n + 1 }"
                @click="playSound('clickToggle'); numberPlayers = n + 1"
              >
                {{ n + 1 }}
              </button>
            </div>
          </div>
        </template>

        <!-- Duel mode info (hide all options for event games) -->
        <template v-if="gameType === 'duel' && !rotatingEventId">
          <h2 class="section-title">Duel Mode</h2>
          <p class="flavor-text">
            {{ gameMode === 'single' ? 'Challenge a bot to build rival kingdoms. Pick a card to keep and send the other to your rival.' : 'Two advisors compete to build rival kingdoms. Pick a card to keep and send the other to your rival. 2 players locked.' }}
          </p>

          <!-- Bot difficulty selector for single-player duel -->
          <div v-if="gameMode === 'single'" class="bot-difficulty-select">
            <div class="player-buttons">
              <button
                v-for="d in ['easy', 'medium', 'hard']"
                :key="d"
                :class="{ 'btn-primary': botDifficulty === d }"
                @click="playSound('clickToggle'); botDifficulty = d; gatherAdvisors()"
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
                <span class="speed-desc">45 sec per turn</span>
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
              <button class="btn-primary btn-sm" :disabled="!addFriendUsername.trim()" @click="addFriendInline">Add</button>
            </div>
            <p v-if="addFriendError" class="friend-error">{{ addFriendError }}</p>
            <p v-if="addFriendSuccess" class="friend-success">{{ addFriendSuccess }}</p>

            <!-- Pending received friend requests -->
            <div v-if="pendingReceivedFriends.length > 0" class="received-requests">
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

        <!-- Single player heading (hide for event games) -->
        <template v-if="gameMode === 'single' && gameType !== 'duel' && !rotatingEventId">
          <h2 class="section-title">The King's 5-Year Plan</h2>
          <p class="flavor-text">
            You have been appointed by the King to guide the realm through a 5-year plan. Survive — that is all that matters.
          </p>
        </template>

        <!-- Custom Game (premium only, hidden for event games) -->
        <div v-if="auth.state.user?.is_premium && !rotatingEventId" class="custom-game-section">
          <label class="custom-toggle">
            <input v-model="isCustomGame" type="checkbox" @change="onCustomToggle" />
            <span class="custom-toggle-label">Custom Game</span>
          </label>

          <p v-if="isCustomGame" class="custom-warning">Custom games do not count towards leaderboards, achievements, or XP.</p>

          <div v-if="isCustomGame" class="custom-options">
            <div class="custom-option">
              <label>Starting Stats: {{ customStartingStats }}</label>
              <input v-model.number="customStartingStats" type="range" min="1" max="20" class="custom-slider" />
            </div>

            <div class="custom-option">
              <label class="hr-label">House Rules</label>
              <label class="hr-toggle"><input v-model="houseRules.no_negative_effects" type="checkbox" /> No Negative Effects</label>
              <label class="hr-toggle"><input v-model="houseRules.double_positive_effects" type="checkbox" /> Double Positive Effects</label>
              <label class="hr-toggle"><input v-model="houseRules.random_starting_stats" type="checkbox" /> Random Starting Stats</label>
              <label class="hr-toggle"><input v-model="houseRules.hardcore_mode" type="checkbox" /> Hardcore (lose at stat &le; 3)</label>
            </div>
          </div>
        </div>

        <!-- Private lobby (premium only, online) -->
        <div v-if="gameMode === 'online' && auth.state.user?.is_premium" class="private-section">
          <label class="custom-toggle">
            <input v-model="isPrivateGame" type="checkbox" />
            <span class="custom-toggle-label">Private Game</span>
          </label>
          <input v-if="isPrivateGame" v-model="lobbyPassword" type="text" class="lobby-password-input" placeholder="Set password..." />
        </div>

        <div class="step-nav">
          <button class="back-btn" @click="playSound('clickNav'); goBack()">&#8592; Back</button>
          <button
            v-if="!(gameMode === 'single' && gameType === 'duel')"
            class="btn-primary start-btn"
            :disabled="loading || (gameMode === 'online' && gameType !== 'duel' && selectedFriendIds.length === 0) || (isPrivateGame && !lobbyPassword.trim())"
            @click="playSound('clickButton'); gatherAdvisors()"
          >
            {{ loading ? 'Creating...' : (gameMode === 'online' && gameType === 'duel' ? 'Find Opponent' : 'Gather Advisors') }}
          </button>
        </div>
      </div>
    </div>

    <!-- STEP: Matchmaking queue (online duel) -->
    <div v-else-if="step === 'matchmaking'" key="matchmaking">
      <MatchmakingQueue
        :total-rounds="totalRounds"
        :speed-mode="speedMode"
        @matched="onMatchFound"
        @cancelled="step = 'settings'"
      />
    </div>

    <!-- STEP 2: Story intro -->
    <div v-else-if="step === 'story'" key="story" class="story-step">
      <StoryIntro :number-players="numberPlayers" @continue="step = 'characters'" />
    </div>

    <!-- STEP 3: Character selection carousel -->
    <div v-else-if="step === 'characters'" key="characters">
      <!-- All players have picked: show summary -->
      <div v-if="allPlayersPicked" class="summary-panel">
        <h2 class="section-title">Your Council is Assembled</h2>
        <div class="summary-picks">
          <div v-for="(charId, playerNum) in playerSelections" :key="playerNum" class="summary-pick">
            <div class="summary-card">
              <img :src="getCharacterImage(charId)" alt="Advisor" class="summary-portrait" />
              <div class="summary-info">
                <span class="summary-player">Player {{ playerNum }}</span>
                <span class="summary-name">{{ getCharacterName(charId) }}</span>
                <span v-if="getCharacterBonusLabel(charId)" class="summary-bonus">{{ getCharacterBonusLabel(charId) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="summary-actions">
          <button class="back-btn" @click="playSound('clickNav'); undoLastPick()">&#8592; Back</button>
          <button
            class="btn-primary start-btn"
            :disabled="starting"
            @click="playSound('clickButton'); startGame()"
          >
            {{ starting ? 'Beginning...' : 'Begin' }}
          </button>
        </div>
      </div>

      <!-- Picking in progress -->
      <div v-else class="picking-screen">
        <h2 class="section-title picking-header">
          Player {{ currentPickingPlayer }}, choose your advisor
        </h2>

        <div class="carousel-wrapper">
          <Swiper
            :modules="swiperModules"
            effect="cards"
            :grab-cursor="true"
            :cards-effect="{ perSlideOffset: 8, perSlideRotate: 2, rotate: true, slideShadows: false }"
            :style="{ overflow: 'visible' }"
            @swiper="onSwiper"
            @slide-change="onSlideChange"
          >
            <SwiperSlide v-for="char in availableCharacters" :key="char.id">
              <div class="advisor-card" @click="selectCharacter(char.id)">
                <div class="advisor-portrait-wrap">
                  <img :src="char.image_url || '/images/character.png'" :alt="char.name" class="advisor-portrait" />
                  <span v-if="char.level > 0" class="advisor-level-pip">{{ char.level }}</span>
                </div>
                <h3 class="advisor-name">{{ char.display_name || char.name }}</h3>
                <p class="advisor-desc">{{ char.description }}</p>
                <div v-if="getCharacterBonusLabel(char.id)" class="advisor-bonus-badge">{{ getCharacterBonusLabel(char.id) }}</div>
                <!-- Upgrade bonuses -->
                <div v-if="hasUpgradeBonuses(char)" class="advisor-upgrades">
                  <span v-if="char.extra_item_slots > 0" class="advisor-upgrade-tag">+{{ char.extra_item_slots }} Item Slot{{ char.extra_item_slots > 1 ? 's' : '' }}</span>
                  <span v-if="char.card_redraws > 0" class="advisor-upgrade-tag">{{ char.card_redraws }} Redraw{{ char.card_redraws > 1 ? 's' : '' }}</span>
                  <span v-for="(val, stat) in char.passive_bonuses" :key="stat" class="advisor-upgrade-tag">+{{ val }} {{ stat.charAt(0).toUpperCase() + stat.slice(1) }}</span>
                </div>
                <div class="advisor-stats">
                  <div class="advisor-dice-rows">
                    <div v-for="(die, dIdx) in char.dice" :key="dIdx" class="advisor-die-row">
                      <span class="advisor-die-label">Die {{ dIdx + 1 }}</span>
                      <div class="advisor-die-faces">
                        <span v-for="(face, fIdx) in die" :key="fIdx" class="advisor-die-face" :class="{ 'face-wild': face === 'WILD', 'face-upgraded': char.level > 0 && isDiceUpgraded(char, dIdx, fIdx) }">{{ face === 'WILD' ? 'W' : face }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="advisor-ability">
                    <span class="advisor-wild-tag">WILD = {{ char.wild_value }}</span>
                    <span class="advisor-ability-name">{{ char.wild_ability }}</span>
                    <span v-if="char.wild_ability_description" class="advisor-ability-desc">{{ char.wild_ability_description }}</span>
                  </div>
                </div>
              </div>
            </SwiperSlide>
          </Swiper>
        </div>

        <button class="back-btn back-btn-centered" @click="playSound('clickNav'); goBack()">&#8592; Back</button>
      </div>
    </div>
    </Transition>
    </template>
  </div>
</template>

<script setup lang="ts">
import axios, { isAxiosError } from 'axios';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LocationQueryValue } from 'vue-router';
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
import type { Swiper as SwiperInstance } from 'swiper';
import 'swiper/css';
import 'swiper/css/effect-cards';

interface StartingBonus {
  extra_dice?: number;
  random_item?: boolean;
  stat_boosts?: Record<string, number>;
}

interface Character {
  id: number;
  name: string;
  display_name?: string;
  description?: string;
  image_url?: string;
  level: number;
  dice: (string | number)[][];
  base_dice?: (string | number)[][];
  wild_value?: number;
  wild_ability?: string;
  wild_ability_description?: string;
  starting_bonus?: StartingBonus;
  extra_item_slots: number;
  card_redraws: number;
  passive_bonuses?: Record<string, number>;
  is_locked_for_user?: boolean;
}

interface GameInvite {
  id: number;
  sender?: { name?: string };
}

interface FriendEntry {
  id: number;
  user: { id: number; name?: string };
}

interface HouseRules {
  no_negative_effects: boolean;
  double_positive_effects: boolean;
  random_starting_stats: boolean;
  hardcore_mode: boolean;
}

interface RotatingEventData {
  game_type?: string;
  game_mode?: string;
  total_rounds?: number;
  character_pool?: number[];
}

interface GamePayload {
  game_mode: string;
  game_type: string;
  num_players: number;
  total_rounds: number | undefined;
  rotating_event_id?: number;
  bot_difficulty?: string;
  is_custom?: boolean;
  starting_stats?: number;
  house_rules?: HouseRules;
  is_private?: boolean;
  lobby_password?: string;
}

interface StartPayload {
  characters: (number | undefined)[];
  bot_difficulty?: string;
}

const auth = useAuth();
const toast = useToast();
const router = useRouter();
const route = useRoute();

function noop(): void {
  // Fallback when the parent does not provide the handler.
}

const openNotifications = inject<() => void>('openNotifications', noop);
const openRules = inject<() => void>('openRules', noop);
const openTutorial = inject<() => void>('openTutorial', noop);

const step = ref('mode');
const gameMode = ref('single');
const gameType = ref('cooperative');
const numberPlayers = ref(2);
const totalRounds = ref<number | undefined>(undefined);
const gameId = ref<number | undefined>(undefined);
const characters = ref<Character[]>([]);
const loading = ref(false);
const starting = ref(false);
// Carousel state
const currentPickingPlayer = ref(1);
const playerSelections = ref<Record<number, number>>({});
const swiperInstance = ref<SwiperInstance | undefined>(undefined);
const activeSlideIndex = ref(0);
const pendingInvites = ref<GameInvite[]>([]);
// Online friends picker
const availableFriends = ref<FriendEntry[]>([]);
const pendingReceivedFriends = ref<FriendEntry[]>([]);
const selectedFriendIds = ref<number[]>([]);
const friendsLoading = ref(false);
const addFriendUsername = ref('');
const addFriendError = ref('');
const addFriendSuccess = ref('');
const botDifficulty = ref('medium');
// Home screen stats
const homeStats = reactive({ level: 1, elo: 1000, unclaimed: 0, activeGames: 0 });
const notifCount = ref(0);
const showMobileMenu = ref(false);
const speedMode = ref('speed');
// Custom game
const isCustomGame = ref(false);
const customStartingStats = ref(8);
const houseRules = reactive<HouseRules>({
  no_negative_effects: false,
  double_positive_effects: false,
  random_starting_stats: false,
  hardcore_mode: false,
});
// Rotating event
const rotatingEventId = ref<number | undefined>(undefined);
const rotatingEventData = ref<RotatingEventData | undefined>(undefined);
// Private lobby
const isPrivateGame = ref(false);
const lobbyPassword = ref('');
// Lobby browser
// (game length is now controlled by GameRule on the backend)

const swiperModules = [EffectCards];

const xpProgress = computed(() => {
  const user = auth.state.user;
  if (!user) return 0;
  const level = user.level ?? 1;
  const xp = user.xp ?? 0;
  const currentLevelXp = 100 * (level - 1) * level / 2;
  const nextLevelXp = 100 * level * (level + 1) / 2;
  const range = nextLevelXp - currentLevelXp;
  if (range <= 0) return 1;
  return Math.min(Math.max((xp - currentLevelXp) / range, 0), 1);
});

const xpRingCircumference = computed(() => 2 * Math.PI * 20);

const xpRingOffset = computed(() => xpRingCircumference.value * (1 - xpProgress.value));

const availableCharacters = computed(() => {
  if (gameType.value === 'duel') {
    // Duel: both players can pick the same character
    return characters.value.filter(c => !c.is_locked_for_user);
  }
  const selectedIds = Object.values(playerSelections.value);
  return characters.value.filter(c => !selectedIds.includes(c.id) && !c.is_locked_for_user);
});

const allPlayersPicked = computed(() => {
  // Single-player duel: only 1 character needed (bot gets assigned automatically)
  const needed = (gameMode.value === 'single' && gameType.value === 'duel') ? 1 : numberPlayers.value;
  return Object.keys(playerSelections.value).length >= needed;
});

watch(
  () => route.fullPath,
  () => {
    if (route.path !== '/') {
    	return;
    }

    const eventId = firstQueryValue(route.query.event_id);
    if (eventId === undefined) {
      resetToHome();
    } else {
      handleEventParameter(eventId);
    }
  },
);

onMounted(async () => {
  if (!auth.state.user) {
  	return;
  }

  await fetchPendingInvites();
  subscribeToInvites();
  void fetchHomeStats();
  // Check for rotating event query param
  const eventId = firstQueryValue(route.query.event_id);
  if (eventId !== undefined) {
    handleEventParameter(eventId);
  }
  // Check for resume query param (game stuck in setup)
  const resume = firstQueryValue(route.query.resume);
  if (resume !== undefined) {
    void resumeSetup(resume);
  }
});

onBeforeUnmount(() => {
  if (auth.state.user) {
    getEcho()?.leave(`user.${auth.state.user.id}`);
  }
});

function firstQueryValue(value: LocationQueryValue | LocationQueryValue[] | undefined): string | undefined {
  const single = Array.isArray(value) ? value[0] : value;
  return single ?? undefined;
}

interface EchoChannel {
  listen: (event: string, callback: (data: { game_id: number }) => void) => EchoChannel;
}

interface EchoInstance {
  private: (channel: string) => EchoChannel;
  leave: (channel: string) => void;
}

function getEcho(): EchoInstance | undefined {
  return (window as unknown as { Echo?: EchoInstance }).Echo;
}

function handleEventParameter(eventId: string): void {
  // Reset state but preserve event context
  step.value = 'mode';
  gameId.value = undefined;
  characters.value = [];
  currentPickingPlayer.value = 1;
  playerSelections.value = {};
  activeSlideIndex.value = 0;
  // Set event and fetch details
  rotatingEventId.value = Number.parseInt(eventId);
  rotatingEventData.value = undefined;
  void fetchRotatingEvent(rotatingEventId.value);
}

async function fetchRotatingEvent(eventId: number): Promise<void> {
  try {
    const response = await axios.get<{ event: RotatingEventData }>(`/api/rotating-events/${eventId}`);
    rotatingEventData.value = response.data.event;
    // Auto-set game type and mode from event
    if (rotatingEventData.value.game_type) gameType.value = rotatingEventData.value.game_type;
    if (rotatingEventData.value.game_mode) gameMode.value = rotatingEventData.value.game_mode;
    // Override total rounds if event specifies it
    if (rotatingEventData.value.total_rounds) {
      totalRounds.value = rotatingEventData.value.total_rounds;
    }
    // Auto-advance to settings step
    step.value = 'settings';
  } catch {
    // ignore fetch errors
  }
}

async function fetchHomeStats(): Promise<void> {
  try {
    const [statsResponse, achResponse, historyResponse, unreadResponse] = await Promise.allSettled([
      axios.get<{ level?: number; elo_rating?: number }>('/api/auth/stats'),
      axios.get<{ earned?: boolean; claimed?: boolean }[]>('/api/achievements'),
      axios.get<{ active_games?: unknown[] }>('/api/games/history'),
      axios.get<{ count?: number }>('/api/notifications/unread-count'),
    ]);

    if (statsResponse.status === 'fulfilled') {
      const s = statsResponse.value.data;
      homeStats.level = s.level || 1;
      homeStats.elo = s.elo_rating || 1000;
    }

    if (achResponse.status === 'fulfilled') {
      homeStats.unclaimed = achResponse.value.data.filter(a => a.earned && !a.claimed).length;
    }

    if (historyResponse.status === 'fulfilled') {
      homeStats.activeGames = (historyResponse.value.data.active_games || []).length;
    }

    // Notification badge: pending invites + unread DB notifications
    const databaseUnread = unreadResponse.status === 'fulfilled' ? (unreadResponse.value.data?.count || 0) : 0;
    notifCount.value = pendingInvites.value.length + databaseUnread;
  } catch {
    // ignore stats errors
  }
}

async function fetchPendingInvites(): Promise<void> {
  try {
    const response = await axios.get<GameInvite[]>('/api/game-invites/pending');
    pendingInvites.value = response.data;
  } catch {
    // silently fail
  }
}

function subscribeToInvites(): void {
  const echo = getEcho();
  if (!echo || !auth.state.user) return;
  echo.private(`user.${auth.state.user.id}`)
    .listen('GameInviteReceived', () => {
      void fetchPendingInvites();
      notifCount.value++;
    })
    .listen('FriendRequestReceived', () => {
      notifCount.value++;
    })
    .listen('UserNotificationReceived', () => {
      notifCount.value++;
    })
    .listen('MatchFound', (data) => {
      if (step.value !== 'matchmaking') {
        router.push(`/game/${data.game_id}`);
      }
    });
}

async function acceptInvite(invite: GameInvite): Promise<void> {
  try {
    const response = await axios.post<{ game_id: number }>(`/api/game-invites/${invite.id}/accept`);
    router.push(`/game/${response.data.game_id}`);
  } catch (error) {
    toast.error(inviteErrorMessage(error) || 'Failed to accept invite');
  }
}

async function declineInvite(invite: GameInvite): Promise<void> {
  try {
    await axios.post(`/api/game-invites/${invite.id}/decline`);
    pendingInvites.value = pendingInvites.value.filter(index => index.id !== invite.id);
  } catch (error) {
    toast.error(inviteErrorMessage(error) || 'Failed to decline invite');
  }
}

function inviteErrorMessage(error: unknown): string | undefined {
  if (isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error;
  }
  return undefined;
}

function selectMode(): void {
  if (gameMode.value === 'online') {
    // Online goes straight to duel
    numberPlayers.value = 2;
    gameType.value = 'duel';
    totalRounds.value = 24;
    step.value = 'settings';
    return;
  }
  numberPlayers.value = gameMode.value === 'single' ? 1 : 2;
  gameType.value = 'cooperative';
  step.value = 'gameType';
}

async function fetchFriendsForPicker(): Promise<void> {
  friendsLoading.value = true;
  try {
    const response = await axios.get<{ friends: FriendEntry[]; pending_received?: FriendEntry[] }>('/api/friends');
    availableFriends.value = response.data.friends;
    pendingReceivedFriends.value = response.data.pending_received || [];
  } catch {
    availableFriends.value = [];
    pendingReceivedFriends.value = [];
  }
  friendsLoading.value = false;
}

async function acceptFriendInline(friendshipId: number): Promise<void> {
  try {
    await axios.post(`/api/friends/${friendshipId}/accept`);
    await fetchFriendsForPicker();
  } catch (error) {
    addFriendError.value = friendErrorMessage(error) || 'Failed to accept';
  }
}

function toggleFriend(userId: number): void {
  const index = selectedFriendIds.value.indexOf(userId);
  if (index === -1) {
    if (selectedFriendIds.value.length < 5) {
      selectedFriendIds.value.push(userId);
    }
  } else {
    selectedFriendIds.value.splice(index, 1);
  }
}

async function addFriendInline(): Promise<void> {
  if (!addFriendUsername.value.trim()) return;
  addFriendError.value = '';
  addFriendSuccess.value = '';
  try {
    await axios.post('/api/friends', { username: addFriendUsername.value.trim() });
    addFriendSuccess.value = `Request sent to ${addFriendUsername.value}`;
    addFriendUsername.value = '';
    await fetchFriendsForPicker();
  } catch (error) {
    addFriendError.value = friendErrorMessage(error) || 'Failed to send request';
  }
}

function friendErrorMessage(error: unknown): string | undefined {
  if (isAxiosError<{ message?: string }>(error)) {
    return error.response?.data?.message;
  }
  return undefined;
}

function onMatchFound(matchedGameId: number): void {
  router.push(`/game/${matchedGameId}`);
}

function onCustomToggle(): void {
  // Reset when toggling off
  if (isCustomGame.value) {
  	return;
  }

  customStartingStats.value = 8;
  Object.assign(houseRules, { no_negative_effects: false, double_positive_effects: false, random_starting_stats: false, hardcore_mode: false });
}

async function gatherAdvisors(): Promise<void> {
  loading.value = true;
  try {
    if (gameMode.value === 'online' && gameType.value === 'duel') {
      // Online duel: use matchmaking
      loading.value = false;
      step.value = 'matchmaking';
      return;
    }
    if (gameMode.value === 'online') {
      // Online cooperative: numberPlayers = selected friends + yourself
      numberPlayers.value = selectedFriendIds.value.length + 1;
      const onlinePayload: GamePayload = {
        game_mode: gameMode.value,
        game_type: gameType.value,
        num_players: numberPlayers.value,
        total_rounds: totalRounds.value,
      };
      if (rotatingEventId.value) {
        onlinePayload.rotating_event_id = rotatingEventId.value;
      }
      if (isCustomGame.value) {
        onlinePayload.is_custom = true;
        onlinePayload.starting_stats = customStartingStats.value;
        onlinePayload.house_rules = { ...houseRules };
      }
      if (isPrivateGame.value) {
        onlinePayload.is_private = true;
        onlinePayload.lobby_password = lobbyPassword.value;
      }
      const gameResponse = await axios.post<{ id: number }>('/api/games', onlinePayload);
      gameId.value = gameResponse.data.id;
      // Auto-invite selected friends
      for (const friendUserId of selectedFriendIds.value) {
        try {
          await axios.post(`/api/games/${gameId.value}/invite`, { user_id: friendUserId });
        } catch {
          // silently skip if invite fails
        }
      }
      router.push(`/game/${gameId.value}`);
      return;
    }
    const gamePayload: GamePayload = {
      game_mode: gameMode.value,
      game_type: gameType.value,
      num_players: numberPlayers.value,
      total_rounds: totalRounds.value,
    };
    if (rotatingEventId.value) {
      gamePayload.rotating_event_id = rotatingEventId.value;
    }
    if (gameMode.value === 'single' && gameType.value === 'duel') {
      gamePayload.bot_difficulty = botDifficulty.value;
    }
    if (isCustomGame.value) {
      gamePayload.is_custom = true;
      gamePayload.starting_stats = customStartingStats.value;
      gamePayload.house_rules = { ...houseRules };
    }
    const [gameResponse, charsResponse] = await Promise.all([
      axios.post<{ id: number }>('/api/games', gamePayload),
      axios.get<Character[]>('/api/characters', { params: { game_type: gameType.value } }),
    ]);
    gameId.value = gameResponse.data.id;
    let allChars = charsResponse.data;
    // Filter characters if rotating event has character_pool
    if (rotatingEventData.value?.character_pool) {
      const allowedIds = rotatingEventData.value.character_pool;
      allChars = allChars.filter(c => allowedIds.includes(c.id));
    }
    characters.value = allChars;
    step.value = gameMode.value === 'single' ? 'characters' : 'story';
  } catch (error) {
    toast.error('Failed to create game: ' + gameErrorMessage(error, 'message'));
  }
  loading.value = false;
}

function gameErrorMessage(error: unknown, key: 'message' | 'error'): string {
  if (isAxiosError<{ message?: string; error?: string }>(error)) {
    return error.response?.data?.[key] ?? error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'Something went wrong';
}

function onSwiper(swiper: SwiperInstance): void {
  swiperInstance.value = swiper;
}

function onSlideChange(swiper: SwiperInstance): void {
  activeSlideIndex.value = swiper.activeIndex;
}

function selectCharacter(charId: number): void {
  playSound('clickCard');
  playerSelections.value[currentPickingPlayer.value] = charId;
  const pickCount = (gameMode.value === 'single' && gameType.value === 'duel') ? 1 : numberPlayers.value;
  if (currentPickingPlayer.value < pickCount) {
    currentPickingPlayer.value++;
    void nextTick(() => {
      activeSlideIndex.value = 0;
      if (swiperInstance.value) {
        swiperInstance.value.slideTo(0, 0);
      }
    });
  }
}

function removePlayerSelection(playerNumber: number): void {
  playerSelections.value = Object.fromEntries(
    Object.entries(playerSelections.value).filter(([key]) => Number(key) !== playerNumber),
  );
}

function undoLastPick(): void {
  const pickCount = (gameMode.value === 'single' && gameType.value === 'duel') ? 1 : numberPlayers.value;
  // Remove the last pick and go back to picking
  currentPickingPlayer.value = pickCount;
  removePlayerSelection(currentPickingPlayer.value);
  void nextTick(() => {
    activeSlideIndex.value = 0;
    if (swiperInstance.value) {
      swiperInstance.value.slideTo(0, 0);
    }
  });
}

function hasUpgradeBonuses(char: Character): boolean {
  return (char.extra_item_slots > 0)
    || (char.card_redraws > 0)
    || Object.keys(char.passive_bonuses || {}).length > 0;
}

function isDiceUpgraded(char: Character, dieIndex: number, faceIndex: number): boolean {
  if (!char.base_dice) return false;
  const baseValue = char.base_dice[dieIndex]?.[faceIndex];
  const moduleValue = char.dice[dieIndex]?.[faceIndex];
  return baseValue !== moduleValue;
}

function getCharacterBonusLabel(charId: number): string {
  const char = characters.value.find(c => c.id === charId);
  if (!char?.starting_bonus) return '';
  const parts: string[] = [];
  const b = char.starting_bonus;
  if (b.extra_dice) parts.push(`+${b.extra_dice} Extra ${b.extra_dice === 1 ? 'Die' : 'Dice'}`);
  if (b.random_item) parts.push('Random Item');
  if (b.stat_boosts) {
    for (const [stat, value] of Object.entries(b.stat_boosts)) {
      const label = stat.charAt(0).toUpperCase() + stat.slice(1);
      parts.push(`${value > 0 ? '+' : ''}${value} ${label}`);
    }
  }
  return parts.join(', ');
}

function getCharacterName(charId: number): string {
  const char = characters.value.find(c => c.id === charId);
  return char ? char.name : 'Unknown';
}

function getCharacterImage(charId: number): string {
  const char = characters.value.find(c => c.id === charId);
  return char?.image_url || '/images/character.png';
}

function goBack(): void {
  if (step.value === 'gameType') {
    step.value = 'mode';
    return;
  }
  if (step.value === 'settings') {
    goBackFromSettings();
    return;
  }
  if (step.value === 'matchmaking' || step.value === 'story') {
    step.value = 'settings';
    return;
  }
  if (step.value === 'characters') {
    goBackFromCharacters();
  }
}

function goBackFromSettings(): void {
  if (rotatingEventId.value) {
    // Event game: go back to home and clear event
    rotatingEventId.value = undefined;
    rotatingEventData.value = undefined;
    router.replace('/');
    step.value = 'mode';
    return;
  }
  if (gameMode.value === 'online' || gameMode.value === 'single') {
    // Online skips gameType (goes straight to duel), single also goes to mode
    step.value = 'mode';
    return;
  }
  step.value = 'gameType';
}

function goBackFromCharacters(): void {
  if (currentPickingPlayer.value > 1) {
    // Go back one player pick
    currentPickingPlayer.value--;
    removePlayerSelection(currentPickingPlayer.value);
    void nextTick(() => {
      activeSlideIndex.value = 0;
      if (swiperInstance.value) {
        swiperInstance.value.slideTo(0, 0);
      }
    });
    return;
  }
  playerSelections.value = {};
  step.value = gameMode.value === 'single' ? 'settings' : 'story';
}

function resetToHome(): void {
  step.value = 'mode';
  gameId.value = undefined;
  characters.value = [];
  currentPickingPlayer.value = 1;
  playerSelections.value = {};
  rotatingEventId.value = undefined;
  rotatingEventData.value = undefined;
  activeSlideIndex.value = 0;
  void fetchHomeStats();
}

async function resumeSetup(gameIdToResume: string): Promise<void> {
  try {
    const [gameResponse, charsResponse] = await Promise.all([
      axios.get<{ game: { id: number; status: string; game_mode: string; game_type?: string; num_players: number; total_rounds?: number } }>(`/api/games/${gameIdToResume}`),
      axios.get<Character[]>('/api/characters'),
    ]);
    const game = gameResponse.data.game;
    if (game.status !== 'setup') return; // game already started
    gameId.value = game.id;
    gameMode.value = game.game_mode;
    gameType.value = game.game_type || 'cooperative';
    numberPlayers.value = game.num_players;
    totalRounds.value = game.total_rounds;
    characters.value = charsResponse.data;
    currentPickingPlayer.value = 1;
    playerSelections.value = {};
    step.value = 'characters';
  } catch {
    // If resume fails, just stay on home
  }
}

async function startGame(): Promise<void> {
  starting.value = true;
  try {
    const selectedIds: (number | undefined)[] = [];
    const pickCount = (gameMode.value === 'single' && gameType.value === 'duel') ? 1 : numberPlayers.value;
    for (let index = 1; index <= pickCount; index++) {
      selectedIds.push(playerSelections.value[index]);
    }
    const startPayload: StartPayload = { characters: selectedIds };
    if (gameMode.value === 'single' && gameType.value === 'duel') {
      startPayload.bot_difficulty = botDifficulty.value;
    }
    await axios.post(`/api/games/${gameId.value}/start`, startPayload);
    router.push(`/game/${gameId.value}`);
  } catch (error) {
    toast.error('Failed to start: ' + gameErrorMessage(error, 'error'));
  }
  starting.value = false;
}
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
    justify-content: center;
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
  font-weight: 700;
  margin-bottom: 15px;
  text-align: center;
  text-shadow: 0 2px 4px rgba(0,0,0,0.6), 0 0 20px rgba(240,192,80,0.1);
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
  background: linear-gradient(180deg, var(--wood-light), var(--wood-medium), var(--wood-dark));
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 16px 14px;
  cursor: pointer;
  transition: all 0.15s;
  box-sizing: border-box;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 0 rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,220,140,0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.mode-card-half {
  width: 48%;
}

.mode-card:hover {
  border-color: var(--accent-gold-bright);
  box-shadow: 0 6px 0 rgba(0,0,0,0.3), 0 0 16px rgba(240,192,80,0.2), inset 0 1px 0 rgba(255,220,140,0.15);
  transform: translateY(-2px);
}

.mode-card:active {
  transform: translateY(3px);
  box-shadow: 0 1px 0 rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,220,140,0.1);
}

.mode-icon {
  display: block;
  font-size: 1.8rem;
  margin-bottom: 4px;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
}

.mode-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  font-weight: 700;
  margin-bottom: 2px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.6);
}

.mode-subtitle {
  display: block;
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-style: italic;
}

/* Pending invites */
.invites-panel {
  margin-top: 20px;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  border: 2px solid var(--border-gold);
  border-radius: 14px;
  box-shadow: 0 4px 0 rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,220,140,0.08);
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
  border: 2px solid var(--border-gold);
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
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  border: 2px solid rgba(138, 106, 46, 0.5);
  border-radius: 10px;
  color: var(--text-secondary);
  font-size: 1rem;
  padding: 12px 20px;
  cursor: pointer;
  letter-spacing: 0;
  box-shadow: 0 3px 0 rgba(0,0,0,0.25);
}

.back-btn:hover {
  color: var(--text-bright);
  border-color: var(--border-gold);
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  box-shadow: 0 3px 0 rgba(0,0,0,0.25), 0 0 8px rgba(240,192,80,0.1);
  transform: translateY(-1px);
}

.back-btn:active {
  transform: translateY(2px);
  box-shadow: 0 1px 0 rgba(0,0,0,0.25);
}

.back-btn-centered {
  display: block;
  margin: 12px auto 0;
}

.start-btn {
  font-size: 1.25rem;
  padding: 14px 24px;
  text-transform: uppercase;
  letter-spacing: 2px;
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
  padding: 16px;
  width: 320px;
  min-height: 480px;
  box-shadow:
    0 8px 32px rgba(0, 0, 0, 0.6),
    inset 0 1px 0 rgba(212, 168, 67, 0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  transition: border-color 0.2s;
}

.advisor-card:hover {
  border-color: var(--accent-gold-bright);
}

.advisor-portrait-wrap {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  overflow: visible;
  border: 3px solid var(--accent-gold);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
  margin-bottom: 15px;
  position: relative;
}

.advisor-level-pip {
  position: absolute;
  bottom: -4px;
  right: -4px;
  background: linear-gradient(135deg, var(--accent-gold), #b08830);
  color: #1a0f05;
  font-size: 0.7rem;
  font-weight: 800;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #1a1209;
  font-family: 'Cinzel', serif;
  z-index: 1;
}

.advisor-portrait {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
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


/* Picking screen — no panel wrapper, content centered */
.picking-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Inline stats on advisor card */
.advisor-stats {
  width: 100%;
  margin-top: auto;
  border-top: 1px solid rgba(212, 168, 67, 0.15);
  padding-top: 10px;
}

.advisor-dice-rows {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 8px;
}

.advisor-die-row {
  display: flex;
  align-items: center;
  gap: 6px;
  justify-content: center;
}

.advisor-die-label {
  color: var(--text-secondary);
  font-size: 0.7rem;
  min-width: 34px;
  text-align: right;
  font-family: 'Cinzel', serif;
}

.advisor-die-faces {
  display: flex;
  gap: 3px;
}

.advisor-die-face {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 3px;
  font-family: 'Cinzel', serif;
  font-size: 0.72rem;
  color: var(--text-bright);
  font-weight: 700;
}

.advisor-die-face.face-wild {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  font-size: 0.55rem;
}

.advisor-die-face.face-upgraded {
  color: #5ab87a;
  border-color: rgba(90, 184, 122, 0.5);
  background: rgba(90, 184, 122, 0.15);
}

.advisor-upgrades {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: center;
  margin-bottom: 8px;
}

.advisor-upgrade-tag {
  font-size: 0.62rem;
  padding: 2px 6px;
  background: rgba(90, 184, 122, 0.1);
  border: 1px solid rgba(90, 184, 122, 0.25);
  border-radius: 4px;
  color: #5ab87a;
}

.advisor-ability {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}

.advisor-wild-tag {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  font-family: 'Cinzel', serif;
}

.advisor-ability-name {
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-style: italic;
  text-transform: capitalize;
}

.advisor-ability-desc {
  color: var(--text-secondary);
  font-size: 0.7rem;
  opacity: 0.8;
  width: 100%;
  text-align: center;
}

/* Character bonus badge on advisor card */
.advisor-bonus-badge {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.4);
  border-radius: 6px;
  padding: 4px 10px;
  color: var(--accent-gold);
  font-size: 0.78rem;
  font-weight: 600;
  text-align: center;
  margin-bottom: 4px;
}

.summary-bonus {
  font-size: 0.72rem;
  color: var(--accent-gold);
  font-style: italic;
  opacity: 0.9;
}

/* Summary panel */
.summary-panel {
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
}

.summary-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    width: 100%;
    max-width: 360px;
}

.summary-actions > button {
    flex: 1;
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
  padding: 0;
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
  background: linear-gradient(135deg, var(--accent-gold-bright), var(--accent-gold), #b8842a);
  color: var(--wood-dark);
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--border-gold);
  box-shadow: 0 2px 8px rgba(240,192,80,0.35), inset 0 -2px 4px rgba(0,0,0,0.2);
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
  text-shadow: 0 1px 3px rgba(0,0,0,0.5);
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
  padding: 5px 12px;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-medium));
  border: 2px solid var(--border-gold);
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 2px 0 rgba(0,0,0,0.3);
}

.home-elo:hover {
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  border-color: var(--accent-gold-bright);
  box-shadow: 0 2px 0 rgba(0,0,0,0.3), 0 0 8px rgba(240,192,80,0.2);
}

.elo-trophy {
  font-size: 0.9rem;
}

.elo-value {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 0.85rem;
  text-shadow: 0 1px 2px rgba(0,0,0,0.4);
}

.home-coins {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 12px;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-medium));
  border: 2px solid var(--border-gold);
  border-radius: 16px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  color: var(--accent-gold);
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 2px 0 rgba(0,0,0,0.3);
  text-shadow: 0 1px 2px rgba(0,0,0,0.4);
}

.home-coins:hover {
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  border-color: var(--accent-gold-bright);
  box-shadow: 0 2px 0 rgba(0,0,0,0.3), 0 0 8px rgba(240,192,80,0.2);
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
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-medium));
  border: 2px solid var(--border-gold);
  color: var(--text-secondary);
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
  padding: 0;
  letter-spacing: 0;
  box-shadow: 0 3px 0 rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,220,140,0.1);
  flex-shrink: 0;
  overflow: visible;
}

.side-icon-btn:hover {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  transform: translateY(-1px);
  box-shadow: 0 4px 0 rgba(0,0,0,0.3), 0 0 10px rgba(240,192,80,0.2), inset 0 1px 0 rgba(255,220,140,0.15);
}

.side-icon-btn:active {
  transform: translateY(2px);
  box-shadow: 0 1px 0 rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,220,140,0.1);
}

.side-premium-btn {
  background: linear-gradient(180deg, #4a3a1e, var(--wood-light));
  border-color: var(--accent-gold);
  color: var(--accent-gold);
  box-shadow: 0 3px 0 rgba(0,0,0,0.3), 0 0 10px rgba(240,192,80,0.25);
}

.side-premium-btn:hover {
  background: linear-gradient(180deg, #5a4a28, #4a3a1e);
  box-shadow: 0 4px 0 rgba(0,0,0,0.3), 0 0 16px rgba(240,192,80,0.4);
}

.side-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: linear-gradient(180deg, #e74c3c, #c0392b);
  color: #fff;
  font-size: 0.55rem;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  border-radius: 9px;
  padding: 0 3px;
  border: 2px solid var(--wood-dark);
  box-shadow: 0 2px 4px rgba(0,0,0,0.4);
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
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  border: 2px solid var(--border-gold);
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
  color: var(--text-primary);
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
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

/* Enhanced Daily Challenge */
.daily-enhanced {
  margin-bottom: 10px;
}

/* In-Progress Games in grid */
.home-grid-main .in-progress-card {
  margin-bottom: 0;
}

/* In-Progress Games Card */
.in-progress-card {
  cursor: pointer;
  transition: all 0.15s;
  padding: 12px 16px;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-medium));
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  box-shadow: 0 3px 0 rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,220,140,0.08);
}

.in-progress-card:hover {
  border-color: var(--accent-gold);
  box-shadow: 0 4px 0 rgba(0,0,0,0.3), 0 0 12px rgba(240,192,80,0.2);
  transform: translateY(-1px);
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
    padding: 10px 10px;
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
    min-height: 350px;
    padding: 14px;
  }

  .advisor-portrait-wrap {
    width: 80px;
    height: 80px;
    margin-bottom: 8px;
  }

  .advisor-name {
    font-size: 1.05rem;
  }

  .advisor-desc {
    font-size: 0.78rem;
    margin-bottom: 6px;
  }

  .advisor-die-face {
    width: 22px;
    height: 22px;
    font-size: 0.65rem;
  }

  .advisor-die-face.face-wild {
    font-size: 0.5rem;
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
  border: 2px solid var(--border-gold);
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
</style>
