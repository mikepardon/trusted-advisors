<template>
  <div v-if="loading" class="loading">Loading final results...</div>
  <div v-else-if="gameData" class="game-over">
    <!-- DUEL END SCREEN -->
    <template v-if="isDuel">
      <div class="card-panel result-panel">
        <h2 class="game-over-title" :class="isDuelWinner ? 'title-win' : 'title-loss'">
          {{ duelEndTitle }}
        </h2>
        <p class="end-flavor">{{ duelEndFlavor }}</p>

        <div class="duel-kingdoms-final">
          <div
            v-for="kingdom in duelKingdoms"
            :key="kingdom.id"
            class="duel-kingdom-panel"
            :class="{ 'kingdom-winner': kingdom.player?.player_number === gameData.game.winner_player_number }"
          >
            <h3 class="kingdom-header">
              <span
                :class="{ 'clickable-name': kingdom.player?.user_id }"
                @click="kingdom.player?.user_id && (showProfileUserId = kingdom.player.user_id)"
              >{{ playerDisplayName(kingdom.player) }}</span>
              <span v-if="isBothTimeout" class="draw-badge">DRAW</span>
              <span v-else-if="kingdom.player?.player_number === gameData.game.winner_player_number" class="winner-badge">WINNER</span>
              <span v-else-if="isTimeout && kingdom.player?.player_number === timedOutPlayerNumber" class="timeout-badge">TIMED OUT</span>
            </h3>
            <div class="kingdom-sub">
              <span class="kingdom-character">{{ kingdom.player?.character?.name }}</span>
              <span v-if="playerNewElo(kingdom.player)" class="kingdom-elo">
                ELO: {{ playerNewElo(kingdom.player) }}
                <span v-if="playerEloChange(kingdom.player)" :class="playerEloChange(kingdom.player) > 0 ? 'elo-up' : 'elo-down'">
                  ({{ playerEloChange(kingdom.player) > 0 ? '+' : '' }}{{ playerEloChange(kingdom.player) }})
                </span>
              </span>
            </div>
            <div class="stats-grid">
              <div v-for="stat in stats" :key="stat.key" class="final-stat">
                <span class="stat-icon">{{ stat.icon }}</span>
                <span class="stat-label">{{ stat.label }}</span>
                <span class="stat-val" :class="getValClass(kingdom[stat.key])">
                  {{ kingdom[stat.key] }}
                </span>
              </div>
            </div>
            <div class="kingdom-score">
              Score: <strong>{{ kingdomTotal(kingdom) }}</strong>
            </div>
          </div>
        </div>

        <div class="round-summary">
          <p class="rounds-survived">
            Months Played: <strong>{{ gameData.game.current_round }}</strong> / {{ gameData.game.total_rounds }}
          </p>
        </div>

        <!-- Completion rewards -->
        <div v-if="completion" class="completion-rewards">
          <div v-if="myXp" class="reward-item reward-xp">+{{ myXp }} XP</div>
          <div v-if="myCoins" class="reward-item reward-coins">+{{ myCoins }} Coins</div>
          <div v-if="myLevelUp" class="reward-item reward-level">Level Up! Lv.{{ myLevelUp }}</div>
          <div v-if="myEloChange" class="reward-item" :class="myEloChange > 0 ? 'reward-elo-up' : 'reward-elo-down'">
            ELO {{ myEloChange > 0 ? '+' : '' }}{{ myEloChange }}
          </div>
          <div v-for="ach in myAchievements" :key="ach.id" class="reward-item reward-achievement">
            {{ ach.name }}
          </div>
          <div v-if="completion.challenge_completed" class="reward-item reward-challenge">
            Challenge Complete: {{ completion.challenge_completed.title }} (+{{ completion.challenge_completed.reward_xp }} XP)
          </div>
        </div>

        <!-- XP Progress Bar -->
        <div v-if="myXpDetails" class="xp-progress-section">
          <div class="xp-progress-header">
            <span class="xp-level-label">Level {{ xpBarLevel }}</span>
            <span class="xp-amount">+{{ myXp }} XP</span>
          </div>
          <div class="xp-progress-track">
            <div class="xp-progress-fill" :style="{ width: xpBarPercent + '%' }"></div>
          </div>
          <div class="xp-progress-footer">
            <span>{{ xpBarDisplayXp }} / {{ xpForLevel(xpBarLevel + 1) }} XP</span>
          </div>
          <transition name="levelup-pop">
            <div v-if="showLevelUp" class="level-up-banner">
              <span class="level-up-star">&#11088;</span>
              <span class="level-up-text">Level {{ myXpDetails.new_level }}!</span>
              <span class="level-up-star">&#11088;</span>
            </div>
          </transition>
          <div v-if="myUnlocks.length && showLevelUp" class="new-unlocks">
            <div v-for="unlock in myUnlocks" :key="unlock.id" class="unlock-item">
              <span class="unlock-icon">&#127381;</span>
              <span class="unlock-name">{{ unlock.name }}</span>
              <button class="btn-unlock-claim" @click="$router.push('/shop')">View</button>
            </div>
          </div>
        </div>

        <div class="button-row">
          <button class="btn-primary play-again" @click="rematch" :disabled="rematchLoading">
            {{ rematchLoading ? 'Creating...' : 'Rematch' }}
          </button>
          <button class="play-again" @click="$router.push('/')">New Game</button>
          <button class="play-again share-btn" @click="shareReplay">
            {{ shareCopied ? 'Copied!' : 'Share Replay' }}
          </button>
        </div>
      </div>
    </template>

    <!-- COOPERATIVE END SCREEN (single / pass_and_play / online) -->
    <template v-else>
    <div class="card-panel result-panel">
      <h2 class="game-over-title" :class="isWin ? 'title-win' : 'title-loss'">
        {{ endTitle }}
      </h2>
      <p class="end-flavor">{{ endFlavor }}</p>

      <div class="final-stats">
        <h3>Final Kingdom Status</h3>
        <div class="stats-grid">
          <div v-for="stat in stats" :key="stat.key" class="final-stat">
            <span class="stat-icon">{{ stat.icon }}</span>
            <span class="stat-label">{{ stat.label }}</span>
            <span class="stat-val" :class="getValClass(gameData.game[stat.key])">
              {{ gameData.game[stat.key] }}
            </span>
          </div>
        </div>
      </div>

      <div class="total-score">
        <h3>Final Score</h3>
        <div class="score">{{ finalScore }}</div>
        <p class="score-rank">{{ scoreRank }}</p>
        <div v-if="scoreBreakdown" class="score-breakdown">
          <div class="breakdown-row">
            <span>Kingdom Total</span>
            <span>{{ scoreBreakdown.base_score }}</span>
          </div>
          <div class="breakdown-row">
            <span>Year Multiplier</span>
            <span>&times;{{ scoreBreakdown.year_multiplier }}</span>
          </div>
          <div class="breakdown-row">
            <span>Balance Bonus</span>
            <span>+{{ scoreBreakdown.balance_bonus }}</span>
          </div>
          <div v-if="scoreBreakdown.bonus_score" class="breakdown-row">
            <span>Bonus Score</span>
            <span>+{{ scoreBreakdown.bonus_score }}</span>
          </div>
          <div class="breakdown-row breakdown-total">
            <span>Final Score</span>
            <span>{{ scoreBreakdown.final_score }}</span>
          </div>
        </div>
      </div>

      <div class="round-summary">
        <p class="rounds-survived">
          Months Survived: <strong>{{ gameData.game.current_round }}</strong> / {{ gameData.game.total_rounds }}
        </p>
      </div>

      <div class="advisors-section">
        <h3>Your Advisors</h3>
        <div v-for="player in gameData.game.players" :key="player.id" class="advisor">
          <strong
            :class="{ 'clickable-name': player.user_id }"
            @click="player.user_id && (showProfileUserId = player.user_id)"
          >{{ player.character.name }}</strong>
          <div v-if="player.items && player.items.length" class="advisor-items">
            <span
              v-for="pi in player.items"
              :key="pi.id"
              class="advisor-item-tag"
              :class="pi.is_cursed ? 'tag-cursed' : 'tag-normal'"
            >
              {{ pi.is_cursed ? '\u{1F480} ' : '' }}{{ pi.item.name }}
            </span>
          </div>
        </div>
      </div>

      <div class="summary">
        <p v-if="isWin">
          After two years of counsel,
          your wisdom has guided the kingdom through its darkest hours.
          The Kingdom endures thanks to your leadership!
        </p>
        <p v-else>
          After {{ gameData.game.current_round }} months,
          the kingdom could no longer sustain itself.
          Perhaps next time, balance will be maintained.
        </p>
      </div>

      <!-- Completion rewards -->
      <div v-if="completion" class="completion-rewards">
        <div v-if="myXp" class="reward-item reward-xp">+{{ myXp }} XP</div>
        <div v-if="myCoins" class="reward-item reward-coins">+{{ myCoins }} Coins</div>
        <div v-if="myLevelUp" class="reward-item reward-level">Level Up! Lv.{{ myLevelUp }}</div>
        <div v-for="ach in myAchievements" :key="ach.id" class="reward-item reward-achievement">
          {{ ach.name }}
        </div>
        <div v-if="completion.challenge_completed" class="reward-item reward-challenge">
          Challenge Complete: {{ completion.challenge_completed.title }} (+{{ completion.challenge_completed.reward_xp }} XP)
        </div>
      </div>

      <!-- XP Progress Bar -->
      <div v-if="myXpDetails" class="xp-progress-section">
        <div class="xp-progress-header">
          <span class="xp-level-label">Level {{ xpBarLevel }}</span>
          <span class="xp-amount">+{{ myXp }} XP</span>
        </div>
        <div class="xp-progress-track">
          <div class="xp-progress-fill" :style="{ width: xpBarPercent + '%' }"></div>
        </div>
        <div class="xp-progress-footer">
          <span>{{ xpBarDisplayXp }} / {{ xpForLevel(xpBarLevel + 1) }} XP</span>
        </div>
        <transition name="levelup-pop">
          <div v-if="showLevelUp" class="level-up-banner">
            <span class="level-up-star">&#11088;</span>
            <span class="level-up-text">Level {{ myXpDetails.new_level }}!</span>
            <span class="level-up-star">&#11088;</span>
          </div>
        </transition>
        <div v-if="myUnlocks.length && showLevelUp" class="new-unlocks">
          <div v-for="unlock in myUnlocks" :key="unlock.id" class="unlock-item">
            <span class="unlock-icon">&#127381;</span>
            <span class="unlock-name">{{ unlock.name }}</span>
            <button class="btn-unlock-claim" @click="$router.push('/shop')">View</button>
          </div>
        </div>
      </div>

      <div class="button-row">
        <template v-if="isSinglePlayer">
          <button class="play-again" @click="$router.push('/')">Home</button>
          <button class="btn-primary play-again" @click="rematch" :disabled="rematchLoading">
            {{ rematchLoading ? 'Creating...' : 'New Game' }}
          </button>
        </template>
        <template v-else>
          <button class="btn-primary play-again" @click="rematch" :disabled="rematchLoading">
            {{ rematchLoading ? 'Creating...' : 'Rematch' }}
          </button>
          <button class="play-again" @click="$router.push('/')">New Game</button>
        </template>
        <button class="play-again share-btn" @click="shareReplay">
          {{ shareCopied ? 'Copied!' : 'Share Replay' }}
        </button>
      </div>
    </div>
    </template>

    <PlayerProfile v-if="showProfileUserId" :userId="showProfileUserId" @close="showProfileUserId = null" />
  </div>
