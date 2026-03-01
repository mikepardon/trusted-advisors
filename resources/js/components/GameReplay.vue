<template>
  <div class="replay-page">
    <h2 class="section-title">Game Replay</h2>

    <div v-if="loading" class="replay-loading">Loading replay...</div>
    <div v-else-if="error" class="replay-error">{{ error }}</div>

    <template v-else>
      <!-- Game info header -->
      <div class="card-panel replay-info">
        <div class="replay-meta">
          <span class="type-badge" :class="'type-' + (game.game_type || 'cooperative')">
            {{ game.game_type === 'duel' ? 'Duel' : 'Classic' }}
          </span>
          <span class="mode-badge" :class="'mode-' + (game.game_mode || 'single')">
            {{ { single: 'Solo', pass_and_play: 'Local', online: 'Online' }[game.game_mode] || 'Solo' }}
          </span>
          <span class="replay-rounds">{{ totalRoundsPlayed }} rounds played</span>
        </div>
        <div class="replay-players">
          <span v-for="p in game.players" :key="p.id" class="replay-player">
            {{ p.character?.name || 'Player' }}
          </span>
        </div>
      </div>

      <!-- Round navigation -->
      <div class="round-nav">
        <button class="nav-btn" :disabled="currentRound <= 1" @click="currentRound--">&larr; Prev</button>
        <span class="round-label">Round {{ currentRound }} / {{ totalRoundsPlayed }}</span>
        <button class="nav-btn" :disabled="currentRound >= totalRoundsPlayed" @click="currentRound++">&rarr; Next</button>
      </div>

      <!-- Event Banner -->
      <div v-if="currentEventData" class="card-panel event-banner">
        <div class="event-header">
          <span class="event-icon">&#9733;</span>
          <strong class="event-name">{{ currentEventData.name }}</strong>
          <span v-if="isNewEvent" class="event-new-badge">New!</span>
        </div>
        <p class="event-description">{{ currentEventData.description }}</p>
      </div>

      <!-- Cards Dealt Section -->
      <div v-if="currentRoundHands && currentRoundHands.length > 0" class="card-panel cards-section">
        <h3 class="section-sub-title">Cards Dealt</h3>
        <div class="cards-grid">
          <div v-for="(hand, i) in currentRoundHands" :key="i" class="dealt-card" :class="'role-' + (hand.role || 'unknown')">
            <div class="dealt-card-header">
              <span class="dealt-card-name">{{ hand.card?.title || 'Card' }}</span>
              <span v-if="hand.role" class="role-badge" :class="'badge-' + hand.role">{{ hand.role }}</span>
            </div>
            <div v-if="hand.card?.difficulty" class="dealt-card-difficulty">
              Difficulty: {{ hand.card.difficulty }}
            </div>
            <div v-if="hand.card?.positive_effects" class="dealt-card-effects">
              <span v-for="(val, stat) in hand.card.positive_effects" :key="'pos-' + stat" class="mini-effect effect-positive">
                {{ formatStatName(stat) }} +{{ val }}
              </span>
            </div>
            <div v-if="hand.card?.negative_effects" class="dealt-card-effects">
              <span v-for="(val, stat) in hand.card.negative_effects" :key="'neg-' + stat" class="mini-effect effect-negative">
                {{ formatStatName(stat) }} {{ val }}
              </span>
            </div>
            <div v-if="hand.player?.character?.name" class="dealt-card-player">
              {{ hand.player.character.name }}
            </div>
          </div>
        </div>
      </div>

      <!-- Round results -->
      <div class="round-details">
        <div v-if="!currentRoundData || currentRoundData.length === 0" class="no-data">
          No data for this round.
        </div>
        <div v-for="result in currentRoundData" :key="result.id" class="result-card card-panel">
          <div class="result-header">
            <strong class="card-name">{{ resultCardName(result) }}</strong>
            <span v-if="result.player" class="player-tag">{{ result.player?.character?.name || 'Player' }}</span>
          </div>

          <div class="result-body">
            <!-- Duel dice (array of player roll objects) -->
            <template v-if="isDuelResult(result)">
              <div v-for="(pr, pi) in result.dice_results" :key="pi" class="dice-section">
                <span class="sub-label">{{ pr.character_name }}:</span>
                <span v-for="(roll, ri) in pr.rolls" :key="ri" class="die-face">{{ roll.value }}</span>
                <span class="dice-total">= {{ pr.rolls.reduce((s, r) => s + r.value, 0) }}</span>
              </div>
              <div class="dice-section">
                <span class="sub-label">vs Difficulty {{ result.stat_totals?.total_difficulty }}</span>
                <span class="dice-outcome" :class="result.success ? 'outcome-pass' : 'outcome-fail'">
                  {{ result.success ? 'Success' : 'Failed' }}
                </span>
              </div>
            </template>

            <!-- Cooperative dice (flat array of numbers) -->
            <template v-else-if="result.dice_results">
              <div class="dice-section">
                <span class="sub-label">Dice:</span>
                <span v-for="(d, i) in result.dice_results" :key="i" class="die-face">{{ d }}</span>
                <span class="dice-outcome" :class="result.success ? 'outcome-pass' : 'outcome-fail'">
                  {{ result.success ? 'Success' : 'Failed' }}
                </span>
              </div>
            </template>

            <!-- Stat changes (effects_applied as object for duel, array for cooperative) -->
            <div v-if="hasEffects(result)" class="effects-section">
              <span class="sub-label">Effects:</span>
              <div class="effects-list">
                <template v-if="Array.isArray(result.effects_applied)">
                  <span v-for="(eff, i) in result.effects_applied" :key="i" class="effect-tag">{{ eff }}</span>
                </template>
                <template v-else>
                  <span v-for="(val, stat) in result.effects_applied" :key="stat" class="effect-tag" :class="val > 0 ? 'effect-positive' : 'effect-negative'">
                    {{ formatStatName(stat) }} {{ val > 0 ? '+' : '' }}{{ val }}
                  </span>
                </template>
              </div>
            </div>

            <!-- Special Effects (items, curses, etc.) -->
            <div v-if="result.special_effects && result.special_effects.length > 0" class="special-effects-section">
              <span class="sub-label">Special:</span>
              <div class="effects-list">
                <span v-for="(effect, i) in result.special_effects" :key="i" class="effect-tag effect-special">
                  {{ effect }}
                </span>
              </div>
            </div>

            <!-- Kingdom Stats Snapshot -->
            <div v-if="result.kingdom_snapshot" class="kingdom-snapshot">
              <span class="sub-label">Kingdom After:</span>
              <div class="stat-chips">
                <span
                  v-for="(val, stat) in result.kingdom_snapshot"
                  :key="stat"
                  class="stat-chip"
                  :class="statChangeClass(stat, val)"
                >
                  {{ formatStatName(stat) }}: {{ val }}
                  <span v-if="statDelta(stat, val) !== 0" class="stat-delta">
                    ({{ statDelta(stat, val) > 0 ? '+' : '' }}{{ statDelta(stat, val) }})
                  </span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="replay-actions">
        <button v-if="!shareToken" class="btn-primary back-btn" @click="$router.push('/campaigns')">Back to History</button>
        <button v-else class="btn-primary back-btn" @click="$router.push('/')">Home</button>
        <button v-if="!shareToken" class="share-btn" @click="shareReplay">
          {{ shareCopied ? 'Copied!' : 'Share Replay' }}
        </button>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'GameReplay',
  props: {
    id: { type: [String, Number], default: null },
    shareToken: { type: String, default: null },
  },
  data() {
    return {
      loading: true,
      error: null,
      game: null,
      rounds: {},
      hands: {},
      totalRoundsPlayed: 0,
      currentRound: 1,
      shareCopied: false,
    };
  },
  computed: {
    currentRoundData() {
      return this.rounds[this.currentRound] || [];
    },
    currentRoundHands() {
      return this.hands[this.currentRound] || [];
    },
    isDuel() {
      return this.game?.game_type === 'duel';
    },
    currentEventData() {
      // Find event_data from the first result of this round
      const results = this.currentRoundData;
      if (!results || results.length === 0) return null;
      return results[0]?.event_data || null;
    },
    isNewEvent() {
      // Event changes every 3 rounds (rounds 1,4,7,10...)
      if (!this.currentEventData) return false;
      if (this.currentRound === 1) return true;
      // Check if previous round had a different event
      const prevResults = this.rounds[this.currentRound - 1] || [];
      const prevEvent = prevResults[0]?.event_data;
      return !prevEvent || prevEvent.id !== this.currentEventData.id;
    },
    previousRoundSnapshot() {
      if (this.currentRound <= 1) return null;
      const prevResults = this.rounds[this.currentRound - 1] || [];
      // Get the last result's kingdom_snapshot from previous round
      for (let i = prevResults.length - 1; i >= 0; i--) {
        if (prevResults[i].kingdom_snapshot) return prevResults[i].kingdom_snapshot;
      }
      return null;
    },
  },
  async mounted() {
    try {
      const url = this.shareToken
        ? `/api/replays/${this.shareToken}`
        : `/api/games/${this.id}/replay`;
      const res = await axios.get(url);
      this.game = res.data.game;
      this.rounds = res.data.rounds;
      this.hands = res.data.hands || {};
      this.totalRoundsPlayed = res.data.total_rounds_played;
    } catch (e) {
      this.error = e.response?.data?.error || 'Failed to load replay';
    }
    this.loading = false;
  },
  methods: {
    isDuelResult(result) {
      return Array.isArray(result.dice_results) && result.dice_results.length > 0 && result.dice_results[0]?.rolls;
    },
    resultCardName(result) {
      if (result.card?.title) return result.card.title;
      if (result.card?.name) return result.card.name;
      if (this.isDuel && result.player?.character?.name) {
        return result.player.character.name + "'s Turn";
      }
      return 'Card';
    },
    hasEffects(result) {
      if (!result.effects_applied) return false;
      if (Array.isArray(result.effects_applied)) return result.effects_applied.length > 0;
      return Object.keys(result.effects_applied).length > 0;
    },
    formatStatName(stat) {
      return stat.charAt(0).toUpperCase() + stat.slice(1).replace(/_/g, ' ');
    },
    statDelta(stat, currentVal) {
      const prev = this.previousRoundSnapshot;
      if (!prev || prev[stat] === undefined) return 0;
      return currentVal - prev[stat];
    },
    statChangeClass(stat, currentVal) {
      const delta = this.statDelta(stat, currentVal);
      if (delta > 0) return 'stat-up';
      if (delta < 0) return 'stat-down';
      return '';
    },
    async shareReplay() {
      try {
        const res = await axios.post(`/api/games/${this.id}/share`);
        await navigator.clipboard.writeText(res.data.share_url);
        this.shareCopied = true;
        setTimeout(() => { this.shareCopied = false; }, 2000);
      } catch {
        alert('Failed to generate share link');
      }
    },
  },
};
</script>

