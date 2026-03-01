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

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';

export default {
  name: 'MatchmakingQueue',
  props: {
    totalRounds: { type: Number, required: true },
  },
  emits: ['matched', 'cancelled'],
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      entry: null,
      matched: false,
      opponentName: '',
      opponentElo: null,
      elapsed: 0,
      elapsedTimer: null,
      pollTimer: null,
    };
  },
  computed: {
    userElo() {
      return this.auth.state.user?.elo_rating || 1200;
    },
    formattedElapsed() {
      const mins = Math.floor(this.elapsed / 60);
      const secs = this.elapsed % 60;
      return `${mins}:${secs.toString().padStart(2, '0')}`;
    },
  },
  async mounted() {
    try {
      const res = await axios.post('/api/matchmaking/join', {
        total_rounds: this.totalRounds,
      });
      this.entry = res.data;

      if (this.entry.status === 'matched') {
        this.onMatchFound(this.entry.matched_game_id, this.entry.opponent_name, this.entry.opponent_elo);
        return;
      }

      // Start elapsed timer
      this.elapsedTimer = setInterval(() => {
        this.elapsed++;
      }, 1000);

      // Poll for status
      this.pollTimer = setInterval(() => this.pollStatus(), 3000);

      // Listen for broadcast
      this.subscribeEcho();
    } catch (e) {
      this.$emit('cancelled');
    }
  },
  beforeUnmount() {
    clearInterval(this.elapsedTimer);
    clearInterval(this.pollTimer);
    this.unsubscribeEcho();

    // Leave queue if still searching
    if (this.entry && !this.matched) {
      axios.post('/api/matchmaking/leave').catch(() => {});
    }
  },
  methods: {
    async pollStatus() {
      try {
        const res = await axios.get('/api/matchmaking/status');
        if (res.data.status === 'matched') {
          this.onMatchFound(res.data.matched_game_id, res.data.opponent_name, res.data.opponent_elo);
        }
      } catch {
        // ignore poll errors
      }
    },
    subscribeEcho() {
      if (!window.Echo || !this.auth.state.user) return;
      window.Echo.private(`user.${this.auth.state.user.id}`)
        .listen('MatchFound', (data) => {
          this.onMatchFound(data.game_id, data.opponent_name, data.opponent_elo);
        });
    },
    unsubscribeEcho() {
      // Don't fully leave the channel - just stop listening
      // (other components may use the same channel)
      if (window.Echo && this.auth.state.user) {
        const channel = window.Echo.private(`user.${this.auth.state.user.id}`);
        channel.stopListening('MatchFound');
      }
    },
    onMatchFound(gameId, opponentName, opponentElo) {
      clearInterval(this.elapsedTimer);
      clearInterval(this.pollTimer);
      this.matched = true;
      this.opponentName = opponentName || 'Opponent';
      this.opponentElo = opponentElo || null;

      setTimeout(() => {
        this.$emit('matched', gameId);
      }, 1500);
    },
    cancel() {
      clearInterval(this.elapsedTimer);
      clearInterval(this.pollTimer);
      axios.post('/api/matchmaking/leave').catch(() => {});
      this.$emit('cancelled');
    },
  },
};
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
