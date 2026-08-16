<template>
  <div class="lobby">
    <div class="lobby-header">
      <button class="ta-back" aria-label="Leave lobby" @click="leaveLobby">&lsaquo;</button>
    </div>

    <!-- PHASE 1: Gathering Players -->
    <Transition name="fade" mode="out-in">
    <div v-if="!allJoined" key="gathering" class="card-panel lobby-panel">
      <h2 class="section-title">Gathering Advisors</h2>
      <p class="lobby-subtitle">Waiting for players to join ({{ filledSlots }}/{{ numberPlayers }})</p>

      <div class="player-slots">
        <div
          v-for="slot in numberPlayers"
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
      <div v-if="isHost && availableFriends.length > 0 && filledSlots < numberPlayers" class="invite-section">
        <h3 class="invite-title">Invite Friends</h3>
        <div class="friend-invite-list">
          <div
            v-for="friend in availableFriends"
            :key="friend.user.id"
            class="friend-invite-row"
          >
            <span class="friend-name">{{ friend.user.name }}</span>
            <button class="btn-small" :disabled="inviting" @click="inviteFriend(friend.user.id)">
              Invite
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- PHASE 2: Character Selection (simultaneous) -->
    <div v-else-if="!allSelected" key="picking" class="card-panel carousel-panel">
      <!-- Turn timer -->
      <div v-if="turnTimeLimit && timeRemaining > 0" :class="['lobby-timer', { urgent: timeRemaining <= 10 }]">
        {{ formattedTime }}
      </div>

      <!-- I still need to pick -->
      <template v-if="myPlayerNeedsPick">
        <h2 class="section-title picking-header">
          Choose your advisor
        </h2>
        <p class="picking-subtitle">Swipe through and tap to select</p>

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
                  <div v-for="(die, di) in char.dice" :key="di" class="dice-row">
                    <span class="dice-label">Die {{ di + 1 }}:</span>
                    <span v-for="(face, fi) in die" :key="fi" class="dice-face">{{ face }}</span>
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

        <!-- Show who has already picked -->
        <div v-if="pickedPlayers.length > 0" class="already-picked">
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
          <div v-for="slot in numberPlayers" :key="slot" class="pick-slot">
            <span class="pick-number">Player {{ slot }}</span>
            <span v-if="getPlayerForSlot(slot)?.character" class="pick-char">
              {{ getPlayerForSlot(slot).character.name }}
            </span>
            <span v-else class="pick-active">Choosing...</span>
          </div>
        </div>
      </template>
    </div>

    <!-- PHASE 3: The Council — both seats filled, ready to begin -->
    <div v-else key="council" class="card-panel lobby-panel council">
      <h2 class="section-title">The Council</h2>
      <p class="lobby-subtitle">Both seats filled &mdash; the reign is about to begin</p>

      <div class="council-seats">
        <div
          v-for="seat in councilSeats"
          :key="seat.number"
          class="council-seat"
          :class="seat.isPlayerOne ? 'seat-p1' : 'seat-p2'"
        >
          <div class="seat-portrait" :style="{ backgroundImage: `url(${seat.image})` }"></div>
          <div class="seat-body">
            <div class="seat-role">Player {{ seat.number }}</div>
            <div class="seat-name">{{ seat.name }}</div>
            <div v-if="seat.wild" class="seat-meta">{{ seat.wild }}</div>
          </div>
          <span class="seat-ready">Ready</span>
        </div>
      </div>

      <div class="council-first-month">
        <div class="fm-head">
          <span class="fm-label">First Month</span>
          <span class="fm-line"></span>
          <span class="fm-speed">{{ speedLabel }}</span>
        </div>
        <div class="fm-dials">
          <div v-for="stat in firstMonthStats" :key="stat.label" class="fm-dial">
            <div class="fm-ring" :style="{ borderColor: stat.color }">
              <span class="fm-val" :style="{ color: stat.color }">{{ stat.value }}</span>
            </div>
            <span class="fm-dial-label">{{ stat.label }}</span>
          </div>
        </div>
        <p class="fm-note">Both realms open on the same figures. Every month after this one, they diverge.</p>
      </div>

      <div class="council-rules">
        <div v-for="rule in councilRules" :key="rule.text" class="rule-row">
          <span class="rule-glyph">{{ rule.glyph }}</span>
          <span class="rule-text">{{ rule.text }}</span>
        </div>
      </div>

      <div class="council-begin">
        <span class="begin-crown">&#9819;</span>
        <span>{{ beginCountdown > 0 ? `The reign begins in ${beginCountdown}…` : "Beginning…" }}</span>
      </div>
    </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import axios, { isAxiosError } from "axios";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import { EffectCards } from "swiper/modules";
