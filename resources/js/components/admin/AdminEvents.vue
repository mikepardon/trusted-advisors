<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Events</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input type="file" ref="csvInput" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Event</button>
        <button class="btn-ai" @click="showAiModal = true">Generate with AI</button>
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
      <div v-if="showBalanceStats && eventBalanceStats" class="balance-body">
        <div class="balance-summary">
          <div class="balance-stat-card">
            <span class="balance-stat-label">Total Events</span>
            <span class="balance-stat-value">{{ eventBalanceStats.count }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-pos-label">Positive</span>
            <span class="balance-stat-value">{{ eventBalanceStats.positiveCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-neg-label">Negative</span>
            <span class="balance-stat-value">{{ eventBalanceStats.negativeCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Mixed</span>
            <span class="balance-stat-value">{{ eventBalanceStats.mixedCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Neutral</span>
            <span class="balance-stat-value">{{ eventBalanceStats.neutralCount }}</span>
          </div>
        </div>
        <div class="balance-section-row">
          <div class="balance-section">
            <h4 class="balance-section-title">Mechanic Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, mech) in eventBalanceStats.mechanicDist" :key="mech" class="balance-dist-badge">
                {{ mech.replace(/_/g, ' ') }}: {{ count }}
              </span>
            </div>
          </div>
        </div>
        <table class="admin-table balance-table">
          <thead>
            <tr>
              <th>Stat</th>
              <th>Total Modifier</th>
              <th>Events w/ Stat</th>
              <th>Avg Modifier</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(data, stat) in eventBalanceStats.perStat" :key="stat">
              <td class="name-col" style="text-transform: capitalize;">{{ stat }}</td>
              <td :class="data.total > 0 ? 'balance-pos' : data.total < 0 ? 'balance-neg' : ''">{{ data.total > 0 ? '+' : '' }}{{ data.total }}</td>
              <td>{{ data.count }}</td>
              <td :class="data.avg > 0 ? 'balance-pos' : data.avg < 0 ? 'balance-neg' : ''">{{ data.avg > 0 ? '+' : '' }}{{ data.avg.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AdminSearchInput v-model="searchQuery" />

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="cards-list">
      <div v-for="ev in filteredEvents" :key="ev.id" class="item-card">
        <div class="item-header">
          <h4>{{ ev.title }}</h4>
          <div class="item-actions">
            <button @click="openEdit(ev)">Edit</button>
            <button class="btn-danger" @click="confirmDelete(ev)">Delete</button>
          </div>
        </div>
        <p class="item-desc">{{ ev.effect }}</p>
        <div class="item-meta" v-if="ev.stat_modifiers">
          <strong>Stat Modifiers:</strong>
          <span v-for="(val, stat) in ev.stat_modifiers" :key="stat" class="mod-badge" :class="val > 0 ? 'mod-pos' : 'mod-neg'">
            {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
        <div class="item-meta" v-if="ev.mechanic">
          <strong>Mechanic:</strong>
          <span class="mechanic-badge">{{ mechanicLabel(ev.mechanic) }}</span>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Event' : 'New Event' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Title</label>
            <input v-model="form.title" required />
          </div>
          <div class="form-group">
            <label>Effect Description</label>
            <textarea v-model="form.effect" rows="4" required></textarea>
          </div>

          <div class="form-group">
            <label>Stat Modifiers</label>
            <div class="stat-grid">
              <div v-for="stat in stats" :key="stat.key" class="stat-cell">
                <span class="stat-icon-label" :title="stat.label">{{ stat.icon }}</span>
                <input
                  type="number"
                  :value="form.modifiers[stat.key] || ''"
                  @input="setModifier(stat.key, $event.target.value)"
                  class="stat-input"
                  :placeholder="0"
                />
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Mechanic</label>
            <select v-model="form.mechanic">
              <option :value="null">None</option>
              <option value="stat_modifier">Stat Modifier Only</option>
              <option value="reduce_dice">Reduce Dice</option>
              <option value="grant_items">Grant Items</option>
              <option value="altered_deal">Altered Deal</option>
              <option value="score_event">Score Event</option>
            </select>
          </div>

          <div v-if="form.mechanic === 'reduce_dice'" class="form-group">
            <label>Dice to Remove</label>
            <input type="number" v-model.number="form.mechanic_data.amount" min="1" max="5" placeholder="1" />
          </div>

          <div v-if="form.mechanic === 'grant_items'" class="form-group mechanic-checkbox">
            <label>
              <input type="checkbox" v-model="form.mechanic_data.random" />
              Grant random item to each advisor
            </label>
          </div>

          <div v-if="form.mechanic === 'altered_deal'" class="form-group">
            <label>Positive Cards</label>
            <input type="number" v-model.number="form.mechanic_data.positive_cards" min="0" max="10" placeholder="2" />
            <label class="mt-label">Negative Cards</label>
            <input type="number" v-model.number="form.mechanic_data.negative_cards" min="0" max="10" placeholder="2" />
          </div>

          <div v-if="form.mechanic === 'score_event'" class="form-group">
            <label>Score Per Round</label>
            <input type="number" v-model.number="form.mechanic_data.score_per_round" placeholder="5" />
          </div>

          <div class="form-group">
            <label style="color: var(--accent-gold); font-weight: 600;">Availability</label>
            <div style="display: flex; gap: 16px; margin-top: 4px;">
              <label><input type="checkbox" v-model="form.available_cooperative" /> Co-op</label>
              <label><input type="checkbox" v-model="form.available_duel" /> Duel</label>
            </div>
          </div>

          <div class="form-group">
            <label>Addon</label>
            <select v-model="form.addon_id">
              <option :value="null">Base Game</option>
              <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
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
    <!-- AI Generate Modal -->
    <div v-if="showAiModal" class="modal-overlay" @click.self="showAiModal = false">
      <div class="modal-content">
        <h3>Generate Event with AI</h3>
        <div class="form-group">
          <label>Prompt (optional — describe the event you want)</label>
          <textarea v-model="aiPrompt" rows="3" placeholder="e.g. A plague that only affects left-handed people"></textarea>
        </div>
        <div v-if="aiError" class="form-error">{{ aiError }}</div>
        <div class="modal-actions">
          <button class="btn-primary" :disabled="aiGenerating" @click="generateWithAi">
            {{ aiGenerating ? 'Generating...' : 'Generate' }}
          </button>
          <button type="button" @click="showAiModal = false">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import AdminSearchInput from './AdminSearchInput.vue';

export default {
  name: 'AdminEvents',
  components: { AdminSearchInput },
  data() {
    return {
      events: [],
      addons: [],
      loading: true,
      searchQuery: '',
      showModal: false,
      editing: null,
      saving: false,
      formError: '',
      showAiModal: false,
      aiPrompt: '',
      aiGenerating: false,
      aiError: '',
      importResult: null,
      showBalanceStats: false,
      stats: [
        { key: 'wealth', label: 'Wealth', icon: '\u{1FA99}' },
        { key: 'influence', label: 'Influence', icon: '\u{1F3DB}' },
        { key: 'security', label: 'Security', icon: '\u{1F6E1}' },
        { key: 'religion', label: 'Religion', icon: '\u{1F54C}' },
        { key: 'food', label: 'Food', icon: '\u{1F33E}' },
        { key: 'happiness', label: 'Happiness', icon: '\u{1F3AD}' },
      ],
      form: { title: '', effect: '', modifiers: {}, addon_id: null, mechanic: null, mechanic_data: {}, available_cooperative: true, available_duel: true },
    };
  },
  computed: {
    filteredEvents() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.events;
      return this.events.filter(ev =>
        (ev.title || '').toLowerCase().includes(q) ||
        (ev.effect || '').toLowerCase().includes(q)
      );
    },
    eventBalanceStats() {
      if (!this.events.length) return null;
      const statKeys = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

      // Per-stat modifier totals and averages
      const perStat = {};
      statKeys.forEach(key => {
        let total = 0;
        let count = 0;
        this.events.forEach(ev => {
          if (ev.stat_modifiers && ev.stat_modifiers[key] !== undefined) {
            total += ev.stat_modifiers[key];
            count++;
          }
        });
        perStat[key] = { total, count, avg: count > 0 ? total / count : 0 };
      });

      // Mechanic distribution
      const mechanicDist = {};
      this.events.forEach(ev => {
        const m = ev.mechanic || 'none';
        mechanicDist[m] = (mechanicDist[m] || 0) + 1;
      });

      // Positive vs negative events
      let positiveCount = 0, negativeCount = 0, mixedCount = 0, neutralCount = 0;
      this.events.forEach(ev => {
        if (!ev.stat_modifiers || Object.keys(ev.stat_modifiers).length === 0) {
          neutralCount++;
          return;
        }
        const values = Object.values(ev.stat_modifiers);
        const hasPos = values.some(v => v > 0);
        const hasNeg = values.some(v => v < 0);
        if (hasPos && hasNeg) mixedCount++;
        else if (hasPos) positiveCount++;
        else if (hasNeg) negativeCount++;
        else neutralCount++;
      });

      return {
        count: this.events.length,
        perStat,
        mechanicDist,
        positiveCount,
        negativeCount,
        mixedCount,
        neutralCount,
      };
    },
  },
  async mounted() {
    await Promise.all([this.fetch(), this.fetchAddons()]);
  },
  methods: {
    async fetch() {
      this.loading = true;
      const res = await axios.get('/api/admin/events');
      this.events = res.data;
      this.loading = false;
    },
    async fetchAddons() {
      try {
        const res = await axios.get('/api/admin/addons');
        this.addons = res.data;
      } catch { /* ignore */ }
    },
    mechanicLabel(mechanic) {
      const labels = {
        stat_modifier: 'Stat Modifier Only',
        reduce_dice: 'Reduce Dice',
        grant_items: 'Grant Items',
        altered_deal: 'Altered Deal',
        score_event: 'Score Event',
      };
      return labels[mechanic] || mechanic;
    },
    statIcon(stat) {
      const s = this.stats.find(s => s.key === stat);
      return s ? s.icon : '';
    },
    setModifier(key, value) {
      const num = value === '' ? null : parseInt(value);
      if (num === null || num === 0 || isNaN(num)) {
        delete this.form.modifiers[key];
      } else {
        this.form.modifiers[key] = num;
      }
    },
    openCreate() {
      this.editing = null;
      this.form = { title: '', effect: '', modifiers: {}, addon_id: null, mechanic: null, mechanic_data: {}, available_cooperative: true, available_duel: true };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(ev) {
      this.editing = ev;
      this.form = {
        title: ev.title,
        effect: ev.effect,
        modifiers: { ...(ev.stat_modifiers || {}) },
        addon_id: ev.addon_id || null,
        mechanic: ev.mechanic || null,
        mechanic_data: { ...(ev.mechanic_data || {}) },
        available_cooperative: ev.available_cooperative ?? true,
        available_duel: ev.available_duel ?? true,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';

      const stat_modifiers = Object.keys(this.form.modifiers).length > 0
        ? { ...this.form.modifiers }
        : null;

      const mechanic = this.form.mechanic || null;
      const mechanic_data = mechanic && Object.keys(this.form.mechanic_data).length > 0
        ? { ...this.form.mechanic_data }
        : null;

      const payload = {
        title: this.form.title,
        effect: this.form.effect,
        stat_modifiers,
        addon_id: this.form.addon_id || null,
        mechanic,
        mechanic_data,
        available_cooperative: this.form.available_cooperative,
        available_duel: this.form.available_duel,
      };

      this.saving = true;
      try {
        if (this.editing) {
          await axios.put(`/api/admin/events/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/events', payload);
        }
        this.showModal = false;
        await this.fetch();
      } catch (e) {
        this.formError = e.response?.data?.message || 'Save failed';
      }
      this.saving = false;
    },
    async generateWithAi() {
      this.aiError = '';
      this.aiGenerating = true;
      try {
        const res = await axios.post('/api/admin/ai/generate-event', {
          prompt: this.aiPrompt || undefined,
        });
        const data = res.data;
        this.showAiModal = false;
        this.aiPrompt = '';
        // Open create modal pre-filled with AI data
        this.editing = null;
        this.form = {
          title: data.title || '',
          effect: data.effect || '',
          modifiers: { ...(data.stat_modifiers || {}) },
          addon_id: null,
        };
        this.formError = '';
        this.showModal = true;
      } catch (e) {
        this.aiError = e.response?.data?.message || e.response?.data?.error || 'AI generation failed';
      }
      this.aiGenerating = false;
    },
    exportCsv() {
      window.location.href = '/api/admin/events/export-csv';
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
        const res = await axios.post('/api/admin/events/import-csv', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.importResult = res.data;
        await this.fetch();
      } catch (e) {
        this.importResult = { created: 0, updated: 0, errors: [e.response?.data?.message || 'Import failed'] };
      }
      event.target.value = '';
    },
    async confirmDelete(ev) {
      if (!confirm(`Delete event "${ev.title}"?`)) return;
      try {
        await axios.delete(`/api/admin/events/${ev.id}`);
        await this.fetch();
      } catch (e) {
        alert('Delete failed: ' + (e.response?.data?.message || e.message));
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

.header-buttons {
  display: flex;
  gap: 8px;
}

.btn-ai {
  background: rgba(100, 60, 180, 0.2);
  color: #b080e0;
  border: 1px solid rgba(100, 60, 180, 0.4);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-ai:hover {
  background: rgba(100, 60, 180, 0.35);
  border-color: rgba(140, 90, 210, 0.6);
}

.btn-ai:disabled {
  opacity: 0.5;
  cursor: default;
}

.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.cards-list { display: flex; flex-direction: column; gap: 12px; }

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
  font-size: 1.1rem;
}

.item-actions { display: flex; gap: 6px; }
.item-actions button { padding: 5px 12px; font-size: 0.8rem; }

.item-desc { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 8px; }

.item-meta { font-size: 0.85rem; color: var(--text-secondary); }
.item-meta strong { color: var(--text-primary); }

.mod-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: 6px;
  font-size: 0.8rem;
  text-transform: capitalize;
}

.mod-pos { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
.mod-neg { background: rgba(192, 57, 43, 0.2); color: #c0392b; }

.mechanic-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: 6px;
  font-size: 0.8rem;
  background: rgba(100, 60, 180, 0.2);
  color: #b080e0;
}

.mechanic-checkbox label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-bright);
  font-size: 0.9rem;
  cursor: pointer;
}

.mechanic-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--accent-gold);
}

.mt-label { margin-top: 8px; }

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
.form-group input:not([type="checkbox"]):not([type="number"]), .form-group textarea, .form-group select {
  width: 100%; background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright); padding: 8px 12px;
  border-radius: 4px; font-family: inherit; font-size: 0.95rem;
}
.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-gold); }

/* Stat grid */
.stat-grid {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.stat-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(138, 106, 46, 0.15);
  border-radius: 6px;
  padding: 6px 8px;
}

.stat-icon-label {
  font-size: 1.2rem;
  line-height: 1;
  cursor: help;
}

.stat-input {
  width: 52px;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright);
  padding: 4px 6px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.9rem;
  text-align: center;
}

.stat-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.form-error { color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }

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
  border: 1px solid var(--border-gold);
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

.balance-table {
  font-size: 0.85rem;
}

.name-col {
  color: var(--text-bright);
  font-weight: 600;
}
</style>
