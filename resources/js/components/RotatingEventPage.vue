<template>
  <div class="event-page">
    <div v-if="loading" class="loading">Loading event...</div>

    <template v-else-if="event">
      <div class="card-panel event-header">
        <h2 class="section-title">{{ event.name }}</h2>
        <p class="event-desc">{{ event.description }}</p>
        <div class="event-meta">
          <span class="meta-badge type-badge" :class="'type-' + event.game_type">{{ event.game_type === 'duel' ? 'Duel' : 'Classic' }}</span>
          <span class="meta-badge mode-badge" :class="'mode-' + event.game_mode">{{ modeLabel(event.game_mode) }}</span>
          <span v-if="event.reward_coins" class="meta-badge reward-badge">&#129689; {{ event.reward_coins }} coins</span>
          <span class="meta-badge time-badge">{{ timeLeft }}</span>
        </div>
        <div v-if="event.modifiers" class="modifiers">
          <span v-if="event.modifiers.starting_stats" class="mod-tag">Starting Stats: {{ event.modifiers.starting_stats }}</span>
          <span v-if="event.modifiers.xp_multiplier" class="mod-tag">XP x{{ event.modifiers.xp_multiplier }}</span>
        </div>
        <button class="btn-primary play-btn" @click="playEvent">Play Event</button>
      </div>

      <!-- Your entries -->
      <div v-if="userEntries.length > 0" class="card-panel">
        <h3 class="sub-title">Your Scores</h3>
        <div class="entries-list">
          <div v-for="(entry, i) in userEntries" :key="entry.id" class="entry-row">
            <span class="entry-rank">#{{ i + 1 }}</span>
            <span class="entry-score">{{ entry.score }}</span>
            <button class="replay-btn" @click="$router.push('/game/' + entry.game_id + '/replay')">Replay</button>
          </div>
        </div>
      </div>

      <!-- Leaderboard -->
      <div class="card-panel">
        <h3 class="sub-title">Leaderboard</h3>
        <div v-if="leaderboard.length === 0" class="empty">No entries yet. Be the first!</div>
        <div v-else class="leaderboard-list">
          <div v-for="entry in leaderboard" :key="entry.user_id" class="lb-row" :class="{ 'lb-me': entry.user_id === userId }">
            <span class="lb-rank">{{ entry.rank }}</span>
            <span class="lb-name">{{ entry.username }}</span>
            <span class="lb-score">{{ entry.best_score }}</span>
            <span class="lb-games">{{ entry.games_played }} game{{ entry.games_played !== 1 ? 's' : '' }}</span>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="empty">Event not found.</div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';

export default {
  name: 'RotatingEventPage',
  props: { id: [String, Number] },
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      event: null,
      userEntries: [],
      leaderboard: [],
      loading: true,
      timer: null,
    };
  },
  computed: {
    userId() {
      return this.auth.state.user?.id;
    },
    timeLeft() {
      if (!this.event) return '';
      const end = new Date(this.event.ends_at);
      const now = new Date();
      const diff = end - now;
      if (diff <= 0) return 'Ended';
      const hours = Math.floor(diff / 3600000);
      const days = Math.floor(hours / 24);
      if (days > 0) return `${days}d ${hours % 24}h left`;
      const mins = Math.floor((diff % 3600000) / 60000);
      return `${hours}h ${mins}m left`;
    },
  },
  async mounted() {
    await this.fetchEvent();
    this.timer = setInterval(() => this.$forceUpdate(), 60000);
  },
  beforeUnmount() {
    if (this.timer) clearInterval(this.timer);
  },
  methods: {
    async fetchEvent() {
      this.loading = true;
      try {
        const res = await axios.get(`/api/rotating-events/${this.id}`);
        this.event = res.data.event;
        this.userEntries = res.data.user_entries;
        this.leaderboard = res.data.leaderboard;
      } catch {}
      this.loading = false;
    },
    modeLabel(mode) {
      const labels = { single: 'Solo', pass_and_play: 'Local', online: 'Online' };
      return labels[mode] || mode;
    },
    playEvent() {
      // Navigate to game setup — the event will be passed as a query parameter
      this.$router.push({ path: '/', query: { event_id: this.event.id } });
    },
  },
};
</script>

<style scoped>
.event-page {
  max-width: 600px;
  margin: 0 auto;
}

.loading, .empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 40px 0;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
  margin-bottom: 10px;
  text-align: center;
}

.sub-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1rem;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.event-desc {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  margin-bottom: 14px;
  line-height: 1.5;
}

.event-meta {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.meta-badge {
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.type-cooperative { background: rgba(100, 100, 160, 0.2); color: #a0a0d0; border: 1px solid rgba(100, 100, 160, 0.3); }
.type-duel { background: rgba(200, 80, 60, 0.2); color: #e08060; border: 1px solid rgba(200, 80, 60, 0.3); }
.mode-single { background: rgba(100, 100, 160, 0.15); color: #9090c0; border: 1px solid rgba(100, 100, 160, 0.3); }
.mode-pass_and_play { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); border: 1px solid rgba(212, 168, 67, 0.3); }
.mode-online { background: rgba(67, 160, 212, 0.15); color: #60b8e0; border: 1px solid rgba(67, 160, 212, 0.3); }
.reward-badge { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); border: 1px solid rgba(212, 168, 67, 0.3); }
.time-badge { background: rgba(138, 58, 185, 0.15); color: #c890e0; border: 1px solid rgba(138, 58, 185, 0.3); }

.modifiers {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 16px;
}

.mod-tag {
  padding: 4px 10px;
  background: rgba(212, 168, 67, 0.08);
  border: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 4px;
  font-size: 0.8rem;
  color: var(--text-secondary);
}

.play-btn {
  display: block;
  margin: 0 auto;
  padding: 10px 30px;
  font-size: 1.1rem;
}

.entries-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.entry-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 6px;
}

.entry-rank {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.85rem;
  min-width: 30px;
}

.entry-score {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  font-weight: 700;
  flex: 1;
}

.replay-btn {
  background: rgba(100, 100, 160, 0.15);
  border: 1px solid rgba(100, 100, 160, 0.3);
  color: #a0a0d0;
  padding: 3px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
}

.leaderboard-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.lb-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 6px;
  transition: background 0.2s;
}

.lb-me {
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(212, 168, 67, 0.3);
}

.lb-rank {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
  min-width: 30px;
  font-size: 0.9rem;
}

.lb-name {
  flex: 1;
  color: var(--text-bright);
  font-size: 0.95rem;
}

.lb-score {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 1rem;
}

.lb-games {
  font-size: 0.75rem;
  color: var(--text-secondary);
  min-width: 60px;
  text-align: right;
}
</style>