import type { Swiper as SwiperInstance } from "swiper";
import "swiper/css";
import "swiper/css/effect-cards";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import { playSound } from "../sounds";

interface LobbyUser {
  id: number;
  name?: string;
}

interface LobbyCharacter {
  id: number;
  name: string;
  description?: string;
  image_url?: string;
  dice?: number[][];
  wild_value?: number;
  wild_ability?: string;
  wild_ability_description?: string;
  is_locked_for_user?: boolean;
}

interface LobbyPlayer {
  user_id: number;
  player_number: number;
  character_id?: number;
  user?: LobbyUser;
  character?: LobbyCharacter;
}

interface LobbyInvite {
  status: string;
  receiver_id: number;
  receiver?: LobbyUser;
}

interface LobbyFriend {
  user: LobbyUser;
}

interface LobbyResponse {
  players: LobbyPlayer[];
  invites: LobbyInvite[];
  characters: LobbyCharacter[];
  num_players: number;
  all_joined: boolean;
  all_selected: boolean;
}

interface FriendsResponse {
  friends?: LobbyFriend[];
}

interface SelectCharacterResponse {
  all_selected: boolean;
}

const { gameId, hostId, gameType = "cooperative", turnTimeLimit = 0 } = defineProps<{
  gameId: string | number;
  hostId: number;
  gameType?: string;
  turnTimeLimit?: number;
}>();

const emit = defineEmits<{ "startGame": []; "lobbyUpdated": []; "leave": [] }>();

function leaveLobby(): void {
  emit("leave");
}

const auth = useAuth();
const toast = useToast();

const players = ref<LobbyPlayer[]>([]);
const invites = ref<LobbyInvite[]>([]);
const characters = ref<LobbyCharacter[]>([]);
const friends = ref<LobbyFriend[]>([]);
const numberPlayers = ref(2);
const allJoined = ref(false);
const allSelected = ref(false);
const inviting = ref(false);
const selectingCharacter = ref(false);
const autoStarting = ref(false);
const activeSlideIndex = ref(0);
const timeRemaining = ref(0);
const timerInterval = ref<ReturnType<typeof setInterval> | undefined>(undefined);
const beginCountdown = ref(0);
const beginTimer = ref<ReturnType<typeof setInterval> | undefined>(undefined);

// The six shared opening kingdom figures (all realms start level at 8). Colours match
// the app's stat identities: Wealth/People/Military/Church/Food/Mood.
const firstMonthStats = [
  { label: "Wealth", value: 8, color: "#f0c050" },
  { label: "Church", value: 8, color: "#e8e0d0" },
  { label: "Military", value: 8, color: "#5a9bd4" },
  { label: "People", value: 8, color: "#a070d0" },
  { label: "Food", value: 8, color: "#5ea84a" },
  { label: "Mood", value: 8, color: "#c0392b" },
];

const councilRules = [
  { glyph: "✦", text: "Two cards are drawn each month. Keep one, pass the other to your rival." },
  { glyph: "⬡", text: "Three dice per turn. Your advisor's wild face counts as any value." },
  { glyph: "♛", text: "Highest realm score after twelve months wins the crown." },
];

const swiperModules = [EffectCards];

const isHost = computed(() => auth.state.user?.id === hostId);

const myPlayer = computed(() => players.value.find((p) => p.user_id === auth.state.user?.id));

const filledSlots = computed(() => players.value.length);

