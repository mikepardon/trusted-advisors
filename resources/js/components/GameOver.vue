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
              >{{ kingdom.player?.character?.name || 'Player' }}</span>
              <span v-if="kingdom.player?.player_number === gameData.game.winner_player_number" class="winner-badge">WINNER</span>
            </h3>
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
            <span>{{ myXpDetails.new_xp }} / {{ xpForLevel(xpBarLevel + 1) }} XP</span>
          </div>
        </div>

        <div class="button-row">
          <button class="btn-primary play-again" @click="rematch" :disabled="rematchLoading">
            {{ rematchLoading ? 'Creating...' : 'Rematch' }}
          </button>
          <button class="play-again" @click="$router.push('/')">New Game</button>
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
        <h3>Total Score</h3>
        <div class="score">{{ totalScore }}</div>
        <p class="score-rank">{{ scoreRank }}</p>
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
          <span>{{ myXpDetails.new_xp }} / {{ xpForLevel(xpBarLevel + 1) }} XP</span>
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
      showProfileUserId: null,
      xpBarPercent: 0,
      xpBarLevel: 0,
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
      // In pass-and-play, "you" is player 1 by convention; in online, check auth
      return true; // Both players see the results
    },
    duelEndTitle() {
      const winner = this.gameData?.game?.winner_player_number;
      if (!winner) return 'The Duel is Over';
      const player = this.gameData?.game?.players?.find(p => p.player_number === winner);
      return `${player?.character?.name || 'Player ' + winner} Wins!`;
    },
    duelEndFlavor() {
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
    scoreRank() {
      if (!this.isWin) return 'The Kingdom has fallen. Better luck next time.';
      const s = this.totalScore;
      if (s >= 150) return 'Legendary - The kingdom enters a new Golden Age!';
      if (s >= 120) return 'Excellent - Your wisdom will be remembered for centuries.';
      if (s >= 90) return 'Good - The kingdom stands strong thanks to your guidance.';
      if (s >= 60) return 'Adequate - The kingdom survives, but just barely.';
      return 'Poor - The kingdom limps on, weakened by your counsel.';
    },
    endTitle() {
      if (!this.isWin) return 'The Kingdom Has Fallen';
      const s = this.totalScore;
      if (s >= 120) return 'God Save the King!';
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

      return 'Your advisors have guided the kingdom through two years of crisis. The realm celebrates, and your deeds are judged.';
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
      return Math.floor(100 * level * (level + 1) / 2);
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

      // Animate after a short delay
      setTimeout(() => {
        if (newLevel > oldLevel) {
          // Fill current bar to 100%, then reset for new level
          this.xpBarPercent = 100;
          setTimeout(() => {
            this.xpBarLevel = newLevel;
            this.xpBarPercent = 0;
            setTimeout(() => {
              const newLevelStart = this.xpForLevel(newLevel);
              const newLevelEnd = this.xpForLevel(newLevel + 1);
              const newPercent = newLevelEnd > newLevelStart
                ? ((newXp - newLevelStart) / (newLevelEnd - newLevelStart)) * 100 : 0;
              this.xpBarPercent = Math.min(100, Math.max(0, newPercent));
            }, 200);
          }, 800);
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

        // For online games, invite the other players
        if (game.game_mode === 'online' && game.players) {
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