</template>

<script>
import axios from 'axios';
import { playSound } from '../sounds';
import { useAuth } from '../stores/auth';
import PlayerProfile from './PlayerProfile.vue';

export default {
  name: 'GameOver',
  components: { PlayerProfile },
  props: {
    id: { type: [String, Number], required: true },
  },
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      gameData: null,
      completion: null,
      loading: true,
      rematchLoading: false,
      shareCopied: false,
      showProfileUserId: null,
      timedOutPlayerNumber: null,
      xpBarPercent: 0,
      xpBarLevel: 0,
      xpBarDisplayXp: 0,
      showLevelUp: false,
      stats: [
        { key: 'wealth', label: 'Wealth', icon: '\u{1FA99}' },
        { key: 'influence', label: 'Influence', icon: '\u{1F3DB}' },
        { key: 'security', label: 'Security', icon: '\u{1F6E1}' },
        { key: 'religion', label: 'Religion', icon: '\u{1F54C}' },
        { key: 'food', label: 'Food', icon: '\u{1F33E}' },
        { key: 'happiness', label: 'Happiness', icon: '\u{1F3AD}' },
      ],
    };
  },
  computed: {
    isSinglePlayer() {
      return this.gameData?.game?.game_mode === 'single';
    },
    isDuel() {
      return this.gameData?.game?.game_type === 'duel' || this.gameData?.game_type === 'duel';
    },
    duelKingdoms() {
      return this.gameData?.player_kingdoms || [];
    },
    isDuelWinner() {
      if (!this.isDuel) return false;
      if (this.isBothTimeout) return false;
      if (this.didITimeout) return false;
      if (this.isTimeout && !this.didITimeout) return true;
      const winner = this.gameData?.game?.winner_player_number;
      if (!winner || !this.myPlayerNumber) return true;
      return winner === this.myPlayerNumber;
    },
    isTimeout() {
      return this.timedOutPlayerNumber != null && this.timedOutPlayerNumber !== null;
    },
    isBothTimeout() {
      return this.timedOutPlayerNumber === 0;
    },
    myPlayerNumber() {
      const userId = this.auth.state.user?.id;
      const myPlayer = this.gameData?.game?.players?.find(p => p.user_id === userId);
      return myPlayer?.player_number || null;
    },
    didITimeout() {
      if (!this.isTimeout || !this.myPlayerNumber) return false;
      if (this.isBothTimeout) return true;
      return this.timedOutPlayerNumber === this.myPlayerNumber;
    },
    duelEndTitle() {
      if (this.isBothTimeout) return 'Draw — Both Timed Out';
      if (this.isTimeout) {
        if (this.didITimeout) return 'You Timed Out!';
        const winner = this.gameData?.game?.winner_player_number;
        const player = this.gameData?.game?.players?.find(p => p.player_number === winner);
        const name = player?.user?.name || player?.character?.name || 'Player ' + winner;
        return `${name} Wins!`;
      }
      const winner = this.gameData?.game?.winner_player_number;
      if (!winner) return 'The Duel is Over';
      const player = this.gameData?.game?.players?.find(p => p.player_number === winner);
      const name = player?.user?.name || player?.character?.name || 'Player ' + winner;
      return `${name} Wins!`;
    },
    duelEndFlavor() {
      if (this.isBothTimeout) {
        return 'Neither ruler could act in time. The kingdoms stand in uneasy stalemate.';
      }
      if (this.isTimeout) {
        if (this.didITimeout) {
          return 'You ran out of time. Your opponent claims victory by forfeit.';
        }
        return 'Your opponent ran out of time. Victory is yours by forfeit!';
      }
      const winner = this.gameData?.game?.winner_player_number;
      if (!winner) return 'The campaign has ended.';
      const loser = winner === 1 ? 2 : 1;
      const winKingdom = this.duelKingdoms.find(k => k.player?.player_number === winner);
      const loseKingdom = this.duelKingdoms.find(k => k.player?.player_number === loser);

      if (loseKingdom) {
        const stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        const collapsed = stats.find(s => loseKingdom[s] <= 0);
        if (collapsed) {
          return `The rival kingdom collapsed when ${collapsed} reached zero.`;
        }
        const atMax = stats.filter(s => winKingdom && winKingdom[s] >= 20).length;
        if (atMax >= 3) {
          return 'Three pillars of the kingdom reached their zenith. A decisive victory!';
        }
      }
      return 'After a long campaign, the stronger kingdom prevails.';
    },
    isWin() {
      return this.gameData?.game?.win === true;
    },
    totalScore() {
      if (!this.gameData) return 0;
      const g = this.gameData.game;
      return g.wealth + g.influence + g.security + g.religion + g.food + g.happiness;
    },
    scoreBreakdown() {
      // Use stored breakdown from the game-over response, or compute client-side
      const stored = this.gameData?._scoreBreakdown;
      if (stored) return stored;
      // Compute from game data
      const g = this.gameData?.game;
      if (!g) return null;
      const base = g.wealth + g.influence + g.security + g.religion + g.food + g.happiness;
      const stats = [g.wealth, g.influence, g.security, g.religion, g.food, g.happiness];
      const spread = Math.max(...stats) - Math.min(...stats);
      const balanceBonus = Math.max(0, 30 - spread * 3);
      const years = Math.floor((g.total_rounds || 24) / 12);
      const multipliers = { 1: 1.0, 2: 1.4, 3: 1.7, 4: 1.9, 5: 2.0 };
      const yearMult = multipliers[years] || 1.0;
      const bonusScore = g.bonus_score || 0;
      const finalScore = g.final_score ?? (Math.floor(base * yearMult) + balanceBonus + bonusScore);
      return { base_score: base, year_multiplier: yearMult, balance_bonus: balanceBonus, bonus_score: bonusScore, final_score: finalScore };
    },
    finalScore() {
      return this.scoreBreakdown?.final_score ?? this.totalScore;
    },
    scoreRank() {
      if (!this.isWin) return 'The Kingdom has fallen. Better luck next time.';
      const s = this.finalScore;
      if (s >= 200) return 'Legendary - The kingdom enters a new Golden Age!';
      if (s >= 150) return 'Excellent - Your wisdom will be remembered for centuries.';
      if (s >= 100) return 'Good - The kingdom stands strong thanks to your guidance.';
      if (s >= 60) return 'Adequate - The kingdom survives, but just barely.';
      return 'Poor - The kingdom limps on, weakened by your counsel.';
    },
    endTitle() {
      if (!this.isWin) return 'The Kingdom Has Fallen';
      const s = this.finalScore;
      if (s >= 150) return 'God Save the King!';
      if (s >= 60) return 'The Kingdom Endures';
      return 'A Narrow Survival';
    },
    endFlavor() {
      const g = this.gameData?.game;
      if (!g) return '';

      if (!this.isWin) {
        const tooLow = this.stats.find(s => g[s.key] <= 0);
        if (tooLow) {
          return `The kingdom collapsed when ${tooLow.label.toLowerCase()} reached zero. The people lost faith in their advisors.`;
        }
        return 'The campaign has ended in defeat.';
      }

      const years = Math.max(1, Math.floor((g.total_rounds || 24) / 12));
      const yearWord = years === 1 ? 'one year' : years === 2 ? 'two years' : years === 3 ? 'three years' : years === 4 ? 'four years' : 'five years';
      return `Your advisors have guided the kingdom through ${yearWord} of crisis. The realm celebrates, and your deeds are judged.`;
    },
    myXp() {
      if (!this.completion?.xp_awards) return null;
      const vals = Object.values(this.completion.xp_awards);
      return vals.length > 0 ? vals[0] : null;
    },
    myLevelUp() {
      if (!this.completion?.level_ups) return null;
      const vals = Object.values(this.completion.level_ups);
      return vals.length > 0 ? vals[0] : null;
    },
    myEloChange() {
      if (!this.completion?.elo_changes) return null;
      const vals = Object.values(this.completion.elo_changes);
      return vals.length > 0 ? vals[0]?.change : null;
    },
    myAchievements() {
      if (!this.completion?.achievements_unlocked) return [];
      const vals = Object.values(this.completion.achievements_unlocked);
      return vals.length > 0 ? vals[0] : [];
    },
    myXpDetails() {
      if (!this.completion?.xp_details) return null;
      const vals = Object.values(this.completion.xp_details);
      return vals.length > 0 ? vals[0] : null;
    },
    myCoins() {
      if (!this.completion?.coin_awards) return null;
      const vals = Object.values(this.completion.coin_awards);
      return vals.length > 0 ? vals[0]?.coins : null;
    },
    myUnlocks() {
      if (!this.completion?.new_unlocks) return [];
      const vals = Object.values(this.completion.new_unlocks);
      return vals.length > 0 ? vals[0] : [];
    },
  },
  async mounted() {
    try {
      const res = await axios.get(`/api/games/${this.id}`);
      this.gameData = res.data;

      // Load completion data from sessionStorage (set by GameBoard/DuelBoard)
      const stored = sessionStorage.getItem(`game_completion_${this.id}`);
      if (stored) {
        this.completion = JSON.parse(stored);
        sessionStorage.removeItem(`game_completion_${this.id}`);
      }

      // Load timeout data from sessionStorage or game API
      const storedTimeout = sessionStorage.getItem(`game_timeout_${this.id}`);
      if (storedTimeout) {
        this.timedOutPlayerNumber = JSON.parse(storedTimeout);
        sessionStorage.removeItem(`game_timeout_${this.id}`);
      } else if (this.gameData?.timed_out_player_number != null) {
        this.timedOutPlayerNumber = this.gameData.timed_out_player_number;
      } else if (this.gameData?.game?.timed_out_player_number != null) {
        this.timedOutPlayerNumber = this.gameData.game.timed_out_player_number;
      }

      // Load score breakdown from sessionStorage
      const storedBreakdown = sessionStorage.getItem(`game_score_breakdown_${this.id}`);
      if (storedBreakdown) {
        this.gameData._scoreBreakdown = JSON.parse(storedBreakdown);
        sessionStorage.removeItem(`game_score_breakdown_${this.id}`);
      }

      this.$nextTick(() => {
        if (this.isDuel) {
          playSound('win');
        } else if (this.isWin) {
          playSound('win');
        } else {
          playSound('totalLoss');
        }
        // Animate XP bar
        this.animateXpBar();
        // Update auth store with new stats
        if (this.myXpDetails) {
          const coinAwards = this.completion?.coin_awards ? Object.values(this.completion.coin_awards) : [];
          const newCoins = coinAwards.length > 0 ? coinAwards[0]?.new_coins : undefined;
          this.auth.updateUserStats({
            xp: this.myXpDetails.new_xp,
            level: this.myXpDetails.new_level,
            coins: newCoins,
          });
        }
      });
    } catch (e) {
      alert('Failed to load results');
    }
    this.loading = false;
  },
  methods: {
    playerDisplayName(player) {
      if (player?.user?.name) return player.user.name;
      return player?.character?.name || 'Player';
    },
    playerNewElo(player) {
      if (!this.completion?.elo_changes || !player?.user_id) return null;
      const elo = this.completion.elo_changes[player.user_id];
      return elo?.new ?? null;
    },
    playerEloChange(player) {
      if (!this.completion?.elo_changes || !player?.user_id) return null;
      const elo = this.completion.elo_changes[player.user_id];
      return elo?.change ?? null;
    },
    getValClass(val) {
      if (val <= 3) return 'val-critical';
      if (val <= 7) return 'val-danger';
      if (val <= 12) return 'val-low';
      if (val >= 18) return 'val-high';
      return 'val-normal';
    },
    kingdomTotal(k) {
      return (k.wealth || 0) + (k.influence || 0) + (k.security || 0)
        + (k.religion || 0) + (k.food || 0) + (k.happiness || 0);
    },
    xpForLevel(level) {
      return Math.floor(100 * (level - 1) * level / 2);
    },
    animateXpBar() {
      const d = this.myXpDetails;
      if (!d) return;
      const oldXp = d.old_xp;
      const newXp = d.new_xp;
      const oldLevel = d.old_level;
      const newLevel = d.new_level;

      // Start at old position
      const oldLevelStart = this.xpForLevel(oldLevel);
      const oldLevelEnd = this.xpForLevel(oldLevel + 1);
      const oldPercent = oldLevelEnd > oldLevelStart
        ? ((oldXp - oldLevelStart) / (oldLevelEnd - oldLevelStart)) * 100 : 0;

      this.xpBarLevel = oldLevel;
      this.xpBarPercent = Math.min(100, Math.max(0, oldPercent));
      this.xpBarDisplayXp = oldXp;
      this.showLevelUp = false;

      // Animate the XP counter
      this.animateXpCounter(oldXp, newXp, newLevel > oldLevel ? 1200 : 1600);

      // Animate after a short delay
      setTimeout(() => {
        if (newLevel > oldLevel) {
          // Fill current bar to 100%, then reset for new level
          this.xpBarPercent = 100;
          setTimeout(() => {
            this.xpBarLevel = newLevel;
            this.xpBarPercent = 0;
            this.showLevelUp = true;
            playSound('win');
            setTimeout(() => {
              const newLevelStart = this.xpForLevel(newLevel);
              const newLevelEnd = this.xpForLevel(newLevel + 1);
              const newPercent = newLevelEnd > newLevelStart
                ? ((newXp - newLevelStart) / (newLevelEnd - newLevelStart)) * 100 : 0;
              this.xpBarPercent = Math.min(100, Math.max(0, newPercent));
            }, 300);
          }, 1000);
        } else {
          // Same level, just animate to new position
          const levelStart = this.xpForLevel(oldLevel);
          const levelEnd = this.xpForLevel(oldLevel + 1);
          const newPercent = levelEnd > levelStart
            ? ((newXp - levelStart) / (levelEnd - levelStart)) * 100 : 0;
          this.xpBarPercent = Math.min(100, Math.max(0, newPercent));
        }
      }, 600);
    },
    animateXpCounter(from, to, duration) {
      const start = performance.now();
      const step = (now) => {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        // Ease-out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        this.xpBarDisplayXp = Math.round(from + (to - from) * eased);
        if (progress < 1) {
          requestAnimationFrame(step);
        }
      };
      requestAnimationFrame(step);
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
    async rematch() {
      this.rematchLoading = true;
      try {
        const game = this.gameData.game;
        const res = await axios.post('/api/games', {
          game_mode: game.game_mode,
          game_type: game.game_type || 'cooperative',
          num_players: game.num_players || game.players?.length || 1,
          total_rounds: game.total_rounds,
        });
        const newGameId = res.data.id;

        if (game.game_mode === 'online') {
          // For online games, invite the other players and go to lobby
          if (game.players) {
            const currentUserId = this.auth.state.user?.id;
            const opponents = game.players.filter(p => p.user_id && p.user_id !== currentUserId);
            for (const opp of opponents) {
              try {
                await axios.post(`/api/games/${newGameId}/invite`, { user_id: opp.user_id });
              } catch {
                // Invite may fail if user blocked, etc. - continue
              }
            }
          }
          this.$router.push(`/game/${newGameId}`);
        } else {
          // For single/pass_and_play: reuse same characters and start immediately
          const currentUserId = this.auth.state.user?.id;
          const myPlayer = game.players?.find(p => p.user_id === currentUserId) || game.players?.[0];
          const botPlayer = game.players?.find(p => p.is_bot);

          let characterIds;
          if (game.game_type === 'duel' && game.game_mode === 'single') {
            // Single-player duel: only send the human's character
            characterIds = [myPlayer?.character_id].filter(Boolean);
          } else {
            // Cooperative or pass-and-play: send all non-bot characters in order
            characterIds = (game.players || [])
              .filter(p => !p.is_bot)
              .sort((a, b) => a.player_number - b.player_number)
              .map(p => p.character_id)
              .filter(Boolean);
          }

          if (characterIds.length) {
            const startPayload = { characters: characterIds };
            if (botPlayer?.bot_difficulty) {
              startPayload.bot_difficulty = botPlayer.bot_difficulty;
            }
            await axios.post(`/api/games/${newGameId}/start`, startPayload);
          }

          this.$router.push(`/game/${newGameId}`);
        }
      } catch {
        alert('Failed to create rematch.');
      }
      this.rematchLoading = false;
    },
  },
};
</script>