const pendingInvites = computed(() => invites.value.filter((index) => index.status === "pending"));

const availableFriends = computed(() => {
  const playerUserIds = new Set(players.value.map((p) => p.user_id));
  const invitedUserIds = new Set(invites.value.map((index) => index.receiver_id));
  return friends.value.filter(
    (f) => !playerUserIds.has(f.user.id) && !invitedUserIds.has(f.user.id),
  );
});

const takenCharacterIds = computed(() =>
  players.value.filter((p) => p.character_id).map((p) => p.character_id),
);

const availableCharacters = computed(() => {
  if (gameType === "duel") {
    // Duel: both players can pick the same character
    return characters.value.filter((c) => !c.is_locked_for_user);
  }
  return characters.value.filter(
    (c) => !takenCharacterIds.value.includes(c.id) && !c.is_locked_for_user,
  );
});

const myPlayerNeedsPick = computed(() => {
  if (!myPlayer.value) return false;
  return !myPlayer.value.character_id;
});

const pickedPlayers = computed(() => players.value.filter((p) => p.character_id));

const formattedTime = computed(() => {
  const mins = Math.floor(timeRemaining.value / 60);
  const secs = timeRemaining.value % 60;
  return `${mins}:${secs.toString().padStart(2, "0")}`;
});

const councilSeats = computed(() =>
  players.value
    .toSorted((a, b) => a.player_number - b.player_number)
    .map((p) => ({
      number: p.player_number,
      name: p.character?.name ?? p.user?.name ?? `Player ${p.player_number}`,
      wild:
        p.character && p.character.wild_value !== undefined
          ? `Wild ${p.character.wild_value} · ${p.character.wild_ability ?? ""}`.trim()
          : "",
      image: p.character?.image_url || "/images/character.png",
      isPlayerOne: p.player_number === 1,
    })),
);

const speedLabel = computed(() => {
  if (turnTimeLimit >= 3600) {
    return "Daily";
  }
  return turnTimeLimit > 0 ? `Speed · ${turnTimeLimit}s` : "Speed";
});

// Hold the Council on screen for a five-count before the reign begins.
function startBeginCountdown(): void {
  if (beginTimer.value) {
    return;
  }
  beginCountdown.value = 5;
  beginTimer.value = setInterval(() => {
    if (beginCountdown.value > 0) {
      beginCountdown.value--;
    } else {
      clearInterval(beginTimer.value);
      beginTimer.value = undefined;
    }
  }, 1000);
}

async function fetchLobby(): Promise<void> {
  try {
    const response = await axios.get<LobbyResponse>(`/api/games/${gameId}/lobby`);
    players.value = response.data.players;
    invites.value = response.data.invites;
    characters.value = response.data.characters;
    numberPlayers.value = response.data.num_players;
    allJoined.value = response.data.all_joined;
    allSelected.value = response.data.all_selected;
  } catch (error) {
    console.error("Failed to fetch lobby", error);
  }
}

async function fetchFriends(): Promise<void> {
  try {
    const response = await axios.get<FriendsResponse>("/api/friends");
    friends.value = response.data.friends || [];
  } catch (error) {
    console.error("Failed to fetch friends", error);
  }
}

async function inviteFriend(userId: number): Promise<void> {
  inviting.value = true;
  try {
    await axios.post(`/api/games/${gameId}/invite`, { user_id: userId });
    await fetchLobby();
  } catch (error) {
    const message = isAxiosError<{ error?: string }>(error)
      ? error.response?.data?.error ?? "Failed to invite"
      : "Failed to invite";
    toast.error(message);
  }
  inviting.value = false;
}

function onSwiper(swiper: SwiperInstance): void {
  activeSlideIndex.value = swiper.activeIndex;
}

function onSlideChange(swiper: SwiperInstance): void {
  activeSlideIndex.value = swiper.activeIndex;
}

