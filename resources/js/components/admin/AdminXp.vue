<template>
  <div>
    <h2 class="page-title">XP &amp; Levels</h2>

    <div v-if="loading" class="loading">Loading...</div>

    <template v-else>
      <!-- Game Settings -->
      <div class="section-panel">
        <h3 class="section-title">Game Settings</h3>
        <p class="section-desc">Default game configuration. Changes save automatically.</p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Setting</th>
              <th>Description</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="name-col">Default Total Rounds</td>
              <td class="desc-col">Number of rounds for standard cooperative games (12 per year)</td>
              <td><input type="number" v-model.number="defaultTotalRounds" class="xp-input" min="12" max="120" step="12" @change="saveDefaultRounds" /></td>
            </tr>
          </tbody>
        </table>
        <p v-if="roundsSaved" class="saved-msg">Saved!</p>
      </div>

      <!-- XP & Coin Sources Table -->
      <div class="section-panel">
        <h3 class="section-title">XP &amp; Coin Award Sources</h3>
        <p class="section-desc">How XP and coins are earned at the end of each game. Changes save automatically.</p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Source</th>
              <th>Description</th>
              <th>XP</th>
              <th>Coins</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="name-col">Base</td>
              <td class="desc-col">Awarded to every player who completes a game</td>
              <td><input type="number" v-model.number="config.base_xp" class="xp-input" min="0" @change="saveConfig" /></td>
              <td><input type="number" v-model.number="coinConfig.base_coins" class="xp-input" min="0" @change="saveCoinConfig" /></td>
            </tr>
            <tr>
              <td class="name-col">Win Bonus (Cooperative)</td>
              <td class="desc-col">Extra reward for winning a cooperative game</td>
              <td><input type="number" v-model.number="config.coop_win_bonus" class="xp-input" min="0" @change="saveConfig" /></td>
              <td><input type="number" v-model.number="coinConfig.coop_win_bonus" class="xp-input" min="0" @change="saveCoinConfig" /></td>
            </tr>
            <tr>
              <td class="name-col">Win Bonus (Duel)</td>
              <td class="desc-col">Extra reward for winning a duel game</td>
              <td><input type="number" v-model.number="config.duel_win_bonus" class="xp-input" min="0" @change="saveConfig" /></td>
              <td><input type="number" v-model.number="coinConfig.duel_win_bonus" class="xp-input" min="0" @change="saveCoinConfig" /></td>
            </tr>
            <tr>
              <td class="name-col">Online Multiplier</td>
              <td class="desc-col">Multiplier applied for online games</td>
              <td><input type="number" v-model.number="config.online_multiplier" class="xp-input" min="1" max="5" step="0.1" @change="saveConfig" /></td>
              <td><input type="number" v-model.number="coinConfig.online_multiplier" class="xp-input" min="1" max="5" step="0.1" @change="saveCoinConfig" /></td>
            </tr>
          </tbody>
        </table>
        <p v-if="saved" class="saved-msg">Saved!</p>

        <!-- Live formula -->
        <h4 class="sub-title">Computed Awards</h4>
        <table class="admin-table compact">
          <thead>
            <tr><th>Scenario</th><th>Offline XP</th><th>Online XP</th><th>Offline Coins</th><th>Online Coins</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Loss (any mode)</td>
              <td>{{ config.base_xp }}</td>
              <td>{{ Math.round(config.base_xp * config.online_multiplier) }}</td>
              <td>{{ coinConfig.base_coins }}</td>
              <td>{{ Math.round(coinConfig.base_coins * coinConfig.online_multiplier) }}</td>
            </tr>
            <tr>
              <td>Cooperative Win</td>
              <td>{{ config.base_xp + config.coop_win_bonus }}</td>
              <td>{{ Math.round((config.base_xp + config.coop_win_bonus) * config.online_multiplier) }}</td>
              <td>{{ coinConfig.base_coins + coinConfig.coop_win_bonus }}</td>
              <td>{{ Math.round((coinConfig.base_coins + coinConfig.coop_win_bonus) * coinConfig.online_multiplier) }}</td>
            </tr>
            <tr>
              <td>Duel Win</td>
              <td>{{ config.base_xp + config.duel_win_bonus }}</td>
              <td>{{ Math.round((config.base_xp + config.duel_win_bonus) * config.online_multiplier) }}</td>
              <td>{{ coinConfig.base_coins + coinConfig.duel_win_bonus }}</td>
              <td>{{ Math.round((coinConfig.base_coins + coinConfig.duel_win_bonus) * coinConfig.online_multiplier) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Other XP Sources -->
      <div class="section-panel">
        <h3 class="section-title">Other XP Sources</h3>
        <table class="admin-table compact">
          <thead>
            <tr><th>Source</th><th>Description</th><th>XP</th></tr>
          </thead>
          <tbody>
            <tr>
              <td class="name-col">Daily Challenge</td>
              <td class="desc-col">Completing the daily challenge awards its configured reward_xp</td>
              <td>Varies per challenge</td>
            </tr>
            <tr>
              <td class="name-col">Achievement Claim</td>
              <td class="desc-col">Claiming a completed achievement awards its reward_xp</td>
              <td>Varies per achievement</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Level Table with unlocks -->
      <div class="section-panel">
        <h3 class="section-title">Level Table</h3>
        <div class="formula-block">
          <code>XP for Level N = 100 &times; N &times; (N + 1) / 2</code>
        </div>

        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Level</th>
                <th>Total XP</th>
                <th>XP to Next</th>
                <th>Unlocks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in levels" :key="l.level" :class="{ 'has-reward': l.rewards.length > 0 }">
                <td class="level-col">{{ l.level }}</td>
                <td>{{ l.total_xp.toLocaleString() }}</td>
                <td>{{ l.xp_to_next.toLocaleString() }}</td>
                <td>
                  <div v-for="r in l.rewards" :key="r.id" class="reward-badge">
                    <span class="reward-type">{{ r.type }}</span>
                    {{ r.entity_name }}
                    <button class="reward-remove" @click="removeUnlock(r.id)" title="Remove">&times;</button>
                  </div>
                  <button class="btn-add-unlock" @click="openAddUnlock(l.level)">+ unlock</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Unlock Modal -->
      <div v-if="showUnlockModal" class="modal-overlay" @click.self="showUnlockModal = false">
        <div class="modal-content">
          <h3>Add Unlock at Level {{ unlockForm.level }}</h3>
          <form @submit.prevent="saveUnlock">
            <div class="form-group">
              <label>Type</label>
              <select v-model="unlockForm.type" @change="unlockForm.entity_id = ''">
                <option value="character">Character</option>
                <option value="item">Item</option>
              </select>
            </div>
            <div class="form-group">
              <label>{{ unlockForm.type === 'character' ? 'Character' : 'Item' }}</label>
              <select v-model.number="unlockForm.entity_id" required>
                <option value="" disabled>Select...</option>
                <option v-for="e in unlockEntityOptions" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
            </div>
            <div v-if="unlockError" class="form-error">{{ unlockError }}</div>
            <div class="modal-actions">
              <button type="submit" class="btn-primary">Add</button>
              <button type="button" @click="showUnlockModal = false">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminXp',
  data() {
    return {
      loading: true,
      saved: false,
      config: {
        base_xp: 50,
        coop_win_bonus: 100,
        duel_win_bonus: 150,
        online_multiplier: 1.5,
      },
      coinConfig: {
        base_coins: 10,
        coop_win_bonus: 15,
        duel_win_bonus: 25,
        online_multiplier: 1.5,
      },
      levels: [],
      characters: [],
      items: [],
      defaultTotalRounds: 60,
      roundsSaved: false,
      showUnlockModal: false,
      unlockForm: { level: 1, type: 'character', entity_id: '' },
      unlockError: '',
    };
  },
  computed: {
    unlockEntityOptions() {
      return this.unlockForm.type === 'character' ? this.characters : this.items;
    },
  },
  async mounted() {
    await Promise.all([this.loadConfig(), this.loadCoinConfig(), this.loadLevels(), this.loadEntities(), this.loadDefaultRounds()]);
    this.loading = false;
  },
  methods: {
    async loadConfig() {
      try {
        const res = await axios.get('/api/admin/rules');
        if (res.data.xp_config) {
          this.config = { ...this.config, ...res.data.xp_config };
        }
      } catch {
        // use defaults
      }
    },
    async loadCoinConfig() {
      try {
        const res = await axios.get('/api/admin/rules');
        if (res.data.coin_config) {
          this.coinConfig = { ...this.coinConfig, ...res.data.coin_config };
        }
      } catch {
        // use defaults
      }
    },
    async saveCoinConfig() {
      try {
        await axios.put('/api/admin/rules/coin_config', { value: { ...this.coinConfig } });
        this.saved = true;
        setTimeout(() => { this.saved = false; }, 1500);
      } catch {
        // silently fail
      }
    },
    async loadLevels() {
      try {
        const res = await axios.get('/api/admin/levels');
        this.levels = res.data;
      } catch {
        // ignore
      }
    },
    async loadEntities() {
      try {
        const res = await axios.get('/api/admin/unlockables');
        this.characters = res.data.characters || [];
        this.items = res.data.items || [];
      } catch {
        // ignore
      }
    },
    async saveConfig() {
      try {
        await axios.put('/api/admin/rules/xp_config', { value: { ...this.config } });
        this.saved = true;
        setTimeout(() => { this.saved = false; }, 1500);
      } catch {
        // silently fail
      }
    },
    async loadDefaultRounds() {
      try {
        const res = await axios.get('/api/admin/rules');
        if (res.data.default_total_rounds != null) {
          this.defaultTotalRounds = res.data.default_total_rounds;
        }
      } catch {
        // use default
      }
    },
    async saveDefaultRounds() {
      try {
        await axios.put('/api/admin/rules/default_total_rounds', { value: this.defaultTotalRounds });
        this.roundsSaved = true;
        setTimeout(() => { this.roundsSaved = false; }, 1500);
      } catch {
        // silently fail
      }
    },
    openAddUnlock(level) {
      this.unlockForm = { level, type: 'character', entity_id: '' };
      this.unlockError = '';
      this.showUnlockModal = true;
    },
    async saveUnlock() {
      this.unlockError = '';
      try {
        await axios.post('/api/admin/unlockables', {
          type: this.unlockForm.type,
          entity_id: this.unlockForm.entity_id,
          unlock_method: 'level',
          unlock_value: this.unlockForm.level,
        });
        this.showUnlockModal = false;
        await this.loadLevels();
      } catch (e) {
        this.unlockError = e.response?.data?.message || 'Error';
      }
    },
    async removeUnlock(id) {
      if (!confirm('Remove this unlock?')) return;
      try {
        await axios.delete(`/api/admin/unlockables/${id}`);
        await this.loadLevels();
      } catch {
        // ignore
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

.xp-input {
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
.xp-input:focus { outline: none; border-color: var(--accent-gold); }

.saved-msg { color: var(--accent-green, #4a8a3a); font-size: 0.85rem; margin-top: 8px; }

.formula-block {
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
  padding: 12px 16px;
  margin-bottom: 14px;
}
.formula-block code { color: var(--accent-gold); font-size: 0.95rem; }

.table-wrap { overflow-x: auto; }

.has-reward { background: rgba(212, 168, 67, 0.04); }
.level-col { font-family: 'Cinzel', serif; color: var(--accent-gold); font-weight: 700; font-size: 1rem; }

.reward-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(138, 106, 46, 0.25);
  border-radius: 4px;
  padding: 3px 8px;
  margin: 2px 4px 2px 0;
  font-size: 0.82rem;
  color: var(--text-bright);
}
.reward-type { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent-gold); opacity: 0.8; }
.reward-remove { background: none; border: none; color: #d05040; cursor: pointer; font-size: 1rem; padding: 0 2px; line-height: 1; opacity: 0.6; }
.reward-remove:hover { opacity: 1; }

.btn-add-unlock {
  background: none;
  border: 1px dashed rgba(138, 106, 46, 0.3);
  color: var(--text-secondary);
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.72rem;
  opacity: 0.5;
  transition: all 0.2s;
}
.btn-add-unlock:hover { opacity: 1; border-color: var(--accent-gold); color: var(--accent-gold); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 420px; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.2rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 5px; }
.form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(184,148,46,0.3); color: var(--text-bright); padding: 8px 12px; border-radius: 4px; font-family: inherit; font-size: 0.95rem; }
.form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
</style>