<style scoped>
.loading {
  text-align: center;
  padding: 60px;
  color: var(--text-secondary);
}

.game-over {
  max-width: 700px;
  margin: 0 auto;
}

.result-panel {
  text-align: center;
}

.game-over-title {
  font-family: 'Cinzel', serif;
  font-size: 2.5rem;
  margin-bottom: 10px;
}

.title-win { color: var(--accent-gold); }
.title-loss { color: var(--accent-red); }

.end-flavor {
  font-style: italic;
  color: var(--text-secondary);
  margin-bottom: 25px;
  font-size: 1.1rem;
  line-height: 1.5;
}

.final-stats h3,
.total-score h3,
.advisors-section h3 {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  margin-bottom: 12px;
  font-size: 1.1rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 25px;
}

.final-stat {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
}

.stat-label {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.stat-val {
  font-weight: 700;
  font-size: 1.1rem;
}

.val-critical { color: #e74c3c; }
.val-danger { color: #e67e22; }
.val-low { color: #d4a843; }
.val-normal { color: var(--text-bright); }
.val-high { color: #27ae60; }

.total-score {
  margin-bottom: 25px;
}

.score {
  font-family: 'Cinzel', serif;
  font-size: 3.5rem;
  color: var(--accent-gold);
  font-weight: 900;
}

.score-rank {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 1.1rem;
  margin-top: 5px;
}

.score-breakdown {
  margin-top: 12px;
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(184, 148, 46, 0.2);
  border-radius: 8px;
  padding: 12px 16px;
  text-align: left;
  display: inline-block;
  min-width: 220px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  padding: 3px 0;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.breakdown-row span:last-child {
  color: var(--text-bright);
  font-weight: 600;
}

.breakdown-total {
  border-top: 1px solid rgba(184, 148, 46, 0.3);
  margin-top: 4px;
  padding-top: 6px;
  font-size: 1rem;
}

.breakdown-total span:last-child {
  color: var(--accent-gold);
  font-weight: 900;
}

.round-summary {
  margin-bottom: 20px;
}

.rounds-survived {
  font-size: 1.1rem;
  color: var(--text-primary);
}

.rounds-survived strong {
  color: var(--accent-gold);
  font-size: 1.3rem;
}

.advisors-section {
  margin-bottom: 25px;
}

.advisor {
  color: var(--text-primary);
  margin-bottom: 5px;
}

.advisor strong {
  color: var(--accent-gold);
}

.clickable-name {
  cursor: pointer;
}

.clickable-name:hover {
  text-decoration: underline;
}

.advisor-items {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.advisor-item-tag {
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 4px;
}

.tag-normal {
  background: rgba(74, 138, 58, 0.15);
  color: #4a8a3a;
}

.tag-cursed {
  background: rgba(160, 48, 32, 0.2);
  color: #c0392b;
}

.summary {
  color: var(--text-secondary);
  font-style: italic;
  margin-bottom: 25px;
  line-height: 1.5;
}

.button-row {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.play-again {
  font-size: 1.1rem;
  padding: 12px 36px;
}

.share-btn {
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
  border: 1px solid rgba(67, 160, 212, 0.3);
}

.share-btn:hover {
  background: rgba(67, 160, 212, 0.25);
  border-color: rgba(67, 160, 212, 0.5);
}

/* Completion rewards */
.completion-rewards {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
  margin-bottom: 20px;
}

.reward-item {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  animation: rewardPop 0.4s ease;
}

.reward-xp {
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 67, 0.4);
}

.reward-coins {
  background: rgba(255, 200, 50, 0.2);
  color: #f0c030;
  border: 1px solid rgba(255, 200, 50, 0.4);
}

.reward-level {
  background: rgba(74, 138, 58, 0.2);
  color: #6abf50;
  border: 1px solid rgba(74, 138, 58, 0.4);
}

.reward-elo-up {
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
  border: 1px solid rgba(67, 160, 212, 0.3);
}

.reward-elo-down {
  background: rgba(160, 48, 32, 0.15);
  color: #d05040;
  border: 1px solid rgba(160, 48, 32, 0.3);
}

.reward-achievement {
  background: rgba(180, 130, 255, 0.15);
  color: #b482ff;
  border: 1px solid rgba(180, 130, 255, 0.3);
}

.reward-challenge {
  background: rgba(74, 138, 58, 0.15);
  color: #6abf50;
  border: 1px solid rgba(74, 138, 58, 0.3);
}

@keyframes rewardPop {
  0% { transform: scale(0.5); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

/* XP Progress Bar */
.xp-progress-section {
  max-width: 400px;
  margin: 0 auto 24px;
}

.xp-progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.xp-level-label {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  font-weight: 700;
}

.xp-amount {
  font-size: 0.85rem;
  color: var(--accent-gold);
  font-weight: 600;
}

.xp-progress-track {
  height: 14px;
  background: rgba(0, 0, 0, 0.4);
  border-radius: 7px;
  overflow: hidden;
  border: 1px solid rgba(138, 106, 46, 0.3);
}

.xp-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843, #e8c468);
  border-radius: 7px;
  transition: width 1s ease-in-out;
  box-shadow: 0 0 8px rgba(212, 168, 67, 0.4);
}

.xp-progress-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 4px;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

/* Level-up banner */
.level-up-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 12px;
  padding: 10px;
  background: linear-gradient(135deg, rgba(212, 168, 67, 0.2), rgba(232, 196, 104, 0.1));
  border: 2px solid var(--accent-gold);
  border-radius: 8px;
  animation: levelGlow 1.5s ease-in-out infinite alternate;
}

@keyframes levelGlow {
  from { box-shadow: 0 0 8px rgba(212, 168, 67, 0.3); }
  to { box-shadow: 0 0 24px rgba(212, 168, 67, 0.5); }
}

.level-up-star {
  font-size: 1.3rem;
  animation: starSpin 1s ease-in-out;
}

@keyframes starSpin {
  0% { transform: scale(0) rotate(-180deg); }
  60% { transform: scale(1.3) rotate(10deg); }
  100% { transform: scale(1) rotate(0deg); }
}

.level-up-text {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  font-weight: 900;
  color: var(--accent-gold);
  letter-spacing: 2px;
}

.levelup-pop-enter-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}

.levelup-pop-enter-from {
  opacity: 0;
  transform: scale(0.5);
}

/* New unlocks */
.new-unlocks {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.unlock-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: rgba(180, 130, 255, 0.1);
  border: 1px solid rgba(180, 130, 255, 0.3);
  border-radius: 6px;
  animation: rewardPop 0.5s ease;
}

.unlock-icon {
  font-size: 1.1rem;
}

.unlock-name {
  flex: 1;
  font-family: 'Cinzel', serif;
  color: #b482ff;
  font-size: 0.9rem;
  font-weight: 600;
}

.btn-unlock-claim {
  padding: 4px 14px;
  font-size: 0.75rem;
  background: rgba(180, 130, 255, 0.2);
  border: 1px solid rgba(180, 130, 255, 0.4);
  color: #b482ff;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
}

.btn-unlock-claim:hover {
  background: rgba(180, 130, 255, 0.35);
}

/* Duel end screen */
.duel-kingdoms-final {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 25px;
}

.duel-kingdom-panel {
  background: rgba(26, 18, 9, 0.5);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 16px;
  text-align: center;
}

.duel-kingdom-panel.kingdom-winner {
  border-color: var(--accent-gold);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.25);
}

.kingdom-header {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 12px;
}

.kingdom-sub {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 8px;
}

.kingdom-character {
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-style: italic;
}

.kingdom-elo {
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.elo-up {
  color: #4a8a3a;
  font-weight: 700;
}

.elo-down {
  color: #d05040;
  font-weight: 700;
}

.winner-badge {
  display: inline-block;
  background: linear-gradient(180deg, #b8942e, #8a6a14);
  color: #1a1209;
  font-size: 0.6rem;
  padding: 2px 8px;
  border-radius: 3px;
  margin-left: 8px;
  vertical-align: middle;
  letter-spacing: 1px;
}

.timeout-badge {
  display: inline-block;
  background: linear-gradient(180deg, #a03020, #7a2018);
  color: #fdd;
  font-size: 0.6rem;
  padding: 2px 8px;
  border-radius: 3px;
  margin-left: 8px;
  vertical-align: middle;
  letter-spacing: 1px;
}

.draw-badge {
  display: inline-block;
  background: rgba(150, 150, 150, 0.3);
  color: var(--text-secondary);
  font-size: 0.6rem;
  padding: 2px 8px;
  border-radius: 3px;
  margin-left: 8px;
  vertical-align: middle;
  letter-spacing: 1px;
}

.kingdom-score {
  margin-top: 12px;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.kingdom-score strong {
  color: var(--accent-gold);
  font-size: 1.3rem;
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .game-over-title {
    font-size: 1.8rem;
  }

  .end-flavor {
    font-size: 0.95rem;
    margin-bottom: 18px;
  }

  .score {
    font-size: 2.5rem;
  }

  .score-rank {
    font-size: 0.95rem;
  }

  .stats-grid {
    gap: 6px;
  }

  .stat-label {
    font-size: 0.8rem;
  }

  .stat-val {
    font-size: 1rem;
  }

  .result-panel {
    padding: 16px 12px;
  }

  .final-stats h3,
  .total-score h3,
  .advisors-section h3 {
    font-size: 0.95rem;
  }

  .rounds-survived {
    font-size: 0.95rem;
  }

  .duel-kingdoms-final {
    grid-template-columns: 1fr;
    gap: 10px;
  }
}
</style>
