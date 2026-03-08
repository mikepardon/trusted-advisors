<template>
  <div class="timeline-section">
    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filterType" @change="resetAndFetch" class="filter-select">
        <option value="">All Types</option>
        <option value="cooperative">Classic</option>
        <option value="duel">Duel</option>
      </select>
      <select v-model="filterMode" @change="resetAndFetch" class="filter-select">
        <option value="">All Modes</option>
        <option value="single">Solo</option>
        <option value="pass_and_play">Local</option>
        <option value="online">Online</option>
      </select>
    </div>

    <div v-if="loading && entries.length === 0" class="loading">Loading matches...</div>
    <div v-else-if="entries.length === 0" class="empty">No completed games found.</div>

    <template v-else>
      <template v-for="(group, date) in groupedEntries" :key="date">
        <div class="date-header">{{ formatDateHeader(date) }}</div>
        <div
          v-for="match in group"
          :key="match.id"
          class="match-entry"
          @click="$router.push('/game/' + match.id + '/replay')"
        >
          <div class="match-top">
            <span :class="['outcome-badge', 'outcome-' + match.outcome]">
              {{ outcomeLabel(match.outcome) }}
            </span>
            <div class="match-badges">
              <span class="type-badge-sm" :class="'type-' + match.game_type">{{ match.game_type === 'duel' ? 'Duel' : 'Classic' }}</span>
              <span class="mode-badge-sm" :class="'mode-' + match.game_mode">{{ modeLabel(match.game_mode) }}</span>
              <span v-if="match.rotating_event" class="event-badge-sm">{{ match.rotating_event.name }}</span>
            </div>
          </div>
          <div class="match-bottom">
            <div class="match-players">
              <span v-for="(p, i) in match.players" :key="i" class="player-chip">
                <img v-if="p.character_image" :src="p.character_image" class="player-thumb" />
                <span>{{ p.character_name || p.username || '?' }}</span>
              </span>
            </div>
            <div class="match-meta">
              <span class="match-score"><span class="meta-label">Total Score</span>{{ match.score }}</span>
              <span v-if="match.duration_minutes" class="match-duration"><span class="meta-label">Duration</span>{{ formatDuration(match.duration_minutes) }}</span>
            </div>
          </div>
        </div>
      </template>

      <button v-if="hasMore" class="load-more-btn" @click="loadNext" :disabled="loading">
        {{ loading ? 'Loading...' : 'Load More' }}
      </button>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MatchHistoryTimeline',
  data() {
    return {
      entries: [],
      loading: false,
      currentPage: 1,
      lastPage: 1,
      filterType: '',
      filterMode: '',
    };
  },
  computed: {
    hasMore() {
      return this.currentPage < this.lastPage;
    },
    groupedEntries() {
      const groups = {};
      for (const entry of this.entries) {
        const date = entry.played_at.substring(0, 10);
        if (!groups[date]) groups[date] = [];
        groups[date].push(entry);
      }
      return groups;
    },
  },
  async mounted() {
    await this.fetchPage();
  },
  methods: {
    formatDuration(minutes) {
      const m = Math.floor(minutes);
      const s = Math.round((minutes - m) * 60);
      if (m === 0) return `${s}s`;
      if (s === 0) return `${m}m`;
      return `${m}m ${s}s`;
    },
    async fetchPage() {
      if (this.loading) return;
      this.loading = true;
      try {
        const params = { page: this.currentPage };
        if (this.filterType) params.game_type = this.filterType;
        if (this.filterMode) params.game_mode = this.filterMode;
        const res = await axios.get('/api/games/timeline', { params });
        this.entries.push(...res.data.data);
        this.lastPage = res.data.last_page;
      } catch {}
      this.loading = false;
    },
    async resetAndFetch() {
      this.entries = [];
      this.currentPage = 1;
      this.lastPage = 1;
      await this.fetchPage();
    },
    async loadNext() {
      if (this.hasMore && !this.loading) {
        this.currentPage++;
        await this.fetchPage();
      }
    },
    formatDateHeader(dateStr) {
      const date = new Date(dateStr + 'T00:00:00');
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);

      if (date.getTime() === today.getTime()) return 'Today';
      if (date.getTime() === yesterday.getTime()) return 'Yesterday';
      return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
    },
    outcomeLabel(outcome) {
      const labels = { win: 'Victory', loss: 'Defeat', draw: 'Draw', cancelled: 'Cancelled' };
      return labels[outcome] || outcome;
    },
    modeLabel(mode) {
      const labels = { single: 'Solo', pass_and_play: 'Local', online: 'Online' };
      return labels[mode] || mode;
    },
  },
};
</script>

<style scoped>
.filter-bar {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}

.filter-select {
  flex: 1;
  background: rgba(0, 0, 0, 0.3);
  border: 2px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 0.9rem;
  padding: 8px 10px;
  outline: none;
  cursor: pointer;
}

.filter-select:focus {
  border-color: var(--accent-gold);
}

.loading, .empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px 0;
}

.date-header {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  padding: 12px 0 6px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
  margin-bottom: 8px;
}

.match-entry {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.25);
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.match-entry:hover {
  background: rgba(212, 168, 67, 0.06);
}

.match-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.match-badges {
  display: flex;
  gap: 4px;
}

.match-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.match-players {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  flex: 1;
}

.player-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.82rem;
  color: var(--text-secondary);
}

.player-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid rgba(138, 106, 46, 0.3);
}

.match-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.match-score {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 1rem;
}

.match-duration {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.meta-label {
  display: block;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  opacity: 0.7;
  margin-bottom: 1px;
}

/* Badges */
.outcome-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
}
.outcome-win { background: rgba(74, 138, 58, 0.2); color: #6abf50; border: 1px solid rgba(74, 138, 58, 0.4); }
.outcome-loss { background: rgba(160, 48, 32, 0.2); color: #d05040; border: 1px solid rgba(160, 48, 32, 0.4); }
.outcome-draw { background: rgba(180, 160, 60, 0.2); color: #c0b040; border: 1px solid rgba(180, 160, 60, 0.4); }
.outcome-cancelled { background: rgba(120, 120, 120, 0.2); color: #999; border: 1px solid rgba(120, 120, 120, 0.4); }

.type-badge-sm, .mode-badge-sm, .event-badge-sm {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
}
.type-cooperative { background: rgba(100, 100, 160, 0.2); color: #a0a0d0; border: 1px solid rgba(100, 100, 160, 0.3); }
.type-duel { background: rgba(200, 80, 60, 0.2); color: #e08060; border: 1px solid rgba(200, 80, 60, 0.3); }
.mode-single { background: rgba(100, 100, 160, 0.15); color: #9090c0; border: 1px solid rgba(100, 100, 160, 0.3); }
.mode-pass_and_play { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); border: 1px solid rgba(212, 168, 67, 0.3); }
.mode-online { background: rgba(67, 160, 212, 0.15); color: #60b8e0; border: 1px solid rgba(67, 160, 212, 0.3); }
.event-badge-sm { background: rgba(138, 58, 185, 0.15); color: #c890e0; border: 1px solid rgba(138, 58, 185, 0.3); }

.load-more-btn {
  display: block;
  width: 100%;
  margin-top: 12px;
  padding: 10px;
  background: rgba(138, 106, 46, 0.12);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.2s;
}

.load-more-btn:hover {
  background: rgba(138, 106, 46, 0.25);
}

.load-more-btn:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>
