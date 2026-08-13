<template>
  <div class="card-panel matchmaking-panel">
    <h2 class="section-title">Finding Opponent</h2>

    <div v-if="matched" class="match-found">
      <p class="match-text">Opponent found!</p>
      <p class="opponent-name">{{ opponentName }}</p>
      <p v-if="opponentElo" class="opponent-elo">ELO: {{ opponentElo }}</p>
    </div>

    <template v-else>
      <div class="search-animation">
        <div class="search-spinner"></div>
        <p class="search-text">Searching for a worthy opponent...</p>
      </div>

      <div class="search-info">
        <div class="elapsed-time">{{ formattedElapsed }}</div>
        <div class="elo-display">
          <span class="elo-label">Your ELO:</span>
          <span class="elo-value">{{ userElo }}</span>
        </div>
      </div>

      <button class="btn-cancel" @click="cancel">Cancel</button>
    </template>
  </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useAuth } from "../stores/auth";

interface MatchFoundEvent {
  game_id: number;
  opponent_name?: string;
  opponent_elo?: number;
}

interface EchoChannel {
  listen: (event: string, callback: (data: MatchFoundEvent) => void) => void;
  stopListening: (event: string) => void;
}

interface EchoInstance {
  private: (channel: string) => EchoChannel;
}

interface MatchmakingEntry {
  status?: string;
  matched_game_id?: number;
  opponent_name?: string;
  opponent_elo?: number;
}

const { totalRounds, speedMode = "speed" } = defineProps<{
  totalRounds: number;
  speedMode?: string;
}>();

const emit = defineEmits<{ matched: [gameId: number]; cancelled: [] }>();

const auth = useAuth();

const entry = ref<MatchmakingEntry | undefined>(undefined);
const matched = ref(false);
const opponentName = ref("");
const opponentElo = ref<number | undefined>(undefined);
const elapsed = ref(0);
const elapsedTimer = ref<ReturnType<typeof setInterval> | undefined>(undefined);
const pollTimer = ref<ReturnType<typeof setInterval> | undefined>(undefined);

const userElo = computed(() => auth.state.user?.elo_rating || 1200);

const formattedElapsed = computed(() => {
  const mins = Math.floor(elapsed.value / 60);
  const secs = elapsed.value % 60;
  return `${mins}:${secs.toString().padStart(2, "0")}`;
});

function getEcho(): EchoInstance | undefined {
  return (window as unknown as { Echo?: EchoInstance }).Echo;
}

async function pollStatus(): Promise<void> {
  try {
    const response = await axios.get<MatchmakingEntry>("/api/matchmaking/status");
    if (response.data.status === "matched") {
      onMatchFound(response.data.matched_game_id, response.data.opponent_name, response.data.opponent_elo);
    }
  } catch {
    // ignore poll errors
  }
}

async function leaveQueue(): Promise<void> {
  try {
    await axios.post("/api/matchmaking/leave");
  } catch {
    // ignore leave errors
  }
}

function subscribeEcho(): void {
  const echo = getEcho();
  if (!echo || !auth.state.user) {
    return;
  }
  echo.private(`user.${auth.state.user.id}`).listen("MatchFound", (data) => {
    onMatchFound(data.game_id, data.opponent_name, data.opponent_elo);
  });
}

function unsubscribeEcho(): void {
  // Don't fully leave the channel - just stop listening
  // (other components may use the same channel)
  const echo = getEcho();
  if (echo && auth.state.user) {
    const channel = echo.private(`user.${auth.state.user.id}`);
    channel.stopListening("MatchFound");
  }
}

function onMatchFound(gameId: number | undefined, name: string | undefined, elo: number | undefined): void {
  clearInterval(elapsedTimer.value);
  clearInterval(pollTimer.value);
  matched.value = true;
  opponentName.value = name || "Opponent";
  opponentElo.value = elo || undefined;

  setTimeout(() => {
    if (gameId !== undefined) {
      emit("matched", gameId);
    }
  }, 1500);
}

function cancel(): void {
  clearInterval(elapsedTimer.value);
  clearInterval(pollTimer.value);
  void leaveQueue();
  emit("cancelled");
}

onMounted(async () => {
  try {
    const response = await axios.post<MatchmakingEntry>("/api/matchmaking/join", {
      total_rounds: totalRounds,
      speed_mode: speedMode,
    });
    entry.value = response.data;

    if (entry.value.status === "matched") {
      onMatchFound(entry.value.matched_game_id, entry.value.opponent_name, entry.value.opponent_elo);
      return;
    }

    // Start elapsed timer
    elapsedTimer.value = setInterval(() => {
      elapsed.value++;
    }, 1000);

    // Poll for status
    pollTimer.value = setInterval(() => void pollStatus(), 3000);

    // Listen for broadcast
    subscribeEcho();
  } catch {
    emit("cancelled");
  }
});

onBeforeUnmount(() => {
  clearInterval(elapsedTimer.value);
  clearInterval(pollTimer.value);
  unsubscribeEcho();

  // Leave queue if still searching
  if (entry.value && !matched.value) {
    void leaveQueue();
  }
});
</script>

<style scoped>
.matchmaking-panel {
  text-align: center;
  padding: 40px 20px;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  margin-bottom: 30px;
}

.search-animation {
  margin-bottom: 30px;
}

.search-spinner {
  width: 60px;
  height: 60px;
  border: 3px solid rgba(138, 106, 46, 0.2);
  border-top: 3px solid var(--accent-gold);
  border-radius: 50%;
  margin: 0 auto 16px;
  animation: spin 1.2s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.search-text {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 1.1rem;
}

.search-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  margin-bottom: 30px;
}

.elapsed-time {
  font-family: 'Cinzel', serif;
  font-size: 2rem;
  color: var(--text-bright);
}

.elo-display {
  display: flex;
  gap: 6px;
  align-items: center;
}

.elo-label {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.elo-value {
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 1rem;
}

.btn-cancel {
  background: rgba(160, 48, 32, 0.2);
  color: #d05040;
  border: 1px solid rgba(160, 48, 32, 0.4);
  padding: 10px 30px;
  font-size: 1rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: rgba(160, 48, 32, 0.35);
  border-color: rgba(160, 48, 32, 0.6);
}

.match-found {
  animation: matchPop 0.5s ease;
}

.match-text {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
  margin-bottom: 8px;
}

.opponent-name {
  color: var(--text-bright);
  font-size: 1.2rem;
  font-weight: 600;
}

.opponent-elo {
  color: var(--accent-gold);
  font-size: 0.95rem;
  font-weight: 600;
  margin-top: 4px;
}

@keyframes matchPop {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
</style>