async function selectCharacterByIndex(index: number): Promise<void> {
  if (selectingCharacter.value) return;
  if (index !== activeSlideIndex.value) return;

  const char = availableCharacters.value[index];
  if (!char) return;

  playSound("clickCard");
  selectingCharacter.value = true;
  try {
    const response = await axios.post<SelectCharacterResponse>(
      `/api/games/${gameId}/select-character`,
      { character_id: char.id },
    );
    await fetchLobby();

    // If I'm host and all selected, auto-start
    if (response.data.all_selected && isHost.value) {
      await autoStartGame();
    }
  } catch (error) {
    const message = isAxiosError<{ error?: string }>(error)
      ? error.response?.data?.error ?? "Failed to select character"
      : "Failed to select character";
    if (message.toLowerCase().includes("already taken") || message.toLowerCase().includes("already selected")) {
      await fetchLobby();
    } else {
      toast.error(message);
    }
  }
  selectingCharacter.value = false;
}

async function autoStartGame(): Promise<void> {
  if (autoStarting.value) return;
  if (!isHost.value) return;
  autoStarting.value = true;

  // Let the Council screen and its countdown play out before the reign begins.
  startBeginCountdown();
  await new Promise((resolve) => setTimeout(resolve, 5000));

  try {
    const lobbyResponse = await axios.get<LobbyResponse>(`/api/games/${gameId}/lobby`);
    const lobbyPlayers = lobbyResponse.data.players;
    const characterIds = lobbyPlayers
      .toSorted((a, b) => a.player_number - b.player_number)
      .map((p) => p.character_id);

    await axios.post(`/api/games/${gameId}/start`, { characters: characterIds });
    // GameStarted broadcast will trigger lobby-updated
  } catch (error) {
    console.error("Auto-start failed", error);
    // Fallback: let host click manually
    emit("startGame");
  }
  autoStarting.value = false;
}

function startTimer(): void {
  clearTimer();
  timeRemaining.value = turnTimeLimit;
  timerInterval.value = setInterval(() => {
    if (timeRemaining.value > 0) {
      timeRemaining.value--;
    } else {
      clearTimer();
    }
  }, 1000);
}

function clearTimer(): void {
  if (!timerInterval.value) {
  	return;
  }

  clearInterval(timerInterval.value);
  timerInterval.value = undefined;
}

function getPlayerForSlot(slot: number): LobbyPlayer | undefined {
  return players.value.find((p) => p.player_number === slot);
}

function getPendingInviteForSlot(slot: number): LobbyInvite | undefined {
  const filledPlayerSlots = players.value.map((p) => p.player_number);
  const emptySlotIndex = slot - filledPlayerSlots.filter((s) => s <= slot).length;
  const pending = pendingInvites.value;
  if (emptySlotIndex >= 0 && emptySlotIndex < pending.length) {
    return pending[emptySlotIndex];
  }
  return undefined;
}

function slotState(slot: number): string {
  if (getPlayerForSlot(slot)) return "slot-joined";
  if (getPendingInviteForSlot(slot)) return "slot-invited";
  return "slot-vacant";
}

watch(allJoined, (joined) => {
  if (joined && turnTimeLimit) {
    startTimer();
  }
});

// When both advisors are locked in, run the Council countdown (both players see it;
// only the host actually starts the game, inside autoStartGame).
watch(allSelected, (selected) => {
  if (selected) {
    startBeginCountdown();
  }
});

onMounted(async () => {
  await fetchLobby();
  await fetchFriends();
  if (turnTimeLimit && allJoined.value) {
    startTimer();
  }
  if (allSelected.value) {
    startBeginCountdown();
  }
});

onBeforeUnmount(() => {
  clearTimer();
  if (beginTimer.value) {
    clearInterval(beginTimer.value);
  }
});

// The parent (GameBoard) drives real-time refresh and start through these.
defineExpose({ fetchLobby, autoStartGame });
</script>

<style scoped>
.lobby { height: 100%; }

.lobby-header {
  display: flex;
  align-items: center;
  padding: 4px 4px 12px;
}

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

/* The single child fills the row and lays its label/name/badge out with spacing;
   without this the spans collapse together (e.g. "Player 1MIKE"). */
