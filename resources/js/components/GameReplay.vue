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
      <div v-if="currentEventData && currentEventData.name" class="card-panel event-banner">
        <div class="event-header">
          <span class="event-icon">&#9733;</span>
          <strong class="event-name">{{ currentEventData.name }}</strong>
          <span v-if="isNewEvent" class="event-new-badge">New!</span>
        </div>
        <p v-if="currentEventData.description" class="event-description">{{ currentEventData.description }}</p>
        <div v-if="currentEventData.stat_modifiers" class="event-modifiers">
          <span
            v-for="(val, stat) in currentEventData.stat_modifiers"
            :key="stat"
            class="mini-effect"
            :class="val > 0 ? 'effect-positive' : 'effect-negative'"
          >
            {{ formatStatName(stat) }} {{ val > 0 ? '+' : '' }}{{ val }}/round
          </span>
        </div>
      </div>

      <!-- Cards Dealt Section -->
      <div v-if="currentRoundHands && currentRoundHands.length > 0" class="card-panel cards-section">
        <h3 class="section-sub-title">Cards Dealt</h3>
        <div class="cards-grid">
          <div
            v-for="(hand, i) in currentRoundHands"
            :key="i"
            class="dealt-card clickable"
            :class="'role-' + (hand.role || 'unknown')"
            @click="openCardDetail(hand.card)"
          >
            <div class="dealt-card-header">
              <span class="dealt-card-name">{{ hand.card?.title || 'Card' }}</span>
              <span v-if="hand.role" class="role-badge" :class="'badge-' + hand.role">{{ hand.role }}</span>
            </div>
            <div v-if="hand.card?.difficulty" class="dealt-card-difficulty">
              Difficulty: {{ hand.card.difficulty }}
            </div>
            <div v-if="hand.card?.positive_effects" class="dealt-card-effects">
              <span v-for="(val, stat) in filterStatEffects(hand.card.positive_effects)" :key="'pos-' + stat" class="mini-effect effect-positive">
                {{ formatStatName(stat) }} +{{ val }}
              </span>
            </div>
            <div v-if="hand.card?.negative_effects" class="dealt-card-effects">
              <span v-for="(val, stat) in filterStatEffects(hand.card.negative_effects)" :key="'neg-' + stat" class="mini-effect effect-negative">
                {{ formatStatName(stat) }} {{ val }}
              </span>
            </div>
            <div v-if="hand.player?.character?.name" class="dealt-card-player">
              {{ hand.player.character.name }}
            </div>
            <div class="tap-hint">Tap for details</div>
          </div>
        </div>
      </div>

      <!-- Items Received This Round -->
      <div v-if="roundItemsReceived.length > 0" class="card-panel items-section">
        <h3 class="section-sub-title">Items Received</h3>
        <div class="special-tags-grid">
          <button
            v-for="pi in roundItemsReceived"
            :key="'recv-' + pi.id"
            class="special-tag tag-item"
            @click="openItemDetail(pi.item)"
          >
            <span class="tag-icon">&#9876;</span>
            <span class="tag-name">{{ pi.item?.name || 'Item' }}</span>
            <span class="tag-player">{{ getPlayerNameById(pi.game_player_id) }}</span>
          </button>
        </div>
      </div>

      <!-- Items Used This Round -->
      <div v-if="roundItemsUsed.length > 0" class="card-panel items-section">
        <h3 class="section-sub-title">Items Used</h3>
        <div class="special-tags-grid">
          <button
            v-for="pi in roundItemsUsed"
            :key="'used-' + pi.id"
            class="special-tag tag-item-used"
            @click="openItemDetail(pi.item)"
          >
            <span class="tag-icon">&#9876;</span>
            <span class="tag-name">{{ pi.item?.name || 'Item' }}</span>
            <span class="tag-player">{{ getPlayerNameById(pi.game_player_id) }}</span>
          </button>
        </div>
      </div>

      <!-- Curses Received This Round -->
      <div v-if="roundCurses.length > 0" class="card-panel curses-section">
        <h3 class="section-sub-title">Curses Received</h3>
        <div class="special-tags-grid">
          <button
            v-for="pc in roundCurses"
            :key="'curse-' + pc.id"
            class="special-tag tag-curse"
            @click="openCurseDetail(pc.curse)"
          >
            <span class="tag-icon">&#9760;</span>
            <span class="tag-name">{{ pc.curse?.name || 'Curse' }}</span>
            <span class="tag-player">{{ getPlayerNameById(pc.game_player_id) }}</span>
          </button>
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
                <template v-for="(effect, i) in result.special_effects" :key="i">
                  <button
                    v-if="effect.type === 'draw_item' || effect.type === 'item_blocked' || effect.type === 'discard_item'"
                    class="effect-tag effect-special effect-clickable"
                    @click="openItemByName(effect.item)"
                  >
                    {{ effect.description || effect }}
                  </button>
                  <span v-else class="effect-tag effect-special">
                    {{ typeof effect === 'string' ? effect : (effect.description || JSON.stringify(effect)) }}
                  </span>
                </template>
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

    <!-- Detail Overlay -->
    <teleport to="body">
      <transition name="detail-fade">
        <div v-if="detailOverlay.show" class="detail-overlay" @click.self="detailOverlay.show = false">
          <div class="detail-viewer">
            <button class="detail-close" @click="detailOverlay.show = false">&times;</button>

            <!-- Card Detail -->
            <template v-if="detailOverlay.type === 'card' && detailOverlay.data">
              <div class="detail-parchment">
                <div class="parchment-ornament">&#9876;</div>
                <h3 class="parchment-title">{{ detailOverlay.data.title || 'Card' }}</h3>

                <div v-if="detailOverlay.data.category" class="parchment-category">{{ detailOverlay.data.category }}</div>

                <div class="parchment-divider"><span class="divider-diamond">&#9830;</span></div>

                <p v-if="detailOverlay.data.description" class="parchment-desc">{{ detailOverlay.data.description }}</p>

                <div class="parchment-stat-row">
                  <span class="parchment-stat difficulty-stat">Difficulty: {{ detailOverlay.data.difficulty }}</span>
                </div>

                <div class="parchment-divider divider-thin"><span class="divider-diamond small">&#8226;</span></div>

                <!-- Positive effects -->
                <div v-if="detailOverlay.data.positive_effects" class="parchment-effects">
                  <span class="effects-label">On Success:</span>
                  <div class="effects-chips">
                    <span
                      v-for="(val, stat) in filterStatEffects(detailOverlay.data.positive_effects)"
                      :key="'dp-' + stat"
                      class="stat-chip chip-positive"
                    >
                      {{ formatStatName(stat) }} +{{ val }}
                    </span>
                    <span v-if="detailOverlay.data.positive_effects.draw_item" class="stat-chip chip-special">Draw Item</span>
                    <span v-if="detailOverlay.data.positive_effects.draw_curse" class="stat-chip chip-curse">Draw Curse</span>
                    <span v-if="detailOverlay.data.positive_effects.recover_die" class="stat-chip chip-positive">Recover Die</span>
                    <span v-if="detailOverlay.data.positive_effects.remove_curse" class="stat-chip chip-positive">Remove Curse</span>
                  </div>
                </div>

                <!-- Negative effects -->
                <div v-if="detailOverlay.data.negative_effects" class="parchment-effects">
                  <span class="effects-label">Always:</span>
                  <div class="effects-chips">
                    <span
                      v-for="(val, stat) in filterStatEffects(detailOverlay.data.negative_effects)"
                      :key="'dn-' + stat"
                      class="stat-chip chip-negative"
                    >
                      {{ formatStatName(stat) }} {{ val }}
                    </span>
                    <span v-if="detailOverlay.data.negative_effects.draw_item" class="stat-chip chip-special">Draw Item</span>
                    <span v-if="detailOverlay.data.negative_effects.draw_curse" class="stat-chip chip-curse">Draw Curse</span>
                    <span v-if="detailOverlay.data.negative_effects.lose_die" class="stat-chip chip-negative">Lose Die</span>
                    <span v-if="detailOverlay.data.negative_effects.discard_item" class="stat-chip chip-negative">Discard Item</span>
                  </div>
                </div>

                <!-- Flavor text -->
                <div v-if="detailOverlay.data.positive_flavor || detailOverlay.data.negative_flavor" class="parchment-flavor">
                  <p v-if="detailOverlay.data.positive_flavor" class="flavor-text flavor-positive">{{ detailOverlay.data.positive_flavor }}</p>
                  <p v-if="detailOverlay.data.negative_flavor" class="flavor-text flavor-negative">{{ detailOverlay.data.negative_flavor }}</p>
                </div>
              </div>
            </template>

            <!-- Item Detail -->
            <template v-if="detailOverlay.type === 'item' && detailOverlay.data">
              <div class="detail-parchment">
                <div class="parchment-ornament">&#9876;</div>
                <h3 class="parchment-title">{{ detailOverlay.data.name || 'Item' }}</h3>

                <div class="tag-row">
                  <span v-if="detailOverlay.data.is_consumable" class="type-tag consumable-tag">Consumable</span>
                  <span v-else class="type-tag reusable-tag">Reusable</span>
                  <span v-if="detailOverlay.data.target === 'opponent'" class="type-tag opponent-tag">Targets Opponent</span>
                </div>

                <div class="parchment-divider"><span class="divider-diamond">&#9830;</span></div>

                <p class="parchment-desc">{{ detailOverlay.data.description || '' }}</p>

                <div class="parchment-divider divider-thin"><span class="divider-diamond small">&#8226;</span></div>

                <div v-if="itemEffectSummary(detailOverlay.data)" class="effects-chips centered">
                  <span class="stat-chip" :class="itemChipClass(detailOverlay.data)">
                    {{ itemEffectSummary(detailOverlay.data) }}
                  </span>
                </div>

                <div v-if="detailOverlay.data.effect_type" class="parchment-meta">
                  {{ detailOverlay.data.effect_type }}
                </div>
              </div>
            </template>

            <!-- Curse Detail -->
            <template v-if="detailOverlay.type === 'curse' && detailOverlay.data">
              <div class="detail-parchment parchment-curse">
                <div class="parchment-ornament curse-ornament">&#9760;</div>
                <h3 class="parchment-title curse-title">{{ detailOverlay.data.name || 'Curse' }}</h3>

                <div class="tag-row">
                  <span class="type-tag cursed-tag">Curse</span>
                </div>

                <div class="parchment-divider curse-divider"><span class="divider-diamond">&#9830;</span></div>

                <p class="parchment-desc">{{ detailOverlay.data.description || '' }}</p>

                <div class="parchment-divider divider-thin curse-divider"><span class="divider-diamond small">&#8226;</span></div>

                <!-- Negative effect (penalty) -->
                <div v-if="detailOverlay.data.negative_effect" class="parchment-effects">
                  <span class="effects-label curse-label">Penalty:</span>
                  <div class="effects-chips centered">
                    <span class="stat-chip chip-negative">{{ describeCurseEffect(detailOverlay.data.negative_effect) }}</span>
                  </div>
                </div>

                <!-- Positive effect (reward for completing) -->
                <div v-if="detailOverlay.data.positive_effect" class="parchment-effects">
                  <span class="effects-label">Reward:</span>
                  <div class="effects-chips centered">
                    <span class="stat-chip chip-positive">{{ describeCurseEffect(detailOverlay.data.positive_effect) }}</span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../stores/toast';

