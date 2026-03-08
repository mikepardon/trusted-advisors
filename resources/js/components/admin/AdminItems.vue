<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Items</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input type="file" ref="csvInput" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Item</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = null">Dismiss</button>
    </div>

    <!-- Balance Stats Panel -->
    <div class="balance-panel">
      <div class="balance-header" @click="showBalanceStats = !showBalanceStats">
        <h3 class="balance-title">Balance Stats</h3>
        <button type="button" class="balance-toggle">{{ showBalanceStats ? 'Hide' : 'Show' }}</button>
      </div>
      <div v-if="showBalanceStats && itemBalanceStats" class="balance-body">
        <div class="balance-summary">
          <div class="balance-stat-card">
            <span class="balance-stat-label">Total Items</span>
            <span class="balance-stat-value">{{ itemBalanceStats.count }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-pos-label">Positive</span>
            <span class="balance-stat-value">{{ itemBalanceStats.positiveCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-neg-label">Negative</span>
            <span class="balance-stat-value">{{ itemBalanceStats.negativeCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Consumable</span>
            <span class="balance-stat-value">{{ itemBalanceStats.consumableCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Permanent</span>
            <span class="balance-stat-value">{{ itemBalanceStats.permanentCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg + Bonus</span>
            <span class="balance-stat-value balance-pos">{{ itemBalanceStats.avgPosBonus.toFixed(2) }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg - Bonus</span>
            <span class="balance-stat-value balance-neg">{{ itemBalanceStats.avgNegBonus.toFixed(2) }}</span>
          </div>
        </div>
        <div class="balance-section-row">
          <div class="balance-section">
            <h4 class="balance-section-title">Effect Type Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, type) in itemBalanceStats.effectTypeDist" :key="type" class="balance-dist-badge">
                {{ type }}: {{ count }}
              </span>
            </div>
          </div>
          <div class="balance-section">
            <h4 class="balance-section-title">Bonus Type Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, type) in itemBalanceStats.bonusTypeDist" :key="type" class="balance-dist-badge">
                {{ type.replace(/_/g, ' ') }}: {{ count }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <AdminSearchInput v-model="searchQuery" />

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="cards-list">
      <div v-for="item in filteredItems" :key="item.id" class="item-card">
        <div class="item-header">
          <h4>{{ item.name }}</h4>
          <div class="item-actions">
            <button @click="openEdit(item)">Edit</button>
            <button class="btn-danger" @click="confirmDelete(item)">Delete</button>
          </div>
        </div>
        <p class="item-desc">{{ item.description }}</p>
        <div class="item-meta">
          <span class="item-type">{{ item.effect_type }}</span>
          <span v-if="item.is_negative" class="item-tag tag-neg">Negative</span>
          <span v-if="item.is_consumable" class="item-tag tag-con">Consumable</span>
        </div>
        <div class="item-effect" :class="item.is_negative ? 'effect-neg' : ''">
          {{ item.effect?.bonus_value > 0 ? '+' : '' }}{{ item.effect?.bonus_value }} {{ item.effect?.bonus_type || 'roll_bonus' }}
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Item' : 'New Item' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label>Effect Type</label>
            <select v-model="form.effect_type" required>
              <option value="passive">Passive</option>
              <option value="active">Active</option>
              <option value="immediate">Immediate</option>
            </select>
          </div>
          <div class="form-group">
            <label>Bonus Type</label>
            <select v-model="form.bonus_type" required>
              <option value="roll_bonus">Roll Bonus (+)</option>
              <option value="roll_penalty">Roll Penalty (-)</option>
              <option value="difficulty_reduction">Difficulty Reduction (-)</option>
              <option value="difficulty_increase">Difficulty Increase (+)</option>
              <option value="reroll">Reroll</option>
              <option value="stat_boost">Stat Boost (Immediate)</option>
              <option value="heal_die">Heal Die (Immediate)</option>
              <option value="score_bonus">Score Bonus (Immediate)</option>
              <option value="end_game_multiplier">End-Game Modifier % (Immediate)</option>
              <option value="score_per_round">Score Per Round (Passive)</option>
              <option value="score_multiplier">Score Multiplier (Passive)</option>
            </select>
          </div>
          <div v-if="form.bonus_type === 'stat_boost'" class="form-group">
            <label>Target Stat</label>
            <select v-model="form.stat" required>
              <option value="wealth">Wealth</option>
              <option value="influence">Influence</option>
              <option value="security">Security</option>
              <option value="religion">Religion</option>
              <option value="food">Food</option>
              <option value="happiness">Happiness</option>
            </select>
          </div>
          <div class="form-group">
            <label>Bonus Value</label>
            <input v-model.number="form.bonus_value" type="number" required />
          </div>
          <div class="form-group">
            <label>
              <input type="checkbox" v-model="form.is_negative" />
              Is Negative
            </label>
          </div>
          <div class="form-group">
            <label>
              <input type="checkbox" v-model="form.is_consumable" />
              Is Consumable
            </label>
          </div>

          <div class="form-group">
            <label>Addon</label>
            <select v-model="form.addon_id">
              <option :value="null">Base Game</option>
              <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <!-- Duel Effect Override -->
          <div style="border: 1px solid rgba(138, 58, 185, 0.3); background: rgba(138, 58, 185, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
              <input type="checkbox" v-model="form.useDuelEffect" />
              <span style="color: #c890e0; font-weight: 700;">Use different effect for Duel mode</span>
            </label>
            <template v-if="form.useDuelEffect">
              <div class="form-group">
                <label>Duel Bonus Type</label>
                <select v-model="form.bonus_type_duel">
                  <option value="roll_bonus">Roll Bonus (+)</option>
                  <option value="roll_penalty">Roll Penalty (-)</option>
                  <option value="difficulty_reduction">Difficulty Reduction (-)</option>
                  <option value="difficulty_increase">Difficulty Increase (+)</option>
                  <option value="reroll">Reroll</option>
                  <option value="stat_boost">Stat Boost (Immediate)</option>
                  <option value="heal_die">Heal Die (Immediate)</option>
                  <option value="score_bonus">Score Bonus (Immediate)</option>
                  <option value="end_game_multiplier">End-Game Modifier % (Immediate)</option>
                </select>
              </div>
              <div v-if="form.bonus_type_duel === 'stat_boost'" class="form-group">
                <label>Duel Target Stat</label>
                <select v-model="form.stat_duel">
                  <option value="wealth">Wealth</option>
                  <option value="influence">Influence</option>
                  <option value="security">Security</option>
                  <option value="religion">Religion</option>
                  <option value="food">Food</option>
                  <option value="happiness">Happiness</option>
                </select>
              </div>
              <div class="form-group">
                <label>Duel Bonus Value</label>
                <input v-model.number="form.bonus_value_duel" type="number" />
              </div>
            </template>
          </div>

          <div class="form-group">
            <label style="color: var(--accent-gold); font-weight: 600;">Availability</label>
            <div style="display: flex; gap: 16px; margin-top: 4px;">
              <label><input type="checkbox" v-model="form.available_cooperative" /> Co-op</label>
              <label><input type="checkbox" v-model="form.available_duel" /> Duel</label>
            </div>
          </div>

          <div v-if="formError" class="form-error">{{ formError }}</div>

          <div class="modal-actions">
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
            <button type="button" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';
import AdminSearchInput from './AdminSearchInput.vue';

export default {
  name: 'AdminItems',
  components: { AdminSearchInput },
  setup() { return { toast: useToast() }; },
  data() {
    return {
      items: [],
      addons: [],
      loading: true,
      searchQuery: '',
      showModal: false,
      editing: null,
      saving: false,
      formError: '',
      importResult: null,
      showBalanceStats: false,
      form: {
        name: '',
        description: '',
        effect_type: 'passive',
        bonus_type: 'roll_bonus',
        bonus_value: 1,
        stat: 'food',
        is_negative: false,
        is_consumable: false,
        addon_id: null,
        available_cooperative: true,
        available_duel: true,
        useDuelEffect: false,
        bonus_type_duel: 'roll_bonus',
        bonus_value_duel: 1,
        stat_duel: 'food',
      },
    };
  },
  computed: {
    filteredItems() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.items;
      return this.items.filter(item =>
        (item.name || '').toLowerCase().includes(q) ||
        (item.description || '').toLowerCase().includes(q) ||
        (item.effect_type || '').toLowerCase().includes(q)
      );
    },
    itemBalanceStats() {
      if (!this.items.length) return null;

      // Positive vs negative count
      const positiveCount = this.items.filter(i => !i.is_negative).length;
      const negativeCount = this.items.filter(i => i.is_negative).length;

      // Consumable vs permanent
      const consumableCount = this.items.filter(i => i.is_consumable).length;
      const permanentCount = this.items.filter(i => !i.is_consumable).length;

      // Avg bonus value (separate for positive and negative items)
      const posItems = this.items.filter(i => !i.is_negative && i.effect?.bonus_value != null);
      const negItems = this.items.filter(i => i.is_negative && i.effect?.bonus_value != null);
      const avgPosBonus = posItems.length ? posItems.reduce((s, i) => s + Math.abs(i.effect.bonus_value), 0) / posItems.length : 0;
      const avgNegBonus = negItems.length ? negItems.reduce((s, i) => s + Math.abs(i.effect.bonus_value), 0) / negItems.length : 0;

      // Bonus type distribution
      const bonusTypeDist = {};
      this.items.forEach(i => {
        const bt = i.effect?.bonus_type || 'unknown';
        bonusTypeDist[bt] = (bonusTypeDist[bt] || 0) + 1;
      });

      // Effect type distribution
      const effectTypeDist = {};
      this.items.forEach(i => {
        const et = i.effect_type || 'unknown';
        effectTypeDist[et] = (effectTypeDist[et] || 0) + 1;
      });

      return {
        count: this.items.length,
        positiveCount,
        negativeCount,
        consumableCount,
        permanentCount,
        avgPosBonus,
        avgNegBonus,
        bonusTypeDist,
        effectTypeDist,
      };
    },
  },
  async mounted() {
    await Promise.all([this.fetch(), this.fetchAddons()]);
  },
  methods: {
    async fetch() {
      this.loading = true;
      const res = await axios.get('/api/admin/items');
      this.items = res.data;
      this.loading = false;
    },
    async fetchAddons() {
      try {
        const res = await axios.get('/api/admin/addons');
        this.addons = res.data;
      } catch { /* ignore */ }
    },
    openCreate() {
      this.editing = null;
      this.form = {
        name: '',
        description: '',
        effect_type: 'passive',
        bonus_type: 'roll_bonus',
        bonus_value: 1,
        stat: 'food',
        is_negative: false,
        is_consumable: false,
        addon_id: null,
        available_cooperative: true,
        available_duel: true,
        useDuelEffect: false,
        bonus_type_duel: 'roll_bonus',
        bonus_value_duel: 1,
        stat_duel: 'food',
      };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(item) {
      this.editing = item;
      const hasDuelEffect = item.effect_duel != null;
      this.form = {
        name: item.name,
        description: item.description,
        effect_type: item.effect_type || 'passive',
        bonus_type: item.effect?.bonus_type || 'roll_bonus',
        bonus_value: item.effect?.bonus_value || 1,
        stat: item.effect?.stat || 'food',
        is_negative: item.is_negative || false,
        is_consumable: item.is_consumable || false,
        addon_id: item.addon_id || null,
        available_cooperative: item.available_cooperative ?? true,
        available_duel: item.available_duel ?? true,
        useDuelEffect: hasDuelEffect,
        bonus_type_duel: item.effect_duel?.bonus_type || item.effect?.bonus_type || 'roll_bonus',
        bonus_value_duel: item.effect_duel?.bonus_value ?? item.effect?.bonus_value ?? 1,
        stat_duel: item.effect_duel?.stat || item.effect?.stat || 'food',
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
      const payload = {
        name: this.form.name,
        description: this.form.description,
        effect_type: this.form.effect_type,
        is_negative: this.form.is_negative,
        is_consumable: this.form.is_consumable,
        addon_id: this.form.addon_id || null,
        available_cooperative: this.form.available_cooperative,
        available_duel: this.form.available_duel,
        effect: {
          bonus_type: this.form.bonus_type,
          bonus_value: this.form.bonus_value,
          ...(this.form.bonus_type === 'stat_boost' ? { stat: this.form.stat } : {}),
        },
        effect_duel: this.form.useDuelEffect ? {
          bonus_type: this.form.bonus_type_duel,
          bonus_value: this.form.bonus_value_duel,
          ...(this.form.bonus_type_duel === 'stat_boost' ? { stat: this.form.stat_duel } : {}),
        } : null,
      };

      this.saving = true;
      try {
        if (this.editing) {
          await axios.put(`/api/admin/items/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/items', payload);
        }
        this.showModal = false;
        await this.fetch();
      } catch (e) {
        this.formError = e.response?.data?.message || 'Save failed';
      }
      this.saving = false;
    },
    exportCsv() {
      window.location.href = '/api/admin/items/export-csv';
    },
    triggerImport() {
      this.$refs.csvInput.click();
    },
    async handleImportFile(event) {
      const file = event.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('file', file);
      try {
        const res = await axios.post('/api/admin/items/import-csv', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.importResult = res.data;
        await this.fetch();
      } catch (e) {
        this.importResult = { created: 0, updated: 0, errors: [e.response?.data?.message || 'Import failed'] };
      }
      event.target.value = '';
    },
    async confirmDelete(item) {
      if (!confirm(`Delete item "${item.name}"?`)) return;
      try {
        await axios.delete(`/api/admin/items/${item.id}`);
        await this.fetch();
      } catch (e) {
        this.toast.error('Delete failed: ' + (e.response?.data?.message || e.message));
      }
    },
  },
};
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
}

.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.cards-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
}

.item-card {
  background: var(--bg-secondary);
  border: 1px solid rgba(184, 148, 46, 0.2);
  border-radius: 8px;
  padding: 16px;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.item-header h4 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
}

.item-actions { display: flex; gap: 6px; }
.item-actions button { padding: 5px 12px; font-size: 0.8rem; }

.item-desc {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.4;
  margin-bottom: 8px;
}

.item-meta {
  display: flex;
  gap: 8px;
  margin-bottom: 6px;
}

.item-type {
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-transform: capitalize;
}

.item-tag {
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.tag-neg { background: rgba(192, 57, 43, 0.2); color: #c0392b; }
.tag-con { background: rgba(212, 168, 67, 0.2); color: #d4a843; }

.item-effect {
  color: var(--accent-green);
  font-weight: 700;
  font-size: 0.9rem;
  text-transform: capitalize;
}

.item-effect.effect-neg {
  color: #c0392b;
}

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex; align-items: center; justify-content: center;
  z-index: 200;
}

.modal-content {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 28px;
  width: 90%; max-width: 550px;
  max-height: 85vh; overflow-y: auto;
}

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px; font-size: 1.3rem;
}

.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 5px; }
.form-group input:not([type="checkbox"]), .form-group textarea, .form-group select {
  width: 100%; background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright); padding: 8px 12px;
  border-radius: 4px; font-family: inherit; font-size: 0.95rem;
}
.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }

.header-buttons {
  display: flex;
  gap: 8px;
}

.btn-csv {
  background: rgba(40, 120, 80, 0.2);
  color: #5ab87a;
  border: 1px solid rgba(40, 120, 80, 0.4);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-csv:hover {
  background: rgba(40, 120, 80, 0.35);
  border-color: rgba(60, 160, 100, 0.6);
}

.import-result {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 0.9rem;
  position: relative;
}

.import-ok {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
}

.import-warn {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.4);
  color: #d4a843;
}

.import-error-line {
  font-size: 0.8rem;
  color: #d05040;
  margin-top: 4px;
}

.import-dismiss {
  position: absolute;
  top: 8px;
  right: 8px;
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 0.8rem;
  padding: 2px 8px;
}

/* Balance Stats Panel */
.balance-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 18px 22px;
  margin-bottom: 24px;
}

.balance-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.balance-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

.balance-toggle {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  border: 1px solid rgba(138, 106, 46, 0.3);
  padding: 4px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  letter-spacing: 1px;
}

.balance-toggle:hover {
  background: rgba(212, 168, 67, 0.25);
}

.balance-body {
  margin-top: 14px;
}

.balance-summary {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.balance-stat-card {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.balance-stat-label {
  color: var(--text-secondary);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.balance-stat-value {
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 0.95rem;
}

.balance-pos-label { color: #27ae60; }
.balance-neg-label { color: #c0392b; }
.balance-pos { color: #27ae60; }
.balance-neg { color: #c0392b; }

.balance-section-row {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.balance-section {
  flex: 1;
  min-width: 200px;
}

.balance-section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.balance-dist-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.balance-dist-badge {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 0.8rem;
  color: var(--accent-gold);
  text-transform: capitalize;
}
</style>
