<template>
  <div>
    <h2 class="page-title">Coin Rewards</h2>

    <div v-if="loading" class="loading">Loading...</div>

    <template v-else>
      <!-- Editable Config Table -->
      <div class="section-panel">
        <h3 class="section-title">Coin Award Settings</h3>
        <p class="section-desc">How coins are earned at the end of each game. Changes save automatically.</p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Source</th>
              <th>Description</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="name-col">Base Coins</td>
              <td class="desc-col">Awarded to every player who completes a game</td>
              <td><input type="number" v-model.number="config.base_coins" class="coin-input" min="0" @change="saveConfig" /></td>
            </tr>
            <tr>
              <td class="name-col">Win Bonus (Cooperative)</td>
              <td class="desc-col">Extra coins for winning a cooperative game</td>
              <td><input type="number" v-model.number="config.coop_win_bonus" class="coin-input" min="0" @change="saveConfig" /></td>
            </tr>
            <tr>
              <td class="name-col">Win Bonus (Duel)</td>
              <td class="desc-col">Extra coins for winning a duel game</td>
              <td><input type="number" v-model.number="config.duel_win_bonus" class="coin-input" min="0" @change="saveConfig" /></td>
            </tr>
            <tr>
              <td class="name-col">Online Multiplier</td>
              <td class="desc-col">Multiplier applied to all coins for online games</td>
              <td><input type="number" v-model.number="config.online_multiplier" class="coin-input" min="1" max="5" step="0.1" @change="saveConfig" /></td>
            </tr>
          </tbody>
        </table>
        <p v-if="saved" class="saved-msg">Saved!</p>

        <!-- Computed Awards -->
        <h4 class="sub-title">Computed Awards</h4>
        <table class="admin-table compact">
          <thead>
            <tr><th>Scenario</th><th>Offline</th><th>Online</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Loss (any mode)</td>
              <td>{{ config.base_coins }} coins</td>
              <td>{{ Math.round(config.base_coins * config.online_multiplier) }} coins</td>
            </tr>
            <tr>
              <td>Cooperative Win</td>
              <td>{{ config.base_coins + config.coop_win_bonus }} coins</td>
              <td>{{ Math.round((config.base_coins + config.coop_win_bonus) * config.online_multiplier) }} coins</td>
            </tr>
            <tr>
              <td>Duel Win</td>
              <td>{{ config.base_coins + config.duel_win_bonus }} coins</td>
              <td>{{ Math.round((config.base_coins + config.duel_win_bonus) * config.online_multiplier) }} coins</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminCoins',
  data() {
    return {
      loading: true,
      saved: false,
      config: {
        base_coins: 10,
        coop_win_bonus: 15,
        duel_win_bonus: 25,
        online_multiplier: 1.5,
      },
    };
  },
  async mounted() {
    await this.loadConfig();
    this.loading = false;
  },
  methods: {
    async loadConfig() {
      try {
        const res = await axios.get('/api/admin/rules');
        if (res.data.coin_config) {
          this.config = { ...this.config, ...res.data.coin_config };
        }
      } catch {
        // use defaults
      }
    },
    async saveConfig() {
      try {
        await axios.put('/api/admin/rules/coin_config', { value: { ...this.config } });
        this.saved = true;
        setTimeout(() => { this.saved = false; }, 1500);
      } catch {
        // silently fail
      }
    },
  },
};
</script>

<style scoped>
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); text-align: center; margin-bottom: 24px; font-size: 1.6rem; }

.section-panel {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}

.section-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1rem; margin-bottom: 8px; }
.sub-title { font-family: 'Cinzel', serif; color: var(--text-secondary); font-size: 0.85rem; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 1px; }
.section-desc { color: var(--text-secondary); font-size: 0.85rem; font-style: italic; margin-bottom: 14px; }
.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.admin-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.admin-table th, .admin-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid rgba(184, 148, 46, 0.2); }
.admin-table th { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
.admin-table.compact td { padding: 8px 12px; }
.admin-table tbody tr:hover { background: rgba(212, 168, 67, 0.05); }

.name-col { color: var(--text-bright); font-weight: 600; white-space: nowrap; }
.desc-col { color: var(--text-secondary); }

.coin-input {
  width: 90px;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--accent-gold);
  padding: 6px 10px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
  text-align: center;
}
.coin-input:focus { outline: none; border-color: var(--accent-gold); }

.saved-msg { color: var(--accent-green, #4a8a3a); font-size: 0.85rem; margin-top: 8px; }
</style>