<style scoped>
.replay-page {
  max-width: 700px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
  text-align: center;
}

.replay-loading, .replay-error, .no-data {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.replay-error { color: var(--accent-red); }

.replay-info {
  text-align: center;
  margin-bottom: 16px;
}

.replay-meta {
  display: flex;
  gap: 8px;
  justify-content: center;
  align-items: center;
  margin-bottom: 8px;
}

.replay-rounds {
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.replay-players {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
}

.replay-player {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
}

.type-badge, .mode-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.type-cooperative { background: rgba(100, 100, 160, 0.2); color: #a0a0d0; border: 1px solid rgba(100, 100, 160, 0.3); }
.type-duel { background: rgba(200, 80, 60, 0.2); color: #e08060; border: 1px solid rgba(200, 80, 60, 0.3); }
.mode-single { background: rgba(100, 100, 160, 0.15); color: #9090c0; border: 1px solid rgba(100, 100, 160, 0.3); }
.mode-pass_and_play { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); border: 1px solid rgba(212, 168, 67, 0.3); }
.mode-online { background: rgba(67, 160, 212, 0.15); color: #60b8e0; border: 1px solid rgba(67, 160, 212, 0.3); }

/* Round nav */
.round-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-bottom: 16px;
}

.nav-btn {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--accent-gold);
  padding: 6px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.nav-btn:disabled {
  opacity: 0.3;
  cursor: default;
}

.round-label {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1rem;
}

/* Event banner */
.event-banner {
  margin-bottom: 12px;
  border-left: 3px solid var(--accent-gold);
}

.event-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.event-icon {
  color: var(--accent-gold);
  font-size: 1.1rem;
}

.event-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
}

.event-new-badge {
  font-size: 0.7rem;
  font-weight: 700;
  color: #fff;
  background: rgba(200, 80, 60, 0.7);
  padding: 1px 6px;
  border-radius: 3px;
}

.event-description {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
  margin: 0;
}

/* Cards dealt */
.cards-section {
  margin-bottom: 12px;
}

.section-sub-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.9rem;
  margin: 0 0 10px 0;
}

.cards-grid {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.dealt-card {
  flex: 1;
  min-width: 140px;
  max-width: 200px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(100, 100, 160, 0.2);
  border-radius: 6px;
  padding: 10px;
}

.dealt-card.role-positive { border-color: rgba(74, 138, 58, 0.3); }
.dealt-card.role-negative { border-color: rgba(160, 48, 32, 0.3); }

.dealt-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
  gap: 4px;
}

.dealt-card-name {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.8rem;
}

.role-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 3px;
  text-transform: uppercase;
  white-space: nowrap;
}

.badge-positive { background: rgba(74, 138, 58, 0.2); color: #6abf50; }
.badge-negative { background: rgba(160, 48, 32, 0.2); color: #d05040; }

.dealt-card-difficulty {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-bottom: 4px;
}

.dealt-card-effects {
  display: flex;
  gap: 3px;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.mini-effect {
  font-size: 0.7rem;
  padding: 1px 4px;
  border-radius: 3px;
}

.dealt-card-player {
  font-size: 0.7rem;
  color: var(--text-secondary);
  margin-top: 4px;
  font-style: italic;
}

/* Result cards */
.round-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
}

.result-card {
  padding: 14px;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.card-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
}

.player-tag {
  font-size: 0.8rem;
  color: var(--text-secondary);
  background: rgba(0, 0, 0, 0.2);
  padding: 2px 8px;
  border-radius: 4px;
}

.sub-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-right: 6px;
}

.dice-section {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.die-face {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 4px;
  padding: 2px 8px;
  font-weight: 700;
  color: var(--text-bright);
  font-size: 0.9rem;
}

.dice-outcome {
  font-size: 0.8rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}

.dice-total {
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 0.9rem;
}

.outcome-pass { color: #6abf50; background: rgba(74, 138, 58, 0.15); }
.outcome-fail { color: #d05040; background: rgba(160, 48, 32, 0.15); }

.effects-section, .special-effects-section {
  margin-bottom: 8px;
}

.effects-list {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.effect-tag {
  font-size: 0.8rem;
  padding: 2px 8px;
  border-radius: 4px;
  background: rgba(100, 100, 160, 0.15);
  color: #a0a0d0;
}

.effect-positive {
  background: rgba(74, 138, 58, 0.15);
  color: #6abf50;
}

.effect-negative {
  background: rgba(160, 48, 32, 0.15);
  color: #d05040;
}

.effect-special {
  background: rgba(180, 140, 50, 0.15);
  color: var(--accent-gold);
}

/* Kingdom snapshot */
.kingdom-snapshot {
  margin-top: 8px;
}

.stat-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.stat-chip {
  font-size: 0.75rem;
  padding: 2px 6px;
  border-radius: 3px;
  background: rgba(0, 0, 0, 0.2);
  color: var(--text-primary);
  transition: all 0.2s;
}

.stat-chip.stat-up {
  background: rgba(74, 138, 58, 0.15);
  color: #6abf50;
}

.stat-chip.stat-down {
  background: rgba(160, 48, 32, 0.15);
  color: #d05040;
}

.stat-delta {
  font-size: 0.65rem;
  opacity: 0.8;
}

/* Actions */
.replay-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.back-btn {
  font-size: 1rem;
  padding: 10px 30px;
}

.share-btn {
  font-size: 1rem;
  padding: 10px 30px;
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
  border: 1px solid rgba(67, 160, 212, 0.3);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.share-btn:hover {
  background: rgba(67, 160, 212, 0.25);
  border-color: rgba(67, 160, 212, 0.5);
}

@media (max-width: 768px) {
  .result-header {
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
  }

  .cards-grid {
    flex-direction: column;
  }

  .dealt-card {
    max-width: none;
  }
}
</style>
