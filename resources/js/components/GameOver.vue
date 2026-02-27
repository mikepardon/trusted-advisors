<template>
  <div v-if="loading" class="loading">Loading final results...</div>
  <div v-else-if="gameData" class="game-over">
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
          <strong>{{ player.character.name }}</strong>
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

      <button class="btn-primary play-again" @click="$router.push('/')">
        Play Again
      </button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { playSound } from '../sounds';

export default {
  name: 'GameOver',
  props: {
    id: { type: [String, Number], required: true },
  },
  data() {
    return {
      gameData: null,
      loading: true,
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
  },
  async mounted() {
    try {
      const res = await axios.get(`/api/games/${this.id}`);
      this.gameData = res.data;
      this.$nextTick(() => {
        if (this.isWin) {
          playSound('win');
        } else {
          playSound('totalLoss');
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

.play-again {
  font-size: 1.3rem;
  padding: 14px 50px;
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

  .play-again {
    font-size: 1.1rem;
    padding: 12px 36px;
  }

  .final-stats h3,
  .total-score h3,
  .advisors-section h3 {
    font-size: 0.95rem;
  }

  .rounds-survived {
    font-size: 0.95rem;
  }
}
</style>