export default {
  name: 'GameReplay',
  setup() {
    return { toast: useToast() };
  },
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
      detailOverlay: { show: false, type: null, data: null },
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
      const results = this.currentRoundData;
      if (!results || results.length === 0) return null;
      return results[0]?.event_data || null;
    },
    isNewEvent() {
      if (!this.currentEventData) return false;
      if (this.currentRound === 1) return true;
      const prevResults = this.rounds[this.currentRound - 1] || [];
      const prevEvent = prevResults[0]?.event_data;
      return !prevEvent || prevEvent.id !== this.currentEventData.id;
    },
    previousRoundSnapshot() {
      if (this.currentRound <= 1) return null;
      const prevResults = this.rounds[this.currentRound - 1] || [];
      for (let i = prevResults.length - 1; i >= 0; i--) {
        if (prevResults[i].kingdom_snapshot) return prevResults[i].kingdom_snapshot;
      }
      return null;
    },
    allPlayerItems() {
      if (!this.game?.players) return [];
      return this.game.players.flatMap(p =>
        (p.items || []).map(i => ({ ...i, game_player_id: p.id }))
      );
    },
    roundItemsReceived() {
      return this.allPlayerItems.filter(i => i.acquired_round === this.currentRound);
    },
    roundItemsUsed() {
      return this.allPlayerItems.filter(i => i.used_round === this.currentRound);
    },
    allPlayerCurses() {
      if (!this.game?.players) return [];
      return this.game.players.flatMap(p =>
        (p.curses || []).map(c => ({ ...c, game_player_id: p.id }))
      );
    },
    roundCurses() {
      return this.allPlayerCurses.filter(c => c.acquired_round === this.currentRound);
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
    filterStatEffects(effects) {
      if (!effects) return {};
      const special = ['draw_item', 'draw_curse', 'recover_die', 'lose_die', 'discard_item', 'remove_curse', 'bonus_score', 'end_game_modifier', 'grant_item_id'];
      const filtered = {};
      for (const [k, v] of Object.entries(effects)) {
        if (!special.includes(k)) filtered[k] = v;
      }
      return filtered;
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
    getPlayerNameById(gamePlayerId) {
      if (!this.game?.players) return '';
      const player = this.game.players.find(p => p.id === gamePlayerId);
      return player?.character?.name || '';
    },
    openCardDetail(card) {
      if (!card) return;
      this.detailOverlay = { show: true, type: 'card', data: card };
    },
    openItemDetail(item) {
      if (!item) return;
      this.detailOverlay = { show: true, type: 'item', data: item };
    },
    openItemByName(itemName) {
      if (!itemName) return;
      const found = this.allPlayerItems.find(pi => pi.item?.name === itemName);
      if (found?.item) {
        this.openItemDetail(found.item);
      }
    },
    openCurseDetail(curse) {
      if (!curse) return;
      this.detailOverlay = { show: true, type: 'curse', data: curse };
    },
    itemEffectSummary(item) {
      if (!item?.effect) return '';
      const type = item.effect.bonus_type || '';
      const value = item.effect.bonus_value ?? 0;
      switch (type) {
        case 'roll_bonus': return `+${value} to roll`;
        case 'roll_penalty': return `${value} to roll`;
        case 'difficulty_reduction': return `-${Math.abs(value)} difficulty`;
        case 'difficulty_increase': return `+${Math.abs(value)} difficulty`;
        case 'score_bonus': return `${value > 0 ? '+' : ''}${value} renown`;
        case 'stat_boost': return `+${value} ${item.effect.stat || 'stat'}`;
        case 'heal_die': return 'Recover a lost die';
        case 'shield_negative': return 'Block negative effects';
        case 'debuff_roll': return `${value} to opponent roll`;
        case 'increase_difficulty': return `+${Math.abs(value)} opponent difficulty`;
        case 'peek_cards': return 'Peek at opponent cards';
        case 'steal_stat': return `Steal ${value} stat point`;
        default: return item.description || 'Single-use effect';
      }
    },
    itemChipClass(item) {
      const type = item?.effect?.bonus_type || '';
      if (['roll_bonus', 'difficulty_reduction', 'heal_die', 'shield_negative', 'stat_boost'].includes(type)) return 'chip-positive';
      if (['debuff_roll', 'increase_difficulty', 'roll_penalty', 'difficulty_increase'].includes(type)) return 'chip-negative';
      return 'chip-special';
    },
    describeCurseEffect(effect) {
      if (!effect) return '';
      const type = effect.type || '';
      const value = effect.value ?? 0;
      switch (type) {
        case 'stat_penalty': return `${value} ${effect.stat || 'stat'} per round`;
        case 'difficulty_modifier': return `+${value} difficulty`;
        case 'double_negative': return 'Double negative effects';
        case 'lose_die': return 'Lose a die';
        case 'stat_bonus': return `+${value} ${effect.stat || 'stat'}`;
        case 'recover_die': return 'Recover a die';
        case 'remove_curse': return 'Remove this curse';
        default: return type.replace(/_/g, ' ');
      }
    },
    async shareReplay() {
      try {
        const res = await axios.post(`/api/games/${this.id}/share`);
        await navigator.clipboard.writeText(res.data.share_url);
        this.shareCopied = true;
        setTimeout(() => { this.shareCopied = false; }, 2000);
      } catch {
        this.toast.error('Failed to generate share link');
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
  margin: 0 0 6px 0;
}

.event-modifiers {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-top: 4px;
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
  transition: all 0.2s;
}

.dealt-card.clickable {
  cursor: pointer;
}

.dealt-card.clickable:hover {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
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

.tap-hint {
  font-size: 0.6rem;
  color: var(--text-secondary);
  opacity: 0.5;
  text-align: center;
  margin-top: 6px;
}

/* Items & Curses sections */
.items-section, .curses-section {
  margin-bottom: 12px;
}

.special-tags-grid {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.special-tag {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid;
  font-size: 0.85rem;
  transition: all 0.2s;
  background: none;
  font-family: inherit;
}

.special-tag:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.tag-item {
  background: rgba(212, 168, 67, 0.1);
  border-color: rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.tag-item:hover {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
}

.tag-item-used {
  background: rgba(100, 100, 160, 0.1);
  border-color: rgba(100, 100, 160, 0.3);
  color: #a0a0d0;
}

.tag-item-used:hover {
  background: rgba(100, 100, 160, 0.2);
  border-color: #a0a0d0;
}

.tag-curse {
  background: rgba(192, 57, 43, 0.1);
  border-color: rgba(192, 57, 43, 0.3);
  color: #e07060;
}

.tag-curse:hover {
  background: rgba(192, 57, 43, 0.2);
  border-color: #e07060;
}

.tag-icon {
  font-size: 1rem;
  opacity: 0.8;
}

.tag-name {
  font-family: 'Cinzel', serif;
  font-weight: 600;
}

.tag-player {
  font-size: 0.7rem;
  opacity: 0.6;
  margin-left: auto;
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

.effect-clickable {
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
}

.effect-clickable:hover {
  border-color: var(--accent-gold);
  background: rgba(180, 140, 50, 0.25);
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

/* --- Detail Overlay --- */
.detail-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
}

.detail-viewer {
  position: relative;
  max-width: 380px;
  width: 90%;
  padding: 20px;
}

.detail-close {
  position: absolute;
  top: -10px;
  right: -10px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  border: 1px solid var(--border-gold, #6b5b3a);
  color: var(--text-secondary, #a09080);
  font-size: 1.4rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s;
  z-index: 10;
}

.detail-close:hover {
  color: var(--accent-gold, #c9a84c);
}

/* Parchment card */
.detail-parchment {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 24px 20px;
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.detail-parchment.parchment-curse {
  border-color: rgba(192, 57, 43, 0.7);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(192, 57, 43, 0.15);
}

.parchment-ornament {
  font-size: 2rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.5;
  margin-bottom: 8px;
}

.parchment-ornament.curse-ornament {
  color: #c0392b;
}

.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 6px;
  line-height: 1.3;
}

.parchment-title.curse-title {
  color: #e07060;
}

.parchment-category {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary, #a09080);
  margin-bottom: 4px;
}

.tag-row {
  display: flex;
  gap: 6px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.type-tag {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 3px;
  padding: 1px 8px;
}

.consumable-tag {
  color: var(--accent-gold, #c9a84c);
  border: 1px solid var(--accent-gold, #c9a84c);
}

.reusable-tag {
  color: #67c23a;
  border: 1px solid #67c23a;
}

.opponent-tag {
  color: #e57373;
  border: 1px solid #e57373;
}

.cursed-tag {
  color: #c0392b;
  border: 1px solid #c0392b;
}

.parchment-divider {
  position: relative;
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
  margin: 12px 0;
}

.parchment-divider.divider-thin {
  background: linear-gradient(90deg, transparent, rgba(138, 106, 46, 0.4), transparent);
  margin: 8px 0;
}

.parchment-divider.curse-divider {
  background: linear-gradient(90deg, transparent, rgba(192, 57, 43, 0.5), transparent);
}

.divider-diamond {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #2a1f14;
  color: var(--accent-gold, #c9a84c);
  padding: 0 8px;
  font-size: 0.7rem;
}

.divider-diamond.small {
  font-size: 0.5rem;
  color: var(--text-secondary, #a09080);
}

.parchment-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  flex: 1;
}

.parchment-stat-row {
  margin: 8px 0;
}

.difficulty-stat {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary, #a09080);
  font-size: 0.85rem;
}

.parchment-effects {
  width: 100%;
  margin-bottom: 8px;
}

.effects-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary, #a09080);
  display: block;
  margin-bottom: 4px;
  text-align: center;
}

.effects-label.curse-label {
  color: #e07060;
}

.effects-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.effects-chips.centered {
  justify-content: center;
}

.chip-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.chip-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
}

.chip-special {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold, #c9a84c);
}

.chip-curse {
  background: rgba(192, 57, 43, 0.15);
  color: #e07060;
}

.parchment-flavor {
  margin-top: 8px;
  width: 100%;
}

.flavor-text {
  font-size: 0.75rem;
  font-style: italic;
  text-align: center;
  margin: 2px 0;
}

.flavor-text.flavor-positive {
  color: rgba(74, 138, 58, 0.7);
}

.flavor-text.flavor-negative {
  color: rgba(160, 48, 32, 0.7);
}

.parchment-meta {
  margin-top: 10px;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary, #a09080);
  opacity: 0.7;
}

/* Overlay transition */
.detail-fade-enter-active {
  transition: opacity 0.25s ease;
}
.detail-fade-leave-active {
  transition: opacity 0.2s ease;
}
.detail-fade-enter-from,
.detail-fade-leave-to {
  opacity: 0;
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

  .detail-parchment {
    padding: 18px 16px;
    min-height: 240px;
  }

  .parchment-title {
    font-size: 1.05rem;
  }

  .parchment-desc {
    font-size: 0.82rem;
  }
}
</style>