.slot-filled,
.slot-pending,
.slot-empty {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
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

/* Lobby timer */
.lobby-timer {
  text-align: center;
  font-family: 'Cinzel', serif;
  font-size: 1.6rem;
  color: var(--text-bright);
  background: rgba(26, 18, 9, 0.8);
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 6px 20px;
  width: fit-content;
  margin: 0 auto 12px;
}

.lobby-timer.urgent {
  color: var(--accent-red);
  border-color: var(--accent-red);
  animation: pulse 1s ease-in-out infinite;
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

/* The Council — both seats filled, ready to begin */
.council-seats {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.council-seat {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 16px;
  box-shadow: 0 4px 0 rgba(0, 0, 0, 0.45);
}

.council-seat.seat-p1 {
  background: linear-gradient(90deg, rgba(240, 192, 80, 0.14), rgba(18, 12, 7, 0.9));
  border: 1.5px solid rgba(240, 192, 80, 0.5);
}

.council-seat.seat-p2 {
  background: linear-gradient(90deg, rgba(154, 208, 255, 0.14), rgba(18, 12, 7, 0.9));
  border: 1.5px solid rgba(154, 208, 255, 0.45);
}

.seat-portrait {
  width: 54px;
  height: 54px;
  flex: none;
  border-radius: 50%;
  background-size: cover;
  background-position: center 18%;
}

.seat-p1 .seat-portrait { border: 2.5px solid #f0c050; }
.seat-p2 .seat-portrait { border: 2.5px solid #9ad0ff; }

.seat-body {
  flex: 1;
  min-width: 0;
}

.seat-role {
  font-family: 'Cinzel', serif;
  font-size: 0.56rem;
  font-weight: 700;
  letter-spacing: 1.6px;
  text-transform: uppercase;
  color: #8a7a5a;
}

.seat-name {
  margin-top: 2px;
  font-family: 'Cinzel', serif;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-bright, #f0e0c8);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.seat-meta {
  font-size: 0.72rem;
  color: #bcac8c;
}

.seat-ready {
  flex: none;
  font-family: 'Cinzel', serif;
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  padding: 6px 10px;
  border-radius: 9px;
  background: rgba(74, 138, 58, 0.22);
  color: #6fd06a;
  border: 1px solid rgba(74, 138, 58, 0.45);
}

.council-first-month {
  margin-top: 15px;
  padding: 13px;
  border-radius: 16px;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(240, 192, 80, 0.22);
}

.fm-head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.fm-label {
  font-family: 'Cinzel', serif;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 2.2px;
  text-transform: uppercase;
  color: var(--accent-gold);
}

.fm-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, rgba(240, 192, 80, 0.3), transparent);
}

.fm-speed {
  font-size: 0.7rem;
  color: #8a7a5a;
}

.fm-dials {
  margin-top: 11px;
  display: flex;
  justify-content: space-between;
  gap: 3px;
}

.fm-dial {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.fm-ring {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 2px solid;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #100b06;
}

.fm-val {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 800;
}

.fm-dial-label {
  font-family: 'Cinzel', serif;
  font-size: 0.48rem;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  color: #8a7a5a;
}

.fm-note {
  margin-top: 10px;
  margin-bottom: 0;
  font-size: 0.75rem;
  line-height: 1.4;
  color: #8a7a5a;
}

.council-rules {
  margin-top: 15px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rule-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.rule-glyph {
  width: 24px;
  height: 24px;
  flex: none;
  border-radius: 8px;
  background: rgba(240, 192, 80, 0.12);
  border: 1px solid rgba(240, 192, 80, 0.28);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  color: var(--accent-gold);
}

.rule-text {
  flex: 1;
  font-size: 0.78rem;
  line-height: 1.4;
  color: #9a8a68;
}

.council-begin {
  margin-top: 16px;
  text-align: center;
  padding: 16px;
  border-radius: 16px;
  background: linear-gradient(180deg, #ffe897, #f0c050 55%, #b8842a);
  border: 2px solid #fff0b0;
  box-shadow: 0 6px 0 #7a5410;
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: 1px;
  color: #241703;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.begin-crown {
  font-size: 1.1rem;
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
    min-height: 350px;
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
